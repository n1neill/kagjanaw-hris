<?php

use App\Enums\DtrStatus;
use App\Enums\LeaveLedgerEntryType;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Leave\ForcedLeaveService;
use App\Services\Leave\LeaveCreditPostingService;
use App\Services\Leave\LeaveLedger;
use App\Services\Leave\LeavePostingResult;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00:00'));
});

function postSeptember(Employee $employee): LeavePostingResult
{
    return app(LeaveCreditPostingService::class)->postMonth($employee, CarbonImmutable::parse('2026-09-01'));
}

function balances(Employee $employee): array
{
    $ledger = app(LeaveLedger::class);

    return [
        'vacation' => $ledger->balance($employee, LeaveType::vacation()),
        'sick' => $ledger->balance($employee, LeaveType::sick()),
    ];
}

it('earns 1.250 days of each and deducts the month\'s tardiness from vacation leave', function () {
    $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);
    grantLeaveCredits($employee, vacation: '10.000', sick: '10.000', asOf: '2026-08-31');
    validatedMonthOfDtr($employee, '2026-09', fn (CarbonImmutable $date): array => $date->day === 7
        ? ['late_minutes' => 90, 'undertime_minutes' => 57]
        : []);

    $result = postSeptember($employee);

    expect($result->status)->toBe(LeavePostingResult::POSTED)
        ->and($result->earned)->toBe(1250)
        ->and($result->tardiness)->toBe(306)
        ->and(balances($employee))->toBe(['vacation' => 10944, 'sick' => 11250]);
    $tardiness = $employee->leaveLedgerEntries()->where('entry_type', LeaveLedgerEntryType::Tardiness)->sole();
    expect($tardiness->days)->toBe('-0.306')
        ->and($tardiness->particulars)->toBe('Tardiness and undertime, September 2026 (2 h 27 m)')
        ->and($tardiness->entry_date->toDateString())->toBe('2026-09-30');
});

it('posts nothing more when run again', function () {
    $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);
    grantLeaveCredits($employee);
    validatedMonthOfDtr($employee, '2026-09', fn (CarbonImmutable $date): array => ['late_minutes' => 10]);
    postSeptember($employee);
    $entries = $employee->leaveLedgerEntries()->count();

    $result = postSeptember($employee);

    expect($result->status)->toBe(LeavePostingResult::UP_TO_DATE)
        ->and($employee->leaveLedgerEntries()->count())->toBe($entries);
});

it('posts only the difference after the DTR is corrected', function () {
    $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);
    grantLeaveCredits($employee);
    validatedMonthOfDtr($employee, '2026-09', fn (CarbonImmutable $date): array => $date->day === 7 ? ['late_minutes' => 60] : []);
    postSeptember($employee);
    $employee->dailyTimeRecords()->whereDate('work_date', '2026-09-07')->update(['late_minutes' => 0]);

    postSeptember($employee);

    $tardiness = $employee->leaveLedgerEntries()->where('entry_type', LeaveLedgerEntryType::Tardiness)->orderBy('id')->pluck('days')->all();
    expect($tardiness)->toBe(['-0.125', '0.125'])
        ->and(balances($employee)['vacation'])->toBe(11250);
});

it('earns less for unexcused absences and leave without pay', function () {
    $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);
    grantLeaveCredits($employee);
    validatedMonthOfDtr($employee, '2026-09', fn (CarbonImmutable $date): array => $date->day === 8
        ? ['am_in' => null, 'am_out' => null, 'pm_in' => null, 'pm_out' => null, 'absent_minutes' => 480, 'status' => DtrStatus::Absent]
        : []);
    $leave = LeaveRequest::factory()->for($employee)->approved()->between('2026-09-10', '2026-09-10')->create(['days_with_pay' => '0.500', 'days_without_pay' => '0.500']);
    $leave->leaveDays()->update(['days_with_pay' => '0.500']);

    $result = postSeptember($employee);

    expect($result->earned)->toBe(1188);
});

