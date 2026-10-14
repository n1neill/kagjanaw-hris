<?php

use App\Enums\Role;
use App\Models\User;
use App\Services\SystemSettings;
use Carbon\CarbonImmutable;

$validSettings = [
    'sync_interval_minutes' => '10',
    'backup_enabled' => '1',
    'backup_time' => '19:30',
    'backups_kept' => '14',
    'audit_retention_years' => '7',
];

it('shows the settings with their defaults', function () {
    $admin = User::factory()->systemAdministrator()->create();

    $this->actingAs($admin)->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertViewHas('backupTime', SystemSettings::DEFAULT_BACKUP_TIME)
        ->assertViewHas('syncIntervalMinutes', config('attendance.sync_interval_minutes'));
});

it('saves the system settings', function () use ($validSettings) {
    $admin = User::factory()->systemAdministrator()->create();

    $response = $this->actingAs($admin)->put(route('admin.settings.update'), $validSettings);

    $response->assertRedirect(route('admin.settings.edit'));
    $settings = app(SystemSettings::class);
    expect($settings->syncIntervalMinutes())->toBe(10)
        ->and($settings->isBackupEnabled())->toBeTrue()
        ->and($settings->backupTime())->toBe('19:30')
        ->and($settings->backupsKept())->toBe(14)
        ->and($settings->auditRetentionYears())->toBe(7);
});

it('turns the daily backup off when the box is unchecked', function () use ($validSettings) {
    $admin = User::factory()->systemAdministrator()->create();

    $this->actingAs($admin)->put(route('admin.settings.update'), [...$validSettings, 'backup_enabled' => null]);

    expect(app(SystemSettings::class)->isBackupEnabled())->toBeFalse();
});

it('rejects values out of range', function () use ($validSettings) {
    $admin = User::factory()->systemAdministrator()->create();

    $response = $this->actingAs($admin)->put(route('admin.settings.update'), [
        ...$validSettings,
        'sync_interval_minutes' => '60',
        'backup_time' => '25:00',
        'backups_kept' => '2',
        'audit_retention_years' => '0',
    ]);

    $response->assertSessionHasErrors(['sync_interval_minutes', 'backup_time', 'backups_kept', 'audit_retention_years']);
});

it('runs the sync every N minutes past the hour and the backup at its time', function () {
    $admin = User::factory()->systemAdministrator()->create();
    $settings = app(SystemSettings::class);
    $settings->update(['sync_interval_minutes' => 15, 'backup_enabled' => true, 'backup_time' => '18:00', 'backups_kept' => 30, 'audit_retention_years' => 5], $admin);

    expect($settings->isSyncDue(CarbonImmutable::parse('2026-10-13 09:45')))->toBeTrue()
        ->and($settings->isSyncDue(CarbonImmutable::parse('2026-10-13 09:50')))->toBeFalse()
        ->and($settings->isBackupDue(CarbonImmutable::parse('2026-10-13 18:00')))->toBeTrue()
        ->and($settings->isBackupDue(CarbonImmutable::parse('2026-10-13 18:01')))->toBeFalse();

    $settings->update(['sync_interval_minutes' => 15, 'backup_enabled' => false, 'backup_time' => '18:00', 'backups_kept' => 30, 'audit_retention_years' => 5], $admin);

    expect($settings->isBackupDue(CarbonImmutable::parse('2026-10-13 18:00')))->toBeFalse();
});

it('is only available to the System Administrator', function (Role $role) use ($validSettings) {
    $user = User::factory()->withRole($role)->create();

    $this->actingAs($user)->get(route('admin.settings.edit'))->assertForbidden();
    $this->actingAs($user)->put(route('admin.settings.update'), $validSettings)->assertForbidden();
})->with([
    'hr officer' => Role::HrOfficer,
    'department head' => Role::DepartmentHead,
    'employee' => Role::Employee,
]);
