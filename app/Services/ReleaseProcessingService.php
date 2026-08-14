<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CollectionFileCheckStatus;
use App\Models\Category;
use App\Models\Collection;
use App\Models\MusicInfo;
use App\Models\Release;
use App\Models\ReleaseNzbCreationFailure;
use App\Models\Settings;
use App\Models\UsenetGroup;
use App\Services\Binaries\BinariesConfig;
use App\Services\Categorization\CategorizationService;
use App\Services\NNTP\NNTPService;
use App\Services\Nzb\NzbCreationCandidateQuery;
use App\Services\Nzb\NzbService;
use App\Services\Releases\ReleaseBrowseService;
use App\Services\Releases\ReleaseDuplicateFinder;
use App\Services\Releases\ReleaseManagementService;
use App\Support\Data\NzbCreationResult;
use App\Support\Data\ProcessReleasesSettings;
use App\Support\Data\ReleaseCreationResult;
use App\Support\Data\ReleaseDeleteStats;
use App\Support\ReleaseSearchIndexSync;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Service for processing collections into releases and creating NZB files.
 *
 * This service handles the complete release processing pipeline:
 * - Finding complete collections
 * - Calculating collection sizes
 * - Creating releases from collections
 * - Generating NZB files
 * - Categorizing releases
 * - Cleanup of old/unwanted releases
 */
final class ReleaseProcessingService
{
    private const int BATCH_SIZE = 500;

    private const int MAX_RETRIES = 5;

    private const int RETRY_BASE_DELAY_US = 20000;

    private const int BATCH_PAUSE_US = 10000;

    private const int CATEGORIZE_CHUNK_SIZE = 1000;

    private const int NZB_CREATION_MAX_ATTEMPTS = 3;

    private bool $echoCLI;

    private readonly ProcessReleasesSettings $settings;

    private readonly NzbService $nzb;

    private readonly ReleaseCleaningService $releaseCleaning;

    private readonly ReleaseManagementService $releaseManagement;

    private readonly ReleaseImageService $releaseImage;

    private readonly ReleaseCreationService $releaseCreationService;

    private readonly CollectionCleanupService $collectionCleanupService;

    private readonly ?PostProcessService $postProcessService;

    private readonly BinariesConfig $binariesConfig;

    public function __construct(
        ?NzbService $nzb = null,
        ?ReleaseCleaningService $releaseCleaning = null,
        ?ReleaseManagementService $releaseManagement = null,
        ?ReleaseImageService $releaseImage = null,
        ?ReleaseCreationService $releaseCreationService = null,
        ?CollectionCleanupService $collectionCleanupService = null,
        ?PostProcessService $postProcessService = null,
        ?BinariesConfig $binariesConfig = null,
    ) {
        $this->echoCLI = (bool) config('nntmux.echocli');

        $this->nzb = $nzb ?? app(NzbService::class);
        $this->releaseCleaning = $releaseCleaning ?? new ReleaseCleaningService;
        $this->releaseManagement = $releaseManagement ?? app(ReleaseManagementService::class);
        $this->releaseImage = $releaseImage ?? new ReleaseImageService;
        $this->collectionCleanupService = $collectionCleanupService
            ?? new CollectionCleanupService;

        $this->releaseCreationService = $releaseCreationService
            ?? new ReleaseCreationService(
                $this->releaseCleaning,
                $this->collectionCleanupService,
                app(ReleaseDuplicateFinder::class)
            );
        $this->postProcessService = $postProcessService;
        $this->binariesConfig = $binariesConfig ?? BinariesConfig::fromSettings();

        $this->settings = $this->loadSettings();
        $this->validateSettings();
    }

    /**
     * Load all required settings from database.
     */
    private function loadSettings(): ProcessReleasesSettings
    {
        $settingKeys = [
            'delaytime', 'crossposttime', 'maxnzbsprocessed', 'completionpercent',
            'collection_timeout', 'maxsizetoformrelease', 'minsizetoformrelease',
            'minfilestoformrelease', 'releaseretentiondays', 'deletepasswordedrelease',
            'miscotherretentionhours', 'mischashedretentionhours', 'partretentionhours',
            'last_run_time',
        ];

        $dbSettings = [];
        foreach ($settingKeys as $key) {
            $dbSettings[$key] = Settings::settingValue($key);
        }

        return ProcessReleasesSettings::forDatabase($dbSettings);
    }

    /**
     * Validate loaded settings and warn about invalid configurations.
     */
    private function validateSettings(): void
    {
        if (! $this->settings->hasValidCompletion()) {
            cli()->error(
                PHP_EOL.'Invalid completion setting. Value must be between 0 and 100.'
            );
        }
    }

    // ========================================================================
    // Public API
    // ========================================================================

    /**
     * Get the current completion percentage setting.
     */
    public function getCompletion(): int
    {
        return $this->settings->completion;
    }

    /**
     * Get the release creation limit.
     */
    public function getReleaseCreationLimit(): int
    {
        return $this->settings->releaseCreationLimit;
    }

    /**
     * Get the collection delay time in hours.
     */
    public function getCollectionDelayTime(): int
    {
        return $this->settings->collectionDelayTime;
    }

    /**
     * Get the cross-post detection time window in hours.
     */
    public function getCrossPostTime(): int
    {
        return $this->settings->crossPostTime;
    }

    /**
     * Check if CLI echo is enabled.
     */
    public function isEchoCLI(): bool
    {
        return $this->echoCLI;
    }

    /**
     * Set CLI echo mode.
     */
    public function setEchoCLI(bool $echo): self
    {
        $this->echoCLI = $echo;

        return $this;
    }

    /**
     * Main method for creating releases/NZB files from collections.
     *
     * @param  int  $categorize  Categorization type (1=name, 2=searchname)
     * @param  int  $postProcess  Whether to run post-processing (1=yes)
     * @param  string  $groupName  Optional group name to filter processing
     * @param  NNTPService  $nntp  NNTP connection for post-processing
     * @return int Total number of releases added
     *
     * @throws Throwable
     */
    public function processReleases(
        int $categorize,
        int $postProcess,
        string $groupName,
        NNTPService $nntp
    ): int {
        $this->echoCLI = (bool) config('nntmux.echocli');
        $overallStartTime = now()->toImmutable();

        $this->outputBanner();
        if (! $this->validateNzbPath()) {
            return 0;
        }

        $groupID = $this->resolveGroupId($groupName);
        $normalizedGroupId = $this->normalizeGroupId($groupID);

        if ($this->echoCLI && $groupName !== '') {
            $this->outputInfo("Processing group: {$groupName}");
        }

        // Phase 1: Collection processing
        $this->outputHeader('Phase 1: Collection Processing');
        $this->processIncompleteCollections($normalizedGroupId);
        $this->processCollectionSizes($normalizedGroupId);
        $this->deleteUnwantedCollections($normalizedGroupId);

        // Phase 2: Release creation loop
        $this->outputHeader('Phase 2: Release Creation');
        $totals = $this->runReleaseCreationLoop($normalizedGroupId, $categorize, $postProcess, $nntp);

        // Phase 3: Cleanup
        $this->outputHeader('Phase 3: Cleanup');
        $this->deleteReleases();

        $this->outputFinalSummary(
            $totals['releases'],
            $totals['nzbs'],
            $totals['dupes'],
            $totals['iterations'],
            $overallStartTime
        );

        return $totals['releases'];
    }

