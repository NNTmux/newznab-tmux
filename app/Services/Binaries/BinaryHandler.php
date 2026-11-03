<?php

declare(strict_types=1);

namespace App\Services\Binaries;

use App\Support\SqlError;
use App\Support\Utf8;
use Illuminate\Support\Facades\DB;

/**
 * Handles binary record creation and updates during header storage.
 */
final class BinaryHandler
{
    /**
     * Hard upper bound on the number of rows packed into a single SQL
     * statement (multi-row INSERT, OR-clause SELECT, etc.). Keeps the
     * generated SQL string and PDO parameter list bounded regardless of
     * how many headers the caller passes in.
     */
    private int $sqlChunkSize;

    /** @var array<int, true> IDs of binaries created in this batch */
    private array $insertedBinaryIds = [];

    /** @var array<string, array{CollectionID: int, BinaryID: int}> Processed articles */
    private array $articles = [];

    private ?\Throwable $lastException = null;

    public function __construct(int $sqlChunkSize = 500)
    {
        $this->sqlChunkSize = max(1, min(1000, $sqlChunkSize));
    }

    /**
     * Reset state for a new batch.
     */
    public function reset(): void
    {
        $this->insertedBinaryIds = [];
        $this->articles = [];
        $this->lastException = null;
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
        $hash = $this->identityHash($header, $fileNumber);
        $articleKey = $this->binaryLookupKey($hash, $collectionId);

        // Return cached if already processed
        if (isset($this->articles[$articleKey])) {
            return $this->articles[$articleKey]['BinaryID'];
        }

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
                $this->articles[$articleKey] = [
                    'CollectionID' => $collectionId,
                    'BinaryID' => $binaryId,
                ];

                return $binaryId;
            }
        } catch (\Throwable $e) {
            $this->lastException = $e;
            if (config('app.debug') === true) {
                SqlError::logFailure('Binary insert failed', $e);
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
        foreach ($records as $index => $record) {
            $header = $record['header'];
            $collectionId = (int) $record['collection_id'];
            $fileNumber = (int) $record['file_number'];
            $hash = $this->identityHash($header, $fileNumber);
            $articleKey = $this->binaryLookupKey($hash, $collectionId);

            if (isset($this->articles[$articleKey])) {
                $binaryId = $this->articles[$articleKey]['BinaryID'];
                $resolved[$index] = $binaryId;

                continue;
            }

            $indexesByArticleKey[$articleKey][] = $index;
            if (isset($pending[$articleKey])) {
                continue;
            }

            $pending[$articleKey] = [
                'hash' => $hash,
                'name' => Utf8::clean($header['matches'][1]),
                'collections_id' => $collectionId,
                'totalparts' => (int) $header['matches'][3],
                'filenumber' => $fileNumber,
            ];
        }

        if ($pending === []) {
            return $resolved;
        }

        try {
            $result = $this->bulkInsertAndResolve($pending);
            $idsByKey = $result['ids'];
            foreach ($pending as $articleKey => $row) {
                $lookupKey = $this->binaryLookupKey($row['hash'], (int) $row['collections_id']);
                $binaryId = $idsByKey[$lookupKey] ?? 0;
                if ($binaryId <= 0) {
                    continue;
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
            $this->lastException = $e;
            if (config('app.debug') === true) {
                SqlError::logFailure('Bulk binary insert failed', $e);
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

        // One SELECT establishes "did this row exist?", its id and its stored
        // totalparts.
        $existing = $this->selectExistingBinaries($lookupRows);
        $idsByKey = [];
        $existingKeys = [];
        foreach ($existing as $key => $row) {
            $idsByKey[$key] = $row['id'];
            $existingKeys[$key] = true;
        }

        // Only insert rows the prefetch did not find: ON DUPLICATE KEY on a
        // known row still burns an AUTO_INCREMENT value and takes a write lock.
        $rowsToInsert = [];
        $totalPartsBumps = [];
        foreach ($lookupRows as $row) {
            $key = $this->binaryLookupKey((string) $row['hash'], (int) $row['collections_id']);
            if (! isset($existing[$key])) {
                $rowsToInsert[] = $row;

                continue;
            }
            if ((int) $row['totalparts'] > $existing[$key]['totalparts']) {
                $totalPartsBumps[(int) $row['totalparts']][] = $existing[$key]['id'];
            }
        }

        // Deterministic unique-index lock order across concurrent workers.
        usort(
            $rowsToInsert,
            static fn (array $a, array $b): int => [(int) $a['collections_id'], (string) $a['hash']]
                <=> [(int) $b['collections_id'], (string) $b['hash']]
        );

        if ($rowsToInsert !== []) {
            if (DB::getDriverName() === 'sqlite') {
                $this->bulkInsertBinariesSqlite($rowsToInsert);
            } else {
                $this->bulkInsertBinariesMysql($rowsToInsert);
            }

            foreach ($this->selectExistingBinaries($rowsToInsert) as $key => $row) {
                $idsByKey[$key] = $row['id'];
            }
        }

        $this->raiseTotalParts($totalPartsBumps);

        foreach ($idsByKey as $key => $id) {
            if (! isset($existingKeys[$key])) {
                $this->insertedBinaryIds[$id] = true;
            }
        }

        return ['ids' => $idsByKey, 'existing' => $existingKeys];
    }

    /**
     * Raise binaries.totalparts where a newer header reports more parts.
     *
     * @param  array<int, list<int>>  $binaryIdsByTotalParts
     */
    private function raiseTotalParts(array $binaryIdsByTotalParts): void
    {
        foreach ($binaryIdsByTotalParts as $totalParts => $binaryIds) {
            foreach (array_chunk(array_values(array_unique($binaryIds)), $this->sqlChunkSize) as $chunk) {
                DB::table('binaries')
                    ->whereIn('id', $chunk)
                    ->where('totalparts', '<', $totalParts)
                    ->update(['totalparts' => $totalParts]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array{id: int, totalparts: int}> keyed by binaryLookupKey(hash, collectionId)
     */
    private function selectExistingBinaries(array $rows): array
    {
        $resolved = [];
        foreach ($this->selectBinaryRows($rows) as $row) {
            $resolved[$this->binaryLookupKey((string) $row->hashvalue, (int) $row->collections_id)] = [
                'id' => (int) $row->id,
                'totalparts' => (int) $row->totalparts,
            ];
        }

        return $resolved;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function bulkInsertBinariesSqlite(array $rows): void
    {
        foreach (array_chunk($rows, $this->sqlChunkSize) as $chunk) {
            $insertRows = [];
            foreach ($chunk as $row) {
                $insertRows[] = [
                    'binaryhash' => $row['hash'],
                    'name' => $row['name'],
                    'collections_id' => $row['collections_id'],
                    'totalparts' => $row['totalparts'],
                    'currentparts' => 0,
                    'filenumber' => $row['filenumber'],
                    'partsize' => 0,
                ];
            }

            DB::table('binaries')->insertOrIgnore($insertRows);
        }
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function bulkInsertBinariesMysql(array $rows): void
    {
        foreach (array_chunk($rows, $this->sqlChunkSize) as $chunk) {
            $placeholders = [];
            $bindings = [];
            foreach ($chunk as $row) {
                $placeholders[] = '(UNHEX(?), ?, ?, ?, 0, ?, 0)';
                array_push(
                    $bindings,
                    $row['hash'],
                    $row['name'],
                    $row['collections_id'],
                    $row['totalparts'],
                    $row['filenumber']
                );
            }

            DB::statement(
                'INSERT INTO binaries (binaryhash, name, collections_id, totalparts, currentparts, filenumber, partsize) VALUES '
                .implode(',', $placeholders)
                .' ON DUPLICATE KEY UPDATE totalparts = GREATEST(totalparts, VALUES(totalparts)), id = LAST_INSERT_ID(id)',
                $bindings
            );
        }
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
        $hashExpression = $driver === 'sqlite' ? 'binaryhash' : 'LOWER(HEX(binaryhash))';
        $hashPlaceholder = $driver === 'sqlite' ? '?' : 'UNHEX(?)';

        $hashesByCollection = [];
        foreach ($rows as $row) {
            $hashesByCollection[(int) $row['collections_id']][(string) $row['hash']] = true;
        }

        // Pack several `(collections_id = ? AND binaryhash IN (...))` ranges
        // into one statement, bounded by sqlChunkSize hashes. Each disjunct is
        // a range on ux_binaries_collection_hash, and a chunk touching many
        // small collections costs one round-trip instead of one per collection.
        $batches = [];
        $current = ['disjuncts' => [], 'bindings' => [], 'hashes' => 0];
        foreach ($hashesByCollection as $collectionId => $hashSet) {
            foreach (array_chunk(array_keys($hashSet), $this->sqlChunkSize) as $chunk) {
                if ($this->sqlChunkSize < $current['hashes'] + \count($chunk) && $current['disjuncts'] !== []) {
                    $batches[] = $current;
                    $current = ['disjuncts' => [], 'bindings' => [], 'hashes' => 0];
                }
                $current['disjuncts'][] = '(collections_id = ? AND binaryhash IN ('
                    .implode(',', array_fill(0, \count($chunk), $hashPlaceholder)).'))';
                $current['bindings'][] = $collectionId;
                array_push($current['bindings'], ...$chunk);
                $current['hashes'] += \count($chunk);
            }
        }
        // $rows is non-empty here, so the last batch always holds a disjunct.
        $batches[] = $current;

        $results = [];
        foreach ($batches as $batch) {
            foreach (DB::select(
                "SELECT id, {$hashExpression} AS hashvalue, collections_id, totalparts FROM binaries WHERE "
                .implode(' OR ', $batch['disjuncts']),
                $batch['bindings']
            ) as $row) {
                $results[] = $row;
            }
        }

        return $results;
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
        if ($driver === 'sqlite') {
            return $this->insertBinarySqlite($hash, $name, $collectionId, $totalParts, $fileNumber);
        }

        return $this->insertBinaryMysql($hash, $name, $collectionId, $totalParts, $fileNumber);
    }

    private function insertBinarySqlite(
        string $hash,
        string $name,
        int $collectionId,
        int $totalParts,
        int $fileNumber
    ): int {
        $affected = DB::table('binaries')->insertOrIgnore([
            'binaryhash' => $hash,
            'name' => $name,
            'collections_id' => $collectionId,
            'totalparts' => $totalParts,
            'currentparts' => 0,
            'filenumber' => $fileNumber,
            'partsize' => 0,
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
        int $fileNumber
    ): int {

        $sql = 'INSERT INTO binaries '
            .'(binaryhash, name, collections_id, totalparts, currentparts, filenumber, partsize) '
            .'VALUES (UNHEX(?), ?, ?, ?, 0, ?, 0) '
            .'ON DUPLICATE KEY UPDATE totalparts = GREATEST(totalparts, VALUES(totalparts)), id = LAST_INSERT_ID(id)';

        DB::statement($sql, [$hash, $name, $collectionId, $totalParts, $fileNumber]);

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
     * Rebuild aggregates from stored parts for the binaries touched by a chunk.
     * This is deliberately idempotent: ignored duplicate part inserts cannot
     * inflate currentparts or partsize.
     *
     * @param  list<int>  $binaryIds
     */
    public function refreshAggregates(array $binaryIds, int $chunkSize = 500): bool
    {
        $binaryIds = array_values(array_unique(array_map('intval', $binaryIds)));
        if ($binaryIds === []) {
            return true;
        }

        try {
            if (DB::getDriverName() === 'sqlite') {
                foreach ($binaryIds as $binaryId) {
                    $aggregate = DB::selectOne(
                        'SELECT COUNT(*) AS currentparts, COALESCE(SUM(size), 0) AS partsize FROM parts WHERE binaries_id = ?',
                        [$binaryId]
                    );
                    DB::update(
                        'UPDATE binaries SET currentparts = ?, partsize = ?, partcheck = CASE WHEN ? >= totalparts THEN 1 ELSE 0 END WHERE id = ?',
                        [(int) $aggregate->currentparts, (int) $aggregate->partsize, (int) $aggregate->currentparts, $binaryId]
                    );
                }

                return true;
            }

            $chunkSize = max(1, min($chunkSize, $this->sqlChunkSize));
            foreach (array_chunk($binaryIds, $chunkSize) as $chunk) {
                $placeholders = implode(',', array_fill(0, \count($chunk), '?'));
                DB::update(
                    "UPDATE binaries b
                     INNER JOIN (
                         SELECT p.binaries_id, COUNT(*) AS currentparts, COALESCE(SUM(p.size), 0) AS partsize
                         FROM parts p
                         WHERE p.binaries_id IN ({$placeholders})
                         GROUP BY p.binaries_id
                     ) a ON a.binaries_id = b.id
                     SET b.currentparts = a.currentparts,
                         b.partsize = a.partsize,
                         b.partcheck = CASE WHEN a.currentparts >= b.totalparts THEN 1 ELSE 0 END",
                    $chunk
                );
            }

            return true;
        } catch (\Throwable $e) {
            $this->lastException = $e;
            if (config('app.debug') === true) {
                SqlError::logFailure('Binaries aggregate update failed', $e);
            }

            return false;
        }
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
     * Collections owning the given binaries, from the ids resolved this chunk.
     *
     * @param  list<int>  $binaryIds
     * @return list<int>
     */
    public function collectionIdsForBinaries(array $binaryIds): array
    {
        $wanted = array_fill_keys(array_map('intval', $binaryIds), true);
        $collectionIds = [];
        foreach ($this->articles as $article) {
            if (isset($wanted[$article['BinaryID']])) {
                $collectionIds[$article['CollectionID']] = true;
            }
        }

        return array_keys($collectionIds);
    }

    public function getLastException(): ?\Throwable
    {
        return $this->lastException;
    }

    /** @param array<string, mixed> $header */
    private function identityHash(array $header, int $fileNumber): string
    {
        if ($fileNumber > 0) {
            return md5('file:'.$fileNumber);
        }

        $subject = preg_replace('/\s+/u', ' ', Utf8::clean((string) ($header['matches'][1] ?? ''))) ?? '';
        $poster = preg_replace('/\s+/u', ' ', Utf8::clean((string) ($header['From'] ?? ''))) ?? '';

        return md5('subject:'.mb_strtolower(trim($subject))."\0poster:".mb_strtolower(trim($poster)));
    }
}
