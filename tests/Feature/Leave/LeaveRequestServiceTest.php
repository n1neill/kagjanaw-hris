<?php

use App\Enums\DtrStatus;
use App\Enums\LeaveEndorsementRoute;
use App\Enums\LeaveLedgerEntryType;
use App\Enums\LeavePortion;
use App\Enums\LeaveRequestStatus;
use App\Enums\Role;
use App\Enums\Sex;
use App\Models\DailyTimeRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Notifications\LeaveRequestNotification;
use App\Services\Leave\LeaveLedger;
use App\Services\Leave\LeaveRequestService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    // Wednesday, October 7, 2026.
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00:00'));
    Notification::fake();
});

/**
 * An employee with an account in a department that has a department head.
 *
 * @return array{employee: Employee, head: Employee}
 */
function staffWithDepartmentHead(array $employeeAttributes = []): array
{
    $department = Department::factory()->create();

    return [
        'head' => Employee::factory()->withAccount(Role::DepartmentHead)->create(['department_id' => $department->id]),
        'employee' => Employee::factory()->withAccount()->create(['department_id' => $department->id, 'date_hired' => '2020-01-06', ...$employeeAttributes]),
    ];
}

function fileLeave(Employee $employee, string $code, string $from, string $to, LeavePortion $portion = LeavePortion::FullDay, ?UploadedFile $attachment = null): LeaveRequest
{
    return app(LeaveRequestService::class)->file(
        $employee,
        LeaveType::query()->where('code', $code)->sole(),
        CarbonImmutable::parse($from),
        CarbonImmutable::parse($to),
        $portion,
        'Personal reasons.',
        $attachment,
        $employee->user,
    );
}

