<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

use App\Models\Category;
use App\Models\Predb;
use App\Models\Release;
use App\Models\UsenetGroup;
use App\Services\Categorization\CategorizationService;
use App\Services\ReleaseCleaningService;
use App\Services\Search\ElasticSearchService;
use App\Services\Search\ManticoreSearchService;
use Blacklight\ColorCLI;
use Illuminate\Support\Arr;

/**
 * Service for updating releases with new names.
 *
 * Handles the actual database updates and category re-determination
 * when a release is renamed.
 */
class ReleaseUpdateService
{
    /**
     * PreDB regex pattern for scene release names.
     */
    public const PREDB_REGEX = '/([\w.\'()\[\]-]+(?:[\s._-]+[\w.\'()\[\]-]+)+[-.][\w]+)/ui';

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
    protected ManticoreSearchService $manticore;
    protected ElasticSearchService $elasticsearch;
    protected FileNameCleaner $fileNameCleaner;
    protected ColorCLI $colorCLI;
    protected bool $echoOutput;

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

    public function __construct(
        ?CategorizationService $category = null,
        ?ManticoreSearchService $manticore = null,
        ?ElasticSearchService $elasticsearch = null,
        ?FileNameCleaner $fileNameCleaner = null,
        ?ColorCLI $colorCLI = null
    ) {
        $this->category = $category ?? new CategorizationService();
        $this->manticore = $manticore ?? app(ManticoreSearchService::class);
        $this->elasticsearch = $elasticsearch ?? app(ElasticSearchService::class);
        $this->fileNameCleaner = $fileNameCleaner ?? new FileNameCleaner();
        $this->colorCLI = $colorCLI ?? new ColorCLI();
        $this->echoOutput = config('nntmux.echocli');
    }

    /**
     * Update the release with the new information.
     *
     * @param object|array $release The release to update
     * @param string $name The new name
     * @param string $method The method that found the name
     * @param bool $echo Whether to actually update the database
     * @param string $type The type string for logging
     * @param bool $nameStatus Whether to update status columns
     * @param bool $show Whether to show output
     * @param int|null $preId PreDB ID if matched
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
        ?int $preId = 0
    ): void {
        $preId = $preId ?? 0;
        if (is_array($release)) {
            $release = (object) $release;
        }

        // If $release does not have a releases_id, we should add it.
        if (!isset($release->releases_id)) {
            $release->releases_id = $release->id;
        }

        if ($this->relid !== $release->releases_id) {
            $newName = (new ReleaseCleaningService())->fixerCleaner($name);
            // Normalize and sanity-check candidate for non-trusted sources
            $newName = $this->fileNameCleaner->normalizeCandidateTitle($newName);

            // Determine if the source is trusted enough to bypass plausibility checks
            $trustedSource = $this->isTrustedSource($type, $method, $preId);

            if (!$trustedSource && !$this->fileNameCleaner->isPlausibleReleaseTitle($newName)) {
                // Skip low-quality rename candidates for untrusted sources
                $this->done = true;
                return;
            }

            if (strtolower($newName) !== strtolower($release->searchname)) {
                $this->matched = true;
                $this->relid = (int) $release->releases_id;

                $determinedCategory = $this->category->determineCategory(
                    $release->groups_id,
                    $newName,
                    !empty($release->fromname) ? $release->fromname : ''
                );

                if ($type === 'PAR2, ') {
                    $newName = ucwords($newName);
                    if (preg_match('/(.+?)\.[a-z0-9]{2,3}(PAR2)?$/i', $name, $hit)) {
                        $newName = $hit[1];
                    }
                }

                $this->fixed++;

                // Split on path separator backslash to strip any path
                $newName = explode('\\', $newName);
                $newName = preg_replace(['/^[=_.:\s-]+/', '/[=_.:\s-]+$/'], '', $newName[0]);

                if ($this->echoOutput && $show) {
                    $this->echoReleaseInfo($release, $newName, $determinedCategory, $type, $method);
                }

                $newTitle = substr($newName, 0, 299);

                if ($echo === true) {
                    $this->performDatabaseUpdate($release, $newTitle, $determinedCategory, $type, $nameStatus, $preId);
                }
            }
        }
        $this->done = true;
    }

    /**
     * Check if the source is trusted enough to bypass plausibility checks.
     */
    protected function isTrustedSource(string $type, string $method, int $preId): bool
    {
        return (
            (!empty($preId) && $preId > 0) ||
            str_starts_with($type, 'PreDB') ||
            str_starts_with($type, 'PreDb') ||
            $type === 'UID, ' ||
            $type === 'PAR2 hash, ' ||
            $type === 'CRC32, ' ||
            $type === 'SRR, ' ||
            stripos($method, 'Title Match') !== false ||
            stripos($method, 'file matched source') !== false ||
            stripos($method, 'PreDb') !== false ||
            stripos($method, 'preDB') !== false
        );
    }

