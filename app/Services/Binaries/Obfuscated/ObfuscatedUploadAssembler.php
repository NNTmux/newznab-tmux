<?php

declare(strict_types=1);

namespace App\Services\Binaries\Obfuscated;

use App\Enums\CollectionFileCheckStatus;
use App\Services\Binaries\BinaryHandler;
use App\Services\Binaries\CollectionHandler;
use App\Support\Database\RawSql;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Rebuilds uploads whose every article carries its own random subject.
 *
 * Uploaders such as Nyuu can randomize the subject per article while keeping the real
 * `[fn/tf]` file counter and `yEnc (k/tp)` part counter. Ingestion keys collections by the
 * cleaned subject, so each article becomes a one-part collection that the stale path later
 * deletes. This service finds those articles by poster and counters, rebuilds the files,
 * groups them into uploads ({@see ObfuscatedUploadPlanner}) and merges each upload into one
 * collection in place, so the normal pipeline creates the release, NZB and PAR2 rename.
 */
final class ObfuscatedUploadAssembler
{
    /** collection_regexes_id written on merged collections, next to the generic (-10) and music (-20) ids. */
    public const int ASSEMBLED_REGEX_ID = -30;

    /** A collection the pipeline has not turned into a release yet. */
    private const string UNRELEASED_SQL = '(c.releases_id IS NULL OR c.releases_id = 0)';

    private const string ARTICLE_COLUMNS = 'b.id AS binary_id, c.id AS collection_id, b.name, b.totalparts,'
        .' p.partnumber, p.number, p.size, c.date, c.dateadded';

    private readonly ObfuscatedUploadPlanner $planner;

    private ?bool $hasCollectionGroups = null;

    public function __construct(
        private readonly ObfuscatedUploadConfig $config,
        ?ObfuscatedUploadPlanner $planner = null,
        private readonly BinaryHandler $binaryHandler = new BinaryHandler,
        private readonly CollectionHandler $collectionHandler = new CollectionHandler,
    ) {
        $this->planner = $planner ?? new ObfuscatedUploadPlanner($config);
    }

    public function isEnabled(): bool
    {
        return $this->config->enabled;
    }

    /**
     * Plan, and unless $dryRun, hold and merge the obfuscated uploads of one group.
     */
    public function assembleGroup(int $groupId, bool $dryRun = false, ?int $limit = null): ObfuscatedAssemblyResult
    {
        if ($dryRun) {
            return $this->run($groupId, true, $limit);
        }

        $result = Cache::lock('cbp:obfuscated:'.$groupId, 1800)->get(fn (): ObfuscatedAssemblyResult => $this->run($groupId, false, $limit));

        return $result instanceof ObfuscatedAssemblyResult ? $result : new ObfuscatedAssemblyResult;
    }

