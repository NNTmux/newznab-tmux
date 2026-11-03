<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

use App\Enums\RenameApplyResult;
use App\Events\ReleaseNameFixed;
use App\Models\Category;
use App\Models\Release;
use App\Models\UsenetGroup;
use App\Services\AdditionalProcessing\ReleaseSearchSyncCoordinator;
use App\Services\AdditionalProcessing\State\PersistenceMetricsCollector;
use App\Services\Categorization\CategorizationService;
use App\Services\NameFixing\Data\ReleaseIdentity;
use App\Services\ReleaseCleaningService;
use App\Services\TvProcessing\TvProcessingCandidateQuery;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service for updating releases with new names.
 *
 * Handles the actual database updates and category re-determination
 * when a release is renamed.
 */
class ReleaseUpdateService
{
    /**
     * @var list<string>
     */
    private const SINGLE_UPDATE_COLUMNS = [
        'predb_id',
        'proc_nfo',
        'proc_files',
        'proc_par2',
        'proc_uid',
        'proc_hash16k',
        'proc_srr',
        'proc_crc32',
    ];

    /**
     * Length of releases.searchname (varchar(255), counted in characters).
     */
    public const MAX_SEARCHNAME_LENGTH = 255;

    /**
     * PreDB regex pattern for scene release names.
     */
    public const PREDB_REGEX = '/([\w.\'()\[\]+%-]+(?:[\s._-]+[\w.\'()\[\]+%-]+)+[-.][\w]+)/ui';

    // Constants for name fixing status
    public const PROC_NFO_NONE = 0;

    public const PROC_NFO_DONE = 1;

    public const PROC_FILES_NONE = 0;

    public const PROC_FILES_DONE = 1;

    public const PROC_PAR2_NONE = 0;

    public const PROC_PAR2_DONE = 1;

    public const PROC_UID_NONE = 0;

    public const PROC_UID_DONE = 1;

    public const PROC_HASH16K_NONE = 0;

    public const PROC_HASH16K_DONE = 1;

    public const PROC_SRR_NONE = 0;

    public const PROC_SRR_DONE = 1;

    public const PROC_CRC_NONE = 0;

    public const PROC_CRC_DONE = 1;

    // Constants for overall rename status
    public const IS_RENAMED_NONE = 0;

    public const IS_RENAMED_DONE = 1;

    protected CategorizationService $category;

    protected FileNameCleaner $fileNameCleaner;

    protected bool $echoOutput;

    private readonly ReleaseSearchSyncCoordinator $searchSyncCoordinator;

    /**
     * The release ID we are trying to rename.
     */
    protected int $relid = 0;

    /**
     * Has the current release found a new name?
     */
    public bool $matched = false;

    /**
     * Was the check completed?
     */
    public bool $done = false;

    /**
     * How many releases have got a new name?
     */
    public int $fixed = 0;

    /**
     * How many releases were checked.
     */
    public int $checked = 0;

    /**
     * Outcome of the last rename or PreDB link this service tried to write, if any.
     */
    public ?RenameApplyResult $lastResult = null;

    public function __construct(
        ?CategorizationService $category = null,
        ?FileNameCleaner $fileNameCleaner = null,
        ?ReleaseSearchSyncCoordinator $searchSyncCoordinator = null,
    ) {
        $this->category = $category ?? new CategorizationService;
        $this->fileNameCleaner = $fileNameCleaner ?? new FileNameCleaner;
        $this->searchSyncCoordinator = $searchSyncCoordinator
            ?? new ReleaseSearchSyncCoordinator(new PersistenceMetricsCollector);
        $this->echoOutput = config('nntmux.echocli');
    }

