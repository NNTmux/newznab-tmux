<?php

namespace App\Services\Categorization\Pipes;

use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\Categorizers\MiscCategorizer;
use App\Services\Categorization\ReleaseContext;

/**
 * Pipe for miscellaneous content and hash detection.
 * This runs last as a fallback.
 */
class MiscPipe extends AbstractCategorizationPipe
{
    protected int $priority = 100;
    private MiscCategorizer $categorizer;

    public function __construct()
    {
        $this->categorizer = new MiscCategorizer();
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

