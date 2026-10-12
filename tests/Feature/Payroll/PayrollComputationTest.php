<?php

use App\Enums\DtrStatus;
use App\Enums\PayBasis;
use App\Models\AllowanceType;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\EmployeeAllowance;
use App\Models\EmployeeDeduction;
use App\Models\EmploymentType;
use App\Models\PayrollItem;
use App\Models\User;
use App\Services\Payroll\PayrollSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/*
 * Expected amounts are worked out by hand from the seeded tables:
 * SG 11 Step 1 is ₱31,705 nationally; at the default 5th-class rate of 70%
 * that is ₱22,193.50, rounded to ₱22,194.00 a month (₱11,097.00 a half).
 */

beforeEach(function () {
    // Wednesday, October 7, 2026.
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00:00'));
});

function payrollItemFor(Employee $employee): PayrollItem
{
    return PayrollItem::query()->where('employee_id', $employee->id)->latest('id')->firstOrFail();
}

it('pays half the LGU salary and PERA, less half of each contribution', function () {
    $employee = Employee::factory()->create(['salary_grade' => 11, 'salary_step' => 1, 'date_hired' => '2020-01-06']);

    $run = computePayroll(2026, 10, 1);

    expect(payrollItemFor($employee)->only(['monthly_rate', 'basic_pay', 'allowances', 'gross_pay', 'gsis', 'philhealth', 'pagibig', 'withholding_tax', 'total_deductions', 'net_pay', 'gsis_employer']))->toBe([
        'monthly_rate' => '22194.00',
        'basic_pay' => '11097.00',
        'allowances' => '1000.00',
        'gross_pay' => '12097.00',
        'gsis' => '998.73',
        'philhealth' => '277.42',
        'pagibig' => '100.00',
        'withholding_tax' => '0.00',
        'total_deductions' => '1376.15',
        'net_pay' => '10720.85',
        'gsis_employer' => '1331.64',
    ]);
    expect($run->only(['employee_count', 'total_net']))->toBe(['employee_count' => 1, 'total_net' => '10720.85']);
});

it('gives the second half the centavo the first half rounds down', function () {
    $employee = Employee::factory()->create(['salary_grade' => 11, 'salary_step' => 1, 'date_hired' => '2020-01-06']);

    computePayroll(2026, 10, 2);

    expect(payrollItemFor($employee)->philhealth)->toBe('277.43');
});

it('withholds tax from the BIR monthly table', function () {
    // SG 24 Step 1: ₱102,603 × 70% = ₱71,822.10 → ₱71,822.00 a month.
    $employee = Employee::factory()->create(['salary_grade' => 24, 'salary_step' => 1, 'date_hired' => '2020-01-06']);

    computePayroll(2026, 10, 1);

    // Half: 35,911.00 − GSIS 3,231.99 − PhilHealth 897.77 − Pag-IBIG 100.00 = 31,681.24 taxable.
    // Monthly basis 63,362.48: 1,875 + 20% × 30,029.48 = 7,880.90; half = 3,940.45.
    expect(payrollItemFor($employee)->withholding_tax)->toBe('3940.45');
});

it('applies the income-class percentage HR sets', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();
    app(PayrollSettings::class)->update([
        'income_class' => 1,
        'income_class_confirmed' => true,
        'percentages' => PayrollSettings::DEFAULT_PERCENTAGES,
        'minimum_net_pay' => '5000.00',
        'daily_rate_tax_rate' => '5.00',
        'attendance_deductions_from' => null,
    ], $hrOfficer);
    $employee = Employee::factory()->create(['salary_grade' => 11, 'salary_step' => 1, 'date_hired' => '2020-01-06']);

    computePayroll(2026, 10, 1, hrOfficer: $hrOfficer);

    // 1st class: 90% × ₱31,705 = ₱28,534.50 → ₱28,535.00.
    expect(payrollItemFor($employee)->monthly_rate)->toBe('28535.00');
});

it('prorates pay by working days for an employee hired during the period', function () {
    // Hired Thursday, October 8: 6 of the 11 working days of October 1–15.
    $employee = Employee::factory()->create(['salary_grade' => 11, 'salary_step' => 1, 'date_hired' => '2026-10-08']);

    computePayroll(2026, 10, 1);

    $item = payrollItemFor($employee);
    expect($item->only(['basic_pay', 'allowances', 'gsis']))->toBe(['basic_pay' => '6052.91', 'allowances' => '545.45', 'gsis' => '544.76'])
        ->and($item->notes)->toBe(['Paid for 6 of 11 working days in the period.']);
});

