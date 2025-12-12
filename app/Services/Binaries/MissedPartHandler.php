<?php

declare(strict_types=1);

namespace App\Services\Binaries;

use App\Models\MissedPart;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles missed parts tracking and repair during header processing.
 */
final class MissedPartHandler
{
    private int $partRepairLimit;

    private int $partRepairMaxTries;

    public function __construct(int $partRepairLimit = 15000, int $partRepairMaxTries = 3)
    {
        $this->partRepairLimit = $partRepairLimit;
        $this->partRepairMaxTries = $partRepairMaxTries;
    }

    /**
     * Add missing article numbers to the repair queue.
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

    private function addMissingPartsSqlite(array $numbers, int $groupId): void
    {
        foreach ($numbers as $number) {
            DB::statement(
                'INSERT INTO missed_parts (numberid, groups_id, attempts) VALUES (?, ?, 1) ON CONFLICT(numberid, groups_id) DO UPDATE SET attempts = attempts + 1',
                [$number, $groupId]
            );
        }
    }

    private function addMissingPartsMysql(array $numbers, int $groupId): void
    {
        $insertStr = 'INSERT INTO missed_parts (numberid, groups_id) VALUES ';
        foreach ($numbers as $number) {
            $insertStr .= '('.$number.','.$groupId.'),';
        }

        DB::insert(rtrim($insertStr, ',').' ON DUPLICATE KEY UPDATE attempts=attempts+1');
    }

    /**
     * Remove successfully repaired parts from the queue.
     */
    public function removeRepairedParts(array $numbers, int $groupId): void
    {
        if (empty($numbers)) {
            return;
        }

        $sql = 'DELETE FROM missed_parts WHERE numberid in (';
        foreach ($numbers as $number) {
            $sql .= $number.',';
        }

        try {
            DB::transaction(static function () use ($groupId, $sql) {
                DB::delete(rtrim($sql, ',').') AND groups_id = '.$groupId);
            }, 10);
        } catch (\Throwable $e) {
            if (config('app.debug') === true) {
                Log::warning('removeRepairedParts failed: '.$e->getMessage());
            }
        }
    }

    /**
     * Get parts that need repair for a group.
     *
     * @return array Array of missed parts
     */
    public function getMissingParts(int $groupId): array
    {
        try {
            return DB::select(
                sprintf(
                    'SELECT * FROM missed_parts WHERE groups_id = %d AND attempts < %d ORDER BY numberid ASC LIMIT %d',
                    $groupId,
                    $this->partRepairMaxTries,
                    $this->partRepairLimit
                )
            );
        } catch (\PDOException $e) {
            if ($e->getMessage() === 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction') {
                Log::notice('Deadlock occurred while fetching missed parts');
                DB::rollBack();
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
            sprintf(
                'UPDATE missed_parts SET attempts = attempts + 1 WHERE groups_id = %d AND numberid <= %d',
                $groupId,
                $maxNumberId
            )
        );
    }

    /**
     * Increment attempts for specific article range (part repair NNTP failures).
     */
    public function incrementRangeAttempts(int $groupId, int $first, int $last): void
    {
        if ($first === $last) {
            MissedPart::query()
                ->where('groups_id', $groupId)
                ->where('numberid', $first)
                ->increment('attempts');
        } else {
            MissedPart::query()
                ->where('groups_id', $groupId)
                ->whereIn('numberid', range($first, $last))
                ->increment('attempts');
        }
    }

    /**
     * Get count of remaining missed parts.
     */
    public function getCount(int $groupId, int $maxNumberId): int
    {
        $result = DB::select(
            sprintf(
                'SELECT COUNT(id) AS num FROM missed_parts WHERE groups_id = %d AND numberid <= %d',
                $groupId,
                $maxNumberId
            )
        );

        return $result[0]->num ?? 0;
    }

    /**
     * Remove parts that exceeded max tries.
     */
    public function cleanupExhaustedParts(int $groupId): void
    {
        DB::transaction(function () use ($groupId) {
            DB::delete(
                sprintf(
                    'DELETE FROM missed_parts WHERE attempts >= %d AND groups_id = %d',
                    $this->partRepairMaxTries,
                    $groupId
                )
            );
        }, 10);
    }
}

