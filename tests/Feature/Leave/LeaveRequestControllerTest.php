<?php

use App\Enums\LeaveEndorsementRoute;
use App\Enums\LeaveRequestStatus;
use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00:00'));
});

/**
 * A department with a head and two employees with accounts.
 *
 * @return array{department: Department, head: Employee, employee: Employee, colleague: Employee}
 */
function leaveOffice(): array
{
    $department = Department::factory()->create();

    return [
        'department' => $department,
        'head' => Employee::factory()->withAccount(Role::DepartmentHead)->create(['department_id' => $department->id]),
        'employee' => Employee::factory()->withAccount()->create(['department_id' => $department->id, 'date_hired' => '2020-01-06']),
        'colleague' => Employee::factory()->withAccount()->create(['department_id' => $department->id]),
    ];
}

describe('filing', function () {
    it('files an application and shows it to the employee', function () {
        ['employee' => $employee] = leaveOffice();

        $response = $this->actingAs($employee->user)->post(route('leave.requests.store'), [
            'leave_type_id' => LeaveType::vacation()->id,
            'date_from' => '2026-10-19',
            'date_to' => '2026-10-20',
            'portion' => 'full',
            'details' => 'Family vacation.',
        ]);

        $request = LeaveRequest::sole();
        $response->assertRedirect(route('leave.requests.show', $request))
            ->assertSessionHas('status', 'Your application was filed and sent for endorsement.');
        expect($request->employee_id)->toBe($employee->id)
            ->and($request->days)->toBe('2.000');
    });

    it('shows the leave rule that was broken', function () {
        ['employee' => $employee] = leaveOffice();

        $response = $this->actingAs($employee->user)->post(route('leave.requests.store'), [
            'leave_type_id' => LeaveType::vacation()->id,
            'date_from' => '2026-10-08',
            'portion' => 'full',
            'details' => 'Errand.',
        ]);

        $response->assertSessionHasErrors(['date_from' => 'Vacation Leave must be filed at least 5 days before the leave starts (on or before Oct 3, 2026).']);
        expect(LeaveRequest::count())->toBe(0);
    });

    it('rejects an empty form', function () {
        ['employee' => $employee] = leaveOffice();

        $response = $this->actingAs($employee->user)->post(route('leave.requests.store'), []);

        $response->assertSessionHasErrors([
            'leave_type_id' => 'The leave type field is required.',
            'date_from' => 'The first day field is required.',
            'details' => 'The details field is required.',
        ]);
    });

    it('stores the attachment privately', function () {
        Storage::fake('local');
        ['employee' => $employee] = leaveOffice();

        $this->actingAs($employee->user)->post(route('leave.requests.store'), [
            'leave_type_id' => LeaveType::sick()->id,
            'date_from' => '2026-09-28',
            'date_to' => '2026-10-06',
            'portion' => 'full',
            'details' => 'Dengue; confined at the district hospital.',
            'attachment' => UploadedFile::fake()->create('medical-certificate.pdf', 120, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $request = LeaveRequest::sole();
        Storage::disk('local')->assertExists($request->attachment_path);
        expect($request->attachment_path)->toStartWith("leave-requests/{$employee->id}/");
    });

    it('does not let a system account without an employee record apply', function () {
        $admin = User::factory()->systemAdministrator()->create();

        $response = $this->actingAs($admin)->get(route('leave.requests.create'));

        $response->assertForbidden();
    });
});

describe('who can see an application', function () {
    it('shows the application to its employee, their department head and HR', function (string $viewer) {
        ['employee' => $employee, 'head' => $head] = leaveOffice();
        $request = LeaveRequest::factory()->for($employee)->create();
        $user = match ($viewer) {
            'employee' => $employee->user,
            'department head' => $head->user,
            'hr officer' => User::factory()->hrOfficer()->create(),
        };

        $response = $this->actingAs($user)->get(route('leave.requests.show', $request));

        $response->assertOk()->assertSee('Vacation Leave');
    })->with(['employee', 'department head', 'hr officer']);

    it('hides the application from everyone else', function (string $viewer) {
        ['employee' => $employee, 'colleague' => $colleague] = leaveOffice();
        $request = LeaveRequest::factory()->for($employee)->create();
        $user = match ($viewer) {
            'a colleague' => $colleague->user,
            'another department head' => Employee::factory()->withAccount(Role::DepartmentHead)->create()->user,
            'the Mayor (not the endorser)' => Employee::factory()->withAccount(Role::MunicipalMayor)->create()->user,
            'the system administrator' => User::factory()->systemAdministrator()->create(),
        };

        $this->actingAs($user)->get(route('leave.requests.show', $request))->assertNotFound();
        $this->actingAs($user)->get(route('leave.requests.attachment', $request))->assertNotFound();
    })->with(['a colleague', 'another department head', 'the Mayor (not the endorser)', 'the system administrator']);

    it('lets the Mayor see the department heads\' applications sent to the Mayor', function () {
        ['head' => $head] = leaveOffice();
        $mayor = Employee::factory()->withAccount(Role::MunicipalMayor)->create();
        $request = LeaveRequest::factory()->for($head)->create(['endorsement_route' => LeaveEndorsementRoute::MunicipalMayor]);

        $this->actingAs($mayor->user)->get(route('leave.requests.show', $request))->assertOk();
    });

    it('limits HR\'s application list to HR', function (Role $role) {
        $user = User::factory()->withRole($role)->create();

        $this->actingAs($user)->get(route('leave.requests.index'))->assertForbidden();
    })->with([Role::Employee, Role::DepartmentHead, Role::MunicipalMayor, Role::SystemAdministrator]);

    it('lists endorsed applications for HR by default', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        LeaveRequest::factory()->endorsed()->create(['details' => 'x', 'employee_id' => Employee::factory()->create(['last_name' => 'Endorsed-Person'])->id]);
        LeaveRequest::factory()->create(['employee_id' => Employee::factory()->create(['last_name' => 'Pending-Person'])->id]);

        $response = $this->actingAs($hrOfficer)->get(route('leave.requests.index'));

        $response->assertSee('Endorsed-Person')->assertDontSee('Pending-Person');
    });
});

describe('acting on an application', function () {
    it('lets the department head endorse, and HR approve', function () {
        ['employee' => $employee, 'head' => $head] = leaveOffice();
        grantLeaveCredits($employee);
        $hrOfficer = User::factory()->hrOfficer()->create();
        $request = LeaveRequest::factory()->for($employee)->between('2026-10-19', '2026-10-20')->create();

        $this->actingAs($head->user)->post(route('leave.requests.endorse', $request), ['remarks' => 'OK.'])
            ->assertRedirect(route('leave.requests.show', $request));
        $this->actingAs($hrOfficer)->post(route('leave.requests.approve', $request))
            ->assertSessionHas('status', 'The application was approved.');

        expect($request->refresh()->status)->toBe(LeaveRequestStatus::Approved)
            ->and($request->days_with_pay)->toBe('2.000');
    });

    it('refuses actions by the wrong person', function (string $action, string $actor, LeaveRequestStatus $status) {
        ['employee' => $employee, 'head' => $head, 'colleague' => $colleague] = leaveOffice();
        $request = LeaveRequest::factory()->for($employee)->create(['status' => $status]);
        $user = match ($actor) {
            'employee' => $employee->user,
            'colleague' => $colleague->user,
            'department head' => $head->user,
            'hr officer' => User::factory()->hrOfficer()->create(),
        };

        $response = $this->actingAs($user)->post(route("leave.requests.{$action}", $request), ['remarks' => 'Because.']);

        $response->assertForbidden();
        expect($request->refresh()->status)->toBe($status);
    })->with([
        'employee endorses own' => ['endorse', 'employee', LeaveRequestStatus::Pending],
        'colleague endorses' => ['endorse', 'colleague', LeaveRequestStatus::Pending],
        'department head approves' => ['approve', 'department head', LeaveRequestStatus::Endorsed],
        'HR approves before endorsement' => ['approve', 'hr officer', LeaveRequestStatus::Pending],
        'colleague cancels' => ['cancel', 'colleague', LeaveRequestStatus::Pending],
        'employee cancels after approval' => ['cancel', 'employee', LeaveRequestStatus::Approved],
        'department head cancels' => ['cancel', 'department head', LeaveRequestStatus::Pending],
    ]);

    it('does not let an HR officer approve their own leave', function () {
        $hrEmployee = Employee::factory()->withAccount(Role::HrOfficer)->create();
        $request = LeaveRequest::factory()->for($hrEmployee)->endorsed()->create();

        $this->actingAs($hrEmployee->user)->post(route('leave.requests.approve', $request))->assertForbidden();
    });

    it('requires a reason to reject', function () {
        ['employee' => $employee, 'head' => $head] = leaveOffice();
        $request = LeaveRequest::factory()->for($employee)->create();

        $response = $this->actingAs($head->user)->post(route('leave.requests.reject', $request), ['remarks' => '']);

        $response->assertSessionHasErrors(['remarks' => 'Give the reason in the remarks.']);
        expect($request->refresh()->status)->toBe(LeaveRequestStatus::Pending);
    });

    it('lets the employee withdraw an application waiting for a decision', function () {
        ['employee' => $employee] = leaveOffice();
        $request = LeaveRequest::factory()->for($employee)->create();

        $this->actingAs($employee->user)->post(route('leave.requests.cancel', $request));

        expect($request->refresh()->status)->toBe(LeaveRequestStatus::Cancelled);
    });

    it('lists only the head\'s own department in the endorsement queue', function () {
        ['employee' => $employee, 'head' => $head] = leaveOffice();
        LeaveRequest::factory()->for($employee)->create();
        LeaveRequest::factory()->for(Employee::factory()->create(['last_name' => 'Other-Office']))->create();

        $response = $this->actingAs($head->user)->get(route('leave.endorsements'));

        $response->assertSee($employee->sortable_name)->assertDontSee('Other-Office');
    });
});

describe('notifications', function () {
    it('marks a notification read and opens the application', function () {
        ['employee' => $employee, 'head' => $head] = leaveOffice();
        $this->actingAs($employee->user)->post(route('leave.requests.store'), [
            'leave_type_id' => LeaveType::vacation()->id,
            'date_from' => '2026-10-19',
            'portion' => 'full',
            'details' => 'Errand.',
        ]);
        $notification = $head->user->notifications()->sole();

        $response = $this->actingAs($head->user)->get(route('notifications.open', $notification->id));

        $response->assertRedirect(route('leave.requests.show', LeaveRequest::sole(), absolute: false));
        expect($notification->refresh()->read_at)->not->toBeNull();
    });

    it('does not open another user\'s notification', function () {
        ['employee' => $employee, 'head' => $head] = leaveOffice();
        $this->actingAs($employee->user)->post(route('leave.requests.store'), [
            'leave_type_id' => LeaveType::vacation()->id,
            'date_from' => '2026-10-19',
            'portion' => 'full',
            'details' => 'Errand.',
        ]);
        $notification = $head->user->notifications()->sole();

        $this->actingAs($employee->user)->get(route('notifications.open', $notification->id))->assertNotFound();
        expect($notification->refresh()->read_at)->toBeNull();
    });

    it('shows unread notifications in the bell', function () {
        ['employee' => $employee, 'head' => $head] = leaveOffice();
        $this->actingAs($employee->user)->post(route('leave.requests.store'), [
            'leave_type_id' => LeaveType::vacation()->id,
            'date_from' => '2026-10-19',
            'portion' => 'full',
            'details' => 'Errand.',
        ]);

        $response = $this->actingAs($head->user)->get(route('dashboard'));

        $response->assertSee('View notifications (1 unread)')
            ->assertSee(e($employee->full_name).' applied for Vacation Leave, Oct 19, 2026 and needs your endorsement.', escape: false);
    });
});
