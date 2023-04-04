<?php

namespace Blacklight\processing;

use App\Models\Category;
use App\Models\Collection;
use App\Models\MusicInfo;
use App\Models\Predb;
use App\Models\Release;
use App\Models\ReleaseRegex;
use App\Models\ReleasesGroups;
use App\Models\Settings;
use App\Models\UsenetGroup;
use Blacklight\Categorize;
use Blacklight\ConsoleTools;
use Blacklight\Genres;
use Blacklight\NNTP;
use Blacklight\NZB;
use Blacklight\ReleaseCleaning;
use Blacklight\ReleaseImage;
use Blacklight\Releases;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JetBrains\PhpStorm\ArrayShape;

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

    public ConsoleTools $consoleTools;

    public NZB $nzb;

    public ReleaseCleaning $releaseCleaning;

    public Releases $releases;

    public ReleaseImage $releaseImage;

    /**
     * Time (hours) to wait before delete a stuck/broken collection.
     */
    private int $collectionTimeout;

    /**
     * @param  array  $options  Class instances / Echo to cli ?
     *
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Echo' => true,
            'ConsoleTools' => null,
            'Groups' => null,
            'NZB' => null,
            'ReleaseCleaning' => null,
            'ReleaseImage' => null,
            'Releases' => null,
            'Settings' => null,
        ];
        $options += $defaults;

        $this->echoCLI = ($options['Echo'] && config('nntmux.echocli'));

        $this->consoleTools = ($options['ConsoleTools'] instanceof ConsoleTools ? $options['ConsoleTools'] : new ConsoleTools());
        $this->nzb = ($options['NZB'] instanceof NZB ? $options['NZB'] : new NZB());
        $this->releaseCleaning = ($options['ReleaseCleaning'] instanceof ReleaseCleaning ? $options['ReleaseCleaning'] : new ReleaseCleaning());
        $this->releases = ($options['Releases'] instanceof Releases ? $options['Releases'] : new Releases());
        $this->releaseImage = ($options['ReleaseImage'] instanceof ReleaseImage ? $options['ReleaseImage'] : new ReleaseImage());

        $dummy = Settings::settingValue('..delaytime');
        $this->collectionDelayTime = ($dummy !== '' ? (int) $dummy : 2);
        $dummy = Settings::settingValue('..crossposttime');
        $this->crossPostTime = ($dummy !== '' ? (int) $dummy : 2);
        $dummy = Settings::settingValue('..maxnzbsprocessed');
        $this->releaseCreationLimit = ($dummy !== '' ? (int) $dummy : 1000);
        $dummy = Settings::settingValue('..completionpercent');
        $this->completion = ($dummy !== '' ? (int) $dummy : 0);
        if ($this->completion > 100) {
            $this->completion = 100;
            $this->consoleTools->error(PHP_EOL.'You have an invalid setting for completion. It cannot be higher than 100.');
        }
        $this->collectionTimeout = (int) Settings::settingValue('indexer.processing.collection_timeout');
    }

    /**
     * Main method for creating releases/NZB files from collections.
     *
     * @param  string  $groupName  (optional)
     *
     * @throws \Throwable
     */
    public function processReleases(int $categorize, int $postProcess, string $groupName, NNTP $nntp, bool $echooutput): int
    {
        $this->echoCLI = ($echooutput && config('nntmux.echocli'));
        $groupID = '';

        if (! empty($groupName) && $groupName !== 'mgr') {
            $groupInfo = UsenetGroup::getByName($groupName);
            if ($groupInfo !== null) {
                $groupID = $groupInfo['id'];
            }
        }

        if ($this->echoCLI) {
            $this->consoleTools->header('Starting release update process ('.now()->format('Y-m-d H:i:s').')');
        }

        if (! file_exists(Settings::settingValue('..nzbpath'))) {
            if ($this->echoCLI) {
                $this->consoleTools->error('Bad or missing nzb directory - '.Settings::settingValue('..nzbpath'));
            }

            return 0;
        }

        $this->processIncompleteCollections($groupID);
        $this->processCollectionSizes($groupID);
        $this->deleteUnwantedCollections($groupID);

        $totalReleasesAdded = 0;
        do {
            $releasesCount = $this->createReleases($groupID);
            $totalReleasesAdded += $releasesCount['added'];

            $nzbFilesAdded = $this->createNZBs($groupID);

            $this->categorizeReleases($categorize, $groupID);
            $this->postProcessReleases($postProcess, $nntp);
            $this->deleteCollections($groupID);

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
        DB::update(
            sprintf('UPDATE releases SET categories_id = %d, iscategorized = 0 %s', Category::OTHER_MISC, $where)
        );
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
        $cat = new Categorize();
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
                    $this->consoleTools->overWritePrimary(
                        'Categorizing: '.$this->consoleTools->percentString($categorized, $total)
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
            $this->consoleTools->header('Process Releases -> Attempting to find complete collections.');
        }

        $where = (! empty($groupID) ? ' AND c.groups_id = '.$groupID.' ' : ' ');

        $this->processStuckCollections($groupID);
        $this->collectionFileCheckStage1($groupID);
        $this->collectionFileCheckStage2($groupID);
        $this->collectionFileCheckStage3($where);
        $this->collectionFileCheckStage4($where);
        $this->collectionFileCheckStage5($groupID);
        $this->collectionFileCheckStage6($where);

        if ($this->echoCLI) {
            $countQuery = Collection::query()->where('filecheck', self::COLLFC_COMPPART);

            if (! empty($groupID)) {
                $countQuery->where('groups_id', $groupID);
            }
            $count = $countQuery->count('id');

            $totalTime = now()->diffInSeconds($startTime);

            $this->consoleTools->primary(
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
            $this->consoleTools->header('Process Releases -> Calculating collection sizes (in bytes).');
        }
        // Get the total size in bytes of the collection for collections where filecheck = 2.
        DB::transaction(function () use ($groupID, $startTime) {
            $checked = DB::update(
                sprintf(
                    '
				UPDATE collections c
				SET c.filesize =
				(
					SELECT COALESCE(SUM(b.partsize), 0)
					FROM binaries b
					WHERE b.collections_id = c.id
				),
				c.filecheck = %d
				WHERE c.filecheck = %d
				AND c.filesize = 0 %s',
                    self::COLLFC_SIZED,
                    self::COLLFC_COMPPART,
                    (! empty($groupID) ? ' AND c.groups_id = '.$groupID : ' ')
                )
            );
            if ($checked > 0 && $this->echoCLI) {
                $this->consoleTools->primary(
                    $checked.' collections set to filecheck = 3(size calculated)',
                    true
                );
                $totalTime = now()->diffInSeconds($startTime);
                $this->consoleTools->primary($totalTime.Str::plural(' second', $totalTime), true);
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
            $this->consoleTools->header('Process Releases -> Delete collections smaller/larger than minimum size/file count from group/site setting.');
        }

        $groupID === '' ? $groupIDs = UsenetGroup::getActiveIDs() : $groupIDs = [['id' => $groupID]];

        $minSizeDeleted = $maxSizeDeleted = $minFilesDeleted = 0;

        $maxSizeSetting = Settings::settingValue('.release.maxsizetoformrelease');
        $minSizeSetting = Settings::settingValue('.release.minsizetoformrelease');
        $minFilesSetting = Settings::settingValue('.release.minfilestoformrelease');

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
                    $minSizeDeleted,
                    $maxSizeSetting,
                    $maxSizeDeleted,
                    $minFilesSetting,
                    $groupMinFilesSetting,
                    $minFilesDeleted,
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

                    $totalTime = now()->diffInSeconds($startTime);

                    if ($this->echoCLI) {
                        $this->consoleTools->primary('Deleted '.($minSizeDeleted + $maxSizeDeleted + $minFilesDeleted).' collections: '.PHP_EOL.$minSizeDeleted.' smaller than, '.$maxSizeDeleted.' bigger than, '.$minFilesDeleted.' with less files than site/group settings in: '.$totalTime.Str::plural(' second', $totalTime), true);
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
    #[ArrayShape(['added' => 'int', 'dupes' => 'int'])]
 public function createReleases(int|string $groupID): array
 {
     $startTime = now()->toImmutable();

     $categorize = new Categorize();
     $returnCount = $duplicate = 0;

     if ($this->echoCLI) {
         $this->consoleTools->header('Process Releases -> Create releases from complete collections.');
     }
     $collectionsQuery = Collection::query()
         ->where('collections.filecheck', self::COLLFC_SIZED)
         ->where('collections.filesize', '>', 0);
     if (! empty($groupID)) {
         $collectionsQuery->where('collections.groups_id', $groupID);
     }
     $collectionsQuery->select(['collections.*', 'usenet_groups.name as gname'])
         ->join('usenet_groups', 'usenet_groups.id', '=', 'collections.groups_id')
         ->limit($this->releaseCreationLimit);
     $collections = $collectionsQuery->get();
     if ($this->echoCLI && $collections->count() > 0) {
         $this->consoleTools->primary(\count($collections).' Collections ready to be converted to releases.', true);
     }

     foreach ($collections as $collection) {
         $cleanRelName = mb_convert_encoding(str_replace(['#', '@', '$', '%', '^', '§', '¨', '©', 'Ö'], '', $collection->subject), 'UTF-8');
         $fromName = mb_convert_encoding(
             trim($collection->fromname, "'"), 'UTF-8'
         );

         // Look for duplicates, duplicates match on releases.name, releases.fromname and releases.size
         // A 1% variance in size is considered the same size when the subject and poster are the same
         $dupeCheck = Release::query()
             ->where(['name' => $cleanRelName, 'fromname' => $fromName])
             ->whereBetween('size', [$collection->filesize * .99, $collection->filesize * 1.01])
             ->first(['id']);

         if ($dupeCheck === null) {
             $cleanedName = $this->releaseCleaning->releaseCleaner(
                 $collection->subject,
                 $collection->fromname,
                 $collection->gname
             );

             if (\is_array($cleanedName)) {
                 $properName = $cleanedName['properlynamed'] ?? false;
                 $preID = $cleanedName['predb'] ?? false;
                 $cleanedName = $cleanedName['cleansubject'] ?? $cleanRelName;
             } else {
                 $properName = true;
                 $preID = false;
             }

             if ($preID === false && $cleanedName !== '') {
                 // try to match the cleaned searchname to predb title or filename here
                 $preMatch = Predb::matchPre($cleanedName);
                 if ($preMatch !== false) {
                     $cleanedName = $preMatch['title'];
                     $preID = $preMatch['predb_id'];
                     $properName = true;
                 }
             }

             $determinedCategory = $categorize->determineCategory($collection->groups_id, $cleanedName);

             $releaseID = Release::insertRelease(
                 [
                     'name' => $cleanRelName,
                     'searchname' => ! empty($cleanedName) ? mb_convert_encoding($cleanedName, 'UTF-8') : $cleanRelName,
                     'totalpart' => $collection->totalfiles,
                     'groups_id' => $collection->groups_id,
                     'guid' => createGUID(),
                     'postdate' => $collection->date,
                     'fromname' => $fromName,
                     'size' => $collection->filesize,
                     'categories_id' => $determinedCategory['categories_id'] ?? Category::OTHER_MISC,
                     'isrenamed' => $properName === true ? 1 : 0,
                     'predb_id' => $preID === false ? 0 : $preID,
                     'nzbstatus' => NZB::NZB_NONE,
                 ]
             );

             if ($releaseID !== null) {
                 // Update collections table to say we inserted the release.
                 DB::transaction(static function () use ($collection, $releaseID) {
                     Collection::query()->where('id', $collection->id)->update(['filecheck' => self::COLLFC_INSERTED, 'releases_id' => $releaseID]);
                 }, 10);

                 // Add the id of regex that matched the collection and release name to release_regexes table
                 ReleaseRegex::insertOrIgnore([
                     'releases_id' => $releaseID,
                     'collection_regex_id' => $collection->collection_regexes_id,
                     'naming_regex_id' => $cleanedName['id'] ?? 0,
                 ]);

                 if (preg_match_all('#(\S+):\S+#', $collection->xref, $hits)) {
                     foreach ($hits[1] as $grp) {
                         //check if the group name is in a valid format
                         $grpTmp = UsenetGroup::isValidGroup($grp);
                         if ($grpTmp !== false) {
                             //check if the group already exists in database
                             $xrefGrpID = UsenetGroup::getIDByName($grpTmp);
                             if ($xrefGrpID === '') {
                                 $xrefGrpID = UsenetGroup::addGroup(
                                     [
                                         'name' => $grpTmp,
                                         'description' => 'Added by Release processing',
                                         'backfill_target' => 1,
                                         'first_record' => 0,
                                         'last_record' => 0,
                                         'active' => 0,
                                         'backfill' => 0,
                                         'minfilestoformrelease' => '',
                                         'minsizetoformrelease' => '',
                                     ]
                                 );
                             }

                             $relGroupsChk = ReleasesGroups::query()->where(
                                 [
                                     ['releases_id', '=', $releaseID],
                                     ['groups_id', '=', $xrefGrpID],
                                 ]
                             )->first();

                             if ($relGroupsChk === null) {
                                 ReleasesGroups::query()->insert(
                                     [
                                         'releases_id' => $releaseID,
                                         'groups_id' => $xrefGrpID,
                                     ]
                                 );
                             }
                         }
                     }
                 }

                 $returnCount++;

                 if ($this->echoCLI) {
                     echo "Added $returnCount releases.\r";
                 }
             }
         } else {
             // The release was already in the DB, so delete the collection.
             DB::transaction(static function () use ($collection) {
                 Collection::query()->where('collectionhash', $collection->collectionhash)->delete();
             }, 10);

             $duplicate++;
         }
     }

     $totalTime = now()->diffInSeconds($startTime);

     if ($this->echoCLI) {
         $this->consoleTools->primary(
             PHP_EOL.
             number_format($returnCount).
             ' Releases added and '.
             number_format($duplicate).
             ' duplicate collections deleted in '.
             $totalTime.Str::plural(' second', $totalTime),
             true
         );
     }

     return ['added' => $returnCount, 'dupes' => $duplicate];
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
            $this->consoleTools->header('Process Releases -> Create the NZB, delete collections/binaries/parts.');
        }

        $releasesQuery = Release::query()->with('category.parent')->where('nzbstatus', '=', 0);
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

        $totalTime = now()->diffInSeconds($startTime);

        if ($this->echoCLI) {
            $this->consoleTools->primary(
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
            $this->consoleTools->header('Process Releases -> Categorize releases.');
        }
        $type = match ((int) $categorize) {
            2 => 'searchname',
            default => 'name',
        };
        $this->categorizeRelease(
            $type,
            $groupID
        );

        $totalTime = now()->diffInSeconds($startTime);

        if ($this->echoCLI) {
            $this->consoleTools->primary($totalTime.Str::plural(' second', $totalTime));
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
            $this->consoleTools->info(
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
        $startTime = now()->toImmutable();

        $deletedCount = 0;

        // CBP older than retention.
        if ($this->echoCLI) {
            echo
                $this->consoleTools->header('Process Releases -> Delete finished collections.'.PHP_EOL).
                $this->consoleTools->primary(sprintf(
                    'Deleting collections/binaries/parts older than %d hours.',
                    Settings::settingValue('..partretentionhours')
                ), true);
        }

        DB::transaction(function () use ($deletedCount, $startTime) {
            $deleted = 0;
            $deleteQuery = Collection::query()
                ->where('dateadded', '<', now()->subHours(Settings::settingValue('..partretentionhours')))
                ->delete();
            if ($deleteQuery > 0) {
                $deleted = $deleteQuery;
                $deletedCount += $deleted;
            }
            $firstQuery = $fourthQuery = now();

            $totalTime = $firstQuery->diffInSeconds($startTime);

            if ($this->echoCLI) {
                $this->consoleTools->primary(
                    'Finished deleting '.$deleted.' old collections/binaries/parts in '.
                    $totalTime.Str::plural(' second', $totalTime),
                    true
                );
            }

            // Cleanup orphaned collections, binaries and parts
            // this really shouldn't happen, but just incase - so we only run 1/200 of the time
            if (random_int(0, 200) <= 1) {
                // CBP collection orphaned with no binaries or parts.
                if ($this->echoCLI) {
                    echo $this->consoleTools->header('Process Releases -> Remove CBP orphans.'.PHP_EOL).$this->consoleTools->primary('Deleting orphaned collections.');
                }

                $deleted = 0;
                $deleteQuery = Collection::query()->whereNull('binaries.id')->orWhereNull('parts.binaries_id')->leftJoin('binaries', 'collections.id', '=', 'binaries.collections_id')->leftJoin('parts', 'binaries.id', '=', 'parts.binaries_id')->delete();

                if ($deleteQuery > 0) {
                    $deleted = $deleteQuery;
                    $deletedCount += $deleted;
                }

                $totalTime = now()->diffInSeconds($firstQuery);

                if ($this->echoCLI) {
                    $this->consoleTools->primary('Finished deleting '.$deleted.' orphaned collections in '.$totalTime.Str::plural(' second', $totalTime), true);
                }
            }

            if ($this->echoCLI) {
                $this->consoleTools->primary('Deleting collections that were missed after NZB creation.', true);
            }

            $deleted = 0;
            // Collections that were missing on NZB creation.
            $collections = Collection::query()->where('releases.nzbstatus', '=', 1)->leftJoin('releases', 'releases.id', '=', 'collections.releases_id')->select('collections.id')->get();

            foreach ($collections as $collection) {
                $deleted++;
                Collection::query()->where('id', $collection->id)->delete();
            }
            $deletedCount += $deleted;

            $colDelTime = now()->diffInSeconds($fourthQuery);
            $totalTime = $fourthQuery->diffInSeconds($startTime);

            if ($this->echoCLI) {
                $this->consoleTools->primary('Finished deleting '.$deleted.' collections missed after NZB creation in '.$colDelTime.Str::plural(' second', $colDelTime).PHP_EOL.'Removed '.number_format($deletedCount).' parts/binaries/collection rows in '.$totalTime.Str::plural(' second', $totalTime), true);
            }
        }, 10);
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
            $this->consoleTools->header('Process Releases -> Delete releases smaller/larger than minimum size/file count from group/site setting.');
        }

        $groupIDs = $groupID === '' ? UsenetGroup::getActiveIDs() : [['id' => $groupID]];

        $maxSizeSetting = Settings::settingValue('.release.maxsizetoformrelease');
        $minSizeSetting = Settings::settingValue('.release.minsizetoformrelease');
        $minFilesSetting = Settings::settingValue('.release.minfilestoformrelease');

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
            $this->consoleTools->primary(
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
        $genres = new Genres();
        $passwordDeleted = $duplicateDeleted = $retentionDeleted = $completionDeleted = $disabledCategoryDeleted = 0;
        $disabledGenreDeleted = $miscRetentionDeleted = $miscHashedDeleted = $categoryMinSizeDeleted = 0;

        // Delete old releases and finished collections.
        if ($this->echoCLI) {
            $this->consoleTools->header('Process Releases -> Delete old releases and passworded releases.');
        }

        // Releases past retention.
        if ((int) Settings::settingValue('..releaseretentiondays') !== 0) {
            $releases = Release::query()->where('postdate', '<', now()->subDays((int) Settings::settingValue('..releaseretentiondays')))->select(['id', 'guid'])->get();
            foreach ($releases as $release) {
                $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                $retentionDeleted++;
            }
        }

        // Passworded releases.
        if ((int) Settings::settingValue('..deletepasswordedrelease') === 1) {
            $releases = Release::query()->join('release_files', 'release_files.releases_id', '=', 'releases.id')->select(['id', 'guid'])->where('release_files.passworded', '=', Releases::PASSWD_RAR)->orWhere('passwordstatus', '=', Releases::PASSWD_RAR)->get();
            foreach ($releases as $release) {
                $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                $passwordDeleted++;
            }
        }

        if ((int) $this->crossPostTime !== 0) {
            // Cross posted releases.
            $releases = Release::query()->where('adddate', '>', now()->subHours($this->crossPostTime))->havingRaw('COUNT(name) > 1')->groupBy('name')->select(['id', 'guid'])->get();
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
        if (Settings::settingValue('..miscotherretentionhours') > 0) {
            $releases = Release::query()->where('categories_id', Category::OTHER_MISC)->where('adddate', '<=', now()->subHours((int) Settings::settingValue('..miscotherretentionhours')))->select(['id', 'guid'])->get();
            foreach ($releases as $release) {
                $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                $miscRetentionDeleted++;
            }
        }

        // Misc hashed.
        if ((int) Settings::settingValue('..mischashedretentionhours') > 0) {
            $releases = Release::query()->where('categories_id', Category::OTHER_HASHED)->where('adddate', '<=', now()->subHours((int) Settings::settingValue('..mischashedretentionhours')))->select(['id', 'guid'])->get();
            foreach ($releases as $release) {
                $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                $miscHashedDeleted++;
            }
        }

        if ($this->echoCLI) {
            $this->consoleTools->primary(
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
                $this->consoleTools->primary(
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
            $collectionsCheck->groupBy('binaries.collections_id', 'collections.totalfiles', 'collections.id')
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
            $collectionsCheck->groupBy('collections.id');

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
            DB::update(
                sprintf(
                    '
				UPDATE binaries b
				INNER JOIN
				(
					SELECT b.id
					FROM binaries b
					INNER JOIN collections c ON c.id = b.collections_id
					WHERE c.filecheck = %d
					AND b.partcheck = %d %s
					AND b.currentparts = b.totalparts
					GROUP BY b.id, b.totalparts
				) r ON b.id = r.id
				SET b.partcheck = %d',
                    self::COLLFC_TEMPCOMP,
                    self::FILE_INCOMPLETE,
                    $where,
                    self::FILE_COMPLETE
                )
            );
        }, 10);

        DB::transaction(static function () use ($where) {
            DB::update(
                sprintf(
                    '
				UPDATE binaries b
				INNER JOIN
				(
					SELECT b.id
					FROM binaries b
					INNER JOIN collections c ON c.id = b.collections_id
					WHERE c.filecheck = %d
					AND b.partcheck = %d %s
					AND b.currentparts >= (b.totalparts + 1)
					GROUP BY b.id, b.totalparts
				) r ON b.id = r.id
				SET b.partcheck = %d',
                    self::COLLFC_ZEROPART,
                    self::FILE_INCOMPLETE,
                    $where,
                    self::FILE_COMPLETE
                )
            );
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
    private function collectionFileCheckStage4(string &$where): void
    {
        DB::transaction(static function () use ($where) {
            DB::update(
                sprintf(
                    '
				UPDATE collections c INNER JOIN
					(SELECT c.id FROM collections c
					INNER JOIN binaries b ON c.id = b.collections_id
					WHERE b.partcheck = 1 AND c.filecheck IN (%d, %d) %s
					GROUP BY b.collections_id, c.totalfiles, c.id HAVING COUNT(b.id) >= c.totalfiles)
				r ON c.id = r.id SET filecheck = %d',
                    self::COLLFC_TEMPCOMP,
                    self::COLLFC_ZEROPART,
                    $where,
                    self::COLLFC_COMPPART
                )
            );
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
    private function collectionFileCheckStage6(string &$where): void
    {
        DB::transaction(function () use ($where) {
            DB::update(
                sprintf(
                    "
				UPDATE collections c SET filecheck = %d, totalfiles = (SELECT COUNT(b.id) FROM binaries b WHERE b.collections_id = c.id)
				WHERE c.dateadded < NOW() - INTERVAL '%d' HOUR
				AND c.filecheck IN (%d, %d, 10) %s",
                    self::COLLFC_COMPPART,
                    $this->collectionDelayTime,
                    self::COLLFC_DEFAULT,
                    self::COLLFC_COMPCOLL,
                    $where
                )
            );
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
        $lastRun = Settings::settingValue('indexer.processing.last_run_time');

        DB::transaction(function () use ($groupID, $lastRun) {
            $objQuery = Collection::query()
                ->where('added', '<', Carbon::createFromFormat('Y-m-d H:i:s', $lastRun)->subHours($this->collectionTimeout));
            if (! empty($groupID)) {
                $objQuery->where('groups_id', $groupID);
            }
            $obj = $objQuery->delete();
            if ($this->echoCLI && $obj > 0) {
                $this->consoleTools->primary('Deleted '.$obj.' broken/stuck collections.', true);
            }
        }, 10);
    }
}
