<?php

use App\Services\Payroll\Money;

it('reads stored decimals as centavos without float rounding', function (string|int|float|null $value, int $expected) {
    expect(Money::toCentavos($value))->toBe($expected);
})->with([
    'decimal string' => ['1234.50', 123450],
    'one decimal' => ['15.5', 1550],
    'negative' => ['-0.25', -25],
    'whole number string' => ['22194', 2219400],
    'float' => [0.1 + 0.2, 30],
    'null' => [null, 0],
]);

it('formats centavos for display with thousands separators', function (int $centavos, string $expected) {
    expect(Money::format($centavos))->toBe($expected);
})->with([
    [1072085, '10,720.85'],
    [46232900, '462,329.00'],
    [-109973, '-1,099.73'],
    [5, '0.05'],
]);

it('rounds a ratio half up, away from zero', function (int $centavos, int $numerator, int $denominator, int $expected) {
    expect(Money::ratio($centavos, $numerator, $denominator))->toBe($expected);
})->with([
    'one day of ₱22,194 (÷ 22)' => [2219400, 1, 22, 100882],
    'exactly half rounds up' => [5, 1, 2, 3],
    'negative half rounds away from zero' => [-5, 1, 2, -3],
    'zero denominator' => [100, 1, 0, 0],
]);

it('takes a percentage of an amount', function () {
    // 9.00% of ₱22,194.00 = ₱1,997.46.
    expect(Money::percentOf(2219400, 900))->toBe(199746);
});

it('rounds to the nearest peso, half up', function (int $centavos, int $expected) {
    expect(Money::roundToPeso($centavos))->toBe($expected);
})->with([
    'half peso rounds up' => [2219350, 2219400],
    'above half rounds up' => [1024380, 1024400],
    'just below half' => [1024349, 1024300],
]);

it('splits a monthly amount so the second half carries the odd centavo', function () {
    expect(Money::splitMonthly(55485))->toBe([1 => 27742, 2 => 27743]);
});
