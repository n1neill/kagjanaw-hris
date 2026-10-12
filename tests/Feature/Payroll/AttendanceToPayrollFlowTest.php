<?php

use App\Enums\DtrStatus;
use App\Enums\Role;
use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\PayrollRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/*
 * Integration: biometric punches -> DTR -> a sick leave approved partly
 * without pay -> HR validates the month (leave card posted) -> the next
 * payroll deducts exactly what leave credits could not cover, and PERA is
 * reduced the same way.
 */

it('deducts last month\'s leave without pay and absences in the next payroll', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-02 09:00:00'));
    $department = Department::factory()->create();
    $hrOfficer = User::factory()->hrOfficer()->create();
    $head = Employee::factory()->withAccount(Role::DepartmentHead)->create(['department_id' => $department->id, 'salary_grade' => null, 'salary_step' => null]);
    $employee = Employee::factory()->withAccount()->create([
        'department_id' => $department->id, 'date_hired' => '2020-01-06', 'salary_grade' => 11, 'salary_step' => 1,
    ]);
    grantLeaveCredits($employee, vacation: '0.500', sick: '0.000', asOf: '2026-08-31');

    // Every weekday of September punched in full, except the 15th (sick) and the 22nd (absent).
    foreach (CarbonPeriod::create('2026-09-01', '2026-09-30') as $date) {
        $date = CarbonImmutable::parse($date);

        if ($date->isWeekend() || in_array($date->day, [15, 22], true)) {
            continue;
        }

        foreach (['07:55', '12:01', '12:55', '17:02'] as $time) {
            AttendanceLog::factory()->for($employee)->create(['logged_at' => $date->toDateString().' '.$time.':00']);
        }
    }

    // Sick leave on the 15th: no sick leave left, so half a day of vacation leave and half a day without pay.
    $this->actingAs($employee->user)->post(route('leave.requests.store'), [
        'leave_type_id' => LeaveType::sick()->id, 'date_from' => '2026-09-15', 'portion' => 'full', 'details' => 'Flu.',
    ])->assertSessionHasNoErrors();
    $leaveRequest = $employee->leaveRequests()->sole();
    $this->actingAs($head->user)->post(route('leave.requests.endorse', $leaveRequest))->assertSessionHasNoErrors();
    $this->actingAs($hrOfficer)->post(route('leave.requests.approve', $leaveRequest))->assertSessionHasNoErrors();

    $this->artisan('attendance:process', ['--from' => '2026-09-01', '--to' => '2026-09-30'])->assertSuccessful();
    $this->actingAs($hrOfficer)->post(route('attendance.dtr.validate', [$employee, 'month' => '2026-09']))->assertSessionHasNoErrors();

    $this->actingAs($hrOfficer)->post(route('payroll.runs.store'), ['month' => '2026-10', 'half' => 1, 'pay_basis' => 'salary-grade'])->assertSessionHasNoErrors();

    expect($employee->dailyTimeRecords()->whereDate('work_date', '2026-09-15')->sole()->status)->toBe(DtrStatus::OnLeave);
    $item = PayrollRun::query()->sole()->items()->where('employee_id', $employee->id)->sole();
    // 1.5 days without pay: ₱22,194 × 1.5 ÷ 22 = ₱1,513.23 and PERA ₱2,000 × 1.5 ÷ 22 = ₱136.36.
    expect($item->attendanceLines()->sole()->only(['days_without_pay', 'absent_minutes', 'salary_amount', 'allowance_amount']))->toBe([
        'days_without_pay' => '0.500',
        'absent_minutes' => 480,
        'salary_amount' => '1513.23',
        'allowance_amount' => '136.36',
    ]);
    expect($item->only(['attendance_deductions', 'gross_pay']))->toBe(['attendance_deductions' => '1649.59', 'gross_pay' => '10447.41']);
});
