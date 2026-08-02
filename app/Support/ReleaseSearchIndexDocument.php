<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalizes release rows into search index document fields (Manticore + Elasticsearch).
 */
final class ReleaseSearchIndexDocument
{
    /**
     * Fields that may be returned to browse/search consumers.
     * Sensitive release data must never be added to this list.
     *
     * @return list<string>
     */
    public static function fields(): array
    {
        return [
            'id', 'guid', 'name', 'searchname', 'plainsearchname', 'fromname', 'filename',
            'categories_id', 'category_name', 'parent_category', 'sub_category',
            'groups_id', 'group_name', 'imdbid', 'tmdbid', 'traktid', 'tvdb', 'tvmaze',
            'tvrage', 'trakt', 'imdb', 'tmdb', 'videos_id', 'tv_episodes_id', 'movieinfo_id', 'anidbid',
            'parentid', 'episode_title', 'series', 'episode', 'firstaired_ts',
            'size', 'postdate_ts', 'adddate_ts', 'totalpart', 'grabs', 'comments',
            'passwordstatus', 'nzbstatus', 'nfostatus', 'haspreview', 'jpgstatus',
            'nfoid', 'reid',
        ];
    }

    /**
     * Normalize a release row for bulk indexing. Rows already produced by {@see normalize()}
     * (e.g. from release search populate) lack `postdate` / `adddate` keys;
     * calling {@see normalize()} again would zero `postdate_ts` / `adddate_ts` unless those sources are restored.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function normalizeForBulk(array $row): array
    {
        if (array_key_exists('postdate_ts', $row) && ! array_key_exists('postdate', $row)) {
            $row = array_merge($row, [
                'postdate' => $row['postdate_ts'] ?? null,
                'adddate' => $row['adddate_ts'] ?? null,
            ]);
        }

        return self::normalize($row);
    }

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
            'guid' => (string) ($row['guid'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'searchname' => (string) ($row['searchname'] ?? ''),
            'plainsearchname' => (string) ($row['plainsearchname'] ?? self::plainSearchName($row['searchname'] ?? '')),
            'fromname' => (string) ($row['fromname'] ?? ''),
            'categories_id' => (int) ($row['categories_id'] ?? 0),
            'category_name' => (string) ($row['category_name'] ?? ''),
            'parent_category' => (string) ($row['parent_category'] ?? ''),
            'sub_category' => (string) ($row['sub_category'] ?? ''),
            'group_name' => (string) ($row['group_name'] ?? ''),
            'filename' => (string) ($row['filename'] ?? ''),
            'imdbid' => (string) ($row['imdbid'] ?? ''),
            'tmdbid' => (int) ($row['tmdbid'] ?? 0),
            'traktid' => (int) ($row['traktid'] ?? 0),
            'tvdb' => (int) ($row['tvdb'] ?? 0),
            'tvmaze' => (int) ($row['tvmaze'] ?? 0),
            'tvrage' => (int) ($row['tvrage'] ?? 0),
            'trakt' => (int) ($row['trakt'] ?? 0),
            'imdb' => (string) ($row['imdb'] ?? ''),
            'tmdb' => (int) ($row['tmdb'] ?? 0),
            'videos_id' => (int) ($row['videos_id'] ?? 0),
            'tv_episodes_id' => (int) ($row['tv_episodes_id'] ?? 0),
            'movieinfo_id' => (int) ($row['movieinfo_id'] ?? 0),
            'anidbid' => (int) ($row['anidbid'] ?? 0),
            'parentid' => (int) ($row['parentid'] ?? 0),
            'episode_title' => (string) ($row['episode_title'] ?? ''),
            'series' => (int) ($row['series'] ?? 0),
            'episode' => (int) ($row['episode'] ?? 0),
            'firstaired_ts' => self::datetimeToUnix($row['firstaired'] ?? ($row['firstaired_ts'] ?? null)),
            'size' => (int) ($row['size'] ?? 0),
            'postdate_ts' => $postdateTs,
            'adddate_ts' => $adddateTs,
            'totalpart' => (int) ($row['totalpart'] ?? 0),
            'grabs' => (int) ($row['grabs'] ?? 0),
            'comments' => (int) ($row['comments'] ?? 0),
            'passwordstatus' => (int) ($row['passwordstatus'] ?? 0),
            'groups_id' => (int) ($row['groups_id'] ?? 0),
            'nzbstatus' => (int) ($row['nzbstatus'] ?? 0),
            'nfostatus' => (int) ($row['nfostatus'] ?? 0),
            'haspreview' => (int) ($row['haspreview'] ?? 0),
            'jpgstatus' => (int) ($row['jpgstatus'] ?? 0),
            'nfoid' => (int) ($row['nfoid'] ?? 0),
            'reid' => (int) ($row['reid'] ?? 0),
        ];
    }

    /**
     * Convert an indexed document into the date/attribute names expected by the views.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public static function toReleaseRow(array $document): array
    {
        $row = self::normalizeForBulk($document);
        $row['postdate'] = self::unixToDatetime((int) $row['postdate_ts']);
        $row['adddate'] = self::unixToDatetime((int) $row['adddate_ts']);

        return $row;
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

    private static function plainSearchName(mixed $value): string
    {
        return trim((string) preg_replace('/[._-]+/', ' ', (string) $value));
    }

    private static function unixToDatetime(int $timestamp): ?string
    {
        return $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : null;
    }
}
