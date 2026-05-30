<?php

declare(strict_types=1);

namespace App\Services\Binaries;

use App\Models\Collection;
use App\Services\CollectionsCleaningService;
use App\Services\XrefService;
use App\Support\Utf8;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles collection record creation and retrieval during header storage.
 */
final class CollectionHandler
{
    /**
     * Hard upper bound on rows packed into a single SQL statement
     * (multi-row INSERT, IN(...) lookup, etc.). Keeps the generated SQL
     * and PDO parameter count bounded regardless of caller batch size.
     */
    private const MAX_SQL_ROWS_PER_STATEMENT = 500;

    private CollectionsCleaningService $collectionsCleaning;

    private XrefService $xrefService;

    /** @var array<string, int> Cached collection IDs by key */
    private array $collectionIds = [];

    /** @var array<int, true> IDs of collections created in this batch */
    private array $insertedCollectionIds = [];

    /** @var array<string, true> Collection hashes touched in this batch */
    private array $batchCollectionHashes = [];

    /** @var array<string, string|null> Cached collection xrefs by collection key */
    private array $existingXrefs = [];

    /** @var array<string, int> Cached collection IDs by collectionhash (populated by bulk prefetch) */
    private array $existingIdsByHash = [];

    public function __construct(
        ?CollectionsCleaningService $collectionsCleaning = null,
        ?XrefService $xrefService = null
    ) {
        $this->collectionsCleaning = $collectionsCleaning ?? new CollectionsCleaningService;
        $this->xrefService = $xrefService ?? new XrefService;
    }

    /**
     * Reset state for a new batch.
     */
    public function reset(): void
    {
        $this->collectionIds = [];
        $this->insertedCollectionIds = [];
        $this->batchCollectionHashes = [];
        $this->existingXrefs = [];
        $this->existingIdsByHash = [];
    }

    /**
     * Get or create a collection for the given header.
     *
     * @param  array<string, mixed>  $header
     * @return int|null Collection ID or null on failure
     */
    public function getOrCreateCollection(
        array $header,
        int $groupId,
        string $groupName,
        int $totalFiles,
        string $batchNoise
    ): ?int {
        $collMatch = $this->collectionsCleaning->collectionsCleaner(
            $header['matches'][1],
            $groupName
        );

        $collectionKey = $collMatch['name'].$totalFiles;

        // Return cached ID if already processed this batch
        if (isset($this->collectionIds[$collectionKey])) {
            return $this->collectionIds[$collectionKey];
        }

        $collectionHash = sha1($collectionKey);
        $this->batchCollectionHashes[$collectionHash] = true;

        $headerDate = is_numeric($header['Date']) ? (int) $header['Date'] : strtotime($header['Date']);
        $now = now()->timestamp;
        $unixtime = min($headerDate, $now) ?: $now;

        if (! array_key_exists($collectionKey, $this->existingXrefs)) {
            $this->existingXrefs[$collectionKey] = Collection::whereCollectionhash($collectionHash)->value('xref');
        }
        $existingXref = $this->existingXrefs[$collectionKey];
        $headerTokens = $this->xrefService->extractTokens($header['Xref'] ?? '');
        $newTokens = $this->xrefService->diffNewTokens($existingXref, $header['Xref'] ?? '');
        $finalXrefAppend = implode(' ', $newTokens);

        $subject = substr(Utf8::clean($header['matches'][1]), 0, 255);
        $fromName = Utf8::clean($header['From']);

        $driver = DB::getDriverName();

        try {
            $collectionId = $this->insertOrGetCollection(
                $driver,
                $subject,
                $fromName,
                $unixtime,
                $headerTokens,
                $finalXrefAppend,
                $groupId,
                $totalFiles,
                $collectionHash,
                $collMatch['id'],
                $batchNoise
            );

            if ($collectionId > 0) {
                $this->collectionIds[$collectionKey] = $collectionId;

                return $collectionId;
            }
        } catch (\Throwable $e) {
            if (config('app.debug') === true) {
                Log::error('Collection insert failed: '.$e->getMessage());
            }
        }

        return null;
    }

