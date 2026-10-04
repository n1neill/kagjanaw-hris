<?php

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;

describe('access', function () {
    it('allows HR officers and system administrators to manage departments', function (Role $role) {
        $user = User::factory()->withRole($role)->create();
        Department::factory()->create(['name' => 'Municipal Health Office']);

        $response = $this->actingAs($user)->get(route('reference-data.departments.index'));

        $response->assertSee('Municipal Health Office');
    })->with([
        'hr officer' => Role::HrOfficer,
        'system administrator' => Role::SystemAdministrator,
    ]);

    it('forbids department heads and employees', function (Role $role) {
        $user = User::factory()->withRole($role)->create();

        $response = $this->actingAs($user)->post(route('reference-data.departments.store'), [
            'code' => 'MHO',
            'name' => 'Municipal Health Office',
            'is_active' => '1',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('departments', ['code' => 'MHO']);
    })->with([
        'department head' => Role::DepartmentHead,
        'employee' => Role::Employee,
    ]);
});

describe('store', function () {
    it('creates a department with an upper-case code', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $response = $this->actingAs($hrOfficer)->post(route('reference-data.departments.store'), [
            'code' => ' mho ',
            'name' => 'Municipal Health Office',
            'description' => 'Rural Health Unit',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('reference-data.departments.index'))
            ->assertSessionHas('status', 'Municipal Health Office added.');
        $this->assertDatabaseHas('departments', [
            'code' => 'MHO',
            'name' => 'Municipal Health Office',
            'description' => 'Rural Health Unit',
            'is_active' => true,
        ]);
    });

    it('rejects a duplicate code or name', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        Department::factory()->create(['code' => 'MHO', 'name' => 'Municipal Health Office']);

        $response = $this->actingAs($hrOfficer)->post(route('reference-data.departments.store'), [
            'code' => 'mho',
            'name' => 'Municipal Health Office',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors([
            'code' => 'The code has already been taken.',
            'name' => 'The name has already been taken.',
        ]);
        $this->assertDatabaseCount('departments', 1);
    });

    it('rejects a code with spaces or symbols', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $response = $this->actingAs($hrOfficer)->post(route('reference-data.departments.store'), [
            'code' => 'M H/O',
            'name' => 'Municipal Health Office',
        ]);

        $response->assertSessionHasErrors(['code' => 'The code field must only contain letters, numbers, dashes, and underscores.']);
        $this->assertDatabaseCount('departments', 0);
    });
});

describe('update', function () {
    it('deactivates a department when the active box is unchecked', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $department = Department::factory()->create(['code' => 'MTOUR', 'name' => 'Municipal Tourism Office']);

        $response = $this->actingAs($hrOfficer)->put(route('reference-data.departments.update', $department), [
            'code' => 'MTOUR',
            'name' => 'Municipal Tourism Office',
        ]);

        $response->assertRedirect(route('reference-data.departments.index'));
        expect($department->refresh()->is_active)->toBeFalse();
    });

    it('keeps its own code and name unique-safe when unchanged', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $department = Department::factory()->create(['code' => 'MTO', 'name' => "Municipal Treasurer's Office"]);

        $response = $this->actingAs($hrOfficer)->put(route('reference-data.departments.update', $department), [
            'code' => 'MTO',
            'name' => "Municipal Treasurer's Office",
            'description' => 'Collects local taxes and fees.',
            'is_active' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        expect($department->refresh()->description)->toBe('Collects local taxes and fees.');
    });
});
