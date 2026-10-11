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

describe('show', function () {
    it('shows HR the month with punches, totals and correction links', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);
        DailyTimeRecord::factory()->for($employee)->late(25)->create(['work_date' => '2026-10-06']);

        $response = $this->actingAs($hrOfficer)->get(route('attendance.dtr.show', [$employee, 'month' => '2026-10']));

        $response->assertSee('8:25')
            ->assertSee('25 m')
            ->assertSee(route('attendance.dtr.edit', [$employee, '2026-10-06']))
            ->assertSee('Validate October');
    });

    it('lets a department head view the DTR of their own staff without correction tools', function () {
        $department = Department::factory()->create();
        $head = Employee::factory()->withAccount(Role::DepartmentHead)->create(['department_id' => $department->id]);
        $staff = Employee::factory()->create(['department_id' => $department->id, 'date_hired' => '2020-01-06']);
        DailyTimeRecord::factory()->for($staff)->create(['work_date' => '2026-10-06']);

        $response = $this->actingAs($head->user)->get(route('attendance.dtr.show', $staff));

        $response->assertOk()
            ->assertDontSee(route('attendance.dtr.edit', [$staff, '2026-10-06']))
            ->assertDontSee('Validate October');
    });

    it('hides the DTR from viewers outside the employee\'s scope', function (string $viewer) {
        $staff = Employee::factory()->withAccount()->create();
        $user = match ($viewer) {
            'head of another department' => Employee::factory()->withAccount(Role::DepartmentHead)->create()->user,
            'a colleague' => Employee::factory()->withAccount()->create(['department_id' => $staff->department_id])->user,
            'system administrator' => User::factory()->systemAdministrator()->create(),
        };

        $this->actingAs($user)->get(route('attendance.dtr.show', $staff))->assertNotFound();
        $this->actingAs($user)->get(route('attendance.dtr.pdf', $staff))->assertNotFound();
    })->with(['head of another department', 'a colleague', 'system administrator']);

    it('rejects a future month', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($hrOfficer)->get(route('attendance.dtr.show', [$employee, 'month' => '2026-11']));

        $response->assertSessionHasErrors(['month' => 'Choose the current month or an earlier one.']);
    });
});

describe('pdf', function () {
    it('renders Civil Service Form No. 48 for the employee', function () {
        $employee = Employee::factory()->withAccount()->create(['date_hired' => '2020-01-06']);
        DailyTimeRecord::factory()->for($employee)->create(['work_date' => '2026-10-06']);

        $response = $this->actingAs($employee->user)->get(route('attendance.dtr.pdf', [$employee, 'month' => '2026-10']));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        expect($response->headers->get('Content-Disposition'))->toContain("DTR-{$employee->employee_number}-2026-10.pdf")
            ->and($response->getContent())->toStartWith('%PDF');
    });
});

describe('my attendance', function () {
    it('shows employees their own month', function () {
        $employee = Employee::factory()->withAccount()->create(['date_hired' => '2020-01-06']);
        DailyTimeRecord::factory()->for($employee)->absent()->create(['work_date' => '2026-10-06']);

        $response = $this->actingAs($employee->user)->get(route('attendance.my', ['month' => '2026-10']));

        $response->assertSee('October 2026')->assertSee('Absent');
    });

    it('is not available to system administrators', function () {
        $admin = User::factory()->systemAdministrator()->create();

        $this->actingAs($admin)->get(route('attendance.my'))->assertForbidden();
    });
});
