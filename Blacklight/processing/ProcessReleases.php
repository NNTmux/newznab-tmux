<?php

declare(strict_types=1);

namespace Blacklight\processing;

use App\Enums\CollectionFileCheckStatus;
use App\Enums\FileCompletionStatus;
use App\Models\Category;
use App\Models\Collection;
use App\Models\MusicInfo;
use App\Models\Release;
use App\Models\Settings;
use App\Models\UsenetGroup;
use App\Services\Categorization\CategorizationService;
use App\Services\CollectionCleanupService;
use App\Services\ReleaseCreationService;
use App\Support\DTOs\ProcessReleasesSettings;
use App\Support\DTOs\ReleaseCreationResult;
use App\Support\DTOs\ReleaseDeleteStats;
use Blacklight\ColorCLI;
use Blacklight\Genres;
use Blacklight\NNTP;
use Blacklight\NZB;
use Blacklight\ReleaseCleaning;
use Blacklight\ReleaseImage;
use Blacklight\Releases;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Processes collections into releases and creates NZB files.
 *
 * This class handles the complete release processing pipeline:
 * - Finding complete collections
 * - Calculating collection sizes
 * - Creating releases from collections
 * - Generating NZB files
 * - Categorizing releases
 * - Cleanup of old/unwanted releases
 *
 * @phpstan-type GroupId int|string|null
 */
final class ProcessReleases
{
    // Legacy constants for backward compatibility - use enums for new code
    /** @deprecated Use CollectionFileCheckStatus::Default instead */
    public const COLLFC_DEFAULT = 0;
    /** @deprecated Use CollectionFileCheckStatus::CompleteCollection instead */
    public const COLLFC_COMPCOLL = 1;
    /** @deprecated Use CollectionFileCheckStatus::CompleteParts instead */
    public const COLLFC_COMPPART = 2;
    /** @deprecated Use CollectionFileCheckStatus::Sized instead */
    public const COLLFC_SIZED = 3;
    /** @deprecated Use CollectionFileCheckStatus::Inserted instead */
    public const COLLFC_INSERTED = 4;
    /** @deprecated Use CollectionFileCheckStatus::Delete instead */
    public const COLLFC_DELETE = 5;
    /** @deprecated Use CollectionFileCheckStatus::TempComplete instead */
    public const COLLFC_TEMPCOMP = 15;
    /** @deprecated Use CollectionFileCheckStatus::ZeroPart instead */
    public const COLLFC_ZEROPART = 16;

    /** @deprecated Use FileCompletionStatus::Incomplete instead */
    public const FILE_INCOMPLETE = 0;
    /** @deprecated Use FileCompletionStatus::Complete instead */
    public const FILE_COMPLETE = 1;

    /** Batch size for bulk operations */
    private const int BATCH_SIZE = 500;

    /** Maximum retry attempts for database operations */
    private const int MAX_RETRIES = 5;

    /** Base delay for exponential backoff (microseconds) */
    private const int RETRY_BASE_DELAY_US = 20000;

    /** Pause between batch operations (microseconds) */
    private const int BATCH_PAUSE_US = 10000;

    /** Chunk size for release categorization */
    private const int CATEGORIZE_CHUNK_SIZE = 1000;

    /** Chunk size for NZB creation */
    private const int NZB_CHUNK_SIZE = 100;

    public bool $echoCLI;

    private readonly ProcessReleasesSettings $settings;
    private readonly ColorCLI $colorCLI;
    private readonly NZB $nzb;
    private readonly ReleaseCleaning $releaseCleaning;
    private readonly Releases $releases;
    private readonly ReleaseImage $releaseImage;
    private readonly ReleaseCreationService $releaseCreationService;
    private readonly CollectionCleanupService $collectionCleanupService;

