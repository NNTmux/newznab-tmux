<?php

namespace App\Services\AdultProcessing;

/**
 * Passable object that travels through the adult movie processing pipeline.
 */
class AdultProcessingPassable
{
    public AdultReleaseContext $context;

    public AdultProcessingResult $result;

    public bool $debug;

    public array $providerResults = [];

    public ?string $cookie = null;

    public function __construct(AdultReleaseContext $context, bool $debug = false, ?string $cookie = null)
    {
        $this->context = $context;
        $this->debug = $debug;
        $this->cookie = $cookie;
        $this->result = AdultProcessingResult::pending();
    }

    /**
     * Check if we should stop processing (match found).
     */
    public function shouldStopProcessing(): bool
    {
        return $this->result->isMatched();
    }

    /**
     * Update the result from a provider.
     */
    public function updateResult(AdultProcessingResult $result, string $providerName): void
    {
        if ($this->debug) {
            $this->providerResults[$providerName] = [
                'status' => $result->status,
                'title' => $result->title,
                'debug' => $result->debug,
            ];
        }

        // Update if we got a match
        if ($result->isMatched()) {
            $this->result = $result;
        }
    }

    /**
     * Get the clean title for searching.
     */
    public function getCleanTitle(): string
    {
        return $this->context->cleanTitle;
    }

    /**
     * Get the cookie string.
     */
    public function getCookie(): ?string
    {
        return $this->cookie;
    }

    /**
     * Build the final result array.
     */
    public function toArray(): array
    {
        $returnValue = [
            'status' => $this->result->status,
            'title' => $this->result->title,
            'provider' => $this->result->providerName,
            'movieData' => $this->result->movieData,
        ];

        if ($this->debug) {
            $returnValue['debug'] = [
                'release_id' => $this->context->releaseId,
                'search_name' => $this->context->searchName,
                'clean_title' => $this->context->cleanTitle,
                'provider_results' => $this->providerResults,
            ];
        }

        return $returnValue;
    }
}
