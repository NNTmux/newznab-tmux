<?php

namespace Blacklight;

use App\Models\Release;
use App\Models\Settings;
use App\Models\UsenetGroup;
use App\Services\BlacklistService;
use App\Services\Categorization\CategorizationService;
use Blacklight\utility\Utility;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Class NZBImport.
 */
class NZBImport
{
    protected BlacklistService $blacklistService;

    protected ReleaseCleaning $releaseCleaner;

    protected \stdClass|bool $site;

    /**
     * @var int
     */
    protected mixed $crossPostt;

    protected CategorizationService $category;

    /**
     * List of all the group names/ids in the DB.
     */
    protected array $allGroups;

    /**
     * Was this run from the browser?
     */
    protected bool $browser;

    /**
     * Return value for browser.
     */
    protected string $retVal;

    /**
     * Guid of the current releases.
     */
    protected string $relGuid;

    /**
     * @var bool
     */
    public mixed $echoCLI;

    public NZB $nzb;

    /**
     * @var string the MD5 hash of the first segment Message-ID of the NZB
     */
    protected string $nzbGuid;

    protected ColorCLI $colorCli;

    public function __construct(array $options = [])
    {
        $this->echoCLI = config('nntmux.echocli');
        $this->blacklistService = new BlacklistService;
        $this->category = new CategorizationService();
        $this->nzb = new NZB;
        $this->releaseCleaner = new ReleaseCleaning;
        $this->colorCli = new ColorCLI;
        $this->crossPostt = Settings::settingValue('crossposttime') !== '' ? Settings::settingValue('crossposttime') : 2;

        // Set properties from options
        $this->browser = isset($options['Browser']) ? $options['Browser'] : '';
        $this->retVal = '';
    }

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function beginImport($filesToProcess, bool $useNzbName = false, bool $delete = false, bool $deleteFailed = false, int $source = 1): bool|string
    {
        // Get all the groups in the DB.
        if (! $this->getAllGroups()) {
            if ($this->browser) {
                return $this->retVal;
            }

            return false;
        }

        $start = now()->toImmutable()->format('Y-m-d H:i:s');
        $nzbsImported = $nzbsSkipped = 0;

        // Filter files to only process NZB files
        $nzbFiles = array_filter($filesToProcess, function ($file) {
            return Str::endsWith($file, '.nzb') || Str::endsWith($file, '.nzb.gz');
        });

        if (empty($nzbFiles)) {
            $this->echoOut('No NZB files found to process.');
            if ($this->browser) {
                return $this->retVal;
            }

            return false;
        }

        $totalFilesFiltered = count($filesToProcess) - count($nzbFiles);
        if ($totalFilesFiltered > 0) {
            $this->echoOut("Filtered out {$totalFilesFiltered} non-NZB files. Processing ".count($nzbFiles).' NZB files.');
        }

        // Loop over the NZB file names only.
        foreach ($nzbFiles as $nzbFile) {
            $this->nzbGuid = '';

            // Check if the file is really there.
            if (File::isFile($nzbFile)) {
                // Get the contents of the NZB file as a string.
                if (Str::endsWith($nzbFile, '.nzb.gz')) {
                    $nzbString = Utility::unzipGzipFile($nzbFile);
                } else {
                    $nzbString = File::get($nzbFile);
                }

                if ($nzbString === false) {
                    $this->echoOut('ERROR: Unable to read: '.$nzbFile);

                    if ($deleteFailed) {
                        File::delete($nzbFile);
                    }
                    $nzbsSkipped++;

                    continue;
                }

                // Load it as an XML object.
                $nzbXML = @simplexml_load_string($nzbString);
                if ($nzbXML === false || strtolower($nzbXML->getName()) !== 'nzb') {
                    $this->echoOut('ERROR: Unable to load NZB XML data: '.$nzbFile);

                    if ($deleteFailed) {
                        File::delete($nzbFile);
                    }
                    $nzbsSkipped++;

                    continue;
                }

                // Try to insert the NZB details into the DB.
                $nzbFileName = $useNzbName === true ? str_ireplace('.nzb', '', basename($nzbFile)) : '';
                try {
                    $inserted = $this->scanNZBFile($nzbXML, $nzbFileName, $source);
                } catch (\Exception $e) {
                    $this->echoOut('ERROR: Problem inserting: '.$nzbFile);
                    $inserted = false;
                }

                if ($inserted) {
                    // Try to copy the NZB to the NZB folder.
                    $path = $this->nzb->getNZBPath($this->relGuid, 0, true);

                    // Try to compress the NZB file in the NZB folder.
                    $fp = gzopen($path, 'w5');
                    gzwrite($fp, $nzbString);
                    gzclose($fp);

                    if (! File::isFile($path)) {
                        $this->echoOut('ERROR: Problem compressing NZB file to: '.$path);

                        // Remove the release.
                        Release::query()->where('guid', $this->relGuid)->delete();

                        if ($deleteFailed) {
                            File::delete($nzbFile);
                        }
                        $nzbsSkipped++;
                    } else {
                        $this->updateNzbGuid();
                        if ($delete) {
                            // Remove the nzb file.
                            File::delete($nzbFile);
                        }

                        $nzbsImported++;
                    }
                } else {
                    $this->echoOut('ERROR: Failed to insert NZB!');
                    if ($deleteFailed) {
                        File::delete($nzbFile);
                    }
                    $nzbsSkipped++;
                }
            } else {
                $this->echoOut('ERROR: Unable to fetch: '.$nzbFile);
                $nzbsSkipped++;
            }
        }
        $this->echoOut(
            'Proccessed '.
            $nzbsImported.
            ' NZBs in '.
            now()->diffInSeconds($start, true).' seconds, '.
            $nzbsSkipped.
            ' NZBs were skipped.'
        );

        if ($this->browser) {
            return $this->retVal;
        }

        return true;
    }