it('records tardiness that vacation leave cannot cover as without pay', function () {
    $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);
    grantLeaveCredits($employee, vacation: '0.000');
    validatedMonthOfDtr($employee, '2026-09', fn (CarbonImmutable $date): array => ['late_minutes' => 60]);

    $result = postSeptember($employee);

    expect($result->tardiness)->toBe(1250)
        ->and($result->tardinessWithoutPay)->toBe(1500)
        ->and(balances($employee)['vacation'])->toBe(0)
        ->and($employee->leaveLedgerEntries()->where('entry_type', LeaveLedgerEntryType::Tardiness)->sole()->days_without_pay)->toBe('1.500');
});

it('prorates the first month of a new employee', function () {
    $employee = Employee::factory()->create(['date_hired' => '2026-09-16']);
    validatedMonthOfDtr($employee, '2026-09');
    $employee->dailyTimeRecords()->whereDate('work_date', '<', '2026-09-16')->delete();

    $result = postSeptember($employee);

    expect($result->earned)->toBe(625)
        ->and(balances($employee))->toBe(['vacation' => 625, 'sick' => 625]);
});

it('waits until the month can be posted', function (callable $arrange, string $reason) {
    $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);
    $arrange($employee);

    $result = postSeptember($employee);

    expect($result->status)->toBe(LeavePostingResult::SKIPPED)
        ->and($result->message)->toBe($reason)
        ->and($employee->leaveLedgerEntries()->whereNot('entry_type', LeaveLedgerEntryType::OpeningBalance)->count())->toBe(0);
})->with([
    'DTR not validated' => [
        function (Employee $employee): void {
            grantLeaveCredits($employee);
            validatedMonthOfDtr($employee, '2026-09');
            DailyTimeRecord::query()->whereDate('work_date', '2026-09-15')->update(['validated_at' => null]);
        },
        'The DTR for the month is not fully validated.',
    ],
    'no opening balance' => [
        fn (Employee $employee) => validatedMonthOfDtr($employee, '2026-09'),
        'No opening balance has been imported.',
    ],
    'opening balance after the month' => [
        function (Employee $employee): void {
            grantLeaveCredits($employee, asOf: '2026-09-30');
            validatedMonthOfDtr($employee, '2026-09');
        },
        'Already included in the opening balance.',
    ],
    'employment type earns no credits' => [
        function (Employee $employee): void {
            $employee->update(['employment_type_id' => EmploymentType::factory()->create(['earns_leave_credits' => false])->id]);
        },
        'This employment type does not earn leave credits.',
    ],
]);

it('does not post a month that has not ended', function () {
    $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);

    $result = app(LeaveCreditPostingService::class)->postMonth($employee, CarbonImmutable::parse('2026-10-01'));

    expect($result->message)->toBe('The month has not ended yet.');
});

describe('forced leave', function () {
    it('forfeits the days of forced leave not taken by employees with 10 or more days of vacation leave', function () {
        $this->travelTo(CarbonImmutable::parse('2027-01-15'));
        $withLeaveTaken = Employee::factory()->create(['date_hired' => '2020-01-06']);
        $without = Employee::factory()->create(['date_hired' => '2020-01-06']);
        $belowThreshold = Employee::factory()->create(['date_hired' => '2020-01-06']);
        grantLeaveCredits($withLeaveTaken, vacation: '20.000', asOf: '2025-12-31');
        grantLeaveCredits($without, vacation: '20.000', asOf: '2025-12-31');
        grantLeaveCredits($belowThreshold, vacation: '9.000', asOf: '2025-12-31');
        LeaveRequest::factory()->for($withLeaveTaken)->approved()->between('2026-06-01', '2026-06-03')->create();

        $count = app(ForcedLeaveService::class)->forfeitUnused(2026);

        expect($count)->toBe(2)
            ->and(balances($withLeaveTaken)['vacation'])->toBe(18000)
            ->and(balances($without)['vacation'])->toBe(15000)
            ->and(balances($belowThreshold)['vacation'])->toBe(9000);
        expect(app(ForcedLeaveService::class)->forfeitUnused(2026))->toBe(0);
    });
});