it('pays assigned allowances and taxes the taxable ones', function () {
    $employee = Employee::factory()->create(['salary_grade' => 11, 'salary_step' => 1, 'date_hired' => '2020-01-06']);
    $hazard = AllowanceType::factory()->taxable()->create(['name' => 'Hazard Pay', 'monthly_amount' => '20000.00']);
    EmployeeAllowance::factory()->for($employee)->for($hazard)->create();
    $unassigned = AllowanceType::factory()->create(['name' => 'Subsistence Allowance']);

    computePayroll(2026, 10, 1);

    $item = payrollItemFor($employee);
    // Taxable: 11,097.00 + 10,000.00 − 1,376.15 = 19,720.85; monthly 39,441.70:
    // 1,875 + 20% × 6,108.70 = 3,096.74; half = 1,548.37.
    expect($item->allowances)->toBe('11000.00')
        ->and($item->withholding_tax)->toBe('1548.37')
        ->and(collect($item->earnings)->pluck('label'))->not->toContain($unassigned->name);
});

describe('absences without pay', function () {
    it('deducts last month\'s absences and reduces PERA once the DTR is validated', function () {
        $employee = Employee::factory()->create(['salary_grade' => 11, 'salary_step' => 1, 'date_hired' => '2020-01-06']);
        grantLeaveCredits($employee, asOf: '2026-08-31');
        validatedMonthOfDtr($employee, '2026-09', fn (CarbonImmutable $date): array => $date->day === 7
            ? ['am_in' => null, 'am_out' => null, 'pm_in' => null, 'pm_out' => null, 'absent_minutes' => 480, 'worked_minutes' => 0, 'status' => DtrStatus::Absent]
            : []);

        computePayroll(2026, 10, 1);

        // One day: ₱22,194 ÷ 22 = ₱1,008.82 salary and ₱2,000 ÷ 22 = ₱90.91 PERA.
        $item = payrollItemFor($employee);
        expect($item->only(['attendance_deductions', 'gross_pay', 'net_pay']))->toBe([
            'attendance_deductions' => '1099.73',
            'gross_pay' => '10997.27',
            'net_pay' => '9621.12',
        ]);
        expect($item->attendanceLines->sole()->only(['salary_amount', 'allowance_amount', 'absent_minutes']))
            ->toBe(['salary_amount' => '1008.82', 'allowance_amount' => '90.91', 'absent_minutes' => 480]);
    });

    it('does not deduct the same month twice', function () {
        $employee = Employee::factory()->create(['salary_grade' => 11, 'salary_step' => 1, 'date_hired' => '2020-01-06']);
        grantLeaveCredits($employee, asOf: '2026-08-31');
        validatedMonthOfDtr($employee, '2026-09', fn (CarbonImmutable $date): array => $date->day === 7
            ? ['absent_minutes' => 480, 'worked_minutes' => 0, 'status' => DtrStatus::Absent]
            : []);
        computePayroll(2026, 10, 1);

        computePayroll(2026, 10, 2);

        expect(payrollItemFor($employee)->attendance_deductions)->toBe('0.00');
    });

    it('refunds the difference when a deducted month is corrected', function () {
        $employee = Employee::factory()->create(['salary_grade' => 11, 'salary_step' => 1, 'date_hired' => '2020-01-06']);
        grantLeaveCredits($employee, asOf: '2026-08-31');
        validatedMonthOfDtr($employee, '2026-09', fn (CarbonImmutable $date): array => $date->day === 7
            ? ['absent_minutes' => 480, 'worked_minutes' => 0, 'status' => DtrStatus::Absent]
            : []);
        computePayroll(2026, 10, 1);
        $employee->dailyTimeRecords()->whereDate('work_date', '2026-09-07')->update(['absent_minutes' => 0, 'worked_minutes' => 480, 'status' => DtrStatus::Present->value]);

        computePayroll(2026, 10, 2);

        expect(payrollItemFor($employee)->attendance_deductions)->toBe('-1099.73');
    });

    it('deducts what one payroll can bear and the rest in the next', function () {
        $employee = Employee::factory()->create(['salary_grade' => 11, 'salary_step' => 1, 'date_hired' => '2020-01-06']);
        grantLeaveCredits($employee, asOf: '2026-08-31');
        validatedMonthOfDtr($employee, '2026-09', fn (CarbonImmutable $date): array => $date->day <= 17
            ? ['absent_minutes' => 480, 'worked_minutes' => 0, 'status' => DtrStatus::Absent]
            : []);

        computePayroll(2026, 10, 1);
        $first = payrollItemFor($employee);
        computePayroll(2026, 10, 2);
        $second = payrollItemFor($employee);

        // 13 weekdays absent: ₱13,114.64 salary and ₱1,181.82 PERA, against ₱12,097.00 less
        // ₱1,376.15 contributions = ₱10,720.85 available in the first half.
        expect($first->only(['attendance_deductions', 'gross_pay', 'withholding_tax', 'net_pay']))->toBe([
            'attendance_deductions' => '10720.85',
            'gross_pay' => '1376.15',
            'withholding_tax' => '0.00',
            'net_pay' => '0.00',
        ])
            ->and($first->notes)->toBe(['3,575.61 of absences without pay for September 2026 will be deducted in the next payroll.'])
            ->and($second->attendance_deductions)->toBe('3575.61');
    });

    it('waits for the DTR to be validated and says so', function () {
        $employee = Employee::factory()->create(['salary_grade' => 11, 'salary_step' => 1, 'date_hired' => '2020-01-06', 'last_name' => 'Waiting', 'first_name' => 'Rosa', 'middle_name' => null]);
        grantLeaveCredits($employee, asOf: '2026-08-31');
        DailyTimeRecord::factory()->for($employee)->absent()->create(['work_date' => '2026-09-07']);

        $run = computePayroll(2026, 10, 1);

        expect(payrollItemFor($employee)->attendance_deductions)->toBe('0.00')
            ->and($run->warnings)->toBe([[
                'message' => 'The DTR for September 2026 is not yet validated for 1 employee (Waiting, Rosa); their absences will be deducted in a later payroll.',
                'blocking' => false,
            ]]);
    });

    it('does not wait on months already covered by the opening leave balance', function () {
        $employee = Employee::factory()->create(['salary_grade' => 11, 'salary_step' => 1, 'date_hired' => '2020-01-06']);
        grantLeaveCredits($employee, asOf: '2026-09-30');

        $run = computePayroll(2026, 10, 1);

        expect($run->warnings)->toBeNull()
            ->and(payrollItemFor($employee)->attendance_deductions)->toBe('0.00');
    });

    it('does not deduct absences of employees who earn no leave credits', function () {
        $elective = EmploymentType::factory()->create(['earns_leave_credits' => false]);
        $employee = Employee::factory()->for($elective)->create(['salary_grade' => 27, 'salary_step' => 1, 'date_hired' => '2025-06-30']);
        DailyTimeRecord::factory()->for($employee)->absent()->create(['work_date' => '2026-09-07', 'validated_at' => now()]);

        computePayroll(2026, 10, 1);

        expect(payrollItemFor($employee)->attendance_deductions)->toBe('0.00');
    });
});

