<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AnidbInfo;
use App\Models\AnidbTitle;
use App\Models\BookInfo;
use App\Models\ConsoleInfo;
use App\Models\GamesInfo;
use App\Models\MusicInfo;
use App\Models\SteamApp;
use Carbon\Carbon;

/**
 * Map Eloquent models / rows to search-engine documents for secondary indexes.
 */
final class SecondaryIndexDocuments
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function musicFromArray(array $row): array
    {
        return [
            'title' => (string) ($row['title'] ?? ''),
            'artist' => (string) ($row['artist'] ?? ''),
            'year' => (string) ($row['year'] ?? ''),
            'genres_id' => (int) ($row['genres_id'] ?? 0),
            'cover' => (int) ($row['cover'] ?? 0),
        ];
    }

    public static function music(MusicInfo $m): array
    {
        return self::musicFromArray($m->getAttributes());
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function bookFromArray(array $row): array
    {
        return [
            'title' => (string) ($row['title'] ?? ''),
            'author' => (string) ($row['author'] ?? ''),
            'publishdate' => (string) ($row['publishdate'] ?? ''),
            'cover' => (int) ($row['cover'] ?? 0),
        ];
    }

    public static function book(BookInfo $b): array
    {
        return self::bookFromArray($b->getAttributes());
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function gamesFromArray(array $row): array
    {
        $ts = self::releasedateToTimestamp($row['releasedate'] ?? null);

        return [
            'title' => (string) ($row['title'] ?? ''),
            'genres_id' => (int) ($row['genres_id'] ?? 0),
            'releasedate_ts' => $ts,
            'cover' => (int) ($row['cover'] ?? 0),
        ];
    }

    public static function games(GamesInfo $g): array
    {
        return self::gamesFromArray($g->getAttributes());
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function consoleFromArray(array $row): array
    {
        $ts = self::releasedateToTimestamp($row['releasedate'] ?? null);

        return [
            'title' => (string) ($row['title'] ?? ''),
            'platform' => (string) ($row['platform'] ?? ''),
            'genres_id' => (int) ($row['genres_id'] ?? 0),
            'releasedate_ts' => $ts,
            'cover' => (int) ($row['cover'] ?? 0),
        ];
    }

    public static function console(ConsoleInfo $c): array
    {
        return self::consoleFromArray($c->getAttributes());
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function steamFromArray(array $row): array
    {
        return [
            'name' => (string) ($row['name'] ?? ''),
            'appid' => (int) ($row['appid'] ?? 0),
        ];
    }

    public static function steam(SteamApp $s): array
    {
        return self::steamFromArray($s->getAttributes());
    }

    /**
     * @return array<string, mixed>
     */
    public static function animeTitle(AnidbTitle $t, ?AnidbInfo $info = null): array
    {
        $info ??= AnidbInfo::query()->where('anidbid', $t->anidbid)->first();

        return [
            'title' => (string) $t->title,
            'anidbid' => (int) $t->anidbid,
            'anilist_id' => (int) ($info?->anilist_id ?? 0),
            'mal_id' => (int) ($info?->mal_id ?? 0),
            'lang' => (string) $t->lang,
            'title_type' => (string) $t->type,
            'media_type' => (string) ($info?->media_type ?? ''),
            'status' => (string) ($info?->status ?? ''),
        ];
    }

    private static function releasedateToTimestamp(mixed $rd): int
    {
        if ($rd instanceof Carbon) {
            return $rd->getTimestamp();
        }
        if ($rd !== null && $rd !== '') {
            try {
                return Carbon::parse((string) $rd)->getTimestamp();
            } catch (\Throwable) {
                return 0;
            }
        }

        return 0;
    }
}
