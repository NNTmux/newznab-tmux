<?php

declare(strict_types=1);

namespace App\Services\Binaries;

use App\Support\SqlError;
use Illuminate\Support\Facades\DB;

/**
 * Handles part record creation during header storage.
 *
 * @phpstan-type PartRow array{binaries_id: int, number: int|string, messageid: string, partnumber: int, size: int|string}
 */
final class PartHandler
{
    /** @var list<PartRow> Pending parts to insert */
    private array $parts = [];

    /** @var list<int|string> Part numbers successfully inserted */
    private array $insertedPartNumbers = [];

    /** @var list<int|string> Part numbers that failed to insert */
    private array $failedPartNumbers = [];

    /** @var array<int, true> Binary ids whose stored parts must be re-aggregated */
    private array $touchedBinaryIds = [];

    private int $chunkSize;

    private ?\Throwable $lastException = null;

    /** @phpstan-ignore property.onlyWritten */
    private bool $addToPartRepair;

    public function __construct(int $chunkSize = 500, bool $addToPartRepair = true)
    {
        $this->chunkSize = max(1, $chunkSize);
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
        $this->touchedBinaryIds = [];
        $this->lastException = null;
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
        $partNumber = (int) ($header['matches'][2] ?? 0);
        $messageId = trim((string) ($header['Message-ID'] ?? ''));
        if ($partNumber <= 0 || $messageId === '' || strlen($messageId) > 255 || preg_match('/^[\x20-\x7E]+$/D', $messageId) !== 1) {
            if (isset($header['Number'])) {
                $this->failedPartNumbers[] = $header['Number'];
            }

            return false;
        }

        $this->parts[] = [
            'binaries_id' => $binaryId,
            'number' => $header['Number'],
            'messageid' => $messageId,
            'partnumber' => $partNumber,
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

        $pendingParts = $this->parts;
        $parts = $this->deduplicateParts($pendingParts);
        foreach ($parts as $part) {
            $this->touchedBinaryIds[(int) $part['binaries_id']] = true;
        }

        $insertedCount = $this->insertChunk($parts);

        if ($insertedCount === null) {
            foreach ($pendingParts as $part) {
                $this->failedPartNumbers[] = $part['number'];
            }

            $this->parts = [];

            return false;
        }

        if ($insertedCount === \count($parts)) {
            foreach ($parts as $part) {
                $this->insertedPartNumbers[] = $part['number'];
            }

            $this->parts = [];

            return true;
        }

        $existingKeys = $this->existingPartKeys($parts);
        foreach ($parts as $part) {
            $key = $this->partKey((int) $part['binaries_id'], (int) $part['partnumber']);
            if (! isset($existingKeys[$key])) {
                $this->failedPartNumbers[] = $part['number'];
            }
        }

        $this->parts = [];

        return empty($this->failedPartNumbers);
    }

    /**
     * @param  list<PartRow>  $parts
     */
    private function insertChunk(array $parts): ?int
    {
        $driver = DB::getDriverName();
        $totalInserted = 0;

        try {
            foreach (array_chunk($parts, $this->chunkSize) as $chunk) {
                $placeholders = [];
                $bindings = [];

                foreach ($chunk as $row) {
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

                $totalInserted += (int) DB::affectingStatement($sql, $bindings);
            }

            return $totalInserted;
        } catch (\Throwable $e) {
            $this->lastException = $e;
            if (config('app.debug') === true) {
                SqlError::logFailure('Parts chunk insert failed', $e);
            }

            return null;
        }
    }

    /**
     * @param  list<PartRow>  $parts
     * @return array<string, true>
     */
    private function existingPartKeys(array $parts): array
    {
        if (empty($parts)) {
            return [];
        }

        // Deduplicate (binaries_id, partnumber) pairs so we don't bind the same
        // tuple twice when a chunk contains repeats.
        $uniquePairs = [];
        foreach ($parts as $part) {
            $bid = (int) $part['binaries_id'];
            $partNumber = (int) $part['partnumber'];
            $uniquePairs[$bid.':'.$partNumber] = [$bid, $partNumber];
        }

        $keys = [];
        // Single tuple-IN per sub-chunk: (binaries_id, partnumber) IN ((?,?),...).
        // Both MySQL and SQLite (3.15+) support this row-constructor form,
        // which lets one SELECT replace the previous "one SELECT per binary".
        foreach (array_chunk(array_values($uniquePairs), $this->chunkSize) as $chunk) {
            $tuples = implode(',', array_fill(0, \count($chunk), '(?,?)'));
            $bindings = [];
            foreach ($chunk as [$bid, $partNumber]) {
                $bindings[] = $bid;
                $bindings[] = $partNumber;
            }

            $rows = DB::select(
                "SELECT binaries_id, partnumber FROM parts WHERE (binaries_id, partnumber) IN ({$tuples})",
                $bindings
            );

            foreach ($rows as $row) {
                $keys[$this->partKey((int) $row->binaries_id, (int) $row->partnumber)] = true;
            }
        }

        return $keys;
    }

    private function partKey(int $binaryId, int $partNumber): string
    {
        return $binaryId.':'.$partNumber;
    }

    /**
     * Choose one deterministic segment for each (binary, part number).
     * Prefer a non-empty message id, then the larger payload, then the lower
     * article number. This matches the storage migration's duplicate policy.
     *
     * @param  list<PartRow>  $parts
     * @return list<PartRow>
     */
    private function deduplicateParts(array $parts): array
    {
        $deduplicated = [];
        foreach ($parts as $part) {
            $key = $this->partKey((int) $part['binaries_id'], (int) $part['partnumber']);
            if (! isset($deduplicated[$key]) || $this->shouldPrefer($part, $deduplicated[$key])) {
                $deduplicated[$key] = $part;
            }
        }

        return array_values($deduplicated);
    }

    /**
     * @param  PartRow  $candidate
     * @param  PartRow  $current
     */
    private function shouldPrefer(array $candidate, array $current): bool
    {
        $candidateHasMessageId = trim((string) $candidate['messageid']) !== '';
        $currentHasMessageId = trim((string) $current['messageid']) !== '';
        if ($candidateHasMessageId !== $currentHasMessageId) {
            return $candidateHasMessageId;
        }

        if ((int) $candidate['size'] !== (int) $current['size']) {
            return (int) $candidate['size'] > (int) $current['size'];
        }

        return (int) $candidate['number'] < (int) $current['number'];
    }

    /** @return list<int> */
    public function getTouchedBinaryIds(): array
    {
        return array_keys($this->touchedBinaryIds);
    }

    /**
     * Get numbers of successfully inserted parts.
     *
     * @return list<int|string>
     */
    public function getInsertedNumbers(): array
    {
        return $this->insertedPartNumbers;
    }

    /**
     * Get numbers of failed part inserts.
     *
     * @return list<int|string>
     */
    public function getFailedNumbers(): array
    {
        return $this->failedPartNumbers;
    }

    public function getLastException(): ?\Throwable
    {
        return $this->lastException;
    }

    /**
     * Check if there are pending parts waiting to be flushed.
     */
    public function hasPending(): bool
    {
        return ! empty($this->parts);
    }
}
