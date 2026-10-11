<?php

use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Policies\EmployeePolicy;

/*
 * The complete access matrix for employee records and attendance. Each case
 * states who is looking (and how they relate to the record) and which
 * abilities they hold.
 */

test('access to an employee record follows role and relationship', function (string $viewer, array $expected) {
    $department = Department::factory()->create();
    $record = Employee::factory()->withAccount()->create(['department_id' => $department->id]);

    $user = match ($viewer) {
        'hr officer' => User::factory()->hrOfficer()->create(),
        'head of the same department' => Employee::factory()->withAccount(Role::DepartmentHead)->create(['department_id' => $department->id])->user,
        'head of another department' => Employee::factory()->withAccount(Role::DepartmentHead)->create()->user,
        'the employee' => $record->user,
        'a colleague in the same department' => Employee::factory()->withAccount()->create(['department_id' => $department->id])->user,
        'system administrator' => User::factory()->systemAdministrator()->create(),
    };

    expect([
        'viewAny' => $user->can('viewAny', Employee::class),
        'view' => $user->can('view', $record),
        'viewSensitive' => $user->can('viewSensitive', $record),
        'viewDocuments' => $user->can('viewDocuments', $record),
        'update' => $user->can('update', $record),
        'manageDocuments' => $user->can('manageDocuments', $record),
        'viewAttendance' => $user->can('viewAttendance', $record),
        'manageAttendance' => $user->can('manageAttendance', $record),
    ])->toBe($expected);
})->with([
    'hr officer' => ['hr officer', ['viewAny' => true, 'view' => true, 'viewSensitive' => true, 'viewDocuments' => true, 'update' => true, 'manageDocuments' => true, 'viewAttendance' => true, 'manageAttendance' => true]],
    'head of the same department' => ['head of the same department', ['viewAny' => true, 'view' => true, 'viewSensitive' => false, 'viewDocuments' => false, 'update' => false, 'manageDocuments' => false, 'viewAttendance' => true, 'manageAttendance' => false]],
    'head of another department' => ['head of another department', ['viewAny' => true, 'view' => false, 'viewSensitive' => false, 'viewDocuments' => false, 'update' => false, 'manageDocuments' => false, 'viewAttendance' => false, 'manageAttendance' => false]],
    'the employee' => ['the employee', ['viewAny' => false, 'view' => true, 'viewSensitive' => true, 'viewDocuments' => true, 'update' => false, 'manageDocuments' => false, 'viewAttendance' => true, 'manageAttendance' => false]],
    'a colleague in the same department' => ['a colleague in the same department', ['viewAny' => false, 'view' => false, 'viewSensitive' => false, 'viewDocuments' => false, 'update' => false, 'manageDocuments' => false, 'viewAttendance' => false, 'manageAttendance' => false]],
    'system administrator' => ['system administrator', ['viewAny' => false, 'view' => false, 'viewSensitive' => false, 'viewDocuments' => false, 'update' => false, 'manageDocuments' => false, 'viewAttendance' => false, 'manageAttendance' => false]],
]);

test('denied record access is reported as not found', function () {
    $employee = Employee::factory()->withAccount()->create();
    $colleague = Employee::factory()->create();

    $response = (new EmployeePolicy)->view($employee->user, $colleague);

    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
});
