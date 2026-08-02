<?php

declare(strict_types=1);

namespace App\Services\Search\Support;

use App\Enums\SecondarySearchIndex;

final class ManticoreIndexRegistry
{
    /**
     * @return array<string, array{settings: array<string, int|string>, columns: array<string, array<string, mixed>>}>
     */
    public static function definitions(): array
    {
        $settings = self::fullTextSettings();

        return [
            'releases' => ['settings' => $settings, 'columns' => [
                'guid' => ['type' => 'string'], 'name' => ['type' => 'text'],
                'searchname' => ['type' => 'text'], 'plainsearchname' => ['type' => 'text'],
                'fromname' => ['type' => 'text'], 'filename' => ['type' => 'text'],
                'category_name' => ['type' => 'string'], 'parent_category' => ['type' => 'string'],
                'sub_category' => ['type' => 'string'], 'group_name' => ['type' => 'string'],
                'categories_id' => ['type' => 'integer'], 'imdbid' => ['type' => 'string'],
                'tmdbid' => ['type' => 'integer'], 'traktid' => ['type' => 'integer'],
                'tvdb' => ['type' => 'integer'], 'tvmaze' => ['type' => 'integer'],
                'tvrage' => ['type' => 'integer'], 'trakt' => ['type' => 'integer'],
                'imdb' => ['type' => 'string'], 'tmdb' => ['type' => 'integer'],
                'videos_id' => ['type' => 'integer'],
                'tv_episodes_id' => ['type' => 'integer'], 'movieinfo_id' => ['type' => 'integer'],
                'anidbid' => ['type' => 'integer'], 'parentid' => ['type' => 'integer'],
                'episode_title' => ['type' => 'text'], 'series' => ['type' => 'integer'],
                'episode' => ['type' => 'integer'], 'firstaired_ts' => ['type' => 'bigint'],
                'size' => ['type' => 'bigint'],
                'postdate_ts' => ['type' => 'bigint'], 'adddate_ts' => ['type' => 'bigint'],
                'totalpart' => ['type' => 'integer'], 'grabs' => ['type' => 'integer'],
                'comments' => ['type' => 'integer'], 'nfostatus' => ['type' => 'integer'],
                'jpgstatus' => ['type' => 'integer'], 'nfoid' => ['type' => 'integer'],
                'reid' => ['type' => 'integer'],
                'passwordstatus' => ['type' => 'bigint'], 'groups_id' => ['type' => 'integer'],
                'nzbstatus' => ['type' => 'integer'], 'haspreview' => ['type' => 'bigint'],
            ]],
            'predb' => ['settings' => $settings, 'columns' => [
                'title' => ['type' => 'text', 'attribute' => true],
                'filename' => ['type' => 'text', 'attribute' => true],
                'source' => ['type' => 'string', 'attribute' => true],
            ]],
            'movies' => ['settings' => $settings, 'columns' => [
                'imdbid' => ['type' => 'string'], 'tmdbid' => ['type' => 'integer'],
                'traktid' => ['type' => 'integer'], 'title' => ['type' => 'text'],
                'year' => ['type' => 'text'], 'genre' => ['type' => 'text'],
                'actors' => ['type' => 'text'], 'director' => ['type' => 'text'],
                'rating' => ['type' => 'text'], 'plot' => ['type' => 'text'],
            ]],
            'tvshows' => ['settings' => $settings, 'columns' => [
                'title' => ['type' => 'text'], 'tvdb' => ['type' => 'integer'],
                'trakt' => ['type' => 'integer'], 'tvmaze' => ['type' => 'integer'],
                'tvrage' => ['type' => 'integer'], 'imdb' => ['type' => 'string'],
                'tmdb' => ['type' => 'integer'], 'started' => ['type' => 'text'],
                'type' => ['type' => 'integer'],
            ]],
            ...self::secondaryDefinitions($settings),
        ];
    }

    /** @return array{ranker: string, fields: array<string, int>, fuzzy_distance: int} */
    public static function profile(string $logical): array
    {
        $fields = match ($logical) {
            'releases' => ['searchname' => 12, 'plainsearchname' => 10, 'name' => 8, 'filename' => 5, 'fromname' => 1],
            'predb' => ['title' => 12, 'filename' => 5],
            'movies' => ['title' => 12, 'director' => 5, 'actors' => 3, 'genre' => 2, 'plot' => 1],
            'tvshows' => ['title' => 12],
            'music' => ['title' => 12, 'artist' => 9],
            'books' => ['title' => 12, 'author' => 9],
            'console' => ['title' => 12],
            'steam' => ['name' => 12],
            'games', 'anime' => ['title' => 12],
            default => [],
        };

        return ['ranker' => 'sph04', 'fields' => $fields, 'fuzzy_distance' => 2];
    }

    /** @return list<string> */
    public static function autocompleteFields(string $logical): array
    {
        return match ($logical) {
            'releases' => ['searchname', 'name'],
            'predb' => ['title', 'filename'],
            'movies', 'tvshows', 'music', 'books', 'games', 'console', 'anime' => ['title'],
            'steam' => ['name'],
            default => [],
        };
    }

    /** @return array<string, int|string> */
    public static function inspectableSettings(): array
    {
        // Manticore 28 omits defaults/no-op settings such as min_prefix_len=0
        // and exact_words from SHOW TABLE ... SETTINGS / SHOW CREATE TABLE.
        return ['min_infix_len' => 2, 'index_field_lengths' => 1];
    }

    /** @param array<string, string> $configuredIndexes */
    public static function logicalName(string $table, array $configuredIndexes = []): ?string
    {
        foreach ($configuredIndexes as $logical => $configuredTable) {
            if ($configuredTable === $table) {
                return (string) $logical;
            }
        }

        return str_ends_with($table, '_rt') ? array_search($table, self::defaultTableNames(), true) ?: null : null;
    }

    /** @return array<string, string> */
    public static function defaultTableNames(): array
    {
        $names = [];
        foreach (array_keys(self::definitions()) as $logical) {
            $names[$logical] = $logical.'_rt';
        }

        return $names;
    }

    /** @return array<string, int|string> */
    private static function fullTextSettings(): array
    {
        return [
            'min_prefix_len' => 0,
            'min_infix_len' => 2,
            'exact_words' => 1,
            'index_field_lengths' => 1,
        ];
    }

    /**
     * @param  array<string, int|string>  $settings
     * @return array<string, array{settings: array<string, int|string>, columns: array<string, array<string, mixed>>}>
     */
    private static function secondaryDefinitions(array $settings): array
    {
        $definitions = [];
        foreach (SecondarySearchIndex::cases() as $index) {
            $definitions[$index->value] = ['settings' => $settings, 'columns' => $index->manticoreColumns()];
        }

        return $definitions;
    }
}
