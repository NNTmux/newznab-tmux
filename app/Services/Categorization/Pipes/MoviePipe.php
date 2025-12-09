<?php

namespace App\Services\Categorization\Pipes;

use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\Categorizers\MovieCategorizer;
use App\Services\Categorization\ReleaseContext;

/**
 * Pipe for Movie content categorization.
 */
class MoviePipe extends AbstractCategorizationPipe
{
    protected int $priority = 25;
    private MovieCategorizer $categorizer;

    public function __construct()
    {
        $this->categorizer = new MovieCategorizer();
    }

    public function getName(): string
    {
        return 'Movie';
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

