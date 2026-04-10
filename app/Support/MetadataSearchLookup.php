<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Shared helpers for migrating Scout/MySQL boolean fulltext queries to the unified search engine.
 */
final class MetadataSearchLookup
{
    /**
     * Turn legacy "+word1 +word2" boolean query into a space-separated phrase for Manticore/ES.
     */
    public static function normalizeBooleanSearchWords(string $searchwords): string
    {
        $s = str_replace('+', ' ', $searchwords);

        return trim(preg_replace('/\s+/', ' ', $s));
    }
}
