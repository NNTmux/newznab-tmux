<?php

declare(strict_types=1);

namespace App\Support\Data;

use App\Models\Settings;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Configuration settings for ProcessReleases operations.
 *
 * Hydrate from raw {@see Settings} rows via {@see self::forDatabase()}
 * (renamed from `fromDatabase` to avoid spatie/laravel-data's magical-creation
 * recursion on `self::from()`).
 */
#[TypeScript]
final class ProcessReleasesSettings extends Data
{
    public function __construct(
        public int $collectionDelayTime = 2,
        public int $crossPostTime = 2,
        public int $releaseCreationLimit = 1000,
        public int $completion = 0,
        public int $collectionTimeout = 48,
        public int $maxSizeToFormRelease = 0,
        public int $minSizeToFormRelease = 0,
        public int $minFilesToFormRelease = 0,
        public int $releaseRetentionDays = 0,
        public bool $deletePasswordedRelease = false,
        public int $miscOtherRetentionHours = 0,
        public int $miscHashedRetentionHours = 0,
        public int $partRetentionHours = 24,
        public ?string $lastRunTime = null,
    ) {
        // Clamp completion to a sane upper bound (legacy `min(100, …)`).
        if ($this->completion > 100) {
            $this->completion = 100;
        }
    }

    /**
     * Build settings from a raw Settings table array (mixed snake-case keys,
     * stringly-typed values, possible nulls/empty strings).
     *
     * @param  array<string, mixed>  $dbSettings
     */
    public static function forDatabase(array $dbSettings): self
    {
        $getInt = static fn (string $key, int $default): int => (isset($dbSettings[$key]) && $dbSettings[$key] !== '')
                ? (int) $dbSettings[$key]
                : $default;

        return new self(
            collectionDelayTime: $getInt('delaytime', 2),
            crossPostTime: $getInt('crossposttime', 2),
            releaseCreationLimit: $getInt('maxnzbsprocessed', 1000),
            completion: $getInt('completionpercent', 0),
            collectionTimeout: $getInt('collection_timeout', 48),
            maxSizeToFormRelease: $getInt('maxsizetoformrelease', 0),
            minSizeToFormRelease: $getInt('minsizetoformrelease', 0),
            minFilesToFormRelease: $getInt('minfilestoformrelease', 0),
            releaseRetentionDays: $getInt('releaseretentiondays', 0),
            deletePasswordedRelease: ((int) ($dbSettings['deletepasswordedrelease'] ?? 0)) === 1,
            miscOtherRetentionHours: $getInt('miscotherretentionhours', 0),
            miscHashedRetentionHours: $getInt('mischashedretentionhours', 0),
            partRetentionHours: $getInt('partretentionhours', 24),
            lastRunTime: ! empty($dbSettings['last_run_time']) ? (string) $dbSettings['last_run_time'] : null,
        );
    }

    public function hasValidCompletion(): bool
    {
        return $this->completion >= 0 && $this->completion <= 100;
    }

    public function hasRetentionCleanup(): bool
    {
        return $this->releaseRetentionDays > 0;
    }

    public function hasCrossPostDetection(): bool
    {
        return $this->crossPostTime > 0;
    }

    public function hasCompletionCleanup(): bool
    {
        return $this->completion > 0;
    }
}
