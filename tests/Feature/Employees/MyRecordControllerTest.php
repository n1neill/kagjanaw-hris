<?php

use App\Enums\Role;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;

it('shows employees their own full record and 201 file', function () {
    $employee = Employee::factory()->withAccount()->create(['tin' => '123456789000']);
    EmployeeDocument::factory()->for($employee)->create(['title' => 'Oath of Office 2021']);

    $response = $this->actingAs($employee->user)->get(route('my-record'));

    $response->assertSee($employee->full_name)
        ->assertSee('123456789000')
        ->assertSee('Oath of Office 2021')
        ->assertDontSee(route('employees.edit', $employee))
        ->assertDontSee('action="'.route('employees.documents.store', $employee).'"', escape: false);
});

it('explains when the account has no employee record', function () {
    $user = User::factory()->employee()->create();

    $response = $this->actingAs($user)->get(route('my-record'));

    $response->assertSee('Your account is not linked to an employee record.');
});

it('is not available to system administrators', function () {
    $admin = User::factory()->withRole(Role::SystemAdministrator)->create();

    $response = $this->actingAs($admin)->get(route('my-record'));

    $response->assertForbidden();
});
