<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\SecondarySearchIndex;
use App\Facades\Search;
use App\Models\ConsoleInfo;
use App\Support\SecondaryIndexDocuments;
use Illuminate\Support\Facades\Log;

class ConsoleInfoObserver
{
    public function created(ConsoleInfo $consoleInfo): void
    {
        $this->sync($consoleInfo);
    }

    public function updated(ConsoleInfo $consoleInfo): void
    {
        $this->sync($consoleInfo);
    }

    public function deleted(ConsoleInfo $consoleInfo): void
    {
        try {
            Search::deleteSecondary(SecondarySearchIndex::Console, $consoleInfo->id);
        } catch (\Throwable $e) {
            Log::error('ConsoleInfoObserver: delete from search index failed', [
                'id' => $consoleInfo->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sync(ConsoleInfo $consoleInfo): void
    {
        try {
            Search::insertSecondary(
                SecondarySearchIndex::Console,
                $consoleInfo->id,
                SecondaryIndexDocuments::console($consoleInfo)
            );
        } catch (\Throwable $e) {
            Log::error('ConsoleInfoObserver: sync to search index failed', [
                'id' => $consoleInfo->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