    /**
     * Run the release creation loop.
     *
     * @return array{releases: int, nzbs: int, dupes: int, iterations: int}
     *
     * @throws Throwable
     */
    private function runReleaseCreationLoop(
        ?int $normalizedGroupId,
        int $categorize,
        int $postProcess,
        NNTPService $nntp
    ): array {
        $totals = ['releases' => 0, 'nzbs' => 0, 'dupes' => 0, 'iterations' => 0];
        $limit = $this->settings->releaseCreationLimit;

        do {
            $totals['iterations']++;

            $result = $this->createReleases($normalizedGroupId);
            $totals['releases'] += $result->added;
            $totals['dupes'] += $result->dupes;

            $nzbFilesAdded = $this->createNZBs($normalizedGroupId);
            $totals['nzbs'] += $nzbFilesAdded;

            $this->categorizeReleases($categorize, $normalizedGroupId);
            $this->postProcessReleases($postProcess, $nntp);
            $this->deleteCollections($normalizedGroupId);

            $shouldContinue = $result->total() >= $limit || $nzbFilesAdded >= $limit;
        } while ($shouldContinue);

        return $totals;
    }

    /**
     * Reset all releases to other->misc category.
     */
    public function resetCategorize(string $where = ''): void
    {
        if ($where !== '') {
            DB::update(
                'UPDATE releases SET categories_id = ?, iscategorized = 0 '.$where,
                [Category::OTHER_MISC]
            );
        } else {
            Release::query()->update([
                'categories_id' => Category::OTHER_MISC,
                'iscategorized' => 0,
            ]);
        }

        ReleaseSearchIndexSync::reindexMatchingWhere($where);
    }

    /**
     * Categorize a release using the specified type.
     *
     * @throws \Exception
     */
    public function categorizeRelease(string $type, int|string|null $groupId): int
    {
        $categorizer = new CategorizationService;
        $categorized = 0;

        $query = Release::query()
            ->where('categories_id', Category::OTHER_MISC)
            ->where('iscategorized', 0)
            ->select(['id', 'fromname', 'groups_id', $type]);

        if (! empty($groupId)) {
            $query->where('groups_id', $groupId);
        }

        $total = $query->count();
        if ($total === 0) {
            return 0;
        }

        $this->outputSubHeader('Categorizing Releases');

        $query->chunkById(self::CATEGORIZE_CHUNK_SIZE, function ($releases) use ($categorizer, $type, &$categorized, $total): bool {
            foreach ($releases as $release) {
                $categoryResult = $categorizer->determineCategory(
                    $release->groups_id,
                    $release->{$type},
                    $release->fromname
                );

                Release::query()
                    ->where('id', $release->id)
                    ->update([
                        'categories_id' => $categoryResult['categories_id'],
                        'iscategorized' => 1,
                    ]);

                ReleaseSearchIndexSync::forIds([(int) $release->id]);

                $categorized++;
                $this->outputProgress($categorized, $total, 'Categorizing');
            }

            return true;
        });

        return $categorized;
    }

    /**
     * Process incomplete collections to find complete ones.
     *
     * @throws Throwable
     */
    public function processIncompleteCollections(int|string|null $groupID): void
    {
        $startTime = now()->toImmutable();
        $this->outputSubHeader('Finding Complete Collections');

        $normalizedGroupId = $this->normalizeGroupId($groupID);
        $this->processStuckCollections($normalizedGroupId ?? 0);
        $this->reconcileIncompleteCollections($normalizedGroupId);

        $count = $this->countCompleteCollections($normalizedGroupId);
        $this->outputStat('Complete collections found', $count);
        $this->outputElapsedTime($startTime);
    }

    /**
     * Calculate sizes for complete collections.
     *
     * @throws Throwable
     */
    public function processCollectionSizes(int|string|null $groupID): void
    {
        $startTime = now()->toImmutable();
        $this->outputSubHeader('Calculating Collection Sizes');

        $updated = 0;
        $lastId = 0;
        $normalizedGroupId = $this->normalizeGroupId($groupID);
        do {
            $query = Collection::query()
                ->where('id', '>', $lastId)
                ->where('filecheck', CollectionFileCheckStatus::CompleteParts->value)
                ->when($normalizedGroupId !== null, static fn ($q) => $q->where('groups_id', $normalizedGroupId))
                ->orderBy('id')
                ->limit($this->binariesConfig->reconcileBatchSize);
            $ids = $query->pluck('id')->map(static fn ($id): int => (int) $id)->all();
            if ($ids === []) {
                break;
            }
            $lastId = (int) end($ids);
            $updated += Collection::query()->whereIn('id', $ids)->update([
                'filecheck' => CollectionFileCheckStatus::Sized->value,
            ]);
        } while (\count($ids) === $this->binariesConfig->reconcileBatchSize);

        $this->outputStat('Collections sized', $updated);
        $this->outputElapsedTime($startTime);
    }