function leaveErrors(callable $action): array
{
    try {
        $action();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

describe('filing', function () {
    it('counts working days only and sends the application to the department head', function () {
        ['employee' => $employee, 'head' => $head] = staffWithDepartmentHead();

        $request = fileLeave($employee, 'VL', '2026-10-16', '2026-10-20');

        expect($request->status)->toBe(LeaveRequestStatus::Pending)
            ->and($request->endorsement_route)->toBe(LeaveEndorsementRoute::DepartmentHead)
            ->and($request->days)->toBe('3.000')
            ->and($request->leaveDays()->pluck('date')->map->toDateString()->all())->toBe(['2026-10-16', '2026-10-19', '2026-10-20']);
        Notification::assertSentTo($head->user, LeaveRequestNotification::class);
        Notification::assertNotSentTo($employee->user, LeaveRequestNotification::class);
    });

    it('sends a department head\'s own leave to the Municipal Mayor', function () {
        ['head' => $head] = staffWithDepartmentHead();
        $mayor = Employee::factory()->withAccount(Role::MunicipalMayor)->create();

        $request = fileLeave($head, 'VL', '2026-10-19', '2026-10-19');

        expect($request->endorsement_route)->toBe(LeaveEndorsementRoute::MunicipalMayor);
        Notification::assertSentTo($mayor->user, LeaveRequestNotification::class);
    });

    it('sends leave from an office without a department head to the Municipal Mayor', function () {
        $employee = Employee::factory()->withAccount()->create(['date_hired' => '2020-01-06']);
        Employee::factory()->withAccount(Role::MunicipalMayor)->create();

        $request = fileLeave($employee, 'VL', '2026-10-19', '2026-10-19');

        expect($request->endorsement_route)->toBe(LeaveEndorsementRoute::MunicipalMayor);
    });

    it('sends the Mayor\'s own leave straight to HR', function () {
        $mayor = Employee::factory()->withAccount(Role::MunicipalMayor)->create(['date_hired' => '2022-06-30']);
        $hrOfficer = User::factory()->hrOfficer()->create();

        $request = fileLeave($mayor, 'SPL', '2026-10-19', '2026-10-19');

        expect($request->status)->toBe(LeaveRequestStatus::Endorsed)
            ->and($request->endorsement_route)->toBe(LeaveEndorsementRoute::NotRequired);
        Notification::assertSentTo($hrOfficer, LeaveRequestNotification::class);
    });

    it('counts a half day as half a day', function () {
        ['employee' => $employee] = staffWithDepartmentHead();

        $request = fileLeave($employee, 'VL', '2026-10-19', '2026-10-19', LeavePortion::Afternoon);

        expect($request->days)->toBe('0.500')
            ->and($request->portion)->toBe(LeavePortion::Afternoon);
    });

    it('counts maternity leave in calendar days', function () {
        ['employee' => $employee] = staffWithDepartmentHead(['sex' => Sex::Female]);

        $request = fileLeave($employee, 'ML', '2026-10-12', '2027-01-24', attachment: UploadedFile::fake()->create('ultrasound.pdf', 50, 'application/pdf'));

        expect($request->days)->toBe('105.000');
    });

    it('rejects applications that break a leave rule', function (callable $file, string $field, string $message) {
        ['employee' => $employee] = staffWithDepartmentHead(['sex' => Sex::Male]);

        $errors = leaveErrors(fn () => $file($employee));

        expect($errors[$field] ?? [])->toBe([$message]);
        expect(LeaveRequest::count())->toBe(0);
    })->with([
        'vacation leave filed late' => [
            fn (Employee $employee) => fileLeave($employee, 'VL', '2026-10-09', '2026-10-09'),
            'date_from', 'Vacation Leave must be filed at least 5 days before the leave starts (on or before Oct 4, 2026).',
        ],
        'vacation leave in the past' => [
            fn (Employee $employee) => fileLeave($employee, 'VL', '2026-10-05', '2026-10-05'),
            'date_from', 'Vacation Leave must be filed before the leave starts.',
        ],
        'more special privilege leave than the yearly allotment' => [
            fn (Employee $employee) => fileLeave($employee, 'SPL', '2026-10-19', '2026-10-22'),
            'date_to', 'You have 3.000 days of Special Privilege Leave left for 2026; these dates cover 4.000.',
        ],
        'maternity leave for a male employee' => [
            fn (Employee $employee) => fileLeave($employee, 'ML', '2026-10-19', '2026-10-19'),
            'leave_type_id', 'Maternity Leave is only available to female employees.',
        ],
        'half day across several days' => [
            fn (Employee $employee) => fileLeave($employee, 'VL', '2026-10-19', '2026-10-20', LeavePortion::Morning),
            'portion', 'Half-day leave must start and end on the same date.',
        ],
        'only a weekend' => [
            fn (Employee $employee) => fileLeave($employee, 'VL', '2026-10-17', '2026-10-18'),
            'date_from', 'The selected dates are all rest days or holidays.',
        ],
        'long sick leave without a medical certificate' => [
            fn (Employee $employee) => fileLeave($employee, 'SL', '2026-09-28', '2026-10-06'),
            'attachment', 'Attach a medical certificate for sick leave of more than five days.',
        ],
        'more paternity leave than the law allows' => [
            fn (Employee $employee) => fileLeave($employee, 'PL', '2026-10-05', '2026-10-14', attachment: UploadedFile::fake()->create('birth.pdf', 50, 'application/pdf')),
            'date_to', 'Paternity Leave may cover at most 7.000 days per application; these dates cover 8.000.',
        ],
    ]);

    it('rejects dates already covered by another application', function () {
        ['employee' => $employee] = staffWithDepartmentHead();
        fileLeave($employee, 'VL', '2026-10-19', '2026-10-21');

        $errors = leaveErrors(fn () => fileLeave($employee, 'SPL', '2026-10-21', '2026-10-22'));

        expect($errors['date_from'])->toBe(['You already have a leave application covering Oct 21, 2026.']);
    });

    it('allows the dates again once the earlier application is cancelled', function () {
        ['employee' => $employee] = staffWithDepartmentHead();
        $first = fileLeave($employee, 'VL', '2026-10-19', '2026-10-21');
        app(LeaveRequestService::class)->cancel($first, $employee->user, null);

        $second = fileLeave($employee, 'VL', '2026-10-19', '2026-10-21');

        expect($second->status)->toBe(LeaveRequestStatus::Pending);
    });

    it('does not let employees who earn no credits file vacation leave', function () {
        $jobOrder = EmploymentType::factory()->create(['earns_leave_credits' => false]);
        ['employee' => $employee] = staffWithDepartmentHead(['employment_type_id' => $jobOrder->id]);

        $errors = leaveErrors(fn () => fileLeave($employee, 'VL', '2026-10-19', '2026-10-19'));

        expect($errors['leave_type_id'])->toBe(['Your employment type does not earn vacation or sick leave credits.']);
    });
});

describe('endorsement and approval', function () {
    it('notifies the employee and HR when the department head endorses', function () {
        ['employee' => $employee, 'head' => $head] = staffWithDepartmentHead();
        $hrOfficer = User::factory()->hrOfficer()->create();
        $request = fileLeave($employee, 'VL', '2026-10-19', '2026-10-21');

        $request = app(LeaveRequestService::class)->endorse($request, $head->user, 'Recommended for approval.');

        expect($request->status)->toBe(LeaveRequestStatus::Endorsed)
            ->and($request->endorsed_by)->toBe($head->user->id)
            ->and($request->endorsement_remarks)->toBe('Recommended for approval.');
        Notification::assertSentTo($employee->user, LeaveRequestNotification::class, fn ($notification) => $notification->event->value === 'endorsed');
        Notification::assertSentTo($hrOfficer, LeaveRequestNotification::class);
    });

    it('charges vacation leave on approval and records it on the leave card', function () {
        ['employee' => $employee, 'head' => $head] = staffWithDepartmentHead();
        $hrOfficer = User::factory()->hrOfficer()->create();
        grantLeaveCredits($employee, vacation: '10.000');
        $service = app(LeaveRequestService::class);
        $request = $service->endorse(fileLeave($employee, 'VL', '2026-10-19', '2026-10-21'), $head->user, null);

        $request = $service->approve($request, $hrOfficer, 'Approved.');

        expect($request->status)->toBe(LeaveRequestStatus::Approved)
            ->and($request->days_with_pay)->toBe('3.000')
            ->and($request->days_without_pay)->toBe('0.000')
            ->and(app(LeaveLedger::class)->balance($employee, LeaveType::vacation()))->toBe(7000);
        $entry = $employee->leaveLedgerEntries()->where('entry_type', LeaveLedgerEntryType::Availment)->sole();
        expect($entry->days)->toBe('-3.000')
            ->and($entry->balance_after)->toBe('7.000')
            ->and($entry->particulars)->toBe('Vacation Leave, Oct 19–21, 2026');
        Notification::assertSentTo($employee->user, LeaveRequestNotification::class, fn ($notification) => $notification->event->value === 'approved');
    });

    it('charges sick leave to sick credits, then vacation credits, then without pay', function () {
        ['employee' => $employee, 'head' => $head] = staffWithDepartmentHead();
        $hrOfficer = User::factory()->hrOfficer()->create();
        grantLeaveCredits($employee, vacation: '1.000', sick: '1.500');
        $service = app(LeaveRequestService::class);
        $request = $service->endorse(fileLeave($employee, 'SL', '2026-10-01', '2026-10-05'), $head->user, null);

        $request = $service->approve($request, $hrOfficer, null);

        expect($request->days)->toBe('3.000')
            ->and($request->days_with_pay)->toBe('2.500')
            ->and($request->days_without_pay)->toBe('0.500')
            ->and(app(LeaveLedger::class)->balance($employee, LeaveType::sick()))->toBe(0)
            ->and(app(LeaveLedger::class)->balance($employee, LeaveType::vacation()))->toBe(0)
            ->and($request->leaveDays()->pluck('days_with_pay')->all())->toBe(['1.000', '1.000', '0.500']);
    });

    it('excuses approved past days on the DTR', function () {
        ['employee' => $employee, 'head' => $head] = staffWithDepartmentHead();
        $hrOfficer = User::factory()->hrOfficer()->create();
        grantLeaveCredits($employee);
        DailyTimeRecord::factory()->for($employee)->absent()->create(['work_date' => '2026-10-05']);
        $service = app(LeaveRequestService::class);
        $request = $service->endorse(fileLeave($employee, 'SL', '2026-10-05', '2026-10-05'), $head->user, null);

        $service->approve($request, $hrOfficer, null);

        $record = $employee->dailyTimeRecords()->sole();
        expect($record->status)->toBe(DtrStatus::OnLeave)
            ->and($record->absent_minutes)->toBe(0);
    });

    it('excuses leave on a day that HR corrected, keeping the corrected punches', function () {
        ['employee' => $employee, 'head' => $head] = staffWithDepartmentHead();
        $hrOfficer = User::factory()->hrOfficer()->create();
        grantLeaveCredits($employee);
        DailyTimeRecord::factory()->for($employee)->create([
            'work_date' => '2026-10-05',
            'pm_in' => null,
            'pm_out' => null,
            'absent_minutes' => 240,
            'worked_minutes' => 240,
            'is_adjusted' => true,
        ]);
        $service = app(LeaveRequestService::class);
        $request = $service->endorse(fileLeave($employee, 'SL', '2026-10-05', '2026-10-05', LeavePortion::Afternoon), $head->user, null);

        $service->approve($request, $hrOfficer, null);

        $record = $employee->dailyTimeRecords()->sole();
        expect($record->absent_minutes)->toBe(0)
            ->and($record->am_in)->toBe('07:55:00')
            ->and($record->pm_in)->toBeNull()
            ->and($record->is_adjusted)->toBeTrue();
    });

    it('restores the credits and the DTR when HR cancels an approved leave', function () {
        ['employee' => $employee, 'head' => $head] = staffWithDepartmentHead();
        $hrOfficer = User::factory()->hrOfficer()->create();
        grantLeaveCredits($employee, sick: '5.000');
        DailyTimeRecord::factory()->for($employee)->absent()->create(['work_date' => '2026-10-05']);
        $service = app(LeaveRequestService::class);
        $request = $service->approve($service->endorse(fileLeave($employee, 'SL', '2026-10-05', '2026-10-05'), $head->user, null), $hrOfficer, null);

        $request = $service->cancel($request, $hrOfficer, 'Reported for work.');

        expect($request->status)->toBe(LeaveRequestStatus::Cancelled)
            ->and(app(LeaveLedger::class)->balance($employee, LeaveType::sick()))->toBe(5000)
            ->and($employee->leaveLedgerEntries()->where('entry_type', LeaveLedgerEntryType::Reversal)->sole()->days)->toBe('1.000')
            ->and($employee->dailyTimeRecords()->sole()->status)->toBe(DtrStatus::Absent);
        Notification::assertSentTo($employee->user, LeaveRequestNotification::class, fn ($notification) => $notification->event->value === 'cancelled');
    });

    it('refuses to act on an application that someone else already decided', function () {
        ['employee' => $employee, 'head' => $head] = staffWithDepartmentHead();
        $hrOfficer = User::factory()->hrOfficer()->create();
        grantLeaveCredits($employee);
        $service = app(LeaveRequestService::class);
        $request = $service->endorse(fileLeave($employee, 'VL', '2026-10-19', '2026-10-19'), $head->user, null);
        $staleCopy = $request->fresh();
        $service->approve($request, $hrOfficer, null);

        $errors = leaveErrors(fn () => $service->approve($staleCopy, $hrOfficer, null));

        expect($errors['status'])->toBe(['This application is already approved.'])
            ->and(app(LeaveLedger::class)->balance($employee, LeaveType::vacation()))->toBe(9000);
    });

    it('does not let an endorser reject an application that was endorsed meanwhile', function () {
        ['employee' => $employee, 'head' => $head] = staffWithDepartmentHead();
        $request = fileLeave($employee, 'VL', '2026-10-19', '2026-10-19');
        $staleCopy = $request->fresh();
        $service = app(LeaveRequestService::class);
        $service->endorse($request, $head->user, null);

        $errors = leaveErrors(fn () => $service->reject($staleCopy, $head->user, 'Office audit that week.'));

        expect($errors['status'])->toBe(['This application is already endorsed to HR.'])
            ->and($request->fresh()->status)->toBe(LeaveRequestStatus::Endorsed)
            ->and($request->fresh()->decided_by)->toBeNull();
    });

    it('records the endorser\'s remarks when the application is rejected at endorsement', function () {
        ['employee' => $employee, 'head' => $head] = staffWithDepartmentHead();
        $request = fileLeave($employee, 'VL', '2026-10-19', '2026-10-19');

        $request = app(LeaveRequestService::class)->reject($request, $head->user, 'Office audit that week.');

        expect($request->status)->toBe(LeaveRequestStatus::Rejected)
            ->and($request->endorsement_remarks)->toBe('Office audit that week.')
            ->and($request->decided_by)->toBeNull();
        Notification::assertSentTo($employee->user, LeaveRequestNotification::class, fn ($notification) => $notification->event->value === 'rejected');
    });
});
