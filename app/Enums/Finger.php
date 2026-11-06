<?php

namespace App\Enums;

/**
 * Which finger a fingerprint template was enrolled from.
 */
enum Finger: string
{
    case RightThumb = 'right-thumb';
    case RightIndex = 'right-index';
    case RightMiddle = 'right-middle';
    case RightRing = 'right-ring';
    case RightLittle = 'right-little';
    case LeftThumb = 'left-thumb';
    case LeftIndex = 'left-index';
    case LeftMiddle = 'left-middle';
    case LeftRing = 'left-ring';
    case LeftLittle = 'left-little';

    public function label(): string
    {
        return match ($this) {
            self::RightThumb => 'Right thumb',
            self::RightIndex => 'Right index finger',
            self::RightMiddle => 'Right middle finger',
            self::RightRing => 'Right ring finger',
            self::RightLittle => 'Right little finger',
            self::LeftThumb => 'Left thumb',
            self::LeftIndex => 'Left index finger',
            self::LeftMiddle => 'Left middle finger',
            self::LeftRing => 'Left ring finger',
            self::LeftLittle => 'Left little finger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
