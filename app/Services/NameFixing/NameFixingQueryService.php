<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

use App\Models\Category;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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
        self::SOURCE_UID => "EXISTS (SELECT 1 FROM media_infos source_media WHERE source_media.releases_id = r.id AND source_media.unique_id IS NOT NULL AND source_media.unique_id != '') OR EXISTS (SELECT 1 FROM release_unique source_unique WHERE source_unique.releases_id = r.id AND source_unique.uniqueid != '')",
        self::SOURCE_HASH => "EXISTS (SELECT 1 FROM par_hashes source_hash WHERE source_hash.releases_id = r.id AND source_hash.hash != '')",
        self::SOURCE_PAR2 => '1 = 1',
        self::SOURCE_XXX => "EXISTS (SELECT 1 FROM release_files source_xxx WHERE source_xxx.releases_id = r.id AND source_xxx.name LIKE '%SDPORN%')",
        self::SOURCE_MEDIA_MOVIE => "EXISTS (SELECT 1 FROM media_infos source_movie WHERE source_movie.releases_id = r.id AND source_movie.movie_name IS NOT NULL AND source_movie.movie_name != '')",
    ];

    public function __construct(?ConnectionInterface $database = null)
    {
        $this->database = $database ?? DB::connection();
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
            self::SOURCE_XXX => " AND rf.name LIKE '%SDPORN%'",
            default => throw new InvalidArgumentException("Unsupported release-file source [{$source}]."),
        };

        return $this->selectForReleaseIds(
            'SELECT rf.releases_id, rf.name AS textstring, rf.name AS filename, rf.crc32
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
        return $this->selectForReleaseIds(
            'SELECT mi.releases_id, mi.unique_id AS uid, mi.movie_name, mi.file_name
             FROM media_infos mi
             WHERE mi.releases_id IN (%s)
             UNION ALL
             SELECT ru.releases_id, ru.uniqueid AS uid, NULL AS movie_name, NULL AS file_name
             FROM release_unique ru
             WHERE ru.releases_id IN (%s)
             ORDER BY releases_id',
            array_merge($releaseIds, $releaseIds),
            2
        );
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

        $placeholders = $this->placeholders(count($uniqueIds));
        $bindings = array_merge($uniqueIds, ['nonscene@Ef.net (EF)'], $uniqueIds);
        $rows = $this->database->select(
            "SELECT mi.unique_id AS match_key, r.id AS releases_id, r.size AS relsize,
                    r.searchname, r.fromname, r.predb_id
             FROM media_infos mi
             INNER JOIN releases r ON r.id = mi.releases_id
             WHERE mi.unique_id IN ({$placeholders})
             AND (r.predb_id > 0 OR r.anidbid > 0 OR r.fromname = ?)
             UNION ALL
             SELECT ru.uniqueid AS match_key, r.id AS releases_id, r.size AS relsize,
                    r.searchname, r.fromname, r.predb_id
             FROM release_unique ru
             INNER JOIN releases r ON r.id = ru.releases_id
             WHERE ru.uniqueid IN ({$placeholders})
             AND (r.predb_id > 0 OR r.anidbid > 0 OR r.fromname = 'nonscene@Ef.net (EF)')",
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
        return $this->donors(
            'SELECT rf.crc32 AS match_key, r.id AS releases_id, r.size AS relsize,
                    r.searchname, r.fromname, r.predb_id
             FROM release_files rf
             INNER JOIN releases r ON r.id = rf.releases_id
             WHERE rf.crc32 IN (%s)
             AND r.predb_id > 0',
            $crcs
        );
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
                    r.groups_id, r.categories_id
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
        $operator = $descending ? '<' : '>';
        $direction = $descending ? 'DESC' : 'ASC';

        return $this->database->select(
            'SELECT r.id AS releases_id, r.name, r.searchname, r.fromname,
                    r.groups_id, r.categories_id
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
            $where[] = 'r.categories_id IN ('.implode(',', Category::OTHERS_GROUP).')';
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
        $sql = vsprintf($sql, array_fill(0, $placeholderGroups, $placeholder));

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
