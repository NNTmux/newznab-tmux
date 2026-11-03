<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CollectionFileCheckStatus;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Predb;
use App\Models\Release;
use App\Models\ReleaseRegex;
use App\Models\UsenetGroup;
use App\Services\Categorization\CategorizationService;
use App\Services\NameFixing\ArtOfUsenetCleanerService;
use App\Services\NameFixing\CategoryPrefixedCleanerService;
use App\Services\NameFixing\HouseOfUsenetCleanerService;
use App\Services\NameFixing\NzbSplitUnwrapper;
use App\Services\NameFixing\QuotedFilenameCleanerService;
use App\Services\NameFixing\ReversedNameDetectorService;
use App\Services\NameFixing\SpamPrefixedFilenameCleanerService;
use App\Services\Nzb\NzbService;
use App\Services\Releases\ReleaseDuplicateFinder;
use App\Support\SqlError;
use App\Support\Utf8;
use App\Traits\DetectsHashedNames;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ReleaseCreationService
{
    use DetectsHashedNames;

    private readonly HouseOfUsenetCleanerService $houCleaner;

    private readonly ArtOfUsenetCleanerService $aouCleaner;

    private readonly QuotedFilenameCleanerService $quotedCleaner;

    private readonly SpamPrefixedFilenameCleanerService $spamPrefixedCleaner;

    private readonly CategoryPrefixedCleanerService $categoryPrefixedCleaner;

    private readonly ReversedNameDetectorService $reversedNameDetector;

    private readonly NzbSplitUnwrapper $nzbSplitUnwrapper;

    public function __construct(
        private readonly ReleaseCleaningService $releaseCleaning,
        private readonly CollectionCleanupService $collectionCleanupService,
        private readonly ReleaseDuplicateFinder $releaseDuplicateFinder,
    ) {
        $this->houCleaner = new HouseOfUsenetCleanerService((bool) config('nntmux.echocli', false));
        $this->aouCleaner = new ArtOfUsenetCleanerService((bool) config('nntmux.echocli', false));
        $this->quotedCleaner = new QuotedFilenameCleanerService((bool) config('nntmux.echocli', false));
        $this->spamPrefixedCleaner = new SpamPrefixedFilenameCleanerService((bool) config('nntmux.echocli', false));
        $this->categoryPrefixedCleaner = new CategoryPrefixedCleanerService((bool) config('nntmux.echocli', false));
        $this->reversedNameDetector = new ReversedNameDetectorService((bool) config('nntmux.echocli', false));
        $this->nzbSplitUnwrapper = new NzbSplitUnwrapper;
    }

    /**
     * Create releases from complete collections.
     *
     * @return array{added:int,dupes:int}
     *
     * @throws \Throwable
     */
    public function createReleases(int|string|null $groupID, int $limit, bool $echoCLI): array
    {
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
        $collectionsQuery->select(['collections.*', 'usenet_groups.name as gname'])
            ->join('usenet_groups', 'usenet_groups.id', '=', 'collections.groups_id')
            ->orderBy('collections.id')
            ->limit($limit);
        $collections = $collectionsQuery->get();
        $releaseGroupIds = $this->loadReleaseGroupIds($collections);

        if ($echoCLI && $collections->count() > 0) {
            cli()->primary(\count($collections).' Collections ready to be converted to releases.', true);
        }

        $dropNzbSplitSamples = (bool) config('nntmux.nzbsplit_require_complete_sets', true);

        foreach ($collections as $collection) {
            $nzbSplit = $this->nzbSplitUnwrapper->parse((string) $collection->subject);

            // A sample-only NZBSPLIT upload is never wanted on its own and would carry the full release's name.
            if ($nzbSplit !== null && $nzbSplit->isSample && $dropNzbSplitSamples) {
                $this->collectionCleanupService->deleteCollectionsAndDescendants(
                    [(int) $collection->id],
                    'NZBSPLIT sample-only',
                    $echoCLI
                );

                continue;
            }

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

            // Process House-of-Usenet style releases to extract a clean name
            $passwordStatus = null;
            $houResult = $this->houCleaner->processSubject($collection->subject);
            if ($houResult['matched']) {
                // Extract clean name if available and better than current
                if ($houResult['name'] !== null && ! $properName) {
                    $cleanedName = $houResult['name'];
                    $properName = true;
                }
            }

            // Process Art-of-Usenet style releases (reversed/obfuscated names)
            if (! $properName) {
                $aouResult = $this->aouCleaner->processSubject($collection->subject);
                if ($aouResult['matched']) {
                    // Extract decoded name if available
                    if ($aouResult['name'] !== null) {
                        $cleanedName = $aouResult['name'];
                        $properName = true;
                        Log::debug('Art-of-Usenet release decoded', [
                            'original' => $aouResult['originalObfuscated'],
                            'decoded' => $aouResult['name'],
                        ]);
                    }
                    // Mark as passworded if [Password] indicator present
                    if ($aouResult['hasPassword']) {
                        $passwordStatus = 1; // Passworded
                    }
                }
            }

            // Process quoted filename style releases (e.g., [1/10] "Release.Name.mkv")
            // Only if not already properly named by previous methods
            if (! $properName) {
                $quotedResult = $this->quotedCleaner->processSubject($collection->subject);
                if ($quotedResult['matched'] && $quotedResult['name'] !== null) {
                    $cleanedName = $quotedResult['name'];
                    $properName = ! $this->isOpaqueRandomToken($cleanedName);
                }
            }

            // Process spam-prefixed subjects (e.g., "(Random Spam Text) [01/66] - "Actual.Release.Name.par2"")
            // These have spam text before the part indicator, with the real name in the quoted filename
            if (! $properName) {
                $spamPrefixedResult = $this->spamPrefixedCleaner->processSubject($collection->subject);
                if ($spamPrefixedResult['matched'] && $spamPrefixedResult['name'] !== null) {
                    $cleanedName = $spamPrefixedResult['name'];
                    $properName = ! $this->isOpaqueRandomToken($cleanedName);
                }
            }

            // Process category-prefixed subjects (e.g., "(Porn Video) Title Here.part01.rar.vol031+32.par2")
            // These have a category tag in parentheses before the actual title
            if (! $properName) {
                $categoryPrefixedResult = $this->categoryPrefixedCleaner->processSubject($collection->subject);
                if ($categoryPrefixedResult['matched'] && $categoryPrefixedResult['name'] !== null) {
                    $cleanedName = $categoryPrefixedResult['name'];
                    $properName = ! $this->isOpaqueRandomToken($cleanedName);
                }
            }

            // Try to decode reversed/obfuscated names (e.g., "2D.20xoB011.furieziloP" -> "Polizeiruf.110Box02.D2")
            // This runs on the current cleanedName to fix any reversed names that slipped through
            if (! empty($cleanedName) && $nzbSplit === null) {
                $reversedResult = $this->reversedNameDetector->decode($cleanedName);
                if ($reversedResult['decoded'] && $reversedResult['confidence'] !== 'low') {
                    // Only apply medium/high confidence decodings automatically
                    $cleanedName = $reversedResult['result'];
                    $properName = true;
                    Log::debug('Reversed release name decoded during creation', [
                        'original' => $reversedResult['original'],
                        'decoded' => $reversedResult['result'],
                        'confidence' => $reversedResult['confidence'],
                    ]);
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
                $determinedCategory = $categorize->determineCategory(
                    $collection->groups_id,
                    $cleanedName,
                    $fromName,
                    sourceName: (string) $collection->subject,
                );

                $releaseData = [
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
                ];

                // Add password status if detected (e.g., from Art-of-Usenet [Password] indicator)
                if ($passwordStatus !== null) {
                    $releaseData['passwordstatus'] = $passwordStatus;
                }

                $releaseID = $this->insertReleaseClaimingCollection($releaseData, (int) $collection->id);

                if ($releaseID !== null) {
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
     * Insert the release and claim its collection in one transaction.
     *
     * Overlapping runs over the same group (per-group workers, the global
     * pass, manual runs) can select the same Sized collection. Only the run
     * whose guarded UPDATE flips it to Inserted keeps its release; the other
     * rolls back, so no duplicate release is created and no release is left
     * without collections.
     *
     * @param  array<string, mixed>  $releaseData
     * @return int|null the new release id, or null when another run already claimed the collection
     */
    private function insertReleaseClaimingCollection(array $releaseData, int $collectionId): ?int
    {
        for ($attempt = 1; ; $attempt++) {
            DB::beginTransaction();
            try {
                $releaseId = Release::insertRelease($releaseData);
                $claimed = $releaseId === null ? 0 : Collection::query()
                    ->whereKey($collectionId)
                    ->where('filecheck', CollectionFileCheckStatus::Sized->value)
                    ->whereNull('releases_id')
                    ->update([
                        'filecheck' => CollectionFileCheckStatus::Inserted->value,
                        'releases_id' => $releaseId,
                    ]);

                if ($claimed !== 1) {
                    DB::rollBack();

                    return null;
                }

                DB::commit();

                return (int) $releaseId;
            } catch (\Throwable $e) {
                DB::rollBack();
                if ($attempt >= 5 || ! SqlError::isTransientLock($e)) {
                    throw $e;
                }
                usleep((min(500, 50 * $attempt) + random_int(0, 25)) * 1000);
            }
        }
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
        $idsByName = UsenetGroup::query()
            ->whereIn('name', array_keys($allNames))
            ->pluck('id', 'name')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        foreach (array_keys($allNames) as $name) {
            $validName = UsenetGroup::isValidGroup($name);
            if ($validName === false || isset($idsByName[$validName])) {
                continue;
            }
            $idsByName[$validName] = (int) UsenetGroup::addGroup([
                'name' => $validName,
                'description' => 'Added by Release processing',
                'backfill_target' => 1,
                'first_record' => 0,
                'last_record' => 0,
                'active' => 0,
                'backfill' => 0,
                'minfilestoformrelease' => '',
                'minsizetoformrelease' => '',
            ]);
        }

        $resolved = [];
        foreach ($namesByCollection as $collectionId => $names) {
            foreach (array_keys($names) as $name) {
                if (isset($idsByName[$name])) {
                    $resolved[$collectionId][] = $idsByName[$name];
                }
            }
        }

        return $resolved;
    }
}
