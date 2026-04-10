<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Logical names for metadata indexes (Manticore *_rt / Elasticsearch).
 */
enum SecondarySearchIndex: string
{
    case Music = 'music';
    case Books = 'books';
    case Games = 'games';
    case Console = 'console';
    case Steam = 'steam';
    case Anime = 'anime';

    /**
     * Manticore RT column definitions (CreateManticoreIndexes / auto-create).
     *
     * @return array<string, array<string, mixed>>
     */
    public function manticoreColumns(): array
    {
        return match ($this) {
            self::Music => [
                'title' => ['type' => 'text'],
                'artist' => ['type' => 'text'],
                'year' => ['type' => 'string'],
                'genres_id' => ['type' => 'integer'],
                'cover' => ['type' => 'integer'],
            ],
            self::Books => [
                'title' => ['type' => 'text'],
                'author' => ['type' => 'text'],
                'publishdate' => ['type' => 'string'],
                'cover' => ['type' => 'integer'],
            ],
            self::Games => [
                'title' => ['type' => 'text'],
                'genres_id' => ['type' => 'integer'],
                'releasedate_ts' => ['type' => 'bigint'],
                'cover' => ['type' => 'integer'],
            ],
            self::Console => [
                'title' => ['type' => 'text'],
                'platform' => ['type' => 'string'],
                'genres_id' => ['type' => 'integer'],
                'releasedate_ts' => ['type' => 'bigint'],
                'cover' => ['type' => 'integer'],
            ],
            self::Steam => [
                'name' => ['type' => 'text'],
                'appid' => ['type' => 'integer'],
            ],
            self::Anime => [
                'title' => ['type' => 'text'],
                'anidbid' => ['type' => 'integer'],
                'anilist_id' => ['type' => 'integer'],
                'mal_id' => ['type' => 'integer'],
                'lang' => ['type' => 'string'],
                'title_type' => ['type' => 'string'],
                'media_type' => ['type' => 'string'],
                'status' => ['type' => 'string'],
            ],
        };
    }

    /**
     * Fields used for full-text MATCH (Manticore) / multi_match (ES).
     *
     * @return list<string>
     */
    public function fulltextFields(): array
    {
        return match ($this) {
            self::Music => ['artist', 'title'],
            self::Books => ['author', 'title'],
            self::Games => ['title'],
            self::Console => ['title', 'platform'],
            self::Steam => ['name'],
            self::Anime => ['title'],
        };
    }

    /**
     * Stable integer document id for composite anime title rows (PK is multi-column in MySQL).
     */
    public static function animeTitleDocumentId(int $anidbid, string $type, string $lang, string $title): int
    {
        $payload = $anidbid.'|'.$type.'|'.$lang.'|'.$title;

        return (int) sprintf('%u', crc32($payload));
    }
}
