<?php

declare(strict_types=1);

namespace App\Services\TvProcessing;

use App\Enums\TvLookupStatus;
use App\Models\Category;

/**
 * SQL shared by the TV bucket dispatcher, the pipeline workers, the tmux ready-work
 * count and the episode retry command, so they can never drift apart.
 */
final class TvProcessingCandidateQuery
{
    /** Transient failures before a release with no show is given up as ShowNotFound. */
    public const int MAX_TRANSIENT_ATTEMPTS = 8;

    /** Episode lookups for a known show before the release stops being retried. */
    public const int MAX_EPISODE_ATTEMPTS = 6;

    /** Delay after a transient failure on a release whose show is already known. */
    public const int KNOWN_SHOW_TRANSIENT_DELAY = 900;

    public static function categoryCondition(): string
    {
        return '(categories_id BETWEEN '.Category::TV_ROOT.' AND '.Category::TV_OTHER
            .' AND categories_id <> '.Category::TV_ANIME.' AND size > 1048576)';
    }

    /** Releases with no show yet that are due for the bucket workers. */
    public static function workCondition(int $lookupMode): string
    {
        if ($lookupMode <= 0) {
            return '0 = 1';
        }

        return self::categoryCondition()
            .' AND videos_id = 0 AND tv_episodes_id = '.TvLookupStatus::Pending->value
            .' AND '.self::dueCondition()
            .($lookupMode === 2 ? ' AND isrenamed = 1' : '');
    }

    /** Releases linked to a show whose episode is due for another lookup. */
    public static function episodeRetryCondition(bool $ignoreSchedule = false): string
    {
        $condition = self::categoryCondition()
            .' AND videos_id > 0 AND tv_episodes_id = '.TvLookupStatus::EpisodeMissing->value;

        if ($ignoreSchedule) {
            return $condition;
        }

        return $condition.' AND tv_lookup_attempts < '.self::MAX_EPISODE_ATTEMPTS.' AND '.self::dueCondition();
    }

    public static function transientDelay(int $attempts, int $providerRetryAfter = 0): int
    {
        return max($providerRetryAfter, match ($attempts) {
            1 => 300,
            2 => 900,
            3 => 3600,
            default => 21600,
        });
    }

    public static function episodeDelay(int $attempts): int
    {
        return match ($attempts) {
            1 => 3600,
            2 => 21600,
            3 => 86400,
            4 => 259200,
            default => 604800,
        };
    }

    /**
     * Columns that hand a release back to TV processing from scratch.
     *
     * @return array{videos_id: int, tv_episodes_id: int, tv_lookup_attempts: int, tv_lookup_retry_at: null}
     */
    public static function resetColumns(): array
    {
        return [
            'videos_id' => 0,
            'tv_episodes_id' => TvLookupStatus::Pending->value,
            'tv_lookup_attempts' => 0,
            'tv_lookup_retry_at' => null,
        ];
    }

    private static function dueCondition(): string
    {
        return '(tv_lookup_retry_at IS NULL OR tv_lookup_retry_at <= CURRENT_TIMESTAMP)';
    }
}
