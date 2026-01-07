<?php

namespace App\Services\Categorization\Pipes;

use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\Categorizers\MiscCategorizer;
use App\Services\Categorization\ReleaseContext;

/**
 * Pipe for miscellaneous content and hash detection.
 * This runs FIRST with high priority to detect hashes early and prevent
 * them from being incorrectly categorized by group-based or content-based rules.
 */
class MiscPipe extends AbstractCategorizationPipe
{
    protected int $priority = 1; // Run first to catch hashes early

    private MiscCategorizer $categorizer;

    public function __construct()
    {
        $this->categorizer = new MiscCategorizer;
    }

    public function getName(): string
    {
        return 'Misc';
    }

    protected function categorize(ReleaseContext $context): CategorizationResult
    {
        return $this->categorizer->categorize($context);
    }
}
