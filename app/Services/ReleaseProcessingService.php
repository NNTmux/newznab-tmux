<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CollectionFileCheckStatus;
use App\Enums\FileCompletionStatus;
use App\Models\Category;
use App\Models\Collection;
use App\Models\MusicInfo;
use App\Models\Release;
use App\Models\Settings;
use App\Models\UsenetGroup;
use App\Services\Categorization\CategorizationService;
use App\Services\NNTP\NNTPService;
use App\Services\Nzb\NzbService;
use App\Services\Releases\ReleaseManagementService;
use App\Support\DTOs\ProcessReleasesSettings;
use App\Support\DTOs\ReleaseCreationResult;
use App\Support\DTOs\ReleaseDeleteStats;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

    private const int NZB_CHUNK_SIZE = 100;

    private bool $echoCLI;

    private readonly ProcessReleasesSettings $settings;

    private readonly NzbService $nzb;

    private readonly ReleaseCleaningService $releaseCleaning;

    private readonly ReleaseManagementService $releaseManagement;

    private readonly ReleaseImageService $releaseImage;

    private readonly ReleaseCreationService $releaseCreationService;

    private readonly CollectionCleanupService $collectionCleanupService;

    private readonly ?PostProcessService $postProcessService;

    public function __construct(
        ?NzbService $nzb = null,
        ?ReleaseCleaningService $releaseCleaning = null,
        ?ReleaseManagementService $releaseManagement = null,
        ?ReleaseImageService $releaseImage = null,
        ?ReleaseCreationService $releaseCreationService = null,
        ?CollectionCleanupService $collectionCleanupService = null,
        ?PostProcessService $postProcessService = null,
    ) {
        $this->echoCLI = (bool) config('nntmux.echocli');

        $this->nzb = $nzb ?? app(NzbService::class);
        $this->releaseCleaning = $releaseCleaning ?? new ReleaseCleaningService;
        $this->releaseManagement = $releaseManagement ?? app(ReleaseManagementService::class);
        $this->releaseImage = $releaseImage ?? new ReleaseImageService;

        $this->releaseCreationService = $releaseCreationService
            ?? new ReleaseCreationService($this->releaseCleaning);
        $this->collectionCleanupService = $collectionCleanupService
            ?? new CollectionCleanupService;
        $this->postProcessService = $postProcessService;

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

        return ProcessReleasesSettings::fromDatabase($dbSettings);
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
        $whereSql = $this->buildGroupWhereSql($normalizedGroupId, 'c');

        $this->processStuckCollections($normalizedGroupId ?? 0);
        $this->runCollectionFileCheckStage1($normalizedGroupId ?? 0);
        $this->runCollectionFileCheckStage2($normalizedGroupId ?? 0);
        $this->runCollectionFileCheckStage3($whereSql);
        $this->runCollectionFileCheckStage4($whereSql);
        $this->runCollectionFileCheckStage5($normalizedGroupId ?? 0);
        $this->runCollectionFileCheckStage6($whereSql);

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
        DB::transaction(function () use ($groupID, &$updated): void {
            $normalizedGroupId = $this->normalizeGroupId($groupID);
            $whereSql = $normalizedGroupId !== null
                ? " AND c.groups_id = {$normalizedGroupId} "
                : ' ';

            $sql = <<<SQL
                UPDATE collections c
                SET c.filesize = (
                    SELECT COALESCE(SUM(b.partsize), 0)
                    FROM binaries b
                    WHERE b.collections_id = c.id
                ),
                c.filecheck = ?
                WHERE c.filecheck = ?
                AND c.filesize = 0{$whereSql}
            SQL;

            $updated = DB::update($sql, [
                CollectionFileCheckStatus::Sized->value,
                CollectionFileCheckStatus::CompleteParts->value,
            ]);
        }, 10);

        $this->outputStat('Collections sized', $updated);
        $this->outputElapsedTime($startTime);
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
        DB::transaction(static function () use (&$stats): void {
            $par2OnlyCollectionIds = DB::table('collections as c')
                ->join('binaries as b', 'c.id', '=', 'b.collections_id')
                ->where('c.filecheck', CollectionFileCheckStatus::Sized->value)
                ->where('c.filesize', '>', 0)
                ->groupBy('c.id')
                ->havingRaw("COUNT(b.id) = SUM(CASE WHEN b.name REGEXP '\\\\.(vol[0-9]+\\\\+[0-9]+\\\\.par2|par2)' THEN 1 ELSE 0 END)")
                ->pluck('c.id');

            if ($par2OnlyCollectionIds->isNotEmpty()) {
                $stats['par2Only'] += Collection::query()
                    ->whereIn('id', $par2OnlyCollectionIds)
                    ->delete();
            }
        }, 10);

        foreach ($groupIDs as $grpID) {
            $groupSettings = UsenetGroup::getGroupByID($grpID['id']);
            $groupMinSize = (int) ($groupSettings['minsizetoformrelease'] ?? 0);
            $groupMinFiles = (int) ($groupSettings['minfilestoformrelease'] ?? 0);

            if (! $this->hasSizedCollections()) {
                continue;
            }

            DB::transaction(function () use ($groupMinSize, $groupMinFiles, &$stats): void {
                $effectiveMinSize = max($groupMinSize, $this->settings->minSizeToFormRelease);
                if ($effectiveMinSize > 0) {
                    $stats['minSize'] += Collection::query()
                        ->where('filecheck', CollectionFileCheckStatus::Sized->value)
                        ->where('filesize', '>', 0)
                        ->where('filesize', '<', $effectiveMinSize)
                        ->delete();
                }

                if ($this->settings->maxSizeToFormRelease > 0) {
                    $stats['maxSize'] += Collection::query()
                        ->where('filecheck', CollectionFileCheckStatus::Sized->value)
                        ->where('filesize', '>', $this->settings->maxSizeToFormRelease)
                        ->delete();
                }

                $effectiveMinFiles = max($groupMinFiles, $this->settings->minFilesToFormRelease);
                if ($effectiveMinFiles > 0) {
                    $stats['minFiles'] += Collection::query()
                        ->where('filecheck', CollectionFileCheckStatus::Sized->value)
                        ->where('filesize', '>', 0)
                        ->where('totalfiles', '<', $effectiveMinFiles)
                        ->delete();
                }
            }, 10);
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

        return ReleaseCreationResult::fromArray($result);
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

        $query = Release::query()
            ->with('category.parent')
            ->where('nzbstatus', '=', NzbService::NZB_NONE)
            ->select(['id', 'guid', 'name', 'categories_id']);

        if (! empty($groupID)) {
            $query->where('releases.groups_id', $groupID);
        }

        $nzbCount = 0;
        $total = $query->count();

        if ($total > 0) {
            $query->chunkById(self::NZB_CHUNK_SIZE, function ($releases) use (&$nzbCount, $total): bool {
                foreach ($releases as $release) {
                    if ($this->nzb->writeNzbForReleaseId($release)) {
                        $nzbCount++;
                        $this->outputProgress($nzbCount, $total, 'Creating NZBs');
                    }
                }

                return true;
            });
        }

        $this->outputStat('NZBs created', $nzbCount);
        $this->outputElapsedTime($startTime);

        return $nzbCount;
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

    private function hasSizedCollections(): bool
    {
        return Collection::query()
            ->where('filecheck', CollectionFileCheckStatus::Sized->value)
            ->where('filesize', '>', 0)
            ->exists();
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

    private function buildGroupWhereSql(?int $groupID, string $alias = 'c'): string
    {
        return $groupID !== null ? " AND {$alias}.groups_id = {$groupID} " : ' ';
    }

    // ========================================================================
    // Collection Processing Stages
    // ========================================================================

    /**
     * @throws Throwable
     */
    private function runCollectionFileCheckStage1(int $groupID): void
    {
        DB::transaction(static function () use ($groupID): void {
            $collectionsQuery = Collection::query()
                ->select(['collections.id'])
                ->join('binaries', 'binaries.collections_id', '=', 'collections.id')
                ->where('collections.totalfiles', '>', 0)
                ->where('collections.filecheck', '=', CollectionFileCheckStatus::Default->value)
                ->groupBy(['binaries.collections_id', 'collections.totalfiles', 'collections.id'])
                ->havingRaw('COUNT(binaries.id) IN (collections.totalfiles, collections.totalfiles + 1)');

            if ($groupID !== 0) {
                $collectionsQuery->where('collections.groups_id', $groupID);
            }

            Collection::query()
                ->joinSub($collectionsQuery, 'r', static fn ($join) => $join->on('collections.id', '=', 'r.id'))
                ->update(['collections.filecheck' => CollectionFileCheckStatus::CompleteCollection->value]);
        }, 10);
    }

    /**
     * @throws Throwable
     */
    private function runCollectionFileCheckStage2(int $groupID): void
    {
        DB::transaction(static function () use ($groupID): void {
            $collectionsQuery = Collection::query()
                ->select(['collections.id'])
                ->join('binaries', 'binaries.collections_id', '=', 'collections.id')
                ->where('binaries.filenumber', '=', 0)
                ->where('collections.totalfiles', '>', 0)
                ->where('collections.filecheck', '=', CollectionFileCheckStatus::CompleteCollection->value)
                ->groupBy(['collections.id']);

            if ($groupID !== 0) {
                $collectionsQuery->where('collections.groups_id', $groupID);
            }

            Collection::query()
                ->joinSub($collectionsQuery, 'r', static fn ($join) => $join->on('collections.id', '=', 'r.id'))
                ->update(['collections.filecheck' => CollectionFileCheckStatus::ZeroPart->value]);
        }, 10);

        $this->updateCollectionsFilecheckInChunks(
            $groupID,
            CollectionFileCheckStatus::CompleteCollection->value,
            CollectionFileCheckStatus::TempComplete->value
        );
    }

    /**
     * Update collections filecheck in small chunks to reduce deadlock risk.
     * Retries on deadlock (1213) with exponential backoff.
     *
     * @throws Throwable
     */
    private function updateCollectionsFilecheckInChunks(int $groupID, int $fromStatus, int $toStatus): void
    {
        $attempt = 0;
        $maxAttempts = self::MAX_RETRIES + 1;

        while ($attempt < $maxAttempts) {
            try {
                $updated = 0;
                do {
                    $ids = Collection::query()
                        ->where('filecheck', $fromStatus)
                        ->when($groupID !== 0, static fn ($q) => $q->where('groups_id', $groupID))
                        ->orderBy('id')
                        ->limit(self::BATCH_SIZE)
                        ->pluck('id')
                        ->all();

                    if ($ids === []) {
                        break;
                    }

                    DB::transaction(static function () use ($ids, $toStatus): void {
                        Collection::query()
                            ->whereIn('id', $ids)
                            ->update(['filecheck' => $toStatus]);
                    }, 10);

                    $updated = \count($ids);
                } while ($updated === self::BATCH_SIZE);

                return;
            } catch (QueryException $e) {
                $isDeadlock = ($e->errorInfo[1] ?? 0) === 1213
                    || str_contains($e->getMessage(), 'Deadlock');

                if ($isDeadlock && $attempt < $maxAttempts - 1) {
                    $attempt++;
                    usleep(self::RETRY_BASE_DELAY_US * (2 ** $attempt));
                } else {
                    throw $e;
                }
            }
        }
    }

    /**
     * @throws Throwable
     */
    private function runCollectionFileCheckStage3(string $whereSql): void
    {
        DB::transaction(static function () use ($whereSql): void {
            $sql = <<<SQL
                UPDATE binaries b
                INNER JOIN (
                    SELECT b.id
                    FROM binaries b
                    INNER JOIN collections c ON c.id = b.collections_id
                    WHERE c.filecheck = ?
                    AND b.partcheck = ? {$whereSql}
                    AND b.currentparts = b.totalparts
                    GROUP BY b.id, b.totalparts
                ) r ON b.id = r.id
                SET b.partcheck = ?
            SQL;

            DB::update($sql, [
                CollectionFileCheckStatus::TempComplete->value,
                FileCompletionStatus::Incomplete->value,
                FileCompletionStatus::Complete->value,
            ]);
        }, 10);

        DB::transaction(static function () use ($whereSql): void {
            $sql = <<<SQL
                UPDATE binaries b
                INNER JOIN (
                    SELECT b.id
                    FROM binaries b
                    INNER JOIN collections c ON c.id = b.collections_id
                    WHERE c.filecheck = ?
                    AND b.partcheck = ? {$whereSql}
                    AND b.currentparts >= (b.totalparts + 1)
                    GROUP BY b.id, b.totalparts
                ) r ON b.id = r.id
                SET b.partcheck = ?
            SQL;

            DB::update($sql, [
                CollectionFileCheckStatus::ZeroPart->value,
                FileCompletionStatus::Incomplete->value,
                FileCompletionStatus::Complete->value,
            ]);
        }, 10);
    }

    /**
     * @throws Throwable
     */
    private function runCollectionFileCheckStage4(string $whereSql): void
    {
        DB::transaction(static function () use ($whereSql): void {
            $sql = <<<SQL
                UPDATE collections c
                INNER JOIN (
                    SELECT c.id
                    FROM collections c
                    INNER JOIN binaries b ON c.id = b.collections_id
                    WHERE b.partcheck = ? AND c.filecheck IN (?, ?) {$whereSql}
                    GROUP BY b.collections_id, c.totalfiles, c.id
                    HAVING COUNT(b.id) >= c.totalfiles
                ) r ON c.id = r.id
                SET filecheck = ?
            SQL;

            DB::update($sql, [
                FileCompletionStatus::Complete->value,
                CollectionFileCheckStatus::TempComplete->value,
                CollectionFileCheckStatus::ZeroPart->value,
                CollectionFileCheckStatus::CompleteParts->value,
            ]);
        }, 10);
    }

    /**
     * @throws Throwable
     */
    private function runCollectionFileCheckStage5(int $groupId): void
    {
        DB::transaction(static function () use ($groupId): void {
            $query = Collection::query()
                ->whereIn('filecheck', [
                    CollectionFileCheckStatus::TempComplete->value,
                    CollectionFileCheckStatus::ZeroPart->value,
                ]);

            if ($groupId !== 0) {
                $query->where('groups_id', $groupId);
            }

            $query->update(['filecheck' => CollectionFileCheckStatus::CompleteCollection->value]);
        }, 10);
    }

    /**
     * @throws Throwable
     */
    private function runCollectionFileCheckStage6(string $whereSql): void
    {
        DB::transaction(function () use ($whereSql): void {
            $sql = <<<SQL
                UPDATE collections c
                SET filecheck = ?, totalfiles = (SELECT COUNT(b.id) FROM binaries b WHERE b.collections_id = c.id)
                WHERE c.dateadded < NOW() - INTERVAL ? HOUR
                AND c.filecheck IN (?, ?, 10){$whereSql}
            SQL;

            DB::update($sql, [
                CollectionFileCheckStatus::CompleteParts->value,
                $this->settings->collectionDelayTime,
                CollectionFileCheckStatus::Default->value,
                CollectionFileCheckStatus::CompleteCollection->value,
            ]);
        }, 10);
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
                $groupCondition = $groupID !== 0 ? 'AND groups_id = ? ' : '';
                $batchLimit = self::BATCH_SIZE;

                $sql = <<<SQL
                    DELETE FROM collections WHERE id IN (
                        SELECT id FROM (
                            SELECT id FROM collections WHERE added < ? {$groupCondition}
                            ORDER BY id LIMIT {$batchLimit}
                        ) AS x
                    )
                SQL;

                $params = $groupID !== 0 ? [$cutoff, $groupID] : [$cutoff];
                $affected = DB::affectingStatement($sql, $params);
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
            ->where('passwordstatus', '=', \App\Services\Releases\ReleaseBrowseService::PASSWD_RAR)
            ->orWhereIn('id', function ($query): void {
                $query->select('releases_id')
                    ->from('release_files')
                    ->where('passworded', '=', \App\Services\Releases\ReleaseBrowseService::PASSWD_RAR);
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
