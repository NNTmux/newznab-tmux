<?php

declare(strict_types=1);

namespace App\Enums;

enum RegistrationStatus: int
{
    case Open = 0;
    case Invite = 1;
    case Closed = 2;

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Invite => 'Invite',
            self::Closed => 'Closed',
        };
    }
}
