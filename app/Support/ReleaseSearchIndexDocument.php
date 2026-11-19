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
            'media_movie_name', 'media_file_name', 'media_unique_id',
            'media_container_format', 'media_video_format', 'media_video_codec',
            'media_video_width', 'media_video_height', 'media_audio_format',
            'media_audio_channels', 'media_audio_language', 'media_subtitle_language',
            'has_media_info',
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
            'guid' => self::text($row['guid'] ?? ''),
            'name' => self::text($row['name'] ?? ''),
            'searchname' => self::text($row['searchname'] ?? ''),
            'plainsearchname' => self::text($row['plainsearchname'] ?? self::plainSearchName($row['searchname'] ?? '')),
            'fromname' => self::text($row['fromname'] ?? ''),
            'categories_id' => (int) ($row['categories_id'] ?? 0),
            'category_name' => self::text($row['category_name'] ?? ''),
            'parent_category' => self::text($row['parent_category'] ?? ''),
            'sub_category' => self::text($row['sub_category'] ?? ''),
            'group_name' => self::text($row['group_name'] ?? ''),
            'filename' => self::text($row['filename'] ?? ''),
            'imdbid' => self::text($row['imdbid'] ?? ''),
            'tmdbid' => (int) ($row['tmdbid'] ?? 0),
            'traktid' => (int) ($row['traktid'] ?? 0),
            'tvdb' => (int) ($row['tvdb'] ?? 0),
            'tvmaze' => (int) ($row['tvmaze'] ?? 0),
            'tvrage' => (int) ($row['tvrage'] ?? 0),
            'trakt' => (int) ($row['trakt'] ?? 0),
            'imdb' => self::text($row['imdb'] ?? ''),
            'tmdb' => (int) ($row['tmdb'] ?? 0),
            'videos_id' => (int) ($row['videos_id'] ?? 0),
            'tv_episodes_id' => (int) ($row['tv_episodes_id'] ?? 0),
            'movieinfo_id' => (int) ($row['movieinfo_id'] ?? 0),
            'anidbid' => (int) ($row['anidbid'] ?? 0),
            'parentid' => (int) ($row['parentid'] ?? 0),
            'episode_title' => self::text($row['episode_title'] ?? ''),
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
            'media_movie_name' => self::text($row['media_movie_name'] ?? ''),
            'media_file_name' => self::text($row['media_file_name'] ?? ''),
            'media_unique_id' => self::text($row['media_unique_id'] ?? ''),
            'media_container_format' => self::text($row['media_container_format'] ?? ''),
            'media_video_format' => self::text($row['media_video_format'] ?? ''),
            'media_video_codec' => self::text($row['media_video_codec'] ?? ''),
            'media_video_width' => (int) ($row['media_video_width'] ?? 0),
            'media_video_height' => (int) ($row['media_video_height'] ?? 0),
            'media_audio_format' => self::text($row['media_audio_format'] ?? ''),
            'media_audio_channels' => self::text($row['media_audio_channels'] ?? ''),
            'media_audio_language' => self::text($row['media_audio_language'] ?? ''),
            'media_subtitle_language' => self::text($row['media_subtitle_language'] ?? ''),
            'has_media_info' => (int) ($row['has_media_info'] ?? 0),
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

    /**
     * Text the search clients can always JSON-encode: invalid UTF-8 is replaced
     * and control characters, which carry no search value, become spaces.
     */
    private static function text(mixed $value): string
    {
        $text = (string) $value;
        if ($text === '') {
            return '';
        }

        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_scrub($text, 'UTF-8');
        }

        return (string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $text);
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
