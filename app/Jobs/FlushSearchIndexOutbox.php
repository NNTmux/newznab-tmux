<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Search\SearchIndexOutboxDispatcher;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class FlushSearchIndexOutbox implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public int $uniqueFor = 30;

    public function handle(SearchIndexOutboxDispatcher $dispatcher): void
    {
        $dispatcher->dispatchReleaseBatch(
            max(1, min(5000, (int) config('background-work.search_outbox.batch_size', 1000))),
        );

        if (DB::table('search_index_outbox')->where('entity_type', 'release')->exists()) {
            self::dispatch();
        }
    }

    public function uniqueId(): string
    {
        return 'release-search-index-outbox';
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('release-search-index-outbox'))
                ->releaseAfter(1)
                ->expireAfter(60),
        ];
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [1, 5, 15, 30];
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Search index outbox job exhausted its retries.', [
            'error' => $exception?->getMessage(),
            'oldest_created_at' => DB::table('search_index_outbox')
                ->where('entity_type', 'release')
                ->min('created_at'),
        ]);
    }
}
