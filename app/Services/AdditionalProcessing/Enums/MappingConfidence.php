<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\Enums;

/**
 * How an NZB file was matched to a PAR2 file entry.
 */
enum MappingConfidence: string
{
    /** Its first 16 KiB, or its yEnc name and size, matched the PAR2 entry. */
    case Verified = 'verified';

    /** It was the only file whose size fits the PAR2 entry, and the other way round. */
    case UniqueSize = 'unique-size';
}
