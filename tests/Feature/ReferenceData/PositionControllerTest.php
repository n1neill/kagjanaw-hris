<?php

use App\Enums\Role;
use App\Models\Position;
use App\Models\User;

it('creates a position with its salary grade', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();

    $response = $this->actingAs($hrOfficer)->post(route('reference-data.positions.store'), [
        'title' => 'Administrative Officer IV',
        'salary_grade' => '15',
        'is_active' => '1',
    ]);

    $response->assertRedirect(route('reference-data.positions.index'));
    $this->assertDatabaseHas('positions', [
        'title' => 'Administrative Officer IV',
        'salary_grade' => 15,
        'is_active' => true,
    ]);
});

it('rejects a salary grade outside 1 to 33', function (string $salaryGrade) {
    $hrOfficer = User::factory()->hrOfficer()->create();

    $response = $this->actingAs($hrOfficer)->post(route('reference-data.positions.store'), [
        'title' => 'Administrative Officer IV',
        'salary_grade' => $salaryGrade,
        'is_active' => '1',
    ]);

    $response->assertSessionHasErrors(['salary_grade' => 'The salary grade field must be between 1 and 33.']);
    $this->assertDatabaseCount('positions', 0);
})->with([
    'below the lowest grade' => '0',
    'above the highest grade' => '34',
]);

it('rejects a duplicate title', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();
    Position::factory()->create(['title' => 'Nurse I']);

    $response = $this->actingAs($hrOfficer)->post(route('reference-data.positions.store'), [
        'title' => 'Nurse I',
        'salary_grade' => '15',
    ]);

    $response->assertSessionHasErrors(['title' => 'The title has already been taken.']);
    $this->assertDatabaseCount('positions', 1);
});

it('updates a position', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();
    $position = Position::factory()->create(['title' => 'Nurse I', 'salary_grade' => 11]);

    $this->actingAs($hrOfficer)->put(route('reference-data.positions.update', $position), [
        'title' => 'Nurse I',
        'salary_grade' => '15',
        'is_active' => '1',
    ]);

    expect($position->refresh()->salary_grade)->toBe(15);
});

it('forbids department heads and employees', function (Role $role) {
    $user = User::factory()->withRole($role)->create();
    $position = Position::factory()->create(['title' => 'Nurse I', 'salary_grade' => 15]);

    $response = $this->actingAs($user)->put(route('reference-data.positions.update', $position), [
        'title' => 'Nurse I',
        'salary_grade' => '33',
        'is_active' => '1',
    ]);

    $response->assertForbidden();
    expect($position->refresh()->salary_grade)->toBe(15);
})->with([
    'department head' => Role::DepartmentHead,
    'employee' => Role::Employee,
]);
