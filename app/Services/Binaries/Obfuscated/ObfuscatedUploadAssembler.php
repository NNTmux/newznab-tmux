<?php

declare(strict_types=1);

namespace App\Services\Binaries\Obfuscated;

use App\Enums\CollectionFileCheckStatus;
use App\Services\Binaries\BinaryHandler;
use App\Services\Binaries\CollectionHandler;
use Illuminate\Database\Query\Builder;
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
        return DB::table('collections as c')
            ->join('binaries as b', 'b.collections_id', '=', 'c.id')
            ->where('c.groups_id', $groupId)
            ->whereIn('c.filecheck', [CollectionFileCheckStatus::Default->value, CollectionFileCheckStatus::ObfuscatedHold->value])
            ->where('c.collection_regexes_id', '<>', self::ASSEMBLED_REGEX_ID)
            ->where('b.currentparts', 1)
            ->where('b.totalparts', '>', 1)
            ->groupBy('c.fromname', 'c.totalfiles', 'b.totalparts')
            ->havingRaw('COUNT(*) >= ?', [$this->config->minArticles])
            ->pluck('c.fromname')
            ->map(static fn ($poster): string => (string) $poster)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<ObfuscatedFile>
     */
    private function loadFiles(int $groupId, string $poster): array
    {
        $keys = $this->candidateArticles($groupId, $poster)
            ->selectRaw(
                'c.totalfiles AS tf, b.filenumber AS fn, b.totalparts AS tp,
                 COUNT(*) AS articles, COUNT(DISTINCT p.partnumber) AS parts,
                 SUM(CASE WHEN b.filenumber = p.partnumber THEN 0 ELSE 1 END) AS mismatches,
                 MIN(p.number) AS min_number, MAX(p.number) AS max_number,
                 MIN(c.date) AS min_date, MAX(c.date) AS max_date,
                 MAX(COALESCE(c.last_seen_at, c.dateadded)) AS last_seen,
                 COALESCE(SUM(p.size), 0) AS bytes'
            )
            ->groupBy('c.totalfiles', 'b.filenumber', 'b.totalparts')
            ->get();

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
        foreach ($this->keyArticles($groupId, $poster, $totalFiles, $fileNumber, $totalParts, $counterIsPart)->get() as $row) {
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
                : $this->keyArticles($groupId, $upload->poster, $file->totalFiles, $file->fileNumber, $file->totalParts, $file->counterIsPart)->get()->all();
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
            if (DB::table('collections')->where('collectionhash', $collectionHash)->exists()) {
                throw new RuntimeException('upload already assembled');
            }

            $targetBinaries = [];
            foreach ($files as $ordinal => $rows) {
                $target = (int) $rows[0]->binary_id;
                $targetBinaries[] = $target;
                $this->retargetBinary($target, $targetCollection, $ordinal, (string) $rows[0]->name, (int) $rows[0]->totalparts);

                $others = array_map(static fn ($row): int => (int) $row->binary_id, \array_slice($rows, 1));
                foreach (array_chunk($others, $this->config->sqlChunkSize) as $chunk) {
                    DB::table('parts')->whereIn('binaries_id', $chunk)->update(['binaries_id' => $target]);
                    DB::table('binaries')->whereIn('id', $chunk)->delete();
                }
            }

            $this->moveGroupsAndDeleteSources($sourceCollections, $targetCollection);

            DB::table('collections')->where('id', $targetCollection)->update([
                'subject' => $subject,
                'collectionhash' => $collectionHash,
                'collection_regexes_id' => self::ASSEMBLED_REGEX_ID,
                'totalfiles' => \count($files),
                'date' => min($dates),
                'dateadded' => $added === [] ? now()->format('Y-m-d H:i:s') : min($added),
                'filecheck' => CollectionFileCheckStatus::Default->value,
            ]);

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
        $locked = 0;
        foreach (array_chunk($collectionIds, $this->config->sqlChunkSize) as $chunk) {
            $locked += $this->unreleased(DB::table('collections'))
                ->whereIn('id', $chunk)
                ->whereIn('filecheck', $this->mergeableStatuses())
                ->lockForUpdate()
                ->count();
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

        foreach (array_chunk($sources, $this->config->sqlChunkSize) as $chunk) {
            if ($this->hasCollectionGroups) {
                $groupNames = DB::table('collection_groups')->whereIn('collections_id', $chunk)->distinct()->pluck('group_name');
                DB::table('collection_groups')->insertOrIgnore($groupNames->map(static fn ($name): array => [
                    'collections_id' => $targetCollection,
                    'group_name' => (string) $name,
                ])->all());
                DB::table('collection_groups')->whereIn('collections_id', $chunk)->delete();
            }
            DB::table('collections')
                ->whereIn('id', $chunk)
                ->whereNotExists(static fn (Builder $query) => $query->selectRaw('1')->from('binaries')->whereColumn('binaries.collections_id', 'collections.id'))
                ->delete();
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

        do {
            $ids = $this->unreleased(DB::table('collections as c'), 'c.')
                ->where('c.groups_id', $groupId)
                ->where('c.fromname', $poster)
                ->whereIn('c.filecheck', [CollectionFileCheckStatus::Default->value, CollectionFileCheckStatus::Sized->value])
                ->where('c.collection_regexes_id', '<>', self::ASSEMBLED_REGEX_ID)
                ->where(static fn (Builder $seen) => $seen
                    ->where('c.last_seen_at', '>=', $cutoff)
                    ->orWhere(static fn (Builder $unseen) => $unseen->whereNull('c.last_seen_at')->where('c.dateadded', '>=', $cutoff)))
                ->whereExists(static fn (Builder $query) => $query->selectRaw('1')->from('binaries as hb')
                    ->whereColumn('hb.collections_id', 'c.id')->where('hb.currentparts', 1))
                ->whereNotExists(static fn (Builder $query) => $query->selectRaw('1')->from('binaries as mb')
                    ->whereColumn('mb.collections_id', 'c.id')->where('mb.currentparts', '<>', 1))
                ->limit($limit)
                ->pluck('c.id')
                ->all();
            if ($ids === []) {
                break;
            }
            foreach (array_chunk($ids, $this->config->sqlChunkSize) as $chunk) {
                $held += DB::table('collections')->whereIn('id', $chunk)
                    ->whereIn('filecheck', [CollectionFileCheckStatus::Default->value, CollectionFileCheckStatus::Sized->value])
                    ->update(['filecheck' => CollectionFileCheckStatus::ObfuscatedHold->value]);
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

        return DB::table('collections')
            ->where('groups_id', $groupId)
            ->where('filecheck', CollectionFileCheckStatus::ObfuscatedHold->value)
            ->where(static fn (Builder $idle) => $idle
                ->where('last_seen_at', '<', $cutoff)
                ->orWhere(static fn (Builder $unseen) => $unseen->whereNull('last_seen_at')
                    ->where(static fn (Builder $created) => $created->where('dateadded', '<', $cutoff)->orWhereNull('dateadded'))))
            ->update(['filecheck' => CollectionFileCheckStatus::Default->value]);
    }

    /**
     * Articles of one poster that are still single-part collections the pipeline has not released.
     */
    private function candidateArticles(int $groupId, string $poster): Builder
    {
        return $this->unreleased(DB::table('collections as c'), 'c.')
            ->join('binaries as b', 'b.collections_id', '=', 'c.id')
            ->join('parts as p', 'p.binaries_id', '=', 'b.id')
            ->where('c.groups_id', $groupId)
            ->where('c.fromname', $poster)
            ->whereIn('c.filecheck', $this->mergeableStatuses())
            ->where('c.collection_regexes_id', '<>', self::ASSEMBLED_REGEX_ID)
            ->where('b.currentparts', 1);
    }

    private function keyArticles(int $groupId, string $poster, int $totalFiles, int $fileNumber, int $totalParts, bool $counterIsPart): Builder
    {
        $query = $this->candidateArticles($groupId, $poster)->where('b.totalparts', $totalParts);
        $query = $counterIsPart
            ? $query->where('c.totalfiles', $totalParts)->whereColumn('b.filenumber', 'p.partnumber')
            : $query->where('c.totalfiles', $totalFiles)->where('b.filenumber', $fileNumber);

        return $query->select($this->articleColumns());
    }

    /**
     * @param  list<int>  $binaryIds
     * @return list<object>
     */
    private function articlesByBinaryIds(array $binaryIds): array
    {
        $rows = [];
        foreach (array_chunk($binaryIds, $this->config->sqlChunkSize) as $chunk) {
            array_push($rows, ...$this->unreleased(DB::table('collections as c'), 'c.')
                ->join('binaries as b', 'b.collections_id', '=', 'c.id')
                ->join('parts as p', 'p.binaries_id', '=', 'b.id')
                ->whereIn('b.id', $chunk)
                ->whereIn('c.filecheck', $this->mergeableStatuses())
                ->where('b.currentparts', 1)
                ->select($this->articleColumns())
                ->get()
                ->all());
        }
        if (\count($rows) !== \count($binaryIds)) {
            throw new RuntimeException('file changed since planning');
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function articleColumns(): array
    {
        return [
            'b.id as binary_id', 'c.id as collection_id', 'b.name', 'b.totalparts',
            'p.partnumber', 'p.number', 'p.size', 'c.date', 'c.dateadded',
        ];
    }

    private function unreleased(Builder $query, string $prefix = ''): Builder
    {
        return $query->where(static fn (Builder $release) => $release
            ->whereNull($prefix.'releases_id')
            ->orWhere($prefix.'releases_id', 0));
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
