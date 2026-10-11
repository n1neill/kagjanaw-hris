<?php

use App\Enums\DtrStatus;
use App\Services\Attendance\DayPunches;
use App\Services\Attendance\DayType;
use App\Services\Attendance\DtrCalculator;
use App\Services\Attendance\DtrResult;
use App\Services\Attendance\ScheduleRules;

/**
 * Municipal office hours: 8:00-12:00 and 1:00-5:00, 15-minute grace period.
 */
function officeHours(): ScheduleRules
{
    return ScheduleRules::fromTimes('08:00', '12:00', '13:00', '17:00', 15);
}

function calculateDay(?string $amIn, ?string $amOut, ?string $pmIn, ?string $pmOut, DayType $dayType = DayType::Working, ?string $now = null): DtrResult
{
    return (new DtrCalculator)->calculate(
        DayPunches::fromTimes($amIn, $amOut, $pmIn, $pmOut),
        officeHours(),
        $dayType,
        $now === null ? null : ScheduleRules::toMinutes($now),
    );
}

it('records a complete on-time day as present with eight hours worked', function () {
    $result = calculateDay('07:52', '12:01', '12:55', '17:03');

    expect($result->status)->toBe(DtrStatus::Present)
        ->and($result->lateMinutes)->toBe(0)
        ->and($result->undertimeMinutes)->toBe(0)
        ->and($result->absentMinutes)->toBe(0)
        ->and($result->workedMinutes)->toBe(480);
});

it('treats arrival within the grace period as on time', function (string $amIn, string $pmIn) {
    $result = calculateDay($amIn, '12:00', $pmIn, '17:00');

    expect($result->lateMinutes)->toBe(0);
})->with([
    'exactly on time' => ['08:00', '13:00'],
    'last minute of grace' => ['08:15', '13:15'],
]);

it('counts lateness from the scheduled start once the grace period is exceeded', function (string $amIn, string $pmIn, int $expectedLate) {
    $result = calculateDay($amIn, '12:00', $pmIn, '17:00');

    expect($result->lateMinutes)->toBe($expectedLate)
        ->and($result->status)->toBe(DtrStatus::Present);
})->with([
    'one minute past grace in the morning' => ['08:16', '13:00', 16],
    'late in the afternoon only' => ['08:00', '13:40', 40],
    'late in both sessions' => ['09:00', '13:20', 80],
]);

it('counts leaving early as undertime', function () {
    $result = calculateDay('08:00', '11:30', '13:00', '16:15');

    expect($result->undertimeMinutes)->toBe(75)
        ->and($result->lateMinutes)->toBe(0)
        ->and($result->workedMinutes)->toBe(405)
        ->and($result->status)->toBe(DtrStatus::Present);
});

it('gives no extra credit for early arrival or overtime', function () {
    $result = calculateDay('06:30', '12:45', '12:46', '19:00');

    expect($result->workedMinutes)->toBe(480)
        ->and($result->undertimeMinutes)->toBe(0);
});

it('marks a working day without punches as a full-day absence', function () {
    $result = calculateDay(null, null, null, null);

    expect($result->status)->toBe(DtrStatus::Absent)
        ->and($result->absentMinutes)->toBe(480)
        ->and($result->workedMinutes)->toBe(0);
});

it('marks a day with one missing session as a half-day absence', function (?string $amIn, ?string $amOut, ?string $pmIn, ?string $pmOut) {
    $result = calculateDay($amIn, $amOut, $pmIn, $pmOut);

    expect($result->status)->toBe(DtrStatus::HalfDay)
        ->and($result->absentMinutes)->toBe(240)
        ->and($result->workedMinutes)->toBe(240);
})->with([
    'morning only' => ['08:00', '12:00', null, null],
    'afternoon only' => [null, null, '13:00', '17:00'],
]);

it('flags a session with a single punch for HR review and counts it absent', function () {
    $result = calculateDay('08:00', null, '13:00', '17:00');

    expect($result->status)->toBe(DtrStatus::Incomplete)
        ->and($result->absentMinutes)->toBe(240)
        ->and($result->workedMinutes)->toBe(240);
});

it('treats an out punch earlier than the in punch as a missing punch', function () {
    $result = calculateDay('11:00', '10:00', '13:00', '17:00');

    expect($result->status)->toBe(DtrStatus::Incomplete);
});

it('applies no deductions on holidays and rest days', function (DayType $dayType, DtrStatus $expectedStatus) {
    $result = calculateDay(null, null, null, null, $dayType);

    expect($result->status)->toBe($expectedStatus)
        ->and($result->absentMinutes)->toBe(0)
        ->and($result->lateMinutes)->toBe(0);
})->with([
    'holiday' => [DayType::Holiday, DtrStatus::Holiday],
    'rest day' => [DayType::RestDay, DtrStatus::RestDay],
]);

it('records hours worked on a rest day without judging them', function () {
    $result = calculateDay('09:30', '12:00', '13:00', '15:00', DayType::RestDay);

    expect($result->status)->toBe(DtrStatus::RestDay)
        ->and($result->workedMinutes)->toBe(270)
        ->and($result->lateMinutes)->toBe(0);
});

describe('today', function () {
    it('keeps the day in progress and does not count the afternoon as absent before it ends', function () {
        $result = calculateDay('07:58', null, null, null, now: '09:30');

        expect($result->status)->toBe(DtrStatus::Pending)
            ->and($result->absentMinutes)->toBe(0);
    });

    it('shows a late morning arrival right away', function () {
        $result = calculateDay('08:40', null, null, null, now: '09:30');

        expect($result->lateMinutes)->toBe(40);
    });

    it('judges the morning once it has ended', function () {
        $result = calculateDay(null, null, '13:00', null, now: '14:00');

        expect($result->status)->toBe(DtrStatus::Pending)
            ->and($result->absentMinutes)->toBe(240);
    });

    it('is final once the afternoon session has ended', function () {
        $result = calculateDay('08:00', '12:00', '13:00', '17:00', now: '17:05');

        expect($result->status)->toBe(DtrStatus::Present);
    });
});