describe('loans and other deductions', function () {
    it('deducts half the monthly amount until the total is paid', function () {
        $employee = Employee::factory()->create(['salary_grade' => 11, 'salary_step' => 1, 'date_hired' => '2020-01-06']);
        EmployeeDeduction::factory()->for($employee)->loan('700.00')->create(['name' => 'Cooperative Loan', 'reference' => null, 'monthly_amount' => '1000.00']);
        computePayroll(2026, 10, 1);

        computePayroll(2026, 10, 2);

        expect(PayrollItem::query()->where('employee_id', $employee->id)->orderBy('id')->pluck('other_deductions')->all())->toBe(['500.00', '200.00']);
    });

    it('keeps net pay at the minimum, deducting the rest later', function () {
        $employee = Employee::factory()->create(['salary_grade' => 11, 'salary_step' => 1, 'date_hired' => '2020-01-06']);
        EmployeeDeduction::factory()->for($employee)->create(['name' => 'Salary Loan', 'reference' => null, 'monthly_amount' => '30000.00']);

        computePayroll(2026, 10, 1);

        // Net before loans ₱10,720.85; half of the ₱5,000 minimum stays.
        $item = payrollItemFor($employee);
        expect($item->only(['other_deductions', 'net_pay']))->toBe(['other_deductions' => '8220.85', 'net_pay' => '2500.00'])
            ->and($item->notes)->toBe(['6,779.15 of Salary Loan was not deducted to keep net pay at the minimum.']);
    });

    it('skips deductions that have not started or have ended', function () {
        $employee = Employee::factory()->create(['salary_grade' => 11, 'salary_step' => 1, 'date_hired' => '2020-01-06']);
        EmployeeDeduction::factory()->for($employee)->create(['starts_on' => '2026-11-01']);
        EmployeeDeduction::factory()->for($employee)->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-09-30']);
        EmployeeDeduction::factory()->for($employee)->create(['is_active' => false]);

        computePayroll(2026, 10, 1);

        expect(payrollItemFor($employee)->other_deductions)->toBe('0.00');
    });
});

