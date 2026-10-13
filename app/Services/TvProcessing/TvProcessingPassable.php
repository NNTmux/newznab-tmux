<?php

declare(strict_types=1);

namespace App\Services\TvProcessing;

/**
 * State carried through the TV processing pipeline for one release.
 *
 * Pipes only record what they found here; TvLookupOutcomeWriter persists the final outcome.
 */
class TvProcessingPassable
{
    public ?ParsedTvRelease $parsed = null;

    public bool $parseFailed = false;

    /** The show the release belongs to, 0 while unknown. */
    public int $videoId;

    public ?string $showProvider = null;

    public int $episodeId = 0;

    public bool $seasonPack = false;

    public ?string $matchedBy = null;

    /** @var list<string> */
    public array $transientProviders = [];

    public ?int $maxRetryAfter = null;

    /** @var array<string, array<string, mixed>> */
    public array $providerResults = [];

    public function __construct(
        public readonly TvReleaseContext $context,
        public readonly TvProcessingRunState $runState,
        public readonly bool $debug = false,
    ) {
        // A release already linked to a show (episode retries, tv:reprocess) keeps that show.
        $this->videoId = max(0, $context->videosId);
        if ($this->videoId > 0) {
            $this->showProvider = 'existing';
        }
    }

    public function setParsed(ParsedTvRelease $parsed): void
    {
        $this->parsed = $parsed;
    }

    public function markParseFailed(): void
    {
        $this->parsed = null;
        $this->parseFailed = true;
    }

    public function hasShow(): bool
    {
        return $this->videoId > 0;
    }

    public function setShow(int $videoId, string $provider): void
    {
        $this->videoId = $videoId;
        $this->showProvider = $provider;
    }

    public function markMatched(int $episodeId, string $provider): void
    {
        $this->episodeId = $episodeId;
        $this->matchedBy = $provider;
    }

    public function markSeasonPack(string $provider): void
    {
        $this->seasonPack = true;
        $this->matchedBy = $provider;
    }

    public function markTransient(string $provider, ?int $retryAfter = null): void
    {
        $this->transientProviders[] = $provider;
        if ($retryAfter !== null) {
            $this->maxRetryAfter = max($this->maxRetryAfter ?? 0, $retryAfter);
        }
    }

    /** True once nothing further down the pipeline can improve the outcome. */
    public function isResolved(): bool
    {
        return $this->parseFailed || $this->episodeId > 0 || $this->seasonPack;
    }

    public function recordProviderResult(string $providerName, TvProcessingResult $result): void
    {
        if (! $this->debug) {
            return;
        }

        $this->providerResults[$providerName] = [
            'status' => $result->status,
            'video_id' => $result->videoId,
            'episode_id' => $result->episodeId,
            'debug' => $result->debug,
        ];
    }

    /** The outcome to persist for this release. */
    public function finalResult(): TvProcessingResult
    {
        if ($this->parseFailed || $this->parsed === null) {
            return TvProcessingResult::parseFailed(['search_name' => $this->context->searchName]);
        }

        if ($this->episodeId > 0) {
            return TvProcessingResult::matched($this->videoId, $this->episodeId, (string) $this->matchedBy);
        }

        if ($this->seasonPack && $this->videoId > 0) {
            return TvProcessingResult::seasonPack($this->videoId, (string) $this->matchedBy);
        }

        $transient = $this->transientProviders !== [];

        if ($this->videoId > 0) {
            return TvProcessingResult::episodeMissing(
                $this->videoId,
                $this->showProvider,
                ['transient_providers' => $this->transientProviders],
                $transient,
                $this->maxRetryAfter,
            );
        }

        if ($transient) {
            return TvProcessingResult::transient(
                implode(',', array_unique($this->transientProviders)),
                $this->maxRetryAfter,
            );
        }

        return TvProcessingResult::notFound();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = $this->finalResult();

        $returnValue = [
            'status' => $result->status,
            'matched' => $result->isMatched(),
            'video_id' => $result->videoId,
            'episode_id' => $result->episodeId,
            'provider' => $result->providerName,
        ];

        if ($this->debug) {
            $returnValue['debug'] = [
                'release_id' => $this->context->releaseId,
                'search_name' => $this->context->searchName,
                'parsed_info' => $this->parsed,
                'provider_results' => $this->providerResults,
            ];
        }

        return $returnValue;
    }
}
