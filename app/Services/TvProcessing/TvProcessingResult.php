<?php

namespace App\Services\TvProcessing;

/**
 * Result object returned by TV processing operations.
 */
class TvProcessingResult
{
    public const STATUS_MATCHED = 'matched';

    public const STATUS_NOT_FOUND = 'not_found';

    public const STATUS_PARSE_FAILED = 'parse_failed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_PENDING = 'pending';

    /**
     * @param  array<string, mixed>  $debug
     */
    public function __construct(
        public readonly string $status,
        public readonly ?int $videoId = null,
        public readonly ?int $episodeId = null,
        public readonly ?string $providerName = null,
        public readonly array $debug = [],
    ) {}

    /**
     * Create a successful match result.
     *
     * @param  array<string, mixed>  $debug
     */
    public static function matched(int $videoId, int $episodeId, string $providerName, array $debug = []): self
    {
        return new self(
            status: self::STATUS_MATCHED,
            videoId: $videoId,
            episodeId: $episodeId,
            providerName: $providerName,
            debug: $debug,
        );
    }

    /**
     * Create a not found result.
     *
     * @param  array<string, mixed>  $debug
     */
    public static function notFound(?string $providerName = null, array $debug = []): self
    {
        return new self(
            status: self::STATUS_NOT_FOUND,
            providerName: $providerName,
            debug: $debug,
        );
    }

    /**
     * Create a parse failed result.
     *
     * @param  array<string, mixed>  $debug
     */
    public static function parseFailed(array $debug = []): self
    {
        return new self(
            status: self::STATUS_PARSE_FAILED,
            debug: $debug,
        );
    }

    /**
     * Create a skipped result.
     */
    public static function skipped(string $reason = '', ?string $providerName = null): self
    {
        return new self(
            status: self::STATUS_SKIPPED,
            providerName: $providerName,
            debug: ['reason' => $reason],
        );
    }

    /**
     * Create a pending result (release still needs processing).
     */
    public static function pending(): self
    {
        return new self(status: self::STATUS_PENDING);
    }

    /**
     * Check if the result is a successful match.
     */
    public function isMatched(): bool
    {
        return $this->status === self::STATUS_MATCHED;
    }

    /**
     * Check if processing should continue to the next provider.
     */
    public function shouldContinueProcessing(): bool
    {
        return ! $this->isMatched();
    }
}
