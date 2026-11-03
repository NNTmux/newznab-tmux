<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Binaries\BinariesConfig;
use App\Services\Binaries\CbpOrphanPruner;
use App\Services\Configuration\ConfigurationProvider;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CollectionCleanupService
{
    /**
     * Maximum number of retries for a lock-related DB error before giving up.
     */
    private const LOCK_RETRY_MAX = 5;

    /**
     * Cache key holding the last collection id checked by the orphan sweep.
     */
    public const ORPHAN_CURSOR_CACHE_KEY = 'cbp:orphan_collections_cursor';

    /**
     * SQLSTATE returned by InnoDB on deadlock (1213).
     */
    private const SQLSTATE_DEADLOCK = '40001';

    /**
     * MySQL/MariaDB driver error codes we treat as transient lock contention
     * and therefore safe to retry: 1213 = deadlock, 1205 = lock wait timeout.
     *
     * @var int[]
     */
    private const LOCK_DRIVER_CODES = [1213, 1205];

    private ?bool $cascadeDeleteReady = null;

    private ?bool $hasCollectionGroupsTable = null;

    private readonly BinariesConfig $binariesConfig;

    public function __construct(?BinariesConfig $binariesConfig = null)
    {
        $this->binariesConfig = $binariesConfig ?? BinariesConfig::fromSettings();
    }

    /**
     * Restart the collection delay after processing was down, so a hung run does not
     * turn half-received posts into releases the moment it starts again.
     *
     * Collections idle past part retention are skipped: retention deletes them by
     * last_seen_at whatever their dateadded says. Rows are updated in id batches,
     * so a large backlog never becomes one huge transaction.
     *
     * @return int collections whose delay was restarted
     */
    public function restartCollectionDelay(): int
    {
        $ingestion = app(ConfigurationProvider::class)->ingestion();
        $delayCutoff = now()->subHours($ingestion->collectionDelayHours);
        $idleCutoff = now()->subHours($ingestion->partRetentionHours);
        $passes = Schema::hasColumn('collections', 'last_seen_at')
            ? [
                static fn ($query) => $query->where('last_seen_at', '>=', $idleCutoff),
                static fn ($query) => $query->whereNull('last_seen_at'),
            ]
            : [static fn ($query) => $query];

        $restarted = 0;
        foreach ($passes as $filter) {
            $lastId = 0;
            do {
                $ids = DB::table('collections')
                    ->where($filter)
                    ->where('dateadded', '<', $delayCutoff)
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->limit($this->sqlChunkSize())
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all();
                if ($ids === []) {
                    break;
                }

                $lastId = $ids[array_key_last($ids)];
                $restarted += DB::table('collections')
                    ->whereIn('id', $ids)
                    ->where('dateadded', '<', $delayCutoff)
                    ->update(['dateadded' => now()]);
            } while (count($ids) === $this->sqlChunkSize());
        }

        return $restarted;
    }

    /**
     * Deletes idle collections and, on the global run, orphans and collections
     * missed after NZB creation.
     *
     * Per-group workers ($groupId set) only run retention for their own group;
     * the table-wide scans run once, from the global pass ($groupId null),
     * instead of being repeated by every parallel worker.
     *
     * @return int total deleted rows across operations (approximate)
     */
    public function deleteFinishedAndOrphans(bool $echoCLI, ?int $groupId = null): int
    {
        $startTime = now()->toImmutable();
        $deletedCount = 0;
        $ingestion = app(ConfigurationProvider::class)->ingestion();

        if ($echoCLI) {
            echo cli()->header('Process Releases -> Delete finished collections.'.PHP_EOL).
                cli()->primary(sprintf(
                    'Deleting collections/binaries/parts idle for %d hours.',
                    $ingestion->partRetentionHours
                ), true);
        }

        // Retention keys on last activity, not first sight: dateadded is set
        // once, so large posts still uploading past partRetentionHours used to
        // be deleted mid-ingest. The hard cutoff bounds collections that keep
        // trickling in forever.
        $idleCutoff = now()->subHours($ingestion->partRetentionHours);
        $hardCutoff = now()->subHours(max($ingestion->partRetentionHours, $ingestion->collectionTimeoutHours));
        $batchDeleted = 0;
        do {
            $ids = DB::table('collections')
                ->when($groupId !== null, fn ($query) => $query->where('groups_id', $groupId))
                ->where(fn ($query) => $query
                    ->where('last_seen_at', '<', $idleCutoff)
                    ->orWhere(fn ($idle) => $idle
                        ->whereNull('last_seen_at')
                        ->where(fn ($added) => $added->where('dateadded', '<', $idleCutoff)->orWhereNull('dateadded')))
                    ->orWhere('dateadded', '<', $hardCutoff))
                ->orderBy('id')
                ->limit($this->sqlChunkSize())
                ->pluck('id')
                ->all();

            if ($ids === []) {
                break;
            }

            $affected = $this->deleteCollectionsAndDescendants($ids, 'Cleanup', $echoCLI);

            $batchDeleted += $affected;
            if ($affected < $this->sqlChunkSize()) {
                break;
            }
            // Brief pause to reduce pressure on the lock manager in busy systems.
            usleep(10000);
        } while (true);

        $deletedCount += $batchDeleted;

        if ($echoCLI) {
            $elapsed = now()->diffInSeconds($startTime, true);
            cli()->primary(
                'Finished deleting '.$batchDeleted.' old collections/binaries/parts in '.
                $elapsed.Str::plural(' second', (int) $elapsed),
                true
            );
        }

        if ($groupId !== null) {
            return $deletedCount;
        }

        // Prune orphaned collections (no binaries) every run, but bounded so a large
        // backlog cannot stall the cycle or exhaust memory. Subsequent runs will keep
        // chipping away until the backlog is gone.
        if ($echoCLI) {
            echo cli()->header('Process Releases -> Remove CBP orphans.'.PHP_EOL).
                cli()->primary('Deleting orphaned collections.', true);
        }

        $orphanDeleted = $this->deleteOrphanCollections($echoCLI);
        $deletedCount += $orphanDeleted;

        if ($echoCLI) {
            $totalTime = now()->diffInSeconds($startTime, true);
            cli()->primary(
                'Finished deleting '.$orphanDeleted.' orphaned collections in '.
                $totalTime.Str::plural(' second', (int) $totalTime),
                true
            );
        }

        // Collections whose release has already been NZB'd are dead weight; drop them
        // in bounded batches via a non-locking SELECT-then-single-table-DELETE so we
        // never materialise the full id set in PHP, never issue per-row DELETEs, and
        // never form a cross-table lock cycle with NzbService::writeNzbForReleaseId().
        if ($echoCLI) {
            cli()->primary('Deleting collections that were missed after NZB creation.', true);
        }

        $missedDeleted = $this->deleteCollectionsMissedAfterNzb($echoCLI);
        $deletedCount += $missedDeleted;

        // Descendant rows left behind when cascades were missing; a cached
        // cursor spreads the walk over many runs.
        $orphanRows = app(CbpOrphanPruner::class)->sweepIncrementally();
        if ($echoCLI && array_sum($orphanRows) > 0) {
            cli()->primary(sprintf(
                'Removed orphan rows: %d collection_groups, %d binaries, %d parts.',
                $orphanRows['collection_groups'] ?? 0,
                $orphanRows['binaries'] ?? 0,
                $orphanRows['parts'] ?? 0
            ), true);
        }

        $totalTime = now()->diffInSeconds($startTime, true);

        if ($echoCLI) {
            cli()->primary(
                'Finished deleting '.$missedDeleted.' collections missed after NZB creation in '.($totalTime).Str::plural(' second', (int) $totalTime).
                PHP_EOL.'Removed '.number_format($deletedCount).' collections (with related binaries/parts) in '.$totalTime.Str::plural(' second', (int) $totalTime),
                true
            );
        }

        return $deletedCount;
    }

    /**
     * Delete collections that have no binaries (CBP orphans), one id window per run.
     *
     * An anti-join over the whole table costs a full pass over `binaries`
     * even when nothing is orphaned, so each run checks only the next
     * `nntmux.cbp.orphan_collection_window` ids from a cached cursor and
     * wraps to the start once it passes the newest collection.
     *
     * Uses the same two-phase pattern as deleteCollectionsMissedAfterNzb():
     * a plain NOT EXISTS SELECT against `binaries` (no row locks) followed
     * by a single-table DELETE FROM collections WHERE id IN (...). This
     * avoids cross-table lock acquisition between `collections` and
     * `binaries`, which can deadlock against concurrent BinaryHandler writes.
     */
    private function deleteOrphanCollections(bool $echoCLI): int
    {
        $maxId = DB::table('collections')->max('id');
        if ($maxId === null) {
            Cache::forget(self::ORPHAN_CURSOR_CACHE_KEY);

            return 0;
        }
        $maxId = (int) $maxId;

        $windowStart = (int) Cache::get(self::ORPHAN_CURSOR_CACHE_KEY, 0);
        if ($windowStart >= $maxId) {
            $windowStart = 0;
        }
        $minId = (int) DB::table('collections')->where('id', '>', $windowStart)->min('id');
        $windowStart = max($windowStart, $minId - 1);
        $windowEnd = $windowStart + $this->orphanCollectionWindow();

        $deleted = 0;
        $maxBatches = 20; // hard cap per cycle; bounded backlog drain
        $batchSize = $this->sqlChunkSize();
        $lastId = $windowStart;
        $windowFinished = false;

        for ($i = 0; $i < $maxBatches; $i++) {
            $ids = DB::table('collections as c')
                ->where('c.id', '>', $lastId)
                ->where('c.id', '<=', $windowEnd)
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('binaries as b')
                    ->whereColumn('b.collections_id', 'c.id'))
                ->orderBy('c.id')
                ->limit($batchSize)
                ->pluck('c.id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            if ($ids === []) {
                $windowFinished = true;
                break;
            }

            $lastId = (int) end($ids);
            $deleted += $this->deleteCollectionsAndDescendants($ids, 'Orphan cleanup', $echoCLI);

            if (\count($ids) < $batchSize) {
                $windowFinished = true;
                break;
            }
            usleep(10000);
        }

        $cursor = $windowFinished ? $windowEnd : $lastId;
        if ($cursor >= $maxId) {
            Cache::forget(self::ORPHAN_CURSOR_CACHE_KEY);
        } else {
            Cache::forever(self::ORPHAN_CURSOR_CACHE_KEY, $cursor);
        }

        return $deleted;
    }

    private function orphanCollectionWindow(): int
    {
        return max(1000, (int) config('nntmux.cbp.orphan_collection_window', 100000));
    }

    /**
     * Delete collections whose release was already turned into an NZB
     * (releases.nzbstatus = 1). Batched in two phases per iteration:
     *
     *   1. Non-locking SELECT (autocommit MVCC snapshot) to gather a small
     *      list of `collections.id` values whose joined release row has
     *      nzbstatus = 1. No row locks are taken on `releases`.
     *   2. Single-table DELETE FROM collections WHERE id IN (...). The DELETE
     *      never references `releases`, so the lock graph reduces to one
     *      table and concurrent NzbService transactions (which lock
     *      releases -> collections) cannot form a cross-table cycle.
     *
     * This intentionally replaces the previous single DELETE-with-JOIN
     * subselect, which caused recurring `1213 Deadlock found` errors when
     * multiple `multiprocessing:releases` workers ran in parallel against
     * `NzbService::writeNzbForReleaseId()` on the same DB.
     */
    private function deleteCollectionsMissedAfterNzb(bool $echoCLI): int
    {
        $deleted = 0;
        $maxBatches = 20;
        $batchSize = $this->sqlChunkSize();

        for ($i = 0; $i < $maxBatches; $i++) {
            // The releases_id filter lets the scan start from ix_collection_releaseid;
            // without it MariaDB walks every collection in id order.
            $ids = DB::table('collections as c')
                ->join('releases as r', 'r.id', '=', 'c.releases_id')
                ->whereNotNull('c.releases_id')
                ->where('r.nzbstatus', '=', 1)
                ->orderBy('c.id')
                ->limit($batchSize)
                ->pluck('c.id')
                ->all();

            if ($ids === []) {
                break;
            }

            $affected = $this->deleteCollectionsAndDescendants($ids, 'Missed-NZB cleanup', $echoCLI);

            $deleted += $affected;
            if ($affected < $batchSize) {
                break;
            }
            usleep(10000);
        }

        return $deleted;
    }

    /**
     * Explicitly delete parts, binaries, then collections for the given IDs.
     * This path does not rely on DB-level cascade constraints.
     *
     * @param  list<int>  $collectionIds
     */
    public function deleteCollectionsAndDescendants(
        array $collectionIds,
        string $label = 'CBP cleanup',
        bool $echoCLI = false,
    ): int {
        if ($collectionIds === []) {
            return 0;
        }

        $deletedCollections = 0;

        foreach (array_chunk($collectionIds, $this->sqlChunkSize()) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $deletedCollections += $this->retryOnLockError(
                fn (): int => DB::transaction(
                    function () use ($chunk, $placeholders): int {
                        if ($this->cascadeDeleteReady()) {
                            return (int) DB::affectingStatement(
                                "DELETE FROM collections WHERE id IN ({$placeholders})",
                                $chunk
                            );
                        }

                        DB::statement(
                            "DELETE FROM parts WHERE binaries_id IN (SELECT id FROM binaries WHERE collections_id IN ({$placeholders}))",
                            $chunk
                        );
                        DB::statement(
                            "DELETE FROM binaries WHERE collections_id IN ({$placeholders})",
                            $chunk
                        );
                        if ($this->hasCollectionGroupsTable()) {
                            // Without the cascade FK these rows outlive their
                            // collection and get attached to whichever future
                            // collection reuses the id.
                            DB::statement(
                                "DELETE FROM collection_groups WHERE collections_id IN ({$placeholders})",
                                $chunk
                            );
                        }

                        return (int) DB::affectingStatement(
                            "DELETE FROM collections WHERE id IN ({$placeholders})",
                            $chunk
                        );
                    }
                ),
                $label,
                $echoCLI,
            );
        }

        return $deletedCollections;
    }

    private function hasCollectionGroupsTable(): bool
    {
        return $this->hasCollectionGroupsTable ??= Schema::hasTable('collection_groups');
    }

    private function sqlChunkSize(): int
    {
        return $this->binariesConfig->sqlChunkSize;
    }

    /**
     * Production deletes can safely target only the parent table when both
     * descendant foreign keys cascade. SQLite fixtures and incomplete legacy
     * schemas deliberately retain the explicit descendant-delete fallback.
     */
    private function cascadeDeleteReady(): bool
    {
        if ($this->cascadeDeleteReady !== null) {
            return $this->cascadeDeleteReady;
        }
        if (DB::getDriverName() === 'sqlite') {
            return $this->cascadeDeleteReady = false;
        }

        try {
            $rows = DB::select(
                "SELECT TABLE_NAME, DELETE_RULE
                 FROM information_schema.REFERENTIAL_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE()
                   AND TABLE_NAME IN ('binaries', 'parts', 'collection_groups')"
            );
            $cascades = [];
            foreach ($rows as $row) {
                if (strtoupper((string) $row->DELETE_RULE) === 'CASCADE') {
                    $cascades[(string) $row->TABLE_NAME] = true;
                }
            }

            return $this->cascadeDeleteReady = isset($cascades['binaries'], $cascades['parts'])
                && (isset($cascades['collection_groups']) || ! $this->hasCollectionGroupsTable());
        } catch (\Throwable) {
            return $this->cascadeDeleteReady = false;
        }
    }

    /**
     * Run a DB write inside a bounded retry loop that only swallows transient
     * InnoDB lock errors (deadlock 1213, lock wait timeout 1205). Any other
     * exception is re-thrown so real failures (constraint violations, schema
     * issues, connection drops, etc.) are not silently retried.
     *
     * Backoff is `min(500ms, 20ms * attempt) + 0..25ms jitter` so concurrent
     * cleanup workers stop colliding on the exact same retry cadence.
     *
     * @param  callable():int  $op  Returns the number of rows affected by the write.
     * @param  string  $label  Human-readable label used in the CLI error message.
     * @param  bool  $echoCLI  Whether to echo a final error after exhausting retries.
     * @return int Rows affected on success, or 0 if all retries exhausted.
     */
    private function retryOnLockError(callable $op, string $label, bool $echoCLI): int
    {
        $attempt = 0;

        while (true) {
            try {
                return (int) $op();
            } catch (\Throwable $e) {
                if (! $this->isLockError($e)) {
                    throw $e;
                }

                $attempt++;
                if ($attempt >= self::LOCK_RETRY_MAX) {
                    if ($echoCLI) {
                        cli()->error($label.' delete failed after retries: '.$e->getMessage());
                    }

                    return 0;
                }

                $sleepMs = min(500, 20 * $attempt) + random_int(0, 25);
                usleep($sleepMs * 1000);
            }
        }
    }

    /**
     * Determine whether the given throwable represents a transient InnoDB
     * lock error (deadlock or lock wait timeout) that is safe to retry.
     */
    private function isLockError(\Throwable $e): bool
    {
        if ($e instanceof QueryException) {
            $sqlState = (string) $e->getCode();
            $driverCode = (int) ($e->errorInfo[1] ?? 0);

            if ($sqlState === self::SQLSTATE_DEADLOCK) {
                return true;
            }

            if (in_array($driverCode, self::LOCK_DRIVER_CODES, true)) {
                return true;
            }
        }

        // Some drivers surface PDOException directly; fall back to the message.
        $message = $e->getMessage();
        if (str_contains($message, 'Deadlock found')) {
            return true;
        }

        if (str_contains($message, 'Lock wait timeout exceeded')) {
            return true;
        }

        return false;
    }
}
