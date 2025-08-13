<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Predb;
use App\Models\Release;
use App\Models\ReleaseRegex;
use App\Models\ReleasesGroups;
use App\Models\UsenetGroup;
use Blacklight\Categorize;
use Blacklight\ColorCLI;
use Blacklight\ReleaseCleaning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Blacklight\NZB;
use Blacklight\processing\ProcessReleases; // for constants

class ReleaseCreationService
{
    public function __construct(
        private readonly ColorCLI $colorCLI,
        private readonly ReleaseCleaning $releaseCleaning
    ) {
    }

    /**
     * Create releases from complete collections.
     *
     * @return array{added:int,dupes:int}
     */
    public function createReleases(int|string|null $groupID, int $limit, bool $echoCLI): array
    {
        $startTime = now()->toImmutable();
        $categorize = new Categorize();
        $returnCount = 0;
        $duplicate = 0;

        if ($echoCLI) {
            $this->colorCLI->header('Process Releases -> Create releases from complete collections.');
        }

        $collectionsQuery = Collection::query()
            ->where('collections.filecheck', ProcessReleases::COLLFC_SIZED)
            ->where('collections.filesize', '>', 0);
        if (! empty($groupID)) {
            $collectionsQuery->where('collections.groups_id', $groupID);
        }
        $collectionsQuery->select(['collections.*', 'usenet_groups.name as gname'])
            ->join('usenet_groups', 'usenet_groups.id', '=', 'collections.groups_id')
            ->limit($limit);
        $collections = $collectionsQuery->get();

        if ($echoCLI && $collections->count() > 0) {
            $this->colorCLI->primary(\count($collections).' Collections ready to be converted to releases.', true);
        }

        foreach ($collections as $collection) {
            $cleanRelName = mb_convert_encoding(str_replace(['#', '@', '$', '%', '^', '§', '¨', '©', 'Ö'], '', $collection->subject), 'UTF-8', mb_list_encodings());
            $fromName = mb_convert_encoding(trim($collection->fromname, "'"), 'UTF-8', mb_list_encodings());

            // Deduplicate by name, from, and ~size
            $dupeCheck = Release::query()
                ->where(['name' => $cleanRelName, 'fromname' => $fromName])
                ->whereBetween('size', [$collection->filesize * .99, $collection->filesize * 1.01])
                ->first(['id']);

            if ($dupeCheck === null) {
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
                    $preID = $cleanedMeta['predb'] ?? false;
                    $cleanedName = $cleanedMeta['cleansubject'] ?? $cleanRelName;
                } else {
                    $properName = true;
                    $preID = false;
                    $cleanedName = $cleanRelName;
                }

                if ($preID === false && $cleanedName !== '') {
                    $preMatch = Predb::matchPre($cleanedName);
                    if ($preMatch !== false) {
                        $cleanedName = $preMatch['title'];
                        $preID = $preMatch['predb_id'];
                        $properName = true;
                    }
                }

                $determinedCategory = $categorize->determineCategory($collection->groups_id, $cleanedName);

                $searchName = ! empty($cleanedName) ? mb_convert_encoding($cleanedName, 'UTF-8', mb_list_encodings()) : $cleanRelName;

                $releaseID = Release::insertRelease([
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
                    'predb_id' => $preID === false ? 0 : $preID,
                    'nzbstatus' => NZB::NZB_NONE,
                    'ishashed' => preg_match('/^[a-fA-F0-9]{32}\b|^[a-fA-F0-9]{40}\b|^[a-fA-F0-9]{64}\b|^[a-fA-F0-9]{96}\b|^[a-fA-F0-9]{128}\b/i', $searchName) ? 1 : 0,
                ]);

                if ($releaseID !== null) {
                    DB::transaction(static function () use ($collection, $releaseID) {
                        Collection::query()->where('id', $collection->id)->update([
                            'filecheck' => ProcessReleases::COLLFC_INSERTED,
                            'releases_id' => $releaseID,
                        ]);
                    }, 10);

                    ReleaseRegex::insertOrIgnore([
                        'releases_id' => $releaseID,
                        'collection_regex_id' => $collection->collection_regexes_id,
                        'naming_regex_id' => $namingRegexId,
                    ]);

                    if (preg_match_all('#(\S+):\S+#', $collection->xref, $hits)) {
                        foreach ($hits[1] as $grp) {
                            $grpTmp = UsenetGroup::isValidGroup($grp);
                            if ($grpTmp !== false) {
                                $xrefGrpID = UsenetGroup::getIDByName($grpTmp);
                                if ($xrefGrpID === '') {
                                    $xrefGrpID = UsenetGroup::addGroup([
                                        'name' => $grpTmp,
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

                                $relGroupsChk = ReleasesGroups::query()->where([
                                    ['releases_id', '=', $releaseID],
                                    ['groups_id', '=', $xrefGrpID],
                                ])->first();

                                if ($relGroupsChk === null) {
                                    ReleasesGroups::query()->insert([
                                        'releases_id' => $releaseID,
                                        'groups_id' => $xrefGrpID,
                                    ]);
                                }
                            }
                        }
                    }

                    $returnCount++;
                    if ($echoCLI) {
                        echo "Added $returnCount releases.\r";
                    }
                }
            } else {
                DB::transaction(static function () use ($collection) {
                    Collection::query()->where('collectionhash', $collection->collectionhash)->delete();
                }, 10);

                $duplicate++;
            }
        }

        $totalTime = now()->diffInSeconds($startTime, true);
        if ($echoCLI) {
            $this->colorCLI->primary(
                PHP_EOL.
                number_format($returnCount).
                ' Releases added and '.
                number_format($duplicate).
                ' duplicate collections deleted in '.
                $totalTime.\Illuminate\Support\Str::plural(' second', $totalTime),
                true
            );
        }

        return ['added' => $returnCount, 'dupes' => $duplicate];
    }
}