    /**
     * @throws \Exception
     */
    protected function scanNZBFile(&$nzbXML, $nzbFileName = ''): bool
    {
        $binary_names = [];
        $totalFiles = $totalSize = $groupID = 0;
        $isBlackListed = $groupName = $firstName = $posterName = $postDate = false;

        // Go through the NZB, get the details, look if it's blacklisted, look if we have the groups.
        foreach ($nzbXML->file as $file) {
            $binary_names[] = $file['subject'];
            $totalFiles++;
            $groupID = -1;

            // Get the nzb info.
            if ($firstName === false) {
                $firstName = (string) $file->attributes()->subject;
            }
            if ($posterName === false) {
                $posterName = (string) $file->attributes()->poster;
            }
            if ($postDate === false) {
                $postDate = Carbon::createFromTimestamp((string) $file->attributes()->date, date_default_timezone_get())->format('Y-m-d H:i:s');
            }

            // Make a fake message array to use to check the blacklist.
            $msg = ['Subject' => (string) $file->attributes()->subject, 'From' => (string) $file->attributes()->poster, 'Message-ID' => ''];

            // Get the group names, group_id, check if it's blacklisted.
            $groupArr = [];
            foreach ($file->groups->group as $group) {
                $group = (string) $group;

                // If group_id is -1 try to get a group_id.
                if ($groupID === -1) {
                    if (array_key_exists($group, $this->allGroups)) {
                        $groupID = $this->allGroups[$group];
                        if (! $groupName) {
                            $groupName = $group;
                        }
                    } else {
                        $group = UsenetGroup::isValidGroup($group);
                        if ($group !== false) {
                            $groupID = UsenetGroup::addGroup([
                                'name' => $group,
                                'description' => 'Added by NZBimport script.',
                                'backfill_target' => 1,
                                'minfilestoformrelease' => '',
                                'minsizetoformrelease' => '',
                                'first_record' => 0,
                                'last_record' => 0,
                                'active' => 0,
                                'backfill' => 0,
                            ]);
                            $this->allGroups[$group] = $groupID;

                            $this->echoOut("Adding missing group: ($group)");
                        }
                    }
                }
                // Add all the found groups to an array.
                $groupArr[] = $group;

                // Check if this NZB is blacklisted.
                if ($this->blacklistService->isBlackListed($msg, $group)) {
                    $isBlackListed = true;
                    break;
                }
            }

            // If we found a group and it's not blacklisted.
            if ($groupID !== -1 && ! $isBlackListed) {
                // Get the size of the release.
                if (\count($file->segments->segment) > 0) {
                    foreach ($file->segments->segment as $segment) {
                        $totalSize += (int) $segment->attributes()->bytes;
                    }
                }
            } else {
                if ($isBlackListed) {
                    $errorMessage = 'Subject is blacklisted: '.mb_convert_encoding(trim($firstName), 'UTF-8', mb_list_encodings());
                } else {
                    $errorMessage = 'No group found for '.$firstName.' (one of '.implode(', ', $groupArr).' are missing';
                }
                $this->echoOut($errorMessage);

                // Persist blacklist usage stats if we matched any rule during this NZB processing
                $this->blacklistService->updateBlacklistUsage($this->blacklistService->getAndClearIdsToUpdate());

                return false;
            }
        }

        // After scanning all files, persist any matched whitelist/blacklist usage
        $this->blacklistService->updateBlacklistUsage($this->blacklistService->getAndClearIdsToUpdate());

        // Sort values alphabetically but keep the keys intact
        if (\count($binary_names) > 0) {
            asort($binary_names);
            foreach ($nzbXML->file as $file) {
                if ($file['subject'] === $binary_names[0]) {
                    $this->nzbGuid = md5($file->segments->segment);
                    break;
                }
            }
        }

        // Try to insert the NZB details into the DB.
        return $this->insertNZB(
            [
                'subject' => $firstName,
                'useFName' => $nzbFileName,
                'postDate' => empty($postDate) ? now()->format('Y-m-d H:i:s') : $postDate,
                'from' => empty($posterName) ? '' : $posterName,
                'groups_id' => $groupID,
                'groupName' => $groupName,
                'totalFiles' => $totalFiles,
                'totalSize' => $totalSize,
            ]
        );
    }

