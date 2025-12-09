<?php
namespace App\Services\Categorization\Contracts;
use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\ReleaseContext;
/**
 * Interface for all categorization handlers.
 * 
 * Each categorizer is responsible for determining if a release
 * belongs to its category domain.
 */
interface CategorizerInterface
{
    /**
     * Get the priority of this categorizer (lower = higher priority).
     * Categorizers are executed in priority order.
     */
    public function getPriority(): int;
    /**
     * Get the name of this categorizer for debugging/logging.
     */
    public function getName(): string;
    /**
     * Attempt to categorize the given release.
     * 
     * @param ReleaseContext $context The release information
     * @return CategorizationResult The categorization result
     */
    public function categorize(ReleaseContext $context): CategorizationResult;
    /**
     * Check if this categorizer should be skipped for the given context.
     * Useful for early-exit optimizations.
     */
    public function shouldSkip(ReleaseContext $context): bool;
}
