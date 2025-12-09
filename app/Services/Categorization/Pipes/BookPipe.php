<?php

namespace App\Services\Categorization\Pipes;

use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\Categorizers\BookCategorizer;
use App\Services\Categorization\ReleaseContext;

/**
 * Pipe for Book content categorization.
 */
class BookPipe extends AbstractCategorizationPipe
{
    protected int $priority = 45;
    private BookCategorizer $categorizer;

    public function __construct()
    {
        $this->categorizer = new BookCategorizer();
    }

    public function getName(): string
    {
        return 'Book';
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