    /**
     * Resolve collections for a chunk of headers with one bulk insert and one id lookup.
     *
     * @param  array<int, array<string, mixed>>  $headers
     * @param  array<int, int>  $totalFilesByIndex
     * @return array<int, int> Collection ids keyed by header index
     */
    public function getOrCreateCollections(
        array $headers,
        int $groupId,
        string $groupName,
        array $totalFilesByIndex,
        string $batchNoise
    ): array {
        $resolved = [];
        $pending = [];
        $indexByCollectionKey = [];
        $xrefsToPrefetch = [];

        foreach ($headers as $index => $header) {
            $totalFiles = (int) ($totalFilesByIndex[$index] ?? 0);
            $collMatch = $this->collectionsCleaning->collectionsCleaner(
                $header['matches'][1],
                $groupName
            );

            $collectionKey = $collMatch['name'].$totalFiles;
            if (isset($this->collectionIds[$collectionKey])) {
                $resolved[$index] = $this->collectionIds[$collectionKey];

                continue;
            }

            $indexByCollectionKey[$collectionKey][] = $index;
            if (isset($pending[$collectionKey])) {
                continue;
            }

            $collectionHash = sha1($collectionKey);
            $this->batchCollectionHashes[$collectionHash] = true;

            $xrefsToPrefetch[$collectionKey] = $collectionHash;

            $headerDate = is_numeric($header['Date']) ? (int) $header['Date'] : strtotime($header['Date']);
            $now = now()->timestamp;
            $unixtime = min($headerDate, $now) ?: $now;
            $headerTokens = $this->xrefService->extractTokens($header['Xref'] ?? '');

            $pending[$collectionKey] = [
                'subject' => substr(Utf8::clean($header['matches'][1]), 0, 255),
                'fromname' => Utf8::clean($header['From']),
                'unixtime' => $unixtime,
                'xref' => implode(' ', $headerTokens),
                'header_xref' => $header['Xref'] ?? '',
                'groups_id' => $groupId,
                'totalfiles' => $totalFiles,
                'collectionhash' => $collectionHash,
                'collection_regexes_id' => (int) $collMatch['id'],
                'noise' => $batchNoise,
            ];
        }

        if ($pending === []) {
            return $resolved;
        }

        $this->prefetchExistingCollections($xrefsToPrefetch);
        foreach ($pending as $collectionKey => &$row) {
            $row['xref_append'] = implode(' ', $this->xrefService->diffNewTokens(
                $this->existingXrefs[$collectionKey],
                $row['header_xref']
            ));
            unset($row['header_xref']);
        }
        unset($row);

        try {
            $idsByHash = $this->bulkInsertAndResolve($pending);
            foreach ($pending as $collectionKey => $row) {
                $collectionId = $idsByHash[$row['collectionhash']] ?? 0;
                if ($collectionId <= 0) {
                    continue;
                }

                $this->collectionIds[$collectionKey] = $collectionId;
                foreach ($indexByCollectionKey[$collectionKey] ?? [] as $index) {
                    $resolved[$index] = $collectionId;
                }
            }
        } catch (\Throwable $e) {
            if (config('app.debug') === true) {
                Log::error('Bulk collection insert failed: '.$e->getMessage());
            }
        }

        return $resolved;
    }

