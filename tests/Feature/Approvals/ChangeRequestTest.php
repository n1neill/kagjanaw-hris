<?php

use App\Enums\ChangeAction;
use App\Enums\ChangeRequestStatus;
use App\Enums\PayrollRunStatus;
use App\Enums\Permission;
use App\Models\AllowanceType;
use App\Models\Announcement;
use App\Models\ChangeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Holiday;
use App\Models\Position;
use App\Models\User;
use App\Notifications\ChangeRequestNotification;
use App\Notifications\PayrollReviewRequestedNotification;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * An employee account the System Administrator granted an HR permission on top of its role.
 */
function delegatedUser(Permission $permission): User
{
    $user = Employee::factory()->withAccount()->create()->user;
    $user->givePermissionTo($permission->value);

    return $user->fresh();
}

/**
 * @return array<string, mixed>
 */
function newEmployeeInput(): array
{
    return [
        'last_name' => 'Santos',
        'first_name' => 'Liza',
        'sex' => 'female',
        'civil_status' => 'single',
        'birth_date' => '1995-03-02',
        'department_id' => Department::factory()->create()->id,
        'position_id' => Position::factory()->create(['salary_grade' => 11])->id,
        'employment_type_id' => EmploymentType::factory()->create()->id,
        'salary_grade' => '11',
        'salary_step' => '1',
        'date_hired' => '2026-01-05',
        'employment_status' => 'active',
        'tin' => '123-456-789-000',
    ];
}

beforeEach(function () {
    Storage::fake('local');
});

describe('submitting', function () {
    it('holds a new employee added through granted access until an HR Officer approves it', function () {
        Notification::fake();
        $hrOfficer = User::factory()->hrOfficer()->create();
        $delegate = delegatedUser(Permission::ManageEmployees);

        $response = $this->actingAs($delegate)->post(route('employees.store'), newEmployeeInput());

        $response->assertRedirect(route('employees.index'))
            ->assertSessionHas('status', 'Your change was sent to an HR Officer for approval. It will be recorded once approved.');
        expect(Employee::query()->where('last_name', 'Santos')->exists())->toBeFalse();

        $change = ChangeRequest::sole();
        expect($change->action)->toBe(ChangeAction::CreateEmployee)
            ->and($change->status)->toBe(ChangeRequestStatus::Pending)
            ->and($change->requested_by)->toBe($delegate->id)
            ->and($change->description)->toBe('Add employee: Liza Santos');
        Notification::assertSentTo($hrOfficer, ChangeRequestNotification::class);
        Notification::assertNotSentTo($delegate, ChangeRequestNotification::class);
    });

    it('encrypts the submitted values at rest', function () {
        $delegate = delegatedUser(Permission::ManageEmployees);

        $this->actingAs($delegate)->post(route('employees.store'), newEmployeeInput());

        $stored = DB::table('change_requests')->value('payload');
        expect($stored)->not->toContain('123456789000')
            ->and(ChangeRequest::sole()->payload['tin'])->toBe('123456789000');
    });

    it('applies changes at once for a user who holds the permission through their role', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $this->actingAs($hrOfficer)->post(route('reference-data.departments.store'), ['code' => 'MHO', 'name' => 'Municipal Health Office', 'is_active' => '1']);

        $this->assertDatabaseHas('departments', ['code' => 'MHO']);
        expect(ChangeRequest::count())->toBe(0);
    });

    it('still validates the input before submitting it', function () {
        $delegate = delegatedUser(Permission::ManageReferenceData);

        $response = $this->actingAs($delegate)->post(route('reference-data.departments.store'), ['code' => '', 'name' => '']);

        $response->assertSessionHasErrors(['code', 'name']);
        expect(ChangeRequest::count())->toBe(0);
    });

    it('tells a user with granted access that their changes need approval', function () {
        $delegate = delegatedUser(Permission::ManageWorkCalendar);

        $response = $this->actingAs($delegate)->get(route('work-calendar.holidays.index'));

        $response->assertSee('You are using additional access (Manage the work calendar).');
    });
});

