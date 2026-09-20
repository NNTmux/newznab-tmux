<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Jobs\FlushSearchIndexOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class SearchIndexOutbox
{
    /**
     * @param  iterable<int|string>  $releaseIds
     */
    public function upsertReleases(iterable $releaseIds): void
    {
        $this->recordReleaseActions($releaseIds, 'upsert');
    }

    public function upsertRelease(int $releaseId): void
    {
        $this->upsertReleases([$releaseId]);
    }

    /**
     * @param  iterable<int|string>  $releaseIds
     */
    public function deleteReleases(iterable $releaseIds): void
    {
        $this->recordReleaseActions($releaseIds, 'delete');
    }

    public function deleteRelease(int $releaseId): void
    {
        $this->deleteReleases([$releaseId]);
    }

    /**
     * @param  iterable<int|string>  $releaseIds
     */
    private function recordReleaseActions(iterable $releaseIds, string $action): void
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', is_array($releaseIds) ? $releaseIds : iterator_to_array($releaseIds)),
            static fn (int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            return;
        }

        $createdAt = now();
        DB::table('search_index_outbox')->insert(array_map(
            static fn (int $releaseId): array => [
                'entity_type' => 'release',
                'entity_id' => $releaseId,
                'action' => $action,
                'payload' => null,
                'created_at' => $createdAt,
            ],
            $ids,
        ));

        DB::afterCommit(function (): void {
            try {
                FlushSearchIndexOutbox::dispatch()
                    ->delay(now()->addSeconds(max(0, (int) config('background-work.search_outbox.debounce_seconds', 2))));
            } catch (\Throwable $exception) {
                Log::warning('Search outbox dispatch failed; the scheduled sweeper will retry it.', [
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }
}
