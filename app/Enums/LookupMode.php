<?php

declare(strict_types=1);

namespace App\Enums;

enum LookupMode: int
{
    case Disabled = 0;
    case All = 1;
    case Renamed = 2;

    public function label(): string
    {
        return match ($this) {
            self::Disabled => 'Disabled',
            self::All => 'All releases',
            self::Renamed => 'Renamed releases only',
        };
    }
}
