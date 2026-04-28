<?php

declare(strict_types=1);

namespace App\Services\Binaries;

use App\Support\Utf8;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles binary record creation and updates during header storage.
 */
final class BinaryHandler
{
    /** @var array<int, array{Size: int, Parts: int}> Pending binary updates */
    private array $binariesUpdate = [];

    /** @var array<int, true> IDs of binaries created in this batch */
    private array $insertedBinaryIds = [];

    /** @var array<string, array{CollectionID: int, BinaryID: int}> Processed articles */
    private array $articles = [];

    public function __construct() {}

    /**
     * Reset state for a new batch.
     */
    public function reset(): void
    {
        $this->binariesUpdate = [];
        $this->insertedBinaryIds = [];
        $this->articles = [];
    }

    /**
     * Get or create a binary for the given header.
     *
     * @param  array<string, mixed>  $header
     * @return int|null Binary ID or null on failure
     */
    public function getOrCreateBinary(
        array $header,
        int $collectionId,
        int $groupId,
        int $fileNumber
    ): ?int {
        $articleKey = $header['matches'][1];

        // Return cached if already processed
        if (isset($this->articles[$articleKey])) {
            $binaryId = $this->articles[$articleKey]['BinaryID'];
            $this->binariesUpdate[$binaryId]['Size'] += $header['Bytes'];
            $this->binariesUpdate[$binaryId]['Parts']++;

            return $binaryId;
        }

        $hash = md5((string) ($header['matches'][1] ?? '').(string) ($header['From'] ?? '').(string) $groupId);
        $driver = DB::getDriverName();

        try {
            $binaryId = $this->insertOrGetBinary(
                $driver,
                $hash,
                $header,
                $collectionId,
                $fileNumber
            );

            if ($binaryId > 0) {
                $this->binariesUpdate[$binaryId] = ['Size' => 0, 'Parts' => 0];
                $this->articles[$articleKey] = [
                    'CollectionID' => $collectionId,
                    'BinaryID' => $binaryId,
                ];

                return $binaryId;
            }
        } catch (\Throwable $e) {
            if (config('app.debug') === true) {
                Log::error('Binary insert failed: '.$e->getMessage());
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function insertOrGetBinary(
        string $driver,
        string $hash,
        array $header,
        int $collectionId,
        int $fileNumber
    ): int {
        $name = Utf8::clean($header['matches'][1]);
        $totalParts = (int) $header['matches'][3];
        $partSize = (int) $header['Bytes'];

        if ($driver === 'sqlite') {
            return $this->insertBinarySqlite($hash, $name, $collectionId, $totalParts, $fileNumber, $partSize);
        }

        return $this->insertBinaryMysql($hash, $name, $collectionId, $totalParts, $fileNumber, $partSize);
    }

    private function insertBinarySqlite(
        string $hash,
        string $name,
        int $collectionId,
        int $totalParts,
        int $fileNumber,
        int $partSize
    ): int {
        $affected = DB::table('binaries')->insertOrIgnore([
            'binaryhash' => $hash,
            'name' => $name,
            'collections_id' => $collectionId,
            'totalparts' => $totalParts,
            'currentparts' => 1,
            'filenumber' => $fileNumber,
            'partsize' => $partSize,
        ]);

        if ($affected > 0 && ($lastId = (int) DB::connection()->getPdo()->lastInsertId()) > 0) {
            $this->insertedBinaryIds[$lastId] = true;

            return $lastId;
        }

        $bin = DB::selectOne(
            'SELECT id FROM binaries WHERE binaryhash = ? AND collections_id = ? LIMIT 1',
            [$hash, $collectionId]
        );

        return (int) ($bin->id ?? 0);
    }

    private function insertBinaryMysql(
        string $hash,
        string $name,
        int $collectionId,
        int $totalParts,
        int $fileNumber,
        int $partSize
    ): int {

        $sql = 'INSERT INTO binaries '
            .'(binaryhash, name, collections_id, totalparts, currentparts, filenumber, partsize) '
            .'VALUES (UNHEX(?), ?, ?, ?, 1, ?, ?) '
            .'ON DUPLICATE KEY UPDATE currentparts = currentparts + 1, partsize = partsize + VALUES(partsize)';

        DB::statement($sql, [$hash, $name, $collectionId, $totalParts, $fileNumber, $partSize]);

        $lastId = (int) DB::connection()->getPdo()->lastInsertId();
        if ($lastId > 0) {
            $this->insertedBinaryIds[$lastId] = true;

            return $lastId;
        }

        $bin = DB::selectOne(
            'SELECT id FROM binaries WHERE binaryhash = UNHEX(?) AND collections_id = ? LIMIT 1',
            [$hash, $collectionId]
        );

        return (int) ($bin->id ?? 0);
    }

    /**
     * Flush accumulated size/parts updates to the database.
     */
    public function flushUpdates(int $chunkSize = 1000): bool
    {
        $updates = $this->getPendingUpdates();
        if (empty($updates)) {
            return true;
        }

        $driver = DB::getDriverName();

        try {
            if ($driver === 'sqlite') {
                return $this->flushUpdatesSqlite($updates); // @phpstan-ignore argument.type
            }

            return $this->flushUpdatesMysql($updates, $chunkSize); // @phpstan-ignore argument.type
        } catch (\Throwable $e) {
            if (config('app.debug') === true) {
                Log::error('Binaries aggregate update failed: '.$e->getMessage());
            }

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $updates
     */
    private function flushUpdatesSqlite(array $updates): bool
    {
        foreach ($updates as $row) {
            DB::statement(
                'UPDATE binaries SET partsize = partsize + ?, currentparts = currentparts + ? WHERE id = ?',
                [$row['partsize'], $row['currentparts'], $row['id']]
            );
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $updates
     */
    private function flushUpdatesMysql(array $updates, int $chunkSize): bool
    {
        foreach (array_chunk($updates, $chunkSize) as $chunk) {
            $selects = [];
            $bindings = [];

            foreach ($chunk as $row) {
                $selects[] = 'SELECT ? AS id, ? AS partsize, ? AS currentparts';
                $bindings[] = $row['id'];
                $bindings[] = $row['partsize'];
                $bindings[] = $row['currentparts'];
            }

            $sql = 'UPDATE binaries b INNER JOIN ('
                .implode(' UNION ALL ', $selects)
                .') u ON u.id = b.id '
                .'SET b.partsize = b.partsize + u.partsize, '
                .'b.currentparts = b.currentparts + u.currentparts';

            DB::statement($sql, $bindings);
        }

        return true;
    }

    /**
     * Check if article is already processed.
     */
    public function hasArticle(string $articleKey): bool
    {
        return isset($this->articles[$articleKey]);
    }

    /**
     * Get IDs created in this batch.
     *
     * @return list<int>
     */
    public function getInsertedIds(): array
    {
        return array_keys($this->insertedBinaryIds);
    }

    /**
     * Get pending binary updates that haven't been flushed.
     *
     * @return list<array<string, int>>
     */
    private function getPendingUpdates(): array
    {
        $rows = [];
        foreach ($this->binariesUpdate as $binaryId => $binary) {
            if (($binary['Size'] ?? 0) > 0 || ($binary['Parts'] ?? 0) > 0) {
                $rows[] = [
                    'id' => $binaryId,
                    'partsize' => $binary['Size'],
                    'currentparts' => $binary['Parts'],
                ];
            }
        }

        return $rows;
    }
}
