<?php

declare(strict_types=1);

namespace App\Services\Categorization\Pipes;

use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\Categorizers\GroupNameCategorizer;
use App\Services\Categorization\ReleaseContext;

/**
 * Pipe for group-based categorization.
 */
class GroupNamePipe extends AbstractCategorizationPipe
{
    protected int $priority = 5;

    private GroupNameCategorizer $categorizer;

    public function __construct()
    {
        $this->categorizer = new GroupNameCategorizer;
    }

    public function getName(): string
    {
        return 'GroupName';
    }

    protected function categorize(ReleaseContext $context): CategorizationResult
    {
        return $this->categorizer->categorize($context);
    }
}
