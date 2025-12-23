<?php

declare(strict_types=1);

namespace App\Services\Binaries;

use App\Models\Collection;
use App\Services\CollectionsCleaningService;
use App\Services\XrefService;
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
    }

    /**
     * Get or create a collection for the given header.
     *
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

        $existingXref = Collection::whereCollectionhash($collectionHash)->value('xref');
        $headerTokens = $this->xrefService->extractTokens($header['Xref'] ?? '');
        $newTokens = $this->xrefService->diffNewTokens($existingXref, $header['Xref'] ?? '');
        $finalXrefAppend = implode(' ', $newTokens);

        $subject = substr(mb_convert_encoding($header['matches'][1], 'UTF-8', mb_list_encodings()), 0, 255);
        $fromName = mb_convert_encoding($header['From'], 'UTF-8', mb_list_encodings());

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
        DB::statement(
            'INSERT OR IGNORE INTO collections (subject, fromname, date, xref, groups_id, totalfiles, collectionhash, collection_regexes_id, dateadded, noise) VALUES (?, ?, datetime(?, "unixepoch"), ?, ?, ?, ?, datetime("now"), ?)',
            [
                $subject,
                $fromName,
                $unixtime,
                implode(' ', $headerTokens),
                $groupId,
                $totalFiles,
                $collectionHash,
                $regexId,
                $batchNoise,
            ]
        );

        $lastId = (int) DB::connection()->getPdo()->lastInsertId();
        if ($lastId > 0) {
            $this->insertedCollectionIds[$lastId] = true;

            return $lastId;
        }

        return (int) (Collection::whereCollectionhash($collectionHash)->value('id') ?? 0);
    }

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
     */
    public function getInsertedIds(): array
    {
        return array_keys($this->insertedCollectionIds);
    }

    /**
     * Get all collection IDs processed this batch.
     */
    public function getAllIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->collectionIds)));
    }

    /**
     * Get all collection hashes processed this batch.
     */
    public function getBatchHashes(): array
    {
        return array_keys($this->batchCollectionHashes);
    }
}

