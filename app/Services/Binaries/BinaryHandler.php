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
     * Resolve binaries for a chunk of headers with one bulk insert and one id lookup.
     *
     * @param  array<int, array{header: array<string, mixed>, collection_id: int, file_number: int}>  $records
     * @return array<int, int> Binary ids keyed by header index
     */
    public function getOrCreateBinaries(array $records, int $groupId): array
    {
        $resolved = [];
        $pending = [];
        $indexesByArticleKey = [];
        $extraUpdatesByArticleKey = [];

        foreach ($records as $index => $record) {
            $header = $record['header'];
            $collectionId = (int) $record['collection_id'];
            $fileNumber = (int) $record['file_number'];
            $articleKey = $header['matches'][1];

            if (isset($this->articles[$articleKey])) {
                $binaryId = $this->articles[$articleKey]['BinaryID'];
                $this->binariesUpdate[$binaryId]['Size'] += (int) $header['Bytes'];
                $this->binariesUpdate[$binaryId]['Parts']++;
                $resolved[$index] = $binaryId;

                continue;
            }

            $indexesByArticleKey[$articleKey][] = $index;
            if (isset($pending[$articleKey])) {
                $extraUpdatesByArticleKey[$articleKey]['Size'] = ($extraUpdatesByArticleKey[$articleKey]['Size'] ?? 0) + (int) $header['Bytes'];
                $extraUpdatesByArticleKey[$articleKey]['Parts'] = ($extraUpdatesByArticleKey[$articleKey]['Parts'] ?? 0) + 1;

                continue;
            }

            $hash = md5((string) ($header['matches'][1] ?? '').(string) ($header['From'] ?? '').(string) $groupId);
            $pending[$articleKey] = [
                'hash' => $hash,
                'name' => Utf8::clean($header['matches'][1]),
                'collections_id' => $collectionId,
                'totalparts' => (int) $header['matches'][3],
                'filenumber' => $fileNumber,
                'partsize' => (int) $header['Bytes'],
            ];
        }

        if ($pending === []) {
            return $resolved;
        }

        try {
            $result = $this->bulkInsertAndResolve($pending);
            $idsByKey = $result['ids'];
            $existingKeys = $result['existing'];
            $driver = DB::getDriverName();
            foreach ($pending as $articleKey => $row) {
                $lookupKey = $this->binaryLookupKey($row['hash'], (int) $row['collections_id']);
                $binaryId = $idsByKey[$lookupKey] ?? 0;
                if ($binaryId <= 0) {
                    continue;
                }

                $this->binariesUpdate[$binaryId] = $this->binariesUpdate[$binaryId] ?? ['Size' => 0, 'Parts' => 0];
                if ($driver === 'sqlite' && isset($existingKeys[$lookupKey])) {
                    $this->binariesUpdate[$binaryId]['Size'] += (int) $row['partsize'];
                    $this->binariesUpdate[$binaryId]['Parts']++;
                }
                if (isset($extraUpdatesByArticleKey[$articleKey])) {
                    $this->binariesUpdate[$binaryId]['Size'] += $extraUpdatesByArticleKey[$articleKey]['Size'];
                    $this->binariesUpdate[$binaryId]['Parts'] += $extraUpdatesByArticleKey[$articleKey]['Parts'];
                }

                $this->articles[$articleKey] = [
                    'CollectionID' => (int) $row['collections_id'],
                    'BinaryID' => $binaryId,
                ];

                foreach ($indexesByArticleKey[$articleKey] ?? [] as $index) {
                    $resolved[$index] = $binaryId;
                }
            }
        } catch (\Throwable $e) {
            if (config('app.debug') === true) {
                Log::error('Bulk binary insert failed: '.$e->getMessage());
            }
        }

        return $resolved;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rowsByArticleKey
     * @return array{ids: array<string, int>, existing: array<string, true>}
     */
    private function bulkInsertAndResolve(array $rowsByArticleKey): array
    {
        $lookupRows = array_values($rowsByArticleKey);
        $existingKeys = $this->existingBinaryKeys($lookupRows);

        if (DB::getDriverName() === 'sqlite') {
            $this->bulkInsertBinariesSqlite($lookupRows);
        } else {
            $this->bulkInsertBinariesMysql($lookupRows);
        }

        $idsByKey = $this->resolveBinaryIds($lookupRows);
        foreach ($idsByKey as $key => $id) {
            if (! isset($existingKeys[$key])) {
                $this->insertedBinaryIds[$id] = true;
            }
        }

        return ['ids' => $idsByKey, 'existing' => $existingKeys];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, true>
     */
    private function existingBinaryKeys(array $rows): array
    {
        $keys = [];
        foreach ($this->selectBinaryRows($rows) as $row) {
            $keys[$this->binaryLookupKey((string) $row->hashvalue, (int) $row->collections_id)] = true;
        }

        return $keys;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function bulkInsertBinariesSqlite(array $rows): void
    {
        $insertRows = [];
        foreach ($rows as $row) {
            $insertRows[] = [
                'binaryhash' => $row['hash'],
                'name' => $row['name'],
                'collections_id' => $row['collections_id'],
                'totalparts' => $row['totalparts'],
                'currentparts' => 1,
                'filenumber' => $row['filenumber'],
                'partsize' => $row['partsize'],
            ];
        }

        DB::table('binaries')->insertOrIgnore($insertRows);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function bulkInsertBinariesMysql(array $rows): void
    {
        $placeholders = [];
        $bindings = [];
        foreach ($rows as $row) {
            $placeholders[] = '(UNHEX(?), ?, ?, ?, 1, ?, ?)';
            array_push(
                $bindings,
                $row['hash'],
                $row['name'],
                $row['collections_id'],
                $row['totalparts'],
                $row['filenumber'],
                $row['partsize']
            );
        }

        DB::statement(
            'INSERT INTO binaries (binaryhash, name, collections_id, totalparts, currentparts, filenumber, partsize) VALUES '
            .implode(',', $placeholders)
            .' ON DUPLICATE KEY UPDATE currentparts = currentparts + 1, partsize = partsize + VALUES(partsize)',
            $bindings
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function resolveBinaryIds(array $rows): array
    {
        $resolved = [];
        foreach ($this->selectBinaryRows($rows) as $row) {
            $resolved[$this->binaryLookupKey((string) $row->hashvalue, (int) $row->collections_id)] = (int) $row->id;
        }

        return $resolved;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<object>
     */
    private function selectBinaryRows(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $driver = DB::getDriverName();
        $clauses = [];
        $bindings = [];
        foreach ($rows as $row) {
            $clauses[] = $driver === 'sqlite'
                ? '(binaryhash = ? AND collections_id = ?)'
                : '(binaryhash = UNHEX(?) AND collections_id = ?)';
            $bindings[] = $row['hash'];
            $bindings[] = $row['collections_id'];
        }

        $hashExpression = $driver === 'sqlite' ? 'binaryhash' : 'LOWER(HEX(binaryhash))';

        return DB::select(
            "SELECT id, {$hashExpression} AS hashvalue, collections_id FROM binaries WHERE ".implode(' OR ', $clauses),
            $bindings
        );
    }

    private function binaryLookupKey(string $hash, int $collectionId): string
    {
        return strtolower($hash).':'.$collectionId;
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
