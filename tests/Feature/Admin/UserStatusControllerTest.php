<?php

use App\Enums\Role;
use App\Models\User;

it('deactivates an account so it can no longer sign in', function () {
    $admin = User::factory()->systemAdministrator()->create();
    $account = User::factory()->employee()->create(['name' => 'Ana Reyes']);

    $response = $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->patch(route('admin.users.status', $account), ['is_active' => '0']);

    $response->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('status', 'Account for Ana Reyes deactivated.');
    expect($account->refresh()->is_active)->toBeFalse();
});

it('reactivates a deactivated account', function () {
    $admin = User::factory()->systemAdministrator()->create();
    $account = User::factory()->employee()->inactive()->create(['name' => 'Ana Reyes']);

    $response = $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->patch(route('admin.users.status', $account), ['is_active' => '1']);

    $response->assertSessionHas('status', 'Account for Ana Reyes activated.');
    expect($account->refresh()->is_active)->toBeTrue();
});

it('forbids administrators from deactivating themselves', function () {
    $admin = User::factory()->systemAdministrator()->create();

    $response = $this->actingAs($admin)->patch(route('admin.users.status', $admin), ['is_active' => '0']);

    $response->assertForbidden();
    expect($admin->refresh()->is_active)->toBeTrue();
});

it('requires the status value', function () {
    $admin = User::factory()->systemAdministrator()->create();
    $account = User::factory()->employee()->create();

    $response = $this->actingAs($admin)->patch(route('admin.users.status', $account), []);

    $response->assertSessionHasErrors(['is_active' => 'The is active field is required.']);
    expect($account->refresh()->is_active)->toBeTrue();
});

it('forbids roles without the manage users permission', function (Role $role) {
    $user = User::factory()->withRole($role)->create();
    $account = User::factory()->employee()->create();

    $response = $this->actingAs($user)->patch(route('admin.users.status', $account), ['is_active' => '0']);

    $response->assertForbidden();
    expect($account->refresh()->is_active)->toBeTrue();
})->with([
    'hr officer' => Role::HrOfficer,
    'department head' => Role::DepartmentHead,
    'employee' => Role::Employee,
]);
