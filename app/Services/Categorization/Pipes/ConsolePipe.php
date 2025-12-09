<?php

namespace App\Services\Categorization\Pipes;

use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\Categorizers\ConsoleCategorizer;
use App\Services\Categorization\ReleaseContext;

/**
 * Pipe for Console game categorization.
 */
class ConsolePipe extends AbstractCategorizationPipe
{
    protected int $priority = 35;
    private ConsoleCategorizer $categorizer;

    public function __construct()
    {
        $this->categorizer = new ConsoleCategorizer();
    }

    public function getName(): string
    {
        return 'Console';
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