    /**
     * @param ColorCLI|null $colorCLI Console output handler
     * @param NZB|null $nzb NZB file handler
     * @param ReleaseCleaning|null $releaseCleaning Release name cleaner
     * @param Releases|null $releases Release operations handler
     * @param ReleaseImage|null $releaseImage Release image handler
     * @param ReleaseCreationService|null $releaseCreationService Service for creating releases
     * @param CollectionCleanupService|null $collectionCleanupService Service for cleaning collections
     */
    public function __construct(
        ?ColorCLI $colorCLI = null,
        ?NZB $nzb = null,
        ?ReleaseCleaning $releaseCleaning = null,
        ?Releases $releases = null,
        ?ReleaseImage $releaseImage = null,
        ?ReleaseCreationService $releaseCreationService = null,
        ?CollectionCleanupService $collectionCleanupService = null,
    ) {
        $this->echoCLI = (bool) config('nntmux.echocli');

        // Initialize dependencies with defaults
        $this->colorCLI = $colorCLI ?? new ColorCLI();
        $this->nzb = $nzb ?? new NZB();
        $this->releaseCleaning = $releaseCleaning ?? new ReleaseCleaning();
        $this->releases = $releases ?? new Releases();
        $this->releaseImage = $releaseImage ?? new ReleaseImage();

        $this->releaseCreationService = $releaseCreationService
            ?? new ReleaseCreationService($this->colorCLI, $this->releaseCleaning);
        $this->collectionCleanupService = $collectionCleanupService
            ?? new CollectionCleanupService($this->colorCLI);

        $this->settings = $this->loadSettings();
        $this->validateSettings();
    }

