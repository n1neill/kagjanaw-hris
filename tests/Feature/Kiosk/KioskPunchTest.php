<?php

use App\Enums\AttendanceLogStatus;
use App\Enums\AttendanceSource;
use App\Enums\SyncStatus;
use App\Models\AttendanceLog;
use App\Models\BiometricDevice;
use App\Models\Employee;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 18:00:00'));
    $this->kiosk = BiometricDevice::factory()->kiosk()->create();
});

it('stores pushed punches and builds the daily time record', function () {
    $employee = Employee::factory()->create(['biometric_user_id' => '1024', 'date_hired' => '2020-01-06']);

    $response = $this->withToken('test-kiosk-token')->postJson(route('kiosk.punches'), ['punches' => [
        ['biometric_user_id' => '1024', 'logged_at' => '2026-10-06T08:25:31+08:00'],
        ['biometric_user_id' => '1024', 'logged_at' => '2026-10-06T12:01:00+08:00'],
        ['biometric_user_id' => '1024', 'logged_at' => '2026-10-06T12:58:00+08:00'],
        // Sent in UTC: 08:30 UTC is 16:30 in Manila.
        ['biometric_user_id' => '1024', 'logged_at' => '2026-10-06T08:30:00Z'],
    ]]);

    $response->assertOk()->assertExactJson(['received' => 4, 'inserted' => 4, 'duplicates' => 0, 'flagged' => 0]);

    $log = AttendanceLog::query()->orderBy('logged_at')->first();
    expect($log->logged_at->toDateTimeString())->toBe('2026-10-06 08:25:31')
        ->and($log->source)->toBe(AttendanceSource::Device)
        ->and($log->biometric_device_id)->toBe($this->kiosk->id);

    $record = $employee->dailyTimeRecords()->sole();
    expect([$record->am_in, $record->am_out, $record->pm_in, $record->pm_out])->toBe(['08:25:00', '12:01:00', '12:58:00', '16:30:00']);

    $run = $this->kiosk->syncRuns()->sole();
    expect($run->status)->toBe(SyncStatus::Succeeded)->and($run->triggered_by)->toBeNull();
});

it('accepts a re-sent batch without storing it twice', function () {
    Employee::factory()->create(['biometric_user_id' => '1024', 'date_hired' => '2020-01-06']);
    $batch = ['punches' => [['biometric_user_id' => '1024', 'logged_at' => '2026-10-07T07:58:00+08:00']]];

    $this->withToken('test-kiosk-token')->postJson(route('kiosk.punches'), $batch)->assertOk();
    $this->withToken('test-kiosk-token')->postJson(route('kiosk.punches'), $batch)
        ->assertOk()
        ->assertJson(['received' => 1, 'inserted' => 0, 'duplicates' => 1]);

    $this->assertDatabaseCount('attendance_logs', 1);
});

it('flags punches from a biometric ID no employee has', function () {
    $this->withToken('test-kiosk-token')->postJson(route('kiosk.punches'), ['punches' => [
        ['biometric_user_id' => '5555', 'logged_at' => '2026-10-07T08:00:00+08:00'],
    ]])->assertJson(['flagged' => 1]);

    expect(AttendanceLog::sole()->status)->toBe(AttendanceLogStatus::UnknownUser);
});

it('rejects a malformed batch', function () {
    $this->withToken('test-kiosk-token')->postJson(route('kiosk.punches'), ['punches' => [
        ['biometric_user_id' => '', 'logged_at' => 'yesterday-ish', 'verify_type' => 'retina'],
    ]])->assertUnprocessable()->assertJsonValidationErrors([
        'punches.0.biometric_user_id' => 'The punches.0.biometric_user_id field is required.',
        'punches.0.logged_at' => 'The punches.0.logged_at field must be a valid date.',
        'punches.0.verify_type' => 'The selected punches.0.verify_type is invalid.',
    ]);

    $this->withToken('test-kiosk-token')->postJson(route('kiosk.punches'), ['punches' => []])
        ->assertJsonValidationErrors(['punches' => 'The punches field is required.']);

    $this->assertDatabaseCount('attendance_logs', 0);
});

it('leaves kiosks out of the scheduled sync', function () {
    $this->artisan('attendance:sync')
        ->expectsOutput('No active biometric devices to sync.')
        ->assertSuccessful();

    expect($this->kiosk->syncRuns()->count())->toBe(0);
});
