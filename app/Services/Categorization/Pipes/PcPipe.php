<?php

namespace App\Services\Categorization\Pipes;

use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\Categorizers\PcCategorizer;
use App\Services\Categorization\ReleaseContext;

/**
 * Pipe for PC content categorization (Games, Software, etc.).
 */
class PcPipe extends AbstractCategorizationPipe
{
    protected int $priority = 30;
    private PcCategorizer $categorizer;

    public function __construct()
    {
        $this->categorizer = new PcCategorizer();
    }

    public function getName(): string
    {
        return 'PC';
    }

    protected function shouldSkip(ReleaseContext $context): bool
    {
        return $this->categorizer->shouldSkip($context);
    }

    protected function categorize(ReleaseContext $context): CategorizationResult
    {
        return $this->categorizer->categorize($context);
    }
}

