<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\DTO;

use App\Services\AdditionalProcessing\Enums\MappingConfidence;

/**
 * An NZB file whose real name the PAR2 index revealed.
 */
final readonly class MappedNzbFile
{
    public function __construct(
        public int $sourceIndex,
        public Par2FileEntry $file,
        public MappingConfidence $confidence,
    ) {}
}
