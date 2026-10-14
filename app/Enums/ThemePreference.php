<?php

namespace App\Enums;

/**
 * The colour scheme a user chose for the interface. System follows the
 * light or dark setting of the user's own computer.
 */
enum ThemePreference: string
{
    case Light = 'light';
    case Dark = 'dark';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Light => 'Light',
            self::Dark => 'Dark',
            self::System => 'System',
        };
    }

    /**
     * The icon shown beside the option in the theme switcher.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Light => 'sun',
            self::Dark => 'moon',
            self::System => 'computer',
        };
    }
}
