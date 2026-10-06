<?php

namespace App\Services;

use App\Models\Category;

final class BookProcessingCandidateQuery
{
    public const int MAX_FAILURES = 5;

    public const int EXHAUSTED = -3;

    public static function categoryCondition(): string
    {
        return '(categories_id BETWEEN '.Category::BOOKS_ROOT.' AND '.Category::BOOKS_UNKNOWN
            .' OR categories_id = '.Category::MUSIC_AUDIOBOOK.')';
    }

    public static function metadataCondition(int $lookupMode): string
    {
        if ($lookupMode <= 0) {
            return '0 = 1';
        }

        return '(bookinfo_id IS NULL AND book_lookup_attempts < '.self::MAX_FAILURES
            .' AND (book_lookup_retry_at IS NULL OR book_lookup_retry_at <= CURRENT_TIMESTAMP)'
            .($lookupMode === 2 ? ' AND isrenamed = 1' : '').')';
    }

    public static function normalizationCondition(int $lookupMode): string
    {
        if ($lookupMode <= 0) {
            return '0 = 1';
        }

        return '(book_name_normalized_at IS NULL AND ('
            .($lookupMode === 2 ? '' : 'isrenamed = 0 OR ')
            ."searchname LIKE 'N:/NZB%' OR searchname LIKE 'N_NZB_%'"
            ." OR name LIKE 'N:/NZB%' OR name LIKE 'N_NZB_%'))";
    }

    /** Shared by dispatch, workers, and the tmux ready-work count. */
    public static function workCondition(int $lookupMode): string
    {
        return self::categoryCondition().' AND ('.self::metadataCondition($lookupMode)
            .' OR '.self::normalizationCondition($lookupMode).')';
    }

    public static function retryDelay(int $failures, int $providerRetryAfter): int
    {
        return max($providerRetryAfter, match ($failures) {
            1 => 300,
            2 => 900,
            3 => 3600,
            default => 21600,
        });
    }
}
