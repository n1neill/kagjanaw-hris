<?php

use App\Enums\DtrStatus;
use App\Models\BiometricDevice;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;

/*
 * Integration: biometric device -> raw logs -> Daily Time Record -> HR
 * review -> printed CSC Form 48, using the mock driver with no test doubles.
 */

it('carries punches from the mock device all the way to a validated DTR', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 18:00:00'));
    $hrOfficer = User::factory()->hrOfficer()->create();
    $employee = Employee::factory()->withAccount()->create(['biometric_user_id' => '1024', 'date_hired' => '2026-01-05']);
    $device = BiometricDevice::factory()->create(['driver' => 'mock']);

    $this->artisan('attendance:sync')->assertSuccessful();
    $this->artisan('attendance:process', ['--from' => '2026-10-01', '--to' => '2026-10-07'])->assertSuccessful();

    $records = $employee->dailyTimeRecords()->where('work_date', '>=', '2026-10-01')->orderBy('work_date')->get();
    expect($records->pluck('work_date')->map->toDateString()->all())
        ->toBe(['2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04', '2026-10-05', '2026-10-06', '2026-10-07'])
        ->and($records->where('status', DtrStatus::RestDay)->count())->toBe(2)
        ->and($device->refresh()->last_synced_at)->not->toBeNull();

    foreach ($records->where('status', DtrStatus::Incomplete) as $record) {
        $this->actingAs($hrOfficer)->put(route('attendance.dtr.update', [$employee, $record->work_date->toDateString()]), [
            'am_in' => '08:00', 'am_out' => '12:00', 'pm_in' => '13:00', 'pm_out' => '17:00',
            'reason' => 'Missing punch confirmed by the department head.',
        ])->assertSessionHasNoErrors();
    }

    $this->actingAs($hrOfficer)
        ->post(route('attendance.dtr.validate', [$employee, 'month' => '2026-10']))
        ->assertSessionHasNoErrors();

    expect($employee->dailyTimeRecords()->where('work_date', '>=', '2026-10-01')->whereNull('validated_at')->count())->toBe(0);

    $this->actingAs($employee->user)
        ->get(route('attendance.dtr.pdf', [$employee, 'month' => '2026-10']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});
