<?php

use App\Enums\Role;
use App\Models\User;

it('redirects guests to the login page', function () {
    $response = $this->get('/dashboard');

    $response->assertRedirect(route('login'));
});

it('renders the dashboard that matches the user role', function (Role $role, string $view, string $heading) {
    $user = User::factory()->withRole($role)->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertViewIs($view)->assertSee($heading);
})->with([
    'system administrator' => [Role::SystemAdministrator, 'dashboard.admin', 'System Administration'],
    'hr officer' => [Role::HrOfficer, 'dashboard.hr', 'HR Dashboard'],
    'department head' => [Role::DepartmentHead, 'dashboard.department-head', 'Department Dashboard'],
    'employee' => [Role::Employee, 'dashboard.employee', 'My Dashboard'],
]);

it('shows account counts on the administrator dashboard', function () {
    $admin = User::factory()->systemAdministrator()->create();
    User::factory()->count(2)->employee()->create();
    User::factory()->employee()->inactive()->create();

    $response = $this->actingAs($admin)->get('/dashboard');

    $response->assertViewHas('totalUsers', 4)
        ->assertViewHas('activeUsers', 3)
        ->assertViewHas('inactiveUsers', 1)
        ->assertViewHas('usersPerRole', [
            'System Administrator' => 1,
            'HR Officer' => 0,
            'Department Head' => 0,
            'Employee' => 3,
        ]);
});

it('forbids a user without a role from opening the dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertForbidden();
});

it('shows only the menu items the role is permitted to use', function (Role $role, array $visible, array $hidden) {
    $user = User::factory()->withRole($role)->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertSeeInOrder($visible);
    foreach ($hidden as $label) {
        $response->assertDontSee($label);
    }
})->with([
    'system administrator' => [Role::SystemAdministrator, ['Reference Data', 'User Accounts'], []],
    'hr officer' => [Role::HrOfficer, ['Reference Data'], ['User Accounts']],
    'department head' => [Role::DepartmentHead, ['Dashboard'], ['Reference Data', 'User Accounts']],
    'employee' => [Role::Employee, ['Dashboard'], ['Reference Data', 'User Accounts']],
]);
