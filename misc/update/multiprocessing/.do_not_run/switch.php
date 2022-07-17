<?php

if (! isset($argv[1])) {
    exit('This script is not intended to be run manually.'.PHP_EOL);
}

require_once dirname(__DIR__, 4).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\Settings;
use App\Models\UsenetGroup;
use Blacklight\Backfill;
use Blacklight\Binaries;
use Blacklight\Nfo;
use Blacklight\NNTP;
use Blacklight\processing\post\ProcessAdditional;
use Blacklight\processing\PostProcess;
use Blacklight\processing\ProcessReleases;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Are we coming from python or php ? $options[0] => (string): python|php
// The type of process we want to do: $options[1] => (string): releases
$options = explode('  ', $argv[1]);

switch ($options[1]) {

    // Runs backFill interval or all.
    // $options[2] => (string)group name, Name of group to work on.
    // $options[3] => (int)   backfill type from tmux settings. 1 = Backfill interval , 2 = Bakfill all
    case 'backfill':
        if (in_array((int) $options[3], [1, 2], false)) {
            $value = (int) Settings::settingValue('site.tmux.backfill_qty');
            if ($value !== null) {
                try {
                    $nntp = nntp();
                } catch (Throwable $e) {
                    Log::error($e->getTraceAsString());
                    echo $e->getMessage();
                }
                try {
                    (new Backfill())->backfillAllGroups($options[2], ($options[3] === 1 ? '' : $value['value']));
                } catch (Throwable $e) {
                    Log::error($e->getTraceAsString());
                    echo $e->getMessage();
                }
            }
        }
        break;

    /*  BackFill up to x number of articles for all groups.
     *
     * $options[2] => (string) Group name.
     * $options[3] => (int)    Quantity of articles to download.
     */
    case 'backfill_all_quantity':
        try {
            $nntp = nntp();
        } catch (Throwable $e) {
            Log::error($e->getTraceAsString());
            echo $e->getMessage();
        }
        try {
            (new Backfill())->backfillAllGroups($options[2], $options[3]);
        } catch (Throwable $e) {
            Log::error($e->getTraceAsString());
            echo $e->getMessage();
        }
        break;

    // BackFill a single group, 10000 parts.
    // $options[2] => (string)group name, Name of group to work on.
    case 'backfill_all_quick':
        try {
            $nntp = nntp();
        } catch (Throwable $e) {
            Log::error($e->getTraceAsString());
            echo $e->getMessage();
        }
        try {
            (new Backfill())->backfillAllGroups($options[2], 10000, 'normal');
        } catch (Throwable $e) {
            Log::error($e->getTraceAsString());
            echo $e->getMessage();
        }
        break;

    /* Get a range of article headers for a group.
     *
     * $options[2] => (string) backfill/binaries
     * $options[3] => (string) Group name.
     * $options[4] => (int)    First article number in range.
     * $options[5] => (int)    Last article number in range.
     * $options[6] => (int)    Number of threads.
     */
    case 'get_range':
        try {
            $nntp = nntp();
        } catch (Throwable $e) {
            echo $e->getMessage();
        }
        $groupMySQL = UsenetGroup::getByName($options[3])->toArray();
        try {
            if ($nntp::isError($nntp->selectGroup($groupMySQL['name'])) && $nntp::isError($nntp->dataError($nntp, $groupMySQL['name']))) {
                return;
            }
        } catch (Throwable $e) {
            Log::error($e->getTraceAsString());
            echo $e->getMessage();
        }
        try {
            $binaries = new Binaries(['NNTP' => $nntp, 'Groups' => null]);
        } catch (Throwable $e) {
            Log::error($e->getTraceAsString());
            echo $e->getMessage();
        }
        try {
            $return = $binaries->scan($groupMySQL, $options[4], $options[5], ((int) Settings::settingValue('..safepartrepair') === 1 ? 'update' : 'backfill'));
        } catch (Throwable $e) {
            Log::error($e->getTraceAsString());
            echo $e->getMessage();
        }
        if (empty($return)) {
            exit();
        }
        $columns = [];
        switch ($options[2]) {
            case 'binaries':
                if ($return['lastArticleNumber'] <= $groupMySQL['last_record']) {
                    exit();
                }
                $unixTime = is_numeric($return['lastArticleDate']) ? $return['lastArticleDate'] : strtotime($return['lastArticleDate']);
                $columns[1] = sprintf(
                    'last_record_postdate = FROM_UNIXTIME(%s)',
                    $unixTime
                );
                $columns[2] = sprintf('last_record = %s', $return['lastArticleNumber']);
                $query = sprintf(
                    'UPDATE usenet_groups SET %s, %s, last_updated = NOW() WHERE id = %d AND last_record < %s',
                    $columns[1],
                    $columns[2],
                    $groupMySQL['id'],
                    $return['lastArticleNumber']
                );
                break;
            case 'backfill':
                if ($return['firstArticleNumber'] >= $groupMySQL['first_record']) {
                    exit();
                }
                $unixTime = is_numeric($return['firstArticleDate']) ? $return['firstArticleDate'] : strtotime($return['firstArticleDate']);
                $columns[1] = sprintf(
                    'first_record_postdate = FROM_UNIXTIME(%s)',
                    $unixTime
                );
                $columns[2] = sprintf('first_record = %s', $return['firstArticleNumber']);
                $query = sprintf(
                    'UPDATE usenet_groups SET %s, %s, last_updated = NOW() WHERE id = %d AND first_record > %s',
                    $columns[1],
                    $columns[2],
                    $groupMySQL['id'],
                    $return['firstArticleNumber']
                );
                break;
            default:
                exit();
        }
        DB::update($query);
        break;

    /* Do part repair for a group.
     *
     * $options[2] => (string) Group name.
     */
    case 'part_repair':
        $groupMySQL = UsenetGroup::getByName($options[2])->toArray();
        try {
            $nntp = nntp();
        } catch (Throwable $e) {
            echo $e->getMessage();
        }
        // Select group, here, only once
        try {
            $data = $nntp->selectGroup($groupMySQL['name']);
        } catch (Throwable $e) {
            echo $e->getMessage();
        }
        try {
            if ($nntp::isError($data) && $nntp->dataError($nntp, $groupMySQL['name']) === false) {
                exit();
            }
        } catch (Throwable $e) {
            echo $e->getMessage();
        }
        try {
            (new Binaries(['NNTP' => $nntp]))->partRepair($groupMySQL);
        } catch (Throwable $e) {
            echo $e->getMessage();
        }
        break;

    // Process releases.
    // $options[2] => (string)groupCount, number of groups terminated by _ | (int)groupid, group to work on
    case 'releases':
        try {
            $releases = new ProcessReleases();
        } catch (Throwable $e) {
            echo $e->getMessage();
        }

        //Runs function that are per group
        if (is_numeric($options[2])) {
            try {
                processReleases($releases, $options[2]);
            } catch (Throwable $e) {
                echo $e->getMessage();
            }
        } else {
            try {
                processReleases($releases, '');
            } catch (Throwable $e) {
                echo $e->getMessage();
            }
            // Run functions that run on releases table after all others completed.
            $groupCount = rtrim($options[2], '_');
            if (! is_numeric($groupCount)) {
                $groupCount = 1;
            }
            try {
                $releases->deletedReleasesByGroup();
            } catch (Throwable $e) {
                echo $e->getMessage();
            }
            try {
                $releases->deleteReleases();
            } catch (Throwable $e) {
                echo $e->getMessage();
            }
            //$releases->processRequestIDs('', (5000 * $groupCount), true);
            //$releases->processRequestIDs('', (1000 * $groupCount), false);
            try {
                $releases->categorizeReleases(2);
            } catch (Throwable $e) {
                echo $e->getMessage();
            }
        }
        break;

    /* Update a single group's article headers.
     *
     * $options[2] => (string) Group name.
     */
    case 'update_group_headers':
        try {
            $nntp = nntp();
        } catch (Throwable $e) {
            echo $e->getMessage();
        }
        $groupMySQL = UsenetGroup::getByName($options[2])->toArray();
        try {
            (new Binaries(['NNTP' => $nntp]))->updateGroup($groupMySQL);
        } catch (Throwable $e) {
            echo $e->getMessage();
        }
        break;

    // Do a single group (update_binaries/backFill/update_releases/postprocess).
    // $options[2] => (int)groupid, group to work on
    case 'update_per_group':
        if (is_numeric($options[2])) {
            // Get the group info from MySQL.
            $groupMySQL = UsenetGroup::find($options[2])->toArray();

            if ($groupMySQL === null) {
                exit('ERROR: Group not found with id '.$options[2].PHP_EOL);
            }

            // Connect to NNTP.
            try {
                $nntp = nntp();
            } catch (Throwable $e) {
                echo $e->getMessage();
            }
            try {
                $backFill = new Backfill();
            } catch (Throwable $e) {
                echo $e->getMessage();
            }

            // Update the group for new binaries.
            try {
                (new Binaries())->updateGroup($groupMySQL);
            } catch (Throwable $e) {
                echo $e->getMessage();
            }

            // BackFill the group with 20k articles.
            try {
                $backFill->backfillAllGroups($groupMySQL['name'], 20000, 'normal');
            } catch (Throwable $e) {
                echo $e->getMessage();
            }

            // Create releases.
            try {
                processReleases(new ProcessReleases(), $options[2]);
            } catch (Throwable $e) {
                echo $e->getMessage();
            }

            // Post process the releases.
            try {
                (new ProcessAdditional(['Echo' => true, 'NNTP' => $nntp]))->start($options[2]);
            } catch (Throwable $e) {
                echo $e->getMessage();
            }
            try {
                (new Nfo())->processNfoFiles($nntp, $options[2], '', (int) Settings::settingValue('..lookupimdb'), (int) Settings::settingValue('..lookuptvrage'));
            } catch (Throwable $e) {
                echo $e->getMessage();
            }
        }
        break;

    // Post process additional and NFO.
    // $options[2] => (char)Letter or number a-f 0-9, first character of release guid.
    case 'pp_additional':
    case 'pp_nfo':
        if (charCheck($options[2])) {

            // Create the connection here and pass, this is for post processing, so check for alternate.
            try {
                $nntp = nntp(true);
            } catch (Throwable $e) {
                echo $e->getMessage();
            }

            if ($options[1] === 'pp_nfo') {
                try {
                    (new Nfo())->processNfoFiles($nntp, '', $options[2], (int) Settings::settingValue('..lookupimdb'), (int) Settings::settingValue('..lookuptvrage'));
                } catch (Throwable $e) {
                    echo $e->getMessage();
                }
            } else {
                try {
                    (new ProcessAdditional(['Echo' => true, 'NNTP' => $nntp]))->start('', $options[2]);
                } catch (Throwable $e) {
                    echo $e->getMessage();
                }
            }
        }
        break;

    /* Post process movies.
     *
     * $options[2] (char) Single character, first letter of release guid.
     * $options[3] (int)  Process all releases or renamed releases only.
     */
    case 'pp_movie':
        if (charCheck($options[2])) {
            try {
                (new PostProcess())->processMovies('', $options[2], $options[3] ?? '');
            } catch (Throwable $e) {
                echo $e->getMessage();
            }
        }
        break;

    /* Post process TV.
     *
     * $options[2] (char) Single character, first letter of release guid.
     * $options[3] (int)  Process all releases or renamed releases only.
     */
    case 'pp_tv':
        if (charCheck($options[2])) {
            try {
                (new PostProcess())->processTv('', $options[2], $options[3] ?? '');
            } catch (Throwable $e) {
                echo $e->getMessage();
            }
        }
        break;
}

