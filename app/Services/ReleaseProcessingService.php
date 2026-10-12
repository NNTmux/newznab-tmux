<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CollectionFileCheckStatus;
use App\Models\Category;
use App\Models\Collection;
use App\Models\MusicInfo;
use App\Models\Release;
use App\Models\ReleaseNzbCreationFailure;
use App\Models\UsenetGroup;
use App\Services\Binaries\BinariesConfig;
use App\Services\Categorization\CategorizationService;
use App\Services\Configuration\ConfigurationProvider;
use App\Services\Configuration\ProcessingRuntimeStateRepository;
use App\Services\NNTP\NNTPService;
use App\Services\Nzb\NzbCreationCandidateQuery;
use App\Services\Nzb\NzbService;
use App\Services\Releases\ReleaseBrowseService;
use App\Services\Releases\ReleaseDuplicateFinder;
use App\Services\Releases\ReleaseManagementService;
use App\Services\Search\SearchService;
use App\Services\Tmux\CollectionReconciliationBarrier;
use App\Support\Data\NzbCreationResult;
use App\Support\Data\ProcessReleasesSettings;
use App\Support\Data\ReleaseCreationResult;
use App\Support\Data\ReleaseDeleteStats;
use App\Support\ReleaseSearchIndexSync;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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
 *
 * @phpstan-type PriorityWindowState array{max_id: int, group_ids: list<int>, position: int, cursors: array<int, array{last_size: int, last_id: int}>, exhausted: array<int, true>}
 */
final class ReleaseProcessingService
{
    private const int BATCH_SIZE = 500;

    private const int READY_WINDOW_SIZE = 50;

    private const int MAX_RETRIES = 5;

    private const int RETRY_BASE_DELAY_US = 20000;

    private const int BATCH_PAUSE_US = 10000;

    private const int CATEGORIZE_CHUNK_SIZE = 1000;

    private const int NZB_CREATION_MAX_ATTEMPTS = 3;

    private const int COLLECTION_WINDOWS_PER_PASS = 20;

    private const string COLLECTION_WINDOW_CURSOR_PREFIX = 'cbp:release-window:cursor:';

    private const string READY_WINDOW_CURSOR_PREFIX = 'cbp:release-ready-window:cursor:';

    private const string PRIORITY_WINDOW_CURSOR_PREFIX = 'cbp:release-priority-window:cursor:';

