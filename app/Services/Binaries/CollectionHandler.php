<?php

declare(strict_types=1);

namespace App\Services\Binaries;

use App\Enums\CollectionFileCheckStatus;
use App\Models\Collection;
use App\Services\CollectionsCleaningService;
use App\Services\XrefService;
use App\Support\SqlError;
use App\Support\Utf8;
use Illuminate\Support\Facades\DB;

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
    private int $sqlChunkSize;

    private CollectionsCleaningService $collectionsCleaning;

    private XrefService $xrefService;

    /** @var array<string, int> Cached collection IDs by key */
    private array $collectionIds = [];

    /** @var array<int, true> IDs of collections created in this batch */
    private array $insertedCollectionIds = [];

    /** @var array<string, true> Collection hashes touched in this batch */
    private array $batchCollectionHashes = [];

    /** @var array<string, int> Cached collection IDs by collectionhash (populated by bulk prefetch) */
    private array $existingIdsByHash = [];

    private ?\Throwable $lastException = null;

    public function __construct(
        ?CollectionsCleaningService $collectionsCleaning = null,
        ?XrefService $xrefService = null,
        int $sqlChunkSize = 500,
    ) {
        $this->collectionsCleaning = $collectionsCleaning ?? new CollectionsCleaningService;
        $this->xrefService = $xrefService ?? new XrefService;
        $this->sqlChunkSize = max(1, min(1000, $sqlChunkSize));
    }

    /**
     * Reset state for a new batch.
     */
    public function reset(): void
    {
        $this->collectionIds = [];
        $this->insertedCollectionIds = [];
        $this->batchCollectionHashes = [];
        $this->existingIdsByHash = [];
        $this->lastException = null;
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

        $collectionHash = sha1($collectionKey, true);
        $this->batchCollectionHashes[$collectionHash] = true;

        $headerDate = is_numeric($header['Date']) ? (int) $header['Date'] : strtotime($header['Date']);
        $now = now()->timestamp;
        $unixtime = min($headerDate, $now) ?: $now;

        $headerTokens = $this->xrefService->extractTokens($header['Xref'] ?? '');

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
                $groupId,
                $totalFiles,
                $collectionHash,
                $collMatch['id'],
                $batchNoise
            );

            if ($collectionId > 0) {
                $this->collectionIds[$collectionKey] = $collectionId;
                $this->insertCollectionGroups([
                    $collectionKey => array_fill_keys(
                        $this->xrefService->extractGroupNames($header['Xref'] ?? '') ?: [$groupName],
                        true
                    ),
                ]);

                return $collectionId;
            }
        } catch (\Throwable $e) {
            $this->lastException = $e;
            if (config('app.debug') === true) {
                SqlError::logFailure('Collection insert failed', $e);
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
        $hashesToPrefetch = [];
        $groupNamesByCollectionKey = [];

        foreach ($headers as $index => $header) {
            $totalFiles = (int) ($totalFilesByIndex[$index] ?? 0);
            $collMatch = $this->collectionsCleaning->collectionsCleaner(
                $header['matches'][1],
                $groupName
            );

            $collectionKey = $collMatch['name'].$totalFiles;
            $groupNames = $this->xrefService->extractGroupNames($header['Xref'] ?? '');
            if ($groupNames === []) {
                $groupNames = [$groupName];
            }
            foreach ($groupNames as $xrefGroupName) {
                $groupNamesByCollectionKey[$collectionKey][$xrefGroupName] = true;
            }

            if (isset($this->collectionIds[$collectionKey])) {
                $resolved[$index] = $this->collectionIds[$collectionKey];

                continue;
            }

            $indexByCollectionKey[$collectionKey][] = $index;
            if (isset($pending[$collectionKey])) {
                continue;
            }

            $collectionHash = sha1($collectionKey, true);
            $this->batchCollectionHashes[$collectionHash] = true;

            $hashesToPrefetch[$collectionKey] = $collectionHash;

            $headerDate = is_numeric($header['Date']) ? (int) $header['Date'] : strtotime($header['Date']);
            $now = now()->timestamp;
            $unixtime = min($headerDate, $now) ?: $now;
            $headerTokens = $this->xrefService->extractTokens($header['Xref'] ?? '');

            $pending[$collectionKey] = [
                'subject' => substr(Utf8::clean($header['matches'][1]), 0, 255),
                'fromname' => Utf8::clean($header['From']),
                'unixtime' => $unixtime,
                'xref' => implode(' ', $headerTokens),
                'groups_id' => $groupId,
                'totalfiles' => $totalFiles,
                'collectionhash' => $collectionHash,
                'collection_regexes_id' => (int) $collMatch['id'],
                'noise' => $batchNoise,
            ];
        }

        if ($pending === []) {
            $this->insertCollectionGroups($groupNamesByCollectionKey);

            return $resolved;
        }

        $this->prefetchExistingCollections($hashesToPrefetch);

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
            $this->insertCollectionGroups($groupNamesByCollectionKey);
        } catch (\Throwable $e) {
            $this->lastException = $e;
            if (config('app.debug') === true) {
                SqlError::logFailure('Bulk collection insert failed', $e);
            }
        }

        return $resolved;
    }

    /**
     * Prefetch existing collections in one round-trip. Populates both
     * $existingIdsByHash so the
     * subsequent bulkInsertAndResolve() can skip its existence-check SELECT
     * and only re-query for the freshly inserted rows.
     *
     * @param  array<string, string>  $collectionHashByKey
     */
    private function prefetchExistingCollections(array $collectionHashByKey): void
    {
        $missing = array_filter(
            $collectionHashByKey,
            fn (string $hash): bool => ! isset($this->existingIdsByHash[$hash]),
            ARRAY_FILTER_USE_BOTH
        );

        if ($missing === []) {
            return;
        }

        $rows = [];
        foreach (array_chunk(array_values($missing), $this->sqlChunkSize) as $chunk) {
            $placeholders = implode(',', array_fill(0, \count($chunk), '?'));
            $existingRows = DB::select(
                "SELECT id, collectionhash FROM collections WHERE collectionhash IN ({$placeholders})",
                $chunk
            );
            foreach ($existingRows as $row) {
                $rows[(string) $row->collectionhash] = [
                    'id' => (int) $row->id,
                ];
            }
        }

        foreach ($missing as $collectionKey => $hash) {
            if (isset($rows[$hash])) {
                $this->existingIdsByHash[$hash] = $rows[$hash]['id'];
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
            $this->bulkInsertCollectionsMysql($rowsByCollectionKey);
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

        foreach (array_chunk($rows, $this->sqlChunkSize) as $chunk) {
            DB::table('collections')->insertOrIgnore($chunk);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $rowsByCollectionKey
     */
    private function bulkInsertCollectionsMysql(array $rowsByCollectionKey): void
    {
        foreach (array_chunk(array_values($rowsByCollectionKey), $this->sqlChunkSize) as $chunk) {
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

        // Cross-post groups are normalized in collection_groups. Avoid
        // rewriting the wide collections row for every new article number.
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
        foreach (array_chunk($hashes, $this->sqlChunkSize) as $chunk) {
            $placeholders = implode(',', array_fill(0, \count($chunk), '?'));
            foreach (DB::select(
                "SELECT id, collectionhash FROM collections WHERE collectionhash IN ({$placeholders})",
                $chunk
            ) as $row) {
                $resolved[(string) $row->collectionhash] = (int) $row->id;
            }
        }

        return $resolved;
    }

    /**
     * @param  array<string, array<string, true>>  $groupNamesByCollectionKey
     */
    private function insertCollectionGroups(array $groupNamesByCollectionKey): void
    {
        $rows = [];
        foreach ($groupNamesByCollectionKey as $collectionKey => $groupNames) {
            $collectionId = $this->collectionIds[$collectionKey] ?? null;
            if ($collectionId === null) {
                continue;
            }
            foreach (array_keys($groupNames) as $groupName) {
                $rows[] = ['collections_id' => $collectionId, 'group_name' => $groupName];
            }
        }

        foreach (array_chunk($rows, $this->sqlChunkSize) as $chunk) {
            if (DB::getDriverName() === 'sqlite') {
                DB::table('collection_groups')->insertOrIgnore($chunk);

                continue;
            }

            $values = [];
            $bindings = [];
            foreach ($chunk as $row) {
                $values[] = '(?, ?)';
                $bindings[] = $row['collections_id'];
                $bindings[] = $row['group_name'];
            }
            DB::statement(
                'INSERT IGNORE INTO collection_groups (collections_id, group_name) VALUES '.implode(',', $values),
                $bindings
            );
        }
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

        // affectingStatement distinguishes a brand-new insert (rowCount = 1)
        // from a duplicate-key hit (rowCount = 0). LAST_INSERT_ID(id) in
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
     * Refresh size and readiness for only the collections touched by a header
     * chunk. Binary and part aggregates have already been refreshed, so this
     * replaces broad table-wide completion scans on the normal ingestion path.
     *
     * @param  list<int>  $collectionIds
     */
    public function refreshAggregates(array $collectionIds, int $chunkSize = 500): bool
    {
        $collectionIds = array_values(array_unique(array_map('intval', $collectionIds)));
        if ($collectionIds === []) {
            return true;
        }

        $inProgressStatuses = [
            CollectionFileCheckStatus::Default->value,
            CollectionFileCheckStatus::CompleteCollection->value,
            CollectionFileCheckStatus::CompleteParts->value,
            CollectionFileCheckStatus::TempComplete->value,
            CollectionFileCheckStatus::ZeroPart->value,
        ];

        try {
            $chunkSize = max(1, min($chunkSize, $this->sqlChunkSize));
            if (DB::getDriverName() === 'sqlite') {
                foreach ($collectionIds as $collectionId) {
                    $aggregate = DB::selectOne(
                        'SELECT COUNT(*) AS currentfiles,
                                COALESCE(SUM(CASE WHEN partcheck = 1 THEN 1 ELSE 0 END), 0) AS completefiles,
                                COALESCE(SUM(partsize), 0) AS filesize
                         FROM binaries WHERE collections_id = ?',
                        [$collectionId]
                    );
                    $collection = DB::selectOne(
                        'SELECT totalfiles, filecheck FROM collections WHERE id = ?',
                        [$collectionId]
                    );
                    if ($collection === null) {
                        continue;
                    }

                    $currentFiles = (int) $aggregate->currentfiles;
                    $totalFiles = (int) $collection->totalfiles;
                    $ready = \in_array((int) $collection->filecheck, $inProgressStatuses, true)
                        && $totalFiles > 0
                        && ($currentFiles === $totalFiles || $currentFiles === $totalFiles + 1)
                        && (int) $aggregate->completefiles >= $totalFiles;

                    DB::update(
                        'UPDATE collections SET filesize = ?, last_seen_at = ?, filecheck = ? WHERE id = ?',
                        [
                            (int) $aggregate->filesize,
                            now()->format('Y-m-d H:i:s'),
                            $ready ? CollectionFileCheckStatus::Sized->value : (int) $collection->filecheck,
                            $collectionId,
                        ]
                    );
                }

                return true;
            }

            foreach (array_chunk($collectionIds, $chunkSize) as $chunk) {
                $idPlaceholders = implode(',', array_fill(0, \count($chunk), '?'));
                $statusPlaceholders = implode(',', array_fill(0, \count($inProgressStatuses), '?'));
                DB::update(
                    "UPDATE collections c
                     INNER JOIN (
                         SELECT b.collections_id,
                                COUNT(*) AS currentfiles,
                                SUM(CASE WHEN b.partcheck = 1 THEN 1 ELSE 0 END) AS completefiles,
                                COALESCE(SUM(b.partsize), 0) AS filesize
                         FROM binaries b
                         WHERE b.collections_id IN ({$idPlaceholders})
                         GROUP BY b.collections_id
                     ) a ON a.collections_id = c.id
                     SET c.filesize = a.filesize,
                         c.last_seen_at = NOW(),
                         c.filecheck = CASE
                             WHEN c.filecheck IN ({$statusPlaceholders})
                              AND c.totalfiles > 0
                              AND a.currentfiles IN (c.totalfiles, c.totalfiles + 1)
                              AND a.completefiles >= c.totalfiles
                             THEN ? ELSE c.filecheck END",
                    [...$chunk, ...$inProgressStatuses, CollectionFileCheckStatus::Sized->value]
                );
            }

            return true;
        } catch (\Throwable $e) {
            $this->lastException = $e;
            if (config('app.debug') === true) {
                SqlError::logFailure('Collection aggregate refresh failed', $e);
            }

            return false;
        }
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

    public function getLastException(): ?\Throwable
    {
        return $this->lastException;
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
