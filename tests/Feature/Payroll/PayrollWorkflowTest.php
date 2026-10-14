<?php

use App\Enums\PayBasis;
use App\Enums\PayrollRunStatus;
use App\Jobs\ComputePayrollRun;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\User;
use App\Notifications\PayslipReleasedNotification;
use App\Services\Payroll\PayrollRunService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00:00'));
});

/**
 * A computed regular payroll for October 1–15 with one employee who has an account.
 *
 * @return array{run: PayrollRun, employee: Employee, hrOfficer: User}
 */
function computedPayrollWithOneEmployee(): array
{
    $hrOfficer = User::factory()->hrOfficer()->create();
    $employee = Employee::factory()->withAccount()->create(['salary_grade' => 11, 'salary_step' => 1, 'date_hired' => '2020-01-06']);

    return ['run' => computePayroll(2026, 10, 1, hrOfficer: $hrOfficer), 'employee' => $employee, 'hrOfficer' => $hrOfficer];
}

describe('creating and computing', function () {
    it('creates a payroll and computes it', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create(['salary_grade' => 11, 'salary_step' => 1, 'date_hired' => '2020-01-06']);

        $response = $this->actingAs($hrOfficer)->post(route('payroll.runs.store'), ['month' => '2026-10', 'half' => 1, 'pay_basis' => 'salary-grade']);

        $run = PayrollRun::query()->sole();
        $response->assertRedirect(route('payroll.runs.show', $run));
        expect($run->status)->toBe(PayrollRunStatus::Computed)
            ->and($run->items()->pluck('employee_id')->all())->toBe([$employee->id]);
        $this->actingAs($hrOfficer)->get(route('payroll.runs.show', $run))
            ->assertSee($employee->sortable_name)
            ->assertSee('10,720.85');
    });

    it('queues the computation and shows the payroll as computing', function () {
        Queue::fake([ComputePayrollRun::class]);
        $hrOfficer = User::factory()->hrOfficer()->create();

        $this->actingAs($hrOfficer)->post(route('payroll.runs.store'), ['month' => '2026-10', 'half' => 2, 'pay_basis' => 'daily-rate']);

        $run = PayrollRun::query()->sole();
        expect($run->status)->toBe(PayrollRunStatus::Computing)
            ->and($run->pay_basis)->toBe(PayBasis::DailyRate);
        Queue::assertPushed(ComputePayrollRun::class, fn (ComputePayrollRun $job): bool => $job->run->is($run));
    });

    it('refuses a second payroll of the same kind for a period', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        computePayroll(2026, 10, 1, hrOfficer: $hrOfficer);

        $response = $this->actingAs($hrOfficer)->post(route('payroll.runs.store'), ['month' => '2026-10', 'half' => 1, 'pay_basis' => 'salary-grade']);

        $response->assertSessionHasErrors(['period' => 'The regular payroll for October 1–15, 2026 already exists.']);
        expect(PayrollRun::query()->count())->toBe(1);
    });

    it('rejects an incomplete request', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $response = $this->actingAs($hrOfficer)->post(route('payroll.runs.store'), []);

        $response->assertSessionHasErrors(['month', 'half', 'pay_basis']);
    });

    it('queues a payroll stuck at computing again', function () {
        Queue::fake([ComputePayrollRun::class]);
        $hrOfficer = User::factory()->hrOfficer()->create();
        $run = app(PayrollRunService::class)->create(2026, 10, 1, PayBasis::SalaryGrade, $hrOfficer);
        $run->forceFill(['status' => PayrollRunStatus::Computing])->save();

        $response = $this->actingAs($hrOfficer)->post(route('payroll.runs.compute', $run));

        $response->assertSessionHasNoErrors();
        Queue::assertPushed(ComputePayrollRun::class, fn (ComputePayrollRun $job): bool => $job->run->is($run));
    });

    it('puts a payroll whose computation failed back to draft with the reason', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $service = app(PayrollRunService::class);
        $run = $service->create(2026, 10, 1, PayBasis::SalaryGrade, $hrOfficer);
        $run->forceFill(['status' => PayrollRunStatus::Computing])->save();

        (new ComputePayrollRun($run))->failed(new RuntimeException('Database went away'));

        expect($run->fresh()->only(['status', 'failure_message']))->toBe([
            'status' => PayrollRunStatus::Draft,
            'failure_message' => 'The computation failed: Database went away',
        ]);
    });

    it('is only for HR', function () {
        $employee = Employee::factory()->withAccount()->create();

        $this->actingAs($employee->user)->get(route('payroll.runs.index'))->assertForbidden();
        $this->actingAs($employee->user)->post(route('payroll.runs.store'), ['month' => '2026-10', 'half' => 1, 'pay_basis' => 'salary-grade'])->assertForbidden();
        expect(PayrollRun::query()->count())->toBe(0);
    });
});

