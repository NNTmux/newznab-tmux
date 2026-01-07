<?php

namespace App\Observers;

use App\Facades\Search;
use App\Models\Video;
use Illuminate\Support\Facades\Log;

class VideoObserver
{
    /**
     * Handle the Video "created" event.
     */
    public function created(Video $video): void
    {
        $this->syncToSearchIndex($video);
    }

    /**
     * Handle the Video "updated" event.
     */
    public function updated(Video $video): void
    {
        $this->syncToSearchIndex($video);
    }

    /**
     * Handle the Video "deleted" event.
     */
    public function deleted(Video $video): void
    {
        try {
            Search::deleteTvShow($video->id);
        } catch (\Throwable $e) {
            Log::error('VideoObserver: Failed to delete TV show from search index', [
                'video_id' => $video->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sync the TV show to the search index.
     */
    private function syncToSearchIndex(Video $video): void
    {
        try {
            Search::insertTvShow([
                'id' => $video->id,
                'title' => $video->title ?? '',
                'tvdb' => $video->tvdb ?? 0,
                'trakt' => $video->trakt ?? 0,
                'tvmaze' => $video->tvmaze ?? 0,
                'tvrage' => $video->tvrage ?? 0,
                'imdb' => $video->imdb ?? 0,
                'tmdb' => $video->tmdb ?? 0,
                'started' => $video->started ?? '',
                'type' => $video->type ?? 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('VideoObserver: Failed to sync TV show to search index', [
                'video_id' => $video->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
