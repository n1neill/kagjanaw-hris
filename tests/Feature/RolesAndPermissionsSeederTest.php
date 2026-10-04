<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

/*
 * The complete role/permission matrix. Expected grants are written out here,
 * independently of App\Enums\Role::permissions(), so a mistake in either the
 * enum mapping or the seeder fails this test.
 */

test('each role is granted exactly its permissions', function (Role $role, array $expectedGrants) {
    $user = User::factory()->withRole($role)->create();

    $actualGrants = collect(Permission::cases())
        ->filter(fn (Permission $permission): bool => $user->can($permission))
        ->map(fn (Permission $permission): string => $permission->value)
        ->sort()
        ->values()
        ->all();

    expect($actualGrants)->toBe(collect($expectedGrants)->sort()->values()->all());
})->with([
    'system administrator' => [Role::SystemAdministrator, [
        'audit-logs.view',
        'backups.manage',
        'biometric-devices.manage',
        'reference-data.manage',
        'settings.manage',
        'users.manage',
    ]],
    'hr officer' => [Role::HrOfficer, [
        'announcements.manage',
        'attendance.manage',
        'attendance.view-own',
        'employees.manage',
        'leave.approve',
        'leave.file',
        'payroll.process',
        'payslips.view-own',
        'records.view-own',
        'reference-data.manage',
        'reports.generate',
    ]],
    'department head' => [Role::DepartmentHead, [
        'attendance.view-department',
        'attendance.view-own',
        'employees.view-department',
        'leave.endorse',
        'leave.file',
        'payslips.view-own',
        'records.view-own',
        'reports.view-department',
    ]],
    'employee' => [Role::Employee, [
        'attendance.view-own',
        'leave.file',
        'payslips.view-own',
        'records.view-own',
    ]],
]);

test('re-running the seeder does not duplicate roles or permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->assertDatabaseCount('roles', 4);
    $this->assertDatabaseCount('permissions', count(Permission::cases()));
});
