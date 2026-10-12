<?php

use App\Enums\PayBasis;
use App\Enums\Role;
use App\Models\EmploymentType;
use App\Models\User;

it('creates an employment type with a lower-case code', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();

    $response = $this->actingAs($hrOfficer)->post(route('reference-data.employment-types.store'), [
        'code' => 'Job-Order',
        'name' => 'Job Order',
        'description' => 'Not considered government service.',
        'is_active' => '1',
    ]);

    $response->assertRedirect(route('reference-data.employment-types.index'));
    $this->assertDatabaseHas('employment_types', [
        'code' => 'job-order',
        'name' => 'Job Order',
        'is_active' => true,
    ]);
});

it('rejects a duplicate code', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();
    EmploymentType::factory()->create(['code' => 'casual', 'name' => 'Casual']);

    $response = $this->actingAs($hrOfficer)->post(route('reference-data.employment-types.store'), [
        'code' => 'CASUAL',
        'name' => 'Casual Employee',
    ]);

    $response->assertSessionHasErrors(['code' => 'The code has already been taken.']);
    $this->assertDatabaseCount('employment_types', 1);
});

it('updates an employment type', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();
    $employmentType = EmploymentType::factory()->create(['code' => 'casual', 'name' => 'Casual']);

    $this->actingAs($hrOfficer)->put(route('reference-data.employment-types.update', $employmentType), [
        'code' => 'casual',
        'name' => 'Casual (Plantilla)',
    ]);

    $employmentType->refresh();
    expect($employmentType->name)->toBe('Casual (Plantilla)')
        ->and($employmentType->is_active)->toBeFalse();
});

it('sets how employees of a type are paid', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();
    $employmentType = EmploymentType::factory()->create(['code' => 'job-order', 'name' => 'Job Order']);

    $this->actingAs($hrOfficer)->put(route('reference-data.employment-types.update', $employmentType), [
        'code' => 'job-order',
        'name' => 'Job Order',
        'pay_basis' => 'daily-rate',
    ]);

    expect($employmentType->fresh()->pay_basis)->toBe(PayBasis::DailyRate);
});

it('keeps the pay basis when none is sent', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();
    $employmentType = EmploymentType::factory()->dailyRate()->create(['code' => 'job-order', 'name' => 'Job Order']);

    $this->actingAs($hrOfficer)->put(route('reference-data.employment-types.update', $employmentType), [
        'code' => 'job-order',
        'name' => 'Job Order Worker',
    ]);

    expect($employmentType->fresh()->pay_basis)->toBe(PayBasis::DailyRate);
});

it('forbids department heads and employees', function (Role $role) {
    $user = User::factory()->withRole($role)->create();

    $response = $this->actingAs($user)->get(route('reference-data.employment-types.create'));

    $response->assertForbidden();
})->with([
    'department head' => Role::DepartmentHead,
    'employee' => Role::Employee,
]);
