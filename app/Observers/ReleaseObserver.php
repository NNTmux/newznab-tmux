<?php

declare(strict_types=1);

namespace App\Observers;

use App\Facades\Search;
use App\Models\Release;
use App\Services\Nzb\NzbService;
use App\Services\ReleaseImageService;
use Illuminate\Support\Facades\Log;

/**
 * Observer for Release model to keep search indexes in sync.
 *
 * Delegates document building to Search::updateRelease(), which loads the full
 * row (including movieinfo/videos joins and release_files filenames).
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
        $indexedFields = [
            'name',
            'searchname',
            'fromname',
            'categories_id',
            'imdbid',
            'movieinfo_id',
            'videos_id',
            'size',
            'totalpart',
            'grabs',
            'passwordstatus',
            'groups_id',
            'nzbstatus',
            'haspreview',
            'postdate',
            'adddate',
        ];

        $changed = false;
        foreach ($indexedFields as $field) {
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
     * Handle the Release "deleting" event.
     *
     * When a release is about to be deleted, remove the NZB file and associated images from disk.
     * This runs before the model is deleted so we still have access to the guid.
     */
    public function deleting(Release $release): void
    {
        // Delete NZB file
        try {
            $nzbService = app(NzbService::class);
            $nzbService->deleteNzb($release->guid);
        } catch (\Throwable $e) {
            Log::error('ReleaseObserver: Failed to delete NZB file', [
                'release_id' => $release->id,
                'guid' => $release->guid,
                'error' => $e->getMessage(),
            ]);
        }

        // Delete associated images (previews, thumbnails, video samples)
        try {
            $releaseImageService = app(ReleaseImageService::class);
            $releaseImageService->delete($release->guid);
        } catch (\Throwable $e) {
            Log::error('ReleaseObserver: Failed to delete release images', [
                'release_id' => $release->id,
                'guid' => $release->guid,
                'error' => $e->getMessage(),
            ]);
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
     * Sync the release to the search index (full document from DB + joins).
     */
    private function syncToSearchIndex(Release $release): void
    {
        try {
            Search::updateRelease($release->id);

            if (config('app.debug')) {
                Log::debug('ReleaseObserver: Updated search index for release', [
                    'release_id' => $release->id,
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
