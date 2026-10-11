<?php

use App\Models\User;
use App\Models\WorkSchedule;

it('updates office hours, grace period and working days', function () {
    $admin = User::factory()->systemAdministrator()->create();

    $response = $this->actingAs($admin)->put(route('admin.work-schedule.update'), [
        'am_start' => '07:30',
        'am_end' => '11:30',
        'pm_start' => '12:30',
        'pm_end' => '16:30',
        'grace_minutes' => '10',
        'working_days' => ['1', '2', '3', '4', '5', '6'],
    ]);

    $response->assertRedirect(route('admin.work-schedule.edit'));
    $schedule = WorkSchedule::current();
    expect($schedule->rules()->amStart)->toBe(450)
        ->and($schedule->grace_minutes)->toBe(10)
        ->and($schedule->working_days)->toBe([1, 2, 3, 4, 5, 6]);
    expect(WorkSchedule::count())->toBe(1);
});

it('rejects sessions out of order and missing working days', function () {
    $admin = User::factory()->systemAdministrator()->create();

    $response = $this->actingAs($admin)->put(route('admin.work-schedule.update'), [
        'am_start' => '08:00',
        'am_end' => '07:00',
        'pm_start' => '13:00',
        'pm_end' => '17:00',
        'grace_minutes' => '15',
        'working_days' => [],
    ]);

    $response->assertSessionHasErrors([
        'am_end' => 'The morning end field must be a date after morning start.',
        'working_days' => 'The working days field is required.',
    ]);
});

it('is only available to system administrators', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();

    $this->actingAs($hrOfficer)->get(route('admin.work-schedule.edit'))->assertForbidden();
});
