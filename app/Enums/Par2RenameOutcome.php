<?php

declare(strict_types=1);

namespace App\Enums;

enum Par2RenameOutcome: string
{
    case Renamed = 'renamed';

    case NoMatch = 'no_match';

    case Retryable = 'retryable';

    case Deferred = 'deferred';

}
