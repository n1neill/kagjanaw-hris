<?php

use App\Services\Leave\LeaveDays;

it('reads stored decimals as thousandths of a day without float rounding', function (string|int|float|null $value, int $expected) {
    expect(LeaveDays::toThousandths($value))->toBe($expected);
})->with([
    'decimal string' => ['1.250', 1250],
    'short decimal' => ['15.5', 15500],
    'negative' => ['-0.062', -62],
    'whole number string' => ['7', 7000],
    'float' => [0.1 + 0.2, 300],
    'null' => [null, 0],
]);

it('formats thousandths as a three-decimal string', function (int $thousandths, string $expected) {
    expect(LeaveDays::toDecimal($thousandths))->toBe($expected);
})->with([
    [1250, '1.250'],
    [15000, '15.000'],
    [-62, '-0.062'],
    [0, '0.000'],
]);
