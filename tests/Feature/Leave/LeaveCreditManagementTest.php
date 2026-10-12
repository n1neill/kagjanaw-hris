<?php

use App\Enums\LeaveLedgerEntryType;
use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Leave\LeaveLedger;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00:00'));
});

describe('leave card', function () {
    it('shows employees their own leave card', function () {
        $employee = Employee::factory()->withAccount()->create();
        grantLeaveCredits($employee, vacation: '12.500');

        $response = $this->actingAs($employee->user)->get(route('leave.card', $employee));

        $response->assertOk()->assertSee('Opening balance')->assertSee('12.500');
    });

    it('hides other employees\' leave cards', function (string $viewer) {
        $department = Department::factory()->create();
        $employee = Employee::factory()->withAccount()->create(['department_id' => $department->id]);
        $user = match ($viewer) {
            'colleague' => Employee::factory()->withAccount()->create(['department_id' => $department->id])->user,
            'department head' => Employee::factory()->withAccount(Role::DepartmentHead)->create(['department_id' => $department->id])->user,
            'system administrator' => User::factory()->systemAdministrator()->create(),
        };

        $this->actingAs($user)->get(route('leave.card', $employee))->assertNotFound();
    })->with(['colleague', 'department head', 'system administrator']);

    it('lets HR post an adjustment with a reason', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create();
        grantLeaveCredits($employee, vacation: '5.000');

        $response = $this->actingAs($hrOfficer)->post(route('leave.adjustments.store', $employee), [
            'ledger' => 'VL',
            'days' => '-1.250',
            'entry_date' => '2026-10-07',
            'reason' => 'Undeclared absence on Sept 3 found on audit.',
        ]);

        $response->assertRedirect(route('leave.card', $employee))
            ->assertSessionHas('status', '1.250 days deducted from vacation leave.');
        $entry = $employee->leaveLedgerEntries()->where('entry_type', LeaveLedgerEntryType::Adjustment)->sole();
        expect($entry->balance_after)->toBe('3.750')
            ->and($entry->remarks)->toBe('Undeclared absence on Sept 3 found on audit.')
            ->and($entry->created_by)->toBe($hrOfficer->id);
    });

    it('rejects a zero adjustment', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($hrOfficer)->post(route('leave.adjustments.store', $employee), [
            'ledger' => 'SL', 'days' => '0', 'entry_date' => '2026-10-07', 'reason' => 'Nothing.',
        ]);

        $response->assertSessionHasErrors(['days' => 'Enter the number of days to add (positive) or deduct (negative).']);
    });

    it('does not let employees adjust their own balance', function () {
        $employee = Employee::factory()->withAccount()->create();

        $response = $this->actingAs($employee->user)->post(route('leave.adjustments.store', $employee), [
            'ledger' => 'VL', 'days' => '100', 'entry_date' => '2026-10-07', 'reason' => 'Bonus.',
        ]);

        $response->assertForbidden();
        expect($employee->leaveLedgerEntries()->count())->toBe(0);
    });

    it('issues a Certificate of Leave Credits as a PDF to HR only', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->withAccount()->create();
        grantLeaveCredits($employee);

        $this->actingAs($hrOfficer)->get(route('leave.certificate', [$employee, 'purpose' => 'loan application']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($employee->user)->get(route('leave.certificate', $employee))->assertForbidden();
    });
});

describe('opening balances import', function () {
    it('imports balances as the first line of each leave card and reports bad rows', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create(['employee_number' => '2021-0230', 'date_hired' => '2021-01-04']);
        $alreadyStarted = Employee::factory()->create(['employee_number' => '2019-0005']);
        grantLeaveCredits($alreadyStarted);
        $csv = "employee_number,vacation_leave,sick_leave\n2021-0230,45.250,60\n2019-0005,1,1\n9999-0000,1,1\n2021-0230,2,2\n";

        $response = $this->actingAs($hrOfficer)->post(route('leave.opening-balances.store'), [
            'as_of' => '2026-08-31',
            'file' => UploadedFile::fake()->createWithContent('balances.csv', $csv),
        ]);

        $response->assertRedirect(route('leave.opening-balances.create'));
        $result = session('importResult');
        expect($result['imported'])->toBe(1)
            ->and($result['errors'])->toBe([
                'Row 3: 2019-0005 already has leave card entries; use an adjustment instead.',
                'Row 4: no employee has ID 9999-0000.',
                'Row 5: employee 2021-0230 appears more than once.',
            ]);
        $ledger = app(LeaveLedger::class);
        expect($ledger->balance($employee, LeaveType::vacation()))->toBe(45250)
            ->and($ledger->balance($employee, LeaveType::sick()))->toBe(60000)
            ->and($employee->leaveLedgerEntries()->first()->entry_date->toDateString())->toBe('2026-08-31');
    });

    it('requires the cut-off to be a month end', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $response = $this->actingAs($hrOfficer)->post(route('leave.opening-balances.store'), [
            'as_of' => '2026-08-15',
            'file' => UploadedFile::fake()->createWithContent('balances.csv', "employee_number,vacation_leave,sick_leave\n"),
        ]);

        $response->assertSessionHasErrors(['as_of' => 'Choose the last day of a month, e.g. Aug 31, 2026.']);
    });

    it('is only available to HR', function (Role $role) {
        $user = User::factory()->withRole($role)->create();

        $this->actingAs($user)->get(route('leave.opening-balances.create'))->assertForbidden();
    })->with([Role::Employee, Role::DepartmentHead, Role::SystemAdministrator]);
});

