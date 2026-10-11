<?php

use App\Enums\Role;
use App\Enums\SyncStatus;
use App\Enums\VerifyType;
use App\Models\BiometricDevice;
use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\RawPunch;
use Carbon\CarbonImmutable;
use Tests\Fakes\FakeBiometricDriver;

describe('settings', function () {
    it('adds a device', function () {
        $admin = User::factory()->systemAdministrator()->create();

        $response = $this->actingAs($admin)->post(route('admin.devices.store'), [
            'name' => 'Main Lobby Time Clock',
            'driver' => 'mock',
            'ip_address' => '192.168.1.201',
            'port' => '4370',
            'location' => 'Municipal Hall',
            'is_active' => '1',
        ]);

        $device = BiometricDevice::sole();
        $response->assertRedirect(route('admin.devices.show', $device));
        expect($device->only(['name', 'driver', 'ip_address', 'port', 'is_active']))->toBe([
            'name' => 'Main Lobby Time Clock',
            'driver' => 'mock',
            'ip_address' => '192.168.1.201',
            'port' => 4370,
            'is_active' => true,
        ]);
    });

    it('rejects an invalid address, port or driver', function () {
        $admin = User::factory()->systemAdministrator()->create();

        $response = $this->actingAs($admin)->post(route('admin.devices.store'), [
            'name' => 'Clock',
            'driver' => 'telnet',
            'ip_address' => '192.168.1.999',
            'port' => '70000',
        ]);

        $response->assertSessionHasErrors([
            'driver' => 'The selected driver is invalid.',
            'ip_address' => 'The IP address field must be a valid IP address.',
            'port' => 'The port field must be between 1 and 65535.',
        ]);
        $this->assertDatabaseCount('biometric_devices', 0);
    });

    it('deactivates a device when the active box is unchecked', function () {
        $admin = User::factory()->systemAdministrator()->create();
        $device = BiometricDevice::factory()->create();

        $this->actingAs($admin)->put(route('admin.devices.update', $device), [
            'name' => $device->name,
            'driver' => 'mock',
            'ip_address' => $device->ip_address,
            'port' => '4370',
        ]);

        expect($device->refresh()->is_active)->toBeFalse();
    });

    it('is only available to system administrators', function (Role $role) {
        $user = User::factory()->withRole($role)->create();
        $device = BiometricDevice::factory()->create();

        $this->actingAs($user)->get(route('admin.devices.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.devices.sync', $device))->assertForbidden();
    })->with([
        'hr officer' => Role::HrOfficer,
        'department head' => Role::DepartmentHead,
        'employee' => Role::Employee,
    ]);
});

describe('actions', function () {
    it('reports device details when the connection test succeeds', function () {
        $admin = User::factory()->systemAdministrator()->create();
        $device = BiometricDevice::factory()->create(['driver' => 'fake']);
        FakeBiometricDriver::register();

        $response = $this->actingAs($admin)->from(route('admin.devices.show', $device))->post(route('admin.devices.test', $device));

        $response->assertRedirect(route('admin.devices.show', $device))
            ->assertSessionHas('status', 'Connected to Fake K40 (serial FAKE0001, firmware Ver 6.60): 12 users enrolled, 0 logs stored.');
    });

    it('explains why the connection test failed', function () {
        $admin = User::factory()->systemAdministrator()->create();
        $device = BiometricDevice::factory()->unreachable()->create(['name' => 'Lobby']);

        $response = $this->actingAs($admin)->post(route('admin.devices.test', $device));

        $response->assertSessionHas('error', 'Connection failed: Device Lobby at 0.0.0.0:4370 did not respond.');
    });

    it('synchronizes on demand and shows the run in the sync log', function () {
        $this->travelTo(CarbonImmutable::parse('2026-10-07 18:00:00'));
        $admin = User::factory()->systemAdministrator()->create();
        Employee::factory()->create(['biometric_user_id' => '1024']);
        $device = BiometricDevice::factory()->create(['driver' => 'fake']);
        FakeBiometricDriver::register([new RawPunch('1024', CarbonImmutable::parse('2026-10-07 07:58'), VerifyType::Face)]);

        $response = $this->actingAs($admin)->post(route('admin.devices.sync', $device));

        $response->assertSessionHas('status', 'Sync finished: 1 read, 1 new, 0 already stored, 0 flagged.');
        $run = $device->syncRuns()->sole();
        expect($run->status)->toBe(SyncStatus::Succeeded)->and($run->triggered_by)->toBe($admin->id);
        $this->actingAs($admin)->get(route('admin.devices.show', $device))->assertSee($admin->name);
    });

    it('reports a failed sync', function () {
        $admin = User::factory()->systemAdministrator()->create();
        $device = BiometricDevice::factory()->unreachable()->create(['name' => 'Lobby']);

        $response = $this->actingAs($admin)->post(route('admin.devices.sync', $device));

        $response->assertSessionHas('error', 'Sync failed: Device Lobby at 0.0.0.0:4370 did not respond.');
        expect($device->syncRuns()->sole()->status)->toBe(SyncStatus::Failed);
    });
});

describe('scheduled sync command', function () {
    it('syncs only active devices and fails when any device fails', function () {
        $active = BiometricDevice::factory()->create(['driver' => 'fake', 'name' => 'Lobby']);
        $inactive = BiometricDevice::factory()->inactive()->create(['driver' => 'fake']);
        FakeBiometricDriver::register(failure: 'Timed out.');

        $this->artisan('attendance:sync')
            ->expectsOutput('Lobby: Timed out.')
            ->assertFailed();

        expect($active->syncRuns()->count())->toBe(1)
            ->and($inactive->syncRuns()->count())->toBe(0);
    });

    it('does nothing without active devices', function () {
        $this->artisan('attendance:sync')
            ->expectsOutput('No active biometric devices to sync.')
            ->assertSuccessful();
    });
});