    /**
     * Update the release with the new information.
     *
     * @param  object|array<string, mixed>  $release  The release to update
     * @param  string  $name  The new name
     * @param  string  $method  The method that found the name
     * @param  bool  $echo  Whether to actually update the database
     * @param  string  $type  The type string for logging
     * @param  bool  $nameStatus  Whether to update status columns
     * @param  bool  $show  Whether to show output
     * @param  int|null  $preId  PreDB ID if matched
     * @param  ReleaseIdentity|null  $identity  Row values the decision was based on; defaults to $release
     * @param  string|null  $canonicalTitle  PreDB title the match was made against, re-checked in the transaction
     * @param  (Closure(object): bool)|null  $ownershipCheck  Runs on the locked row; false refuses the write
     *
     * The outcome of the write is left in {@see self::$lastResult}.
     *
     * @throws \Exception
     */
    public function updateRelease(
        object|array $release,
        string $name,
        string $method,
        bool $echo,
        string $type,
        bool $nameStatus,
        bool $show,
        ?int $preId = 0,
        ?ReleaseIdentity $identity = null,
        ?string $canonicalTitle = null,
        ?Closure $ownershipCheck = null,
    ): void {
        $this->lastResult = null;
        if (in_array($type, ['CRC32, ', 'CRC/OSO, ', 'CRC/Size, '], true)) {
            $this->applyCrcMatch($release, $name, $preId ?? 0, $echo, $show);

            return;
        }

        $preId = $preId ?? 0;
        $identity ??= ReleaseIdentity::fromRelease($release);
        if (is_array($release)) {
            $release = (object) $release;
        }

        // If $release does not have a releases_id, we should add it.
        if (! isset($release->releases_id)) {
            $release->releases_id = $release->id;
        }

        if ($this->relid !== $release->releases_id) {
            $cleanedName = (new ReleaseCleaningService)->fixerCleaner($name);
            // Normalize and sanity-check candidate for non-trusted sources
            $normalizedName = $this->fileNameCleaner->normalizeCandidateTitle($cleanedName);
            $newName = $this->fileNameCleaner->formatSearchName($cleanedName, $normalizedName);

            // Determine if the source is trusted enough to bypass plausibility checks
            $trustedSource = $this->isTrustedSource($type, $method, $preId);

            if (! $trustedSource && ! $this->fileNameCleaner->isPlausibleReleaseTitle($normalizedName)) {
                // Skip low-quality rename candidates for untrusted sources
                $this->done = true;

                return;
            }

            if (strtolower($newName) !== strtolower($release->searchname)) {
                if ($type === 'PAR2, ') {
                    $newName = ucwords($newName);
                    if (preg_match('/(.+?)\.[a-z0-9]{2,3}(PAR2)?$/i', $name, $hit)) {
                        $newName = $hit[1];
                    }
                }

                // Split on path separator backslash to strip any path
                $newName = explode('\\', $newName);
                $newTitle = (string) preg_replace(['/^[=_.:\s-]+/', '/[=_.:\s-]+$/'], '', $newName[0]);

                // releases.searchname is varchar(255); a longer name would be silently truncated
                // by a non-strict MySQL session, so it is rejected rather than stored cut short.
                if (mb_strlen($newTitle) > self::MAX_SEARCHNAME_LENGTH) {
                    $this->lastResult = RenameApplyResult::TooLong;
                    $this->done = true;

                    return;
                }

                if ($this->renamesSeasonPackToEpisode((string) $release->searchname, $newTitle)) {
                    $this->lastResult = RenameApplyResult::SeasonPackEpisode;
                    $this->done = true;

                    return;
                }

                $this->relid = (int) $release->releases_id;
                if ($echo !== true) {
                    $this->matched = true;
                    $this->fixed++;
                }

                $determinedCategory = null;
                if ($this->echoOutput && $show) {
                    $determinedCategory = $this->category->determineCategory(
                        $release->groups_id,
                        $newTitle,
                        ! empty($release->fromname) ? $release->fromname : ''
                    );

                    $this->echoReleaseInfo($release, $newTitle, $determinedCategory, $type, $method);
                }

                if ($echo === true) {
                    $this->lastResult = $this->performDatabaseUpdate(
                        $release,
                        $newTitle,
                        $type,
                        $nameStatus,
                        $preId,
                        $identity,
                        $canonicalTitle,
                        $ownershipCheck,
                    );
                    if ($this->lastResult->written()) {
                        $this->matched = true;
                    }
                }
            }
        }
        $this->done = true;
    }

