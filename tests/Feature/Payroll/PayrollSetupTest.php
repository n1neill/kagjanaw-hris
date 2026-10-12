<?php

use App\Enums\AllowanceCoverage;
use App\Enums\ContributionType;
use App\Models\AllowanceType;
use App\Models\DeductionRate;
use App\Models\Employee;
use App\Models\EmployeeAllowance;
use App\Models\EmployeeDeduction;
use App\Models\PayrollItem;
use App\Models\SalaryGrade;
use App\Models\TaxBracket;
use App\Models\User;
use App\Services\Payroll\PayrollSettings;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00:00'));
});

/**
 * @return array<string, mixed>
 */
function payrollSettingsPayload(array $overrides = []): array
{
    return [
        'income_class' => 4,
        'income_class_confirmed' => '1',
        'percentages' => [1 => '90', 2 => '85', 3 => '80', 4 => '75', 5 => '70'],
        'minimum_net_pay' => '5000',
        'daily_rate_tax_rate' => '3',
        'attendance_deductions_from' => '2026-09',
        ...$overrides,
    ];
}

it('renders the payroll pages for HR', function (Closure $url, string $expected) {
    $hrOfficer = User::factory()->hrOfficer()->create();
    computePayroll(2026, 10, 1, hrOfficer: $hrOfficer);

    $this->actingAs($hrOfficer)->get($url())->assertOk()->assertSee($expected);
})->with([
    'payroll runs' => [fn () => route('payroll.runs.index'), 'October 1–15, 2026'],
    'allowances' => [fn () => route('payroll.allowances.index'), 'Personnel Economic Relief Allowance (PERA)'],
    'new allowance' => [fn () => route('payroll.allowances.create'), 'Reduced for days without pay'],
    'edit allowance' => [fn () => route('payroll.allowances.edit', AllowanceType::query()->where('code', 'PERA')->sole()), '2000.00'],
    'contribution and tax rates' => [fn () => route('payroll.rates.index'), 'RA 11223 (UHC Act)'],
    'new contribution rate' => [fn () => route('payroll.rates.contributions.create'), 'Employee share'],
    'edit contribution rate' => [fn () => route('payroll.rates.contributions.edit', DeductionRate::query()->where('type', ContributionType::Gsis)->sole()), '12.00'],
    'edit tax table' => [fn () => route('payroll.rates.tax.edit', '2023-01-01'), '8541.80'],
    'edit salary schedule' => [fn () => route('payroll.salary-schedule.edit', '2026-01-01'), '462329.00'],
    'edit deduction' => [fn () => route('payroll.employees.deductions.edit', [($deduction = EmployeeDeduction::factory()->create(['name' => 'Union Dues']))->employee, $deduction]), 'Union Dues'],
]);

describe('payroll settings', function () {
    it('saves the income class and the payroll rules', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $response = $this->actingAs($hrOfficer)->put(route('payroll.settings.update'), payrollSettingsPayload());

        $response->assertRedirect(route('payroll.settings.edit'));
        $settings = app(PayrollSettings::class);
        expect($settings->incomeClass())->toBe(4)
            ->and($settings->isIncomeClassConfirmed())->toBeTrue()
            ->and($settings->salaryPercentage())->toBe(7500)
            ->and($settings->dailyRateTaxRate())->toBe(300)
            ->and($settings->attendanceDeductionsFrom()->toDateString())->toBe('2026-09-01');
    });

    it('rejects a percentage over 100', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $response = $this->actingAs($hrOfficer)->put(route('payroll.settings.update'), payrollSettingsPayload(['percentages' => [1 => '120', 2 => '85', 3 => '80', 4 => '75', 5 => '70']]));

        $response->assertSessionHasErrors(['percentages.1' => 'The percentage field must not be greater than 100.']);
        expect(app(PayrollSettings::class)->incomeClass())->toBe(PayrollSettings::DEFAULT_INCOME_CLASS);
    });

    it('warns until the income class is confirmed', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $this->actingAs($hrOfficer)->get(route('payroll.settings.edit'))
            ->assertSee('The income class has not been confirmed. Payroll uses 5th class until it is');
    });

    it('is only for HR', function () {
        $admin = User::factory()->systemAdministrator()->create();

        $this->actingAs($admin)->put(route('payroll.settings.update'), payrollSettingsPayload())->assertForbidden();
    });
});