describe('approving', function () {
    it('records the new employee and tells the submitter', function () {
        Notification::fake();
        $hrOfficer = User::factory()->hrOfficer()->create();
        $delegate = delegatedUser(Permission::ManageEmployees);
        $this->actingAs($delegate)->post(route('employees.store'), newEmployeeInput());
        $change = ChangeRequest::sole();

        $response = $this->actingAs($hrOfficer)->post(route('approvals.approve', $change), ['remarks' => 'Checked against the appointment.']);

        $response->assertRedirect(route('approvals.index'))
            ->assertSessionHas('status', 'Change approved and recorded: Add employee: Liza Santos.');
        expect(Employee::query()->where('last_name', 'Santos')->exists())->toBeTrue();
        $change->refresh();
        expect($change->status)->toBe(ChangeRequestStatus::Approved)
            ->and($change->reviewed_by)->toBe($hrOfficer->id)
            ->and($change->review_remarks)->toBe('Checked against the appointment.');
        Notification::assertSentTo($delegate, ChangeRequestNotification::class, fn ($notification): bool => $notification->status === ChangeRequestStatus::Approved);
    });

    it('shows current and proposed values for an edit, and applies the edit on approval', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $delegate = delegatedUser(Permission::ManageReferenceData);
        $department = Department::factory()->create(['code' => 'MHO', 'name' => 'Municipal Health Office']);

        $this->actingAs($delegate)->put(route('reference-data.departments.update', $department), [
            'code' => 'MHO',
            'name' => 'Rural Health Unit',
            'is_active' => '1',
        ]);

        expect($department->fresh()->name)->toBe('Municipal Health Office');
        $change = ChangeRequest::sole();

        $this->actingAs($hrOfficer)->get(route('approvals.show', $change))
            ->assertSeeInOrder(['Current', 'Proposed'])
            ->assertSeeInOrder(['Name', 'Municipal Health Office', 'Rural Health Unit']);

        $this->actingAs($hrOfficer)->post(route('approvals.approve', $change));

        expect($department->fresh()->name)->toBe('Rural Health Unit');
    });

    it('applies a change from each module as submitted', function (Permission $permission, Closure $submit, Closure $assertApplied) {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $delegate = delegatedUser($permission);

        $submit($this->actingAs($delegate));
        $change = ChangeRequest::sole();

        $this->actingAs($hrOfficer)->post(route('approvals.approve', $change))->assertSessionHasNoErrors();

        expect($change->fresh()->status)->toBe(ChangeRequestStatus::Approved);
        $assertApplied($delegate);
    })->with([
        'holiday' => [
            Permission::ManageWorkCalendar,
            fn ($test) => $test->post(route('work-calendar.holidays.store'), ['date' => '2026-12-08', 'name' => 'Feast of the Immaculate Conception', 'type' => 'special-non-working']),
            fn () => expect(Holiday::query()->where('name', 'Feast of the Immaculate Conception')->exists())->toBeTrue(),
        ],
        'allowance type' => [
            Permission::ProcessPayroll,
            fn ($test) => $test->post(route('payroll.allowances.store'), ['code' => 'HAZ', 'name' => 'Hazard Pay', 'monthly_amount' => '1500.00', 'coverage' => 'assigned', 'is_taxable' => '1', 'is_active' => '1']),
            fn () => expect(AllowanceType::query()->where('code', 'HAZ')->exists())->toBeTrue(),
        ],
        'announcement' => [
            Permission::ManageAnnouncements,
            fn ($test) => $test->post(route('announcements.store'), ['title' => 'Flag ceremony', 'body' => 'Monday, 8:00 AM.', 'audience' => 'everyone', 'action' => 'publish']),
            fn (User $delegate) => expect(Announcement::query()->where('title', 'Flag ceremony')->where('created_by', $delegate->id)->whereNotNull('published_at')->exists())->toBeTrue(),
        ],
    ]);

    it('keeps an uploaded file until approval, then attaches it to the 201 file', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $delegate = delegatedUser(Permission::ManageEmployees);
        $employee = Employee::factory()->create();

        $this->actingAs($delegate)->post(route('employees.documents.store', $employee), [
            'document_type' => 'appointment',
            'file' => UploadedFile::fake()->create('appointment.pdf', 120, 'application/pdf'),
        ]);

        $change = ChangeRequest::sole();
        $pendingPath = $change->files['file']['path'];
        expect($employee->documents()->count())->toBe(0);
        Storage::disk('local')->assertExists($pendingPath);
        $this->actingAs($hrOfficer)->get(route('approvals.file', [$change, 'file']))->assertDownload('appointment.pdf');

        $this->actingAs($hrOfficer)->post(route('approvals.approve', $change));

        $document = $employee->documents()->sole();
        expect($document->original_name)->toBe('appointment.pdf')
            ->and($document->uploaded_by)->toBe($delegate->id);
        Storage::disk('local')->assertExists($document->path);
        Storage::disk('local')->assertMissing($pendingPath);
    });

    it('leaves the change pending with the reason when it no longer fits the records', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $delegate = delegatedUser(Permission::ManageEmployees);
        $change = ChangeRequest::factory()->create([
            'action' => ChangeAction::UpdateEmployee,
            'requested_by' => $delegate->id,
            'payload' => ['last_name' => 'Gone'],
            'subject_type' => (new Employee)->getMorphClass(),
            'subject_id' => 999999,
        ]);

        $response = $this->actingAs($hrOfficer)->post(route('approvals.approve', $change));

        $response->assertRedirect(route('approvals.show', $change))
            ->assertSessionHasErrors(['change' => 'The change could not be applied: The record this change concerns no longer exists.']);
        expect($change->fresh())
            ->status->toBe(ChangeRequestStatus::Pending)
            ->failure_message->toBe('The record this change concerns no longer exists.');
    });

    it('reports a validation failure at approval and keeps the change pending', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $delegate = delegatedUser(Permission::ProcessPayroll);
        $this->actingAs($delegate)->post(route('payroll.rates.tax.store'), ['effective_from' => '2027-01-01']);
        $change = ChangeRequest::sole();
        $this->actingAs($hrOfficer)->post(route('payroll.rates.tax.store'), ['effective_from' => '2027-01-01']);

        $this->actingAs($hrOfficer)->post(route('approvals.approve', $change))
            ->assertSessionHasErrors('change');

        expect($change->fresh()->status)->toBe(ChangeRequestStatus::Pending)
            ->and($change->fresh()->failure_message)->toBe('A tax table effective on that date already exists.');
    });

    it('does not let the submitter approve their own change', function () {
        $delegate = delegatedUser(Permission::ManageReferenceData);
        $change = ChangeRequest::factory()->create(['requested_by' => $delegate->id]);

        $response = $this->actingAs($delegate)->post(route('approvals.approve', $change));

        $response->assertForbidden();
        expect($change->fresh()->status)->toBe(ChangeRequestStatus::Pending);
    });

    it('does not apply a change twice', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $change = ChangeRequest::factory()->approved()->create();

        $response = $this->actingAs($hrOfficer)->post(route('approvals.approve', $change));

        $response->assertForbidden();
        expect(Department::query()->where('name', $change->payload['name'])->exists())->toBeFalse();
    });
});

