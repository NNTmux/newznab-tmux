<?php

declare(strict_types=1);

namespace App\Enums;

enum TmuxBackfillMode: int
{
    case Disabled = 0;
    case All = 1;
    case Safe = 4;
}
