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

    public array $allResults = [];

    public function __construct(ReleaseContext $context, bool $debug = false)
    {
        $this->context = $context;
        $this->debug = $debug;
        $this->bestResult = CategorizationResult::noMatch();
    }

    /**
     * Check if we should stop processing (high confidence match found).
     */
    public function shouldStopProcessing(): bool
    {
        return $this->bestResult->confidence >= 0.95;
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
     */
    public function toArray(): array
    {
        $returnValue = ['categories_id' => $this->bestResult->categoryId];

        if ($this->debug) {
            $returnValue['debug'] = [
                'final_category' => $this->bestResult->categoryId,
                'final_confidence' => $this->bestResult->confidence,
                'matched_by' => $this->bestResult->matchedBy,
                'release_name' => $this->context->releaseName,
                'group_name' => $this->context->groupName,
                'all_results' => $this->allResults,
                'categorizer_details' => $this->bestResult->debug,
            ];
        }

        return $returnValue;
    }
}
