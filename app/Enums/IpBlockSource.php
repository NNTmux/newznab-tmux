<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who put an IP address on the block list.
 */
enum IpBlockSource: string
{
    case Auto = 'auto';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Automatic',
            self::Manual => 'Manual',
        };
    }
}
