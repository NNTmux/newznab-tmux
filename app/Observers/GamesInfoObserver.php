<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\SecondarySearchIndex;
use App\Facades\Search;
use App\Models\GamesInfo;
use App\Support\SecondaryIndexDocuments;
use Illuminate\Support\Facades\Log;

class GamesInfoObserver
{
    public function created(GamesInfo $gamesInfo): void
    {
        $this->sync($gamesInfo);
    }

    public function updated(GamesInfo $gamesInfo): void
    {
        $this->sync($gamesInfo);
    }

    public function deleted(GamesInfo $gamesInfo): void
    {
        try {
            Search::deleteSecondary(SecondarySearchIndex::Games, $gamesInfo->id);
        } catch (\Throwable $e) {
            Log::error('GamesInfoObserver: delete from search index failed', [
                'id' => $gamesInfo->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sync(GamesInfo $gamesInfo): void
    {
        try {
            Search::insertSecondary(
                SecondarySearchIndex::Games,
                $gamesInfo->id,
                SecondaryIndexDocuments::games($gamesInfo)
            );
        } catch (\Throwable $e) {
            Log::error('GamesInfoObserver: sync to search index failed', [
                'id' => $gamesInfo->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
