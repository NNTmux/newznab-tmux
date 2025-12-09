<?php

namespace App\Services\Categorization\Pipes;

use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\Categorizers\MusicCategorizer;
use App\Services\Categorization\ReleaseContext;

/**
 * Pipe for Music content categorization.
 */
class MusicPipe extends AbstractCategorizationPipe
{
    protected int $priority = 40;
    private MusicCategorizer $categorizer;

    public function __construct()
    {
        $this->categorizer = new MusicCategorizer();
    }

    public function getName(): string
    {
        return 'Music';
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

