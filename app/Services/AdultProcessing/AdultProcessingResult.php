<?php

namespace App\Services\AdultProcessing;

/**
 * Result object returned by adult movie processing operations.
 */
class AdultProcessingResult
{
    public const STATUS_MATCHED = 'matched';

    public const STATUS_NOT_FOUND = 'not_found';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_PENDING = 'pending';

    /**
     * @param  array<string, mixed>  $debug
     * @param  array<string, mixed>  $movieData
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $title = null,
        public readonly ?string $providerName = null,
        public readonly array $movieData = [],
        public readonly array $debug = [],
    ) {}

    /**
     * Create a successful match result.
     *
     * @param  array<string, mixed>  $debug
     * @param  array<string, mixed>  $movieData
     */
    public static function matched(
        string $title,
        string $providerName,
        array $movieData,
        array $debug = []
    ): self {
        return new self(
            status: self::STATUS_MATCHED,
            title: $title,
            providerName: $providerName,
            movieData: $movieData,
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
     * Create a failed result.
     */
    public static function failed(string $reason = '', ?string $providerName = null): self
    {
        return new self(
            status: self::STATUS_FAILED,
            providerName: $providerName,
            debug: ['reason' => $reason],
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
     * Create a pending result.
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

    /**
     * Get the box cover URL if available.
     */
    public function getBoxCover(): ?string
    {
        return $this->movieData['boxcover'] ?? null;
    }

    /**
     * Get the back cover URL if available.
     */
    public function getBackCover(): ?string
    {
        return $this->movieData['backcover'] ?? null;
    }

    /**
     * Get the synopsis if available.
     */
    public function getSynopsis(): ?string
    {
        return $this->movieData['synopsis'] ?? null;
    }

    /**
     * Get the direct URL if available.
     */
    public function getDirectUrl(): ?string
    {
        return $this->movieData['directurl'] ?? null;
    }
}
