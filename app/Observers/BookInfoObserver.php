<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\SecondarySearchIndex;
use App\Facades\Search;
use App\Models\BookInfo;
use App\Support\SecondaryIndexDocuments;
use Illuminate\Support\Facades\Log;

class BookInfoObserver
{
    public function created(BookInfo $bookInfo): void
    {
        $this->sync($bookInfo);
    }

    public function updated(BookInfo $bookInfo): void
    {
        $this->sync($bookInfo);
    }

    public function deleted(BookInfo $bookInfo): void
    {
        try {
            Search::deleteSecondary(SecondarySearchIndex::Books, $bookInfo->id);
        } catch (\Throwable $e) {
            Log::error('BookInfoObserver: delete from search index failed', [
                'id' => $bookInfo->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sync(BookInfo $bookInfo): void
    {
        try {
            Search::insertSecondary(
                SecondarySearchIndex::Books,
                $bookInfo->id,
                SecondaryIndexDocuments::book($bookInfo)
            );
        } catch (\Throwable $e) {
            Log::error('BookInfoObserver: sync to search index failed', [
                'id' => $bookInfo->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