    /**
     * Reconcile only a bounded keyset page at a time. Stored parts are the
     * authority for binary counts/sizes; binary aggregates are then the
     * authority for collection readiness and filesize.
     */
    private function reconcileIncompleteCollections(?int $groupId): void
    {
        $lastId = 0;
        $statuses = [
            CollectionFileCheckStatus::Default->value,
            CollectionFileCheckStatus::CompleteCollection->value,
            CollectionFileCheckStatus::CompleteParts->value,
            CollectionFileCheckStatus::TempComplete->value,
            CollectionFileCheckStatus::ZeroPart->value,
            10,
        ];

        do {
            $query = Collection::query()
                ->where('id', '>', $lastId)
                ->where(function ($query) use ($statuses): void {
                    $query->where('filecheck', CollectionFileCheckStatus::CompleteParts->value);
                    if (Schema::hasColumn('collections', 'last_seen_at')) {
                        $query->orWhere(function ($stale) use ($statuses): void {
                            $stale->whereIn('filecheck', array_values(array_diff(
                                $statuses,
                                [CollectionFileCheckStatus::CompleteParts->value]
                            )))->whereRaw(
                                'COALESCE(last_seen_at, dateadded, added) < ?',
                                [now()->subHours($this->settings->collectionDelayTime)]
                            );
                        });
                    } else {
                        $query->orWhereIn('filecheck', array_values(array_diff(
                            $statuses,
                            [CollectionFileCheckStatus::CompleteParts->value]
                        )));
                    }
                })
                ->when($groupId !== null, static fn ($q) => $q->where('groups_id', $groupId))
                ->orderBy('id')
                ->limit($this->binariesConfig->reconcileBatchSize);
            $ids = $query->pluck('id')->map(static fn ($id): int => (int) $id)->all();
            if ($ids === []) {
                break;
            }
            $lastId = (int) end($ids);
            $this->reconcileCollectionIds($ids, $statuses);
        } while (\count($ids) === $this->binariesConfig->reconcileBatchSize);
    }

    /**
     * @param  list<int>  $collectionIds
     * @param  list<int>  $statuses
     */
    private function reconcileCollectionIds(array $collectionIds, array $statuses): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->reconcileCollectionIdsSqlite($collectionIds, $statuses);

