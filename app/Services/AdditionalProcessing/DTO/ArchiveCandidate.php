<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\DTO;

final readonly class ArchiveCandidate
{
    /**
     * @param  list<string>  $messageIds
     * @param  list<string>  $tailMessageIds
     */
    public function __construct(
        public string $title,
        public array $messageIds,
        public bool $likelyFirstVolume,
        public int $sourceIndex,
        public array $tailMessageIds = [],
    ) {}
}
