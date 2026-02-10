<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Genre;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class GenreService
{
    public const int CONSOLE_TYPE = Category::GAME_ROOT;

    public const int MUSIC_TYPE = Category::MUSIC_ROOT;

    public const int GAME_TYPE = Category::PC_ROOT;

    public const int STATUS_ENABLED = 0;

    public const int STATUS_DISABLED = 1;

    /**
     * Get genres with optional filtering and caching.
     */
    public function getGenres(string $type = '', bool $activeOnly = false): Collection
    {
        $cacheKey = 'genres_'.md5($type.'_'.($activeOnly ? '1' : '0'));

        return Cache::remember($cacheKey, now()->addMinutes(config('nntmux.cache_expiry_long')), function () use ($type, $activeOnly) {
            return Genre::getFiltered($type, $activeOnly);
        });
    }

    /**
     * Load genres as an associative array (id => lowercase title).
     */
    public function loadGenres(string $type): array
    {
        $genres = $this->getGenres($type);
        $genresArray = [];
        foreach ($genres as $genre) {
            /** @var \App\Models\Genre $genre */
            $genresArray[$genre->id] = strtolower($genre->title);
        }

        return $genresArray;
    }

    /**
     * Get a range of genres with pagination.
     */
    public function getRange(int $start, int $num, string $type = '', bool $activeOnly = false): Collection
    {
        return Genre::getFiltered($type, $activeOnly)
            ->skip($start)
            ->take($num);
    }

    /**
     * Get count of genres.
     */
    public function getCount(string $type = '', bool $activeOnly = false): int
    {
        return Genre::getFilteredCount($type, $activeOnly);
    }

    /**
     * Get genre by ID.
     */
    public function getById(int $id): ?Genre
    {
        return Genre::find($id);
    }

    /**
     * Update genre disabled status.
     */
    public function update(int $id, int $disabled): int
    {
        $this->clearCache();

        return Genre::where('id', $id)->update(['disabled' => $disabled]);
    }

    /**
     * Get all disabled genre IDs.
     */
    public function getDisabledIDs(): Collection
    {
        return Cache::remember('disabled_genres', now()->addMinutes(config('nntmux.cache_expiry_long')), function () {
            return Genre::disabled()->get(['id']);
        });
    }

    /**
     * Clear genre-related cache.
     */
    public function clearCache(): void
    {
        Cache::forget('disabled_genres');
        // Clear other genre caches by pattern if needed
    }
}
