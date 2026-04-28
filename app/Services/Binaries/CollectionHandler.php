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

        $this->prefetchExistingXrefs($xrefsToPrefetch);
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
     * @param  array<string, string>  $collectionHashByKey
     */
    private function prefetchExistingXrefs(array $collectionHashByKey): void
    {
        $missing = array_filter(
            $collectionHashByKey,
            fn (string $hash, string $collectionKey): bool => ! array_key_exists($collectionKey, $this->existingXrefs),
            ARRAY_FILTER_USE_BOTH
        );

        if ($missing === []) {
            return;
        }

        $rows = Collection::query()
            ->whereIn('collectionhash', array_values($missing))
            ->pluck('xref', 'collectionhash')
            ->all();

        foreach ($missing as $collectionKey => $hash) {
            $this->existingXrefs[$collectionKey] = isset($rows[$hash]) ? (string) $rows[$hash] : null;
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $rowsByCollectionKey
     * @return array<string, int> Collection ids keyed by collectionhash
     */
    private function bulkInsertAndResolve(array $rowsByCollectionKey): array
    {
        $hashes = array_values(array_column($rowsByCollectionKey, 'collectionhash'));
        $existingHashes = $this->existingHashes($hashes);

        if (DB::getDriverName() === 'sqlite') {
            $this->bulkInsertCollectionsSqlite($rowsByCollectionKey);
        } else {
            $this->bulkInsertCollectionsMysql($rowsByCollectionKey, $existingHashes);
        }

        $idsByHash = $this->resolveIdsByHash($hashes);
        foreach ($idsByHash as $hash => $id) {
            if (! isset($existingHashes[$hash])) {
                $this->insertedCollectionIds[$id] = true;
            }
        }

        return $idsByHash;
    }

    /**
     * @param  list<string>  $hashes
     * @return array<string, true>
     */
    private function existingHashes(array $hashes): array
    {
        if ($hashes === []) {
            return [];
        }

        return Collection::query()
            ->whereIn('collectionhash', $hashes)
            ->pluck('collectionhash')
            ->mapWithKeys(static fn (string $hash): array => [$hash => true])
            ->all();
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

        DB::table('collections')->insertOrIgnore($rows);
    }

    /**
     * @param  array<string, array<string, mixed>>  $rowsByCollectionKey
     * @param  array<string, true>  $existingHashes
     */
    private function bulkInsertCollectionsMysql(array $rowsByCollectionKey, array $existingHashes): void
    {
        $placeholders = [];
        $bindings = [];
        foreach ($rowsByCollectionKey as $row) {
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

        DB::statement(
            'INSERT INTO collections (subject, fromname, date, xref, groups_id, totalfiles, collectionhash, collection_regexes_id, dateadded, noise) VALUES '
            .implode(',', $placeholders)
            .' ON DUPLICATE KEY UPDATE dateadded = NOW()',
            $bindings
        );

        foreach ($rowsByCollectionKey as $row) {
            if ($row['xref_append'] !== '' && isset($existingHashes[$row['collectionhash']])) {
                DB::update('UPDATE collections SET xref = CONCAT(xref, ?, ?) WHERE collectionhash = ?', [
                    "\n",
                    $row['xref_append'],
                    $row['collectionhash'],
                ]);
            }
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

        return Collection::query()
            ->whereIn('collectionhash', $hashes)
            ->pluck('id', 'collectionhash')
            ->mapWithKeys(static fn (int|string $id, string $hash): array => [$hash => (int) $id])
            ->all();
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
        $insertSql = 'INSERT INTO collections '
            .'(subject, fromname, date, xref, groups_id, totalfiles, collectionhash, collection_regexes_id, dateadded, noise) '
            .'VALUES (?, ?, FROM_UNIXTIME(?), ?, ?, ?, ?, ?, NOW(), ?) '
            .'ON DUPLICATE KEY UPDATE dateadded = NOW()';

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

        DB::statement($insertSql, $bindings);

        $lastId = (int) DB::connection()->getPdo()->lastInsertId();
        if ($lastId > 0) {
            $this->insertedCollectionIds[$lastId] = true;

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
