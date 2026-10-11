<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\DTO;

/**
 * An image posted in the release, with every article needed to download it whole.
 */
final readonly class ImageCandidate
{
    /**
     * @param  list<string>  $messageIds
     */
    public function __construct(
        public string $title,
        public array $messageIds,
        public int $sourceIndex,
        public int $postedSize = 0,
    ) {}
}
