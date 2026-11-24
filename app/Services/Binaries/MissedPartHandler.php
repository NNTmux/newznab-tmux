<?php

declare(strict_types=1);

namespace App\Services\Binaries;

use App\Support\Database\RawSql;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles missed parts tracking and repair during header processing.
 */
final class MissedPartHandler
{
    private int $partRepairLimit;

    private int $partRepairMaxTries;

    private int $chunkSize;

    public function __construct(int $partRepairLimit = 15000, int $partRepairMaxTries = 3, int $chunkSize = 500)
    {
        $this->partRepairLimit = $partRepairLimit;
        $this->partRepairMaxTries = $partRepairMaxTries;
        $this->chunkSize = max(50, min(1000, $chunkSize));
    }

    /**
     * Add missing article numbers to the repair queue.
     *
     * @param  array<int, int|string>  $numbers
     */
    public function addMissingParts(array $numbers, int $groupId): void
    {
        if (empty($numbers)) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->addMissingPartsSqlite($numbers, $groupId);

            return;
        }

        $this->addMissingPartsMysql($numbers, $groupId);
    }

    /**
     * @param  array<int, int|string>  $numbers
     */
    private function addMissingPartsSqlite(array $numbers, int $groupId): void
    {
        foreach (array_chunk(array_unique($numbers), $this->chunkSize) as $chunk) {
            $placeholders = [];
            $bindings = [];

            foreach ($chunk as $number) {
                $placeholders[] = '(?, ?, 1)';
                $bindings[] = $number;
                $bindings[] = $groupId;
            }

            DB::statement(
                'INSERT INTO missed_parts (numberid, groups_id, attempts) VALUES '.implode(',', $placeholders).' ON CONFLICT(numberid, groups_id) DO UPDATE SET attempts = attempts + 1',
                $bindings
            );
        }
    }

    /**
     * @param  array<int, int|string>  $numbers
     */
    private function addMissingPartsMysql(array $numbers, int $groupId): void
    {
        foreach (array_chunk(array_unique($numbers), $this->chunkSize) as $chunk) {
            $placeholders = [];
            $bindings = [];

            foreach ($chunk as $number) {
                $placeholders[] = '(?, ?, 1)';
                $bindings[] = $number;
                $bindings[] = $groupId;
            }

            DB::insert(
                'INSERT INTO missed_parts (numberid, groups_id, attempts) VALUES '.implode(',', $placeholders).' ON DUPLICATE KEY UPDATE attempts = attempts + 1',
                $bindings
            );
        }
    }

    /**
     * Remove successfully repaired parts from the queue.
     *
     * @param  array<int, int|string>  $numbers
     */
    public function removeRepairedParts(array $numbers, int $groupId): void
    {
        if (empty($numbers)) {
            return;
        }

        try {
            // Autocommit per statement: an explicit transaction would only add
            // round-trips. Chunked so a large repair cannot build an unbounded IN list.
            foreach (array_chunk(array_values(array_unique($numbers)), $this->chunkSize) as $chunk) {
                DB::delete(
                    'DELETE FROM missed_parts WHERE groups_id = ? AND numberid IN ('.RawSql::placeholders(\count($chunk)).')',
                    [$groupId, ...$chunk]
                );
            }
        } catch (\Throwable $e) {
            if (config('app.debug') === true) {
                Log::warning('removeRepairedParts failed: '.$e->getMessage());
            }
        }
    }

    /**
     * Get the article numbers that need repair for a group, lowest first.
     *
     * @return list<\stdClass> Rows carrying only `numberid`
     */
    public function getMissingParts(int $groupId): array
    {
        try {
            return DB::select(
                'SELECT numberid FROM missed_parts WHERE groups_id = ? AND attempts < ? ORDER BY numberid LIMIT '.max(1, $this->partRepairLimit),
                [$groupId, $this->partRepairMaxTries]
            );
        } catch (\PDOException $e) {
            if ($e->getMessage() === 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction') {
                Log::notice('Deadlock occurred while fetching missed parts');
            }

            return [];
        }
    }

    /**
     * Increment attempts for parts that weren't repaired.
     */
    public function incrementAttempts(int $groupId, int $maxNumberId): void
    {
        DB::update(
            'UPDATE missed_parts SET attempts = attempts + 1 WHERE groups_id = ? AND numberid <= ?',
            [$groupId, $maxNumberId]
        );
    }

    /**
     * Increment attempts for specific article range (part repair NNTP failures).
     */
    public function incrementRangeAttempts(int $groupId, int $first, int $last): void
    {
        // A range predicate instead of listing every article number in between.
        DB::update(
            'UPDATE missed_parts SET attempts = attempts + 1, updated_at = ? WHERE groups_id = ? AND numberid BETWEEN ? AND ?',
            [now()->format('Y-m-d H:i:s'), $groupId, min($first, $last), max($first, $last)]
        );
    }

    /**
     * Get count of remaining missed parts.
     */
    public function getCount(int $groupId, int $maxNumberId): int
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS remaining FROM missed_parts WHERE groups_id = ? AND numberid <= ?',
            [$groupId, $maxNumberId]
        );

        return (int) ($row->remaining ?? 0);
    }

    /**
     * Remove parts that exceeded max tries.
     */
    public function cleanupExhaustedParts(int $groupId): void
    {
        // Single DELETE — InnoDB autocommits atomically; the explicit
        // transaction wrapper would just add round-trips.
        DB::delete(
            'DELETE FROM missed_parts WHERE groups_id = ? AND attempts >= ?',
            [$groupId, $this->partRepairMaxTries]
        );
    }
}
