<?php

namespace App\Observers;

use App\Facades\Search;
use App\Models\MovieInfo;
use App\Models\Release;
use App\Models\Video;
use Illuminate\Support\Facades\Log;

/**
 * Observer for Release model to keep search indexes in sync.
 *
 * This observer ensures that when releases are updated with movie/TV information
 * during post-processing, the search index is updated with the external IDs
 * (imdbid, tmdbid, tvdb, etc.) for efficient searching.
 */
class ReleaseObserver
{
    /**
     * Handle the Release "created" event.
     *
     * When a release is created (e.g., during NZB import), add it to the search index.
     */
    public function created(Release $release): void
    {
        $this->syncToSearchIndex($release);
    }

    /**
     * Handle the Release "updated" event.
     *
     * When a release is updated (e.g., during post-processing with movie/TV data),
     * update the search index with the new external IDs.
     */
    public function updated(Release $release): void
    {
        // Check if any of the external ID fields were changed
        $externalIdFields = [
            'imdbid',
            'movieinfo_id',
            'videos_id',
            'tv_episodes_id',
            'anidbid',
            'searchname',
            'name',
            'fromname',
            'categories_id',
        ];

        $changed = false;
        foreach ($externalIdFields as $field) {
            if ($release->isDirty($field)) {
                $changed = true;
                break;
            }
        }

        if ($changed) {
            $this->syncToSearchIndex($release);
        }
    }

    /**
     * Handle the Release "deleted" event.
     */
    public function deleted(Release $release): void
    {
        try {
            Search::deleteRelease($release->id);
        } catch (\Throwable $e) {
            Log::error('ReleaseObserver: Failed to delete release from search index', [
                'release_id' => $release->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sync the release to the search index with all external IDs.
     */
    private function syncToSearchIndex(Release $release): void
    {
        try {
            // Load related models if needed
            $movieInfo = null;
            $video = null;

            if ($release->movieinfo_id > 0) {
                $movieInfo = MovieInfo::find($release->movieinfo_id);
            }

            if ($release->videos_id > 0) {
                $video = Video::find($release->videos_id);
            }

            $parameters = [
                'id' => $release->id,
                'name' => $release->name ?? '',
                'searchname' => $release->searchname ?? '',
                'fromname' => $release->fromname ?? '',
                'categories_id' => $release->categories_id ?? 0,
                'filename' => '', // Not available from model
                // Movie external IDs
                'imdbid' => $release->imdbid ?? ($movieInfo?->imdbid ?? 0),
                'tmdbid' => $movieInfo?->tmdbid ?? 0,
                'traktid' => $movieInfo?->traktid ?? 0,
                // TV show external IDs
                'tvdb' => $video?->tvdb ?? 0,
                'tvmaze' => $video?->tvmaze ?? 0,
                'tvrage' => $video?->tvrage ?? 0,
                'videos_id' => $release->videos_id ?? 0,
                'movieinfo_id' => $release->movieinfo_id ?? 0,
            ];

            Search::insertRelease($parameters);

            if (config('app.debug')) {
                Log::debug('ReleaseObserver: Updated search index for release', [
                    'release_id' => $release->id,
                    'imdbid' => $parameters['imdbid'],
                    'videos_id' => $parameters['videos_id'],
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('ReleaseObserver: Failed to sync release to search index', [
                'release_id' => $release->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
