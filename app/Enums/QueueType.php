<?php

declare(strict_types=1);

namespace App\Enums;

enum QueueType: int
{
    case NONE = 0;
    case SABNZBD = 1;
    case NZBGET = 2;

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'None',
            self::SABNZBD => 'SABnzbd',
            self::NZBGET => 'NZBGet',
        };
    }
}
