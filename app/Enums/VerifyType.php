<?php

namespace App\Enums;

/**
 * How the employee identified themselves at the device.
 */
enum VerifyType: string
{
    case Fingerprint = 'fingerprint';
    case Face = 'face';
    case Password = 'password';
    case Card = 'card';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Fingerprint => 'Fingerprint',
            self::Face => 'Face',
            self::Password => 'Password',
            self::Card => 'Card',
            self::Unknown => 'Unknown',
        };
    }
}
