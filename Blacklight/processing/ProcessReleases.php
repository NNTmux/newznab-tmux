<?php

namespace Blacklight\processing;

use Blacklight\NZB;
use Blacklight\NNTP;
use App\Models\Group;
use App\Models\Predb;
use Blacklight\Genres;
use App\Models\Release;
use App\Models\Category;
use App\Models\Settings;
use Blacklight\ColorCLI;
use Blacklight\Releases;
use Blacklight\Categorize;
use App\Models\ReleaseRegex;
use Blacklight\ConsoleTools;
use Blacklight\ReleaseImage;
use App\Models\ReleasesGroups;
use Blacklight\ReleaseCleaning;
use Illuminate\Support\Facades\DB;

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

    /**
     * @var int
     */
    public $collectionDelayTime;

    /**
     * @var int
     */
    public $crossPostTime;

    /**
     * @var int
     */
    public $releaseCreationLimit;

    /**
     * @var int
     */
    public $completion;

    /**
     * @var bool
     */
    public $echoCLI;

    /**
     * @var \PDO
     */
    public $pdo;

    /**
     * @var \Blacklight\ConsoleTools
     */
    public $consoleTools;

    /**
     * @var \Blacklight\NZB
     */
    public $nzb;

    /**
     * @var \Blacklight\ReleaseCleaning
     */
    public $releaseCleaning;

    /**
     * @var \Blacklight\Releases
     */
    public $releases;

    /**
     * @var \Blacklight\ReleaseImage
     */
    public $releaseImage;

    /**
     * Time (hours) to wait before delete a stuck/broken collection.
     *
     *
     * @var int
     */
    private $collectionTimeout;

    /**
     * @var \Blacklight\ColorCLI
     */
    protected $colorCli;

    /**
     * @param array $options Class instances / Echo to cli ?
     *
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Echo'            => true,
            'ConsoleTools'    => null,
            'Groups'          => null,
            'NZB'             => null,
            'ReleaseCleaning' => null,
            'ReleaseImage'    => null,
            'Releases'        => null,
            'Settings'        => null,
        ];
        $options += $defaults;

        $this->echoCLI = ($options['Echo'] && config('nntmux.echocli'));

        $this->pdo = DB::connection()->getPdo();
        $this->consoleTools = ($options['ConsoleTools'] instanceof ConsoleTools ? $options['ConsoleTools'] : new ConsoleTools());
        $this->nzb = ($options['NZB'] instanceof NZB ? $options['NZB'] : new NZB());
        $this->releaseCleaning = ($options['ReleaseCleaning'] instanceof ReleaseCleaning ? $options['ReleaseCleaning'] : new ReleaseCleaning());
        $this->releases = ($options['Releases'] instanceof Releases ? $options['Releases'] : new Releases(['Groups' => null]));
        $this->releaseImage = ($options['ReleaseImage'] instanceof ReleaseImage ? $options['ReleaseImage'] : new ReleaseImage());
        $this->colorCli = new ColorCLI();

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
            $this->colorCli->error(PHP_EOL.'You have an invalid setting for completion. It cannot be higher than 100.');
        }
        $this->collectionTimeout = (int) Settings::settingValue('indexer.processing.collection_timeout');
    }

    /**
     * Main method for creating releases/NZB files from collections.
     *
     * @param int              $categorize
     * @param int              $postProcess
     * @param string           $groupName (optional)
     * @param \Blacklight\NNTP $nntp
     * @param bool             $echooutput
     *
     * @return int
     * @throws \Throwable
     */
    public function processReleases($categorize, $postProcess, $groupName, &$nntp, $echooutput): int
    {
        $this->echoCLI = ($echooutput && config('nntmux.echocli'));
        $groupID = '';

        if (! empty($groupName) && $groupName !== 'mgr') {
            $groupInfo = Group::getByName($groupName);
            if ($groupInfo !== null) {
                $groupID = $groupInfo['id'];
            }
        }

        if ($this->echoCLI) {
            $this->colorCli->header('Starting release update process ('.now()->format('Y-m-d H:i:s').')');
        }

        if (! file_exists(Settings::settingValue('..nzbpath'))) {
            if ($this->echoCLI) {
                $this->colorCli->error('Bad or missing nzb directory - '.Settings::settingValue('..nzbpath'));
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

        // Only run if non-mgr as mgr is not specific to group
        if ($groupName !== 'mgr') {
            $this->deletedReleasesByGroup($groupID);
            $this->deleteReleases();
        }

        return $totalReleasesAdded;
    }

    /**
     * Return all releases to other->misc category.
     *
     * @param string $where Optional "where" query parameter.
     *
     * @void
     */
    public function resetCategorize($where = ''): void
    {
        DB::update(
            sprintf('UPDATE releases SET categories_id = %d, iscategorized = 0 %s', Category::OTHER_MISC, $where)
        );
    }

    /**
     * Categorizes releases.
     *
     * @param string $type  name or searchname | Categorize using the search name or subject.
     * @param string $where Optional "where" query parameter.
     *
     * @return int Quantity of categorized releases.
     * @throws \Exception
     */
    public function categorizeRelease($type, $where = ''): int
    {
        $cat = new Categorize();
        $categorized = $total = 0;
        $releases = Release::fromQuery(
            sprintf(
                '
				SELECT id, fromname, %s, groups_id
				FROM releases %s',
                $type,
                $where
            )
        );
        if (\count($releases) > 0) {
            $total = \count($releases);
            foreach ($releases as $release) {
                $catId = $cat->determineCategory($release->groups_id, $release->{$type}, $release->fromname);
                Release::query()->where('id', $release->id)->update(['categories_id' => $catId['categories_id'], 'iscategorized' => 1]);
                try {
                    $taggedRelease = Release::find($release->id);
                    $taggedRelease->retag($catId['tags']);
                } catch (\Throwable $e) {
                    //Just pass this part, tag is not created for some reason, exception is thrown and blocks release creation
                }
                $categorized++;
                if ($this->echoCLI) {
                    $this->consoleTools->overWritePrimary(
                        'Categorizing: '.$this->consoleTools->percentString($categorized, $total)
                    );
                }
            }
        }
        if ($this->echoCLI !== false && $categorized > 0) {
            echo PHP_EOL;
        }

        return $categorized;
    }

    /**
     * @param $groupID
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function processIncompleteCollections($groupID): void
    {
        $startTime = now();

        if ($this->echoCLI) {
            $this->colorCli->header('Process Releases -> Attempting to find complete collections.');
        }

        $where = (! empty($groupID) ? ' AND c.groups_id = '.$groupID.' ' : ' ');

        $this->processStuckCollections($where);
        $this->collectionFileCheckStage1($where);
        $this->collectionFileCheckStage2($where);
        $this->collectionFileCheckStage3($where);
        $this->collectionFileCheckStage4($where);
        $this->collectionFileCheckStage5($where);
        $this->collectionFileCheckStage6($where);

        if ($this->echoCLI) {
            $count = DB::selectOne(
                sprintf(
                    '
					SELECT COUNT(c.id) AS complete
					FROM collections c
					WHERE c.filecheck = %d %s',
                    self::COLLFC_COMPPART,
                    $where
                )
            );

            $totalTime = now()->diffInSeconds($startTime);

            $this->colorCli->primary(
                    ($count === null ? 0 : $count->complete).' collections were found to be complete. Time: '.
                    $totalTime.str_plural(' second', $totalTime),
                true
                );
        }
    }

    /**
     * @param $groupID
     *
     * @throws \Exception
     */
    public function processCollectionSizes($groupID): void
    {
        $startTime = now();

        if ($this->echoCLI) {
            $this->colorCli->header('Process Releases -> Calculating collection sizes (in bytes).');
        }
        // Get the total size in bytes of the collection for collections where filecheck = 2.
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
            $this->colorCli->primary(
                    $checked.' collections set to filecheck = 3(size calculated)',
                true
                );
            $totalTime = now()->diffInSeconds($startTime);
            $this->colorCli->primary($totalTime.str_plural(' second', $totalTime), true);
        }
    }

    /**
     * @param $groupID
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function deleteUnwantedCollections($groupID): void
    {
        $startTime = now();

        if ($this->echoCLI) {
            $this->colorCli->header(
                    'Process Releases -> Delete collections smaller/larger than minimum size/file count from group/site setting.'
                );
        }

        $groupID === '' ? $groupIDs = Group::getActiveIDs() : $groupIDs = [['id' => $groupID]];

        $minSizeDeleted = $maxSizeDeleted = $minFilesDeleted = 0;

        $maxSizeSetting = Settings::settingValue('.release.maxsizetoformrelease');
        $minSizeSetting = Settings::settingValue('.release.minsizetoformrelease');
        $minFilesSetting = Settings::settingValue('.release.minfilestoformrelease');

        foreach ($groupIDs as $grpID) {
            $groupMinSizeSetting = $groupMinFilesSetting = 0;

            $groupMinimums = Group::getGroupByID($grpID['id']);
            if ($groupMinimums !== null) {
                if (! empty($groupMinimums['minsizetoformrelease']) && $groupMinimums['minsizetoformrelease'] > 0) {
                    $groupMinSizeSetting = (int) $groupMinimums['minsizetoformrelease'];
                }
                if (! empty($groupMinimums['minfilestoformrelease']) && $groupMinimums['minfilestoformrelease'] > 0) {
                    $groupMinFilesSetting = (int) $groupMinimums['minfilestoformrelease'];
                }
            }

            if (DB::selectOne(
                    sprintf(
                        '
						SELECT SQL_NO_CACHE id
						FROM collections c
						WHERE c.filecheck = %d
						AND c.filesize > 0',
                        self::COLLFC_SIZED
                    )
                ) !== null
            ) {
                $deleteQuery = DB::transaction(function () use ($minSizeSetting, $groupMinSizeSetting) {
                    DB::delete(
                        sprintf(
                            '
						DELETE c FROM collections c
						WHERE c.filecheck = %d
						AND c.filesize > 0
						AND GREATEST(%d, %d) > 0
						AND c.filesize < GREATEST(%d, %d)',
                            self::COLLFC_SIZED,
                            $groupMinSizeSetting,
                            $minSizeSetting,
                            $groupMinSizeSetting,
                            $minSizeSetting
                        )
                    );
                }, 3);

                if ($deleteQuery > 0) {
                    $minSizeDeleted += $deleteQuery;
                }

                if ($maxSizeSetting > 0) {
                    $deleteQuery = DB::transaction(function () use ($maxSizeSetting) {
                        DB::delete(
                            sprintf(
                                '
							DELETE c FROM collections c
							WHERE c.filecheck = %d
							AND c.filesize > %d',
                                self::COLLFC_SIZED,
                                $maxSizeSetting
                            )
                        );
                    }, 3);

                    if ($deleteQuery > 0) {
                        $maxSizeDeleted += $deleteQuery;
                    }
                }

                if ($minFilesSetting > 0 || $groupMinFilesSetting > 0) {
                    $deleteQuery = DB::transaction(function () use ($minFilesSetting, $groupMinFilesSetting) {
                        DB::delete(
                            sprintf(
                                '
						DELETE c FROM collections c
						WHERE c.filecheck = %d
						AND GREATEST(%d, %d) > 0
						AND c.totalfiles < GREATEST(%d, %d)',
                                self::COLLFC_SIZED,
                                $groupMinFilesSetting,
                                $minFilesSetting,
                                $groupMinFilesSetting,
                                $minFilesSetting
                            )
                        );
                    }, 3);

                    if ($deleteQuery > 0) {
                        $minFilesDeleted += $deleteQuery;
                    }
                }
            }
        }

        $totalTime = now()->diffInSeconds($startTime);

        if ($this->echoCLI) {
            $this->colorCli->primary(
                    'Deleted '.($minSizeDeleted + $maxSizeDeleted + $minFilesDeleted).' collections: '.PHP_EOL.
                    $minSizeDeleted.' smaller than, '.
                    $maxSizeDeleted.' bigger than, '.
                    $minFilesDeleted.' with less files than site/group settings in: '.
                    $totalTime.str_plural(' second', $totalTime),
                true
                );
        }
    }


    /**
     * @param int|string $groupID (optional)
     *
     * @return array
     * @throws \Throwable
     */
    public function createReleases($groupID): array
    {
        $startTime = now();

        $categorize = new Categorize();
        $returnCount = $duplicate = 0;

        if ($this->echoCLI) {
            $this->colorCli->header('Process Releases -> Create releases from complete collections.');
        }

        $collections = DB::select(
            sprintf(
                '
				SELECT SQL_NO_CACHE c.*, g.name AS gname
				FROM collections c
				INNER JOIN groups g ON c.groups_id = g.id
				WHERE %s c.filecheck = %d
				AND c.filesize > 0
				LIMIT %d',
                (! empty($groupID) ? ' c.groups_id = '.$groupID.' AND ' : ' '),
                self::COLLFC_SIZED,
                $this->releaseCreationLimit
            )
        );

        if ($this->echoCLI && \count($collections) > 0) {
            $this->colorCli->primary(\count($collections).' Collections ready to be converted to releases.', true);
        }

        foreach ($collections as $collection) {
            $cleanRelName = utf8_encode(str_replace(['#', '@', '$', '%', '^', '§', '¨', '©', 'Ö'], '', $collection->subject));
            $fromName = utf8_encode(
                    trim($collection->fromname, "'")
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
                    $properName = $cleanedName['properlynamed'];
                    $preID = $cleanedName['predb'] ?? false;
                    $cleanedName = $cleanedName['cleansubject'];
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
                            'searchname' => ! empty($cleanedName) ? utf8_encode($cleanedName) : $cleanRelName,
                            'totalpart' => $collection->totalfiles,
                            'groups_id' => $collection->groups_id,
                            'guid' => createGUID(),
                            'postdate' => $collection->date,
                            'fromname' => $fromName,
                            'size' => $collection->filesize,
                            'categories_id' => $determinedCategory['categories_id'],
                            'isrenamed' => $properName === true ? 1 : 0,
                            'predb_id' => $preID === false ? 0 : $preID,
                            'nzbstatus' => NZB::NZB_NONE,
                        ]
                    );
                try {
                    $release = Release::find($releaseID);
                    $release->retag($determinedCategory['tags']);
                } catch (\Throwable $e) {
                    //Just pass this part, tag is not created for some reason, exception is thrown and blocks release creation
                }

                if ($releaseID !== null) {
                    // Update collections table to say we inserted the release.
                    DB::update(
                            sprintf(
                                '
								UPDATE collections
								SET filecheck = %d, releases_id = %d
								WHERE id = %d',
                                self::COLLFC_INSERTED,
                                $releaseID,
                                $collection->id
                            )
                        );

                    // Add the id of regex that matched the collection and release name to release_regexes table
                    ReleaseRegex::insertIgnore([
                            'releases_id'            => $releaseID,
                            'collection_regex_id'    => $collection->collection_regexes_id,
                            'naming_regex_id'        => $cleanedName['id'] ?? 0,
                        ]);

                    if (preg_match_all('#(\S+):\S+#', $collection->xref, $matches)) {
                        foreach ($matches[1] as $grp) {
                            //check if the group name is in a valid format
                            $grpTmp = Group::isValidGroup($grp);
                            if ($grpTmp !== false) {
                                //check if the group already exists in database
                                $xrefGrpID = Group::getIDByName($grpTmp);
                                if ($xrefGrpID === '') {
                                    $xrefGrpID = Group::addGroup(
                                            [
                                                'name'                  => $grpTmp,
                                                'description'           => 'Added by Release processing',
                                                'backfill_target'       => 1,
                                                'first_record'          => 0,
                                                'last_record'           => 0,
                                                'active'                => 0,
                                                'backfill'              => 0,
                                                'minfilestoformrelease' => '',
                                                'minsizetoformrelease'  => '',
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
                                                'groups_id'   => $xrefGrpID,
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
                DB::transaction(function () use ($collection) {
                    DB::delete(
                        sprintf(
                            '
							DELETE c
							FROM collections c
							WHERE c.collectionhash = %s',
                            escapeString($collection->collectionhash)
                        )
                    );
                }, 3);

                $duplicate++;
            }
        }

        $totalTime = now()->diffInSeconds($startTime);

        if ($this->echoCLI) {
            $this->colorCli->primary(
                    PHP_EOL.
                    number_format($returnCount).
                    ' Releases added and '.
                    number_format($duplicate).
                    ' duplicate collections deleted in '.
                    $totalTime.str_plural(' second', $totalTime),
                true
                );
        }

        return ['added' => $returnCount, 'dupes' => $duplicate];
    }

    /**
     * Create NZB files from complete releases.
     *
     * @param int|string $groupID (optional)
     *
     * @return int
     * @throws \Throwable
     */
    public function createNZBs($groupID): int
    {
        $startTime = now();

        if ($this->echoCLI) {
            $this->colorCli->header('Process Releases -> Create the NZB, delete collections/binaries/parts.');
        }

        $releases = Release::fromQuery(
            sprintf(
                "
				SELECT SQL_NO_CACHE
					CONCAT(COALESCE(cp.title,'') , CASE WHEN cp.title IS NULL THEN '' ELSE ' > ' END , c.title) AS title,
					r.name, r.id, r.guid
				FROM releases r
				INNER JOIN categories c ON r.categories_id = c.id
				INNER JOIN categories cp ON cp.id = c.parentid
				WHERE %s nzbstatus = 0",
                ! empty($groupID) ? ' r.groups_id = '.$groupID.' AND ' : ' '
            )
        );

        $nzbCount = 0;

        if (\count($releases) > 0) {
            $total = \count($releases);
            // Init vars for writing the NZB's.
            $this->nzb->initiateForWrite($groupID);
            foreach ($releases as $release) {
                if ($this->nzb->writeNzbForReleaseId($release->id, $release->guid, $release->name, $release->title) === true) {
                    $nzbCount++;
                    if ($this->echoCLI) {
                        echo "Creating NZBs and deleting Collections: $nzbCount/$total.\r";
                    }
                }
            }
        }

        $totalTime = now()->diffInSeconds($startTime);

        if ($this->echoCLI) {
            $this->colorCli->primary(
                    number_format($nzbCount).' NZBs created/Collections deleted in '.
                    $totalTime.str_plural(' second', $totalTime).PHP_EOL.
                    'Total time: '.$totalTime.str_plural(' second', $totalTime),
                true
            );
        }

        return $nzbCount;
    }

    /**
     * Categorize releases.
     *
     * @param int        $categorize
     * @param int|string $groupID (optional)
     *
     * @void
     * @throws \Exception
     */
    public function categorizeReleases($categorize, $groupID = ''): void
    {
        $startTime = now();
        if ($this->echoCLI) {
            $this->colorCli->header('Process Releases -> Categorize releases.');
        }
        switch ((int) $categorize) {
            case 2:
                $type = 'searchname';
                break;
            case 1:
            default:

                $type = 'name';
                break;
        }
        $this->categorizeRelease(
            $type,
            (! empty($groupID)
                ? 'WHERE categories_id = '.Category::OTHER_MISC.' AND iscategorized = 0 AND groups_id = '.$groupID
                : 'WHERE categories_id = '.Category::OTHER_MISC.' AND iscategorized = 0')
        );

        $totalTime = now()->diffInSeconds($startTime);

        if ($this->echoCLI) {
            $this->colorCli->primary($totalTime.str_plural(' second', $totalTime));
        }
    }

    /**
     * Post-process releases.
     *
     * @param int  $postProcess
     * @param NNTP $nntp
     *
     * @void
     * @throws \Exception
     */
    public function postProcessReleases($postProcess, &$nntp): void
    {
        if ((int) $postProcess === 1) {
            (new PostProcess(['Echo' => $this->echoCLI]))->processAll($nntp);
        } elseif ($this->echoCLI) {
            $this->colorCli->info(
                    'Post-processing is not running inside the Process Releases class.'.PHP_EOL.
                    'If you are using tmux or screen they might have their own scripts running Post-processing.'
                );
        }
    }

    /**
     * @param $groupID
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function deleteCollections($groupID): void
    {
        $startTime = now();

        $deletedCount = 0;

        // CBP older than retention.
        if ($this->echoCLI) {
            echo
                $this->colorCli->header('Process Releases -> Delete finished collections.'.PHP_EOL).
                $this->colorCli->primary(sprintf(
                    'Deleting collections/binaries/parts older than %d hours.',
                    Settings::settingValue('..partretentionhours')
                ), true);
        }

        $deleted = 0;
        $deleteQuery = DB::transaction(function () {
            DB::delete(
                sprintf(
                    '
				DELETE c
				FROM collections c
				WHERE (c.dateadded < NOW() - INTERVAL %d HOUR)',
                    Settings::settingValue('..partretentionhours')
                )
            );
        }, 3);

        if ($deleteQuery > 0) {
            $deleted = $deleteQuery;
            $deletedCount += $deleted;
        }

        $firstQuery = $fourthQuery = now();

        $totalTime = $firstQuery->diffInSeconds($startTime);

        if ($this->echoCLI) {
            $this->colorCli->primary(
                'Finished deleting '.$deleted.' old collections/binaries/parts in '.
                $totalTime.str_plural(' second', $totalTime),
                true
            );
        }

        // Cleanup orphaned collections, binaries and parts
        // this really shouldn't happen, but just incase - so we only run 1/200 of the time
        if (random_int(0, 200) <= 1) {
            // CBP collection orphaned with no binaries or parts.
            if ($this->echoCLI) {
                echo
                    $this->colorCli->header('Process Releases -> Remove CBP orphans.'.PHP_EOL).
                    $this->colorCli->primary('Deleting orphaned collections.');
            }

            $deleted = 0;
            $deleteQuery = DB::transaction(function () {
                DB::delete(
                    '
					DELETE c, b, p
					FROM collections c
					LEFT JOIN binaries b ON c.id = b.collections_id
					LEFT JOIN parts p ON b.id = p.binaries_id
					WHERE (b.id IS NULL OR p.binaries_id IS NULL)'
                );
            }, 3);

            if ($deleteQuery > 0) {
                $deleted = $deleteQuery;
                $deletedCount += $deleted;
            }

            $secondQuery = now();
            $totalTime = $secondQuery->diffInSeconds($firstQuery);

            if ($this->echoCLI) {
                $this->colorCli->primary(
                    'Finished deleting '.$deleted.' orphaned collections in '.
                    $totalTime.str_plural(' second', $totalTime),
                    true
                );
            }

            // orphaned binaries - binaries with no parts or binaries with no collection
            // Don't delete currently inserting binaries by checking the max id.
            if ($this->echoCLI) {
                $this->colorCli->primary('Deleting orphaned binaries/parts with no collection.', true);
            }

            $deleted = 0;
            $deleteQuery = DB::transaction(function () {
                DB::delete(
                    sprintf(
                        'DELETE b, p FROM binaries b
					LEFT JOIN parts p ON b.id = p.binaries_id
					LEFT JOIN collections c ON b.collections_id = c.id
					WHERE (p.binaries_id IS NULL OR c.id IS NULL)
					AND b.id < %d',
                        $this->maxQueryFormulator('binaries', 20000)
                    )
                );
            }, 3);

            if ($deleteQuery > 0) {
                $deleted = $deleteQuery;
                $deletedCount += $deleted;
            }

            $thirdQuery = now();

            $totalTime = $thirdQuery->diffInSeconds($secondQuery);

            if ($this->echoCLI) {
                $this->colorCli->primary(
                    'Finished deleting '.$deleted.' binaries with no collections or parts in '.$totalTime.str_plural(' second', $totalTime),
                    true
                );
            }

            // orphaned parts - parts with no binary
            // Don't delete currently inserting parts by checking the max id.
            if ($this->echoCLI) {
                $this->colorCli->primary('Deleting orphaned parts with no binaries.', true);
            }
            $deleted = 0;
            $deleteQuery = DB::transaction(function () {
                DB::delete(
                    sprintf(
                        '
					DELETE p
					FROM parts p
					LEFT JOIN binaries b ON p.binaries_id = b.id
					WHERE b.id IS NULL
					AND p.binaries_id < %d',
                        $this->maxQueryFormulator('binaries', 20000)
                    )
                );
            }, 3);

            if ($deleteQuery > 0) {
                $deleted = $deleteQuery;
                $deletedCount += $deleted;
            }

            $fourthQuery = now();

            $totalTime = $fourthQuery->diffInSeconds($thirdQuery);

            if ($this->echoCLI) {
                $this->colorCli->primary(
                    'Finished deleting '.$deleted.' parts with no binaries in '.
                    $totalTime.str_plural(' second', $totalTime),
                    true
                );
            }
        } // done cleaning up Binaries/Parts orphans

        if ($this->echoCLI) {
            $this->colorCli->primary(
                'Deleting collections that were missed after NZB creation.',
                true
            );
        }

        $deleted = 0;
        // Collections that were missing on NZB creation.
        $collections = DB::select(
                '
				SELECT SQL_NO_CACHE c.id
				FROM collections c
				INNER JOIN releases r ON r.id = c.releases_id
				WHERE r.nzbstatus = 1'
        );

        foreach ($collections as $collection) {
            $deleted++;
            DB::transaction(function () use ($collection) {
                DB::delete(
                    sprintf(
                        '
						DELETE c
						FROM collections c
						WHERE c.id = %d',
                        $collection->id
                    )
                );
            }, 3);
        }
        $deletedCount += $deleted;

        $colDelTime = now()->diffInSeconds($fourthQuery);
        $totalTime = $fourthQuery->diffInSeconds($startTime);

        if ($this->echoCLI) {
            $this->colorCli->primary(
                    'Finished deleting '.$deleted.' collections missed after NZB creation in '.
                    $colDelTime.str_plural(' second', $colDelTime).PHP_EOL.
                    'Removed '.
                    number_format($deletedCount).
                    ' parts/binaries/collection rows in '.
                    $totalTime.str_plural(' second', $totalTime),
                true
                );
        }
    }

    /**
     * Delete unwanted releases based on admin settings.
     * This deletes releases based on group.
     *
     * @param int|string $groupID (optional)
     *
     * @void
     * @throws \Exception
     */
    public function deletedReleasesByGroup($groupID = ''): void
    {
        $startTime = now();
        $minSizeDeleted = $maxSizeDeleted = $minFilesDeleted = 0;

        if ($this->echoCLI) {
            $this->colorCli->header('Process Releases -> Delete releases smaller/larger than minimum size/file count from group/site setting.');
        }

        $groupID === '' ? $groupIDs = Group::getActiveIDs() : $groupIDs = [['id' => $groupID]];

        $maxSizeSetting = Settings::settingValue('.release.maxsizetoformrelease');
        $minSizeSetting = Settings::settingValue('.release.minsizetoformrelease');
        $minFilesSetting = Settings::settingValue('.release.minfilestoformrelease');

        foreach ($groupIDs as $grpID) {
            $releases = Release::fromQuery(
                sprintf(
                    '
					SELECT SQL_NO_CACHE r.guid, r.id
					FROM releases r
					INNER JOIN groups g ON g.id = r.groups_id
					WHERE r.groups_id = %d
					AND greatest(IFNULL(g.minsizetoformrelease, 0), %d) > 0
					AND r.size < greatest(IFNULL(g.minsizetoformrelease, 0), %d)',
                    $grpID['id'],
                    $minSizeSetting,
                    $minSizeSetting
                )
            );
            foreach ($releases as $release) {
                $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                $minSizeDeleted++;
            }

            if ($maxSizeSetting > 0) {
                $releases = Release::fromQuery(
                    sprintf(
                        '
						SELECT SQL_NO_CACHE id, guid
						FROM releases
						WHERE groups_id = %d
						AND size > %d',
                        $grpID['id'],
                        $maxSizeSetting
                    )
                );
                foreach ($releases as $release) {
                    $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                    $maxSizeDeleted++;
                }
            }
            if ($minFilesSetting > 0) {
                $releases = Release::fromQuery(
                     sprintf(
                         '
				SELECT SQL_NO_CACHE r.id, r.guid
				FROM releases r
				INNER JOIN groups g ON g.id = r.groups_id
				WHERE r.groups_id = %d
				AND greatest(IFNULL(g.minfilestoformrelease, 0), %d) > 0
				AND r.totalpart < greatest(IFNULL(g.minfilestoformrelease, 0), %d)',
                         $grpID['id'],
                         $minFilesSetting,
                         $minFilesSetting
                     )
                 );
                foreach ($releases as $release) {
                    $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                    $minFilesDeleted++;
                }
            }
        }

        $totalTime = now()->diffInSeconds($startTime);

        if ($this->echoCLI) {
            $this->colorCli->primary(
                    'Deleted '.($minSizeDeleted + $maxSizeDeleted + $minFilesDeleted).
                    ' releases: '.PHP_EOL.
                    $minSizeDeleted.' smaller than, '.$maxSizeDeleted.' bigger than, '.$minFilesDeleted.
                    ' with less files than site/groups setting in: '.
                    $totalTime.str_plural(' second', $totalTime),
                true
                );
        }
    }

    /**
     * Delete releases using admin settings.
     * This deletes releases, regardless of group.
     *
     * @void
     * @throws \Exception
     */
    public function deleteReleases(): void
    {
        $startTime = now();
        $genres = new Genres();
        $passwordDeleted = $duplicateDeleted = $retentionDeleted = $completionDeleted = $disabledCategoryDeleted = 0;
        $disabledGenreDeleted = $miscRetentionDeleted = $miscHashedDeleted = $categoryMinSizeDeleted = 0;

        // Delete old releases and finished collections.
        if ($this->echoCLI) {
            $this->colorCli->header('Process Releases -> Delete old releases and passworded releases.');
        }

        // Releases past retention.
        if ((int) Settings::settingValue('..releaseretentiondays') !== 0) {
            $releases = Release::fromQuery(
                sprintf(
                    'SELECT id, guid FROM releases WHERE postdate < (NOW() - INTERVAL %d DAY)',
                    (int) Settings::settingValue('..releaseretentiondays')
                )
            );
            foreach ($releases as $release) {
                $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                $retentionDeleted++;
            }
        }

        // Passworded releases.
        if ((int) Settings::settingValue('..deletepasswordedrelease') === 1) {
            $releases = Release::fromQuery(
                sprintf(
                    'SELECT id, guid FROM releases WHERE passwordstatus = %d',
                    Releases::PASSWD_RAR
                )
            );
            foreach ($releases as $release) {
                $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                $passwordDeleted++;
            }
        }

        // Possibly passworded releases.
        if ((int) Settings::settingValue('..deletepossiblerelease') === 1) {
            $releases = Release::fromQuery(
                sprintf(
                    'SELECT id, guid FROM releases WHERE passwordstatus = %d',
                    Releases::PASSWD_POTENTIAL
                )
            );
            foreach ($releases as $release) {
                $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                $passwordDeleted++;
            }
        }

        if ((int) $this->crossPostTime !== 0) {
            // Crossposted releases.
            $releases = Release::fromQuery(
                sprintf(
                    'SELECT id, guid FROM releases WHERE adddate > (NOW() - INTERVAL %d HOUR) GROUP BY name HAVING COUNT(name) > 1',
                    $this->crossPostTime
                )
            );
            foreach ($releases as $release) {
                $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                $duplicateDeleted++;
            }
        }

        if ($this->completion > 0) {
            $releases = Release::fromQuery(
                sprintf('SELECT id, guid FROM releases WHERE completion < %d AND completion > 0', $this->completion)
            );
            foreach ($releases as $release) {
                $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                $completionDeleted++;
            }
        }

        // Disabled categories.
        $disabledCategories = Category::getDisabledIDs();
        if (\count($disabledCategories) > 0) {
            foreach ($disabledCategories as $disabledCategory) {
                $releases = Release::fromQuery(
                    sprintf('SELECT id, guid FROM releases WHERE categories_id = %d', (int) $disabledCategory['id'])
                );
                foreach ($releases as $release) {
                    $disabledCategoryDeleted++;
                    $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                }
            }
        }

        // Delete smaller than category minimum sizes.
        $categories = Category::fromQuery(
            '
			SELECT SQL_NO_CACHE c.id AS id,
			CASE WHEN c.minsizetoformrelease = 0 THEN cp.minsizetoformrelease ELSE c.minsizetoformrelease END AS minsize
			FROM categories c
			INNER JOIN categories cp ON cp.id = c.parentid
			WHERE c.parentid IS NOT NULL'
        );

        foreach ($categories as $category) {
            if ((int) $category->minsize > 0) {
                $releases = Release::fromQuery(
                        sprintf(
                            '
							SELECT id, guid
							FROM releases
							WHERE categories_id = %d
							AND size < %d
							LIMIT 1000',
                            (int) $category->id,
                            (int) $category->minsize
                        )
                    );
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
                $releases = Release::fromQuery(
                    sprintf(
                        '
						SELECT id, guid
						FROM releases r
						INNER JOIN
						(
							SELECT id AS mid
							FROM musicinfo
							WHERE musicinfo.genre_id = %d
						) mi ON musicinfo_id = mi.mid',
                        (int) $genre['id']
                    )
                );
                foreach ($releases as $release) {
                    $disabledGenreDeleted++;
                    $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                }
            }
        }

        // Misc other.
        if (Settings::settingValue('..miscotherretentionhours') > 0) {
            $releases = Release::fromQuery(
                sprintf(
                    '
					SELECT SQL_NO_CACHE id, guid
					FROM releases
					WHERE categories_id = %d
					AND adddate <= NOW() - INTERVAL %d HOUR',
                    Category::OTHER_MISC,
                    (int) Settings::settingValue('..miscotherretentionhours')
                )
            );
            foreach ($releases as $release) {
                $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                $miscRetentionDeleted++;
            }
        }

        // Misc hashed.
        if ((int) Settings::settingValue('..mischashedretentionhours') > 0) {
            $releases = Release::fromQuery(
                sprintf(
                    '
					SELECT SQL_NO_CACHE id, guid
					FROM releases
					WHERE categories_id = %d
					AND adddate <= NOW() - INTERVAL %d HOUR',
                    Category::OTHER_HASHED,
                    (int) Settings::settingValue('..mischashedretentionhours')
                )
            );
            foreach ($releases as $release) {
                $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                $miscHashedDeleted++;
            }
        }

        if ($this->echoCLI) {
            $this->colorCli->primary(
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
                $this->colorCli->primary(
                        'Removed '.number_format($totalDeleted).' releases in '.
                        $totalTime.str_plural(' second', $totalTime),
                    true
                    );
            }
        }
    }

    /**
     * Formulate part of a query to prevent deletion of currently inserting parts / binaries / collections.
     *
     * @param string $groupName
     * @param int    $difference
     *
     * @return string
     */
    private function maxQueryFormulator($groupName, $difference): string
    {
        $maxID = DB::selectOne(
            sprintf(
                '
				SELECT IFNULL(MAX(id),0) AS max
				FROM %s',
                $groupName
            )
        );

        return empty($maxID->max) || $maxID->max < $difference ? 0 : $maxID->max - $difference;
    }

    /**
     * Look if we have all the files in a collection (which have the file count in the subject).
     * Set file check to complete.
     * This means the the binary table has the same count as the file count in the subject, but
     * the collection might not be complete yet since we might not have all the articles in the parts table.
     *
     * @param string $where
     *
     * @void
     */
    private function collectionFileCheckStage1(&$where): void
    {
        DB::update(
            sprintf(
                '
				UPDATE collections c
				INNER JOIN
				(
					SELECT c.id
					FROM collections c
					INNER JOIN binaries b ON b.collections_id = c.id
					WHERE c.totalfiles > 0
					AND c.filecheck = %d %s
					GROUP BY b.collections_id, c.totalfiles, c.id
					HAVING COUNT(b.id) IN (c.totalfiles, c.totalfiles + 1)
				) r ON c.id = r.id
				SET filecheck = %d',
                self::COLLFC_DEFAULT,
                $where,
                self::COLLFC_COMPCOLL
            )
        );
    }

    /**
     * The first query sets filecheck to COLLFC_ZEROPART if there's a file that starts with 0 (ex. [00/100]).
     * The second query sets filecheck to COLLFC_TEMPCOMP on everything left over, so anything that starts with 1 (ex. [01/100]).
     *
     * This is done because some collections start at 0 and some at 1, so if you were to assume the collection is complete
     * at 0 then you would never get a complete collection if it starts with 1 and if it starts, you can end up creating
     * a incomplete collection, since you assumed it was complete.
     *
     * @param string $where
     *
     * @void
     */
    private function collectionFileCheckStage2(&$where): void
    {
        DB::update(
            sprintf(
                '
				UPDATE collections c
				INNER JOIN
				(
					SELECT c.id
					FROM collections c
					INNER JOIN binaries b ON b.collections_id = c.id
					WHERE b.filenumber = 0
					AND c.totalfiles > 0
					AND c.filecheck = %d %s
					GROUP BY c.id
				) r ON c.id = r.id
				SET c.filecheck = %d',
                self::COLLFC_COMPCOLL,
                $where,
                self::COLLFC_ZEROPART
            )
        );
        DB::update(
            sprintf(
                '
				UPDATE collections c
				SET filecheck = %d
				WHERE filecheck = %d %s',
                self::COLLFC_TEMPCOMP,
                self::COLLFC_COMPCOLL,
                $where
            )
        );
    }

    /**
     * Check if the files (binaries table) in a complete collection has all the parts.
     * If we have all the parts, set binaries table partcheck to FILE_COMPLETE.
     *
     * @param string $where
     *
     * @void
     */
    private function collectionFileCheckStage3($where): void
    {
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
    }

    /**
     * Check if all files (binaries table) for a collection are complete (if they all have the "parts").
     * Set collections filecheck column to COLLFC_COMPPART.
     * This means the collection is complete.
     *
     * @param string $where
     *
     * @void
     */
    private function collectionFileCheckStage4(&$where): void
    {
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
    }

    /**
     * If not all files (binaries table) had their parts on the previous stage,
     * reset the collection filecheck column to COLLFC_COMPCOLL so we reprocess them next time.
     *
     * @param string $where
     *
     * @void
     */
    private function collectionFileCheckStage5(&$where): void
    {
        DB::update(
            sprintf(
                '
				UPDATE collections c
				SET filecheck = %d
				WHERE filecheck IN (%d, %d) %s',
                self::COLLFC_COMPCOLL,
                self::COLLFC_TEMPCOMP,
                self::COLLFC_ZEROPART,
                $where
            )
        );
    }

    /**
     * If a collection did not have the file count (ie: [00/12]) or the collection is incomplete after
     * $this->collectionDelayTime hours, set the collection to complete to create it into a release/nzb.
     *
     * @param string $where
     *
     * @void
     */
    private function collectionFileCheckStage6(&$where): void
    {
        DB::update(
            sprintf(
                "
				UPDATE collections c SET filecheck = %d, totalfiles = (SELECT COUNT(b.id) FROM binariess b WHERE b.collections_id = c.id)
				WHERE c.dateadded < NOW() - INTERVAL '%d' HOUR
				AND c.filecheck IN (%d, %d, 10) %s",
                self::COLLFC_COMPPART,
                $this->collectionDelayTime,
                self::COLLFC_DEFAULT,
                self::COLLFC_COMPCOLL,
                $where
            )
        );
    }

    /**
     * If a collection has been stuck for $this->collectionTimeout hours, delete it, it's bad.
     *
     * @param string $where
     *
     * @void
     * @throws \Exception
     * @throws \Throwable
     */
    private function processStuckCollections($where): void
    {
        $lastRun = Settings::settingValue('indexer.processing.last_run_time');

        $obj = DB::transaction(function () use ($where, $lastRun) {
            DB::delete(
                sprintf(
                    '
                DELETE c FROM collections c
                WHERE
                    c.added <
                    DATE_SUB(%s, INTERVAL %d HOUR)
                %s',
                    escapeString($lastRun),
                    $this->collectionTimeout,
                    $where
                )
            );
        }, 3);

        if ($this->echoCLI && $obj > 0) {
            $this->colorCli->primary('Deleted '.$obj.' broken/stuck collections.', true);
        }
    }
}
