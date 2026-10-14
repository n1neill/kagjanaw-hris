<?php

use App\Enums\Role;
use App\Models\DailyTimeRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 18:00:00'));
});

it('totals each employee\'s month for HR', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();
    $employee = Employee::factory()->create(['last_name' => 'Santos', 'date_hired' => '2020-01-06']);
    DailyTimeRecord::factory()->for($employee)->late(30)->create(['work_date' => '2026-10-05']);
    DailyTimeRecord::factory()->for($employee)->absent()->create(['work_date' => '2026-10-06']);
    DailyTimeRecord::factory()->for($employee)->create(['work_date' => '2026-10-07', 'undertime_minutes' => 45]);

    $response = $this->actingAs($hrOfficer)->get(route('attendance.summary', ['month' => '2026-10']));

    $row = $response->viewData('rows')->firstWhere('employee.id', $employee->id);
    expect($row)->toMatchArray([
        'working_days' => 3,
        'present_days' => 2,
        'absent_days' => 1.0,
        'late_count' => 1,
        'late_minutes' => 30,
        'undertime_minutes' => 45,
        'missing_punch_days' => 0,
    ]);
});

it('limits department heads to their own department regardless of the filter', function () {
    $department = Department::factory()->create();
    $head = Employee::factory()->withAccount(Role::DepartmentHead)->create(['department_id' => $department->id, 'date_hired' => '2020-01-06']);
    $staff = Employee::factory()->create(['department_id' => $department->id, 'date_hired' => '2020-01-06']);
    $outsider = Employee::factory()->create(['date_hired' => '2020-01-06']);

    $response = $this->actingAs($head->user)->get(route('attendance.summary', ['department' => $outsider->department_id]));

    $ids = $response->viewData('rows')->pluck('employee.id')->all();
    expect($ids)->toContain($staff->id, $head->id)->not->toContain($outsider->id);
});

it('leaves out employees not in service during the month', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();
    $futureHire = Employee::factory()->create(['date_hired' => '2026-11-03']);

    $response = $this->actingAs($hrOfficer)->get(route('attendance.summary', ['month' => '2026-10']));

    expect($response->viewData('rows')->pluck('employee.id'))->not->toContain($futureHire->id);
});

it('exports the summary as CSV', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();
    $employee = Employee::factory()->create(['employee_number' => '2021-0230', 'date_hired' => '2020-01-06']);
    DailyTimeRecord::factory()->for($employee)->late(20)->create(['work_date' => '2026-10-05']);

    $response = $this->actingAs($hrOfficer)->get(route('attendance.summary.export', ['month' => '2026-10', 'format' => 'csv']));

    $response->assertOk()->assertDownload('attendance-summary-2026-10.csv');
    $lines = explode("\n", trim($response->streamedContent()));
    expect($lines[0])->toContain('Attendance Summary')
        ->and($lines[2])->toBe('"Employee ID",Name,Department,"Working days",Present,"Absences (days)","Times late","Late (minutes)","Undertime (minutes)","Missing punches"')
        ->and($lines[3])->toStartWith('2021-0230,')
        ->and($lines[3])->toEndWith(',1,1,0,1,20,0,0');
});

it('forbids employees', function () {
    $employee = Employee::factory()->withAccount()->create();

    $this->actingAs($employee->user)->get(route('attendance.summary'))->assertForbidden();
});
