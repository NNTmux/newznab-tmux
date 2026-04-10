<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\SecondarySearchIndex;
use App\Facades\Search;
use App\Models\SteamApp;
use App\Support\SecondaryIndexDocuments;
use Illuminate\Support\Facades\Log;

class SteamAppObserver
{
    public function created(SteamApp $steamApp): void
    {
        $this->sync($steamApp);
    }

    public function updated(SteamApp $steamApp): void
    {
        $this->sync($steamApp);
    }

    public function deleted(SteamApp $steamApp): void
    {
        try {
            $id = (int) ($steamApp->getAttribute('id') ?? 0);
            if ($id > 0) {
                Search::deleteSecondary(SecondarySearchIndex::Steam, $id);
            }
        } catch (\Throwable $e) {
            Log::error('SteamAppObserver: delete from search index failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sync(SteamApp $steamApp): void
    {
        try {
            $id = (int) ($steamApp->getAttribute('id') ?? 0);
            if ($id <= 0) {
                return;
            }
            Search::insertSecondary(
                SecondarySearchIndex::Steam,
                $id,
                SecondaryIndexDocuments::steam($steamApp)
            );
        } catch (\Throwable $e) {
            Log::error('SteamAppObserver: sync to search index failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