    private function run(int $groupId, bool $dryRun, ?int $limit): ObfuscatedAssemblyResult
    {
        $result = new ObfuscatedAssemblyResult;
        $now = $this->databaseNow();
        $budget = $limit ?? $this->config->maxUploadsPerRun;

        if (! $dryRun) {
            $result->released = $this->releaseExpiredHolds($groupId, $now);
        }

        foreach ($this->signaturePosters($groupId) as $poster) {
            if ($budget > 0) {
                foreach ($this->planner->plan($poster, $this->loadFiles($groupId, $poster), $now) as $upload) {
                    if ($budget <= 0) {
                        break;
                    }
                    $budget--;
                    $result->planned[] = $upload;
                    if ($dryRun) {
                        continue;
                    }
                    try {
                        $result->mergedArticles += $this->merge($groupId, $upload);
                        $result->merged++;
                    } catch (Throwable $e) {
                        $result->failed++;
                        Log::warning('Obfuscated upload merge skipped', [
                            'group' => $groupId,
                            'poster' => $poster,
                            'first_article' => $upload->minNumber(),
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            if (! $dryRun) {
                $result->held += $this->holdPoster($groupId, $poster, $now);
            }
        }

        return $result;
    }

    /**
     * Posters with many single-part collections of one (file count, part count) shape. In a
     * normal post every part of a file shares one binary, so this shape only appears when
     * subjects change per article.
     *
     * @return list<string>
     */
    private function signaturePosters(int $groupId): array
    {
        $rows = DB::select(
            'SELECT c.fromname
             FROM collections c
             INNER JOIN binaries b ON b.collections_id = c.id
             WHERE c.groups_id = ?
               AND c.filecheck IN ('.$this->statusList([CollectionFileCheckStatus::Default, CollectionFileCheckStatus::ObfuscatedHold]).')
               AND c.collection_regexes_id <> '.self::ASSEMBLED_REGEX_ID.'
               AND b.currentparts = 1 AND b.totalparts > 1
             GROUP BY c.fromname, c.totalfiles, b.totalparts
             HAVING COUNT(*) >= ?',
            [$groupId, $this->config->minArticles]
        );

        // One poster can match with several (file count, part count) shapes.
        return array_values(array_unique(array_map(static fn (object $row): string => (string) $row->fromname, $rows)));
    }

    /**
     * @return list<ObfuscatedFile>
     */
    private function loadFiles(int $groupId, string $poster): array
    {
        $keys = DB::select(
            'SELECT c.totalfiles AS tf, b.filenumber AS fn, b.totalparts AS tp,
                    COUNT(*) AS articles, COUNT(DISTINCT p.partnumber) AS parts,
                    SUM(CASE WHEN b.filenumber = p.partnumber THEN 0 ELSE 1 END) AS mismatches,
                    MIN(p.number) AS min_number, MAX(p.number) AS max_number,
                    MIN(c.date) AS min_date, MAX(c.date) AS max_date,
                    MAX(COALESCE(c.last_seen_at, c.dateadded)) AS last_seen,
                    COALESCE(SUM(p.size), 0) AS bytes'
            .$this->candidateArticlesSql()
            .' GROUP BY c.totalfiles, b.filenumber, b.totalparts',
            [$groupId, $poster]
        );

        $buckets = [];
        foreach ($keys as $key) {
            $buckets[(int) $key->tf.'|'.(int) $key->tp][] = $key;
        }

        $files = [];
        foreach ($buckets as $bucket) {
            if ($this->counterIsPart($bucket)) {
                $files = [...$files, ...$this->filesForKey($groupId, $poster, $this->combineKeys($bucket), true)];

                continue;
            }
            foreach ($bucket as $key) {
                $files = [...$files, ...$this->filesForKey($groupId, $poster, $key, false)];
            }
        }

        return $files;
    }

    /**
     * `[k/N] "random.bin"` with a `(k/N)` part counter is parsed as file k of N and part k of N.
     * Only when every article of the (N, N) bucket agrees is it one N-part file without a file counter;
     * a single coincidence (part 2 of a 2-part file in a 2-file upload) must not split a real file.
     *
     * @param  list<object>  $bucket
     */
    private function counterIsPart(array $bucket): bool
    {
        $totalFiles = (int) $bucket[0]->tf;

        return $totalFiles > 1
            && $totalFiles === (int) $bucket[0]->tp
            && \count($bucket) > 1
            && array_sum(array_map(static fn (object $key): int => (int) $key->mismatches, $bucket)) === 0;
    }

    /**
     * @param  list<object>  $bucket
     */
    private function combineKeys(array $bucket): object
    {
        return (object) [
            'tf' => 0,
            'fn' => 0,
            'tp' => (int) $bucket[0]->tp,
            'articles' => array_sum(array_map(static fn (object $key): int => (int) $key->articles, $bucket)),
            // Each key holds one part number (fn == pn), so distinct parts are distinct keys.
            'parts' => \count($bucket),
            'min_number' => min(array_map(static fn (object $key): int => (int) $key->min_number, $bucket)),
            'max_number' => max(array_map(static fn (object $key): int => (int) $key->max_number, $bucket)),
            'min_date' => min(array_map(static fn (object $key): string => (string) $key->min_date, $bucket)),
            'max_date' => max(array_map(static fn (object $key): string => (string) $key->max_date, $bucket)),
            'last_seen' => max(array_map(static fn (object $key): string => (string) $key->last_seen, $bucket)),
            'bytes' => array_sum(array_map(static fn (object $key): int => (int) $key->bytes, $bucket)),
        ];
    }

    /**
     * @return list<ObfuscatedFile>
     */
    private function filesForKey(int $groupId, string $poster, object $key, bool $counterIsPart): array
    {
        $totalFiles = (int) $key->tf;
        $fileNumber = (int) $key->fn;
        $totalParts = (int) $key->tp;
        $lastSeen = $this->timestamp($key->last_seen);

        if ((int) $key->articles === (int) $key->parts) {
            return [new ObfuscatedFile(
                totalFiles: $totalFiles,
                fileNumber: $fileNumber,
                totalParts: $totalParts,
                articles: (int) $key->articles,
                distinctParts: (int) $key->parts,
                minNumber: (int) $key->min_number,
                maxNumber: (int) $key->max_number,
                minTime: $this->timestamp($key->min_date),
                maxTime: $this->timestamp($key->max_date),
                lastSeen: $lastSeen,
                bytes: (int) $key->bytes,
                counterIsPart: $counterIsPart,
            )];
        }

        $articles = [];
        foreach ($this->keyArticles($groupId, $poster, $totalFiles, $fileNumber, $totalParts, $counterIsPart) as $row) {
            $articles[] = [
                'binary' => (int) $row->binary_id,
                'part' => (int) $row->partnumber,
                'number' => (int) $row->number,
                'time' => $this->timestamp($row->date),
                'size' => (int) $row->size,
            ];
        }

        return $articles === []
            ? []
            : $this->planner->splitDuplicateKey($totalFiles, $fileNumber, $totalParts, $articles, $lastSeen, $counterIsPart);
    }

    /**
     * Merge one upload into a single collection: one binary per rebuilt file, every part moved
     * onto it, the emptied rows deleted. Returns the number of articles merged.
     */
    private function merge(int $groupId, ObfuscatedUpload $upload): int
    {
        $files = [];
        foreach ($upload->files as $file) {
            $rows = $file->binaryIds !== null
                ? $this->articlesByBinaryIds($file->binaryIds)
                : $this->keyArticles($groupId, $upload->poster, $file->totalFiles, $file->fileNumber, $file->totalParts, $file->counterIsPart);
            $parts = array_map(static fn ($row): int => (int) $row->partnumber, $rows);
            if ($rows === [] || \count(array_unique($parts)) !== \count($rows)) {
                throw new RuntimeException('file changed since planning');
            }
            usort($rows, static fn ($a, $b): int => (int) $a->partnumber <=> (int) $b->partnumber);
            $files[] = $rows;
        }

        $targetCollection = (int) $files[0][0]->collection_id;
        $collectionHash = sha1('obf:'.$groupId.'|'.$upload->poster.'|'.$upload->totalFiles.'|'.$upload->minNumber(), true);
        $subject = $this->subjectFor((string) $files[0][0]->name, $collectionHash);
        $sourceCollections = [];
        $dates = [];
        $added = [];
        foreach ($files as $rows) {
            foreach ($rows as $row) {
                $sourceCollections[(int) $row->collection_id] = true;
                $dates[] = (string) $row->date;
                if ($row->dateadded !== null) {
                    $added[] = (string) $row->dateadded;
                }
            }
        }
        $sourceCollections = array_keys($sourceCollections);

        DB::transaction(function () use ($files, $targetCollection, $collectionHash, $subject, $sourceCollections, $dates, $added): void {
            $this->lockSources($sourceCollections);
            if (DB::selectOne('SELECT 1 AS present FROM collections WHERE collectionhash = ? LIMIT 1', [$collectionHash]) !== null) {
                throw new RuntimeException('upload already assembled');
            }

            $targetBinaries = [];
            foreach ($files as $ordinal => $rows) {
                $target = (int) $rows[0]->binary_id;
                $targetBinaries[] = $target;
                $this->retargetBinary($target, $targetCollection, $ordinal, (string) $rows[0]->name, (int) $rows[0]->totalparts);

                $others = array_map(static fn ($row): int => (int) $row->binary_id, \array_slice($rows, 1));
                foreach (array_chunk($others, $this->config->sqlChunkSize) as $chunk) {
                    $placeholders = RawSql::placeholders(\count($chunk));
                    DB::update("UPDATE parts SET binaries_id = ? WHERE binaries_id IN ({$placeholders})", [$target, ...$chunk]);
                    DB::delete("DELETE FROM binaries WHERE id IN ({$placeholders})", $chunk);
                }
            }

            $this->moveGroupsAndDeleteSources($sourceCollections, $targetCollection);

            DB::update(
                'UPDATE collections
                 SET subject = ?, collectionhash = ?, collection_regexes_id = ?, totalfiles = ?,
                     date = ?, dateadded = ?, filecheck = ?
                 WHERE id = ?',
                [
                    $subject,
                    $collectionHash,
                    self::ASSEMBLED_REGEX_ID,
                    \count($files),
                    min($dates),
                    $added === [] ? now()->format('Y-m-d H:i:s') : min($added),
                    CollectionFileCheckStatus::Default->value,
                    $targetCollection,
                ]
            );

            if (! $this->binaryHandler->refreshAggregates($targetBinaries, $this->config->sqlChunkSize)
                || ! $this->collectionHandler->refreshAggregates([$targetCollection], $this->config->sqlChunkSize)) {
                throw new RuntimeException('aggregate refresh failed');
            }
        }, 3);

        return array_sum(array_map('count', $files));
    }

    /**
     * @param  list<int>  $collectionIds
     */
    private function lockSources(array $collectionIds): void
    {
        // SQLite has no row locks; its single writer already serializes merges.
        $lockClause = DB::getDriverName() === 'sqlite' ? '' : ' FOR UPDATE';
        $locked = 0;
        foreach (array_chunk($collectionIds, $this->config->sqlChunkSize) as $chunk) {
            $locked += (int) DB::scalar(
                'SELECT COUNT(*) FROM collections c
                 WHERE '.self::UNRELEASED_SQL.'
                   AND c.id IN ('.RawSql::placeholders(\count($chunk)).')
                   AND c.filecheck IN ('.$this->statusList($this->mergeableStatuses()).')'.$lockClause,
                $chunk,
                false
            );
        }
        if ($locked !== \count($collectionIds)) {
            throw new RuntimeException('source collection claimed or removed');
        }
    }

    /**
     * Give the file's binary a hash that is unique inside the merged collection, then move it.
     * Ingestion's `md5('file:'.fn)` would collide for files without a usable file number.
     */
    private function retargetBinary(int $binaryId, int $collectionId, int $ordinal, string $name, int $totalParts): void
    {
        $hash = md5('obf:'.$ordinal);
        $values = [$name, $ordinal + 1, $totalParts, $collectionId, $binaryId];
        if (DB::getDriverName() === 'sqlite') {
            DB::update('UPDATE binaries SET binaryhash = ?, name = ?, filenumber = ?, totalparts = ?, collections_id = ? WHERE id = ?', [$hash, ...$values]);

            return;
        }
        DB::update('UPDATE binaries SET binaryhash = UNHEX(?), name = ?, filenumber = ?, totalparts = ?, collections_id = ? WHERE id = ?', [$hash, ...$values]);
    }

    /**
     * @param  list<int>  $sourceCollections
     */
    private function moveGroupsAndDeleteSources(array $sourceCollections, int $targetCollection): void
    {
        $sources = array_values(array_diff($sourceCollections, [$targetCollection]));
        $this->hasCollectionGroups ??= Schema::hasTable('collection_groups');

        $insertIgnore = DB::getDriverName() === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';

        foreach (array_chunk($sources, $this->config->sqlChunkSize) as $chunk) {
            $placeholders = RawSql::placeholders(\count($chunk));
            if ($this->hasCollectionGroups) {
                $bindings = [];
                foreach (DB::select("SELECT DISTINCT group_name FROM collection_groups WHERE collections_id IN ({$placeholders})", $chunk) as $row) {
                    $bindings[] = $targetCollection;
                    $bindings[] = (string) $row->group_name;
                }
                if ($bindings !== []) {
                    DB::insert(
                        $insertIgnore.' INTO collection_groups (collections_id, group_name) VALUES '
                        .RawSql::placeholders(intdiv(\count($bindings), 2), '(?, ?)'),
                        $bindings
                    );
                }
                DB::delete("DELETE FROM collection_groups WHERE collections_id IN ({$placeholders})", $chunk);
            }
            DB::delete(
                "DELETE FROM collections WHERE id IN ({$placeholders})
                 AND NOT EXISTS (SELECT 1 FROM binaries WHERE binaries.collections_id = collections.id)",
                $chunk
            );
        }
    }

    /**
     * Hold the poster's remaining single-part articles so the stale path does not promote and
     * delete an upload before it is complete. Holds lapse after the hold window ({@see releaseExpiredHolds()}).
     */
    private function holdPoster(int $groupId, string $poster, int $now): int
    {
        $cutoff = $this->dateString($now - $this->config->holdMinutes * 60);
        $limit = $this->config->sqlChunkSize * 10;
        $held = 0;

        $holdable = $this->statusList([CollectionFileCheckStatus::Default, CollectionFileCheckStatus::Sized]);
        $selectSql = 'SELECT c.id FROM collections c
             WHERE '.self::UNRELEASED_SQL.'
               AND c.groups_id = ? AND c.fromname = ?
               AND c.filecheck IN ('.$holdable.')
               AND c.collection_regexes_id <> '.self::ASSEMBLED_REGEX_ID.'
               AND (c.last_seen_at >= ? OR (c.last_seen_at IS NULL AND c.dateadded >= ?))
               AND EXISTS (SELECT 1 FROM binaries hb WHERE hb.collections_id = c.id AND hb.currentparts = 1)
               AND NOT EXISTS (SELECT 1 FROM binaries mb WHERE mb.collections_id = c.id AND mb.currentparts <> 1)
             LIMIT '.$limit;

        do {
            $ids = RawSql::ids($selectSql, [$groupId, $poster, $cutoff, $cutoff]);
            if ($ids === []) {
                break;
            }
            foreach (array_chunk($ids, $this->config->sqlChunkSize) as $chunk) {
                $held += DB::update(
                    'UPDATE collections SET filecheck = ? WHERE id IN ('.RawSql::placeholders(\count($chunk)).')'
                    .' AND filecheck IN ('.$holdable.')',
                    [CollectionFileCheckStatus::ObfuscatedHold->value, ...$chunk]
                );
            }
        } while (\count($ids) === $limit);

        return $held;
    }

    /**
     * Held articles that saw nothing new for the hold window go back to Default, where the
     * stale path and the size filters handle them as before.
     */
    private function releaseExpiredHolds(int $groupId, int $now): int
    {
        $cutoff = $this->dateString($now - $this->config->holdMinutes * 60);

        return DB::update(
            'UPDATE collections SET filecheck = ?
             WHERE groups_id = ? AND filecheck = ?
               AND (last_seen_at < ? OR (last_seen_at IS NULL AND (dateadded < ? OR dateadded IS NULL)))',
            [
                CollectionFileCheckStatus::Default->value,
                $groupId,
                CollectionFileCheckStatus::ObfuscatedHold->value,
                $cutoff,
                $cutoff,
            ]
        );
    }

    /**
     * FROM and WHERE for the articles of one poster that are still single-part collections the
     * pipeline has not released. Binds the group id, then the poster.
     */
    private function candidateArticlesSql(): string
    {
        return ' FROM collections c
             INNER JOIN binaries b ON b.collections_id = c.id
             INNER JOIN parts p ON p.binaries_id = b.id
             WHERE '.self::UNRELEASED_SQL.'
               AND c.groups_id = ? AND c.fromname = ?
               AND c.filecheck IN ('.$this->statusList($this->mergeableStatuses()).')
               AND c.collection_regexes_id <> '.self::ASSEMBLED_REGEX_ID.'
               AND b.currentparts = 1';
    }

    /**
     * @return list<object>
     */
    private function keyArticles(int $groupId, string $poster, int $totalFiles, int $fileNumber, int $totalParts, bool $counterIsPart): array
    {
        $sql = 'SELECT '.self::ARTICLE_COLUMNS.$this->candidateArticlesSql().' AND b.totalparts = ?';

        return $counterIsPart
            ? DB::select($sql.' AND c.totalfiles = ? AND b.filenumber = p.partnumber', [$groupId, $poster, $totalParts, $totalParts])
            : DB::select($sql.' AND c.totalfiles = ? AND b.filenumber = ?', [$groupId, $poster, $totalParts, $totalFiles, $fileNumber]);
    }

    /**
     * @param  list<int>  $binaryIds
     * @return list<object>
     */
    private function articlesByBinaryIds(array $binaryIds): array
    {
        $rows = [];
        foreach (array_chunk($binaryIds, $this->config->sqlChunkSize) as $chunk) {
            array_push($rows, ...DB::select(
                'SELECT '.self::ARTICLE_COLUMNS.'
                 FROM collections c
                 INNER JOIN binaries b ON b.collections_id = c.id
                 INNER JOIN parts p ON p.binaries_id = b.id
                 WHERE '.self::UNRELEASED_SQL.'
                   AND b.id IN ('.RawSql::placeholders(\count($chunk)).')
                   AND c.filecheck IN ('.$this->statusList($this->mergeableStatuses()).')
                   AND b.currentparts = 1',
                $chunk
            ));
        }
        if (\count($rows) !== \count($binaryIds)) {
            throw new RuntimeException('file changed since planning');
        }

        return $rows;
    }

    /**
     * Status values as a literal SQL list; they come from the enum, never from input.
     *
     * @param  list<CollectionFileCheckStatus|int>  $statuses
     */
    private function statusList(array $statuses): string
    {
        return implode(', ', array_map(
            static fn (CollectionFileCheckStatus|int $status): int => $status instanceof CollectionFileCheckStatus ? $status->value : $status,
            $statuses
        ));
    }

    /**
     * Sized is included because one-part files (often the PAR2 index) are promoted at ingest.
     *
     * @return list<int>
     */
    private function mergeableStatuses(): array
    {
        return [
            CollectionFileCheckStatus::Default->value,
            CollectionFileCheckStatus::Sized->value,
            CollectionFileCheckStatus::ObfuscatedHold->value,
        ];
    }

    /**
     * A bare name without `[n/m]` or quotes: the quoted-filename cleaner would otherwise mark the
     * random name as a proper release name and the PAR2 rename would never run.
     */
    private function subjectFor(string $binaryName, string $collectionHash): string
    {
        $name = preg_match('/"([^"]+)"/', $binaryName, $quoted) === 1 ? $quoted[1] : $binaryName;
        $name = preg_replace('/\s*\byEnc\b.*$/i', '', $name) ?? $name;
        $name = trim(preg_replace('/^\s*[\[(]?\d+\s*\/\s*\d+[\])]?\s*-?\s*/', '', $name) ?? $name, " \t-\"");

        return substr($name !== '' ? $name : 'obfuscated-'.substr(bin2hex($collectionHash), 0, 16), 0, 255);
    }

    /**
     * Times stored by MariaDB's NOW() follow the database session time zone, so compare
     * against the database clock there and against the application clock in SQLite tests.
     */
    private function databaseNow(): int
    {
        if (DB::getDriverName() === 'sqlite') {
            return now()->timestamp;
        }

        return $this->timestamp(DB::selectOne('SELECT NOW() AS now')?->now);
    }

    private function timestamp(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (int) (is_numeric($value) ? $value : strtotime((string) $value));
    }

    private function dateString(int $timestamp): string
    {
        return date('Y-m-d H:i:s', $timestamp);
    }
}
