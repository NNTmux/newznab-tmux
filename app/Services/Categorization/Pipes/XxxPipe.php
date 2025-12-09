<?php

namespace App\Services\Categorization\Pipes;

use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\Categorizers\XxxCategorizer;
use App\Services\Categorization\ReleaseContext;

/**
 * Pipe for XXX/Adult content categorization.
 */
class XxxPipe extends AbstractCategorizationPipe
{
    protected int $priority = 10;
    private XxxCategorizer $categorizer;

    public function __construct()
    {
        $this->categorizer = new XxxCategorizer();
    }

    public function getName(): string
    {
        return 'XXX';
    }

    protected function categorize(ReleaseContext $context): CategorizationResult
    {
        return $this->categorizer->categorize($context);
    }
}

