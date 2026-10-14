<?php

use App\Enums\Role;
use App\Models\DailyTimeRecord;
use App\Models\Department;
use App\Models\Employee;
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
            'Municipal Mayor' => 0,
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
    'system administrator' => [Role::SystemAdministrator, ['Reference Data', 'User Accounts'], ['My Record', 'Employees']],
    'hr officer' => [Role::HrOfficer, ['My Record', 'Employees', 'Reference Data'], ['User Accounts']],
    'department head' => [Role::DepartmentHead, ['My Record', 'My Department'], ['Reference Data', 'User Accounts']],
    'employee' => [Role::Employee, ['My Record'], ['Reference Data', 'User Accounts', 'My Department']],
]);

it('shows HR officers the employee headcount', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();
    Employee::factory()->count(3)->create();
    Employee::factory()->separated()->create();

    $response = $this->actingAs($hrOfficer)->get('/dashboard');

    $response->assertViewHas('employeesInService', 3)->assertViewHas('separatedEmployees', 1);
});

it('shows department heads the size of their team in service', function () {
    $department = Department::factory()->create();
    $head = Employee::factory()->withAccount(Role::DepartmentHead)->create(['department_id' => $department->id]);
    Employee::factory()->count(2)->create(['department_id' => $department->id]);
    Employee::factory()->separated()->create(['department_id' => $department->id]);
    Employee::factory()->create();

    $response = $this->actingAs($head->user)->get('/dashboard');

    $response->assertViewHas('teamSize', 3)->assertSee($department->name);
});

it('gives HR officers the attendance, leave, headcount and payroll charts', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();

    $response = $this->actingAs($hrOfficer)->get('/dashboard');

    $response->assertOk()
        ->assertViewHas('attendanceChart', fn (array $chart): bool => $chart['type'] === 'column-stacked')
        ->assertViewHas('leaveChart')
        ->assertViewHas('headcountChart')
        ->assertViewHas('payrollChart', fn (array $chart): bool => $chart['unit'] === 'peso');
});

it('shows department heads the attendance of their own department only', function () {
    $this->travelTo('2026-10-15 09:00');
    $department = Department::factory()->create();
    $head = Employee::factory()->withAccount(Role::DepartmentHead)->create(['department_id' => $department->id]);
    DailyTimeRecord::factory()->create([
        'work_date' => '2026-10-01',
        'employee_id' => Employee::factory()->create(['department_id' => $department->id]),
    ]);
    DailyTimeRecord::factory()->count(4)->create(['work_date' => '2026-10-01']);

    $response = $this->actingAs($head->user)->get('/dashboard');

    $response->assertViewHas('attendanceChart', fn (array $chart): bool => $chart['series'][0]['values'] === [1])
        ->assertViewMissing('payrollChart')
        ->assertViewMissing('headcountChart');
});

it('shows employees a chart of their own month', function () {
    $this->travelTo('2026-10-15 09:00');
    $employee = Employee::factory()->withAccount()->create();
    DailyTimeRecord::factory()->for($employee)->late()->create(['work_date' => '2026-10-01']);
    DailyTimeRecord::factory()->count(3)->create(['work_date' => '2026-10-01']);

    $response = $this->actingAs($employee->user)->get('/dashboard');

    $response->assertViewHas('myAttendanceChart', fn (array $chart): bool => array_sum(array_merge(...array_column($chart['series'], 'values'))) === 1)
        ->assertViewMissing('attendanceChart')
        ->assertSee('data-chart=', false);
});

it('charts the administrator\'s accounts by role', function () {
    $admin = User::factory()->systemAdministrator()->create();

    $response = $this->actingAs($admin)->get('/dashboard');

    $response->assertViewHas('rolesChart', fn (array $chart): bool => array_sum($chart['series'][0]['values']) === 1)
        ->assertViewMissing('payrollChart');
});