    /**
     * Apply a resolved CRC identity independently of console verbosity.
     *
     * @param  object|array<string, mixed>  $release
     */
    public function applyCrcMatch(
        object|array $release,
        string $name,
        int $preId,
        bool $persist = true,
        bool $show = false
    ): bool {
        $this->reset();
        $this->done = true;
        $release = is_array($release) ? (object) $release : $release;
        $releaseId = (int) ($release->releases_id ?? $release->id ?? 0);
        if ($releaseId <= 0 || $preId < 0 || ($persist && $preId === 0)
            || trim($name) === '' || preg_match('/[\\\\\/\x00-\x1f]/', $name)) {
            return false;
        }

        $cleanedName = (new ReleaseCleaningService)->fixerCleaner($name);
        $normalizedName = $this->fileNameCleaner->normalizeCandidateTitle($cleanedName);
        $newTitle = trim($this->fileNameCleaner->formatSearchName($cleanedName, $normalizedName), "=_.: \t\n\r\0\x0B-");
        if ($newTitle === '' || mb_strlen($newTitle) > self::MAX_SEARCHNAME_LENGTH) {
            $this->lastResult = $newTitle === '' ? null : RenameApplyResult::TooLong;

            return false;
        }
        if ($this->renamesSeasonPackToEpisode((string) ($release->searchname ?? ''), $newTitle)) {
            $this->lastResult = RenameApplyResult::SeasonPackEpisode;

            return false;
        }
        // The CRC path applies its own PreDB link rule below, so only the name identity is pinned.
        $captured = ReleaseIdentity::fromRelease($release);
        $identity = new ReleaseIdentity($captured->id, $captured->guid, $captured->name, $captured->searchname);

        if (! $persist) {
            if ((int) ($release->predb_id ?? 0) > 0 && $preId > 0 && (int) $release->predb_id !== $preId) {
                return false;
            }

            if (strcasecmp((string) ($release->searchname ?? ''), $newTitle) !== 0
                || ($preId > 0 && (int) ($release->predb_id ?? 0) !== $preId)) {
                $this->fixed++;
            }
            $this->matched = true;

            return true;
        }

        $changed = false;
        $renamed = false;
        $previous = null;
        $current = DB::transaction(function () use ($releaseId, $newTitle, $preId, $identity, &$changed, &$renamed, &$previous): ?object {
            $stored = DB::table('releases')->where('id', $releaseId)->lockForUpdate()->first();
            if ($stored === null || ((int) $stored->predb_id > 0 && $preId > 0 && (int) $stored->predb_id !== $preId)
                || ! $identity->matches($stored)) {
                return null;
            }
            $previous = clone $stored;
            $previous->releases_id = $releaseId;

            $renamed = strcasecmp((string) $stored->searchname, $newTitle) !== 0;
            $columns = $this->getStatusColumnsForType('CRC32, ');
            $columns['predb_id'] = $preId > 0 ? $preId : (int) $stored->predb_id;
            if ($renamed) {
                $columns = array_merge($columns, [
                    'searchname' => $newTitle,
                    ...TvProcessingCandidateQuery::resetColumns(),
                    'imdbid' => null,
                    'movieinfo_id' => null,
                    'musicinfo_id' => null,
                    'consoleinfo_id' => null,
                    'bookinfo_id' => null,
                    'anidbid' => null,
                ]);
            }

            $changed = $renamed;
            foreach ($columns as $column => $value) {
                if (($stored->{$column} ?? null) != $value) {
                    $changed = true;
                }
            }

            if ($changed) {
                Release::query()->where('id', $releaseId)->update($columns);
            }

            return DB::table('releases')->where('id', $releaseId)->first();
        });

        if ($current === null) {
            return false;
        }

        $this->synchronizeCrcRelease($release, $current);
        $this->relid = $releaseId;
        $this->matched = true;

        if ($changed && $previous !== null) {
            DB::afterCommit(function () use ($release, $releaseId, $newTitle, $previous, $renamed, $show): void {
                $this->fixed++;
                try {
                    if ($renamed) {
                        event(new ReleaseNameFixed(
                            $releaseId,
                            (string) $previous->searchname,
                            $newTitle,
                            (int) $previous->categories_id,
                            (int) $previous->groups_id,
                            (string) ($previous->fromname ?? '')
                        ));
                    }
                } finally {
                    $committed = DB::table('releases')->where('id', $releaseId)->first();
                    if ($committed !== null) {
                        $this->synchronizeCrcRelease($release, $committed);
                    }
                }
                $this->searchSyncCoordinator->request($releaseId);
                if ($this->echoOutput && $show) {
                    $this->echoReleaseInfo($previous, (string) $release->searchname, [
                        'categories_id' => (int) $release->categories_id,
                    ], 'CRC32, ', 'Verified CRC/OSO match');
                }
            });
        }

        return true;
    }

