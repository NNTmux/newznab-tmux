<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Release;
use App\Services\Nzb\NzbService;
use App\Services\ReleaseImageService;
use App\Support\ReleaseSearchIndexSync;
use Illuminate\Support\Facades\Log;

/**
 * Observer for Release model to keep search indexes in sync.
 *
 * Records transactional search-index outbox events for release changes.
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
        ReleaseSearchIndexSync::forIds([(int) $release->id]);
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
            'guid',
            'searchname',
            'fromname',
            'categories_id',
            'imdbid',
            'anidbid',
            'movieinfo_id',
            'videos_id',
            'tv_episodes_id',
            'size',
            'totalpart',
            'grabs',
            'passwordstatus',
            'groups_id',
            'nzbstatus',
            'nfostatus',
            'haspreview',
            'jpgstatus',
            'comments',
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
            ReleaseSearchIndexSync::forIds([(int) $release->id]);
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
        ReleaseSearchIndexSync::deleteIds([(int) $release->id]);
    }
}