describe('approval and release', function () {
    it('approves and releases a payroll, notifying the employees', function () {
        Notification::fake();
        ['run' => $run, 'employee' => $employee, 'hrOfficer' => $hrOfficer] = computedPayrollWithOneEmployee();

        $this->actingAs($hrOfficer)->post(route('payroll.runs.approve', $run))->assertRedirect(route('payroll.runs.show', $run));
        $response = $this->actingAs($hrOfficer)->post(route('payroll.runs.release', $run));

        $response->assertSessionHas('status', 'The payroll was released; 1 employee was notified.');
        expect($run->fresh()->only(['status', 'approved_by', 'released_by']))->toBe([
            'status' => PayrollRunStatus::Released,
            'approved_by' => $hrOfficer->id,
            'released_by' => $hrOfficer->id,
        ]);
        Notification::assertSentTo($employee->user, PayslipReleasedNotification::class, fn ($notification) => $notification->toArray($employee->user)['message'] === 'Your payslip for October 1–15, 2026 is ready.');
    });

    it('does not approve a payroll that still has problems', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        Employee::factory()->create(['salary_grade' => null, 'salary_step' => null]);
        $run = computePayroll(2026, 10, 1, hrOfficer: $hrOfficer);

        $response = $this->actingAs($hrOfficer)->post(route('payroll.runs.approve', $run));

        $response->assertSessionHasErrors(['status' => 'Resolve the problems listed under "Needs attention" and compute again before approving.']);
        expect($run->fresh()->status)->toBe(PayrollRunStatus::Computed);
    });

    it('locks a released payroll', function () {
        ['run' => $run, 'hrOfficer' => $hrOfficer] = computedPayrollWithOneEmployee();
        $service = app(PayrollRunService::class);
        $service->release($service->approve($run, $hrOfficer), $hrOfficer);
        $itemIds = $run->items()->pluck('id')->all();

        $this->actingAs($hrOfficer)->post(route('payroll.runs.compute', $run))->assertSessionHasErrors(['status' => 'This payroll is released.']);
        $this->actingAs($hrOfficer)->post(route('payroll.runs.reopen', $run))->assertSessionHasErrors('status');
        $this->actingAs($hrOfficer)->delete(route('payroll.runs.destroy', $run))->assertSessionHasErrors('status');

        expect($run->fresh()->status)->toBe(PayrollRunStatus::Released)
            ->and($run->items()->pluck('id')->all())->toBe($itemIds);
    });

    it('reopens an approved payroll for corrections', function () {
        ['run' => $run, 'hrOfficer' => $hrOfficer] = computedPayrollWithOneEmployee();
        app(PayrollRunService::class)->approve($run, $hrOfficer);

        $this->actingAs($hrOfficer)->post(route('payroll.runs.reopen', $run));

        expect($run->fresh()->only(['status', 'approved_by']))->toBe(['status' => PayrollRunStatus::Computed, 'approved_by' => null]);
    });

    it('deletes a payroll that has not been approved', function () {
        ['run' => $run, 'hrOfficer' => $hrOfficer] = computedPayrollWithOneEmployee();

        $response = $this->actingAs($hrOfficer)->delete(route('payroll.runs.destroy', $run));

        $response->assertRedirect(route('payroll.runs.index'));
        $this->assertModelMissing($run);
        expect(PayrollItem::query()->count())->toBe(0);
    });
});

