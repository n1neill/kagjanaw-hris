<?php

use App\Services\Leave\CscLeaveTables;

it('earns credits for days in service as printed in the CSC table', function (int $serviceDays, int $expected) {
    expect(CscLeaveTables::earnedForMonth($serviceDays))->toBe($expected);
})->with([
    'full month' => [30, 1250],
    'a 31-day month counts as 30' => [31, 1250],
    '20 days' => [20, 833],
    '15 days' => [15, 625],
    '10 days' => [10, 417],
    '1 day' => [1, 42],
    'no service' => [0, 0],
]);

it('reduces credits for leave without pay as printed in the CSC table', function (int $withoutPay, int $expected) {
    expect(CscLeaveTables::earnedForMonth(30, $withoutPay))->toBe($expected);
})->with([
    'half a day' => [500, 1229],
    '1 day' => [1000, 1208],
    '1.5 days' => [1500, 1188],
    '2 days' => [2000, 1167],
    '15 days' => [15000, 625],
    'the whole month' => [30000, 0],
    'more than the month' => [31000, 0],
]);

it('converts tardiness and undertime minutes to days as printed in the CSC table', function (int $minutes, int $expected) {
    expect(CscLeaveTables::minutesToDays($minutes))->toBe($expected);
})->with([
    'none' => [0, 0],
    '1 minute' => [1, 2],
    '5 minutes' => [5, 10],
    '6 minutes' => [6, 12],
    '7 minutes' => [7, 15],
    '15 minutes' => [15, 31],
    '27 minutes' => [27, 56],
    '30 minutes' => [30, 62],
    '45 minutes' => [45, 94],
    '59 minutes' => [59, 123],
    '1 hour' => [60, 125],
    '2 hours 27 minutes' => [147, 306],
    'a full day' => [480, 1000],
]);
