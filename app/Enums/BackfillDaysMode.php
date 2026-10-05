<?php

declare(strict_types=1);

namespace App\Enums;

enum BackfillDaysMode: int
{
    case DaysPerGroup = 1;
    case SafeDate = 2;
}