describe('payslips', function () {
    it('lets employees download their own released payslips', function () {
        ['run' => $run, 'employee' => $employee, 'hrOfficer' => $hrOfficer] = computedPayrollWithOneEmployee();
        $service = app(PayrollRunService::class);
        $service->release($service->approve($run, $hrOfficer), $hrOfficer);
        $item = $run->items()->sole();

        $this->actingAs($employee->user)->get(route('payroll.payslips.my'))->assertSee('October 1–15, 2026')->assertSee('10,720.85');
        $this->actingAs($employee->user)->get(route('payroll.payslips.show', $item))->assertOk()->assertHeader('content-type', 'application/pdf');
    });

    it('hides payslips that are not yet released', function () {
        ['run' => $run, 'employee' => $employee] = computedPayrollWithOneEmployee();

        $this->actingAs($employee->user)->get(route('payroll.payslips.show', $run->items()->sole()))->assertNotFound();
        $this->actingAs($employee->user)->get(route('payroll.payslips.my'))->assertDontSee('October 1–15, 2026');
    });

    it('hides other employees\' payslips', function () {
        ['run' => $run, 'hrOfficer' => $hrOfficer] = computedPayrollWithOneEmployee();
        $service = app(PayrollRunService::class);
        $service->release($service->approve($run, $hrOfficer), $hrOfficer);
        $colleague = Employee::factory()->withAccount()->create();

        $this->actingAs($colleague->user)->get(route('payroll.payslips.show', $run->items()->sole()))->assertNotFound();
    });

    it('lets HR open any payslip, released or not', function () {
        ['run' => $run, 'hrOfficer' => $hrOfficer] = computedPayrollWithOneEmployee();

        $this->actingAs($hrOfficer)->get(route('payroll.payslips.show', $run->items()->sole()))->assertOk()->assertHeader('content-type', 'application/pdf');
    });

    it('shows the latest released payslip on the employee dashboard', function () {
        ['run' => $run, 'employee' => $employee, 'hrOfficer' => $hrOfficer] = computedPayrollWithOneEmployee();
        $service = app(PayrollRunService::class);
        $service->release($service->approve($run, $hrOfficer), $hrOfficer);

        $this->actingAs($employee->user)->get(route('dashboard'))->assertSee('Latest payslip')->assertSee('₱ 10,720.85');
    });
});

describe('payroll register', function () {
    it('exports the register as CSV with totals', function () {
        ['run' => $run, 'employee' => $employee, 'hrOfficer' => $hrOfficer] = computedPayrollWithOneEmployee();

        $response = $this->actingAs($hrOfficer)->get(route('payroll.runs.register', [$run, 'csv']));

        $rows = array_map('str_getcsv', explode("\n", trim(substr($response->streamedContent(), 3))));
        expect($rows[2])->toBe(['No.', 'Employee No.', 'Employee', 'Position', 'Office', 'Rate', 'Basic pay', 'Allowances', 'Absences w/o pay', 'Gross pay', 'GSIS', 'PhilHealth', 'Pag-IBIG', 'Withholding tax', 'Loans and others', 'Total deductions', 'Net pay', 'GSIS (employer)', 'PhilHealth (employer)', 'Pag-IBIG (employer)'])
            ->and(array_slice($rows[3], 0, 3))->toBe(['1', $employee->employee_number, $employee->sortable_name])
            ->and($rows[3][16])->toBe('10720.85')
            ->and($rows[4][16])->toBe('10720.85');
    });

    it('exports the register as an Excel workbook', function () {
        ['run' => $run, 'employee' => $employee, 'hrOfficer' => $hrOfficer] = computedPayrollWithOneEmployee();

        $response = $this->actingAs($hrOfficer)->get(route('payroll.runs.register', [$run, 'xlsx']));

        $path = tempnam(sys_get_temp_dir(), 'register');
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);
        expect($sheet->getCell('C5')->getValue())->toBe($employee->sortable_name)
            ->and($sheet->getCell('Q5')->getValue())->toBe(10720.85);
    });

    it('exports names that look like formulas as plain text', function () {
        ['run' => $run, 'employee' => $employee, 'hrOfficer' => $hrOfficer] = computedPayrollWithOneEmployee();
        $employee->update(['last_name' => '=HYPERLINK("http://evil.test")']);

        $csv = $this->actingAs($hrOfficer)->get(route('payroll.runs.register', [$run, 'csv']));
        $xlsx = $this->actingAs($hrOfficer)->get(route('payroll.runs.register', [$run, 'xlsx']));

        $rows = array_map('str_getcsv', explode("\n", trim(substr($csv->streamedContent(), 3))));
        expect($rows[3][2])->toStartWith('\'=HYPERLINK')
            ->and($rows[3][16])->toBe('10720.85');
        $path = tempnam(sys_get_temp_dir(), 'register');
        file_put_contents($path, $xlsx->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);
        expect($sheet->getCell('C5')->getDataType())->toBe(DataType::TYPE_STRING)
            ->and($sheet->getCell('Q5')->getValue())->toBe(10720.85);
    });

    it('prints the register as a PDF', function () {
        ['run' => $run, 'hrOfficer' => $hrOfficer] = computedPayrollWithOneEmployee();

        $this->actingAs($hrOfficer)->get(route('payroll.runs.register', [$run, 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
    });
});
