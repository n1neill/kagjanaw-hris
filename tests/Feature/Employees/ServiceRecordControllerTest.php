<?php

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeServiceRecord;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Models\User;

/**
 * A valid entry as HR would submit it.
 *
 * @return array<string, string>
 */
function serviceRecordPayload(array $overrides = []): array
{
    return [
        'service_from' => '2019-01-02',
        'service_to' => '2021-12-31',
        'designation' => 'Administrative Aide IV',
        'status' => 'Casual',
        'annual_salary' => '180000.00',
        'station' => 'Office of the Mayor',
        'branch' => 'Local',
        'leave_without_pay' => '',
        'separation_date' => '',
        'separation_cause' => '',
        'remarks' => '',
        ...$overrides,
    ];
}

describe('viewing', function () {
    it('lists the entries oldest first for HR', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create();
        EmployeeServiceRecord::factory()->for($employee)->current()->create(['designation' => 'Administrative Officer II', 'service_from' => '2022-01-03']);
        EmployeeServiceRecord::factory()->for($employee)->create(['designation' => 'Administrative Aide IV', 'service_from' => '2019-01-02', 'service_to' => '2021-12-31']);

        $response = $this->actingAs($hrOfficer)->get(route('employees.service-record.show', $employee));

        $response->assertOk()
            ->assertSeeInOrder(['Administrative Aide IV', 'Administrative Officer II'])
            ->assertSee(route('employees.service-record.create', $employee));
    });

    it('shows employees their own record without editing controls', function () {
        $employee = Employee::factory()->withAccount()->create();
        $entry = EmployeeServiceRecord::factory()->for($employee)->create(['designation' => 'Nurse I']);

        $response = $this->actingAs($employee->user)->get(route('my-service-record'));

        $response->assertOk()
            ->assertSeeText('My Service Record')
            ->assertSeeText('Nurse I')
            ->assertSee(route('employees.service-record.pdf', $employee))
            ->assertDontSee(route('employees.service-record.create', $employee))
            ->assertDontSee(route('employees.service-record.edit', [$employee, $entry]))
            ->assertDontSee(route('employees.service-record.destroy', [$employee, $entry]));
    });

    it('tells a user without an employee record to contact HR', function () {
        $user = User::factory()->employee()->create();

        $this->actingAs($user)->get(route('my-service-record'))
            ->assertOk()
            ->assertViewIs('employees.no-record');
    });

    it('hides another employee\'s service record as not found', function (string $route) {
        $employee = Employee::factory()->withAccount()->create();
        $colleague = Employee::factory()->create();

        $this->actingAs($employee->user)->get(route($route, $colleague))->assertNotFound();
    })->with(['employees.service-record.show', 'employees.service-record.pdf']);

    it('hides the service record from the employee\'s department head', function () {
        $department = Department::factory()->create();
        $head = Employee::factory()->withAccount(Role::DepartmentHead)->create(['department_id' => $department->id]);
        $employee = Employee::factory()->create(['department_id' => $department->id]);

        $this->actingAs($head->user)->get(route('employees.service-record.show', $employee))->assertNotFound();
    });
});

describe('pdf', function () {
    it('lets the employee print their own service record', function () {
        $employee = Employee::factory()->withAccount()->create();
        EmployeeServiceRecord::factory()->for($employee)->create();

        $this->actingAs($employee->user)->get(route('employees.service-record.pdf', $employee))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        expect(AuditLog::query()->where('event', AuditEvent::Downloaded)->exists())->toBeFalse();
    });

    it('logs HR downloading an employee\'s service record', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($hrOfficer)->get(route('employees.service-record.pdf', $employee))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        expect(AuditLog::query()->where('event', AuditEvent::Downloaded)->sole()->user_id)->toBe($hrOfficer->id);
    });
});