describe('salary schedule', function () {
    it('shows the national and LGU rates', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        // SG 1 Step 1: ₱14,634 nationally; 70% = ₱10,243.80 → ₱10,244.00.
        $this->actingAs($hrOfficer)->get(route('payroll.salary-schedule.index'))
            ->assertSee('14,634.00')
            ->assertSee('10,244.00')
            ->assertSee('EO No. 64, s. 2024, third tranche');
    });

    it('adds a tranche as a copy of the schedule in force', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $response = $this->actingAs($hrOfficer)->post(route('payroll.salary-schedule.store'), ['effective_from' => '2027-01-01']);

        $response->assertRedirect(route('payroll.salary-schedule.edit', '2027-01-01'));
        expect(SalaryGrade::query()->whereDate('effective_from', '2027-01-01')->count())->toBe(258)
            ->and(SalaryGrade::query()->whereDate('effective_from', '2027-01-01')->where('grade', 11)->where('step', 1)->value('amount'))->toBe('31705.00');
    });

    it('pays the new amounts from the tranche\'s effectivity date', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $this->actingAs($hrOfficer)->post(route('payroll.salary-schedule.store'), ['effective_from' => '2027-01-01']);
        $amounts = SalaryGrade::query()->whereDate('effective_from', '2027-01-01')->get()
            ->groupBy('grade')
            ->map(fn ($cells) => $cells->mapWithKeys(fn ($cell) => [$cell->step => $cell->amount])->all())
            ->all();
        $amounts[11][1] = '33000';
        $employee = Employee::factory()->create(['salary_grade' => 11, 'salary_step' => 1, 'date_hired' => '2020-01-06']);

        $this->actingAs($hrOfficer)->put(route('payroll.salary-schedule.update', '2027-01-01'), ['legal_basis' => 'EO No. 64, fourth tranche', 'amounts' => $amounts]);
        computePayroll(2026, 12, 2, hrOfficer: $hrOfficer);
        computePayroll(2027, 1, 1, hrOfficer: $hrOfficer);

        // December: ₱22,194 (₱31,705 × 70%). January: ₱33,000 × 70% = ₱23,100.
        expect(PayrollItem::query()->where('employee_id', $employee->id)->orderBy('id')->pluck('monthly_rate')->all())->toBe(['22194.00', '23100.00']);
    });

    it('refuses a second tranche on the same date', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $response = $this->actingAs($hrOfficer)->post(route('payroll.salary-schedule.store'), ['effective_from' => '2026-01-01']);

        $response->assertSessionHasErrors(['effective_from' => 'A tranche effective on that date already exists.']);
    });
});

describe('contribution rates and tax table', function () {
    it('applies a new contribution rate only from its effectivity date', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create(['salary_grade' => 11, 'salary_step' => 1, 'date_hired' => '2020-01-06']);

        $this->actingAs($hrOfficer)->post(route('payroll.rates.contributions.store'), [
            'type' => 'gsis', 'effective_from' => '2027-01-01', 'employee_rate' => '10', 'employer_rate' => '12',
        ])->assertRedirect(route('payroll.rates.index'));
        computePayroll(2026, 12, 2, hrOfficer: $hrOfficer);
        computePayroll(2027, 1, 1, hrOfficer: $hrOfficer);

        // 9% then 10% of ₱22,194, halved: ₱998.73 (first half rounds down) then ₱1,109.70.
        expect(PayrollItem::query()->where('employee_id', $employee->id)->orderBy('id')->pluck('gsis')->all())->toBe(['998.73', '1109.70']);
    });

    it('refuses two rates of a contribution on the same date', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $response = $this->actingAs($hrOfficer)->post(route('payroll.rates.contributions.store'), [
            'type' => 'philhealth', 'effective_from' => '2024-01-01', 'employee_rate' => '2.5', 'employer_rate' => '2.5',
        ]);

        $response->assertSessionHasErrors(['effective_from' => 'This contribution already has a rate effective on that date.']);
        expect(DeductionRate::query()->where('type', ContributionType::PhilHealth)->count())->toBe(1);
    });

    it('replaces the brackets of a tax table', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $response = $this->actingAs($hrOfficer)->put(route('payroll.rates.tax.update', '2023-01-01'), [
            'legal_basis' => 'Test table',
            'brackets' => [
                ['over' => '0', 'base_tax' => '0', 'rate' => '0'],
                ['over' => '25000', 'base_tax' => '0', 'rate' => '10'],
                ['over' => '', 'base_tax' => '', 'rate' => ''],
            ],
        ]);

        $response->assertRedirect(route('payroll.rates.index'));
        expect(TaxBracket::query()->whereDate('effective_from', '2023-01-01')->orderBy('over')->pluck('rate', 'over')->all())
            ->toBe(['0.00' => '0.00', '25000.00' => '10.00']);
    });

    it('requires the first tax bracket to start at zero', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $response = $this->actingAs($hrOfficer)->put(route('payroll.rates.tax.update', '2023-01-01'), [
            'brackets' => [['over' => '20833', 'base_tax' => '0', 'rate' => '15']],
        ]);

        $response->assertSessionHasErrors(['brackets' => 'The first bracket must start at 0.00.']);
        expect(TaxBracket::query()->count())->toBe(6);
    });

    it('starts a new tax table as a copy of the one in force', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $this->actingAs($hrOfficer)->post(route('payroll.rates.tax.store'), ['effective_from' => '2027-01-01'])
            ->assertRedirect(route('payroll.rates.tax.edit', '2027-01-01'));

        expect(TaxBracket::query()->whereDate('effective_from', '2027-01-01')->count())->toBe(6);
    });
});

