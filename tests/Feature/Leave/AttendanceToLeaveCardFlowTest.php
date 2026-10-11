<?php

use App\Enums\DtrStatus;
use App\Enums\LeaveLedgerEntryType;
use App\Enums\Role;
use App\Models\BiometricDevice;
use App\Models\DailyTimeRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Leave\CscLeaveTables;
use App\Services\Leave\LeaveLedger;
use Carbon\CarbonImmutable;

/*
 * Integration: biometric punches -> DTR -> approved sick leave excuses a day ->
 * HR validates the month -> the leave card earns the month's credits and is
 * charged for the month's tardiness, using the mock device and real services.
 */

it('turns a month of attendance and an approved leave into leave card entries', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-21 18:00:00'));
    $department = Department::factory()->create();
    $hrOfficer = User::factory()->hrOfficer()->create();
    $head = Employee::factory()->withAccount(Role::DepartmentHead)->create(['department_id' => $department->id]);
    $employee = Employee::factory()->withAccount()->create(['department_id' => $department->id, 'biometric_user_id' => '2048', 'date_hired' => '2020-01-06']);
    grantLeaveCredits($employee, vacation: '10.000', sick: '10.000', asOf: '2026-08-31');
    BiometricDevice::factory()->create(['driver' => 'mock']);

    $this->artisan('attendance:sync')->assertSuccessful();

    $this->actingAs($employee->user)->post(route('leave.requests.store'), [
        'leave_type_id' => LeaveType::sick()->id,
        'date_from' => '2026-09-15',
        'portion' => 'full',
        'details' => 'Flu.',
    ])->assertSessionHasNoErrors();
    $leaveRequest = $employee->leaveRequests()->sole();
    $this->actingAs($head->user)->post(route('leave.requests.endorse', $leaveRequest))->assertSessionHasNoErrors();
    $this->actingAs($hrOfficer)->post(route('leave.requests.approve', $leaveRequest))->assertSessionHasNoErrors();

    $this->travelTo(CarbonImmutable::parse('2026-10-02 09:00:00'));
    $this->artisan('attendance:sync')->assertSuccessful();
    $this->artisan('attendance:process', ['--from' => '2026-09-01', '--to' => '2026-09-30'])->assertSuccessful();

    expect($employee->dailyTimeRecords()->whereDate('work_date', '2026-09-15')->sole()->status)->toBe(DtrStatus::OnLeave);

    $employee->dailyTimeRecords()->where('status', DtrStatus::Incomplete)->get()->each(
        fn (DailyTimeRecord $record) => $this->actingAs($hrOfficer)->put(route('attendance.dtr.update', [$employee, $record->work_date->toDateString()]), [
            'am_in' => '08:00', 'am_out' => '12:00', 'pm_in' => '13:00', 'pm_out' => '17:00',
            'reason' => 'Missing punch confirmed by the department head.',
        ])->assertSessionHasNoErrors()
    );

    $this->actingAs($hrOfficer)->post(route('attendance.dtr.validate', [$employee, 'month' => '2026-09']))->assertSessionHasNoErrors();

    $september = $employee->dailyTimeRecords()->whereBetween('work_date', ['2026-09-01', '2026-09-30'])->get();
    $absentDays = CscLeaveTables::minutesToDays((int) $september->sum('absent_minutes'));
    $tardiness = CscLeaveTables::minutesToDays((int) $september->sum(fn (DailyTimeRecord $record): int => $record->late_minutes + $record->undertime_minutes));
    $earned = CscLeaveTables::earnedForMonth(30, $absentDays);

    $ledger = app(LeaveLedger::class);
    expect($ledger->balance($employee, LeaveType::sick()))->toBe(10000 - 1000 + $earned)
        ->and($ledger->balance($employee, LeaveType::vacation()))->toBe(10000 + $earned - $tardiness)
        ->and($employee->leaveLedgerEntries()->where('entry_type', LeaveLedgerEntryType::Accrual)->count())->toBe(2);
});
