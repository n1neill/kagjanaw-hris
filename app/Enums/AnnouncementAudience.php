<?php

namespace App\Enums;

/**
 * Who an announcement is addressed to.
 */
enum AnnouncementAudience: string
{
    case Everyone = 'everyone';
    case Departments = 'departments';

    public function label(): string
    {
        return match ($this) {
            self::Everyone => 'All employees',
            self::Departments => 'Selected offices',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $audience): array => [$audience->value => $audience->label()])
            ->all();
    }
}
