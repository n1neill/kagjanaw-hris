<?php

use App\Enums\AttendanceLogStatus;
use App\Enums\DtrStatus;
use App\Enums\SyncStatus;
use App\Enums\VerifyType;
use App\Models\AttendanceLog;
use App\Models\BiometricDevice;
use App\Models\DeviceSyncRun;
use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\AttendanceSyncService;
use App\Services\Attendance\RawPunch;
use Carbon\CarbonImmutable;
use Tests\Fakes\FakeBiometricDriver;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 18:00:00'));
});

/**
 * @param  list<string>  $times  "Y-m-d H:i" timestamps
 * @return list<RawPunch>
 */
function punchesFor(string $biometricUserId, array $times): array
{
    return array_map(
        fn (string $time): RawPunch => new RawPunch($biometricUserId, CarbonImmutable::parse($time), VerifyType::Fingerprint),
        $times,
    );
}

it('stores punches and builds the daily time record', function () {
    $employee = Employee::factory()->create(['biometric_user_id' => '1024', 'date_hired' => '2020-01-06']);
    $device = BiometricDevice::factory()->create(['driver' => 'fake']);
    FakeBiometricDriver::register(punchesFor('1024', ['2026-10-06 08:25', '2026-10-06 12:01', '2026-10-06 12:58', '2026-10-06 16:30']));

    $run = app(AttendanceSyncService::class)->sync($device);

    expect($run->status)->toBe(SyncStatus::Succeeded)
        ->and($run->fetched_count)->toBe(4)
        ->and($run->inserted_count)->toBe(4)
        ->and($run->duplicate_count)->toBe(0);

    $record = $employee->dailyTimeRecords()->sole();
    expect($record->work_date->toDateString())->toBe('2026-10-06')
        ->and([$record->am_in, $record->am_out, $record->pm_in, $record->pm_out])->toBe(['08:25:00', '12:01:00', '12:58:00', '16:30:00'])
        ->and($record->late_minutes)->toBe(25)
        ->and($record->undertime_minutes)->toBe(30)
        ->and($record->status)->toBe(DtrStatus::Present);
    expect($device->refresh()->last_sync_status)->toBe(SyncStatus::Succeeded)
        ->and($device->last_synced_at->toDateTimeString())->toBe('2026-10-07 18:00:00');
});

it('skips punches already stored when the same logs are synced again', function () {
    Employee::factory()->create(['biometric_user_id' => '1024', 'date_hired' => '2020-01-06']);
    $device = BiometricDevice::factory()->create(['driver' => 'fake']);
    FakeBiometricDriver::register(punchesFor('1024', ['2026-10-06 07:58', '2026-10-06 12:00']));
    $sync = app(AttendanceSyncService::class);

    $sync->sync($device);
    $secondRun = $sync->sync($device);

    expect($secondRun->inserted_count)->toBe(0)
        ->and($secondRun->duplicate_count)->toBe(2);
    $this->assertDatabaseCount('attendance_logs', 2);
});

it('flags punches from unknown IDs and future times and leaves them out of the DTR', function () {
    $employee = Employee::factory()->create(['biometric_user_id' => '1024', 'date_hired' => '2020-01-06']);
    $device = BiometricDevice::factory()->create(['driver' => 'fake']);
    FakeBiometricDriver::register([
        ...punchesFor('5555', ['2026-10-06 08:00']),
        ...punchesFor('1024', ['2026-10-09 08:00']),
    ]);

    $run = app(AttendanceSyncService::class)->sync($device);

    expect($run->flagged_count)->toBe(2);
    $this->assertDatabaseHas('attendance_logs', ['biometric_user_id' => '5555', 'employee_id' => null, 'status' => AttendanceLogStatus::UnknownUser->value]);
    $this->assertDatabaseHas('attendance_logs', ['biometric_user_id' => '1024', 'employee_id' => $employee->id, 'status' => AttendanceLogStatus::FutureTimestamp->value]);
    expect($employee->dailyTimeRecords()->count())->toBe(0);
});

it('records a failed sync without stopping and keeps the last successful sync time', function () {
    $device = BiometricDevice::factory()->create(['driver' => 'fake', 'last_synced_at' => '2026-10-07 09:00:00']);
    FakeBiometricDriver::register(failure: 'Device at 192.168.1.201:4370 did not respond.');

    $run = app(AttendanceSyncService::class)->sync($device);

    expect($run->status)->toBe(SyncStatus::Failed)
        ->and($run->error_message)->toBe('Device at 192.168.1.201:4370 did not respond.')
        ->and($run->finished_at)->not->toBeNull();
    $device->refresh();
    expect($device->last_sync_status)->toBe(SyncStatus::Failed)
        ->and($device->last_synced_at->toDateTimeString())->toBe('2026-10-07 09:00:00');
});

it('re-reads from one day before the last successful sync', function () {
    $device = BiometricDevice::factory()->create(['driver' => 'fake']);
    DeviceSyncRun::factory()->for($device, 'device')->create(['started_at' => '2026-10-07 17:55:00']);
    DeviceSyncRun::factory()->for($device, 'device')->failed()->create(['started_at' => '2026-10-07 17:58:00']);
    FakeBiometricDriver::register();

    app(AttendanceSyncService::class)->sync($device);

    expect(FakeBiometricDriver::$requests[0]['since']->toDateTimeString())->toBe('2026-10-06 17:55:00');
});

it('reads the last seven days on a device\'s first sync', function () {
    $device = BiometricDevice::factory()->create(['driver' => 'fake']);
    FakeBiometricDriver::register();

    app(AttendanceSyncService::class)->sync($device);

    expect(FakeBiometricDriver::$requests[0]['since']->toDateTimeString())->toBe('2026-09-30 00:00:00');
});

it('records who started a manual sync', function () {
    $admin = User::factory()->systemAdministrator()->create();
    $device = BiometricDevice::factory()->create(['driver' => 'fake']);
    FakeBiometricDriver::register();

    $run = app(AttendanceSyncService::class)->sync($device, $admin);

    expect($run->triggered_by)->toBe($admin->id);
});

it('attaches earlier unknown punches when HR assigns the biometric ID to an employee', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();
    $employee = Employee::factory()->withAccount()->create(['biometric_user_id' => null, 'date_hired' => '2020-01-06']);
    $log = AttendanceLog::factory()->unknownUser('7777')->create(['logged_at' => '2026-10-06 08:00:00']);
    AttendanceLog::factory()->unknownUser('7777')->create(['logged_at' => '2026-10-06 17:00:00']);

    $this->actingAs($hrOfficer)->put(route('employees.update', $employee), [
        ...$employee->only(['employee_number', 'last_name', 'first_name', 'birth_place', 'department_id', 'position_id', 'employment_type_id', 'salary_grade', 'salary_step']),
        'sex' => $employee->sex->value,
        'civil_status' => $employee->civil_status->value,
        'birth_date' => $employee->birth_date->toDateString(),
        'date_hired' => $employee->date_hired->toDateString(),
        'employment_status' => 'active',
        'biometric_user_id' => '7777',
    ])->assertSessionHasNoErrors();

    expect($log->refresh()->employee_id)->toBe($employee->id)
        ->and($log->status)->toBe(AttendanceLogStatus::Valid);
    $record = $employee->dailyTimeRecords()->whereDate('work_date', '2026-10-06')->sole();
    expect($record->am_in)->toBe('08:00:00')
        ->and($record->pm_out)->toBe('17:00:00');
});