    private const int PRIORITY_GROUP_PROBES_PER_PASS = 20;

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
        return ProcessReleasesSettings::fromConfiguration(
            app(ConfigurationProvider::class)->ingestion(),
            app(ProcessingRuntimeStateRepository::class)->state(),
        );
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
        return $this->processCollectionsAndReleases(
            $normalizedGroupId,
            fn () => $this->categorizeReleases($categorize, $normalizedGroupId),
            function () use ($postProcess, $nntp, $normalizedGroupId): void {
                $this->postProcessReleases($postProcess, $nntp);
                $this->deleteCollections($normalizedGroupId);
            },
        );
    }

    /**
     * Prioritize numerically eligible collections, alternating with background
     * cleanup under a shared budget of 20 validation windows. Independent
     * cursors retain their snapshot ceilings until exhausted. The global
     * priority reader probes groups round-robin using the same indexed query
     * as a single-group worker, with at most 20 probes per invocation. All
     * selected IDs still pass the complete cleanup rules before creation.
     * Totals are partial while a backlog remains. NZB-only work retains the
     * existing success-count continuation rule.
     *
     * @param  callable():void|null  $afterCreation
     * @param  callable():void|null  $afterPass
     * @return array{releases: int, nzbs: int, dupes: int, iterations: int}
     *
     * @throws Throwable
     */
    public function processReleaseCreationBatches(
        int|string|null $groupID,
        ?callable $afterCreation = null,
        ?callable $afterPass = null,
    ): array {
        $totals = ['releases' => 0, 'nzbs' => 0, 'dupes' => 0, 'iterations' => 0];
        $limit = max(1, $this->settings->releaseCreationLimit);
        $windowSize = min(self::BATCH_SIZE, $limit);
        $normalizedGroupId = $this->normalizeGroupId($groupID);
        $candidateQuery = config('release_verification.required', true)
            ? $this->collectionCleanupService->verificationReadyCollections()
            : Collection::query()->where('filecheck', CollectionFileCheckStatus::Sized->value);
        $candidateQuery->when($normalizedGroupId !== null, static fn ($q) => $q->where('groups_id', $normalizedGroupId));
        $cursorKey = self::COLLECTION_WINDOW_CURSOR_PREFIX.($normalizedGroupId === null ? 'all' : 'group:'.$normalizedGroupId);
        $savedWindow = Cache::get($cursorKey);
        if (is_array($savedWindow)
            && isset($savedWindow['last_id'], $savedWindow['max_id'])
            && is_int($savedWindow['last_id'])
            && is_int($savedWindow['max_id'])
            && $savedWindow['last_id'] >= 0
            && $savedWindow['max_id'] > $savedWindow['last_id']) {
            $lastId = $savedWindow['last_id'];
            $maxId = $savedWindow['max_id'];
        } else {
            $lastId = 0;
            $maxId = (int) (clone $candidateQuery)->max('id');
        }
        if ($maxId === 0) {
            Cache::forget($cursorKey);
        }
        $moreCollections = $maxId > 0;
        $priorityCursorKey = self::PRIORITY_WINDOW_CURSOR_PREFIX.($normalizedGroupId === null ? 'all' : 'group:'.$normalizedGroupId);
        $priorityState = $this->loadPriorityWindowState($normalizedGroupId, $priorityCursorKey);
        $morePriority = $priorityState['group_ids'] !== [];
        $priorityProbes = 0;
        $preferPriority = true;
        $windowsProcessed = 0;
        $processedIds = [];
        $nzbFilesAdded = $limit;

        do {
            $windowIds = [];
            $selectedReader = null;
            while ($windowsProcessed < self::COLLECTION_WINDOWS_PER_PASS
                && ($moreCollections || ($morePriority && $priorityProbes < self::PRIORITY_GROUP_PROBES_PER_PASS))) {
                if (($preferPriority || ! $moreCollections) && $morePriority && $priorityProbes < self::PRIORITY_GROUP_PROBES_PER_PASS) {
                    $priorityWindow = $this->nextPriorityCollectionWindow($normalizedGroupId, $priorityState, $windowSize, $priorityProbes, $processedIds);
                    $windowIds = $priorityWindow['ids'];
                    $morePriority = $priorityWindow['more'];
                    $preferPriority = false;
                    if ($windowIds === []) {
                        $this->persistCollectionWindowState($priorityCursorKey, $priorityState, $morePriority);

                        continue;
                    }
                    $selectedReader = 'priority';
                } else {
                    $pageIds = (clone $candidateQuery)->where('id', '>', $lastId)->where('id', '<=', $maxId)
                        ->orderBy('id')->limit($windowSize + 1)->pluck('id')
                        ->map(static fn ($id): int => (int) $id)->all();
                    $moreCollections = count($pageIds) > $windowSize;
                    $backgroundIds = array_slice($pageIds, 0, $windowSize);
                    if ($backgroundIds !== []) {
                        $lastId = $backgroundIds[array_key_last($backgroundIds)];
                    }
                    $windowIds = array_values(array_filter($backgroundIds, static fn (int $id): bool => ! isset($processedIds[$id])));
                    $preferPriority = true;
                    if ($windowIds === []) {
                        $this->persistCollectionWindowState($cursorKey, ['last_id' => $lastId, 'max_id' => $maxId], $moreCollections);

                        continue;
                    }
                    $selectedReader = 'background';
                }
                $windowsProcessed++;
                break;
            }

            if ($windowIds === [] && $totals['iterations'] > 0 && $nzbFilesAdded < $limit) {
                break;
            }
            $totals['iterations']++;
            [$result, $nzbFilesAdded] = $this->processReleaseCollectionWindow($normalizedGroupId, $windowIds, $afterCreation, $afterPass);
            $totals['releases'] += $result->added;
            $totals['dupes'] += $result->dupes;
            $totals['nzbs'] += $nzbFilesAdded;

            foreach ($windowIds as $id) {
                $processedIds[$id] = true;
            }
            if ($selectedReader === 'priority') {
                $this->persistCollectionWindowState($priorityCursorKey, $priorityState, $morePriority);
            } elseif ($selectedReader === 'background') {
                $this->persistCollectionWindowState($cursorKey, ['last_id' => $lastId, 'max_id' => $maxId], $moreCollections);
            }

            $shouldContinue = (($moreCollections || ($morePriority && $priorityProbes < self::PRIORITY_GROUP_PROBES_PER_PASS))
                && $windowsProcessed < self::COLLECTION_WINDOWS_PER_PASS)
                || $nzbFilesAdded >= $limit;
        } while ($shouldContinue);

        return $totals;
    }

    /** @return array{releases: int, nzbs: int, dupes: int, iterations: int} */
    public function processReadyReleaseWindow(int $groupId): array
    {
        if ($groupId <= 0) {
            throw new \InvalidArgumentException('Ready release processing requires a positive group ID.');
        }
        $limit = min(self::READY_WINDOW_SIZE, max(1, $this->settings->releaseCreationLimit));
        $cursorKey = self::READY_WINDOW_CURSOR_PREFIX.$groupId;
        $savedState = Cache::get($cursorKey);
        $state = $this->isValidPriorityWindowState($savedState, $groupId) ? $savedState : ['max_id' => (int) Collection::query()->max('id'), 'group_ids' => [$groupId], 'position' => 0, 'cursors' => [], 'exhausted' => []];
        $probes = 0;
        $window = $this->nextPriorityCollectionWindow($groupId, $state, $limit, $probes, []);
        if ($window['ids'] === [] && ! NzbCreationCandidateQuery::baseBuilder($groupId)->exists()) {
            $this->persistCollectionWindowState($cursorKey, $state, $window['more']);

            return ['releases' => 0, 'nzbs' => 0, 'dupes' => 0, 'iterations' => 0];
        }
        try {
            [$result, $nzbs] = $this->processReleaseCollectionWindow($groupId, $window['ids'], null, null, $limit);
        } finally {
            $this->persistCollectionWindowState($cursorKey, $state, $window['more']);
        }

        return ['releases' => $result->added, 'nzbs' => $nzbs, 'dupes' => $result->dupes, 'iterations' => 1];
    }

    /**
     * Drain ready collections before preparation, then consume each freshly reconciled
     * page outside the downtime barrier. Sized rows never participate in downtime
     * compensation or stuck cleanup, so they need not wait for either operation.
     *
     * @param  callable(): void|null  $afterCreation
     * @param  callable(): void|null  $afterPass
     * @return array{releases: int, nzbs: int, dupes: int, iterations: int}
     */
    public function processCollectionsAndReleases(
        int|string|null $groupID,
        ?callable $afterCreation = null,
        ?callable $afterPass = null,
    ): array {
        $groupId = $this->normalizeGroupId($groupID);
        $totals = $this->hasReleaseCreationWork($groupId)
            ? $this->processReleaseCreationBatches($groupId, $afterCreation)
            : ['releases' => 0, 'nzbs' => 0, 'dupes' => 0, 'iterations' => 0];
        $windowSize = max(1, min(self::BATCH_SIZE, $this->settings->releaseCreationLimit));

        $this->processIncompleteCollections($groupId, function (array $ids) use ($groupId, $windowSize, $afterCreation, &$totals): void {
            foreach (array_chunk($ids, $windowSize) as $windowIds) {
                $readyIds = Collection::query()->whereIn('id', $windowIds)
                    ->where('filecheck', CollectionFileCheckStatus::CompleteParts->value)
                    ->when($groupId !== null, static fn ($q) => $q->where('groups_id', $groupId))
                    ->orderBy('id')->pluck('id')->map(static fn ($id): int => (int) $id)->all();
                if ($readyIds === []) {
                    continue;
                }
                Collection::query()->whereIn('id', $readyIds)
                    ->where('filecheck', CollectionFileCheckStatus::CompleteParts->value)
                    ->update(['filecheck' => CollectionFileCheckStatus::Sized->value]);
                [$result, $nzbs] = $this->processReleaseCollectionWindow($groupId, $readyIds, $afterCreation, null);
                $totals['releases'] += $result->added;
                $totals['dupes'] += $result->dupes;
                $totals['nzbs'] += $nzbs;
                $totals['iterations']++;
            }
        });

        // Preserve the final sweep for collections completed concurrently by ingestion,
        // and the normal cursor/NZB retry behavior after preparation finishes.
        $this->processCollectionSizes($groupId);
        if ($this->hasReleaseCreationWork($groupId) || $totals['iterations'] === 0) {
            $final = $this->processReleaseCreationBatches($groupId, $afterCreation);
            foreach ($final as $key => $value) {
                $totals[$key] += $value;
            }
        }
        // Legacy callbacks perform retention cleanup across all collection states.
        // Run them only once every preparation page has had a chance to reconcile,
        // size and release its rows, including old CompleteParts outside compensation.
        if ($afterPass !== null) {
            for ($pass = 0; $pass < $totals['iterations']; $pass++) {
                $afterPass();
            }
        }

        return $totals;
    }

    private function hasReleaseCreationWork(?int $groupId): bool
    {
        $query = config('release_verification.required', true)
            ? $this->collectionCleanupService->verificationReadyCollections()
            : Collection::query()->where('filecheck', CollectionFileCheckStatus::Sized->value);

        return $query
            ->when($groupId !== null, static fn ($q) => $q->where('groups_id', $groupId))->exists()
            || NzbCreationCandidateQuery::baseBuilder($groupId)->exists();
    }

    /**
     * @param  list<int>  $windowIds
     * @param  callable(): void|null  $afterCreation
     * @param  callable(): void|null  $afterPass
     * @return array{ReleaseCreationResult, int}
     */
    private function processReleaseCollectionWindow(?int $groupId, array $windowIds, ?callable $afterCreation, ?callable $afterPass, ?int $nzbLimit = null): array
    {
        $rejectedIds = $this->deleteUnwantedCollections($groupId, $windowIds);
        $allowedIds = array_values(array_diff($windowIds, $rejectedIds));
        $pass = app(SearchService::class)->deferReleaseUpdates(function () use ($groupId, $allowedIds, $afterCreation, $nzbLimit): array {
            $result = $nzbLimit === null ? $this->createReleasesAndNzbs($groupId, $allowedIds)
                : $this->createReleasesAndNzbs($groupId, $allowedIds, $nzbLimit);
            if ($afterCreation !== null) {
                $afterCreation();
            }

            return $result;
        });
        if ($afterPass !== null) {
            $afterPass();
        }

        return $pass;
    }

    /** @return PriorityWindowState */
    private function loadPriorityWindowState(?int $groupId, string $cursorKey): array
    {
        $savedState = Cache::get($cursorKey);
        if ($this->isValidPriorityWindowState($savedState, $groupId)) {
            return $savedState;
        }

        $maxId = (int) Collection::query()->where('filecheck', CollectionFileCheckStatus::Sized->value)
            ->when($groupId !== null, static fn ($q) => $q->where('groups_id', $groupId))->max('id');
        $groupIds = [];
        if ($maxId > 0) {
            $groupIds = $groupId !== null ? [$groupId] : UsenetGroup::query()
                ->whereExists(static fn ($q) => $q->select(DB::raw(1))->from('collections as c')
                    ->whereColumn('c.groups_id', 'usenet_groups.id')
                    ->where('c.filecheck', CollectionFileCheckStatus::Sized->value)
                    ->where('c.filesize', '>', 0)->where('c.id', '<=', $maxId))
                ->orderBy('id')->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        }
        Cache::forget($cursorKey);

        return ['max_id' => $maxId, 'group_ids' => $groupIds, 'position' => 0, 'cursors' => [], 'exhausted' => []];
    }

    /**
     * @phpstan-assert-if-true PriorityWindowState $state
     */
    private function isValidPriorityWindowState(mixed $state, ?int $groupId): bool
    {
        if (! is_array($state) || ! isset($state['max_id'], $state['group_ids'], $state['position'], $state['cursors'], $state['exhausted'])
            || ! is_int($state['max_id']) || $state['max_id'] <= 0
            || ! is_array($state['group_ids']) || ! array_is_list($state['group_ids']) || $state['group_ids'] === []
            || ! is_int($state['position']) || $state['position'] < 0 || $state['position'] >= count($state['group_ids'])
            || ! is_array($state['cursors']) || ! is_array($state['exhausted'])) {
            return false;
        }
        foreach ($state['group_ids'] as $id) {
            if (! is_int($id) || $id <= 0) {
                return false;
            }
        }
        if (count(array_unique($state['group_ids'])) !== count($state['group_ids'])
            || ($groupId !== null && $state['group_ids'] !== [$groupId])) {
            return false;
        }
        foreach ($state['cursors'] as $id => $cursor) {
            if (! in_array($id, $state['group_ids'], true) || ! is_array($cursor)
                || ! isset($cursor['last_size'], $cursor['last_id'])
                || ! is_int($cursor['last_size']) || $cursor['last_size'] < 0
                || ! is_int($cursor['last_id']) || $cursor['last_id'] < 0 || $cursor['last_id'] > $state['max_id']) {
                return false;
            }
        }
        foreach ($state['exhausted'] as $id => $done) {
            if (! in_array($id, $state['group_ids'], true) || $done !== true) {
                return false;
            }
        }

        return count($state['exhausted']) < count($state['group_ids']);
    }

    /**
     * @param  PriorityWindowState  $state
     * @param  array<int, true>  $processedIds
     * @return array{ids: list<int>, more: bool}
     */
    private function nextPriorityCollectionWindow(?int $groupId, array &$state, int $windowSize, int &$probes, array $processedIds): array
    {
        $inspected = 0;
        $groupCount = count($state['group_ids']);
        while ($probes < self::PRIORITY_GROUP_PROBES_PER_PASS && $inspected < $groupCount) {
            $probeGroupId = $state['group_ids'][$state['position']];
            $state['position'] = ($state['position'] + 1) % $groupCount;
            $inspected++;
            if (isset($state['exhausted'][$probeGroupId])) {
                continue;
            }
            $probes++;
            $groupSettings = UsenetGroup::getGroupByID($probeGroupId);
            $applyNumeric = $groupId !== null || (int) ($groupSettings['active'] ?? 0) === 1;
            $minSize = $applyNumeric ? max((int) ($groupSettings['minsizetoformrelease'] ?? 0), $this->settings->minSizeToFormRelease) : 0;
            $minFiles = $applyNumeric ? max((int) ($groupSettings['minfilestoformrelease'] ?? 0), $this->settings->minFilesToFormRelease) : 0;
            $query = Collection::query()->where('groups_id', $probeGroupId)
                ->where('filecheck', CollectionFileCheckStatus::Sized->value)
                ->where('filesize', '>=', max(1, $minSize))->where('id', '<=', $state['max_id']);
            if ($applyNumeric && $this->settings->maxSizeToFormRelease > 0) {
                $query->where('filesize', '<=', $this->settings->maxSizeToFormRelease);
            }
            if ($minFiles > 0) {
                $query->where('totalfiles', '>=', $minFiles);
            }
            if (isset($state['cursors'][$probeGroupId])) {
                $cursor = $state['cursors'][$probeGroupId];
                $query->where(static fn ($q) => $q->where('filesize', '>', $cursor['last_size'])
                    ->orWhere(static fn ($sameSize) => $sameSize->where('filesize', $cursor['last_size'])->where('id', '>', $cursor['last_id'])));
            }
            $page = $query->orderBy('filesize')->orderBy('id')->limit($windowSize + 1)->get(['id', 'filesize']);
            $rows = $page->take($windowSize);
            if ($page->count() <= $windowSize) {
                $state['exhausted'][$probeGroupId] = true;
            }
            if ($rows->isNotEmpty()) {
                $last = $rows->last();
                $state['cursors'][$probeGroupId] = ['last_size' => (int) $last->filesize, 'last_id' => (int) $last->id];
            }
            $ids = $rows->pluck('id')->map(static fn ($id): int => (int) $id)
                ->filter(static fn (int $id): bool => ! isset($processedIds[$id]))->values()->all();
            if ($ids !== []) {
                return ['ids' => $ids, 'more' => count($state['exhausted']) < $groupCount];
            }
        }

        return ['ids' => [], 'more' => count($state['exhausted']) < $groupCount];
    }

    /** @param array<string, mixed> $state */
    private function persistCollectionWindowState(string $cursorKey, array $state, bool $more): void
    {
        if ($more) {
            Cache::forever($cursorKey, $state);
        } else {
            Cache::forget($cursorKey);
        }
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
     * @param  callable(list<int>): void|null  $afterReconciliation
     *
     * @throws Throwable
     */
    public function processIncompleteCollections(int|string|null $groupID, ?callable $afterReconciliation = null): void
    {
        $startTime = now()->toImmutable();
        $this->outputSubHeader('Finding Complete Collections');

        $normalizedGroupId = $this->normalizeGroupId($groupID);
        $this->processStuckCollections($normalizedGroupId ?? 0);
        $this->reconcileIncompleteCollections($normalizedGroupId, $afterReconciliation);

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
     *
     * @param  callable(list<int>): void|null  $afterReconciliation
     */
    private function reconcileIncompleteCollections(?int $groupId, ?callable $afterReconciliation): void
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
            $ids = $this->withCollectionReconciliationBarrier(function () use ($lastId, $groupId, $statuses): array {
                $query = Collection::query()
                    ->where('id', '>', $lastId)
                    ->where(function ($query) use ($statuses): void {
                        $query->where('filecheck', CollectionFileCheckStatus::CompleteParts->value)
                            ->orWhere(function ($stale) use ($statuses): void {
                                $stale->whereIn('filecheck', array_values(array_diff(
                                    $statuses,
                                    [CollectionFileCheckStatus::CompleteParts->value]
                                )))->whereRaw(
                                    'COALESCE(dateadded, added) < ?',
                                    [now()->subHours($this->settings->collectionDelayTime)]
                                );
                            });
                    })
                    ->when($groupId !== null, static fn ($q) => $q->where('groups_id', $groupId))
                    ->orderBy('id')
                    ->limit($this->binariesConfig->reconcileBatchSize);
                $ids = $query->pluck('id')->map(static fn ($id): int => (int) $id)->all();
                if ($ids !== []) {
                    $this->reconcileCollectionIds($ids, $statuses);
                }

                return $ids;
            });
            if ($ids === []) {
                break;
            }
            $lastId = (int) end($ids);
            if ($afterReconciliation !== null) {
                $afterReconciliation($ids);
            }
        } while (\count($ids) === $this->binariesConfig->reconcileBatchSize);
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $work
     * @return TResult
     */
    private function withCollectionReconciliationBarrier(callable $work): mixed
    {
        if (! app()->bound(CollectionReconciliationBarrier::class)) {
            return $work();
        }

        return app(CollectionReconciliationBarrier::class)
            ->withReconciliationBarrier($this->settings->collectionDelayTime, $work);
    }

    /**
     * Recompute binary and collection aggregates from stored parts.
     *
     * Header storage writes the same binaries and collections inside its own
     * transactions, and an UPDATE that joins parts locks every parts row it reads, even
     * under READ-COMMITTED (~23k for one batch of a busy group), which deadlocked with it.
     * So read the aggregates with plain SELECTs, which take no locks, and write only the
     * rows that drifted, each by primary key and only if it still holds the values read;
     * if header storage changed it meanwhile, its own aggregate refresh is newer.
     *
     * @param  list<int>  $collectionIds
     * @param  list<int>  $statuses
     */
    private function reconcileCollectionIds(array $collectionIds, array $statuses): void
    {
        $this->reconcileBinaryAggregates($collectionIds);
        $this->reconcileCollectionAggregates($collectionIds, $statuses);
    }

    /**
     * @param  list<int>  $collectionIds
     */
    private function reconcileBinaryAggregates(array $collectionIds): void
    {
        $binaries = DB::table('binaries')
            ->whereIn('collections_id', $collectionIds)
            ->orderBy('id')
            ->get(['id', 'totalparts', 'currentparts', 'partsize', 'partcheck']);

        foreach ($binaries->chunk(max(1, $this->binariesConfig->sqlChunkSize)) as $chunk) {
            $parts = DB::table('parts')
                ->whereIn('binaries_id', $chunk->pluck('id')->all())
                ->groupBy('binaries_id')
                ->selectRaw('binaries_id, COUNT(*) AS currentparts, COALESCE(SUM(size), 0) AS partsize')
                ->get()
                ->keyBy('binaries_id');

            foreach ($chunk as $binary) {
                $aggregate = $parts->get($binary->id);
                $currentParts = (int) ($aggregate->currentparts ?? 0);
                $partSize = (int) ($aggregate->partsize ?? 0);
                $partCheck = $currentParts >= (int) $binary->totalparts ? 1 : 0;
                if ($currentParts === (int) $binary->currentparts
                    && $partSize === (int) $binary->partsize
                    && $partCheck === (int) $binary->partcheck) {
                    continue;
                }

                DB::table('binaries')
                    ->where('id', $binary->id)
                    ->where('currentparts', $binary->currentparts)
                    ->where('partsize', $binary->partsize)
                    ->update(['currentparts' => $currentParts, 'partsize' => $partSize, 'partcheck' => $partCheck]);
            }
        }
    }

    /**
     * @param  list<int>  $collectionIds
     * @param  list<int>  $statuses
     */
    private function reconcileCollectionAggregates(array $collectionIds, array $statuses): void
    {
        $collections = DB::table('collections')
            ->whereIn('id', $collectionIds)
            ->whereIn('filecheck', $statuses)
            ->orderBy('id')
            ->get(['id', 'totalfiles', 'filecheck', 'filesize', 'dateadded', 'added']);
        if ($collections->isEmpty()) {
            return;
        }

        $aggregates = DB::table('binaries')
            ->whereIn('collections_id', $collections->pluck('id')->all())
            ->groupBy('collections_id')
            ->selectRaw('collections_id, COUNT(*) AS currentfiles,
                COALESCE(SUM(CASE WHEN partcheck = 1 THEN 1 ELSE 0 END), 0) AS completefiles,
                COALESCE(SUM(partsize), 0) AS filesize')
            ->get()
            ->keyBy('collections_id');
        $staleBefore = now()->subHours($this->settings->collectionDelayTime)->format('Y-m-d H:i:s');
        $changes = [];

        foreach ($collections as $collection) {
            $aggregate = $aggregates->get($collection->id);
            $currentFiles = (int) ($aggregate->currentfiles ?? 0);
            $fileSize = (int) ($aggregate->filesize ?? 0);
            $createdAt = $collection->dateadded ?? $collection->added;
            $stale = $createdAt !== null
                && (string) $createdAt < $staleBefore
                && \in_array((int) $collection->filecheck, [0, 1, 10], true);
            $totalFiles = $stale && ! config('release_verification.required', true)
                ? $currentFiles : (int) $collection->totalfiles;
            $ready = $totalFiles > 0
                && \in_array($currentFiles, [$totalFiles, $totalFiles + 1], true)
                && (int) ($aggregate->completefiles ?? 0) >= $totalFiles;
            $fileCheck = $ready || $stale ? CollectionFileCheckStatus::CompleteParts->value : (int) $collection->filecheck;
            if ($fileSize === (int) $collection->filesize
                && $totalFiles === (int) $collection->totalfiles
                && $fileCheck === (int) $collection->filecheck) {
                continue;
            }

            $changes[] = [
                'id' => (int) $collection->id,
                'old' => [(int) $collection->filecheck, (int) $collection->totalfiles, (int) $collection->filesize],
                'new' => [$fileSize, $totalFiles, $fileCheck],
            ];
        }

        $this->writeCollectionAggregates($changes);
    }

    /**
     * Write the changed aggregates in one UPDATE per batch instead of one per row (a group
     * can have millions of stale collections). Each row is still written only if it holds
     * the values read; otherwise header storage changed it and its own refresh is newer.
     *
     * @param  list<array{id: int, old: array{int, int, int}, new: array{int, int, int}}>  $changes
     */
    private function writeCollectionAggregates(array $changes): void
    {
        foreach (array_chunk($changes, max(1, min(500, $this->binariesConfig->sqlChunkSize))) as $chunk) {
            $set = ['filesize' => [], 'totalfiles' => [], 'filecheck' => []];
            $guard = [];
            $bindings = ['filesize' => [], 'totalfiles' => [], 'filecheck' => [], 'guard' => [], 'ids' => []];
            foreach ($chunk as $change) {
                [$newSize, $newTotal, $newCheck] = $change['new'];
                [$oldCheck, $oldTotal, $oldSize] = $change['old'];
                foreach (['filesize' => $newSize, 'totalfiles' => $newTotal, 'filecheck' => $newCheck] as $column => $value) {
                    $set[$column][] = 'WHEN ? THEN ?';
                    array_push($bindings[$column], $change['id'], $value);
                }
                $guard[] = 'WHEN ? THEN (filecheck = ? AND totalfiles = ? AND filesize = ?)';
                array_push($bindings['guard'], $change['id'], $oldCheck, $oldTotal, $oldSize);
                $bindings['ids'][] = $change['id'];
            }
            $placeholders = implode(',', array_fill(0, \count($chunk), '?'));
            $sql = 'UPDATE collections SET '
                .'filesize = CASE id '.implode(' ', $set['filesize']).' END, '
                .'totalfiles = CASE id '.implode(' ', $set['totalfiles']).' END, '
                .'filecheck = CASE id '.implode(' ', $set['filecheck']).' END '
                // A flat CASE rather than a chain of ORs, which SQLite rejects past ~1000 terms.
                ."WHERE id IN ({$placeholders}) AND CASE id ".implode(' ', $guard).' ELSE 0 END';
            $values = [...$bindings['filesize'], ...$bindings['totalfiles'], ...$bindings['filecheck'], ...$bindings['ids'], ...$bindings['guard']];
            // Header storage locks collections in a different order inside its own
            // transactions, so a batch can lose a deadlock: retry it. If retries run out
            // the error propagates, as before, so later stages never size rows whose
            // aggregates weren't written.
            DB::transaction(static fn (): int => DB::update($sql, $values), self::MAX_RETRIES);
        }
    }

    /**
     * Delete collections that don't meet size/file count requirements.
     *
     * @param  list<int>|null  $windowIds
     * @return list<int> Rejected IDs in the supplied window, including failed deletes.
     *
     * @throws Throwable
     */
    public function deleteUnwantedCollections(int|string|null $groupID, ?array $windowIds = null): array
    {
        if ($windowIds === []) {
            return [];
        }
        if ($windowIds !== null && count($windowIds) > self::BATCH_SIZE) {
            throw new \InvalidArgumentException('Collection validation windows must contain at most 500 IDs.');
        }

        if (config('release_verification.required', true)) {
            return [];
        }

        $rejectedIds = $windowIds === null ? null : [];
        $startTime = now()->toImmutable();
        $this->outputSubHeader('Filtering Collections by Size/File Count');

        $normalizedGroupId = $this->normalizeGroupId($groupID);
        $groupIDs = $normalizedGroupId === null
            ? UsenetGroup::getActiveIDs()
            : [['id' => $normalizedGroupId]];
        if ($normalizedGroupId === null && $windowIds !== null) {
            $windowGroupIds = Collection::query()->whereIn('id', $windowIds)->pluck('groups_id')->map(static fn ($id): int => (int) $id)->all();
            $groupIDs = $groupIDs->filter(static fn ($group): bool => in_array((int) $group->id, $windowGroupIds, true));
        }

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
        if ($windowIds !== null) {
            $par2OnlyCollectionIds->whereIn('c.id', $windowIds);
        }
        $stats['par2Only'] += $this->deleteCollectionQueryInBatches($par2OnlyCollectionIds, 'Par2-only cleanup', $rejectedIds);

        foreach ($groupIDs as $grpID) {
            $groupSettings = UsenetGroup::getGroupByID($grpID['id']);
            $groupMinSize = (int) ($groupSettings['minsizetoformrelease'] ?? 0);
            $groupMinFiles = (int) ($groupSettings['minfilestoformrelease'] ?? 0);

            if (! $this->hasSizedCollections((int) $grpID['id'], $windowIds)) {
                continue;
            }

            $effectiveMinSize = max($groupMinSize, $this->settings->minSizeToFormRelease);
            if ($effectiveMinSize > 0) {
                $ids = Collection::query()
                    ->where('filecheck', CollectionFileCheckStatus::Sized->value)
                    ->where('groups_id', (int) $grpID['id'])
                    ->where('filesize', '>', 0)
                    ->where('filesize', '<', $effectiveMinSize);
                if ($windowIds !== null) {
                    $ids->whereIn('id', $windowIds);
                }
                $stats['minSize'] += $this->deleteCollectionQueryInBatches($ids, 'Min-size cleanup', $rejectedIds);
            }

            if ($this->settings->maxSizeToFormRelease > 0) {
                $ids = Collection::query()
                    ->where('filecheck', CollectionFileCheckStatus::Sized->value)
                    ->where('groups_id', (int) $grpID['id'])
                    ->where('filesize', '>', $this->settings->maxSizeToFormRelease);
                if ($windowIds !== null) {
                    $ids->whereIn('id', $windowIds);
                }
                $stats['maxSize'] += $this->deleteCollectionQueryInBatches($ids, 'Max-size cleanup', $rejectedIds);
            }

            $effectiveMinFiles = max($groupMinFiles, $this->settings->minFilesToFormRelease);
            if ($effectiveMinFiles > 0) {
                $ids = Collection::query()
                    ->where('filecheck', CollectionFileCheckStatus::Sized->value)
                    ->where('groups_id', (int) $grpID['id'])
                    ->where('filesize', '>', 0)
                    ->where('totalfiles', '<', $effectiveMinFiles);
                if ($windowIds !== null) {
                    $ids->whereIn('id', $windowIds);
                }
                $stats['minFiles'] += $this->deleteCollectionQueryInBatches($ids, 'Min-files cleanup', $rejectedIds);
            }
        }

        $this->outputCollectionDeleteStats($stats, $startTime);

        return array_keys($rejectedIds ?? []);
    }

    /**
     * Create releases from complete collections.
     *
     * @param  list<int>|null  $allowedIds
     *
     * @throws Throwable
     */
    public function createReleases(int|string|null $groupID, ?array $allowedIds = null): ReleaseCreationResult
    {
        $result = $this->releaseCreationService->createReleases(
            $groupID,
            $this->settings->releaseCreationLimit,
            $this->echoCLI,
            $allowedIds
        );

        return ReleaseCreationResult::from($result);
    }

    /**
     * Create releases and their NZBs, indexing each touched release once, in bulk, at the end
     * of the pass instead of at creation and again when its NZB is written.
     *
     * @param  list<int>|null  $allowedIds
     * @return array{0: ReleaseCreationResult, 1: int} The creation result and the number of NZBs written.
     *
     * @throws Throwable
     */
    public function createReleasesAndNzbs(int|string|null $groupID, ?array $allowedIds = null, ?int $nzbLimit = null): array
    {
        return app(SearchService::class)->deferReleaseUpdates(function () use ($groupID, $allowedIds, $nzbLimit): array {
            $result = $this->createReleases($groupID, $allowedIds);
            if ($nzbLimit === null) {
                return [$result, $this->createNZBs($groupID)];
            }
            $preferredIds = Collection::query()->whereIn('id', $allowedIds ?? [])
                ->whereNotNull('releases_id')->pluck('releases_id')->map(static fn ($id): int => (int) $id)->all();

            return [$result, $this->createNZBs($groupID, $nzbLimit, $preferredIds)];
        });
    }

    /**
     * Create NZB files from releases that don't have them yet.
     *
     * @param  list<int>  $preferredIds
     *
     * @throws Throwable
     */
    public function createNZBs(int|string|null $groupID, ?int $maxReleases = null, array $preferredIds = []): int
    {
        $startTime = now()->toImmutable();
        $this->outputSubHeader('Creating NZB Files');

        $nzbCount = 0;
        $retryCount = 0;
        $deletedCount = 0;
        $claimToken = bin2hex(random_bytes(16));
        $limit = max(1, $this->settings->releaseCreationLimit);
        if ($maxReleases !== null) {
            $limit = min($limit, max(1, $maxReleases));
        }
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
        $releases = $maxReleases === null ? null : NzbCreationCandidateQuery::claimBatch($groupID, $limit, $claimToken, $columns, $preferredIds);
        $total = $releases === null ? min(NzbCreationCandidateQuery::baseBuilder($groupID)->count(), $limit) : $releases->count();

        if ($total > 0) {
            $processed = 0;
            $releases ??= NzbCreationCandidateQuery::claimBatch($groupID, $limit, $claimToken, $columns);
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
        if (! $release->relationLoaded('nzbCreationFailure') && NzbCreationCandidateQuery::supportsFailureState()) {
            $release->loadMissing('nzbCreationFailure');
        }

        $failureState = $release->relationLoaded('nzbCreationFailure')
            ? $release->getRelation('nzbCreationFailure')
            : null;

        return ($failureState instanceof ReleaseNzbCreationFailure ? $failureState->attempts : 0) + 1;
    }

    private function recordNzbCreationRetry(Release $release, NzbCreationResult $result): void
    {
        $nextAttempt = $this->nextNzbCreationAttempt($release);
        $failure = ReleaseNzbCreationFailure::query()->updateOrCreate(
            ['releases_id' => (int) $release->id],
            [
                'attempts' => $nextAttempt,
                'last_error' => mb_substr($result->reason, 0, 1000),
            ],
        );
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

    /** @param list<int>|null $windowIds */
    private function hasSizedCollections(int $groupId, ?array $windowIds = null): bool
    {
        return Collection::query()
            ->where('filecheck', CollectionFileCheckStatus::Sized->value)
            ->where('groups_id', $groupId)
            ->when($windowIds !== null, static fn ($q) => $q->whereIn('id', $windowIds))
            ->where('filesize', '>', 0)
            ->exists();
    }

    /**
     * @param  Builder|\Illuminate\Database\Eloquent\Builder<Collection>  $query
     * @param  array<int, bool>|null  $rejectedIds
     */
    private function deleteCollectionQueryInBatches(
        Builder|\Illuminate\Database\Eloquent\Builder $query,
        string $label,
        ?array &$rejectedIds = null,
    ): int {
        $deleted = 0;
        $idColumn = $query instanceof \Illuminate\Database\Eloquent\Builder ? 'id' : 'c.id';
        do {
            // No ORDER BY: each batch deletes what it selected and the next query starts
            // over, so order doesn't matter, and sorting by id made MariaDB walk the primary
            // key through millions of pending collections instead of using a filecheck index.
            $ids = (clone $query)->limit(self::BATCH_SIZE)->pluck($idColumn)->all();
            if ($ids === []) {
                break;
            }
            if ($rejectedIds !== null) {
                foreach ($ids as $id) {
                    $rejectedIds[(int) $id] = true;
                }
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
        if (config('release_verification.required', true)) {
            return;
        }
        $totalDeleted = 0;

        do {
            $affected = $this->withCollectionReconciliationBarrier(
                fn (): int => $this->deleteStuckCollectionBatch($groupID, $this->calculateStuckCollectionsCutoff())
            );
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
                    // No ORDER BY: each batch deletes what it selected and the next query
                    // starts over (see deleteCollectionQueryInBatches()).
                    ->limit(self::BATCH_SIZE);
                if (Schema::hasColumn('collections', 'last_seen_at')) {
                    // Same as COALESCE(last_seen_at, dateadded, added) < cutoff, but written
                    // so the (groups_id, filecheck, last_seen_at, id) index can range-scan it;
                    // the COALESCE form made MariaDB read every collection in the group.
                    $query->where(static function ($stale) use ($cutoff): void {
                        $stale->where('last_seen_at', '<', $cutoff)
                            ->orWhere(static function ($unseen) use ($cutoff): void {
                                $unseen->whereNull('last_seen_at')
                                    ->whereRaw('COALESCE(dateadded, added) < ?', [$cutoff]);
                            });
                    });
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
