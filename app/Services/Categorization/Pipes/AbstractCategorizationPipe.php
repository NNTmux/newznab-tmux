<?php

declare(strict_types=1);

namespace App\Services\Categorization\Pipes;

use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\ReleaseContext;
use Closure;

/**
 * Base class for categorization pipe handlers.
 *
 * Each pipe is responsible for categorizing a specific type of release.
 */
abstract class AbstractCategorizationPipe
{
    protected int $priority = 50;

    /**
     * Handle the categorization request.
     */
    public function handle(CategorizationPassable $passable, Closure $next): CategorizationPassable
    {
        // If we already have a high-confidence match, skip processing
        if ($passable->shouldStopProcessing()) {
            return $next($passable);
        }

        // Skip if this categorizer shouldn't process this release
        if ($this->shouldSkip($passable->context)) {
            return $next($passable);
        }

        // Attempt categorization
        $result = $this->categorize($passable->context);

        // Update the best result
        $passable->updateBestResult($result, $this->getName());

        return $next($passable);
    }

    /**
     * Get the priority of this categorizer (lower = higher priority).
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Get the name of this categorizer for debugging/logging.
     */
    abstract public function getName(): string;

    /**
     * Attempt to categorize the given release.
     */
    abstract protected function categorize(ReleaseContext $context): CategorizationResult;

    /**
     * Check if this categorizer should be skipped for the given context.
     */
    protected function shouldSkip(ReleaseContext $context): bool
    {
        return false;
    }

    /**
     * Create a successful categorization result.
     *
     * @param  array<string, mixed>  $debug
     */
    protected function matched(int $categoryId, float $confidence, string $matchedBy, array $debug = []): CategorizationResult
    {
        return new CategorizationResult($categoryId, $confidence, $matchedBy, $debug);
    }

    /**
     * Create a no-match result.
     */
    protected function noMatch(): CategorizationResult
    {
        return CategorizationResult::noMatch();
    }
}
