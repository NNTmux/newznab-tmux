<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\SecondarySearchIndex;
use App\Facades\Search;
use App\Models\MusicInfo;
use App\Support\SecondaryIndexDocuments;
use Illuminate\Support\Facades\Log;

class MusicInfoObserver
{
    public function created(MusicInfo $musicInfo): void
    {
        $this->sync($musicInfo);
    }

    public function updated(MusicInfo $musicInfo): void
    {
        $this->sync($musicInfo);
    }

    public function deleted(MusicInfo $musicInfo): void
    {
        try {
            Search::deleteSecondary(SecondarySearchIndex::Music, $musicInfo->id);
        } catch (\Throwable $e) {
            Log::error('MusicInfoObserver: delete from search index failed', [
                'id' => $musicInfo->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sync(MusicInfo $musicInfo): void
    {
        try {
            Search::insertSecondary(
                SecondarySearchIndex::Music,
                $musicInfo->id,
                SecondaryIndexDocuments::music($musicInfo)
            );
        } catch (\Throwable $e) {
            Log::error('MusicInfoObserver: sync to search index failed', [
                'id' => $musicInfo->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