describe('managing', function () {
    it('prefills a new entry from the employee\'s current appointment', function () {
        $this->travelTo('2026-10-15');
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create([
            'position_id' => Position::factory()->create(['title' => 'Municipal Accountant'])->id,
            'employment_type_id' => EmploymentType::factory()->create(['name' => 'Permanent'])->id,
            'department_id' => Department::factory()->create(['name' => 'Municipal Accounting Office'])->id,
            'salary_grade' => 24,
            'salary_step' => 1,
            'date_hired' => '2020-01-06',
        ]);

        $response = $this->actingAs($hrOfficer)->get(route('employees.service-record.create', $employee));

        // SG 24 Step 1 at the default 70% LGU rate is ₱71,822.00 a month.
        $response->assertOk()
            ->assertSee('value="2020-01-06"', false)
            ->assertSee('value="Municipal Accountant"', false)
            ->assertSee('value="Permanent"', false)
            ->assertSee('value="Municipal Accounting Office"', false)
            ->assertSee('value="861864.00"', false);
    });

    it('adds an entry', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($hrOfficer)->post(route('employees.service-record.store', $employee), serviceRecordPayload());

        $response->assertRedirect(route('employees.service-record.show', $employee))
            ->assertSessionHas('status', 'Service record entry added.');
        $entry = $employee->serviceRecords()->sole();
        expect($entry->designation)->toBe('Administrative Aide IV')
            ->and($entry->service_to->toDateString())->toBe('2021-12-31')
            ->and($entry->annual_salary)->toBe('180000.00')
            ->and($entry->leave_without_pay)->toBeNull()
            ->and($entry->created_by)->toBe($hrOfficer->id);
    });

    it('updates an entry', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $entry = EmployeeServiceRecord::factory()->create();

        $response = $this->actingAs($hrOfficer)->put(
            route('employees.service-record.update', [$entry->employee, $entry]),
            serviceRecordPayload(['service_to' => '', 'designation' => 'Administrative Officer I']),
        );

        $response->assertRedirect(route('employees.service-record.show', $entry->employee));
        expect($entry->fresh()->only(['service_to', 'designation']))->toBe(['service_to' => null, 'designation' => 'Administrative Officer I']);
    });

    it('ends the service on the separation date', function (string $serviceTo) {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $entry = EmployeeServiceRecord::factory()->create();

        $this->actingAs($hrOfficer)->put(
            route('employees.service-record.update', [$entry->employee, $entry]),
            serviceRecordPayload(['service_to' => $serviceTo, 'separation_date' => '2021-06-30', 'separation_cause' => 'Resigned']),
        )->assertRedirect(route('employees.service-record.show', $entry->employee));

        $entry->refresh();
        expect($entry->service_to->toDateString())->toBe('2021-06-30')
            ->and($entry->separation_date->toDateString())->toBe('2021-06-30');
    })->with([
        'left blank' => '',
        'a different date' => '2021-12-31',
    ]);

    it('deletes an entry', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $entry = EmployeeServiceRecord::factory()->create();

        $this->actingAs($hrOfficer)->delete(route('employees.service-record.destroy', [$entry->employee, $entry]))
            ->assertRedirect(route('employees.service-record.show', $entry->employee));

        $this->assertModelMissing($entry);
    });

    it('does not reach an entry through another employee\'s URL', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $entry = EmployeeServiceRecord::factory()->create();
        $otherEmployee = Employee::factory()->create();

        $this->actingAs($hrOfficer)->delete(route('employees.service-record.destroy', [$otherEmployee, $entry]))
            ->assertNotFound();

        $this->assertModelExists($entry);
    });

    it('rejects an end of service before its start', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($hrOfficer)->post(route('employees.service-record.store', $employee), serviceRecordPayload(['service_to' => '2018-12-31']))
            ->assertSessionHasErrors(['service_to' => 'The end of service cannot be before its start.']);

        $this->assertDatabaseCount('employee_service_records', 0);
    });

    it('requires the cause when a separation date is given', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($hrOfficer)->post(route('employees.service-record.store', $employee), serviceRecordPayload(['separation_date' => '2021-12-31']))
            ->assertSessionHasErrors(['separation_cause' => 'Give the cause of separation.']);

        $this->assertDatabaseCount('employee_service_records', 0);
    });

    it('requires the period, designation and status', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($hrOfficer)->post(route('employees.service-record.store', $employee), [])
            ->assertSessionHasErrors(['service_from', 'designation', 'status']);
    });
});

describe('employees cannot change their own service record', function () {
    it('forbids them from adding an entry', function () {
        $employee = Employee::factory()->withAccount()->create();

        $this->actingAs($employee->user)->post(route('employees.service-record.store', $employee), serviceRecordPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('employee_service_records', 0);
    });

    it('forbids them from editing an entry', function () {
        $employee = Employee::factory()->withAccount()->create();
        $entry = EmployeeServiceRecord::factory()->for($employee)->create(['designation' => 'Nurse I']);

        $this->actingAs($employee->user)->put(route('employees.service-record.update', [$employee, $entry]), serviceRecordPayload(['designation' => 'Chief of Hospital']))
            ->assertForbidden();

        expect($entry->fresh()->designation)->toBe('Nurse I');
    });

    it('forbids them from deleting an entry', function () {
        $employee = Employee::factory()->withAccount()->create();
        $entry = EmployeeServiceRecord::factory()->for($employee)->create();

        $this->actingAs($employee->user)->delete(route('employees.service-record.destroy', [$employee, $entry]))
            ->assertForbidden();

        $this->assertModelExists($entry);
    });
});