describe('allowances', function () {
    it('adds an allowance', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $response = $this->actingAs($hrOfficer)->post(route('payroll.allowances.store'), [
            'code' => 'rata', 'name' => 'Representation Allowance', 'monthly_amount' => '7500',
            'coverage' => 'assigned', 'is_taxable' => '0', 'is_active' => '1',
        ]);

        $response->assertRedirect(route('payroll.allowances.index'));
        expect(AllowanceType::query()->where('code', 'RATA')->sole()->only(['monthly_amount', 'coverage', 'is_taxable', 'reduced_for_unpaid_days']))->toBe([
            'monthly_amount' => '7500.00',
            'coverage' => AllowanceCoverage::Assigned,
            'is_taxable' => false,
            'reduced_for_unpaid_days' => false,
        ]);
    });

    it('assigns an allowance to an employee at their own amount', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create();
        $allowance = AllowanceType::factory()->create(['monthly_amount' => '7500.00']);

        $response = $this->actingAs($hrOfficer)->post(route('payroll.employees.allowances.store', $employee), [
            'allowance_type_id' => $allowance->id, 'allowance_amount' => '5000', 'allowance_starts_on' => '2026-10-01',
        ]);

        $response->assertRedirect(route('payroll.employees.show', $employee));
        expect($employee->allowances()->sole()->only(['allowance_type_id', 'monthly_amount']))->toBe(['allowance_type_id' => $allowance->id, 'monthly_amount' => '5000.00']);
    });

    it('does not assign the same allowance twice', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $assignment = EmployeeAllowance::factory()->create();

        $response = $this->actingAs($hrOfficer)->post(route('payroll.employees.allowances.store', $assignment->employee), ['allowance_type_id' => $assignment->allowance_type_id]);

        $response->assertSessionHasErrors(['allowance_type_id' => 'This allowance is already assigned to the employee.']);
    });

    it('removes an assignment only through its own employee', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $assignment = EmployeeAllowance::factory()->create();
        $otherEmployee = Employee::factory()->create();

        $this->actingAs($hrOfficer)->delete(route('payroll.employees.allowances.destroy', [$otherEmployee, $assignment]))->assertNotFound();
        $this->actingAs($hrOfficer)->delete(route('payroll.employees.allowances.destroy', [$assignment->employee, $assignment]));

        $this->assertModelMissing($assignment);
    });
});

describe('loans and other deductions', function () {
    it('adds a deduction for an employee', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($hrOfficer)->post(route('payroll.employees.deductions.store', $employee), [
            'name' => 'GSIS Consolidated Loan', 'reference' => 'CL-0001', 'monthly_amount' => '2500',
            'total_amount' => '60000', 'starts_on' => '2026-10-01',
        ]);

        $response->assertRedirect(route('payroll.employees.show', $employee));
        expect($employee->payrollDeductions()->sole()->only(['name', 'monthly_amount', 'total_amount', 'priority', 'is_active', 'created_by']))->toBe([
            'name' => 'GSIS Consolidated Loan',
            'monthly_amount' => '2500.00',
            'total_amount' => '60000.00',
            'priority' => 100,
            'is_active' => true,
            'created_by' => $hrOfficer->id,
        ]);
    });

    it('stops a deduction when HR deactivates it', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $deduction = EmployeeDeduction::factory()->create();

        $this->actingAs($hrOfficer)->put(route('payroll.employees.deductions.update', [$deduction->employee, $deduction]), [
            'name' => $deduction->name, 'monthly_amount' => '1000', 'starts_on' => '2026-01-01', 'is_active' => '0',
        ]);

        expect($deduction->fresh()->is_active)->toBeFalse();
    });

    it('rejects an end date before the start date', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($hrOfficer)->post(route('payroll.employees.deductions.store', $employee), [
            'name' => 'Union Dues', 'monthly_amount' => '100', 'starts_on' => '2026-10-01', 'ends_on' => '2026-09-30',
        ]);

        $response->assertSessionHasErrors(['ends_on' => 'The ends on field must be a date after or equal to starts on.']);
    });

    it('shows the pay setup with how much has been deducted', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create(['salary_grade' => 11, 'salary_step' => 1, 'date_hired' => '2020-01-06']);
        EmployeeDeduction::factory()->for($employee)->loan('6000.00')->create(['name' => 'Cooperative Loan', 'monthly_amount' => '1000.00']);
        computePayroll(2026, 10, 1, hrOfficer: $hrOfficer);

        $this->actingAs($hrOfficer)->get(route('payroll.employees.show', $employee))
            ->assertSee('22,194.00')
            ->assertSee('Cooperative Loan')
            ->assertSeeInOrder(['6,000.00', '500.00']);
    });

    it('keeps the pay setup from employees', function () {
        $employee = Employee::factory()->withAccount()->create();

        $this->actingAs($employee->user)->get(route('payroll.employees.show', $employee))->assertForbidden();
    });
});
