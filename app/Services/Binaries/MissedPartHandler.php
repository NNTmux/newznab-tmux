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
     *
     * @param  array<string, mixed>  $numbers
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
     * @param  array<string, mixed>  $numbers
     */
    private function addMissingPartsSqlite(array $numbers, int $groupId): void
    {
        foreach (array_chunk(array_unique($numbers), 1000) as $chunk) {
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
     * @param  array<string, mixed>  $numbers
     */
    private function addMissingPartsMysql(array $numbers, int $groupId): void
    {
        foreach (array_chunk(array_unique($numbers), 1000) as $chunk) {
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
     * @param  array<string, mixed>  $numbers
     */
    public function removeRepairedParts(array $numbers, int $groupId): void
    {
        if (empty($numbers)) {
            return;
        }

        try {
            DB::transaction(static function () use ($groupId, $numbers): void {
                DB::table('missed_parts')
                    ->where('groups_id', $groupId)
                    ->whereIn('numberid', $numbers)
                    ->delete();
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
     * @return array<string, mixed> Array of missed parts
     */
    public function getMissingParts(int $groupId): array
    {
        try {
            return DB::table('missed_parts')
                ->where('groups_id', $groupId)
                ->where('attempts', '<', $this->partRepairMaxTries)
                ->orderBy('numberid')
                ->limit($this->partRepairLimit)
                ->get()
                ->all();
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
        DB::table('missed_parts')
            ->where('groups_id', $groupId)
            ->where('numberid', '<=', $maxNumberId)
            ->increment('attempts');
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
        return DB::table('missed_parts')
            ->where('groups_id', $groupId)
            ->where('numberid', '<=', $maxNumberId)
            ->count('id');
    }

    /**
     * Remove parts that exceeded max tries.
     */
    public function cleanupExhaustedParts(int $groupId): void
    {
        DB::transaction(function () use ($groupId): void {
            DB::table('missed_parts')
                ->where('groups_id', $groupId)
                ->where('attempts', '>=', $this->partRepairMaxTries)
                ->delete();
        }, 10);
    }
}