    private function synchronizeCrcRelease(object $release, object $current): void
    {
        foreach ((array) $current as $column => $value) {
            $release->{$column} = $value;
        }
        $release->releases_id = (int) $current->id;
        $release->preid = (int) $current->predb_id;
    }

    /**
     * Check if the source is trusted enough to bypass plausibility checks.
     */
    protected function isTrustedSource(string $type, string $method, int $preId): bool
    {
        return
            (! empty($preId) && $preId > 0) ||
            str_starts_with($type, 'PreDB') ||
            str_starts_with($type, 'PreDb') ||
            $type === 'UID, ' ||
            $type === 'PAR2 hash, ' ||
            $type === 'CRC32, ' ||
            $type === 'SRR, ' ||
            stripos($method, 'Title Match') !== false ||
            stripos($method, 'file matched source') !== false ||
            stripos($method, 'PreDb') !== false ||
            stripos($method, 'preDB') !== false;
    }

    /**
     * Echo release information to CLI.
     *
     * @param  array<string, mixed>  $determinedCategory
     */
    public function echoReleaseInfo(
        object $release,
        string $newName,
        array $determinedCategory,
        string $type,
        string $method
    ): void {
        $groupName = UsenetGroup::getNameByID($release->groups_id);
        $oldCatName = Category::getNameByID($release->categories_id);
        $newCatName = Category::getNameByID($determinedCategory['categories_id']);

        if ($type === 'PAR2, ') {
            echo PHP_EOL;
        }

        echo PHP_EOL;

        cli()->primary('Release Information:');

        echo '  '.cli()->headerOver('New name:   ').cli()->primary(substr($newName, 0, 100)).PHP_EOL;
        echo '  '.cli()->headerOver('Old name:   ').cli()->primary(substr((string) $release->searchname, 0, 100)).PHP_EOL;
        echo '  '.cli()->headerOver('Use name:   ').cli()->primary(substr((string) $release->name, 0, 100)).PHP_EOL;
        echo PHP_EOL;

        echo '  '.cli()->headerOver('New cat:    ').cli()->primary($newCatName).PHP_EOL;
        echo '  '.cli()->headerOver('Old cat:    ').cli()->primary($oldCatName).PHP_EOL;
        echo '  '.cli()->headerOver('Group:      ').cli()->primary($groupName).PHP_EOL;
        echo PHP_EOL;

        echo '  '.cli()->headerOver('Method:     ').cli()->primary($type.$method).PHP_EOL;
        echo '  '.cli()->headerOver('Release ID: ').cli()->primary((string) $release->releases_id).PHP_EOL;

        if (! empty($release->filename)) {
            echo '  '.cli()->headerOver('Filename:   ').cli()->primary(substr((string) $release->filename, 0, 100)).PHP_EOL;
        }

        if ($type !== 'PAR2, ') {
            echo PHP_EOL;
        }
    }