    /**
     * Echo release information to CLI.
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

        $this->colorCLI->primary('Release Information:');

        echo '  ' . $this->colorCLI->headerOver('New name:   ') . $this->colorCLI->primary(substr($newName, 0, 100)) . PHP_EOL;
        echo '  ' . $this->colorCLI->headerOver('Old name:   ') . $this->colorCLI->primary(substr((string) $release->searchname, 0, 100)) . PHP_EOL;
        echo '  ' . $this->colorCLI->headerOver('Use name:   ') . $this->colorCLI->primary(substr((string) $release->name, 0, 100)) . PHP_EOL;
        echo PHP_EOL;

        echo '  ' . $this->colorCLI->headerOver('New cat:    ') . $this->colorCLI->primary($newCatName) . PHP_EOL;
        echo '  ' . $this->colorCLI->headerOver('Old cat:    ') . $this->colorCLI->primary($oldCatName) . PHP_EOL;
        echo '  ' . $this->colorCLI->headerOver('Group:      ') . $this->colorCLI->primary($groupName) . PHP_EOL;
        echo PHP_EOL;

        echo '  ' . $this->colorCLI->headerOver('Method:     ') . $this->colorCLI->primary($type . $method) . PHP_EOL;
        echo '  ' . $this->colorCLI->headerOver('Release ID: ') . $this->colorCLI->primary((string) $release->releases_id) . PHP_EOL;

        if (!empty($release->filename)) {
            echo '  ' . $this->colorCLI->headerOver('Filename:   ') . $this->colorCLI->primary(substr((string) $release->filename, 0, 100)) . PHP_EOL;
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
        array $determinedCategory,
        string $type,
        bool $nameStatus,
        int $preId
    ): void {
        if ($nameStatus === true) {
            $status = $this->getStatusColumnsForType($type);

            $updateColumns = [
                'videos_id' => 0,
                'tv_episodes_id' => 0,
                'imdbid' => '',
                'musicinfo_id' => '',
                'consoleinfo_id' => '',
                'bookinfo_id' => '',
                'anidbid' => '',
                'predb_id' => $preId,
                'searchname' => $newTitle,
                'categories_id' => $determinedCategory['categories_id'],
            ];

            if (!empty($status)) {
                foreach ($status as $key => $stat) {
                    $updateColumns = Arr::add($updateColumns, $key, $stat);
                }
            }

            Release::query()
                ->where('id', $release->releases_id)
                ->update($updateColumns);
        } else {
            Release::query()
                ->where('id', $release->releases_id)
                ->update([
                    'videos_id' => 0,
                    'tv_episodes_id' => 0,
                    'imdbid' => null,
                    'musicinfo_id' => null,
                    'consoleinfo_id' => null,
                    'bookinfo_id' => null,
                    'anidbid' => null,
                    'predb_id' => $preId,
                    'searchname' => $newTitle,
                    'categories_id' => $determinedCategory['categories_id'],
                    'iscategorized' => 1,
                ]);
        }

        // Update search index
        if (config('nntmux.elasticsearch_enabled') === true) {
            $this->elasticsearch->updateRelease($release->releases_id);
        } else {
            $this->manticore->updateRelease($release->releases_id);
        }
    }

    /**
     * Get the status columns to update for a given type.
     */
    protected function getStatusColumnsForType(string $type): array
    {
        return match ($type) {
            'NFO, ' => ['isrenamed' => 1, 'iscategorized' => 1, 'proc_nfo' => 1],
            'PAR2, ' => ['isrenamed' => 1, 'iscategorized' => 1, 'proc_par2' => 1],
            'Filenames, ', 'file matched source: ' => ['isrenamed' => 1, 'iscategorized' => 1, 'proc_files' => 1],
            'SHA1, ', 'MD5, ' => ['isrenamed' => 1, 'iscategorized' => 1, 'dehashstatus' => 1],
            'PreDB FT Exact, ' => ['isrenamed' => 1, 'iscategorized' => 1],
            'sorter, ' => ['isrenamed' => 1, 'iscategorized' => 1, 'proc_sorter' => 1],
            'UID, ', 'Mediainfo, ' => ['isrenamed' => 1, 'iscategorized' => 1, 'proc_uid' => 1],
            'PAR2 hash, ' => ['isrenamed' => 1, 'iscategorized' => 1, 'proc_hash16k' => 1],
            'SRR, ' => ['isrenamed' => 1, 'iscategorized' => 1, 'proc_srr' => 1],
            'CRC32, ' => ['isrenamed' => 1, 'iscategorized' => 1, 'proc_crc32' => 1],
            default => [],
        };
    }

    /**
     * Update a single column in releases.
     */
    public function updateSingleColumn(string $column, int $status, int $id): void
    {
        if ($column !== '' && $id !== 0) {
            Release::query()->where('id', $id)->update([$column => $status]);
        }
    }

    /**
     * Check if a release matches a PreDB entry.
     */
    public function checkPreDbMatch(object $release, string $textstring): ?array
    {
        if (preg_match_all(self::PREDB_REGEX, $textstring, $hits) && !preg_match('/Source\s\:/i', $textstring)) {
            foreach ($hits as $hit) {
                foreach ($hit as $val) {
                    $title = Predb::query()->where('title', trim($val))->select(['title', 'id'])->first();
                    if ($title !== null) {
                        return ['title' => $title['title'], 'id' => $title['id']];
                    }
                }
            }
        }
        return null;
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

