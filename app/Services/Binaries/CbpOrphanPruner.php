<?php

declare(strict_types=1);

namespace App\Services\Binaries;

use App\Support\SqlError;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes collection_groups, binaries and parts rows whose parent row is gone.
 *
 * Production ran without the cascade foreign keys (cbp:optimize-storage never
 * reached its constraints step), so cleanup left descendants behind. Stale
 * collection_groups rows are the dangerous ones: their collections_id values
 * get reused by new collections, which then inherit the wrong group names.
 *
 * Rows are walked by key range in bounded batches, so each DELETE is a short
 * transaction. Rows inserted after a sweep starts lie above its ceiling and
 * are never examined by that sweep.
 */
final class CbpOrphanPruner
{
    /**
     * Child table => [range column, foreign key column, parent table].
     * binaries is swept before parts so a single pass also clears the parts
     * of binaries it just removed.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    public const array TARGETS = [
        'collection_groups' => ['collections_id', 'collections_id', 'collections'],
        'binaries' => ['id', 'collections_id', 'collections'],
        'parts' => ['binaries_id', 'binaries_id', 'binaries'],
    ];

    private const int LOCK_RETRY_MAX = 5;

    private const string CURSOR_CACHE_PREFIX = 'cbp:orphan_sweep_cursor:';

    /** @var array<string, bool> */
    private array $tableExists = [];

    /**
     * Sweep one table from $startAfter, optionally stopping after $maxBatches.
     *
     * @param  (callable(int $deleted, int $cursor): void)|null  $onBatch
     * @return array{deleted: int, cursor: int|null} cursor is null once the table is fully swept
     */
    public function sweep(
        string $table,
        int $batchSize = 50000,
        ?int $maxBatches = null,
        int $startAfter = 0,
        ?callable $onBatch = null,
    ): array {
        [$rangeColumn, $foreignColumn, $parentTable] = $this->target($table);
        if (! $this->tableExists($table) || ! $this->tableExists($parentTable)) {
            return ['deleted' => 0, 'cursor' => null];
        }

        $batchSize = max(1, $batchSize);
        // Table and column names come from self::TARGETS, never from input.
        $ceiling = DB::scalar("SELECT MAX({$rangeColumn}) FROM {$table}");
        if ($ceiling === null) {
            return ['deleted' => 0, 'cursor' => null];
        }
        $ceiling = (int) $ceiling;

        $deleted = 0;
        $cursor = $startAfter;
        $batches = 0;
        while ($cursor < $ceiling) {
            if ($maxBatches !== null && $batches >= $maxBatches) {
                return ['deleted' => $deleted, 'cursor' => $cursor];
            }

            $upper = DB::scalar(
                "SELECT {$rangeColumn} FROM {$table} WHERE {$rangeColumn} > ? AND {$rangeColumn} <= ?
                 ORDER BY {$rangeColumn} LIMIT 1 OFFSET ".($batchSize - 1),
                [$cursor, $ceiling]
            );
            $upper = $upper === null ? $ceiling : (int) $upper;

            $affected = $this->deleteOrphansInRange($table, $rangeColumn, $foreignColumn, $parentTable, $cursor, $upper);
            $deleted += $affected;
            $cursor = $upper;
            $batches++;

            if ($onBatch !== null) {
                $onBatch($affected, $cursor);
            }
        }

        return ['deleted' => $deleted, 'cursor' => null];
    }

    /**
     * Advance a cached cursor through every table a few batches at a time, so
     * the periodic release run never rescans from the start of a table.
     *
     * @return array<string, int> deleted rows per table
     */
    public function sweepIncrementally(int $batchSize = 5000, int $maxBatchesPerTable = 2): array
    {
        $deleted = [];
        foreach (array_keys(self::TARGETS) as $table) {
            $cacheKey = self::CURSOR_CACHE_PREFIX.$table;
            $result = $this->sweep($table, $batchSize, $maxBatchesPerTable, (int) Cache::get($cacheKey, 0));
            $deleted[$table] = $result['deleted'];

            if ($result['cursor'] === null) {
                Cache::forget($cacheKey);
            } else {
                Cache::forever($cacheKey, $result['cursor']);
            }
        }

        return $deleted;
    }

