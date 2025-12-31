<?php

namespace App\Observers;

use App\Facades\Search;
use App\Models\MovieInfo;
use Illuminate\Support\Facades\Log;

class MovieInfoObserver
{
    /**
     * Handle the MovieInfo "created" event.
     */
    public function created(MovieInfo $movie): void
    {
        $this->syncToSearchIndex($movie);
    }

    /**
     * Handle the MovieInfo "updated" event.
     */
    public function updated(MovieInfo $movie): void
    {
        $this->syncToSearchIndex($movie);
    }

    /**
     * Handle the MovieInfo "deleted" event.
     */
    public function deleted(MovieInfo $movie): void
    {
        try {
            Search::deleteMovie($movie->id);
        } catch (\Throwable $e) {
            Log::error('MovieInfoObserver: Failed to delete movie from search index', [
                'movie_id' => $movie->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sync the movie to the search index.
     */
    private function syncToSearchIndex(MovieInfo $movie): void
    {
        try {
            Search::insertMovie([
                'id' => $movie->id,
                'imdbid' => $movie->imdbid ?? 0,
                'tmdbid' => $movie->tmdbid ?? 0,
                'traktid' => $movie->traktid ?? 0,
                'title' => $movie->title ?? '',
                'year' => $movie->year ?? '',
                'genre' => $movie->genre ?? '',
                'actors' => $movie->actors ?? '',
                'director' => $movie->director ?? '',
                'rating' => $movie->rating ?? '',
                'plot' => $movie->plot ?? '',
            ]);
        } catch (\Throwable $e) {
            Log::error('MovieInfoObserver: Failed to sync movie to search index', [
                'movie_id' => $movie->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