describe('monthly posting', function () {
    it('posts last month\'s credits when HR validates a DTR', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);
        grantLeaveCredits($employee, vacation: '10.000');
        validatedMonthOfDtr($employee, '2026-09', fn (CarbonImmutable $date): array => $date->day === 1 ? ['late_minutes' => 30] : []);
        $employee->dailyTimeRecords()->update(['validated_at' => null]);

        $response = $this->actingAs($hrOfficer)->post(route('attendance.dtr.validate', [$employee, 'month' => '2026-09']));

        $response->assertSessionHas('status', '30 days of September 2026 validated. Leave credits posted: 1.250 days earned; 0.062 days deducted from vacation leave for tardiness and undertime.');
        expect(app(LeaveLedger::class)->balance($employee, LeaveType::vacation()))->toBe(11188);
    });

    it('posts every ready employee from the posting page', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $ready = Employee::factory()->create(['date_hired' => '2020-01-06']);
        $waiting = Employee::factory()->create(['date_hired' => '2020-01-06', 'last_name' => 'Waiting-Person']);
        grantLeaveCredits($ready);
        grantLeaveCredits($waiting);
        validatedMonthOfDtr($ready, '2026-09');

        $this->actingAs($hrOfficer)->get(route('leave.posting.index', ['month' => '2026-09']))
            ->assertSee('Waiting-Person')
            ->assertSee('The DTR for the month is not fully validated.');

        $response = $this->actingAs($hrOfficer)->post(route('leave.posting.store', ['month' => '2026-09']));

        $response->assertSessionHas('status', 'Leave credits for September 2026: 1 employees posted, 0 already up to date, 1 not ready.');
    });

    it('runs from the scheduler command', function () {
        $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);
        grantLeaveCredits($employee);
        validatedMonthOfDtr($employee, '2026-09');

        $this->artisan('leave:post-credits')
            ->expectsOutput('September 2026: 1 employees posted, 0 already up to date, 0 not ready.')
            ->assertSuccessful();
    });
});

describe('leave types', function () {
    it('lets HR change a leave type\'s rules', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $type = LeaveType::query()->where('code', 'SPL')->sole();

        $this->actingAs($hrOfficer)->put(route('leave.types.update', $type), [
            'code' => 'SPL', 'name' => 'Special Privilege Leave', 'credit_source' => 'annual-allotment',
            'annual_days' => '5', 'advance_notice_days' => '3', 'allows_half_day' => '1', 'is_active' => '1',
        ])->assertRedirect(route('leave.types.index'));

        expect($type->refresh()->annual_days)->toBe('5.000')
            ->and($type->advance_notice_days)->toBe(3);
    });

    it('keeps vacation leave active and charged to vacation credits', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $type = LeaveType::vacation();

        $response = $this->actingAs($hrOfficer)->put(route('leave.types.update', $type), [
            'code' => 'XX', 'name' => 'Vacation Leave', 'credit_source' => 'not-charged',
            'advance_notice_days' => '5',
        ]);

        $response->assertSessionHasErrors([
            'credit_source' => 'The credits this leave type is charged to cannot be changed.',
            'is_active' => 'This leave type cannot be deactivated.',
        ]);
        expect($type->refresh()->code)->toBe('VL');
    });
});

describe('leave utilization', function () {
    it('totals approved leave by type for the period and exports it', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        LeaveRequest::factory()->approved()->between('2026-09-07', '2026-09-09')->create();
        LeaveRequest::factory()->between('2026-09-14', '2026-09-14')->create();

        $response = $this->actingAs($hrOfficer)->get(route('leave.reports.utilization', ['year' => 2026, 'month' => 9]));

        $response->assertViewHas('totals', ['employees' => 1, 'days' => 3000, 'with_pay' => 3000, 'without_pay' => 0]);
        $this->actingAs($hrOfficer)->get(route('leave.reports.utilization.export', ['year' => 2026, 'month' => 9]))
            ->assertOk()
            ->assertDownload('leave-utilization-2026-09-01-to-2026-09-30.csv');
    });
});
