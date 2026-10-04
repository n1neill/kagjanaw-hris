<?php

use App\Enums\Role;
use App\Models\User;

test('only system administrators may manage user accounts', function (Role $role, bool $allowed) {
    $actor = User::factory()->withRole($role)->create();
    $account = User::factory()->employee()->create();

    expect($actor->can('viewAny', User::class))->toBe($allowed)
        ->and($actor->can('create', User::class))->toBe($allowed)
        ->and($actor->can('update', $account))->toBe($allowed)
        ->and($actor->can('changeRole', $account))->toBe($allowed)
        ->and($actor->can('changeStatus', $account))->toBe($allowed);
})->with([
    'system administrator' => [Role::SystemAdministrator, true],
    'hr officer' => [Role::HrOfficer, false],
    'department head' => [Role::DepartmentHead, false],
    'employee' => [Role::Employee, false],
]);

test('administrators may edit their own account but not their own role or status', function () {
    $admin = User::factory()->systemAdministrator()->create();

    expect($admin->can('update', $admin))->toBeTrue()
        ->and($admin->can('changeRole', $admin))->toBeFalse()
        ->and($admin->can('changeStatus', $admin))->toBeFalse();
});