            return;
        }

        $idPlaceholders = implode(',', array_fill(0, \count($collectionIds), '?'));
        $statusPlaceholders = implode(',', array_fill(0, \count($statuses), '?'));

        DB::transaction(function () use ($collectionIds, $idPlaceholders, $statuses, $statusPlaceholders): void {
            DB::update(
                "UPDATE binaries b
                 LEFT JOIN (
                    SELECT p.binaries_id, COUNT(*) currentparts, COALESCE(SUM(p.size), 0) partsize
                    FROM parts p INNER JOIN binaries selected ON selected.id = p.binaries_id
                    WHERE selected.collections_id IN ({$idPlaceholders}) GROUP BY p.binaries_id
                 ) p ON p.binaries_id = b.id
                 SET b.currentparts = COALESCE(p.currentparts, 0),
                     b.partsize = COALESCE(p.partsize, 0),
                     b.partcheck = CASE WHEN COALESCE(p.currentparts, 0) >= b.totalparts THEN 1 ELSE 0 END
                 WHERE b.collections_id IN ({$idPlaceholders})",
                [...$collectionIds, ...$collectionIds]
            );

            DB::update(
                "UPDATE collections c
                 LEFT JOIN (
                    SELECT b.collections_id, COUNT(*) currentfiles,
                           COALESCE(SUM(CASE WHEN b.partcheck = 1 THEN 1 ELSE 0 END), 0) completefiles,
                           COALESCE(SUM(b.partsize), 0) filesize
                    FROM binaries b WHERE b.collections_id IN ({$idPlaceholders}) GROUP BY b.collections_id
                 ) a ON a.collections_id = c.id
                 SET c.filesize = COALESCE(a.filesize, 0),
                     c.totalfiles = CASE
                        WHEN c.dateadded < DATE_SUB(NOW(), INTERVAL ? HOUR)
                         AND c.filecheck IN (0, 1, 10)
                        THEN COALESCE(a.currentfiles, 0) ELSE c.totalfiles END,
                     c.filecheck = CASE
                        WHEN c.totalfiles > 0
                         AND COALESCE(a.currentfiles, 0) IN (c.totalfiles, c.totalfiles + 1)
                         AND COALESCE(a.completefiles, 0) >= c.totalfiles THEN ?
                        WHEN c.dateadded < DATE_SUB(NOW(), INTERVAL ? HOUR)
                         AND c.filecheck IN (0, 1, 10) THEN ?
                        ELSE c.filecheck END
                 WHERE c.id IN ({$idPlaceholders}) AND c.filecheck IN ({$statusPlaceholders})",
                [
                    ...$collectionIds,
                    $this->settings->collectionDelayTime,
                    CollectionFileCheckStatus::CompleteParts->value,
                    $this->settings->collectionDelayTime,
                    CollectionFileCheckStatus::CompleteParts->value,
                    ...$collectionIds,
                    ...$statuses,
                ]
            );
        }, self::MAX_RETRIES);
    }

    /**
     * @param  list<int>  $collectionIds
     * @param  list<int>  $statuses
     */
    private function reconcileCollectionIdsSqlite(array $collectionIds, array $statuses): void
    {
        DB::transaction(function () use ($collectionIds, $statuses): void {
            foreach ($collectionIds as $collectionId) {
                $binaryIds = DB::table('binaries')->where('collections_id', $collectionId)->pluck('id')->all();
                foreach ($binaryIds as $binaryId) {
                    $aggregate = DB::selectOne(
                        'SELECT COUNT(*) currentparts, COALESCE(SUM(size), 0) partsize FROM parts WHERE binaries_id = ?',
                        [$binaryId]
                    );
                    DB::update(
                        'UPDATE binaries SET currentparts = ?, partsize = ?, partcheck = CASE WHEN ? >= totalparts THEN 1 ELSE 0 END WHERE id = ?',
                        [(int) $aggregate->currentparts, (int) $aggregate->partsize, (int) $aggregate->currentparts, $binaryId]
                    );
                }

                $collection = DB::table('collections')->where('id', $collectionId)->first();
                if ($collection === null || ! \in_array((int) $collection->filecheck, $statuses, true)) {
                    continue;
                }
                $aggregate = DB::selectOne(
                    'SELECT COUNT(*) currentfiles,
                            COALESCE(SUM(CASE WHEN partcheck = 1 THEN 1 ELSE 0 END), 0) completefiles,
                            COALESCE(SUM(partsize), 0) filesize
                     FROM binaries WHERE collections_id = ?',
                    [$collectionId]
                );
                $stale = strtotime((string) $collection->dateadded) < now()->subHours($this->settings->collectionDelayTime)->timestamp
                    && \in_array((int) $collection->filecheck, [0, 1, 10], true);
                $totalFiles = $stale ? (int) $aggregate->currentfiles : (int) $collection->totalfiles;
                $ready = $totalFiles > 0
                    && \in_array((int) $aggregate->currentfiles, [$totalFiles, $totalFiles + 1], true)
                    && (int) $aggregate->completefiles >= $totalFiles;
                DB::table('collections')->where('id', $collectionId)->update([
                    'filesize' => (int) $aggregate->filesize,
                    'totalfiles' => $totalFiles,
                    'filecheck' => $ready || $stale
                        ? CollectionFileCheckStatus::CompleteParts->value
                        : (int) $collection->filecheck,
                ]);
            }
        }, self::MAX_RETRIES);
    }

    /**
     * Delete collections that don't meet size/file count requirements.
     *
     * @throws Throwable
     */
    public function deleteUnwantedCollections(int|string|null $groupID): void
    {
        $startTime = now()->toImmutable();
        $this->outputSubHeader('Filtering Collections by Size/File Count');

        $normalizedGroupId = $this->normalizeGroupId($groupID);
        $groupIDs = $normalizedGroupId === null
            ? UsenetGroup::getActiveIDs()
            : [['id' => $normalizedGroupId]];

        $stats = ['minSize' => 0, 'maxSize' => 0, 'minFiles' => 0, 'par2Only' => 0];

        // Delete collections where ALL binaries are par2 files (no actual content)
        $par2OnlyCollectionIds = DB::table('collections as c')
            ->join('binaries as b', 'c.id', '=', 'b.collections_id')
            ->where('c.filecheck', CollectionFileCheckStatus::Sized->value)
            ->where('c.filesize', '>', 0)
            ->groupBy('c.id')
            ->havingRaw("COUNT(b.id) = SUM(CASE WHEN b.name REGEXP '\\\\.(vol[0-9]+\\\\+[0-9]+\\\\.par2|par2)' THEN 1 ELSE 0 END)");
        if ($normalizedGroupId !== null) {
            $par2OnlyCollectionIds->where('c.groups_id', $normalizedGroupId);
        }
        $stats['par2Only'] += $this->deleteCollectionQueryInBatches($par2OnlyCollectionIds, 'Par2-only cleanup');

        foreach ($groupIDs as $grpID) {
            $groupSettings = UsenetGroup::getGroupByID($grpID['id']);
            $groupMinSize = (int) ($groupSettings['minsizetoformrelease'] ?? 0);
            $groupMinFiles = (int) ($groupSettings['minfilestoformrelease'] ?? 0);

            if (! $this->hasSizedCollections((int) $grpID['id'])) {
                continue;
            }

            $effectiveMinSize = max($groupMinSize, $this->settings->minSizeToFormRelease);
            if ($effectiveMinSize > 0) {
                $ids = Collection::query()
                    ->where('filecheck', CollectionFileCheckStatus::Sized->value)
                    ->where('groups_id', (int) $grpID['id'])
                    ->where('filesize', '>', 0)
                    ->where('filesize', '<', $effectiveMinSize);
                $stats['minSize'] += $this->deleteCollectionQueryInBatches($ids, 'Min-size cleanup');
            }

            if ($this->settings->maxSizeToFormRelease > 0) {
                $ids = Collection::query()
                    ->where('filecheck', CollectionFileCheckStatus::Sized->value)
                    ->where('groups_id', (int) $grpID['id'])
                    ->where('filesize', '>', $this->settings->maxSizeToFormRelease);
                $stats['maxSize'] += $this->deleteCollectionQueryInBatches($ids, 'Max-size cleanup');
            }

            $effectiveMinFiles = max($groupMinFiles, $this->settings->minFilesToFormRelease);
            if ($effectiveMinFiles > 0) {
                $ids = Collection::query()
                    ->where('filecheck', CollectionFileCheckStatus::Sized->value)
                    ->where('groups_id', (int) $grpID['id'])
                    ->where('filesize', '>', 0)
                    ->where('totalfiles', '<', $effectiveMinFiles);
                $stats['minFiles'] += $this->deleteCollectionQueryInBatches($ids, 'Min-files cleanup');
            }
        }

        $this->outputCollectionDeleteStats($stats, $startTime);
    }

    /**
     * Create releases from complete collections.
     *
     * @throws Throwable
     */
    public function createReleases(int|string|null $groupID): ReleaseCreationResult
    {
        $result = $this->releaseCreationService->createReleases(
            $groupID,
            $this->settings->releaseCreationLimit,
            $this->echoCLI
        );

        return ReleaseCreationResult::from($result);
    }

    /**
     * Create NZB files from releases that don't have them yet.
     *
     * @throws Throwable
     */
    public function createNZBs(int|string|null $groupID): int
    {
        $startTime = now()->toImmutable();
        $this->outputSubHeader('Creating NZB Files');

        $nzbCount = 0;
        $retryCount = 0;
        $deletedCount = 0;
        $claimToken = bin2hex(random_bytes(16));
        $limit = max(1, $this->settings->releaseCreationLimit);
        $total = min(NzbCreationCandidateQuery::baseBuilder($groupID)->count(), $limit);

        if ($total > 0) {
            $columns = [
                'id',
                'guid',
                'name',
                'categories_id',
                'groups_id',
                'postdate',
                'nzbstatus',
                NzbCreationCandidateQuery::CLAIM_TOKEN_COLUMN,
            ];

            $processed = 0;
            $releases = NzbCreationCandidateQuery::claimBatch($groupID, $limit, $claimToken, $columns);
            foreach ($releases as $release) {
                try {
                    $result = $this->nzb->createNzbForRelease($release);
                    if ($result->success) {
                        $nzbCount++;

                        continue;
                    }

                    if ($this->shouldDeleteFailedNzbCreation($release, $result)) {
                        $this->deleteFailedNzbCreationRelease($release, $result, $claimToken);
                        $deletedCount++;

                        continue;
                    }

                    $this->recordNzbCreationRetry($release, $result);
                    $retryCount++;
                } catch (Throwable $e) {
                    $result = NzbCreationResult::transient('Unexpected NZB creation failure: '.$e->getMessage());
                    if ($this->shouldDeleteFailedNzbCreation($release, $result)) {
                        $this->deleteFailedNzbCreationRelease($release, $result, $claimToken);
                        $deletedCount++;
                    } else {
                        $this->recordNzbCreationRetry($release, $result);
                        $retryCount++;
                    }
                } finally {
                    NzbCreationCandidateQuery::clearClaim((int) $release->id, $claimToken);
                    $processed++;
                    $this->outputProgress($processed, $total, 'Creating NZBs');
                }
            }
        }

        $this->outputStat('NZBs created', $nzbCount);
        $this->outputStat('NZB creation retries', $retryCount);
        $this->outputStat('NZB creation failures deleted', $deletedCount);
        $this->outputElapsedTime($startTime);

        return $nzbCount;
    }

    private function shouldDeleteFailedNzbCreation(Release $release, NzbCreationResult $result): bool
    {
        if ($result->isDeterministicFailure()) {
            return true;
        }

        if (! $result->isTransientFailure()) {
            return false;
        }

        return $this->nextNzbCreationAttempt($release) >= self::NZB_CREATION_MAX_ATTEMPTS;
    }

    private function nextNzbCreationAttempt(Release $release): int
    {
        $failureState = $release->relationLoaded('nzbCreationFailure')
            ? $release->getRelation('nzbCreationFailure')
            : null;

        return ($failureState instanceof ReleaseNzbCreationFailure ? $failureState->attempts : 0) + 1;
    }

    private function recordNzbCreationRetry(Release $release, NzbCreationResult $result): void
    {
        $nextAttempt = $this->nextNzbCreationAttempt($release);
        ReleaseNzbCreationFailure::query()->upsert(
            [[
                'releases_id' => (int) $release->id,
                'attempts' => $nextAttempt,
                'last_error' => mb_substr($result->reason, 0, 1000),
            ]],
            ['releases_id'],
            ['attempts', 'last_error'],
        );
        $failure = ReleaseNzbCreationFailure::query()->findOrFail((int) $release->id);
        $release->setRelation('nzbCreationFailure', $failure);

        Log::channel('nzb_creation')->warning('NZB creation failed; release will be retried', [
            'release_id' => $release->id,
            'guid' => $release->guid,
            'failure_type' => $result->failureType,
            'reason' => $result->reason,
            'next_attempt' => $nextAttempt,
            'max_attempts' => self::NZB_CREATION_MAX_ATTEMPTS,
        ]);
    }

    private function deleteFailedNzbCreationRelease(Release $release, NzbCreationResult $result, string $claimToken): void
    {
        Log::channel('nzb_creation')->warning('Deleting release after NZB creation failure', [
            'release_id' => $release->id,
            'guid' => $release->guid,
            'failure_type' => $result->failureType,
            'reason' => $result->reason,
            'attempt' => $this->nextNzbCreationAttempt($release),
            'max_attempts' => self::NZB_CREATION_MAX_ATTEMPTS,
        ]);

        try {
            $collectionIds = $result->collectionIds !== []
                ? $result->collectionIds
                : Collection::query()
                    ->where('releases_id', $release->id)
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all();

            if ($collectionIds !== []) {
                $this->collectionCleanupService->deleteCollectionsAndDescendants(
                    $collectionIds,
                    'Failed NZB creation cleanup',
                    $this->echoCLI
                );
            }

            $this->releaseManagement->deleteSingleWithService(
                ['g' => $release->guid, 'i' => $release->id],
                $this->nzb,
                $this->releaseImage
            );
        } finally {
            NzbCreationCandidateQuery::clearClaim((int) $release->id, $claimToken);
        }
    }

    /**
     * Categorize releases based on the specified field.
     *
     * @throws \Exception
     */
    public function categorizeReleases(int $categorize, int|string|null $groupID = null): void
    {
        $startTime = now()->toImmutable();

        $type = match ($categorize) {
            2 => 'searchname',
            default => 'name',
        };

        $count = $this->categorizeRelease($type, $groupID);

        if ($count > 0) {
            $this->outputStat('Releases categorized', $count);
            $this->outputElapsedTime($startTime);
        }
    }

    /**
     * Run post-processing on releases.
     *
     * @throws \Exception
     */
    public function postProcessReleases(int $postProcess, NNTPService $nntp): void
    {
        if ($postProcess !== 1) {
            return;
        }

        $this->outputSubHeader('Post-Processing Releases');

        $service = $this->postProcessService ?? new PostProcessService;
        $service->processAll($nntp);
    }

    /**
     * Delete finished and orphaned collections.
     *
     * @throws Throwable
     */
    public function deleteCollections(int|string|null $groupID): void
    {
        $this->collectionCleanupService->deleteFinishedAndOrphans($this->echoCLI);
    }

    /**
     * Delete unwanted releases based on group-specific settings.
     *
     * @throws \Exception
     */
    public function deletedReleasesByGroup(int|string $groupID = ''): void
    {
        $startTime = now()->toImmutable();
        $stats = ['minSize' => 0, 'maxSize' => 0, 'minFiles' => 0];

        if ($this->echoCLI) {
            cli()->header(
                'Process Releases -> Delete releases smaller/larger than minimum size/file count from group/site setting.'
            );
        }

        $groupIDs = $groupID === ''
            ? UsenetGroup::getActiveIDs()
            : [['id' => $groupID]];

        foreach ($groupIDs as $grpID) {
            $this->deleteReleasesUnderMinSize($grpID['id'], $stats);
            $this->deleteReleasesOverMaxSize($grpID['id'], $stats);
            $this->deleteReleasesUnderMinFiles($grpID['id'], $stats);
        }

        $this->outputReleaseDeleteByGroupStats($stats, $startTime);
    }

    /**
     * Delete releases based on site-wide settings.
     *
     * @throws \Exception
     */
    public function deleteReleases(): void
    {
        $startTime = now()->toImmutable();
        $this->outputSubHeader('Removing Unwanted Releases');

        $stats = new ReleaseDeleteStats;

        $stats = $this->deleteReleasesOverRetention($stats);
        $stats = $this->deletePasswordedReleases($stats);
        $stats = $this->deleteCrossPostedReleases($stats);
        $stats = $this->deleteIncompleteReleases($stats);
        $stats = $this->deleteDisabledCategoryReleases($stats);
        $stats = $this->deleteCategoryMinSizeReleases($stats);
        $stats = $this->deleteDisabledGenreReleases($stats);
        $stats = $this->deleteMiscReleases($stats);

        $this->outputReleaseDeleteStats($stats, $startTime);
    }

    // ========================================================================
    // Private Helper Methods
    // ========================================================================

    private function validateNzbPath(): bool
    {
        $nzbPath = config('nntmux_settings.path_to_nzbs');

        if (! file_exists($nzbPath)) {
            if ($this->echoCLI) {
                cli()->error("Bad or missing NZB directory - {$nzbPath}");
            }

            return false;
        }

        return true;
    }

    private function resolveGroupId(string $groupName): string
    {
        if ($groupName === '') {
            return '';
        }

        $groupInfo = UsenetGroup::getByName($groupName);

        return $groupInfo !== null ? (string) $groupInfo['id'] : '';
    }

    private function countCompleteCollections(?int $groupId): int
    {
        $query = Collection::query()
            ->where('filecheck', CollectionFileCheckStatus::CompleteParts->value);

        if ($groupId !== null) {
            $query->where('groups_id', $groupId);
        }

        return $query->count('id');
    }

    private function hasSizedCollections(int $groupId): bool
    {
        return Collection::query()
            ->where('filecheck', CollectionFileCheckStatus::Sized->value)
            ->where('groups_id', $groupId)
            ->where('filesize', '>', 0)
            ->exists();
    }

    /** @param Builder|\Illuminate\Database\Eloquent\Builder<Collection> $query */
    private function deleteCollectionQueryInBatches(
        Builder|\Illuminate\Database\Eloquent\Builder $query,
        string $label,
    ): int {
        $deleted = 0;
        $idColumn = $query instanceof \Illuminate\Database\Eloquent\Builder ? 'id' : 'c.id';
        do {
            $ids = (clone $query)->orderBy($idColumn)->limit(self::BATCH_SIZE)->pluck($idColumn)->all();
            if ($ids === []) {
                break;
            }
            $affected = $this->collectionCleanupService->deleteCollectionsAndDescendants(
                array_map('intval', $ids),
                $label,
                $this->echoCLI
            );
            $deleted += $affected;
            if ($affected < self::BATCH_SIZE) {
                break;
            }
        } while (true);

        return $deleted;
    }

    private function normalizeGroupId(int|string|null $groupID): ?int
    {
        if ($groupID === null || $groupID === '') {
            return null;
        }

        if (is_numeric($groupID)) {
            return (int) $groupID;
        }

        $groupInfo = UsenetGroup::getByName($groupID);

        return $groupInfo !== null ? (int) $groupInfo['id'] : null;
    }

    /**
     * @throws Throwable
     */
    private function processStuckCollections(int $groupID): void
    {
        $cutoff = $this->calculateStuckCollectionsCutoff();
        $totalDeleted = 0;

        do {
            $affected = $this->deleteStuckCollectionBatch($groupID, $cutoff);
            $totalDeleted += $affected;

            if ($affected < self::BATCH_SIZE) {
                break;
            }

            usleep(self::BATCH_PAUSE_US);
        } while (true);

        if ($this->echoCLI && $totalDeleted > 0) {
            cli()->primary("Deleted {$totalDeleted} broken/stuck collections.", true);
        }
    }

    private function calculateStuckCollectionsCutoff(): Carbon
    {
        $lastRun = $this->settings->lastRunTime;
        $threshold = null;

        if ($lastRun !== null) {
            try {
                $threshold = Carbon::createFromFormat('Y-m-d H:i:s', $lastRun);
            } catch (Throwable) {
                $threshold = null;
            }
        }

        return ($threshold ?? now())->copy()->subHours($this->settings->collectionTimeout);
    }

    private function deleteStuckCollectionBatch(int $groupID, Carbon $cutoff): int
    {
        $attempt = 0;
        $affected = 0;

        do {
            try {
                $query = DB::table('collections')
                    ->whereIn('filecheck', [
                        CollectionFileCheckStatus::Default->value,
                        CollectionFileCheckStatus::CompleteCollection->value,
                        CollectionFileCheckStatus::TempComplete->value,
                        CollectionFileCheckStatus::ZeroPart->value,
                        10,
                    ])
                    ->orderBy('id')
                    ->limit(self::BATCH_SIZE);
                if (Schema::hasColumn('collections', 'last_seen_at')) {
                    $query->whereRaw('COALESCE(last_seen_at, dateadded, added) < ?', [$cutoff]);
                } else {
                    $query->where('added', '<', $cutoff);
                }
                if ($groupID !== 0) {
                    $query->where('groups_id', '=', $groupID);
                }

                $ids = $query->pluck('id')->all();
                if ($ids === []) {
                    break;
                }

                $affected = $this->collectionCleanupService->deleteCollectionsAndDescendants(
                    $ids,
                    'Stuck collections cleanup',
                    $this->echoCLI
                );
                break;
            } catch (Throwable $e) {
                $attempt++;
                if ($attempt >= self::MAX_RETRIES) {
                    if ($this->echoCLI) {
                        cli()->error(
                            'Stuck collections delete failed after retries: '.$e->getMessage()
                        );
                    }
                    break;
                }
                usleep(self::RETRY_BASE_DELAY_US * $attempt);
            }
        } while (true);

        return $affected;
    }

    // ========================================================================
    // Release Deletion Methods
    // ========================================================================

    /**
     * @param  array<string, mixed>  $stats
     */
    private function deleteReleasesUnderMinSize(int|string $groupId, array &$stats): void
    {
        $releases = Release::query()
            ->where('releases.groups_id', $groupId)
            ->join('usenet_groups', 'usenet_groups.id', '=', 'releases.groups_id')
            ->whereRaw(
                'GREATEST(IFNULL(usenet_groups.minsizetoformrelease, 0), ?) > 0 '.
                'AND releases.size < GREATEST(IFNULL(usenet_groups.minsizetoformrelease, 0), ?)',
                [$this->settings->minSizeToFormRelease, $this->settings->minSizeToFormRelease]
            )
            ->select(['releases.id', 'releases.guid'])
            ->get();

        foreach ($releases as $release) {
            $this->deleteSingleRelease($release);
            $stats['minSize']++;
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function deleteReleasesOverMaxSize(int|string $groupId, array &$stats): void
    {
        if ($this->settings->maxSizeToFormRelease <= 0) {
            return;
        }

        $releases = Release::query()
            ->where('groups_id', $groupId)
            ->where('size', '>', $this->settings->maxSizeToFormRelease)
            ->select(['id', 'guid'])
            ->get();

        foreach ($releases as $release) {
            $this->deleteSingleRelease($release);
            $stats['maxSize']++;
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function deleteReleasesUnderMinFiles(int|string $groupId, array &$stats): void
    {
        if ($this->settings->minFilesToFormRelease <= 0) {
            return;
        }

        $releases = Release::query()
            ->where('releases.groups_id', $groupId)
            ->join('usenet_groups', 'usenet_groups.id', '=', 'releases.groups_id')
            ->whereRaw(
                'GREATEST(IFNULL(usenet_groups.minfilestoformrelease, 0), ?) > 0 '.
                'AND releases.totalpart < GREATEST(IFNULL(usenet_groups.minfilestoformrelease, 0), ?)',
                [$this->settings->minFilesToFormRelease, $this->settings->minFilesToFormRelease]
            )
            ->select(['releases.id', 'releases.guid'])
            ->get();

        foreach ($releases as $release) {
            $this->deleteSingleRelease($release);
            $stats['minFiles']++;
        }
    }

    private function deleteReleasesOverRetention(ReleaseDeleteStats $stats): ReleaseDeleteStats
    {
        if (! $this->settings->hasRetentionCleanup()) {
            return $stats;
        }

        $cutoff = now()->subDays($this->settings->releaseRetentionDays);

        Release::query()
            ->where('postdate', '<', $cutoff)
            ->select(['id', 'guid'])
            ->chunkById(self::BATCH_SIZE, function ($releases) use (&$stats): bool {
                foreach ($releases as $release) {
                    $this->deleteSingleRelease($release);
                    $stats = $stats->increment('retention');
                }

                return true;
            });

        return $stats;
    }

    private function deletePasswordedReleases(ReleaseDeleteStats $stats): ReleaseDeleteStats
    {
        if (! $this->settings->deletePasswordedRelease) {
            return $stats;
        }

        Release::query()
            ->select(['id', 'guid'])
            ->where('passwordstatus', '=', ReleaseBrowseService::PASSWD_RAR)
            ->orWhereIn('id', function ($query): void {
                $query->select('releases_id')
                    ->from('release_files')
                    ->where('passworded', '=', ReleaseBrowseService::PASSWD_RAR);
            })
            ->chunkById(self::BATCH_SIZE, function ($releases) use (&$stats): bool {
                foreach ($releases as $release) {
                    $this->deleteSingleRelease($release);
                    $stats = $stats->increment('password');
                }

                return true;
            });

        return $stats;
    }

    private function deleteCrossPostedReleases(ReleaseDeleteStats $stats): ReleaseDeleteStats
    {
        if (! $this->settings->hasCrossPostDetection()) {
            return $stats;
        }

        $releases = Release::query()
            ->where('adddate', '>', now()->subHours($this->settings->crossPostTime))
            ->groupBy(['name', 'fromname'])
            ->havingRaw('COUNT(name) > 1 AND COUNT(fromname) > 1')
            ->select(['id', 'guid'])
            ->get();

        foreach ($releases as $release) {
            $this->deleteSingleRelease($release);
            $stats = $stats->increment('duplicate');
        }

        return $stats;
    }

    private function deleteIncompleteReleases(ReleaseDeleteStats $stats): ReleaseDeleteStats
    {
        if (! $this->settings->hasCompletionCleanup()) {
            return $stats;
        }

        Release::query()
            ->where('completion', '<', $this->settings->completion)
            ->where('completion', '>', 0)
            ->select(['id', 'guid'])
            ->chunkById(self::BATCH_SIZE, function ($releases) use (&$stats): bool {
                foreach ($releases as $release) {
                    $this->deleteSingleRelease($release);
                    $stats = $stats->increment('completion');
                }

                return true;
            });

        return $stats;
    }

    private function deleteDisabledCategoryReleases(ReleaseDeleteStats $stats): ReleaseDeleteStats
    {
        $disabledCategories = Category::getDisabledIDs();

        if ($disabledCategories->isEmpty()) {
            return $stats;
        }

        $categoryIds = $disabledCategories->pluck('id')->toArray();

        Release::query()
            ->whereIn('categories_id', $categoryIds)
            ->select(['id', 'guid'])
            ->chunkById(self::BATCH_SIZE, function ($releases) use (&$stats): bool {
                foreach ($releases as $release) {
                    $this->deleteSingleRelease($release);
                    $stats = $stats->increment('disabledCategory');
                }

                return true;
            });

        return $stats;
    }

    private function deleteCategoryMinSizeReleases(ReleaseDeleteStats $stats): ReleaseDeleteStats
    {
        $categories = Category::query()
            ->where('minsizetoformrelease', '>', 0)
            ->select(['id', 'minsizetoformrelease as minsize'])
            ->get();

        foreach ($categories as $category) {
            Release::query()
                ->where('categories_id', (int) $category->id)
                ->where('size', '<', (int) $category->minsize) // @phpstan-ignore property.notFound
                ->select(['id', 'guid'])
                ->limit(1000)
                ->chunkById(self::BATCH_SIZE, function ($releases) use (&$stats): bool {
                    foreach ($releases as $release) {
                        $this->deleteSingleRelease($release);
                        $stats = $stats->increment('categoryMinSize');
                    }

                    return true;
                });
        }

        return $stats;
    }

    private function deleteDisabledGenreReleases(ReleaseDeleteStats $stats): ReleaseDeleteStats
    {
        $genres = new GenreService;
        $genreList = $genres->getDisabledIDs();

        if ($genreList->isEmpty()) {
            return $stats;
        }

        foreach ($genreList as $genre) {
            $musicInfoQuery = MusicInfo::query()
                ->where('genre_id', (int) $genre->id) // @phpstan-ignore property.notFound
                ->select(['id']);

            Release::query()
                ->joinSub(
                    $musicInfoQuery,
                    'mi',
                    static fn ($join) => $join->on('releases.musicinfo_id', '=', 'mi.id')
                )
                ->select(['releases.id', 'releases.guid'])
                ->chunkById(self::BATCH_SIZE, function ($releases) use (&$stats): bool {
                    foreach ($releases as $release) {
                        $this->deleteSingleRelease($release);
                        $stats = $stats->increment('disabledGenre');
                    }

                    return true;
                }, 'releases.id');
        }

        return $stats;
    }

    private function deleteMiscReleases(ReleaseDeleteStats $stats): ReleaseDeleteStats
    {
        if ($this->settings->miscOtherRetentionHours > 0) {
            $cutoff = now()->subHours($this->settings->miscOtherRetentionHours);

            Release::query()
                ->where('categories_id', Category::OTHER_MISC)
                ->where('adddate', '<=', $cutoff)
                ->select(['id', 'guid'])
                ->chunkById(self::BATCH_SIZE, function ($releases) use (&$stats): bool {
                    foreach ($releases as $release) {
                        $this->deleteSingleRelease($release);
                        $stats = $stats->increment('miscOther');
                    }

                    return true;
                });
        }

        if ($this->settings->miscHashedRetentionHours > 0) {
            $cutoff = now()->subHours($this->settings->miscHashedRetentionHours);

            Release::query()
                ->where('categories_id', Category::OTHER_HASHED)
                ->where('adddate', '<=', $cutoff)
                ->select(['id', 'guid'])
                ->chunkById(self::BATCH_SIZE, function ($releases) use (&$stats): bool {
                    foreach ($releases as $release) {
                        $this->deleteSingleRelease($release);
                        $stats = $stats->increment('miscHashed');
                    }

                    return true;
                });
        }

        return $stats;
    }

    private function deleteSingleRelease(object $release): void
    {
        $this->releaseManagement->deleteSingle(
            ['g' => $release->guid, 'i' => $release->id],
            $this->nzb,
            $this->releaseImage
        );
    }

    // ========================================================================
    // Output Helper Methods
    // ========================================================================

    private function outputBanner(): void
    {
        if (! $this->echoCLI) {
            return;
        }

        echo PHP_EOL;
        cli()->header('NNTmux Release Processing');
        cli()->info('Started: '.now()->format('Y-m-d H:i:s'));
    }

    private function outputHeader(string $title): void
    {
        if (! $this->echoCLI) {
            return;
        }

        echo PHP_EOL;
        cli()->header(strtoupper($title));
        cli()->header(str_repeat('-', strlen($title)));
    }

    private function outputSubHeader(string $title): void
    {
        if (! $this->echoCLI) {
            return;
        }

        cli()->notice("  {$title}");
    }

    /** @phpstan-ignore method.unused */
    private function outputSuccess(string $message): void
    {
        if (! $this->echoCLI) {
            return;
        }

        cli()->primary("    {$message}");
    }

    private function outputInfo(string $message): void
    {
        if (! $this->echoCLI) {
            return;
        }

        cli()->info("    {$message}");
    }

    private function outputStat(string $label, string|int $value, string $suffix = ''): void
    {
        if (! $this->echoCLI) {
            return;
        }

        $formattedValue = is_int($value) ? number_format($value) : $value;
        cli()->primary("      {$label}: {$formattedValue}{$suffix}");
    }

    private function outputElapsedTime(DateTimeInterface $startTime, string $prefix = 'Time'): void
    {
        if (! $this->echoCLI) {
            return;
        }

        $elapsed = now()->diffInSeconds($startTime, true);
        $timeStr = $this->formatElapsedTime($elapsed);
        cli()->info("      {$prefix}: {$timeStr}");
    }

    private function formatElapsedTime(int|float $seconds): string
    {
        if ($seconds < 1) {
            return sprintf('%dms', (int) ($seconds * 1000));
        }

        if ($seconds < 60) {
            return sprintf('%.1fs', $seconds);
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return sprintf('%dm %ds', $minutes, (int) $remainingSeconds);
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return sprintf('%dh %dm', $hours, $remainingMinutes);
    }

    private function outputProgress(int $current, int $total, string $action): void
    {
        if (! $this->echoCLI || $total === 0) {
            return;
        }

        $percent = min(100, (int) (($current / $total) * 100));
        echo "\r      {$action}: ".number_format($current).'/'.number_format($total)." ({$percent}%)   ";

        if ($current >= $total) {
            echo PHP_EOL;
        }
    }

    private function outputFinalSummary(
        int $releasesAdded,
        int $nzbsCreated,
        int $dupes,
        int $iterations,
        DateTimeInterface $startTime
    ): void {
        if (! $this->echoCLI) {
            return;
        }

        $elapsed = now()->diffInSeconds($startTime, true);

        echo PHP_EOL;
        cli()->header('SUMMARY');
        cli()->header('-------');
        cli()->primary('  Releases added: '.number_format($releasesAdded));
        cli()->primary('  NZBs created: '.number_format($nzbsCreated));
        if ($dupes > 0) {
            cli()->warning('  Duplicates skipped: '.number_format($dupes));
        }
        cli()->info('  Processing cycles: '.number_format($iterations));
        cli()->info('  Total time: '.$this->formatElapsedTime($elapsed));
        echo PHP_EOL;
    }

    /**
     * @param  array{minSize: int, maxSize: int, minFiles: int}  $stats
     */
    private function outputCollectionDeleteStats(array $stats, DateTimeInterface $startTime): void
    {
        $totalDeleted = $stats['minSize'] + $stats['maxSize'] + $stats['minFiles'] + ($stats['par2Only'] ?? 0);

        if ($totalDeleted > 0) {
            $this->outputStat('Too small', $stats['minSize']);
            $this->outputStat('Too large', $stats['maxSize']);
            $this->outputStat('Too few files', $stats['minFiles']);
            if (($stats['par2Only'] ?? 0) > 0) {
                $this->outputStat('Par2 only', $stats['par2Only']);
            }
            $this->outputStat('Total removed', $totalDeleted);
        } else {
            $this->outputInfo('No collections filtered');
        }
        $this->outputElapsedTime($startTime);
    }

    /**
     * @param  array{minSize: int, maxSize: int, minFiles: int}  $stats
     */
    private function outputReleaseDeleteByGroupStats(array $stats, DateTimeInterface $startTime): void
    {
        $total = $stats['minSize'] + $stats['maxSize'] + $stats['minFiles'];

        if ($total > 0) {
            $this->outputStat('Too small', $stats['minSize']);
            $this->outputStat('Too large', $stats['maxSize']);
            $this->outputStat('Too few files', $stats['minFiles']);
        }
        $this->outputElapsedTime($startTime);
    }

    private function outputReleaseDeleteStats(ReleaseDeleteStats $stats, DateTimeInterface $startTime): void
    {
        if (! $this->echoCLI) {
            return;
        }

        $total = $stats->total();

        if ($total > 0) {
            if ($stats->retention > 0) {
                $this->outputStat('Past retention', $stats->retention);
            }
            if ($stats->password > 0) {
                $this->outputStat('Passworded', $stats->password);
            }
            if ($stats->duplicate > 0) {
                $this->outputStat('Cross-posted', $stats->duplicate);
            }
            if ($stats->completion > 0) {
                $this->outputStat("Under {$this->settings->completion}% complete", $stats->completion);
            }
            if ($stats->disabledCategory > 0) {
                $this->outputStat('Disabled categories', $stats->disabledCategory);
            }
            if ($stats->categoryMinSize > 0) {
                $this->outputStat('Under category min size', $stats->categoryMinSize);
            }
            if ($stats->disabledGenre > 0) {
                $this->outputStat('Disabled genres', $stats->disabledGenre);
            }
            if ($stats->miscOther > 0) {
                $this->outputStat('Misc->Other expired', $stats->miscOther);
            }
            if ($stats->miscHashed > 0) {
                $this->outputStat('Misc->Hashed expired', $stats->miscHashed);
            }

            $this->outputStat('Total releases removed', $total);
        } else {
            $this->outputInfo('No releases removed');
        }

        $this->outputElapsedTime($startTime);
    }
}
