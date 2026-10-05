<?php

declare(strict_types=1);

namespace App\Enums;

enum TmuxPostProcessMode: int
{
    case Disabled = 0;
    case Additional = 1;
    case Nfo = 2;
    case All = 3;
}