    /**
     * Prefetch existing collections in one round-trip. Populates both
     * $existingXrefs (keyed by collectionKey) and $existingIdsByHash so the
     * subsequent bulkInsertAndResolve() can skip its existence-check SELECT
     * and only re-query for the freshly inserted rows.
     *
     * @param  array<string, string>  $collectionHashByKey
     */
    private function prefetchExistingCollections(array $collectionHashByKey): void
    {
        $missing = array_filter(
            $collectionHashByKey,
            fn (string $hash, string $collectionKey): bool => ! array_key_exists($collectionKey, $this->existingXrefs),
            ARRAY_FILTER_USE_BOTH
        );

        if ($missing === []) {
            return;
        }

        $rows = [];
        foreach (array_chunk(array_values($missing), self::MAX_SQL_ROWS_PER_STATEMENT) as $chunk) {
            foreach (Collection::query()
                ->whereIn('collectionhash', $chunk)
                ->select(['id', 'collectionhash', 'xref'])
                ->get() as $row) {
                $rows[(string) $row->collectionhash] = [
                    'id' => (int) $row->id,
                    'xref' => (string) $row->xref,
                ];
            }
        }

        foreach ($missing as $collectionKey => $hash) {
            if (isset($rows[$hash])) {
                $this->existingXrefs[$collectionKey] = $rows[$hash]['xref'];
                $this->existingIdsByHash[$hash] = $rows[$hash]['id'];
            } else {
                $this->existingXrefs[$collectionKey] = null;
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $rowsByCollectionKey
     * @return array<string, int> Collection ids keyed by collectionhash
     */
    private function bulkInsertAndResolve(array $rowsByCollectionKey): array
    {
        $hashes = array_values(array_column($rowsByCollectionKey, 'collectionhash'));

        // The prefetch step has already populated $existingIdsByHash, so we
        // know which hashes existed before the INSERT without issuing a
        // separate "existingHashes" SELECT.
        $existingHashes = [];
        $idsByHash = [];
        foreach ($hashes as $hash) {
            if (isset($this->existingIdsByHash[$hash])) {
                $existingHashes[$hash] = true;
                $idsByHash[$hash] = $this->existingIdsByHash[$hash];
            }
        }

        if (DB::getDriverName() === 'sqlite') {
            $this->bulkInsertCollectionsSqlite($rowsByCollectionKey);
        } else {
            $this->bulkInsertCollectionsMysql($rowsByCollectionKey, $existingHashes);
        }

        // Only resolve ids for the hashes we couldn't satisfy from the
        // prefetch cache (i.e. the freshly inserted rows). For chunks where
        // every collection already existed this issues zero extra SELECTs.
        $newHashes = array_values(array_diff(array_unique($hashes), array_keys($idsByHash)));
        if ($newHashes !== []) {
            foreach ($this->resolveIdsByHash($newHashes) as $hash => $id) {
                $idsByHash[$hash] = $id;
                $this->existingIdsByHash[$hash] = $id;
            }
        }

        foreach ($idsByHash as $hash => $id) {
            if (! isset($existingHashes[$hash])) {
                $this->insertedCollectionIds[$id] = true;
            }
        }

        return $idsByHash;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rowsByCollectionKey
     */
    private function bulkInsertCollectionsSqlite(array $rowsByCollectionKey): void
    {
        $rows = [];
        foreach ($rowsByCollectionKey as $row) {
            $rows[] = [
                'subject' => $row['subject'],
                'fromname' => $row['fromname'],
                'date' => date('Y-m-d H:i:s', (int) $row['unixtime']),
                'xref' => $row['xref'],
                'groups_id' => $row['groups_id'],
                'totalfiles' => $row['totalfiles'],
                'collectionhash' => $row['collectionhash'],
                'collection_regexes_id' => $row['collection_regexes_id'],
                'dateadded' => now(),
                'noise' => $row['noise'],
            ];
        }

        foreach (array_chunk($rows, self::MAX_SQL_ROWS_PER_STATEMENT) as $chunk) {
            DB::table('collections')->insertOrIgnore($chunk);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $rowsByCollectionKey
     * @param  array<string, true>  $existingHashes
     */
    private function bulkInsertCollectionsMysql(array $rowsByCollectionKey, array $existingHashes): void
    {
        foreach (array_chunk(array_values($rowsByCollectionKey), self::MAX_SQL_ROWS_PER_STATEMENT) as $chunk) {
            $placeholders = [];
            $bindings = [];
            foreach ($chunk as $row) {
                $placeholders[] = '(?, ?, FROM_UNIXTIME(?), ?, ?, ?, ?, ?, NOW(), ?)';
                array_push(
                    $bindings,
                    $row['subject'],
                    $row['fromname'],
                    $row['unixtime'],
                    $row['xref'],
                    $row['groups_id'],
                    $row['totalfiles'],
                    $row['collectionhash'],
                    $row['collection_regexes_id'],
                    $row['noise']
                );
            }

            // ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id) is the standard
            // "insert or do nothing" idiom that avoids re-writing existing
            // rows (and the redo/binlog churn that comes with it) while still
            // letting LAST_INSERT_ID() return the existing row's id.
            DB::statement(
                'INSERT INTO collections (subject, fromname, date, xref, groups_id, totalfiles, collectionhash, collection_regexes_id, dateadded, noise) VALUES '
                .implode(',', $placeholders)
                .' ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)',
                $bindings
            );
        }

        $this->batchAppendXrefs($rowsByCollectionKey, $existingHashes);
    }

    /**
     * Append xref tokens for every existing collection in a chunk in a single
     * UPDATE...JOIN per sub-chunk instead of N standalone UPDATEs (one per row).
     * Same UNION ALL shape as BinaryHandler::flushUpdatesMysql().
     *
     * @param  array<string, array<string, mixed>>  $rowsByCollectionKey
     * @param  array<string, true>  $existingHashes
     */
    private function batchAppendXrefs(array $rowsByCollectionKey, array $existingHashes): void
    {
        $updates = [];
        foreach ($rowsByCollectionKey as $row) {
            if (($row['xref_append'] ?? '') !== '' && isset($existingHashes[$row['collectionhash']])) {
                $updates[] = [
                    'collectionhash' => $row['collectionhash'],
                    'xref_append' => $row['xref_append'],
                ];
            }
        }

        if ($updates === []) {
            return;
        }

        foreach (array_chunk($updates, self::MAX_SQL_ROWS_PER_STATEMENT) as $chunk) {
            $selects = [];
            $bindings = [];
            foreach ($chunk as $u) {
                $selects[] = 'SELECT ? AS collectionhash, ? AS xref_append';
                $bindings[] = $u['collectionhash'];
                $bindings[] = $u['xref_append'];
            }

            $sql = 'UPDATE collections c INNER JOIN ('
                .implode(' UNION ALL ', $selects)
                .') u ON u.collectionhash = c.collectionhash '
                .'SET c.xref = CONCAT(c.xref, ?, u.xref_append)';
            $bindings[] = "\n";

            DB::statement($sql, $bindings);
        }
    }

    /**
     * @param  list<string>  $hashes
     * @return array<string, int>
     */
    private function resolveIdsByHash(array $hashes): array
    {
        if ($hashes === []) {
            return [];
        }

        $resolved = [];
        foreach (array_chunk($hashes, self::MAX_SQL_ROWS_PER_STATEMENT) as $chunk) {
            $resolved += Collection::query()
                ->whereIn('collectionhash', $chunk)
                ->pluck('id', 'collectionhash')
                ->mapWithKeys(static fn (int|string $id, string $hash): array => [$hash => (int) $id])
                ->all();
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $headerTokens
     */
    private function insertOrGetCollection(
        string $driver,
        string $subject,
        string $fromName,
        int $unixtime,
        array $headerTokens,
        string $finalXrefAppend,
        int $groupId,
        int $totalFiles,
        string $collectionHash,
        int $regexId,
        string $batchNoise
    ): int {
        if ($driver === 'sqlite') {
            return $this->insertCollectionSqlite(
                $subject,
                $fromName,
                $unixtime,
                $headerTokens,
                $groupId,
                $totalFiles,
                $collectionHash,
                $regexId,
                $batchNoise
            );
        }

        return $this->insertCollectionMysql(
            $subject,
            $fromName,
            $unixtime,
            $headerTokens,
            $finalXrefAppend,
            $groupId,
            $totalFiles,
            $collectionHash,
            $regexId,
            $batchNoise
        );
    }

    /**
     * @param  array<string, mixed>  $headerTokens
     */
    private function insertCollectionSqlite(
        string $subject,
        string $fromName,
        int $unixtime,
        array $headerTokens,
        int $groupId,
        int $totalFiles,
        string $collectionHash,
        int $regexId,
        string $batchNoise
    ): int {
        $affected = DB::table('collections')->insertOrIgnore([
            'subject' => $subject,
            'fromname' => $fromName,
            'date' => date('Y-m-d H:i:s', $unixtime),
            'xref' => implode(' ', $headerTokens),
            'groups_id' => $groupId,
            'totalfiles' => $totalFiles,
            'collectionhash' => $collectionHash,
            'collection_regexes_id' => $regexId,
            'dateadded' => now(),
            'noise' => $batchNoise,
        ]);

        if ($affected > 0 && ($lastId = (int) DB::connection()->getPdo()->lastInsertId()) > 0) {
            $this->insertedCollectionIds[$lastId] = true;

            return $lastId;
        }

        return (int) (Collection::whereCollectionhash($collectionHash)->value('id') ?? 0);
    }

    /**
     * @param  array<string, mixed>  $headerTokens
     */
    private function insertCollectionMysql(
        string $subject,
        string $fromName,
        int $unixtime,
        array $headerTokens,
        string $finalXrefAppend,
        int $groupId,
        int $totalFiles,
        string $collectionHash,
        int $regexId,
        string $batchNoise
    ): int {
        // ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id) lets LAST_INSERT_ID()
        // return the existing row's id without rewriting the row (avoids the
        // redo/binlog churn of `dateadded = NOW()`).
        $insertSql = 'INSERT INTO collections '
            .'(subject, fromname, date, xref, groups_id, totalfiles, collectionhash, collection_regexes_id, dateadded, noise) '
            .'VALUES (?, ?, FROM_UNIXTIME(?), ?, ?, ?, ?, ?, NOW(), ?) '
            .'ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)';

        $bindings = [
            $subject,
            $fromName,
            $unixtime,
            implode(' ', $headerTokens),
            $groupId,
            $totalFiles,
            $collectionHash,
            $regexId,
            $batchNoise,
        ];

        if ($finalXrefAppend !== '') {
            $insertSql .= ', xref = CONCAT(xref, "\\n", ?)';
            $bindings[] = $finalXrefAppend;
        }

        // affectingStatement so we can distinguish a brand-new insert
        // (rowCount = 1) from a duplicate-key hit (rowCount = 0 with no xref
        // append, or 2 when xref was actually appended). LAST_INSERT_ID(id) in
        // the ODKU clause makes lastInsertId() return the existing row id
        // even on a duplicate, so we can't rely on lastInsertId() alone.
        $affected = (int) DB::affectingStatement($insertSql, $bindings);
        $lastId = (int) DB::connection()->getPdo()->lastInsertId();

        if ($lastId > 0) {
            if ($affected === 1) {
                $this->insertedCollectionIds[$lastId] = true;
            }

            return $lastId;
        }

        return (int) (Collection::whereCollectionhash($collectionHash)->value('id') ?? 0);
    }

    /**
     * Get IDs created in this batch.
     *
     * @return list<int>
     */
    public function getInsertedIds(): array
    {
        return array_keys($this->insertedCollectionIds);
    }

    /**
     * Get all collection IDs processed this batch.
     *
     * @return list<int>
     */
    public function getAllIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->collectionIds)));
    }

    /**
     * Get all collection hashes processed this batch.
     *
     * @return list<string>
     */
    public function getBatchHashes(): array
    {
        return array_keys($this->batchCollectionHashes);
    }
}
