<?php

use App\Enums\LeaveCreditSource;
use App\Services\Leave\LeaveChargeCalculator;

it('charges vacation leave to vacation credits and the rest without pay', function (int $days, int $vacation, array $expected) {
    $charge = (new LeaveChargeCalculator)->charge(LeaveCreditSource::VacationCredits, $days, $vacation, 10000);

    expect([$charge->fromVacation, $charge->fromSick, $charge->withoutPay])->toBe($expected);
})->with([
    'enough credits' => [3000, 5000, [3000, 0, 0]],
    'exactly enough' => [3000, 3000, [3000, 0, 0]],
    'partly covered' => [3000, 1500, [1500, 0, 1500]],
    'no credits' => [2000, 0, [0, 0, 2000]],
    'a negative balance counts as none' => [1000, -250, [0, 0, 1000]],
]);

it('charges sick leave to sick credits, then vacation credits, then without pay', function (int $sick, int $vacation, array $expected) {
    $charge = (new LeaveChargeCalculator)->charge(LeaveCreditSource::SickCredits, 5000, $vacation, $sick);

    expect([$charge->fromSick, $charge->fromVacation, $charge->withoutPay])->toBe($expected);
})->with([
    'enough sick leave' => [8000, 0, [5000, 0, 0]],
    'sick leave runs out into vacation leave' => [2000, 1000, [2000, 1000, 2000]],
    'covered by vacation leave' => [0, 9000, [0, 5000, 0]],
    'no credits at all' => [0, 0, [0, 0, 5000]],
]);

it('pays allotment and statutory leave in full without touching credits', function (LeaveCreditSource $source) {
    $charge = (new LeaveChargeCalculator)->charge($source, 3000, 0, 0);

    expect($charge->notCharged)->toBe(3000)
        ->and($charge->withPay())->toBe(3000)
        ->and($charge->withoutPay)->toBe(0);
})->with([LeaveCreditSource::AnnualAllotment, LeaveCreditSource::NotCharged]);
