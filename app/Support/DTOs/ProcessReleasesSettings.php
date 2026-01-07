<?php

declare(strict_types=1);

namespace App\Support\DTOs;

/**
 * Configuration settings for ProcessReleases operations.
 */
final readonly class ProcessReleasesSettings
{
    public function __construct(
        public int $collectionDelayTime,
        public int $crossPostTime,
        public int $releaseCreationLimit,
        public int $completion,
        public int $collectionTimeout,
        public int $maxSizeToFormRelease,
        public int $minSizeToFormRelease,
        public int $minFilesToFormRelease,
        public int $releaseRetentionDays,
        public bool $deletePasswordedRelease,
        public int $miscOtherRetentionHours,
        public int $miscHashedRetentionHours,
        public int $partRetentionHours,
        public ?string $lastRunTime,
    ) {}

    /**
     * Default settings values.
     */
    private const DEFAULTS = [
        'collectionDelayTime' => 2,
        'crossPostTime' => 2,
        'releaseCreationLimit' => 1000,
        'completion' => 0,
        'collectionTimeout' => 48,
        'maxSizeToFormRelease' => 0,
        'minSizeToFormRelease' => 0,
        'minFilesToFormRelease' => 0,
        'releaseRetentionDays' => 0,
        'deletePasswordedRelease' => false,
        'miscOtherRetentionHours' => 0,
        'miscHashedRetentionHours' => 0,
        'partRetentionHours' => 24,
        'lastRunTime' => null,
    ];

    /**
     * Create settings from database values.
     *
     * @param  array<string, mixed>  $dbSettings
     */
    public static function fromDatabase(array $dbSettings): self
    {
        $getInt = static fn (string $key, int $default): int => ($dbSettings[$key] ?? '') !== '' ? (int) $dbSettings[$key] : $default;

        $completion = min(100, $getInt('completionpercent', self::DEFAULTS['completion']));

        return new self(
            collectionDelayTime: $getInt('delaytime', self::DEFAULTS['collectionDelayTime']),
            crossPostTime: $getInt('crossposttime', self::DEFAULTS['crossPostTime']),
            releaseCreationLimit: $getInt('maxnzbsprocessed', self::DEFAULTS['releaseCreationLimit']),
            completion: $completion,
            collectionTimeout: $getInt('collection_timeout', self::DEFAULTS['collectionTimeout']),
            maxSizeToFormRelease: $getInt('maxsizetoformrelease', self::DEFAULTS['maxSizeToFormRelease']),
            minSizeToFormRelease: $getInt('minsizetoformrelease', self::DEFAULTS['minSizeToFormRelease']),
            minFilesToFormRelease: $getInt('minfilestoformrelease', self::DEFAULTS['minFilesToFormRelease']),
            releaseRetentionDays: $getInt('releaseretentiondays', self::DEFAULTS['releaseRetentionDays']),
            deletePasswordedRelease: ((int) ($dbSettings['deletepasswordedrelease'] ?? 0)) === 1,
            miscOtherRetentionHours: $getInt('miscotherretentionhours', self::DEFAULTS['miscOtherRetentionHours']),
            miscHashedRetentionHours: $getInt('mischashedretentionhours', self::DEFAULTS['miscHashedRetentionHours']),
            partRetentionHours: $getInt('partretentionhours', self::DEFAULTS['partRetentionHours']),
            lastRunTime: ! empty($dbSettings['last_run_time']) ? (string) $dbSettings['last_run_time'] : null,
        );
    }

    /**
     * Check if completion percentage is valid.
     */
    public function hasValidCompletion(): bool
    {
        return $this->completion >= 0 && $this->completion <= 100;
    }

    /**
     * Check if retention cleanup is enabled.
     */
    public function hasRetentionCleanup(): bool
    {
        return $this->releaseRetentionDays > 0;
    }

    /**
     * Check if cross-post detection is enabled.
     */
    public function hasCrossPostDetection(): bool
    {
        return $this->crossPostTime > 0;
    }

    /**
     * Check if completion-based cleanup is enabled.
     */
    public function hasCompletionCleanup(): bool
    {
        return $this->completion > 0;
    }
}
