<?php

namespace Blacklight\processing;

use App\Models\Category;
use App\Models\Collection;
use App\Models\MusicInfo;
use App\Models\Release;
use App\Models\Settings;
use App\Models\UsenetGroup;
use App\Services\Categorization\CategorizationService;
use App\Services\CollectionCleanupService;
use App\Services\ReleaseCreationService;
use Blacklight\ColorCLI;
use Blacklight\Genres;
use Blacklight\NNTP;
use Blacklight\NZB;
use Blacklight\ReleaseCleaning;
use Blacklight\ReleaseImage;
use Blacklight\Releases;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessReleases
{
    public const COLLFC_DEFAULT = 0; // Collection has default filecheck status

    public const COLLFC_COMPCOLL = 1; // Collection is a complete collection

    public const COLLFC_COMPPART = 2; // Collection is a complete collection and has all parts available

    public const COLLFC_SIZED = 3; // Collection has been calculated for total size

    public const COLLFC_INSERTED = 4; // Collection has been inserted into releases

    public const COLLFC_DELETE = 5; // Collection is ready for deletion

    public const COLLFC_TEMPCOMP = 15; // Collection is complete and being checked for complete parts

    public const COLLFC_ZEROPART = 16; // Collection has a 00/0XX designator (temporary)

    public const FILE_INCOMPLETE = 0; // We don't have all the parts yet for the file (binaries table partcheck column).

    public const FILE_COMPLETE = 1; // We have all the parts for the file (binaries table partcheck column).

    public int $collectionDelayTime;

    public int $crossPostTime;

    public int $releaseCreationLimit;

    public int $completion;

    public bool $echoCLI;

    public \PDO $pdo;

    public ColorCLI $colorCLI;

    public NZB $nzb;

    public ReleaseCleaning $releaseCleaning;

    public Releases $releases;

    public ReleaseImage $releaseImage;

    // New services for better separation of concerns
    private ReleaseCreationService $releaseCreationService;

    private CollectionCleanupService $collectionCleanupService;

    /**
     * Time (hours) to wait before delete a stuck/broken collection.
     */
    private int $collectionTimeout;

    public function __construct()
    {
        $this->echoCLI = config('nntmux.echocli');

        $this->colorCLI = new ColorCLI;
        $this->nzb = new NZB;
        $this->releaseCleaning = new ReleaseCleaning;
        $this->releases = new Releases;
        $this->releaseImage = new ReleaseImage;

        // Initialize services
        $this->releaseCreationService = new ReleaseCreationService($this->colorCLI, $this->releaseCleaning);
        $this->collectionCleanupService = new CollectionCleanupService($this->colorCLI);

        $dummy = Settings::settingValue('delaytime');
        $this->collectionDelayTime = ($dummy !== '' ? (int) $dummy : 2);
        $dummy = Settings::settingValue('crossposttime');
        $this->crossPostTime = ($dummy !== '' ? (int) $dummy : 2);
        $dummy = Settings::settingValue('maxnzbsprocessed');
        $this->releaseCreationLimit = ($dummy !== '' ? (int) $dummy : 1000);
        $dummy = Settings::settingValue('completionpercent');
        $this->completion = ($dummy !== '' ? (int) $dummy : 0);
        if ($this->completion > 100) {
            $this->completion = 100;
            $this->colorCLI->error(PHP_EOL.'You have an invalid setting for completion. It cannot be higher than 100.');
        }
        $this->collectionTimeout = (int) Settings::settingValue('collection_timeout');
    }

    /**
     * Main method for creating releases/NZB files from collections.
     *
     * @param  string  $groupName  (optional)
     *
     * @throws \Throwable
     */
    public function processReleases(int $categorize, int $postProcess, string $groupName, NNTP $nntp): int
    {
        $this->echoCLI = config('nntmux.echocli');
        $groupID = '';

        if (! empty($groupName)) {
            $groupInfo = UsenetGroup::getByName($groupName);
            if ($groupInfo !== null) {
                $groupID = $groupInfo['id'];
            }
        }

        if ($this->echoCLI) {
            $this->colorCLI->header('Starting release update process ('.now()->format('Y-m-d H:i:s').')');
        }

        if (! file_exists(config('nntmux_settings.path_to_nzbs'))) {
            if ($this->echoCLI) {
                $this->colorCLI->error('Bad or missing nzb directory - '.config('nntmux_settings.path_to_nzbs'));
            }

            return 0;
        }

        // Normalize group ID for internal usage
        $gid = $this->normalizeGroupId($groupID);

        $this->processIncompleteCollections($gid);
        $this->processCollectionSizes($gid);
        $this->deleteUnwantedCollections($gid);

        $totalReleasesAdded = 0;
        do {
            $releasesCount = $this->createReleases($gid);
            $totalReleasesAdded += $releasesCount['added'];

            $nzbFilesAdded = $this->createNZBs($gid);

            $this->categorizeReleases($categorize, $gid);
            $this->postProcessReleases($postProcess, $nntp);
            $this->deleteCollections($gid);

            // This loops as long as the number of releases or nzbs added was >= the limit (meaning there are more waiting to be created)
        } while (
            ($releasesCount['added'] + $releasesCount['dupes']) >= $this->releaseCreationLimit
            || $nzbFilesAdded >= $this->releaseCreationLimit
        );

        $this->deleteReleases();

        return $totalReleasesAdded;
    }

    /**
     * Return all releases to other->misc category.
     *
     * @param  string  $where  Optional "where" query parameter.
     *
     * @void
     */
    public function resetCategorize(string $where = ''): void
    {
        if ($where !== '') {
            DB::update('UPDATE releases SET categories_id = ?, iscategorized = 0 '.$where, [Category::OTHER_MISC]);
        } else {
            Release::query()->update(['categories_id' => Category::OTHER_MISC, 'iscategorized' => 0]);
        }
    }

    /**
     * Categorizes releases.
     *
     * @param  string  $type  name or searchname | Categorize using the search name or subject.
     * @return int Quantity of categorized releases.
     *
     * @throws \Exception
     */
    public function categorizeRelease(string $type, $groupId): int
    {
        $cat = new CategorizationService();
        $categorized = $total = 0;
        $releasesQuery = Release::query()->where(['categories_id' => Category::OTHER_MISC, 'iscategorized' => 0]);
        if (! empty($groupId)) {
            $releasesQuery->where('groups_id', $groupId);
        }
        $releases = $releasesQuery->select(['id', 'fromname', 'groups_id', $type])->get();
        if ($releases->count() > 0) {
            $total = \count($releases);
            foreach ($releases as $release) {
                $catId = $cat->determineCategory($release->groups_id, $release->{$type}, $release->fromname);
                Release::query()->where('id', $release->id)->update(['categories_id' => $catId['categories_id'], 'iscategorized' => 1]);
                $categorized++;
                if ($this->echoCLI) {
                    $this->colorCLI->overWritePrimary(
                        'Categorizing: '.$this->colorCLI->percentString($categorized, $total)
                    );
                }
            }
        }
        if ($this->echoCLI && $categorized > 0) {
            echo PHP_EOL;
        }

        return $categorized;
    }

    /**
     * @throws \Exception
     * @throws \Throwable
     */
    public function processIncompleteCollections($groupID): void
    {
        $startTime = now()->toImmutable();

        if ($this->echoCLI) {
            $this->colorCLI->header('Process Releases -> Attempting to find complete collections.');
        }

        $gid = $this->normalizeGroupId($groupID);
        $where = $this->groupWhereSql($gid, 'c');

        $this->processStuckCollections($gid ?? 0);
        $this->collectionFileCheckStage1($gid ?? 0);
        $this->collectionFileCheckStage2($gid ?? 0);
        $this->collectionFileCheckStage3($where);
        $this->collectionFileCheckStage4($where);
        $this->collectionFileCheckStage5($gid ?? 0);
        $this->collectionFileCheckStage6($where);

        if ($this->echoCLI) {
            $countQuery = Collection::query()->where('filecheck', self::COLLFC_COMPPART);

            if (! empty($gid)) {
                $countQuery->where('groups_id', $gid);
            }
            $count = $countQuery->count('id');

            $totalTime = now()->diffInSeconds($startTime, true);

            $this->colorCLI->primary(
                ($count ?? 0).' collections were found to be complete. Time: '.
                $totalTime.Str::plural(' second', $totalTime),
                true
            );
        }
    }

    /**
     * @throws \Exception
     * @throws \Throwable
     */
    public function processCollectionSizes($groupID): void
    {
        $startTime = now()->toImmutable();

        if ($this->echoCLI) {
            $this->colorCLI->header('Process Releases -> Calculating collection sizes (in bytes).');
        }
        // Get the total size in bytes of the collection for collections where filecheck = 2.
        DB::transaction(function () use ($groupID, $startTime) {
            $gid = $this->normalizeGroupId($groupID);
            $where = $gid !== null ? ' AND c.groups_id = '.$gid.' ' : ' ';
            $sql = '
                UPDATE collections c
                SET c.filesize =
                (
                    SELECT COALESCE(SUM(b.partsize), 0)
                    FROM binaries b
                    WHERE b.collections_id = c.id
                ),
                c.filecheck = ?
                WHERE c.filecheck = ?
                AND c.filesize = 0'.$where;
            $checked = DB::update($sql, [self::COLLFC_SIZED, self::COLLFC_COMPPART]);
            if ($checked > 0 && $this->echoCLI) {
                $this->colorCLI->primary(
                    $checked.' collections set to filecheck = 3(size calculated)',
                    true
                );
                $totalTime = now()->diffInSeconds($startTime, true);
                $this->colorCLI->primary($totalTime.Str::plural(' second', $totalTime), true);
            }
        }, 10);
    }

    /**
     * @throws \Exception
     * @throws \Throwable
     */
    public function deleteUnwantedCollections($groupID): void
    {
        $startTime = now()->toImmutable();

        if ($this->echoCLI) {
            $this->colorCLI->header('Process Releases -> Delete collections smaller/larger than minimum size/file count from group/site setting.');
        }

        $gid = $this->normalizeGroupId($groupID);
        $gid === null ? $groupIDs = UsenetGroup::getActiveIDs() : $groupIDs = [['id' => $gid]];

        $minSizeDeleted = $maxSizeDeleted = $minFilesDeleted = 0;

        $maxSizeSetting = Settings::settingValue('maxsizetoformrelease');
        $minSizeSetting = Settings::settingValue('minsizetoformrelease');
        $minFilesSetting = Settings::settingValue('minfilestoformrelease');

        foreach ($groupIDs as $grpID) {
            $groupMinSizeSetting = $groupMinFilesSetting = 0;

            $groupMinimums = UsenetGroup::getGroupByID($grpID['id']);
            if ($groupMinimums !== null) {
                if (! empty($groupMinimums['minsizetoformrelease']) && $groupMinimums['minsizetoformrelease'] > 0) {
                    $groupMinSizeSetting = (int) $groupMinimums['minsizetoformrelease'];
                }
                if (! empty($groupMinimums['minfilestoformrelease']) && $groupMinimums['minfilestoformrelease'] > 0) {
                    $groupMinFilesSetting = (int) $groupMinimums['minfilestoformrelease'];
                }
            }

            if (Collection::query()->where('filecheck', self::COLLFC_SIZED)->where('filesize', '>', 0)->first() !== null) {
                DB::transaction(function () use (
                    $groupMinSizeSetting,
                    $minSizeSetting,
                    &$minSizeDeleted,
                    $maxSizeSetting,
                    &$maxSizeDeleted,
                    $minFilesSetting,
                    $groupMinFilesSetting,
                    &$minFilesDeleted,
                    $startTime
                ) {
                    $deleteQuery = Collection::query()
                        ->where('filecheck', self::COLLFC_SIZED)
                        ->where('filesize', '>', 0)
                        ->whereRaw('GREATEST(?, ?) > 0 AND filesize < GREATEST(?, ?)', [$groupMinSizeSetting, $minSizeSetting, $groupMinSizeSetting, $minSizeSetting])
                        ->delete();

                    if ($deleteQuery > 0) {
                        $minSizeDeleted += $deleteQuery;
                    }

                    if ($maxSizeSetting > 0) {
                        $deleteQuery = Collection::query()
                            ->where('filecheck', '=', self::COLLFC_SIZED)
                            ->where('filesize', '>', $maxSizeSetting)
                            ->delete();

                        if ($deleteQuery > 0) {
                            $maxSizeDeleted += $deleteQuery;
                        }
                    }

                    if ($minFilesSetting > 0 || $groupMinFilesSetting > 0) {
                        $deleteQuery = Collection::query()
                            ->where('filecheck', self::COLLFC_SIZED)
                            ->where('filesize', '>', 0)
                            ->whereRaw('GREATEST(?, ?) > 0 AND totalfiles < GREATEST(?, ?)', [$groupMinFilesSetting, $minFilesSetting, $groupMinFilesSetting, $minFilesSetting])
                            ->delete();

                        if ($deleteQuery > 0) {
                            $minFilesDeleted += $deleteQuery;
                        }
                    }

                    $totalTime = now()->diffInSeconds($startTime, true);

                    if ($this->echoCLI) {
                        $this->colorCLI->primary('Deleted '.($minSizeDeleted + $maxSizeDeleted + $minFilesDeleted).' collections: '.PHP_EOL.$minSizeDeleted.' smaller than, '.$maxSizeDeleted.' bigger than, '.$minFilesDeleted.' with less files than site/group settings in: '.$totalTime.Str::plural(' second', $totalTime), true);
                    }
                }, 10);
            }
        }
    }

    /**
     * @param  int|string  $groupID  (optional)
     *
     * @throws \Throwable
     */
    public function createReleases(int|string $groupID): array
    {
        // Delegate to service
        return $this->releaseCreationService->createReleases($groupID, $this->releaseCreationLimit, $this->echoCLI);
    }

    /**
     * Create NZB files from complete releases.
     *
     * @param  int|string  $groupID  (optional)
     *
     * @throws \Throwable
     */
    public function createNZBs(int|string $groupID): int
    {
        $startTime = now()->toImmutable();

        if ($this->echoCLI) {
            $this->colorCLI->header('Process Releases -> Create the NZB, delete collections/binaries/parts.');
        }

        $releasesQuery = Release::query()->with('category.parent')->where('nzbstatus', '=', NZB::NZB_NONE);
        if (! empty($groupID)) {
            $releasesQuery->where('releases.groups_id', $groupID);
        }
        $releases = $releasesQuery->select(['id', 'guid', 'name', 'categories_id'])->get();

        $nzbCount = 0;

        if ($releases->count() > 0) {
            $total = $releases->count();
            foreach ($releases as $release) {
                if ($this->nzb->writeNzbForReleaseId($release)) {
                    $nzbCount++;
                    if ($this->echoCLI) {
                        echo "Creating NZBs and deleting Collections: $nzbCount/$total.\r";
                    }
                }
            }
        }

        $totalTime = now()->diffInSeconds($startTime, true);

        if ($this->echoCLI) {
            $this->colorCLI->primary(
                number_format($nzbCount).' NZBs created/Collections deleted in '.
                $totalTime.Str::plural(' second', $totalTime).PHP_EOL.
                'Total time: '.$totalTime.Str::plural(' second', $totalTime),
                true
            );
        }

        return $nzbCount;
    }

    /**
     * Categorize releases.
     *
     * @param  int|string  $groupID  (optional)
     *
     * @void
     *
     * @throws \Exception
     */
    public function categorizeReleases(int $categorize, int|string $groupID = ''): void
    {
        $startTime = now()->toImmutable();
        if ($this->echoCLI) {
            $this->colorCLI->header('Process Releases -> Categorize releases.');
        }
        $type = match ((int) $categorize) {
            2 => 'searchname',
            default => 'name',
        };
        $this->categorizeRelease(
            $type,
            $groupID
        );

        $totalTime = now()->diffInSeconds($startTime, true);

        if ($this->echoCLI) {
            $this->colorCLI->primary($totalTime.Str::plural(' second', $totalTime));
        }
    }

    /**
     * Post-process releases.
     *
     *
     * @void
     *
     * @throws \Exception
     */
    public function postProcessReleases(int $postProcess, NNTP $nntp): void
    {
        if ((int) $postProcess === 1) {
            (new PostProcess(['Echo' => $this->echoCLI]))->processAll($nntp);
        } elseif ($this->echoCLI) {
            $this->colorCLI->info(
                'Post-processing is not running inside the Process Releases class.'.PHP_EOL.
                'If you are using tmux or screen they might have their own scripts running Post-processing.'
            );
        }
    }

    /**
     * @throws \Exception
     * @throws \Throwable
     */
    public function deleteCollections($groupID): void
    {
        // Delegate to service (group filter not used in original logic)
        $this->collectionCleanupService->deleteFinishedAndOrphans($this->echoCLI);
    }

    /**
     * Delete unwanted releases based on admin settings.
     * This deletes releases based on group.
     *
     * @param  int|string  $groupID  (optional)
     *
     * @void
     *
     * @throws \Exception
     */
    public function deletedReleasesByGroup(int|string $groupID = ''): void
    {
        $startTime = now()->toImmutable();
        $minSizeDeleted = $maxSizeDeleted = $minFilesDeleted = 0;

        if ($this->echoCLI) {
            $this->colorCLI->header('Process Releases -> Delete releases smaller/larger than minimum size/file count from group/site setting.');
        }

        $groupIDs = $groupID === '' ? UsenetGroup::getActiveIDs() : [['id' => $groupID]];

        $maxSizeSetting = Settings::settingValue('maxsizetoformrelease');
        $minSizeSetting = Settings::settingValue('minsizetoformrelease');
        $minFilesSetting = Settings::settingValue('minfilestoformrelease');

        foreach ($groupIDs as $grpID) {
            $releases = Release::query()->where('releases.groups_id', $grpID['id'])->whereRaw('greatest(IFNULL(usenet_groups.minsizetoformrelease, 0), ?) > 0 AND releases.size < greatest(IFNULL(usenet_groups.minsizetoformrelease, 0), ?)', [$minSizeSetting, $minSizeSetting])->join('usenet_groups', 'usenet_groups.id', '=', 'releases.groups_id')->select(['releases.id', 'releases.guid'])->get();
            foreach ($releases as $release) {
                $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                $minSizeDeleted++;
            }

            if ($maxSizeSetting > 0) {
                $releases = Release::query()->where('groups_id', $grpID['id'])->where('size', '>', $maxSizeSetting)->select(['id', 'guid'])->get();
                foreach ($releases as $release) {
                    $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                    $maxSizeDeleted++;
                }
            }
            if ($minFilesSetting > 0) {
                $releases = Release::query()->where('releases.groups_id', $grpID['id'])->whereRaw('greatest(IFNULL(usenet_groups.minfilestoformrelease, 0), ?) > 0 AND releases.totalpart < greatest(IFNULL(usenet_groups.minfilestoformrelease, 0), ?)', [$minFilesSetting, $minFilesSetting])->join('usenet_groups', 'usenet_groups.id', '=', 'releases.groups_id')->get();
                foreach ($releases as $release) {
                    $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                    $minFilesDeleted++;
                }
            }
        }

        $totalTime = now()->diffInSeconds($startTime);

        if ($this->echoCLI) {
            $this->colorCLI->primary(
                'Deleted '.($minSizeDeleted + $maxSizeDeleted + $minFilesDeleted).
                ' releases: '.PHP_EOL.
                $minSizeDeleted.' smaller than, '.$maxSizeDeleted.' bigger than, '.$minFilesDeleted.
                ' with less files than site/groups setting in: '.
                $totalTime.Str::plural(' second', $totalTime),
                true
            );
        }
    }

    /**
     * Delete releases using admin settings.
     * This deletes releases, regardless of group.
     *
     * @void
     *
     * @throws \Exception
     */
    public function deleteReleases(): void
    {
        $startTime = now()->toImmutable();
        $genres = new Genres;
        $passwordDeleted = $duplicateDeleted = $retentionDeleted = $completionDeleted = $disabledCategoryDeleted = 0;
        $disabledGenreDeleted = $miscRetentionDeleted = $miscHashedDeleted = $categoryMinSizeDeleted = 0;

        // Delete old releases and finished collections.
        if ($this->echoCLI) {
            $this->colorCLI->header('Process Releases -> Delete old releases and passworded releases.');
        }

        // Releases past retention.
        if ((int) Settings::settingValue('releaseretentiondays') !== 0) {
            $releases = Release::query()->where('postdate', '<', now()->subDays((int) Settings::settingValue('releaseretentiondays')))->select(['id', 'guid'])->get();
            foreach ($releases as $release) {
                $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                $retentionDeleted++;
            }
        }

        // Passworded releases.
        if ((int) Settings::settingValue('deletepasswordedrelease') === 1) {
            $releases = Release::query()
                ->select(['id', 'guid'])
                ->where('passwordstatus', '=', Releases::PASSWD_RAR)
                ->orWhereIn('id', function ($query) {
                    $query->select('releases_id')
                        ->from('release_files')
                        ->where('passworded', '=', Releases::PASSWD_RAR);
                })->get();
            foreach ($releases as $release) {
                $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                $passwordDeleted++;
            }
        }

        if ((int) $this->crossPostTime !== 0) {
            // Cross posted releases.
            $releases = Release::query()->where('adddate', '>', now()->subHours($this->crossPostTime))->havingRaw('COUNT(name) > 1 and COUNT(fromname) > 1')->groupBy(['name', 'fromname'])->select(['id', 'guid'])->get();
            foreach ($releases as $release) {
                $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                $duplicateDeleted++;
            }
        }

        if ($this->completion > 0) {
            $releases = Release::query()->where('completion', '<', $this->completion)->where('completion', '>', 0)->select(['id', 'guid'])->get();
            foreach ($releases as $release) {
                $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                $completionDeleted++;
            }
        }

        // Disabled categories.
        $disabledCategories = Category::getDisabledIDs();
        if (\count($disabledCategories) > 0) {
            foreach ($disabledCategories as $disabledCategory) {
                $releases = Release::query()->where('categories_id', (int) $disabledCategory['id'])->select(['id', 'guid'])->get();
                foreach ($releases as $release) {
                    $disabledCategoryDeleted++;
                    $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                }
            }
        }

        // Delete smaller than category minimum sizes.
        $categories = Category::query()->select(['id', 'minsizetoformrelease as minsize'])->get();

        foreach ($categories as $category) {
            if ((int) $category->minsize > 0) {
                $releases = Release::query()->where('categories_id', (int) $category->id)->where('size', '<', (int) $category->minsize)->select(['id', 'guid'])->limit(1000)->get();
                foreach ($releases as $release) {
                    $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                    $categoryMinSizeDeleted++;
                }
            }
        }

        // Disabled music genres.
        $genrelist = $genres->getDisabledIDs();
        if (\count($genrelist) > 0) {
            foreach ($genrelist as $genre) {
                $musicInfoQuery = MusicInfo::query()->where('genre_id', (int) $genre['id'])->select(['id']);
                $releases = Release::query()
                    ->joinSub($musicInfoQuery, 'mi', function ($join) {
                        $join->on('releases.musicinfo_id', '=', 'mi.id');
                    })
                    ->select(['releases.id', 'releases.guid'])
                    ->get();
                foreach ($releases as $release) {
                    $disabledGenreDeleted++;
                    $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                }
            }
        }

        // Misc other.
        if (Settings::settingValue('miscotherretentionhours') > 0) {
            $releases = Release::query()->where('categories_id', Category::OTHER_MISC)->where('adddate', '<=', now()->subHours((int) Settings::settingValue('miscotherretentionhours')))->select(['id', 'guid'])->get();
            foreach ($releases as $release) {
                $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                $miscRetentionDeleted++;
            }
        }

        // Misc hashed.
        if ((int) Settings::settingValue('mischashedretentionhours') > 0) {
            $releases = Release::query()->where('categories_id', Category::OTHER_HASHED)->where('adddate', '<=', now()->subHours((int) Settings::settingValue('mischashedretentionhours')))->select(['id', 'guid'])->get();
            foreach ($releases as $release) {
                $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                $miscHashedDeleted++;
            }
        }

        if ($this->echoCLI) {
            $this->colorCLI->primary(
                'Removed releases: '.
                number_format($retentionDeleted).
                ' past retention, '.
                number_format($passwordDeleted).
                ' passworded, '.
                number_format($duplicateDeleted).
                ' crossposted, '.
                number_format($disabledCategoryDeleted).
                ' from disabled categories, '.
                number_format($categoryMinSizeDeleted).
                ' smaller than category settings, '.
                number_format($disabledGenreDeleted).
                ' from disabled music genres, '.
                number_format($miscRetentionDeleted).
                ' from misc->other '.
                number_format($miscHashedDeleted).
                ' from misc->hashed'.
                (
                    $this->completion > 0
                    ? ', '.number_format($completionDeleted).' under '.$this->completion.'% completion.'
                    : '.'
                ),
                true
            );

            $totalDeleted = (
                $retentionDeleted + $passwordDeleted + $duplicateDeleted + $disabledCategoryDeleted +
                $disabledGenreDeleted + $miscRetentionDeleted + $miscHashedDeleted + $completionDeleted +
                $categoryMinSizeDeleted
            );
            if ($totalDeleted > 0) {
                $totalTime = now()->diffInSeconds($startTime);
                $this->colorCLI->primary(
                    'Removed '.number_format($totalDeleted).' releases in '.
                    $totalTime.Str::plural(' second', $totalTime),
                    true
                );
            }
        }
    }

    /**
     * Look if we have all the files in a collection (which have the file count in the subject).
     * Set file check to complete.
     * This means the the binary table has the same count as the file count in the subject, but
     * the collection might not be complete yet since we might not have all the articles in the parts table.
     *
     *
     * @void
     *
     * @throws \Throwable
     */
    private function collectionFileCheckStage1(int $groupID): void
    {
        DB::transaction(static function () use ($groupID) {
            $collectionsCheck = Collection::query()->select(['collections.id'])
                ->join('binaries', 'binaries.collections_id', '=', 'collections.id')
                ->where('collections.totalfiles', '>', 0)
                ->where('collections.filecheck', '=', self::COLLFC_DEFAULT);
            if (! empty($groupID)) {
                $collectionsCheck->where('collections.groups_id', $groupID);
            }
            $collectionsCheck->groupBy(['binaries.collections_id', 'collections.totalfiles', 'collections.id'])
                ->havingRaw('COUNT(binaries.id) IN (collections.totalfiles, collections.totalfiles+1)');

            Collection::query()->joinSub($collectionsCheck, 'r', function ($join) {
                $join->on('collections.id', '=', 'r.id');
            })->update(['collections.filecheck' => self::COLLFC_COMPCOLL]);
        }, 10);
    }

    /**
     * The first query sets filecheck to COLLFC_ZEROPART if there's a file that starts with 0 (ex. [00/100]).
     * The second query sets filecheck to COLLFC_TEMPCOMP on everything left over, so anything that starts with 1 (ex. [01/100]).
     *
     * This is done because some collections start at 0 and some at 1, so if you were to assume the collection is complete
     * at 0 then you would never get a complete collection if it starts with 1 and if it starts, you can end up creating
     * a incomplete collection, since you assumed it was complete.
     *
     *
     * @void
     *
     * @throws \Throwable
     */
    private function collectionFileCheckStage2(int $groupID): void
    {
        DB::transaction(static function () use ($groupID) {
            $collectionsCheck = Collection::query()->select(['collections.id'])
                ->join('binaries', 'binaries.collections_id', '=', 'collections.id')
                ->where('binaries.filenumber', '=', 0)
                ->where('collections.totalfiles', '>', 0)
                ->where('collections.filecheck', '=', self::COLLFC_COMPCOLL);
            if (! empty($groupID)) {
                $collectionsCheck->where('collections.groups_id', $groupID);
            }
            $collectionsCheck->groupBy(['collections.id']);

            Collection::query()->joinSub($collectionsCheck, 'r', function ($join) {
                $join->on('collections.id', '=', 'r.id');
            })->update(['collections.filecheck' => self::COLLFC_ZEROPART]);
        }, 10);

        DB::transaction(static function () use ($groupID) {
            $collectionQuery = Collection::query()->where('filecheck', '=', self::COLLFC_COMPCOLL);
            if (! empty($groupID)) {
                $collectionQuery->where('groups_id', $groupID);
            }
            $collectionQuery->update(['filecheck' => self::COLLFC_TEMPCOMP]);
        }, 10);
    }

    /**
     * Check if the files (binaries table) in a complete collection has all the parts.
     * If we have all the parts, set binaries table partcheck to FILE_COMPLETE.
     *
     *
     * @void
     *
     * @throws \Throwable
     */
    private function collectionFileCheckStage3(string $where): void
    {
        DB::transaction(static function () use ($where) {
            $sql = '
                UPDATE binaries b
                INNER JOIN
                (
                    SELECT b.id
                    FROM binaries b
                    INNER JOIN collections c ON c.id = b.collections_id
                    WHERE c.filecheck = ?
                    AND b.partcheck = ? '.$where.'
                    AND b.currentparts = b.totalparts
                    GROUP BY b.id, b.totalparts
                ) r ON b.id = r.id
                SET b.partcheck = ?';
            DB::update($sql, [self::COLLFC_TEMPCOMP, self::FILE_INCOMPLETE, self::FILE_COMPLETE]);
        }, 10);

        DB::transaction(static function () use ($where) {
            $sql = '
                UPDATE binaries b
                INNER JOIN
                (
                    SELECT b.id
                    FROM binaries b
                    INNER JOIN collections c ON c.id = b.collections_id
                    WHERE c.filecheck = ?
                    AND b.partcheck = ? '.$where.'
                    AND b.currentparts >= (b.totalparts + 1)
                    GROUP BY b.id, b.totalparts
                ) r ON b.id = r.id
                SET b.partcheck = ?';
            DB::update($sql, [self::COLLFC_ZEROPART, self::FILE_INCOMPLETE, self::FILE_COMPLETE]);
        }, 10);
    }

    /**
     * Check if all files (binaries table) for a collection are complete (if they all have the "parts").
     * Set collections filecheck column to COLLFC_COMPPART.
     * This means the collection is complete.
     *
     *
     * @void
     *
     * @throws \Throwable
     */
    private function collectionFileCheckStage4(string $where): void
    {
        DB::transaction(static function () use ($where) {
            $sql = '
                UPDATE collections c INNER JOIN
                    (SELECT c.id FROM collections c
                    INNER JOIN binaries b ON c.id = b.collections_id
                    WHERE b.partcheck = 1 AND c.filecheck IN (?, ?) '.$where.'
                    GROUP BY b.collections_id, c.totalfiles, c.id HAVING COUNT(b.id) >= c.totalfiles)
                r ON c.id = r.id SET filecheck = ?';
            DB::update($sql, [self::COLLFC_TEMPCOMP, self::COLLFC_ZEROPART, self::COLLFC_COMPPART]);
        }, 10);
    }

    /**
     * If not all files (binaries table) had their parts on the previous stage,
     * reset the collection filecheck column to COLLFC_COMPCOLL so we reprocess them next time.
     *
     *
     * @void
     *
     * @throws \Throwable
     */
    private function collectionFileCheckStage5(int $groupId): void
    {
        DB::transaction(static function () use ($groupId) {
            $collectionQuery = Collection::query()->whereIn('filecheck', [self::COLLFC_TEMPCOMP, self::COLLFC_ZEROPART]);
            if (! empty($groupId)) {
                $collectionQuery->where('groups_id', $groupId);
            }
            $collectionQuery->update(['filecheck' => self::COLLFC_COMPCOLL]);
        }, 10);
    }

    /**
     * If a collection did not have the file count (ie: [00/12]) or the collection is incomplete after
     * $this->collectionDelayTime hours, set the collection to complete to create it into a release/nzb.
     *
     *
     * @void
     *
     * @throws \Throwable
     */
    private function collectionFileCheckStage6(string $where): void
    {
        DB::transaction(function () use ($where) {
            $sql = '
                UPDATE collections c SET filecheck = ?, totalfiles = (SELECT COUNT(b.id) FROM binaries b WHERE b.collections_id = c.id)
                WHERE c.dateadded < NOW() - INTERVAL ? HOUR
                AND c.filecheck IN (?, ?, 10)'.$where;
            DB::update($sql, [self::COLLFC_COMPPART, $this->collectionDelayTime, self::COLLFC_DEFAULT, self::COLLFC_COMPCOLL]);
        }, 10);
    }

    /**
     * If a collection has been stuck for $this->collectionTimeout hours, delete it, it's bad.
     *
     *
     * @void
     *
     * @throws \Exception
     * @throws \Throwable
     */
    private function processStuckCollections(int $groupID): void
    {
        $lastRun = Settings::settingValue('last_run_time');

        // Compute cutoff timestamp once.
        $threshold = null;
        try {
            if (! empty($lastRun)) {
                $threshold = \Illuminate\Support\Carbon::createFromFormat('Y-m-d H:i:s', $lastRun);
            }
        } catch (\Throwable $e) {
            $threshold = null;
        }
        if ($threshold === null) {
            $threshold = now();
        }
        $cutoff = $threshold->copy()->subHours($this->collectionTimeout);

        $totalDeleted = 0;
        $maxRetries = 5;

        // Delete in small batches using a single-statement DELETE via nested subselect to avoid "record changed since last read".
        do {
            $affected = 0;
            $attempt = 0;
            do {
                try {
                    if (! empty($groupID)) {
                        $affected = DB::affectingStatement(
                            'DELETE FROM collections WHERE id IN (
                                SELECT id FROM (
                                    SELECT id FROM collections WHERE added < ? AND groups_id = ? ORDER BY id LIMIT 500
                                ) AS x
                            )',
                            [$cutoff, $groupID]
                        );
                    } else {
                        $affected = DB::affectingStatement(
                            'DELETE FROM collections WHERE id IN (
                                SELECT id FROM (
                                    SELECT id FROM collections WHERE added < ? ORDER BY id LIMIT 500
                                ) AS x
                            )',
                            [$cutoff]
                        );
                    }
                    break; // success
                } catch (\Throwable $e) {
                    $attempt++;
                    if ($attempt >= $maxRetries) {
                        if ($this->echoCLI) {
                            $this->colorCLI->error('Stuck collections delete failed after retries: '.$e->getMessage());
                        }
                        break;
                    }
                    // Exponential backoff to ease contention
                    usleep(20000 * $attempt);
                }
            } while (true);

            $totalDeleted += $affected;

            if ($affected < 500) {
                break;
            }

            // Yield briefly to reduce contention in busy systems.
            usleep(10000);
        } while (true);

        if ($this->echoCLI && $totalDeleted > 0) {
            $this->colorCLI->primary('Deleted '.$totalDeleted.' broken/stuck collections.', true);
        }
    }

    /**
     * Normalize and return the group ID.
     */
    private function normalizeGroupId(int|string $groupID): ?int
    {
        if (is_numeric($groupID)) {
            return (int) $groupID;
        }

        $groupInfo = UsenetGroup::getByName($groupID);

        return $groupInfo !== null ? (int) $groupInfo['id'] : null;
    }

    /**
     * Build the SQL "where" snippet for group ID filtering.
     *
     * @param  string  $alias  Table alias for the group ID column.
     */
    private function groupWhereSql(?int $groupID, string $alias = 'c'): string
    {
        return $groupID !== null ? ' AND '.$alias.'.groups_id = '.$groupID.' ' : ' ';
    }
}