    /**
     * Perform the actual database update.
     */
    protected function performDatabaseUpdate(
        object $release,
        string $newTitle,
        string $type,
        bool $nameStatus,
        int $preId,
        ?ReleaseIdentity $identity = null,
        ?string $canonicalTitle = null,
        ?Closure $ownershipCheck = null,
    ): RenameApplyResult {
        $releaseId = (int) $release->releases_id;
        $identity ??= ReleaseIdentity::fromRelease($release);

        if ($nameStatus === true) {
            $updateColumns = [
                ...TvProcessingCandidateQuery::resetColumns(),
                'imdbid' => null,
                'movieinfo_id' => null,
                'musicinfo_id' => null,
                'consoleinfo_id' => null,
                'bookinfo_id' => null,
                'book_lookup_attempts' => 0,
                'book_lookup_retry_at' => null,
                'book_name_normalized_at' => null,
                'anidbid' => null,
                'predb_id' => $preId,
                'searchname' => $newTitle,
            ];

            foreach ($this->getStatusColumnsForType($type) as $key => $stat) {
                $updateColumns = Arr::add($updateColumns, $key, $stat);
            }
        } else {
            $updateColumns = [
                ...TvProcessingCandidateQuery::resetColumns(),
                'imdbid' => null,
                'movieinfo_id' => null,
                'musicinfo_id' => null,
                'consoleinfo_id' => null,
                'bookinfo_id' => null,
                'book_lookup_attempts' => 0,
                'book_lookup_retry_at' => null,
                'book_name_normalized_at' => null,
                'anidbid' => null,
                'predb_id' => $preId,
                'searchname' => $newTitle,
                'iscategorized' => 1,
            ];
        }

        [$result, $previous] = $this->guardedWrite($releaseId, $identity, $updateColumns, function (object $stored) use ($ownershipCheck, $preId, $canonicalTitle): ?RenameApplyResult {
            if ($ownershipCheck !== null && ! $ownershipCheck($stored)) {
                return RenameApplyResult::OwnershipLost;
            }

            return $preId > 0 ? $this->lockedPredbProblem($stored, $preId, $canonicalTitle) : null;
        });

        if ($result === RenameApplyResult::Applied && $previous !== null) {
            // Listeners and the search index must only ever see a committed rename.
            DB::afterCommit(function () use ($release, $releaseId, $newTitle, $previous, $updateColumns): void {
                $this->fixed++;
                $this->dispatchNameFixed($releaseId, $previous, $newTitle);
                $this->synchronizeRelease($release, $updateColumns, $releaseId);
                $this->searchSyncCoordinator->request($releaseId);
            });
        }

        return $result;
    }

    /**
     * Apply a name found outside the name-fixing checks (subject cleaners, name decoders, media
     * tags, book-name normalization) under the same guards as updateRelease(): the row is locked
     * and must still hold $identity byte for byte, the name must fit releases.searchname, and the
     * search index (and, with $recategorize, the rename listeners) only see a committed change.
     * The PreDB link is never changed here.
     *
     * @param  array<string, mixed>  $columns  Columns the caller writes with the name (status flags, category, resets)
     * @param  object|null  $release  Caller's copy of the row, updated with the committed values
     */
    public function applyRename(
        ReleaseIdentity $identity,
        string $newTitle,
        array $columns = [],
        bool $recategorize = false,
        ?object $release = null,
    ): RenameApplyResult {
        if (trim($newTitle) === '') {
            return $this->lastResult = RenameApplyResult::Unchanged;
        }
        if (mb_strlen($newTitle) > self::MAX_SEARCHNAME_LENGTH) {
            return $this->lastResult = RenameApplyResult::TooLong;
        }

        unset($columns['predb_id']);
        $columns = ['searchname' => $newTitle, ...$columns];
        [$result, $previous] = $this->guardedWrite($identity->id, $identity, $columns);

        if ($result === RenameApplyResult::Applied && $previous !== null) {
            DB::afterCommit(function () use ($identity, $newTitle, $previous, $columns, $recategorize, $release): void {
                if ($recategorize) {
                    $this->dispatchNameFixed($identity->id, $previous, $newTitle);
                }
                if ($release !== null) {
                    $this->synchronizeRelease($release, $columns, $identity->id);
                }
                $this->searchSyncCoordinator->request($identity->id);
            });
        }

        return $this->lastResult = $result;
    }

