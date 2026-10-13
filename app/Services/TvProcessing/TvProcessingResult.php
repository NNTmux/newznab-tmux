<?php

declare(strict_types=1);

namespace App\Services\TvProcessing;

/**
 * Result returned by a pipe, and the final outcome of a release passing through the pipeline.
 */
class TvProcessingResult
{
    public const STATUS_MATCHED = 'matched';

    public const STATUS_SEASON_PACK = 'season_pack';

    public const STATUS_EPISODE_MISSING = 'episode_missing';

    public const STATUS_NOT_FOUND = 'not_found';

    public const STATUS_PARSE_FAILED = 'parse_failed';

    public const STATUS_TRANSIENT = 'transient';

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
        public readonly bool $transient = false,
        public readonly ?int $retryAfter = null,
    ) {}

    /**
     * @param  array<string, mixed>  $debug
     */
    public static function matched(int $videoId, int $episodeId, string $providerName, array $debug = []): self
    {
        return new self(self::STATUS_MATCHED, $videoId, $episodeId, $providerName, $debug);
    }

    public static function seasonPack(int $videoId, string $providerName): self
    {
        return new self(self::STATUS_SEASON_PACK, $videoId, null, $providerName);
    }

    /**
     * The show is known but the episode is not. $transient marks that a provider failed
     * transiently along the way, so the retry comes sooner.
     *
     * @param  array<string, mixed>  $debug
     */
    public static function episodeMissing(int $videoId, ?string $providerName, array $debug = [], bool $transient = false, ?int $retryAfter = null): self
    {
        return new self(self::STATUS_EPISODE_MISSING, $videoId, null, $providerName, $debug, $transient, $retryAfter);
    }

    /**
     * @param  array<string, mixed>  $debug
     */
    public static function notFound(?string $providerName = null, array $debug = []): self
    {
        return new self(self::STATUS_NOT_FOUND, providerName: $providerName, debug: $debug);
    }

    /**
     * @param  array<string, mixed>  $debug
     */
    public static function parseFailed(array $debug = []): self
    {
        return new self(self::STATUS_PARSE_FAILED, debug: $debug);
    }

    /**
     * @param  array<string, mixed>  $debug
     */
    public static function transient(?string $providerName, ?int $retryAfter = null, array $debug = []): self
    {
        return new self(self::STATUS_TRANSIENT, providerName: $providerName, debug: $debug, transient: true, retryAfter: $retryAfter);
    }

    public static function skipped(string $reason = '', ?string $providerName = null): self
    {
        return new self(self::STATUS_SKIPPED, providerName: $providerName, debug: ['reason' => $reason]);
    }

    public static function pending(): self
    {
        return new self(self::STATUS_PENDING);
    }

    public function isMatched(): bool
    {
        return $this->status === self::STATUS_MATCHED;
    }
}