    /**
     * Load all required settings from database.
     */
    private function loadSettings(): ProcessReleasesSettings
    {
        $settingKeys = [
            'delaytime',
            'crossposttime',
            'maxnzbsprocessed',
            'completionpercent',
            'collection_timeout',
            'maxsizetoformrelease',
            'minsizetoformrelease',
            'minfilestoformrelease',
            'releaseretentiondays',
            'deletepasswordedrelease',
            'miscotherretentionhours',
            'mischashedretentionhours',
            'partretentionhours',
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
        if (!$this->settings->hasValidCompletion()) {
            $this->colorCLI->error(
                PHP_EOL . 'Invalid completion setting. Value must be between 0 and 100.'
            );
        }
    }

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
     * Main method for creating releases/NZB files from collections.
     *
     * This orchestrates the complete release processing pipeline:
     * 1. Process incomplete collections to find complete ones
     * 2. Calculate collection sizes
     * 3. Delete unwanted collections based on size/file count rules
     * 4. Create releases from sized collections
     * 5. Generate NZB files for new releases
     * 6. Categorize new releases
     * 7. Run post-processing (if enabled)
     * 8. Clean up processed collections
     * 9. Delete unwanted releases based on retention/quality rules
     *
     * @param int $categorize Categorization type (1=name, 2=searchname)
     * @param int $postProcess Whether to run post-processing (1=yes)
     * @param string $groupName Optional group name to filter processing
     * @param NNTP $nntp NNTP connection for post-processing
     * @return int Total number of releases added
     *
     * @throws Throwable
     */
    public function processReleases(int $categorize, int $postProcess, string $groupName, NNTP $nntp): int
    {
        $this->echoCLI = (bool) config('nntmux.echocli');

        if ($this->echoCLI) {
            $this->colorCLI->header(
                'Starting release update process (' . now()->format('Y-m-d H:i:s') . ')'
            );
        }

        if (!$this->validateNzbPath()) {
            return 0;
        }

        $groupID = $this->resolveGroupId($groupName);
        $normalizedGroupId = $this->normalizeGroupId($groupID);

        // Phase 1: Collection processing
        $this->processIncompleteCollections($normalizedGroupId);
        $this->processCollectionSizes($normalizedGroupId);
        $this->deleteUnwantedCollections($normalizedGroupId);

        // Phase 2: Release creation loop
        $totalReleasesAdded = 0;
        $limit = $this->settings->releaseCreationLimit;

        do {
            $result = $this->createReleases($normalizedGroupId);
            $totalReleasesAdded += $result->added;

            $nzbFilesAdded = $this->createNZBs($normalizedGroupId);

            $this->categorizeReleases($categorize, $normalizedGroupId);
            $this->postProcessReleases($postProcess, $nntp);
            $this->deleteCollections($normalizedGroupId);

            // Continue processing if we hit the limit (more work to do)
            $shouldContinue = $result->total() >= $limit || $nzbFilesAdded >= $limit;
        } while ($shouldContinue);

        // Phase 3: Cleanup
        $this->deleteReleases();

        return $totalReleasesAdded;
    }

    /**
     * Validate that the NZB storage path exists.
     */
    private function validateNzbPath(): bool
    {
        $nzbPath = config('nntmux_settings.path_to_nzbs');

        if (!file_exists($nzbPath)) {
            if ($this->echoCLI) {
                $this->colorCLI->error("Bad or missing NZB directory - {$nzbPath}");
            }
            return false;
        }

        return true;
    }

    /**
     * Resolve a group name to its database ID.
     */
    private function resolveGroupId(string $groupName): string
    {
        if ($groupName === '') {
            return '';
        }

        $groupInfo = UsenetGroup::getByName($groupName);

        return $groupInfo !== null ? (string) $groupInfo['id'] : '';
    }

    /**
     * Reset all releases to other->misc category.
     *
     * @param string $where Optional WHERE clause to limit which releases are reset
     */
    public function resetCategorize(string $where = ''): void
    {
        if ($where !== '') {
            DB::update(
                'UPDATE releases SET categories_id = ?, iscategorized = 0 ' . $where,
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
     * Categorize uncategorized releases.
     *
     * @param string $type Column to use for categorization ('name' or 'searchname')
     * @param int|string|null $groupId Optional group ID to filter releases
     * @return int Number of releases categorized
     *
     * @throws \Exception
     */
    public function categorizeRelease(string $type, int|string|null $groupId): int
    {
        $categorizer = new CategorizationService();
        $categorized = 0;

        $query = Release::query()
            ->where('categories_id', Category::OTHER_MISC)
            ->where('iscategorized', 0)
            ->select(['id', 'fromname', 'groups_id', $type]);

        if (!empty($groupId)) {
            $query->where('groups_id', $groupId);
        }

        $total = $query->count();
        if ($total === 0) {
            return 0;
        }

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

                if ($this->echoCLI) {
                    $this->colorCLI->overWritePrimary(
                        'Categorizing: ' . $this->colorCLI->percentString($categorized, $total)
                    );
                }
            }

            return true;
        });

        if ($this->echoCLI && $categorized > 0) {
            echo PHP_EOL;
        }

        return $categorized;
    }

    /**
     * Process incomplete collections to find complete ones.
     *
     * @throws \Exception
     * @throws Throwable
     */
    public function processIncompleteCollections(int|string|null $groupID): void
    {
        $startTime = now()->toImmutable();

        if ($this->echoCLI) {
            $this->colorCLI->header('Process Releases -> Attempting to find complete collections.');
        }

        $normalizedGroupId = $this->normalizeGroupId($groupID);
        $whereSql = $this->buildGroupWhereSql($normalizedGroupId, 'c');

        // Run all collection check stages
        $this->processStuckCollections($normalizedGroupId ?? 0);
        $this->runCollectionFileCheckStage1($normalizedGroupId ?? 0);
        $this->runCollectionFileCheckStage2($normalizedGroupId ?? 0);
        $this->runCollectionFileCheckStage3($whereSql);
        $this->runCollectionFileCheckStage4($whereSql);
        $this->runCollectionFileCheckStage5($normalizedGroupId ?? 0);
        $this->runCollectionFileCheckStage6($whereSql);

        if ($this->echoCLI) {
            $count = $this->countCompleteCollections($normalizedGroupId);
            $elapsed = now()->diffInSeconds($startTime, true);

            $this->colorCLI->primary(
                "{$count} collections were found to be complete. Time: {$elapsed}" .
                Str::plural(' second', $elapsed),
                true
            );
        }
    }

    /**
     * Count collections marked as complete.
     */
    private function countCompleteCollections(?int $groupId): int
    {
        $query = Collection::query()
            ->where('filecheck', CollectionFileCheckStatus::CompleteParts->value);

        if ($groupId !== null) {
            $query->where('groups_id', $groupId);
        }

        return $query->count('id');
    }

    /**
     * Calculate sizes for complete collections.
     *
     * @throws \Exception
     * @throws Throwable
     */
    public function processCollectionSizes(int|string|null $groupID): void
    {
        $startTime = now()->toImmutable();

        if ($this->echoCLI) {
            $this->colorCLI->header('Process Releases -> Calculating collection sizes (in bytes).');
        }

        DB::transaction(function () use ($groupID, $startTime): void {
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

            if ($updated > 0 && $this->echoCLI) {
                $elapsed = now()->diffInSeconds($startTime, true);
                $this->colorCLI->primary(
                    "{$updated} collections set to filecheck = 3 (size calculated)",
                    true
                );
                $this->colorCLI->primary(
                    $elapsed . Str::plural(' second', $elapsed),
                    true
                );
            }
        }, 10);
    }

    /**
     * Delete collections that don't meet size/file count requirements.
     *
     * @throws \Exception
     * @throws Throwable
     */
    public function deleteUnwantedCollections(int|string|null $groupID): void
    {
        $startTime = now()->toImmutable();

        if ($this->echoCLI) {
            $this->colorCLI->header(
                'Process Releases -> Delete collections smaller/larger than minimum size/file count from group/site setting.'
            );
        }

        $normalizedGroupId = $this->normalizeGroupId($groupID);
        $groupIDs = $normalizedGroupId === null
            ? UsenetGroup::getActiveIDs()
            : [['id' => $normalizedGroupId]];

        $stats = ['minSize' => 0, 'maxSize' => 0, 'minFiles' => 0];

        foreach ($groupIDs as $grpID) {
            $groupSettings = UsenetGroup::getGroupByID($grpID['id']);
            $groupMinSize = (int) ($groupSettings['minsizetoformrelease'] ?? 0);
            $groupMinFiles = (int) ($groupSettings['minfilestoformrelease'] ?? 0);

            if (!$this->hasSizedCollections()) {
                continue;
            }

            DB::transaction(function () use ($groupMinSize, $groupMinFiles, &$stats): void {
                // Delete collections smaller than minimum size
                $effectiveMinSize = max($groupMinSize, $this->settings->minSizeToFormRelease);
                if ($effectiveMinSize > 0) {
                    $stats['minSize'] += Collection::query()
                        ->where('filecheck', CollectionFileCheckStatus::Sized->value)
                        ->where('filesize', '>', 0)
                        ->where('filesize', '<', $effectiveMinSize)
                        ->delete();
                }

                // Delete collections larger than maximum size
                if ($this->settings->maxSizeToFormRelease > 0) {
                    $stats['maxSize'] += Collection::query()
                        ->where('filecheck', CollectionFileCheckStatus::Sized->value)
                        ->where('filesize', '>', $this->settings->maxSizeToFormRelease)
                        ->delete();
                }

                // Delete collections with fewer files than minimum
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
     * Check if there are any sized collections to process.
     */
    private function hasSizedCollections(): bool
    {
        return Collection::query()
            ->where('filecheck', CollectionFileCheckStatus::Sized->value)
            ->where('filesize', '>', 0)
            ->exists();
    }

    /**
     * Output collection deletion statistics.
     *
     * @param array{minSize: int, maxSize: int, minFiles: int} $stats
     */
    private function outputCollectionDeleteStats(array $stats, DateTimeInterface $startTime): void
    {
        $totalDeleted = $stats['minSize'] + $stats['maxSize'] + $stats['minFiles'];
        $elapsed = now()->diffInSeconds($startTime, true);

        if ($this->echoCLI && $totalDeleted > 0) {
            $this->colorCLI->primary(
                "Deleted {$totalDeleted} collections: " . PHP_EOL .
                "{$stats['minSize']} smaller than, {$stats['maxSize']} bigger than, " .
                "{$stats['minFiles']} with less files than site/group settings in: " .
                $elapsed . Str::plural(' second', $elapsed),
                true
            );
        }
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

        if ($this->echoCLI) {
            $this->colorCLI->header('Process Releases -> Create the NZB, delete collections/binaries/parts.');
        }

        $query = Release::query()
            ->with('category.parent')
            ->where('nzbstatus', '=', NZB::NZB_NONE)
            ->select(['id', 'guid', 'name', 'categories_id']);

        if (!empty($groupID)) {
            $query->where('releases.groups_id', $groupID);
        }

        $nzbCount = 0;
        $total = $query->count();

        if ($total > 0) {
            $query->chunkById(self::NZB_CHUNK_SIZE, function ($releases) use (&$nzbCount, $total): bool {
                foreach ($releases as $release) {
                    if ($this->nzb->writeNzbForReleaseId($release)) {
                        $nzbCount++;
                        if ($this->echoCLI) {
                            echo "Creating NZBs and deleting Collections: {$nzbCount}/{$total}.\r";
                        }
                    }
                }
                return true;
            });
        }

        $elapsed = now()->diffInSeconds($startTime, true);

        if ($this->echoCLI) {
            $this->colorCLI->primary(
                number_format($nzbCount) . ' NZBs created/Collections deleted in ' .
                $elapsed . Str::plural(' second', $elapsed) . PHP_EOL .
                'Total time: ' . $elapsed . Str::plural(' second', $elapsed),
                true
            );
        }

        return $nzbCount;
    }

    /**
     * Categorize releases based on the specified field.
     *
     * @param int $categorize Categorization type (1=name, 2=searchname)
     * @param int|string|null $groupID Optional group ID filter
     *
     * @throws \Exception
     */
    public function categorizeReleases(int $categorize, int|string|null $groupID = null): void
    {
        $startTime = now()->toImmutable();

        if ($this->echoCLI) {
            $this->colorCLI->header('Process Releases -> Categorize releases.');
        }

        $type = match ($categorize) {
            2 => 'searchname',
            default => 'name',
        };

        $this->categorizeRelease($type, $groupID);

        $elapsed = now()->diffInSeconds($startTime, true);

        if ($this->echoCLI) {
            $this->colorCLI->primary($elapsed . Str::plural(' second', $elapsed));
        }
    }

    /**
     * Run post-processing on releases.
     *
     * @throws \Exception
     */
    public function postProcessReleases(int $postProcess, NNTP $nntp): void
    {
        if ($postProcess === 1) {
            (new PostProcess(['Echo' => $this->echoCLI]))->processAll($nntp);
            return;
        }

        if ($this->echoCLI) {
            $this->colorCLI->info(
                'Post-processing is not running inside the Process Releases class.' . PHP_EOL .
                'If you are using tmux or screen they might have their own scripts running Post-processing.'
            );
        }
    }

    /**
     * Delete finished and orphaned collections.
     *
     * @throws \Exception
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
            $this->colorCLI->header(
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
     * Delete releases smaller than minimum size for a group.
     *
     * @param array{minSize: int, maxSize: int, minFiles: int} $stats
     */
    private function deleteReleasesUnderMinSize(int|string $groupId, array &$stats): void
    {
        $releases = Release::query()
            ->where('releases.groups_id', $groupId)
            ->join('usenet_groups', 'usenet_groups.id', '=', 'releases.groups_id')
            ->whereRaw(
                'GREATEST(IFNULL(usenet_groups.minsizetoformrelease, 0), ?) > 0 ' .
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
     * Delete releases larger than maximum size for a group.
     *
     * @param array{minSize: int, maxSize: int, minFiles: int} $stats
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
     * Delete releases with fewer files than minimum for a group.
     *
     * @param array{minSize: int, maxSize: int, minFiles: int} $stats
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
                'GREATEST(IFNULL(usenet_groups.minfilestoformrelease, 0), ?) > 0 ' .
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

    /**
     * Output statistics for group-based release deletion.
     *
     * @param array{minSize: int, maxSize: int, minFiles: int} $stats
     */
    private function outputReleaseDeleteByGroupStats(array $stats, DateTimeInterface $startTime): void
    {
        $elapsed = now()->diffInSeconds($startTime, true);
        $total = $stats['minSize'] + $stats['maxSize'] + $stats['minFiles'];

        if ($this->echoCLI) {
            $this->colorCLI->primary(
                "Deleted {$total} releases: " . PHP_EOL .
                "{$stats['minSize']} smaller than, {$stats['maxSize']} bigger than, " .
                "{$stats['minFiles']} with less files than site/groups setting in: " .
                $elapsed . Str::plural(' second', $elapsed),
                true
            );
        }
    }

    /**
     * Delete releases based on site-wide settings (retention, passwords, etc.).
     *
     * @throws \Exception
     */
    public function deleteReleases(): void
    {
        $startTime = now()->toImmutable();

        if ($this->echoCLI) {
            $this->colorCLI->header('Process Releases -> Delete old releases and passworded releases.');
        }

        $stats = new ReleaseDeleteStats();

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

    /**
     * Delete releases past the retention period.
     */
    private function deleteReleasesOverRetention(ReleaseDeleteStats $stats): ReleaseDeleteStats
    {
        if (!$this->settings->hasRetentionCleanup()) {
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

    /**
     * Delete passworded releases.
     */
    private function deletePasswordedReleases(ReleaseDeleteStats $stats): ReleaseDeleteStats
    {
        if (!$this->settings->deletePasswordedRelease) {
            return $stats;
        }

        Release::query()
            ->select(['id', 'guid'])
            ->where('passwordstatus', '=', Releases::PASSWD_RAR)
            ->orWhereIn('id', function ($query): void {
                $query->select('releases_id')
                    ->from('release_files')
                    ->where('passworded', '=', Releases::PASSWD_RAR);
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

    /**
     * Delete cross-posted (duplicate) releases.
     */
    private function deleteCrossPostedReleases(ReleaseDeleteStats $stats): ReleaseDeleteStats
    {
        if (!$this->settings->hasCrossPostDetection()) {
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

    /**
     * Delete releases under a completion threshold.
     */
    private function deleteIncompleteReleases(ReleaseDeleteStats $stats): ReleaseDeleteStats
    {
        if (!$this->settings->hasCompletionCleanup()) {
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

    /**
     * Delete releases from disabled categories.
     */
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

    /**
     * Delete releases smaller than category minimum size.
     */
    private function deleteCategoryMinSizeReleases(ReleaseDeleteStats $stats): ReleaseDeleteStats
    {
        $categories = Category::query()
            ->where('minsizetoformrelease', '>', 0)
            ->select(['id', 'minsizetoformrelease as minsize'])
            ->get();

        foreach ($categories as $category) {
            Release::query()
                ->where('categories_id', (int) $category->id)
                ->where('size', '<', (int) $category->minsize)
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

    /**
     * Delete releases from disabled music genres.
     */
    private function deleteDisabledGenreReleases(ReleaseDeleteStats $stats): ReleaseDeleteStats
    {
        $genres = new Genres();
        $genreList = $genres->getDisabledIDs();

        if ($genreList === null || $genreList->isEmpty()) {
            return $stats;
        }

        foreach ($genreList as $genre) {
            $musicInfoQuery = MusicInfo::query()
                ->where('genre_id', (int) $genre->id)
                ->select(['id']);

            Release::query()
                ->joinSub(
                    $musicInfoQuery,
                    'mi',
                    static fn($join) => $join->on('releases.musicinfo_id', '=', 'mi.id')
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

    /**
     * Delete misc releases based on retention settings.
     */
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

    /**
     * Delete a single release with all associated data.
     */
    private function deleteSingleRelease(object $release): void
    {
        $this->releases->deleteSingle(
            ['g' => $release->guid, 'i' => $release->id],
            $this->nzb,
            $this->releaseImage
        );
    }

    /**
     * Output release deletion statistics.
     */
    private function outputReleaseDeleteStats(ReleaseDeleteStats $stats, DateTimeInterface $startTime): void
    {
        if (!$this->echoCLI) {
            return;
        }

        $completionSuffix = $this->settings->hasCompletionCleanup()
            ? ', ' . number_format($stats->completion) . " under {$this->settings->completion}% completion."
            : '.';

        $this->colorCLI->primary(
            'Removed releases: ' .
            number_format($stats->retention) . ' past retention, ' .
            number_format($stats->password) . ' passworded, ' .
            number_format($stats->duplicate) . ' crossposted, ' .
            number_format($stats->disabledCategory) . ' from disabled categories, ' .
            number_format($stats->categoryMinSize) . ' smaller than category settings, ' .
            number_format($stats->disabledGenre) . ' from disabled music genres, ' .
            number_format($stats->miscOther) . ' from misc->other ' .
            number_format($stats->miscHashed) . ' from misc->hashed' .
            $completionSuffix,
            true
        );

        $total = $stats->total();
        if ($total > 0) {
            $elapsed = now()->diffInSeconds($startTime, true);
            $this->colorCLI->primary(
                'Removed ' . number_format($total) . ' releases in ' .
                $elapsed . Str::plural(' second', $elapsed),
                true
            );
        }
    }

    /**
     * Collection file check stage 1: Find complete collections.
     *
     * Marks collections as complete when they have all expected binary files.
     *
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
                ->joinSub(
                    $collectionsQuery,
                    'r',
                    static fn($join) => $join->on('collections.id', '=', 'r.id')
                )
                ->update(['collections.filecheck' => CollectionFileCheckStatus::CompleteCollection->value]);
        }, 10);
    }

    /**
     * Collection file check stage 2: Handle zero-part collections.
     *
     * Identifies collections that have binaries with file number 0 (special handling).
     *
     * @throws Throwable
     */
    private function runCollectionFileCheckStage2(int $groupID): void
    {
        // Mark collections with zero-numbered files
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
                ->joinSub(
                    $collectionsQuery,
                    'r',
                    static fn($join) => $join->on('collections.id', '=', 'r.id')
                )
                ->update(['collections.filecheck' => CollectionFileCheckStatus::ZeroPart->value]);
        }, 10);

        // Mark remaining complete collections as temporarily complete
        DB::transaction(static function () use ($groupID): void {
            $query = Collection::query()
                ->where('filecheck', '=', CollectionFileCheckStatus::CompleteCollection->value);

            if ($groupID !== 0) {
                $query->where('groups_id', $groupID);
            }

            $query->update(['filecheck' => CollectionFileCheckStatus::TempComplete->value]);
        }, 10);
    }

    /**
     * Collection file check stage 3: Mark complete binaries.
     *
     * Updates binary records where all parts have been received.
     *
     * @throws Throwable
     */
    private function runCollectionFileCheckStage3(string $whereSql): void
    {
        // Mark binaries with exact part count
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

        // Mark binaries with extra parts (zero-part handling)
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
     * Collection file check stage 4: Mark complete collections.
     *
     * Updates collections where all binaries are complete.
     *
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
     * Collection file check stage 5: Reset incomplete collections.
     *
     * Moves collections that didn't complete back to the complete collection status.
     *
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
     * Collection file check stage 6: Force complete delayed collections.
     *
     * For collections older than the delay time, force them to complete status.
     *
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
     * Delete stuck/broken collections.
     *
     * Collections that are older than the timeout threshold and haven't progressed.
     *
     * @throws \Exception
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
            $this->colorCLI->primary("Deleted {$totalDeleted} broken/stuck collections.", true);
        }
    }

    /**
     * Calculate the cutoff timestamp for stuck collections.
     */
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

    /**
     * Delete a batch of stuck collections with retry logic.
     */
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
                        $this->colorCLI->error(
                            'Stuck collections delete failed after retries: ' . $e->getMessage()
                        );
                    }
                    break;
                }
                usleep(self::RETRY_BASE_DELAY_US * $attempt);
            }
        } while (true);

        return $affected;
    }

    /**
     * Normalize a group ID to integer form.
     *
     * @param int|string|null $groupID Group ID as int, string, or null
     * @return int|null Normalized integer ID or null
     */
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
     * Build a SQL WHERE clause snippet for group ID filtering.
     *
     * @param int|null $groupID The group ID to filter by
     * @param string $alias Table alias to use in the clause
     * @return string SQL WHERE clause snippet
     */
    private function buildGroupWhereSql(?int $groupID, string $alias = 'c'): string
    {
        return $groupID !== null ? " AND {$alias}.groups_id = {$groupID} " : ' ';
    }
}