/**
 * Create / process releases for a groupID.
 *
 * @param    $groupID
 * @param  ProcessReleases  $releases
 *
 * @throws \Throwable
 */
function processReleases(ProcessReleases $releases, $groupID): void
{
    $releaseCreationLimit = (Settings::settingValue('..maxnzbsprocessed') !== '' ? (int) Settings::settingValue('..maxnzbsprocessed') : 1000);
    $releases->processIncompleteCollections($groupID);
    $releases->processCollectionSizes($groupID);
    $releases->deleteUnwantedCollections($groupID);

    do {
        $releasesCount = $releases->createReleases($groupID);
        $nzbFilesAdded = $releases->createNZBs($groupID);

        // This loops as long as the number of releases or nzbs added was >= the limit (meaning there are more waiting to be created)
    } while ($releasesCount['added'] + $releasesCount['dupes'] >= $releaseCreationLimit || $nzbFilesAdded >= $releaseCreationLimit);
    $releases->deleteCollections($groupID);
}

/**
 * Check if the character contains a-f or 0-9.
 *
 * @param  string  $char
 * @return bool
 */
function charCheck($char): bool
{
    return \in_array($char, ['a', 'b', 'c', 'd', 'e', 'f', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], true);
}

/**
 * Connect to usenet, return NNTP object.
 *
 * @param bool $alternate Use alternate NNTP provider.
 * @return NNTP
 *
 * @throws \Exception
 */
function &nntp(bool $alternate = false)
{
    $nntp = new NNTP();
    if (($alternate && (int) Settings::settingValue('..alternate_nntp') === 1 ? $nntp->doConnect(true, true) : $nntp->doConnect()) !== true) {
        exit('ERROR: Unable to connect to usenet.'.PHP_EOL);
    }

    return $nntp;
}