    /**
     * Lock the row, check it still has $identity (and $precondition), and write $columns if they change anything.
     *
     * @param  array<string, mixed>  $columns
     * @param  (Closure(object): ?RenameApplyResult)|null  $precondition  Returns a refusal, or null to write
     * @return array{0: RenameApplyResult, 1: object|null} The outcome and the row as it was before the write
     */
    private function guardedWrite(int $releaseId, ReleaseIdentity $identity, array $columns, ?Closure $precondition = null): array
    {
        $previous = null;
        $result = DB::transaction(function () use ($releaseId, $identity, $columns, $precondition, &$previous): RenameApplyResult {
            $stored = DB::table('releases')->where('id', $releaseId)->lockForUpdate()->first();
            if ($stored === null) {
                return RenameApplyResult::Missing;
            }
            if (! $identity->matches($stored)) {
                return RenameApplyResult::StaleIdentity;
            }
            if ($precondition !== null && ($refusal = $precondition($stored)) !== null) {
                return $refusal;
            }
            if (! $this->columnsChange($stored, $columns)) {
                return RenameApplyResult::Unchanged;
            }

            Release::query()->where('id', $releaseId)->update($columns);
            $previous = $stored;

            return RenameApplyResult::Applied;
        });

        return [$result, $previous];
    }

    private function dispatchNameFixed(int $releaseId, object $previous, string $newTitle): void
    {
        if ((string) $previous->searchname !== $newTitle) {
            event(new ReleaseNameFixed(
                $releaseId,
                (string) $previous->searchname,
                $newTitle,
                (int) $previous->categories_id,
                $previous->groups_id,
                (string) ($previous->fromname ?? '')
            ));
        }
    }

