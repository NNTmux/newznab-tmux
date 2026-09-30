<?php

declare(strict_types=1);

namespace App\Enums;

enum GroupScanMode: int
{
    case Posts = 0;
    case Days = 1;
}
