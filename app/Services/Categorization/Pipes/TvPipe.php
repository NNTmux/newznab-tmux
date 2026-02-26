<?php

declare(strict_types=1);

namespace App\Services\Categorization\Pipes;

use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\Categorizers\TvCategorizer;
use App\Services\Categorization\ReleaseContext;

/**
 * Pipe for TV content categorization.
 */
class TvPipe extends AbstractCategorizationPipe
{
    protected int $priority = 20;

    private TvCategorizer $categorizer;

    public function __construct()
    {
        $this->categorizer = new TvCategorizer;
    }

    public function getName(): string
    {
        return 'TV';
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