    /**
     * Re-read the PreDB entry inside the release transaction so a stale joined match is never applied.
     */
    private function lockedPredbProblem(object $stored, int $preId, ?string $canonicalTitle): ?RenameApplyResult
    {
        $linked = (int) ($stored->predb_id ?? 0);
        if ($linked > 0 && $linked !== $preId) {
            return RenameApplyResult::PredbConflict;
        }

        $predb = DB::table('predb')->where('id', $preId)->sharedLock()->first(['id', 'title']);
        if ($predb === null || ($canonicalTitle !== null && (string) $predb->title !== $canonicalTitle)) {
            return RenameApplyResult::PredbInvalid;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $columns
     */
    private function columnsChange(object $stored, array $columns): bool
    {
        foreach ($columns as $column => $value) {
            $current = $stored->{$column} ?? null;
            if ($column === 'searchname' ? (string) $current !== (string) $value : $current != $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Copy the committed values onto the caller's release so later steps of the same run
     * compare against what is now stored instead of rejecting their own earlier rename.
     *
     * @param  array<string, mixed>  $columns
     */
    private function synchronizeRelease(object $release, array $columns, int $releaseId): void
    {
        foreach ($columns as $column => $value) {
            $release->{$column} = $value;
        }

        $current = DB::table('releases')->where('id', $releaseId)->first(['categories_id', 'iscategorized']);
        if ($current !== null) {
            $release->categories_id = $current->categories_id;
            $release->iscategorized = $current->iscategorized;
        }
    }

    /**
     * Get the status columns to update for a given type.
     *
     * @return array<string, mixed>
     */
    protected function getStatusColumnsForType(string $type): array
    {
        return match ($type) {
            'NFO, ' => ['isrenamed' => 1, 'iscategorized' => 1, 'proc_nfo' => 1],
            'PAR2, ' => ['isrenamed' => 1, 'iscategorized' => 1, 'proc_par2' => 1],
            'Filenames, ', 'file matched source: ' => ['isrenamed' => 1, 'iscategorized' => 1, 'proc_files' => 1],
            'PreDB FT Exact, ' => ['isrenamed' => 1, 'iscategorized' => 1],
            'sorter, ' => ['isrenamed' => 1, 'iscategorized' => 1],
            'UID, ', 'Mediainfo, ' => ['isrenamed' => 1, 'iscategorized' => 1, 'proc_uid' => 1],
            'PAR2 hash, ' => ['isrenamed' => 1, 'iscategorized' => 1, 'proc_hash16k' => 1],
            'SRR, ' => ['isrenamed' => 1, 'iscategorized' => 1, 'proc_srr' => 1],
            'CRC32, ', 'CRC/OSO, ', 'CRC/Size, ' => ['isrenamed' => 1, 'iscategorized' => 1, 'proc_crc32' => 1],
            default => [],
        };
    }

    /**
     * Update a single column in releases.
     */
    public function updateSingleColumn(string $column, int $status, int $id): void
    {
        if (! in_array($column, self::SINGLE_UPDATE_COLUMNS, true)) {
            throw new InvalidArgumentException("Unsupported release status column [{$column}].");
        }

        if ($id !== 0) {
            DB::update("UPDATE releases SET {$column} = ? WHERE id = ?", [$status, $id]);
        }
    }

    /**
     * Link a release whose name already equals a PreDB title to that entry.
     *
     * The link is only written while the release still has the identity the match was made
     * against, is not linked elsewhere, and the PreDB entry still carries the expected title.
     * The outcome is left in {@see self::$lastResult}.
     */
    public function attachPredbId(
        int $releaseId,
        int $predbId,
        ?ReleaseIdentity $identity = null,
        ?string $expectedTitle = null,
    ): void {
        if ($releaseId === 0 || $predbId === 0) {
            $this->lastResult = RenameApplyResult::Missing;

            return;
        }

        $result = DB::transaction(function () use ($releaseId, $predbId, $identity, $expectedTitle): RenameApplyResult {
            $stored = DB::table('releases')->where('id', $releaseId)->lockForUpdate()->first();
            if ($stored === null) {
                return RenameApplyResult::Missing;
            }
            if ((int) $stored->predb_id === $predbId) {
                return RenameApplyResult::Unchanged;
            }
            if ($identity !== null && ! $identity->matches($stored)) {
                return RenameApplyResult::StaleIdentity;
            }
            if (($problem = $this->lockedPredbProblem($stored, $predbId, $expectedTitle)) !== null) {
                return $problem;
            }

            DB::table('releases')->where('id', $releaseId)->update(['predb_id' => $predbId]);

            return RenameApplyResult::Applied;
        });

        $this->lastResult = $result;
        $this->done = true;
        if ($result === RenameApplyResult::Applied || $result === RenameApplyResult::Unchanged) {
            $this->relid = $releaseId;
            $this->matched = true;
        }
    }

    /**
     * Check if a release matches a PreDB entry.
     *
     * @return array<string, mixed>
     */
    public function checkPreDbMatch(object $release, string $textstring): ?array
    {
        if (! preg_match_all(self::PREDB_REGEX, $textstring, $hits) || preg_match('/Source\s:/i', $textstring)) {
            return null;
        }

        $candidates = [];
        foreach ($hits as $hit) {
            foreach ($hit as $value) {
                $title = trim($value);
                if ($title !== '') {
                    $candidates[$title] = $title;
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        $candidateValues = array_values($candidates);
        $rows = DB::select(
            'SELECT id, title FROM predb WHERE title IN ('.implode(',', array_fill(0, count($candidateValues), '?')).')',
            $candidateValues
        );
        $matches = [];
        foreach ($rows as $row) {
            $matches[(string) $row->title] = ['title' => (string) $row->title, 'id' => (int) $row->id];
        }

        foreach ($candidateValues as $candidate) {
            if (isset($matches[$candidate])) {
                return $matches[$candidate];
            }
        }

        return null;
    }

    /**
     * A release named as a whole season is never renamed to a single episode of it.
     */
    private function renamesSeasonPackToEpisode(string $currentName, string $newTitle): bool
    {
        return SeasonPackDetector::isSeasonPack($currentName)
            && SeasonPackDetector::isEpisodeName($newTitle)
            && ! SeasonPackDetector::isSeasonPack($newTitle);
    }

    /**
     * Reset status variables for new processing.
     */
    public function reset(): void
    {
        $this->done = $this->matched = false;
    }

    /**
     * Increment the checked counter.
     */
    public function incrementChecked(): void
    {
        $this->checked++;
    }

    /**
     * Get the current statistics.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return [
            'fixed' => $this->fixed,
            'checked' => $this->checked,
            'matched' => $this->matched,
            'done' => $this->done,
        ];
    }
}
