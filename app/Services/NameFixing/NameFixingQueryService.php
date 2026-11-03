<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

use App\Models\Category;
use App\Services\Crc\CrcEvidence;
use App\Services\Search\Contracts\SearchServiceInterface;
use App\Services\Search\DTO\NameFixingSearchQuery;
use App\Services\Search\DTO\NameFixingSearchResult;
use Carbon\CarbonImmutable;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class NameFixingQueryService
{
    public const SOURCE_NFO = 'nfo';

    public const SOURCE_FILES = 'files';

    public const SOURCE_SRR = 'srr';

    public const SOURCE_CRC = 'crc';

    public const SOURCE_UID = 'uid';

    public const SOURCE_HASH = 'hash';

    public const SOURCE_PAR2 = 'par2';

    public const SOURCE_XXX = 'xxx';

    public const SOURCE_MEDIA_MOVIE = 'media_movie';

    public const BATCH_SIZE = 1000;

    private ConnectionInterface $database;

    private ?SearchServiceInterface $searchService;

    /** @var array<int, array<string, mixed>> */
    private array $indexedDocuments = [];

    /** @var list<string> */
    private const INDEXED_CANDIDATE_SOURCES = [
        self::SOURCE_NFO,
        self::SOURCE_FILES,
        self::SOURCE_SRR,
        self::SOURCE_UID,
        self::SOURCE_XXX,
        self::SOURCE_MEDIA_MOVIE,
    ];

    /**
     * @var array<string, string>
     */
    private const STATUS_COLUMNS = [
        self::SOURCE_NFO => 'proc_nfo',
        self::SOURCE_FILES => 'proc_files',
        self::SOURCE_SRR => 'proc_srr',
        self::SOURCE_CRC => 'proc_crc32',
        self::SOURCE_UID => 'proc_uid',
        self::SOURCE_HASH => 'proc_hash16k',
        self::SOURCE_PAR2 => 'proc_par2',
        self::SOURCE_XXX => 'proc_files',
        self::SOURCE_MEDIA_MOVIE => 'proc_uid',
    ];

    /**
     * @var array<string, string>
     */
    private const SOURCE_EXISTS = [
        self::SOURCE_NFO => 'EXISTS (SELECT 1 FROM release_nfos source_nfo WHERE source_nfo.releases_id = r.id)',
        self::SOURCE_FILES => 'EXISTS (SELECT 1 FROM release_files source_file WHERE source_file.releases_id = r.id)',
        self::SOURCE_SRR => "EXISTS (SELECT 1 FROM release_files source_srr WHERE source_srr.releases_id = r.id AND (source_srr.name LIKE '%.srr' OR source_srr.name LIKE '%.srs'))",
        self::SOURCE_CRC => "EXISTS (SELECT 1 FROM release_files source_crc WHERE source_crc.releases_id = r.id AND source_crc.crc32 IS NOT NULL AND source_crc.crc32 != '')",
        self::SOURCE_UID => "EXISTS (SELECT 1 FROM media_infos source_media WHERE source_media.releases_id = r.id AND source_media.unique_id IS NOT NULL AND source_media.unique_id != '')",
        self::SOURCE_HASH => "EXISTS (SELECT 1 FROM par_hashes source_hash WHERE source_hash.releases_id = r.id AND source_hash.hash != '')",
        self::SOURCE_PAR2 => '1 = 1',
        self::SOURCE_XXX => "r.searchname LIKE 'SDX264XXX-%' OR EXISTS (SELECT 1 FROM release_files source_xxx WHERE source_xxx.releases_id = r.id AND (source_xxx.name LIKE '%SDPORN%' OR source_xxx.name LIKE '%SDX264XXX-%'))",
        self::SOURCE_MEDIA_MOVIE => "EXISTS (SELECT 1 FROM media_infos source_movie WHERE source_movie.releases_id = r.id AND source_movie.movie_name IS NOT NULL AND source_movie.movie_name != '')",
    ];

    public function __construct(
        ?ConnectionInterface $database = null,
        ?SearchServiceInterface $searchService = null
    ) {
        $this->database = $database ?? DB::connection();
        $this->searchService = $searchService;
    }

    /**
     * @return list<object>
     */
    public function candidateBatch(
        string $source,
        int $time,
        int $categories,
        int $afterId = 0,
        int $limit = self::BATCH_SIZE
    ): array {
        if (in_array($source, self::INDEXED_CANDIDATE_SOURCES, true)) {
            $indexed = $this->indexedCandidateBatch($source, $time, $categories, $afterId, $limit);
            if ($indexed !== null) {
                return $indexed;
            }
        }

        return $this->databaseCandidateBatch($source, $time, $categories, $afterId, $limit);
    }

    /**
     * Bypass the search index when a run detects that indexed candidates were incomplete.
     *
     * @return list<object>
     */
    public function fallbackCandidateBatch(
        string $source,
        int $time,
        int $categories,
        int $afterId = 0,
        int $limit = self::BATCH_SIZE
    ): array {
        return $this->databaseCandidateBatch($source, $time, $categories, $afterId, $limit);
    }

    /**
     * @return list<object>
     */
    private function databaseCandidateBatch(
        string $source,
        int $time,
        int $categories,
        int $afterId,
        int $limit
    ): array {
        [$where, $bindings] = $this->candidateWhere($source, $time, $categories);
        $bindings[] = $afterId;
        $bindings[] = max(1, $limit);

        return $this->database->select(
            "SELECT r.id AS releases_id, r.id, r.name, r.searchname, r.fromname, r.groups_id,
                    r.categories_id, r.size AS relsize, r.guid, r.predb_id, r.nfostatus,
                    r.proc_nfo, r.proc_files, r.proc_par2, r.proc_uid, r.proc_srr,
                    r.proc_hash16k, r.proc_crc32
             FROM releases r
             WHERE {$where}
             AND r.id > ?
             ORDER BY r.id ASC
             LIMIT ?",
            $bindings
        );
    }

    /**
     * @return list<object>|null
     */
    private function indexedCandidateBatch(
        string $source,
        int $time,
        int $categories,
        int $afterId,
        int $limit
    ): ?array {
        $searchService = $this->searchService();
        if ($searchService === null) {
            return null;
        }

        try {
            $result = $searchService->searchNameFixingCandidates(new NameFixingSearchQuery(
                source: $source,
                afterId: $afterId,
                limit: min(10000, max($limit, $limit * 4)),
                categoryIds: $this->candidateCategoryIds($source, $categories),
                addedAfter: $time === 1 ? CarbonImmutable::now()->subHours(6)->getTimestamp() : 0,
            ));
        } catch (Throwable) {
            return null;
        }

        if (! $result->available || $result->ids === []) {
            return null;
        }

        $this->rememberIndexedDocuments($result);
        $confirmed = $this->candidateRowsForIds(
            $source,
            $time,
            $categories,
            $afterId,
            $limit,
            $result->ids
        );

        if (count($confirmed) >= max(1, $limit)) {
            return $confirmed;
        }

        $fallback = $this->databaseCandidateBatch($source, $time, $categories, $afterId, max(1, $limit));

        return $this->mergeReleaseRows($confirmed, $fallback, max(1, $limit));
    }

    /**
     * @param  list<int>  $releaseIds
     * @return list<object>
     */
    private function candidateRowsForIds(
        string $source,
        int $time,
        int $categories,
        int $afterId,
        int $limit,
        array $releaseIds
    ): array {
        if ($releaseIds === []) {
            return [];
        }

        [$where, $bindings] = $this->candidateWhere($source, $time, $categories);
        $bindings = array_merge($bindings, $releaseIds, [$afterId, max(1, $limit)]);

        return $this->database->select(
            "SELECT r.id AS releases_id, r.id, r.name, r.searchname, r.fromname, r.groups_id,
                    r.categories_id, r.size AS relsize, r.guid, r.predb_id, r.nfostatus,
                    r.proc_nfo, r.proc_files, r.proc_par2, r.proc_uid, r.proc_srr,
                    r.proc_hash16k, r.proc_crc32
             FROM releases r
             WHERE {$where}
             AND r.id IN (".$this->placeholders(count($releaseIds)).')
             AND r.id > ?
             ORDER BY r.id ASC
             LIMIT ?',
            $bindings
        );
    }

    public function countCandidates(string $source, int $time, int $categories): int
    {
        [$where, $bindings] = $this->candidateWhere($source, $time, $categories);
        $rows = $this->database->select(
            "SELECT COUNT(*) AS aggregate FROM releases r WHERE {$where}",
            $bindings
        );

        return (int) ($rows[0]->aggregate ?? 0);
    }

    /**
     * @return list<object>
     */
    public function standardCandidateBatch(string $leftGuid, int $limit): array
    {
        return $this->database->select(
            'SELECT r.id AS releases_id, r.id, r.name, r.searchname, r.fromname, r.guid,
                    r.groups_id, r.categories_id, r.size AS relsize, r.predb_id, r.nfostatus,
                    r.proc_nfo, r.proc_uid, r.proc_files, r.proc_par2, r.proc_hash16k,
                    r.proc_srr, r.proc_crc32
             FROM releases r
             WHERE r.leftguid = ?
             AND r.isrenamed = 0
             AND r.predb_id = 0
             AND r.passwordstatus >= 0
             AND r.nfostatus > -1
             AND (
                (r.nfostatus = 1 AND r.proc_nfo = 0)
                OR r.proc_files = 0
                OR r.proc_uid = 0
                OR r.proc_par2 = 0
                OR r.proc_srr = 0
                OR r.proc_hash16k = 0
                OR r.proc_crc32 = 0
             )
             AND r.categories_id IN ('.implode(',', Category::OTHERS_GROUP).')
             ORDER BY r.id DESC
             LIMIT ?',
            [$leftGuid, max(1, $limit)]
        );
    }

    /**
     * @param  list<int>  $releaseIds
     * @return list<object>
     */
    public function nfoRows(array $releaseIds): array
    {
        return $this->selectForReleaseIds(
            'SELECT rn.releases_id, UNCOMPRESS(rn.nfo) AS textstring
             FROM release_nfos rn
             WHERE rn.releases_id IN (%s)
             ORDER BY rn.releases_id',
            $releaseIds
        );
    }

    /**
     * @param  list<int>  $releaseIds
     * @return list<object>
     */
    public function fileRows(array $releaseIds, string $source = self::SOURCE_FILES): array
    {
        $filter = match ($source) {
            self::SOURCE_FILES => '',
            self::SOURCE_SRR => " AND (rf.name LIKE '%.srr' OR rf.name LIKE '%.srs')",
            self::SOURCE_CRC => " AND rf.crc32 IS NOT NULL AND rf.crc32 != ''",
            self::SOURCE_XXX => " AND (rf.name LIKE '%SDPORN%' OR rf.name LIKE '%SDX264XXX-%')",
            default => throw new InvalidArgumentException("Unsupported release-file source [{$source}]."),
        };

        return $this->selectForReleaseIds(
            'SELECT rf.releases_id, rf.name AS textstring, rf.name AS filename, rf.crc32, rf.size AS filesize
             FROM release_files rf
             WHERE rf.releases_id IN (%s)'.$filter.'
             ORDER BY rf.releases_id, rf.name',
            $releaseIds
        );
    }

    /**
     * @param  list<int>  $releaseIds
     * @return list<object>
     */
    public function mediaRows(array $releaseIds): array
    {
        if ($releaseIds === []) {
            return [];
        }

        $indexedRows = $this->indexedMediaRows($releaseIds);
        $indexedReleaseIds = array_map(
            static fn (object $row): int => (int) $row->releases_id,
            $indexedRows
        );
        $missingReleaseIds = array_values(array_diff($releaseIds, $indexedReleaseIds));
        if ($missingReleaseIds === []) {
            return $indexedRows;
        }

        $databaseRows = $this->selectForReleaseIds(
            'SELECT mi.releases_id, mi.unique_id AS uid, mi.movie_name, mi.file_name
             FROM media_infos mi
             WHERE mi.releases_id IN (%s)
             ORDER BY mi.releases_id',
            $missingReleaseIds
        );

        return [...$indexedRows, ...$databaseRows];
    }

    /**
     * @param  list<int>  $releaseIds
     * @return list<object>
     */
    public function hashRows(array $releaseIds): array
    {
        return $this->selectForReleaseIds(
            'SELECT ph.releases_id, ph.hash
             FROM par_hashes ph
             WHERE ph.releases_id IN (%s)
             ORDER BY ph.releases_id, ph.hash',
            $releaseIds
        );
    }

    /**
     * @param  list<string>  $uniqueIds
     * @return array<string, list<object>>
     */
    public function uidDonors(array $uniqueIds): array
    {
        if ($uniqueIds === []) {
            return [];
        }

        $indexed = $this->indexedUidDonors($uniqueIds);
        if ($indexed !== null) {
            return $indexed;
        }

        return $this->databaseUidDonors($uniqueIds);
    }

    /**
     * @param  list<string>  $uniqueIds
     * @return array<string, list<object>>
     */
    private function databaseUidDonors(array $uniqueIds): array
    {
        if ($uniqueIds === []) {
            return [];
        }

        $placeholders = $this->placeholders(count($uniqueIds));
        $bindings = array_merge($uniqueIds, ['nonscene@Ef.net (EF)']);
        $rows = $this->database->select(
            "SELECT mi.unique_id AS match_key, r.id AS releases_id, r.size AS relsize,
                    r.searchname, r.fromname, r.predb_id
             FROM media_infos mi
             INNER JOIN releases r ON r.id = mi.releases_id
             WHERE mi.unique_id IN ({$placeholders})
             AND (r.predb_id > 0 OR r.anidbid > 0 OR r.fromname = ?)",
            $bindings
        );

        return $this->groupByKey($rows, 'match_key');
    }

    /**
     * @param  list<string>  $hashes
     * @return array<string, list<object>>
     */
    public function hashDonors(array $hashes): array
    {
        return $this->donors(
            'SELECT ph.hash AS match_key, r.id AS releases_id, r.size AS relsize,
                    r.searchname, r.fromname, r.predb_id
             FROM par_hashes ph
             INNER JOIN releases r ON r.id = ph.releases_id
             WHERE ph.hash IN (%s)
             AND (r.predb_id > 0 OR r.anidbid > 0)',
            $hashes
        );
    }

    /**
     * @param  list<string>  $crcs
     * @return array<string, list<object>>
     */
    public function crcDonors(array $crcs): array
    {
        return $this->crcRows(
            'SELECT rf.crc32 AS match_key, r.id AS releases_id, r.size AS relsize,
                    rf.crc32 AS crchash, rf.size AS filesize,
                    r.searchname, r.fromname, r.predb_id
             FROM release_files rf
             INNER JOIN releases r ON r.id = rf.releases_id
             WHERE rf.crc32 IN (%s)
             AND r.predb_id > 0',
            $crcs
        );
    }

    /**
     * Include legacy unpadded/lowercase hashes without losing index lookups.
     *
     * @param  list<string>  $crcs
     * @return array<string, list<object>>
     */
    private function crcRows(string $sql, array $crcs): array
    {
        $variants = [];
        foreach ($crcs as $crc) {
            $normalized = CrcEvidence::normalizeCrc($crc);
            if ($normalized === '') {
                continue;
            }

            $short = ltrim($normalized, '0');
            $short = $short === '' ? '0' : $short;
            for ($length = strlen($short); $length <= 8; $length++) {
                $variant = str_pad($short, $length, '0', STR_PAD_LEFT);
                $variants[$variant] = $variant;
                $variants[strtolower($variant)] = strtolower($variant);
            }
        }

        $grouped = [];
        foreach (array_chunk(array_values($variants), 500) as $values) {
            foreach ($this->database->select(sprintf($sql, $this->placeholders(count($values))), $values) as $row) {
                $key = CrcEvidence::normalizeCrc($row->match_key ?? null);
                if ($key !== '') {
                    $row->crchash = $key;
                    $grouped[$key][] = $row;
                }
            }
        }

        return $grouped;
    }

    /**
     * @return list<object>
     */
    public function predbBatch(int $worker, int $workers, int $limit): array
    {
        $workerCount = max(1, min(16, $workers));
        $workerSlot = max(1, min($workerCount, $worker)) - 1;

        return $this->database->select(
            'SELECT p.id AS predb_id, p.title, p.source, p.searched
             FROM predb p
             WHERE LENGTH(p.title) >= 15
             AND p.title NOT REGEXP \'["<> ]\'
             AND p.searched = 0
             AND p.predate < ?
             AND MOD(p.id, ?) = ?
             ORDER BY p.predate ASC, p.id ASC
             LIMIT ?',
            [
                CarbonImmutable::now()->subDay()->toDateTimeString(),
                $workerCount,
                $workerSlot,
                max(1, $limit),
            ]
        );
    }

    /**
     * @param  list<int>  $candidateIds
     * @return list<object>
     */
    public function confirmPredbCandidates(array $candidateIds, string $title): array
    {
        if ($candidateIds === []) {
            return [];
        }

        $like = '%'.$this->escapeLike($title).'%';

        return $this->database->select(
            'SELECT r.id AS releases_id, r.name, r.fromname, r.searchname,
                    r.groups_id, r.categories_id, r.guid, r.predb_id
             FROM releases r
             WHERE r.id IN ('.$this->placeholders(count($candidateIds)).')
             AND r.predb_id = 0
             AND (r.name LIKE ? ESCAPE \'\\\\\' OR r.searchname LIKE ? ESCAPE \'\\\\\')
             ORDER BY r.id ASC
             LIMIT 21',
            array_merge($candidateIds, [$like, $like])
        );
    }

    public function countPrefileCandidates(): int
    {
        $rows = $this->database->select(
            'SELECT COUNT(*) AS aggregate
             FROM releases r
             WHERE r.predb_id = 0
             AND r.isrenamed = 0
             AND r.categories_id IN ('.implode(',', Category::OTHERS_GROUP).')
             AND EXISTS (
                SELECT 1 FROM release_files rf
                WHERE rf.releases_id = r.id
                AND rf.name IS NOT NULL
             )'
        );

        return (int) ($rows[0]->aggregate ?? 0);
    }

    /**
     * @return list<object>
     */
    public function prefileCandidateBatch(int $cursor, int $limit, bool $descending): array
    {
        $indexed = $this->indexedPrefileCandidateBatch($cursor, $limit, $descending);
        if ($indexed !== null) {
            return $indexed;
        }

        return $this->databasePrefileCandidateBatch($cursor, $limit, $descending);
    }

    /**
     * @return list<object>
     */
    private function databasePrefileCandidateBatch(int $cursor, int $limit, bool $descending): array
    {
        $operator = $descending ? '<' : '>';
        $direction = $descending ? 'DESC' : 'ASC';

        return $this->database->select(
            'SELECT r.id AS releases_id, r.name, r.searchname, r.fromname,
                    r.groups_id, r.categories_id, r.guid, r.predb_id
             FROM releases r
             WHERE r.predb_id = 0
             AND r.isrenamed = 0
             AND r.categories_id IN ('.implode(',', Category::OTHERS_GROUP).")
             AND r.id {$operator} ?
             AND EXISTS (
                SELECT 1 FROM release_files rf
                WHERE rf.releases_id = r.id
                AND rf.name IS NOT NULL
             )
             ORDER BY r.id {$direction}
             LIMIT ?",
            [$cursor, max(1, $limit)]
        );
    }

    /**
     * @param  list<int>  $releaseIds
     * @return list<object>
     */
    private function indexedMediaRows(array $releaseIds): array
    {
        $searchService = $this->searchService();
        if ($searchService === null) {
            return [];
        }

        $missingDocumentIds = array_values(array_filter(
            $releaseIds,
            fn (int $releaseId): bool => ! isset($this->indexedDocuments[$releaseId])
        ));

        if ($missingDocumentIds !== []) {
            try {
                $result = $searchService->searchNameFixingCandidates(new NameFixingSearchQuery(
                    source: NameFixingSearchQuery::SOURCE_MEDIA,
                    limit: count($missingDocumentIds),
                    releaseIds: $missingDocumentIds,
                ));
                if ($result->available) {
                    $this->rememberIndexedDocuments($result);
                }
            } catch (Throwable) {
                return [];
            }
        }

        $rows = [];
        foreach ($releaseIds as $releaseId) {
            $document = $this->indexedDocuments[$releaseId] ?? null;
            if (! is_array($document)
                || (int) ($document['has_media_info'] ?? 0) !== 1
                || ! array_key_exists('media_unique_id', $document)
                || ! array_key_exists('media_movie_name', $document)
                || ! array_key_exists('media_file_name', $document)) {
                continue;
            }

            $rows[] = (object) [
                'releases_id' => $releaseId,
                'uid' => (string) $document['media_unique_id'],
                'movie_name' => (string) $document['media_movie_name'],
                'file_name' => (string) $document['media_file_name'],
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $uniqueIds
     * @return array<string, list<object>>|null
     */
    private function indexedUidDonors(array $uniqueIds): ?array
    {
        $searchService = $this->searchService();
        if ($searchService === null) {
            return null;
        }

        try {
            $result = $searchService->searchNameFixingCandidates(new NameFixingSearchQuery(
                source: NameFixingSearchQuery::SOURCE_UID,
                limit: 10000,
                matchValues: $uniqueIds,
            ));
        } catch (Throwable) {
            return null;
        }

        if (! $result->available || $result->hasMore) {
            return null;
        }
        if ($result->ids === []) {
            return $this->databaseUidDonors($uniqueIds);
        }

        $this->rememberIndexedDocuments($result);
        $rows = $this->database->select(
            'SELECT r.id AS releases_id, r.size AS relsize, r.searchname, r.fromname, r.predb_id
             FROM releases r
             WHERE r.id IN ('.$this->placeholders(count($result->ids)).')
             AND (r.predb_id > 0 OR r.anidbid > 0 OR r.fromname = ?)',
            [...$result->ids, 'nonscene@Ef.net (EF)']
        );

        $grouped = [];
        foreach ($rows as $row) {
            $document = $this->indexedDocuments[(int) $row->releases_id] ?? null;
            $matchKey = is_array($document) ? trim((string) ($document['media_unique_id'] ?? '')) : '';
            if ($matchKey === '') {
                continue;
            }

            $row->match_key = $matchKey;
            $grouped[$matchKey][] = $row;
        }

        $missingValues = array_values(array_diff($uniqueIds, array_keys($grouped)));
        if ($missingValues !== []) {
            foreach ($this->databaseUidDonors($missingValues) as $matchKey => $donors) {
                $grouped[$matchKey] = $donors;
            }
        }

        return $grouped;
    }

    /** @return list<object>|null */
    private function indexedPrefileCandidateBatch(int $cursor, int $limit, bool $descending): ?array
    {
        $searchService = $this->searchService();
        if ($searchService === null) {
            return null;
        }

        try {
            $result = $searchService->searchNameFixingCandidates(new NameFixingSearchQuery(
                source: NameFixingSearchQuery::SOURCE_FILES,
                afterId: $descending ? 0 : $cursor,
                beforeId: $descending ? $cursor : 0,
                limit: min(10000, max($limit, $limit * 4)),
                categoryIds: Category::OTHERS_GROUP,
                descending: $descending,
            ));
        } catch (Throwable) {
            return null;
        }

        if (! $result->available || $result->ids === []) {
            return null;
        }

        $this->rememberIndexedDocuments($result);
        $confirmed = $this->prefileRowsForIds($result->ids, $cursor, $limit, $descending);
        if (count($confirmed) >= max(1, $limit)) {
            return $confirmed;
        }

        $fallback = $this->databasePrefileCandidateBatch($cursor, $limit, $descending);

        return $this->mergeReleaseRows($confirmed, $fallback, max(1, $limit), $descending);
    }

    /**
     * @param  list<int>  $releaseIds
     * @return list<object>
     */
    private function prefileRowsForIds(array $releaseIds, int $cursor, int $limit, bool $descending): array
    {
        $operator = $descending ? '<' : '>';
        $direction = $descending ? 'DESC' : 'ASC';

        return $this->database->select(
            'SELECT r.id AS releases_id, r.name, r.searchname, r.fromname,
                    r.groups_id, r.categories_id, r.guid, r.predb_id
             FROM releases r
             WHERE r.predb_id = 0
             AND r.isrenamed = 0
             AND r.categories_id IN ('.implode(',', Category::OTHERS_GROUP).')
             AND r.id IN ('.$this->placeholders(count($releaseIds)).")
             AND r.id {$operator} ?
             AND EXISTS (
                SELECT 1 FROM release_files rf
                WHERE rf.releases_id = r.id
                AND rf.name IS NOT NULL
             )
             ORDER BY r.id {$direction}
             LIMIT ?",
            [...$releaseIds, $cursor, max(1, $limit)]
        );
    }

    /**
     * @param  list<object>  $primary
     * @param  list<object>  $fallback
     * @return list<object>
     */
    private function mergeReleaseRows(array $primary, array $fallback, int $limit, bool $descending = false): array
    {
        $rows = [];
        foreach ([...$primary, ...$fallback] as $row) {
            $rows[(int) $row->releases_id] = $row;
        }

        $rows = array_values($rows);
        usort($rows, static fn (object $left, object $right): int => $descending
            ? (int) $right->releases_id <=> (int) $left->releases_id
            : (int) $left->releases_id <=> (int) $right->releases_id);

        return array_slice($rows, 0, $limit);
    }

    private function rememberIndexedDocuments(NameFixingSearchResult $result): void
    {
        foreach ($result->documents as $releaseId => $document) {
            if ($releaseId > 0) {
                $this->indexedDocuments[$releaseId] = $document;
            }
        }
    }

    /** @return list<int> */
    private function candidateCategoryIds(string $source, int $categories): array
    {
        if ($categories !== 1) {
            return [];
        }

        $categoryIds = Category::OTHERS_GROUP;
        if ($source === self::SOURCE_XXX) {
            array_push($categoryIds, ...$this->xxxSdCategoryIds());
        }

        return array_values(array_unique($categoryIds));
    }

    /**
     * XXX SD plus the retired XXX ids that fold into it, which keep their releases until releases:recategorize-xxx runs.
     *
     * @return list<int>
     */
    private function xxxSdCategoryIds(): array
    {
        return [Category::XXX_SD, ...array_keys(Category::XXX_RETIRED_FALLBACKS, Category::XXX_SD, true)];
    }

    private function searchService(): ?SearchServiceInterface
    {
        if ($this->searchService instanceof SearchServiceInterface) {
            return $this->searchService;
        }

        $container = Container::getInstance();
        if (! $container->bound(SearchServiceInterface::class)) {
            return null;
        }

        try {
            $service = $container->make(SearchServiceInterface::class);
        } catch (Throwable) {
            return null;
        }

        return $this->searchService = $service;
    }

    /**
     * @param  list<object>  $rows
     * @return array<int, list<object>>
     */
    public function groupByReleaseId(array $rows): array
    {
        return $this->groupByKey($rows, 'releases_id');
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function candidateWhere(string $source, int $time, int $categories): array
    {
        if (! isset(self::SOURCE_EXISTS[$source], self::STATUS_COLUMNS[$source])) {
            throw new InvalidArgumentException("Unsupported name-fixing source [{$source}].");
        }

        $where = ['r.predb_id = 0', '('.self::SOURCE_EXISTS[$source].')'];
        $bindings = [];

        if ($categories !== 3) {
            $statusColumn = self::STATUS_COLUMNS[$source];
            $where[] = '(r.isrenamed = 0 OR r.categories_id IN (?, ?))';
            $bindings[] = Category::OTHER_MISC;
            $bindings[] = Category::OTHER_HASHED;
            $where[] = "r.{$statusColumn} = 0";
        }

        if ($time === 1) {
            $where[] = 'r.adddate > ?';
            $bindings[] = CarbonImmutable::now()->subHours(6)->toDateTimeString();
        }

        if ($categories === 1) {
            $categoryIds = Category::OTHERS_GROUP;
            if ($source === self::SOURCE_XXX) {
                array_push($categoryIds, ...$this->xxxSdCategoryIds());
            }

            $where[] = 'r.categories_id IN ('.implode(',', array_unique($categoryIds)).')';
        }

        return [implode(' AND ', $where), $bindings];
    }

    /**
     * @param  list<int>  $releaseIds
     * @return list<object>
     */
    private function selectForReleaseIds(
        string $sql,
        array $releaseIds,
        int $placeholderGroups = 1
    ): array {
        if ($releaseIds === []) {
            return [];
        }

        $placeholder = $this->placeholders((int) (count($releaseIds) / $placeholderGroups));
        $sql = str_replace('%s', $placeholder, $sql);

        return $this->database->select($sql, $releaseIds);
    }

    /**
     * @param  list<string>  $values
     * @return array<string, list<object>>
     */
    private function donors(string $sql, array $values): array
    {
        if ($values === []) {
            return [];
        }

        $rows = $this->database->select(
            sprintf($sql, $this->placeholders(count($values))),
            $values
        );

        return $this->groupByKey($rows, 'match_key');
    }

    /**
     * @param  list<object>  $rows
     * @return array<int|string, list<object>>
     */
    private function groupByKey(array $rows, string $key): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row->{$key}][] = $row;
        }

        return $grouped;
    }

    private function placeholders(int $count): string
    {
        return implode(',', array_fill(0, $count, '?'));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
