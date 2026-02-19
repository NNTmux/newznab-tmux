<?php

namespace App\Services\Categorization\Pipes;

use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\ReleaseContext;

/**
 * Passable object that travels through the categorization pipeline.
 */
class CategorizationPassable
{
    public ReleaseContext $context;

    public CategorizationResult $bestResult;

    public bool $debug;

    /**
     * When true, the release has been identified as hashed/gibberish
     * and must remain in a misc category. No downstream pipe may override.
     */
    public bool $lockedToMisc = false;

    /**
     * @var array<string, mixed>
     */
    public array $allResults = [];

    public function __construct(ReleaseContext $context, bool $debug = false)
    {
        $this->context = $context;
        $this->debug = $debug;
        $this->bestResult = CategorizationResult::noMatch();
    }

    /**
     * Lock this release to misc categories.
     *
     * Once locked, shouldStopProcessing() returns true and no downstream
     * pipe can assign a non-misc category.
     */
    public function lockToMisc(): void
    {
        $this->lockedToMisc = true;
    }

    /**
     * Check if we should stop processing (high confidence match found or locked to misc).
     */
    public function shouldStopProcessing(): bool
    {
        return $this->lockedToMisc || $this->bestResult->confidence >= 0.95;
    }

    /**
     * Update the best result if the new result is better.
     */
    public function updateBestResult(CategorizationResult $result, string $categorizerName): void
    {
        if ($this->debug) {
            $this->allResults[$categorizerName] = [
                'category_id' => $result->categoryId,
                'confidence' => $result->confidence,
                'matched_by' => $result->matchedBy,
            ];
        }

        if ($result->isSuccessful() && $result->shouldOverride($this->bestResult)) {
            $this->bestResult = $result;
        }
    }

    /**
     * Build the final result array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $returnValue = ['categories_id' => $this->bestResult->categoryId];

        if ($this->debug) {
            $returnValue['debug'] = [
                'final_category' => $this->bestResult->categoryId,
                'final_confidence' => $this->bestResult->confidence,
                'matched_by' => $this->bestResult->matchedBy,
                'locked_to_misc' => $this->lockedToMisc,
                'release_name' => $this->context->releaseName,
                'group_name' => $this->context->groupName,
                'all_results' => $this->allResults,
                'categorizer_details' => $this->bestResult->debug,
            ];
        }

        return $returnValue;
    }
}
