<?php

use App\Enums\DtrStatus;
use App\Enums\HolidayType;
use App\Enums\Role;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 18:00:00'));
});

it('adds a holiday and stops counting that day as an absence', function () {
    $admin = User::factory()->systemAdministrator()->create();
    $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);
    DailyTimeRecord::factory()->for($employee)->absent()->create(['work_date' => '2026-10-06']);

    $response = $this->actingAs($admin)->post(route('admin.holidays.store'), [
        'date' => '2026-10-06',
        'name' => 'Cagdianao Town Fiesta',
        'type' => HolidayType::Local->value,
    ]);

    $response->assertRedirect(route('admin.holidays.index'));
    $this->assertDatabaseHas('holidays', ['name' => 'Cagdianao Town Fiesta', 'type' => 'local']);
    $record = $employee->dailyTimeRecords()->sole();
    expect($record->status)->toBe(DtrStatus::Holiday)
        ->and($record->absent_minutes)->toBe(0);
});

it('restores the working day when a holiday is removed', function () {
    $admin = User::factory()->systemAdministrator()->create();
    $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);
    $holiday = Holiday::factory()->create(['date' => '2026-10-06']);
    DailyTimeRecord::factory()->for($employee)->create(['work_date' => '2026-10-06', 'status' => DtrStatus::Holiday, 'am_in' => null, 'am_out' => null, 'pm_in' => null, 'pm_out' => null]);

    $this->actingAs($admin)->delete(route('admin.holidays.destroy', $holiday));

    $this->assertModelMissing($holiday);
    expect($employee->dailyTimeRecords()->sole()->status)->toBe(DtrStatus::Absent);
});

it('rejects a second holiday on the same date', function () {
    $admin = User::factory()->systemAdministrator()->create();
    Holiday::factory()->create(['date' => '2026-12-25']);

    $response = $this->actingAs($admin)->post(route('admin.holidays.store'), [
        'date' => '2026-12-25',
        'name' => 'Christmas Day',
        'type' => HolidayType::Regular->value,
    ]);

    $response->assertSessionHasErrors(['date' => 'There is already a holiday on this date.']);
});

it('is only available to system administrators', function (Role $role) {
    $user = User::factory()->withRole($role)->create();

    $this->actingAs($user)->get(route('admin.holidays.index'))->assertForbidden();
})->with(['hr officer' => Role::HrOfficer, 'employee' => Role::Employee]);
