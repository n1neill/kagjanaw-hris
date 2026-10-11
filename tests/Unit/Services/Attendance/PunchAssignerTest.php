<?php

use App\Services\Attendance\PunchAssigner;
use App\Services\Attendance\ScheduleRules;

/**
 * @param  list<string>  $times
 * @return array{am_in: ?string, am_out: ?string, pm_in: ?string, pm_out: ?string}
 */
function assignPunches(array $times): array
{
    return (new PunchAssigner)
        ->assign(
            array_map(ScheduleRules::toMinutes(...), $times),
            ScheduleRules::fromTimes('08:00', '12:00', '13:00', '17:00', 15),
            duplicateTapMinutes: 5,
        )
        ->toTimes();
}

it('assigns four punches to the four DTR columns regardless of order', function () {
    expect(assignPunches(['17:02', '07:55', '12:58', '12:03']))->toBe([
        'am_in' => '07:55',
        'am_out' => '12:03',
        'pm_in' => '12:58',
        'pm_out' => '17:02',
    ]);
});

it('ignores repeated taps within five minutes', function () {
    expect(assignPunches(['07:55', '07:56', '07:59', '12:01', '12:58', '17:02', '17:04']))->toBe([
        'am_in' => '07:55',
        'am_out' => '12:01',
        'pm_in' => '12:58',
        'pm_out' => '17:02',
    ]);
});

it('splits the sessions halfway between noon and one o\'clock', function () {
    expect(assignPunches(['08:00', '12:29', '12:31', '17:00']))->toBe([
        'am_in' => '08:00',
        'am_out' => '12:29',
        'pm_in' => '12:31',
        'pm_out' => '17:00',
    ]);
});

it('uses the first and last punches when a session has extra punches', function () {
    expect(assignPunches(['08:00', '10:00', '11:00', '12:00']))->toMatchArray([
        'am_in' => '08:00',
        'am_out' => '12:00',
    ]);
});

it('reads a lone punch as in or out by which half of the session it falls in', function (array $times, array $expected) {
    expect(assignPunches($times))->toBe($expected);
})->with([
    'early morning punch is arrival' => [['08:05'], ['am_in' => '08:05', 'am_out' => null, 'pm_in' => null, 'pm_out' => null]],
    'late morning punch is departure' => [['11:50'], ['am_in' => null, 'am_out' => '11:50', 'pm_in' => null, 'pm_out' => null]],
    'early afternoon punch is arrival' => [['13:10'], ['am_in' => null, 'am_out' => null, 'pm_in' => '13:10', 'pm_out' => null]],
    'late afternoon punch is departure' => [['17:00'], ['am_in' => null, 'am_out' => null, 'pm_in' => null, 'pm_out' => '17:00']],
]);

it('returns no punches for an empty day', function () {
    expect(assignPunches([]))->toBe(['am_in' => null, 'am_out' => null, 'pm_in' => null, 'pm_out' => null]);
});
