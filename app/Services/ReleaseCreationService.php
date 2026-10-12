<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CollectionFileCheckStatus;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Predb;
use App\Models\Release;
use App\Models\ReleaseRegex;
use App\Services\Categorization\CategorizationService;
use App\Services\Nzb\NzbService;
use App\Services\Releases\ReleaseDuplicateFinder;
use App\Services\Releases\ReleaseGroupResolver;
use App\Services\Releases\Verification\CandidateRepository;
use App\Support\Utf8;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ReleaseCreationService
{
    public function __construct(
        private readonly ReleaseCleaningService $releaseCleaning,
        private readonly CollectionCleanupService $collectionCleanupService,
        private readonly ReleaseDuplicateFinder $releaseDuplicateFinder,
    ) {}

    /**
     * Create releases from complete collections.
     *
     * @param  list<int>|null  $allowedIds
     * @return array{added:int,dupes:int}
     *
     * @throws \Throwable
     */
    public function createReleases(int|string|null $groupID, int $limit, bool $echoCLI, ?array $allowedIds = null): array
    {
        if ($allowedIds === []) {
            return ['added' => 0, 'dupes' => 0];
        }
        if (config('release_verification.required', true)) {
            $ids = $this->collectionCleanupService->verificationReadyCollections()
                ->when(! empty($groupID), static fn ($q) => $q->where('groups_id', $groupID))
                ->when($allowedIds !== null, static fn ($q) => $q->whereIn('id', $allowedIds))
                ->orderBy('id')->limit(max(1, $limit))->pluck('id');
            foreach ($ids as $id) {
                try {
                    app(CandidateRepository::class)->archive((int) $id);
                } catch (\Throwable $error) {
                    // Keep this collection for retry without starving later
                    // candidates or preventing the bounded window cursor saving.
                    Log::warning('Verification archival failed; original headers retained.', [
                        'collection_id' => (int) $id,
                        'error_type' => $error::class,
                    ]);
                }
            }

            return ['added' => 0, 'dupes' => 0];
        }

        $startTime = now()->toImmutable();
        $categorize = new CategorizationService;
        $returnCount = 0;
        $duplicate = 0;

        if ($echoCLI) {
            cli()->header('Process Releases -> Create releases from complete collections.');
        }

        $collectionsQuery = Collection::query()
            ->where('collections.filecheck', CollectionFileCheckStatus::Sized->value)
            ->where('collections.filesize', '>', 0);
        if (! empty($groupID)) {
            $collectionsQuery->where('collections.groups_id', $groupID);
        }
        if ($allowedIds !== null) {
            $collectionsQuery->whereIn('collections.id', $allowedIds);
        }
        $collectionsQuery->select(['collections.*', 'usenet_groups.name as gname'])
            ->join('usenet_groups', 'usenet_groups.id', '=', 'collections.groups_id')
            ->limit($limit);
        $collections = $collectionsQuery->get();
        $releaseGroupIds = $this->loadReleaseGroupIds($collections);

        if ($echoCLI && $collections->count() > 0) {
            cli()->primary(\count($collections).' Collections ready to be converted to releases.', true);
        }

        foreach ($collections as $collection) {
            $cleanRelName = Utf8::clean(str_replace(['#', '@', '$', '%', '^', '§', '¨', '©', 'Ö'], '', $collection->subject));
            $fromName = Utf8::clean(trim($collection->fromname, "'"));

            $cleanedMeta = $this->releaseCleaning->releaseCleaner(
                $collection->subject,
                $collection->fromname,
                $collection->gname
            );

            $namingRegexId = 0;
            if (\is_array($cleanedMeta)) {
                $namingRegexId = isset($cleanedMeta['id']) ? (int) $cleanedMeta['id'] : 0;
            }

            if (\is_array($cleanedMeta)) {
                $properName = $cleanedMeta['properlynamed'] ?? false;
                $preID = max(0, (int) ($cleanedMeta['predb'] ?? 0));
                $cleanedName = $cleanedMeta['cleansubject'] ?? $cleanRelName;
            } else {
                $properName = true;
                $preID = 0;
                $cleanedName = $cleanRelName;
            }

            if ($preID === 0 && $cleanedName !== '') {
                $preMatch = Predb::matchPre($cleanedName);
                if ($preMatch !== false) {
                    $cleanedName = $preMatch['title'];
                    $preID = $preMatch['predb_id'];
                    $properName = true;
                }
            }

            $searchName = ! empty($cleanedName) ? Utf8::clean($cleanedName) : $cleanRelName;
            $predbIdInt = (int) $preID;

            [$dupeCheck, $dupeReason] = $this->releaseDuplicateFinder->findDuplicate(
                $cleanRelName,
                $searchName,
                $predbIdInt,
                (int) $collection->filesize
            );

            if ($dupeCheck === null) {
                $determinedCategory = $categorize->determineCategory($collection->groups_id, $cleanedName, $fromName);

                $releaseID = Release::insertRelease([
                    'name' => $cleanRelName,
                    'searchname' => $searchName,
                    'totalpart' => $collection->totalfiles,
                    'groups_id' => $collection->groups_id,
                    'guid' => Str::uuid()->toString(),
                    'postdate' => $collection->date,
                    'fromname' => $fromName,
                    'size' => $collection->filesize,
                    'categories_id' => $determinedCategory['categories_id'] ?? Category::OTHER_MISC,
                    'isrenamed' => $properName === true ? 1 : 0,
                    'predb_id' => $predbIdInt,
                    'nzbstatus' => NzbService::NZB_NONE,
                ]);

                if ($releaseID !== null) {
                    DB::transaction(static function () use ($collection, $releaseID) {
                        Collection::query()->where('id', $collection->id)->update([
                            'filecheck' => CollectionFileCheckStatus::Inserted->value,
                            'releases_id' => $releaseID,
                        ]);
                    }, 10);

                    ReleaseRegex::insertOrIgnore([
                        'releases_id' => $releaseID,
                        'collection_regex_id' => $collection->collection_regexes_id,
                        'naming_regex_id' => $namingRegexId,
                    ]);

                    $groupRows = [];
                    foreach ($releaseGroupIds[(int) $collection->id] ?? [] as $groupId) {
                        $groupRows[] = ['releases_id' => $releaseID, 'groups_id' => $groupId];
                    }
                    if ($groupRows !== []) {
                        DB::table('releases_groups')->insertOrIgnore($groupRows);
                    }

                    $returnCount++;
                    if ($echoCLI) {
                        echo "Added $returnCount releases.\r";
                    }
                }
            } else {
                Log::info('Release import skipped as duplicate', [
                    'reason' => $dupeReason,
                    'matched_release_id' => $dupeCheck->id,
                    'new_searchname' => $searchName,
                    'existing_searchname' => $dupeCheck->searchname,
                    'new_size' => (int) $collection->filesize,
                    'existing_size' => (int) $dupeCheck->size,
                    'new_fromname' => $fromName,
                    'existing_fromname' => $dupeCheck->fromname,
                    'new_name' => $cleanRelName,
                    'existing_name' => $dupeCheck->name,
                ]);

                $this->collectionCleanupService->deleteCollectionsAndDescendants(
                    [$collection->id],
                    'Duplicate cleanup',
                    $echoCLI
                );

                $duplicate++;
            }
        }

        $totalTime = now()->diffInSeconds($startTime, true);
        if ($echoCLI) {
            cli()->primary(
                PHP_EOL.
                number_format($returnCount).
                ' Releases added and '.
                number_format($duplicate).
                ' duplicate collections deleted in '.
                $totalTime.Str::plural(' second', (int) $totalTime),
                true
            );
        }

        return ['added' => $returnCount, 'dupes' => $duplicate];
    }

    /**
     * Resolve all normalized cross-post groups for the selected collection
     * batch up front, avoiding release/group membership N+1 queries.
     *
     * @param  \Illuminate\Support\Collection<int, Collection>  $collections
     * @return array<int, list<int>>
     */
    private function loadReleaseGroupIds(\Illuminate\Support\Collection $collections): array
    {
        $namesByCollection = [];
        $collectionIds = $collections->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        if (Schema::hasTable('collection_groups')) {
            foreach (array_chunk($collectionIds, 500) as $chunk) {
                foreach (DB::table('collection_groups')->whereIn('collections_id', $chunk)->get() as $row) {
                    $namesByCollection[(int) $row->collections_id][(string) $row->group_name] = true;
                }
            }
        }

        foreach ($collections as $collection) {
            $collectionId = (int) $collection->id;
            if (($namesByCollection[$collectionId] ?? []) === []) {
                $fallback = (new XrefService)->extractGroupNames((string) $collection->xref);
                if ($fallback === []) {
                    $fallback = [(string) $collection->gname];
                }
                foreach ($fallback as $name) {
                    $namesByCollection[$collectionId][$name] = true;
                }
            }
        }

        $allNames = [];
        foreach ($namesByCollection as $names) {
            $allNames += $names;
        }
        $idsByName = app(ReleaseGroupResolver::class)->idsByName(array_keys($allNames));

        $resolved = [];
        foreach ($namesByCollection as $collectionId => $names) {
            foreach (array_keys($names) as $name) {
                if (isset($idsByName[$name])) {
                    $resolved[$collectionId][] = $idsByName[$name];
                }
            }
        }

        foreach ($resolved as &$ids) {
            $ids = array_values(array_unique($ids));
        }
        unset($ids);

        return $resolved;
    }
}