    /**
     * Replace collection_groups with only the rows that still have a
     * collection. Much faster than deleting tens of millions of stale rows,
     * but rows written by ingest during the copy would be lost, so the caller
     * must ensure processing is stopped. MySQL/MariaDB only.
     *
     * @return array{kept: int, removed: int}
     */
    public function rebuildCollectionGroups(): array
    {
        $before = (int) DB::scalar('SELECT COUNT(*) FROM collection_groups');
        $hadForeignKey = $this->foreignKeyExists('collection_groups', 'fk_collection_groups_collection');

        DB::statement('DROP TABLE IF EXISTS collection_groups_rebuild');
        DB::statement('DROP TABLE IF EXISTS collection_groups_stale');
        DB::statement('CREATE TABLE collection_groups_rebuild LIKE collection_groups');
        DB::statement(
            'INSERT INTO collection_groups_rebuild (collections_id, group_name)
             SELECT cg.collections_id, cg.group_name
             FROM collection_groups cg INNER JOIN collections c ON c.id = cg.collections_id'
        );
        DB::statement('RENAME TABLE collection_groups TO collection_groups_stale, collection_groups_rebuild TO collection_groups');
        DB::statement('DROP TABLE collection_groups_stale');

        if ($hadForeignKey) {
            DB::statement(
                'ALTER TABLE collection_groups ADD CONSTRAINT fk_collection_groups_collection
                 FOREIGN KEY (collections_id) REFERENCES collections(id) ON DELETE CASCADE ON UPDATE CASCADE'
            );
        }

        // Refresh statistics so the optimizer sees the rebuilt table's size.
        DB::statement('ANALYZE TABLE collection_groups');

        $kept = (int) DB::scalar('SELECT COUNT(*) FROM collection_groups');

        return ['kept' => $kept, 'removed' => max(0, $before - $kept)];
    }

    /**
     * Count collection_groups rows whose collection is gone, stopping at $limit
     * so a heavily polluted table is not scanned to the end.
     */
    public function countStaleCollectionGroups(int $limit): int
    {
        return (int) DB::scalar(
            'SELECT COUNT(*) FROM (
                SELECT 1 FROM collection_groups cg
                WHERE NOT EXISTS (SELECT 1 FROM collections c WHERE c.id = cg.collections_id)
                LIMIT ?
            ) stale',
            [max(0, $limit)]
        );
    }

    /**
     * One schema probe per table for the life of this instance: a sweep over
     * all targets asks about `collections` and `binaries` more than once.
     */
    private function tableExists(string $table): bool
    {
        return $this->tableExists[$table] ??= Schema::hasTable($table);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function target(string $table): array
    {
        if (! isset(self::TARGETS[$table])) {
            throw new \InvalidArgumentException("Unsupported orphan sweep table: {$table}");
        }

        return self::TARGETS[$table];
    }

    private function deleteOrphansInRange(
        string $table,
        string $rangeColumn,
        string $foreignColumn,
        string $parentTable,
        int $after,
        int $upTo,
    ): int {
        $sql = "DELETE FROM {$table} WHERE {$rangeColumn} > ? AND {$rangeColumn} <= ?
                AND NOT EXISTS (SELECT 1 FROM {$parentTable} parent WHERE parent.id = {$table}.{$foreignColumn})";

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::affectingStatement($sql, [$after, $upTo]);
            } catch (\Throwable $e) {
                if ($attempt >= self::LOCK_RETRY_MAX || ! SqlError::isTransientLock($e)) {
                    throw $e;
                }
                usleep((min(500, 50 * $attempt) + random_int(0, 25)) * 1000);
            }
        }
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        return DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$table, $constraint]
        ) !== [];
    }
}