    /**
     * Insert the NZB details into the database.
     *
     *
     * @throws \Exception
     */
    protected function insertNZB($nzbDetails): bool
    {
        // Make up a GUID for the release.
        $this->relGuid = Str::uuid()->toString();

        // Remove part count from subject.
        $partLess = preg_replace('/(\(\d+\/\d+\))*$/', 'yEnc', $nzbDetails['subject']);
        // Remove added yEnc from above and anything after.
        $subject = mb_convert_encoding(trim(preg_replace('/yEnc.*$/i', 'yEnc', $partLess)), 'UTF-8', mb_list_encodings());

        $renamed = 0;
        if ($nzbDetails['useFName'] !== '') {
            // If we are using the filename as the subject. We don't need to clean it.
            $cleanName = $nzbDetails['useFName'];
            $renamed = 1;
        } else {
            // Pass the subject through release cleaner to get a nicer name.
            $cleanName = $this->releaseCleaner->releaseCleaner($subject, $nzbDetails['from'], $nzbDetails['groupName']);
            if (isset($cleanName['properlynamed'])) {
                $cleanName = $cleanName['cleansubject'];
                $renamed = (isset($cleanName['properlynamed']) && $cleanName['properlynamed'] === true ? 1 : 0);
            }
        }

        $escapedSubject = $subject;
        $escapedFromName = $nzbDetails['from'];

        // Look for a duplicate on name, poster and size.
        $dupeCheck = Release::query()->where(['name' => $escapedSubject, 'fromname' => $escapedFromName])->whereBetween('size', [$nzbDetails['totalSize'] * 0.99, $nzbDetails['totalSize'] * 1.01])->first(['id']);

        if ($dupeCheck === null) {
            $escapedSearchName = $cleanName;
            $determinedCategory = $this->category->determineCategory($nzbDetails['groups_id'], $cleanName, $escapedFromName);
            // Insert the release into the DB.
            $relID = Release::insertRelease(
                [
                    'name' => $escapedSubject,
                    'searchname' => $escapedSearchName ?? $escapedSubject,
                    'totalpart' => $nzbDetails['totalFiles'],
                    'groups_id' => $nzbDetails['groups_id'],
                    'guid' => $this->relGuid,
                    'postdate' => $nzbDetails['postDate'],
                    'fromname' => $escapedFromName,
                    'size' => $nzbDetails['totalSize'],
                    'categories_id' => $determinedCategory['categories_id'],
                    'isrenamed' => $renamed,
                    'predb_id' => 0,
                    'nzbstatus' => NZB::NZB_ADDED,
                    'ishashed' => 0,
                ]
            );
        } else {
            $this->echoOut('This release is already in our DB so skipping: '.$subject);

            return false;
        }

        if ($relID === null) {
            $this->echoOut('ERROR: Problem inserting: '.$subject);

            return false;
        }

        return true;
    }

    /**
     * Get all groups in the DB.
     */
    protected function getAllGroups(): bool
    {
        $this->allGroups = [];
        $groups = UsenetGroup::query()->get(['id', 'name']);

        if ($groups instanceof \Traversable) {
            foreach ($groups as $group) {
                $this->allGroups[$group['name']] = $group['id'];
            }
        }

        if (\count($this->allGroups) === 0) {
            $this->echoOut('You have no groups in your database!');

            return false;
        }

        return true;
    }

    /**
     * Echo message to browser or CLI.
     */
    protected function echoOut(string $message): void
    {
        if ($this->browser) {
            $this->retVal .= $message.'<br />';
        } elseif ($this->echoCLI) {
            $this->colorCli->notice($message);
        }
    }

    /**
     * The function updates the NZB guid after there is no chance of deletion.
     */
    protected function updateNzbGuid(): void
    {
        try {
            Release::query()->where('guid', $this->relGuid)->update(['nzb_guid' => sodium_hex2bin($this->nzbGuid)]);
        } catch (\SodiumException $e) {
            $this->echoOut('ERROR: Problem updating nzb_guid for: '.$this->relGuid);
        }
    }
}
