<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalizes release rows into search index document fields (Manticore + Elasticsearch).
 */
final class ReleaseSearchIndexDocument
{
    /**
     * @param  array<string, mixed>  $row  Keys from DB or insert parameters
     * @return array<string, mixed>
     */
    public static function normalize(array $row): array
    {
        $postdateTs = self::datetimeToUnix($row['postdate'] ?? null);
        $adddateTs = self::datetimeToUnix($row['adddate'] ?? null);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'searchname' => (string) ($row['searchname'] ?? ''),
            'fromname' => (string) ($row['fromname'] ?? ''),
            'categories_id' => (int) ($row['categories_id'] ?? 0),
            'filename' => (string) ($row['filename'] ?? ''),
            'imdbid' => (string) ($row['imdbid'] ?? ''),
            'tmdbid' => (int) ($row['tmdbid'] ?? 0),
            'traktid' => (int) ($row['traktid'] ?? 0),
            'tvdb' => (int) ($row['tvdb'] ?? 0),
            'tvmaze' => (int) ($row['tvmaze'] ?? 0),
            'tvrage' => (int) ($row['tvrage'] ?? 0),
            'videos_id' => (int) ($row['videos_id'] ?? 0),
            'movieinfo_id' => (int) ($row['movieinfo_id'] ?? 0),
            'size' => (int) ($row['size'] ?? 0),
            'postdate_ts' => $postdateTs,
            'adddate_ts' => $adddateTs,
            'totalpart' => (int) ($row['totalpart'] ?? 0),
            'grabs' => (int) ($row['grabs'] ?? 0),
            'passwordstatus' => (int) ($row['passwordstatus'] ?? 0),
            'groups_id' => (int) ($row['groups_id'] ?? 0),
            'nzbstatus' => (int) ($row['nzbstatus'] ?? 0),
            'haspreview' => (int) ($row['haspreview'] ?? 0),
        ];
    }

    private static function datetimeToUnix(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value;
        }
        $ts = strtotime((string) $value);

        return $ts !== false ? $ts : 0;
    }
}
