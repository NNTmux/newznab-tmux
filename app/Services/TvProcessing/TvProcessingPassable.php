<?php

namespace App\Services\TvProcessing;

/**
 * Passable object that travels through the TV processing pipeline.
 */
class TvProcessingPassable
{
    public TvReleaseContext $context;

    public TvProcessingResult $result;

    public bool $debug;

    public array $providerResults = [];

    public ?array $parsedInfo = null;

    public function __construct(TvReleaseContext $context, bool $debug = false)
    {
        $this->context = $context;
        $this->debug = $debug;
        $this->result = TvProcessingResult::pending();
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
    public function updateResult(TvProcessingResult $result, string $providerName): void
    {
        if ($this->debug) {
            $this->providerResults[$providerName] = [
                'status' => $result->status,
                'video_id' => $result->videoId,
                'episode_id' => $result->episodeId,
                'debug' => $result->debug,
            ];
        }

        // Update if we got a match
        if ($result->isMatched()) {
            $this->result = $result;
        }
    }

    /**
     * Set the parsed release info.
     */
    public function setParsedInfo(?array $info): void
    {
        $this->parsedInfo = $info;
    }

    /**
     * Get the parsed release info.
     */
    public function getParsedInfo(): ?array
    {
        return $this->parsedInfo;
    }

    /**
     * Check if we have valid parsed info.
     */
    public function hasValidParsedInfo(): bool
    {
        return $this->parsedInfo !== null
            && ! empty($this->parsedInfo['name'])
            && isset($this->parsedInfo['season'], $this->parsedInfo['episode']);
    }

    /**
     * Build the final result array.
     */
    public function toArray(): array
    {
        $returnValue = [
            'status' => $this->result->status,
            'matched' => $this->result->isMatched(),
            'video_id' => $this->result->videoId,
            'episode_id' => $this->result->episodeId,
            'provider' => $this->result->providerName,
        ];

        if ($this->debug) {
            $returnValue['debug'] = [
                'release_id' => $this->context->releaseId,
                'search_name' => $this->context->searchName,
                'parsed_info' => $this->parsedInfo,
                'provider_results' => $this->providerResults,
            ];
        }

        return $returnValue;
    }
}
