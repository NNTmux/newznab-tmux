<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * NZB import result values used while processing imported NZB files.
 */
enum NzbImportStatus: string
{
    case Inserted = 'inserted';

    case Duplicate = 'duplicate';

    case Blacklisted = 'blacklisted';

    case NoGroup = 'nogroup';

    case Failed = 'failed';
}
