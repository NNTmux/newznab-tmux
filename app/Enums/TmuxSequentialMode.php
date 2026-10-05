<?php

declare(strict_types=1);

namespace App\Enums;

enum TmuxSequentialMode: int
{
    case Full = 0;
    case Basic = 1;
    case Stripped = 2;
}