describe('rejecting and withdrawing', function () {
    it('rejects a change with the reason, recording nothing', function () {
        Notification::fake();
        $hrOfficer = User::factory()->hrOfficer()->create();
        $delegate = delegatedUser(Permission::ManageEmployees);
        $this->actingAs($delegate)->post(route('employees.store'), newEmployeeInput());
        $change = ChangeRequest::sole();

        $response = $this->actingAs($hrOfficer)->post(route('approvals.reject', $change), ['remarks' => 'Duplicate of an existing record.']);

        $response->assertRedirect(route('approvals.index'));
        expect(Employee::query()->where('last_name', 'Santos')->exists())->toBeFalse()
            ->and($change->fresh()->status)->toBe(ChangeRequestStatus::Rejected)
            ->and($change->fresh()->review_remarks)->toBe('Duplicate of an existing record.');
        Notification::assertSentTo($delegate, ChangeRequestNotification::class, fn ($notification): bool => $notification->status === ChangeRequestStatus::Rejected);
    });

    it('requires a reason to reject', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $change = ChangeRequest::factory()->create();

        $this->actingAs($hrOfficer)->post(route('approvals.reject', $change), ['remarks' => ''])
            ->assertSessionHasErrors('remarks');

        expect($change->fresh()->status)->toBe(ChangeRequestStatus::Pending);
    });

    it('lets the submitter withdraw a pending change and removes its files', function () {
        $delegate = delegatedUser(Permission::ManageAttendance);
        $this->actingAs($delegate)->post(route('attendance.import.store'), [
            'file' => UploadedFile::fake()->createWithContent('punches.csv', "employee_number,logged_at\n"),
        ]);
        $change = ChangeRequest::sole();
        $path = $change->files['file']['path'];

        $response = $this->actingAs($delegate)->post(route('approvals.cancel', $change));

        $response->assertRedirect(route('approvals.index'));
        expect($change->fresh()->status)->toBe(ChangeRequestStatus::Cancelled);
        Storage::disk('local')->assertMissing($path);
    });
});

