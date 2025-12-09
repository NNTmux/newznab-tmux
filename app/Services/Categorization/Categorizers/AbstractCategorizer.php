<?php
namespace App\Services\Categorization\Categorizers;
use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\Contracts\CategorizerInterface;
use App\Services\Categorization\ReleaseContext;
abstract class AbstractCategorizer implements CategorizerInterface
{
    protected int $priority = 50;
    public function getPriority(): int { return $this->priority; }
    public function shouldSkip(ReleaseContext $context): bool { return false; }
    protected function matched(int $categoryId, float $confidence, string $matchedBy, array $debug = []): CategorizationResult
    {
        return new CategorizationResult($categoryId, $confidence, $matchedBy, $debug);
    }
    protected function noMatch(): CategorizationResult { return CategorizationResult::noMatch(); }
}
