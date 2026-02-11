<?php

declare(strict_types=1);

namespace App\Services\Binaries;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles part record creation during header storage.
 */
final class PartHandler
{
    /** @var array<string, mixed> Pending parts to insert */
    private array $parts = [];

    /** @var array<string, mixed> Part numbers successfully inserted */
    private array $insertedPartNumbers = [];

    /** @var array<string, mixed> Part numbers that failed to insert */
    private array $failedPartNumbers = [];

    private int $chunkSize;

    /** @phpstan-ignore property.onlyWritten */
    private bool $addToPartRepair;

    public function __construct(int $chunkSize = 5000, bool $addToPartRepair = true)
    {
        $this->chunkSize = max(100, $chunkSize);
        $this->addToPartRepair = $addToPartRepair;
    }

    /**
     * Reset state for a new batch.
     */
    public function reset(): void
    {
        $this->parts = [];
        $this->insertedPartNumbers = [];
        $this->failedPartNumbers = [];
    }

    /**
     * Set whether to add failed parts to repair queue.
     */
    public function setAddToPartRepair(bool $value): void
    {
        $this->addToPartRepair = $value;
    }

    /**
     * Add a part to the pending insert queue.
     *
     * @param  array<string, mixed>  $header
     * @return bool True if chunk was flushed successfully (or not needed), false on flush failure
     */
    public function addPart(int $binaryId, array $header): bool
    {
        $this->parts[] = [
            'binaries_id' => $binaryId,
            'number' => $header['Number'],
            'messageid' => $header['Message-ID'],
            'partnumber' => $header['matches'][2],
            'size' => $header['Bytes'],
        ];

        // Auto-flush when chunk size reached
        if (\count($this->parts) >= $this->chunkSize) {
            return $this->flush();
        }

        return true;
    }

    /**
     * Flush pending parts to database.
     */
    public function flush(): bool
    {
        if (empty($this->parts)) {
            return true;
        }

        $success = $this->insertChunk($this->parts);

        if ($success) {
            foreach ($this->parts as $part) {
                $this->insertedPartNumbers[] = $part['number'];
            }
        } else {
            foreach ($this->parts as $part) {
                $this->failedPartNumbers[] = $part['number'];
            }
        }

        $this->parts = [];

        return $success;
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function insertChunk(array $parts): bool
    {
        $placeholders = [];
        $bindings = [];
        $driver = DB::getDriverName();

        foreach ($parts as $row) {
            $placeholders[] = '(?,?,?,?,?)';
            $bindings[] = $row['binaries_id'];
            $bindings[] = $row['number'];
            $bindings[] = $row['messageid'];
            $bindings[] = $row['partnumber'];
            $bindings[] = $row['size'];
        }

        $sql = $driver === 'sqlite'
            ? 'INSERT OR IGNORE INTO parts (binaries_id, number, messageid, partnumber, size) VALUES '.implode(',', $placeholders)
            : 'INSERT IGNORE INTO parts (binaries_id, number, messageid, partnumber, size) VALUES '.implode(',', $placeholders);

        try {
            DB::statement($sql, $bindings);

            return true;
        } catch (\Throwable $e) {
            if (config('app.debug') === true) {
                Log::error('Parts chunk insert failed: '.$e->getMessage());
            }

            return false;
        }
    }

    /**
     * Get numbers of successfully inserted parts.
     *
     * @return array<string, mixed>
     */
    public function getInsertedNumbers(): array
    {
        return $this->insertedPartNumbers;
    }

    /**
     * Get numbers of failed part inserts.
     *
     * @return array<string, mixed>
     */
    public function getFailedNumbers(): array
    {
        return $this->failedPartNumbers;
    }

    /**
     * Check if there are pending parts waiting to be flushed.
     */
    public function hasPending(): bool
    {
        return ! empty($this->parts);
    }
}