describe('Job Order and Contract of Service', function () {
    /**
     * October 1–15, 2026: 11 weekdays worked in full except a half day on the 9th.
     */
    function jobOrderDtr(Employee $employee, bool $validated = true): void
    {
        foreach (CarbonPeriod::create('2026-10-01', '2026-10-15') as $date) {
            $date = CarbonImmutable::parse($date);
            DailyTimeRecord::factory()->for($employee)->create([
                'work_date' => $date->toDateString(),
                'validated_at' => $validated ? now() : null,
                ...match (true) {
                    $date->isWeekend() => ['am_in' => null, 'am_out' => null, 'pm_in' => null, 'pm_out' => null, 'worked_minutes' => 0, 'status' => DtrStatus::RestDay],
                    $date->day === 9 => ['pm_in' => null, 'pm_out' => null, 'worked_minutes' => 240, 'absent_minutes' => 240],
                    default => [],
                },
            ]);
        }
    }

    it('pays the daily rate for the time worked, less the tax rate HR sets', function () {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 09:00:00'));
        $employee = Employee::factory()->for(EmploymentType::factory()->dailyRate())->create(['daily_rate' => '500.00', 'salary_grade' => null, 'salary_step' => null, 'date_hired' => '2026-01-05']);
        jobOrderDtr($employee);

        $run = computePayroll(2026, 10, 1, PayBasis::DailyRate);

        // 10.5 days × ₱500 = ₱5,250.00; 5% tax = ₱262.50. No GSIS, PhilHealth or Pag-IBIG.
        expect(payrollItemFor($employee)->only(['worked_minutes', 'gross_pay', 'gsis', 'philhealth', 'pagibig', 'withholding_tax', 'net_pay']))->toBe([
            'worked_minutes' => 5040,
            'gross_pay' => '5250.00',
            'gsis' => '0.00',
            'philhealth' => '0.00',
            'pagibig' => '0.00',
            'withholding_tax' => '262.50',
            'net_pay' => '4987.50',
        ]);
        expect($run->warnings)->toBeNull();
    });

    it('keeps Job Order workers out of the regular payroll and the reverse', function () {
        $jobOrder = Employee::factory()->for(EmploymentType::factory()->dailyRate())->create(['daily_rate' => '500.00']);
        Employee::factory()->create(['salary_grade' => 11, 'salary_step' => 1]);

        $run = computePayroll(2026, 10, 1, PayBasis::DailyRate);

        expect($run->items()->pluck('employee_id')->all())->toBe([$jobOrder->id]);
    });

    it('flags a period whose DTR is not validated', function () {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 09:00:00'));
        $employee = Employee::factory()->for(EmploymentType::factory()->dailyRate())->create(['daily_rate' => '500.00', 'last_name' => 'Unvalidated', 'first_name' => 'Ana', 'middle_name' => null]);
        jobOrderDtr($employee, validated: false);

        $run = computePayroll(2026, 10, 1, PayBasis::DailyRate);

        expect($run->warnings)->toBe([['message' => 'Unvalidated, Ana\'s DTR for the period is not fully validated.', 'blocking' => true]]);
    });
});

it('pays nobody it cannot price and flags them', function () {
    $employee = Employee::factory()->create(['salary_grade' => null, 'salary_step' => null, 'last_name' => 'Nograde', 'first_name' => 'Ben', 'middle_name' => null]);

    $run = computePayroll(2026, 10, 1);

    expect($run->employee_count)->toBe(0)
        ->and($run->warnings)->toBe([['message' => 'Nograde, Ben has no salary grade and step in the Salary Schedule, so was not paid.', 'blocking' => true]]);
});

it('includes employees separated during the period and leaves out those separated before', function () {
    $leaving = Employee::factory()->create(['salary_grade' => 11, 'salary_step' => 1, 'date_hired' => '2020-01-06', 'employment_status' => 'resigned', 'separation_date' => '2026-10-09']);
    $gone = Employee::factory()->create(['salary_grade' => 11, 'salary_step' => 1, 'date_hired' => '2020-01-06', 'employment_status' => 'resigned', 'separation_date' => '2026-09-30']);

    $run = computePayroll(2026, 10, 1);

    // In service October 1, 2, 5, 6, 7, 8 and 9: 7 of 11 working days.
    expect($run->items()->pluck('employee_id')->all())->toBe([$leaving->id])
        ->and(payrollItemFor($leaving)->basic_pay)->toBe('7061.73');
});
