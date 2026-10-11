<?php

use App\Enums\DtrStatus;
use App\Enums\EmploymentStatus;
use App\Models\AttendanceLog;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\Holiday;
use App\Services\Attendance\AttendanceProcessor;
use Carbon\CarbonImmutable;

beforeEach(function () {
    // Wednesday evening, after office hours.
    $this->travelTo(CarbonImmutable::parse('2026-10-07 18:00:00'));
});

function processDays(Employee $employee, string $from, string $to): void
{
    app(AttendanceProcessor::class)->processEmployee($employee, CarbonImmutable::parse($from), CarbonImmutable::parse($to));
}

function dtrOn(Employee $employee, string $date): ?DailyTimeRecord
{
    return $employee->dailyTimeRecords()->whereDate('work_date', $date)->first();
}

it('marks working days without punches absent, weekends as rest days and holidays as holidays', function () {
    $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);
    Holiday::factory()->create(['date' => '2026-10-05', 'name' => 'Town Fiesta']);

    processDays($employee, '2026-10-03', '2026-10-06');

    expect(dtrOn($employee, '2026-10-03')->status)->toBe(DtrStatus::RestDay)
        ->and(dtrOn($employee, '2026-10-04')->status)->toBe(DtrStatus::RestDay)
        ->and(dtrOn($employee, '2026-10-05')->status)->toBe(DtrStatus::Holiday)
        ->and(dtrOn($employee, '2026-10-06')->status)->toBe(DtrStatus::Absent)
        ->and(dtrOn($employee, '2026-10-06')->absent_minutes)->toBe(480)
        ->and(dtrOn($employee, '2026-10-05')->absent_minutes)->toBe(0);
});

it('does not create records before hiring, after separation or in the future', function () {
    $employee = Employee::factory()->create([
        'date_hired' => '2026-10-02',
        'employment_status' => EmploymentStatus::Resigned,
        'separation_date' => '2026-10-06',
    ]);

    processDays($employee, '2026-10-01', '2026-10-09');

    expect($employee->dailyTimeRecords()->pluck('work_date')->map->toDateString()->all())
        ->toBe(['2026-10-02', '2026-10-03', '2026-10-04', '2026-10-05', '2026-10-06']);
});

it('ignores flagged punches', function () {
    $employee = Employee::factory()->create(['date_hired' => '2020-01-06', 'biometric_user_id' => '1024']);
    AttendanceLog::factory()->for($employee)->create(['logged_at' => '2026-10-06 08:00:00', 'status' => 'future-timestamp']);

    processDays($employee, '2026-10-06', '2026-10-06');

    expect(dtrOn($employee, '2026-10-06')->status)->toBe(DtrStatus::Absent);
});

it('never overwrites a day that HR corrected', function () {
    $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);
    DailyTimeRecord::factory()->for($employee)->create(['work_date' => '2026-10-06', 'is_adjusted' => true]);

    processDays($employee, '2026-10-06', '2026-10-06');

    expect(dtrOn($employee, '2026-10-06')->status)->toBe(DtrStatus::Present)
        ->and(dtrOn($employee, '2026-10-06')->am_in)->toBe('07:55:00');
});

it('keeps today in progress until the afternoon session ends', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00:00'));
    $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);
    AttendanceLog::factory()->for($employee)->create(['logged_at' => '2026-10-07 07:58:00']);

    processDays($employee, '2026-10-07', '2026-10-07');

    expect(dtrOn($employee, '2026-10-07')->status)->toBe(DtrStatus::Pending)
        ->and(dtrOn($employee, '2026-10-07')->absent_minutes)->toBe(0);
});

it('processes every employee in service through the nightly command', function () {
    $active = Employee::factory()->create(['date_hired' => '2020-01-06']);
    $separatedLongAgo = Employee::factory()->create(['date_hired' => '2010-01-04', 'employment_status' => EmploymentStatus::Retired, 'separation_date' => '2025-12-31']);

    $this->artisan('attendance:process', ['--from' => '2026-10-06', '--to' => '2026-10-06'])
        ->expectsOutput('Processed 1 employees from 2026-10-06 to 2026-10-06.')
        ->assertSuccessful();

    expect(dtrOn($active, '2026-10-06'))->not->toBeNull()
        ->and($separatedLongAgo->dailyTimeRecords()->count())->toBe(0);
});

it('rejects a reversed date range', function () {
    $this->artisan('attendance:process', ['--from' => '2026-10-06', '--to' => '2026-10-01'])
        ->expectsOutput('--from must not be after --to.')
        ->assertExitCode(2);
});