describe('the approvals page', function () {
    it('shows HR Officers the queue and submitters only their own changes', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $delegate = delegatedUser(Permission::ManageReferenceData);
        ChangeRequest::factory()->create(['requested_by' => $delegate->id, 'description' => 'Add department: Mine']);
        ChangeRequest::factory()->create(['description' => 'Add department: Someone else']);

        $this->actingAs($hrOfficer)->get(route('approvals.index'))
            ->assertSee('Waiting for approval')
            ->assertSee('Add department: Mine')
            ->assertSee('Add department: Someone else');

        $this->actingAs($delegate)->get(route('approvals.index'))
            ->assertSee('My submissions')
            ->assertSee('Add department: Mine')
            ->assertDontSee('Add department: Someone else');
    });

    it('hides another user\'s change from a submitter', function () {
        $delegate = delegatedUser(Permission::ManageReferenceData);
        $change = ChangeRequest::factory()->create();

        $this->actingAs($delegate)->get(route('approvals.show', $change))->assertNotFound();
    });
});

describe('payroll', function () {
    beforeEach(function () {
        $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00:00'));
    });

    it('lets a user with granted payroll access compute a run but leaves approval and release to an HR Officer', function () {
        Notification::fake();
        Queue::fake();
        $hrOfficer = User::factory()->hrOfficer()->create();
        $delegate = delegatedUser(Permission::ProcessPayroll);

        $this->actingAs($delegate)->post(route('payroll.runs.store'), ['month' => '2026-10', 'half' => 1, 'pay_basis' => 'salary-grade'])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($hrOfficer, PayrollReviewRequestedNotification::class);
    });

    it('forbids a user with granted payroll access from approving, reopening or releasing', function (string $action, PayrollRunStatus $status) {
        $delegate = delegatedUser(Permission::ProcessPayroll);
        $run = computePayroll(2026, 10, 1);
        $run->forceFill(['status' => $status])->save();

        $this->actingAs($delegate)->get(route('payroll.runs.show', $run))
            ->assertSee('An HR Officer reviews, approves and releases this payroll.');

        $this->actingAs($delegate)->post(route("payroll.runs.{$action}", $run))->assertForbidden();
        expect($run->fresh()->status)->toBe($status);
    })->with([
        'approve' => ['approve', PayrollRunStatus::Computed],
        'reopen' => ['reopen', PayrollRunStatus::Approved],
        'release' => ['release', PayrollRunStatus::Approved],
    ]);

    it('still lets an HR Officer approve a run a delegate computed', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $delegate = delegatedUser(Permission::ProcessPayroll);
        Employee::factory()->create(['salary_grade' => 11, 'salary_step' => 1, 'date_hired' => '2020-01-06']);
        $run = computePayroll(2026, 10, 1, hrOfficer: $delegate);

        $this->actingAs($hrOfficer)->post(route('payroll.runs.approve', $run))->assertSessionHasNoErrors();

        expect($run->fresh()->status)->toBe(PayrollRunStatus::Approved);
    });
});
