<?php

namespace Blacklight;

use App\Models\BinaryBlacklist;
use App\Models\Collection;
use App\Models\MissedPart;
use App\Models\Settings;
use App\Models\UsenetGroup;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Class Binaries.
 */
class Binaries
{
    public const OPTYPE_BLACKLIST = 1;

    public const OPTYPE_WHITELIST = 2;

    public const BLACKLIST_DISABLED = 0;

    public const BLACKLIST_ENABLED = 1;

    public const BLACKLIST_FIELD_SUBJECT = 1;

    public const BLACKLIST_FIELD_FROM = 2;

    public const BLACKLIST_FIELD_MESSAGEID = 3;

    public array $blackList = [];

    public array $whiteList = [];

    public int $messageBuffer;

    /**
     * @var \Blacklight\ColorCLI
     */
    protected mixed $colorCli;

    /**
     * @var \Blacklight\CollectionsCleaning
     */
    protected mixed $_collectionsCleaning;

    /**
     * @var \Blacklight\NNTP
     */
    protected mixed $_nntp;

    /**
     * Should we use header compression?
     */
    protected bool $_compressedHeaders;

    /**
     * Should we use part repair?
     */
    protected bool $_partRepair;

    protected \Closure|\PDO $_pdo;

    /**
     * How many days to go back on a new group?
     */
    protected bool $_newGroupScanByDays;

    /**
     * How many headers to download on new groups?
     */
    protected int $_newGroupMessagesToScan;

    /**
     * How many days to go back on new groups?
     */
    protected int $_newGroupDaysToScan;

    /**
     * How many headers to download per run of part repair?
     */
    protected int $_partRepairLimit;

    /**
     * Echo to cli?
     */
    protected bool $_echoCLI;

    /**
     * Max tries to download headers.
     */
    protected int $_partRepairMaxTries;

    /**
     * An array of BinaryBlacklist IDs that should have their activity date updated.
     */
    protected array $_binaryBlacklistIdsToUpdate = [];

    protected \DateTime $startCleaning;

    protected \DateTime $startLoop;

    /**
     * @var int How long it took in seconds to download headers
     */
    protected int $timeHeaders;

    /**
     * @var int How long it took in seconds to clean/parse headers
     */
    protected int $timeCleaning;

    protected \DateTime $startPR;

    protected \DateTime $startUpdate;

    /**
     * @var int The time it took to insert the headers
     */
    protected int $timeInsert;

    /**
     * @var array the header currently being scanned
     */
    protected array $header;

    /**
     * @var bool Should we add parts to part repair queue?
     */
    protected bool $addToPartRepair;

    /**
     * @var array Numbers of Headers received from the USP
     */
    protected array $headersReceived;

    /**
     * @var array The current newsgroup information being updated
     */
    protected array $groupMySQL;

    /**
     * @var int the last article number in the range
     */
    protected int $last;

    /**
     * @var int the first article number in the range
     */
    protected int $first;

    /**
     * @var int How many received headers were not yEnc encoded
     */
    protected int $notYEnc;

    /**
     * @var int How many received headers were blacklist matched
     */
    protected int $headersBlackListed;

    /**
     * @var array Header numbers that were not inserted
     */
    protected array $headersNotInserted;

    public function __construct()
    {
        $this->startUpdate = now();
        $this->timeCleaning = 0;

        $this->_echoCLI = config('nntmux.echocli');

        $this->_pdo = DB::connection()->getPdo();
        $this->colorCli = new ColorCLI();
        $this->_nntp = new NNTP();
        $this->_collectionsCleaning = new CollectionsCleaning();

        $this->messageBuffer = Settings::settingValue('..maxmssgs') !== '' ?
            (int) Settings::settingValue('..maxmssgs') : 20000;
        $this->_compressedHeaders = (int) Settings::settingValue('..compressedheaders') === 1;
        $this->_partRepair = (int) Settings::settingValue('..partrepair') === 1;
        $this->_newGroupScanByDays = (int) Settings::settingValue('..newgroupscanmethod') === 1;
        $this->_newGroupMessagesToScan = Settings::settingValue('..newgroupmsgstoscan') !== '' ? (int) Settings::settingValue('..newgroupmsgstoscan') : 50000;
        $this->_newGroupDaysToScan = Settings::settingValue('..newgroupdaystoscan') !== '' ? (int) Settings::settingValue('..newgroupdaystoscan') : 3;
        $this->_partRepairLimit = Settings::settingValue('..maxpartrepair') !== '' ? (int) Settings::settingValue('..maxpartrepair') : 15000;
        $this->_partRepairMaxTries = (Settings::settingValue('..partrepairmaxtries') !== '' ? (int) Settings::settingValue('..partrepairmaxtries') : 3);

        $this->blackList = $this->whiteList = [];
    }

    /**
     * Download new headers for all active groups.
     *
     * @param  int  $maxHeaders  (Optional) How many headers to download max.
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function updateAllGroups(int $maxHeaders = 100000): void
    {
        $groups = UsenetGroup::getActive()->toArray();

        $groupCount = \count($groups);
        if ($groupCount > 0) {
            $counter = 1;
            $allTime = now();

            $this->log(
                'Updating: '.$groupCount.' group(s) - Using compression? '.($this->_compressedHeaders ? 'Yes' : 'No'),
                __FUNCTION__,
                'header'
            );

            // Loop through groups.
            foreach ($groups as $group) {
                $this->log(
                    'Starting group '.$counter.' of '.$groupCount,
                    __FUNCTION__,
                    'header'
                );
                $this->updateGroup($group, $maxHeaders);
                $counter++;
            }

            $endTime = now()->diffInSeconds($allTime);
            $this->log(
                'Updating completed in '.$endTime.Str::plural(' second', $endTime),
                __FUNCTION__,
                'primary'
            );
        } else {
            $this->log(
                'No groups specified. Ensure groups are added to NNTmux\'s database for updating.',
                __FUNCTION__,
                'warning'
            );
        }
    }

    /**
     * When the indexer is started, log the date/time.
     */
    public function logIndexerStart(): void
    {
        Settings::query()->where('setting', '=', 'last_run_time')->update(['value' => now()]);
    }

    /**
     * Download new headers for a single group.
     *
     * @param  array  $groupMySQL  Array of MySQL results for a single group.
     * @param  int  $maxHeaders  (Optional) How many headers to download max.
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function updateGroup(array $groupMySQL, int $maxHeaders = 0): void
    {
        $startGroup = now();
        $this->logIndexerStart();

        // Select the group on the NNTP server, gets the latest info on it.
        $groupNNTP = $this->_nntp->selectGroup($groupMySQL['name']);
        if ($this->_nntp::isError($groupNNTP)) {
            $groupNNTP = $this->_nntp->dataError($this->_nntp, $groupMySQL['name']);

            if (isset($groupNNTP['code']) && (int) $groupNNTP['code'] === 411) {
                UsenetGroup::disableIfNotExist($groupMySQL['id']);
            }
            if ($this->_nntp::isError($groupNNTP)) {
                return;
            }
        }

        if ($this->_echoCLI) {
            $this->colorCli->primary('Processing '.$groupMySQL['name']);
        }

        // Attempt to repair any missing parts before grabbing new ones.
        if ((int) $groupMySQL['last_record'] !== 0) {
            if ($this->_partRepair) {
                if ($this->_echoCLI) {
                    $this->colorCli->primary('Part repair enabled. Checking for missing parts.');
                }
                $this->partRepair($groupMySQL);
            } elseif ($this->_echoCLI) {
                $this->colorCli->primary('Part repair disabled by user.');
            }
        }

        // Generate postdate for first record, for those that upgraded.
        if ($groupMySQL['first_record_postdate'] === null && (int) $groupMySQL['first_record'] !== 0) {
            $groupMySQL['first_record_postdate'] = $this->postdate($groupMySQL['first_record'], $groupNNTP);
            UsenetGroup::query()->where('id', $groupMySQL['id'])->update(['first_record_postdate' => Carbon::createFromTimestamp($groupMySQL['first_record_postdate'])]);
        }

        // Get first article we want aka the oldest.
        if ((int) $groupMySQL['last_record'] === 0) {
            if ($this->_newGroupScanByDays) {
                // For new newsgroups - determine here how far we want to go back using date.
                $first = $this->daytopost($this->_newGroupDaysToScan, $groupNNTP);
            } elseif ($groupNNTP['first'] >= ($groupNNTP['last'] - ($this->_newGroupMessagesToScan + $this->messageBuffer))) {
                // If what we want is lower than the groups first article, set the wanted first to the first.
                $first = $groupNNTP['first'];
            } else {
                // Or else, use the newest article minus how much we should get for new groups.
                $first = (string) ($groupNNTP['last'] - ($this->_newGroupMessagesToScan + $this->messageBuffer));
            }

            // We will use this to subtract so we leave articles for the next time (in case the server doesn't have them yet)
            $leaveOver = $this->messageBuffer;

        // If this is not a new group, go from our newest to the servers newest.
        } else {
            // Set our oldest wanted to our newest local article.
            $first = $groupMySQL['last_record'];

            // This is how many articles we will grab. (the servers newest minus our newest).
            $totalCount = (string) ($groupNNTP['last'] - $first);

            // Check if the server has more articles than our loop limit x 2.
            if ($totalCount > ($this->messageBuffer * 2)) {
                // Get the remainder of $totalCount / $this->message buffer
                $leaveOver = round($totalCount % $this->messageBuffer, 0, PHP_ROUND_HALF_DOWN) + $this->messageBuffer;
            } else {
                // Else get half of the available.
                $leaveOver = round($totalCount / 2, 0, PHP_ROUND_HALF_DOWN);
            }
        }

        // The last article we want, aka the newest.
        $last = $groupLast = (string) ($groupNNTP['last'] - $leaveOver);

        // If the newest we want is older than the oldest we want somehow.. set them equal.
        if ($last < $first) {
            $last = $groupLast = $first;
        }

        // This is how many articles we are going to get.
        $total = (string) ($groupLast - $first);
        // This is how many articles are available (without $leaveOver).
        $realTotal = (string) ($groupNNTP['last'] - $first);

        // Check if we should limit the amount of fetched new headers.
        if ($maxHeaders > 0) {
            if ($maxHeaders < ($groupLast - $first)) {
                $groupLast = $last = (string) ($first + $maxHeaders);
            }
            $total = (string) ($groupLast - $first);
        }

        // If total is bigger than 0 it means we have new parts in the newsgroup.
        if ($total > 0) {
            if ($this->_echoCLI) {
                $this->colorCli->primary(
                    (
                        (int) $groupMySQL['last_record'] === 0
                            ? 'New group '.$groupNNTP['group'].' starting with '.
                            (
                                $this->_newGroupScanByDays
                                    ? $this->_newGroupDaysToScan.' days'
                                    : number_format($this->_newGroupMessagesToScan).' messages'
                            ).' worth.'
                            : 'Group '.$groupNNTP['group'].' has '.number_format($realTotal).' new articles.'
                    ).
                    ' Leaving '.number_format($leaveOver).
                    " for next pass.\nServer oldest: ".number_format($groupNNTP['first']).
                    ' Server newest: '.number_format($groupNNTP['last']).
                    ' Local newest: '.number_format($groupMySQL['last_record'])
                );
            }

            $done = false;
            // Get all the parts (in portions of $this->messageBuffer to not use too much memory).
            while (! $done) {
                // Increment last until we reach $groupLast (group newest article).
                if ($total > $this->messageBuffer) {
                    if ((string) ($first + $this->messageBuffer) > $groupLast) {
                        $last = $groupLast;
                    } else {
                        $last = (string) ($first + $this->messageBuffer);
                    }
                }
                // Increment first so we don't get an article we already had.
                $first++;

                if ($this->_echoCLI) {
                    $this->colorCli->header(
                        PHP_EOL.'Getting '.number_format($last - $first + 1).' articles ('.number_format($first).
                        ' to '.number_format($last).') from '.$groupMySQL['name'].' - ('.
                        number_format($groupLast - $last).' articles in queue).'
                    );
                }

                // Get article headers from newsgroup.
                $scanSummary = $this->scan($groupMySQL, $first, $last);

                // Check if we fetched headers.
                if (! empty($scanSummary)) {
                    // If new group, update first record & postdate
                    if ($groupMySQL['first_record_postdate'] === null && (int) $groupMySQL['first_record'] === 0) {
                        $groupMySQL['first_record'] = $scanSummary['firstArticleNumber'];

                        if (isset($scanSummary['firstArticleDate'])) {
                            $groupMySQL['first_record_postdate'] = strtotime($scanSummary['firstArticleDate']);
                        } else {
                            $groupMySQL['first_record_postdate'] = $this->postdate($groupMySQL['first_record'], $groupNNTP);
                        }

                        UsenetGroup::query()
                            ->where('id', $groupMySQL['id'])
                            ->update(
                                [
                                    'first_record' => $scanSummary['firstArticleNumber'],
                                    'first_record_postdate' => Carbon::createFromTimestamp(
                                        $groupMySQL['first_record_postdate']
                                    ),
                                ]
                            );
                    }

                    $scanSummary['lastArticleDate'] = (isset($scanSummary['lastArticleDate']) ? strtotime($scanSummary['lastArticleDate']) : false);
                    if (! is_numeric($scanSummary['lastArticleDate'])) {
                        $scanSummary['lastArticleDate'] = $this->postdate($scanSummary['lastArticleNumber'], $groupNNTP);
                    }

                    UsenetGroup::query()
                        ->where('id', $groupMySQL['id'])
                        ->update(
                            [
                                'last_record' => $scanSummary['lastArticleNumber'],
                                'last_record_postdate' => Carbon::createFromTimestamp($scanSummary['lastArticleDate']),
                                'last_updated' => now(),
                            ]
                        );
                } else {
                    // If we didn't fetch headers, update the record still.
                    UsenetGroup::query()
                        ->where('id', $groupMySQL['id'])
                        ->update(
                            [
                                'last_record' => $last,
                                'last_updated' => now(),
                            ]
                        );
                }

                if ((int) $last === (int) $groupLast) {
                    $done = true;
                } else {
                    $first = $last;
                }
            }

            if ($this->_echoCLI) {
                $endGroup = now()->diffInSeconds($startGroup);
                $this->colorCli->primary(
                    PHP_EOL.'Group '.$groupMySQL['name'].' processed in '.
                    $endGroup.Str::plural(' second', $endGroup)
                );
            }
        } elseif ($this->_echoCLI) {
            $this->colorCli->primary(
                'No new articles for '.$groupMySQL['name'].' (first '.number_format($first).
                ', last '.number_format($last).', grouplast '.number_format($groupMySQL['last_record']).
                ', total '.number_format($total).")\n".'Server oldest: '.number_format($groupNNTP['first']).
                ' Server newest: '.number_format($groupNNTP['last']).' Local newest: '.number_format($groupMySQL['last_record'])
            );
        }
    }

    /**
     * Loop over range of wanted headers, insert headers into DB.
     *
     * @param  array  $groupMySQL  The group info from mysql.
     * @param  int  $first  The oldest wanted header.
     * @param  int  $last  The newest wanted header.
     * @param  string  $type  Is this part repair or update or backfill?
     * @param  array|null  $missingParts  If we are running in part repair, the list of missing article numbers.
     * @return array Empty on failure.
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function scan(array $groupMySQL, int $first, int $last, string $type = 'update', array $missingParts = null): array
    {
        // Start time of scan method and of fetching headers.
        $this->startLoop = now();
        $this->groupMySQL = $groupMySQL;
        $this->last = $last;
        $this->first = $first;

        $this->notYEnc = $this->headersBlackListed = 0;

        $returnArray = $stdHeaders = [];

        $partRepair = ($type === 'partrepair');
        $this->addToPartRepair = ($type === 'update' && $this->_partRepair);

        // Download the headers.
        if ($partRepair) {
            // This is slower but possibly is better with missing headers.
            $headers = $this->_nntp->getOverview($this->first.'-'.$this->last, true, false);
        } else {
            $headers = $this->_nntp->getXOVER($this->first.'-'.$this->last);
        }

        // If there was an error, try to reconnect.
        if ($this->_nntp::isError($headers)) {
            // Increment if part repair and return false.
            if ($partRepair) {
                MissedPart::query()->where('groups_id', $this->groupMySQL['id'])->where('numberid', ((int) $this->first === (int) $this->last ? '= '.$this->first : 'IN ('.implode(',', range($this->first, $this->last)).')'))->increment('attempts', 1);

                return $returnArray;
            }

            // This is usually a compression error, so try disabling compression.
            $this->_nntp->doQuit();
            if ($this->_nntp->doConnect(false) !== true) {
                return $returnArray;
            }

            // Re-select group, download headers again without compression and re-enable compression.
            $this->_nntp->selectGroup($this->groupMySQL['name']);
            $headers = $this->_nntp->getXOVER($this->first.'-'.$this->last);
            $this->_nntp->enableCompression();

            // Check if the non-compression headers have an error.
            if ($this->_nntp::isError($headers)) {
                $message = ((int) $headers->code === 0 ? 'Unknown error' : $headers->message);
                $this->log(
                    "Code {$headers->code}: $message\nSkipping group: {$this->groupMySQL['name']}",
                    __FUNCTION__,
                    'error'
                );

                return $returnArray;
            }
        }

        // Start of processing headers.
        $this->startCleaning = now();

        // End of the getting data from usenet.
        $this->timeHeaders = $this->startCleaning->diffInSeconds($this->startLoop);

        // Check if we got headers.
        $msgCount = \count($headers);

        if ($msgCount < 1) {
            return $returnArray;
        }

        $this->getHighLowArticleInfo($returnArray, $headers, $msgCount);

        $headersRepaired = $rangeNotReceived = $this->headersReceived = $this->headersNotInserted = [];

        foreach ($headers as $header) {
            // Check if we got the article or not.
            if (isset($header['Number'])) {
                $this->headersReceived[] = $header['Number'];
            } else {
                if ($this->addToPartRepair) {
                    $rangeNotReceived[] = $header['Number'];
                }

                continue;
            }

            // If set we are running in partRepair mode.
            if ($partRepair && $missingParts !== null) {
                if (! \in_array($header['Number'], $missingParts, false)) {
                    // If article isn't one that is missing skip it.
                    continue;
                }
                // We got the part this time. Remove article from part repair.
                $headersRepaired[] = $header['Number'];
            }

            /*
             * Find part / total parts. Ignore if no part count found.
             *
             * \s* Trims the leading space.
             * (?!"Usenet Index Post) ignores these types of articles, they are useless.
             * (.+) Fetches the subject.
             * \s+ Trims trailing space after the subject.
             * \((\d+)\/(\d+)\) Gets the part count.
             * No ending ($) as there are cases of subjects with extra data after the part count.
             */
            if (preg_match('/^\s*(?!"Usenet Index Post)(.+)\s+\((\d+)\/(\d+)\)/', $header['Subject'], $header['matches'])) {
                // Add yEnc to subjects that do not have them, but have the part number at the end of the header.
                if (stripos($header['Subject'], 'yEnc') === false) {
                    $header['matches'][1] .= ' yEnc';
                }
            } else {
                $this->notYEnc++;

                continue;
            }

            // Filter subject based on black/white list.
            if ($this->isBlackListed($header, $this->groupMySQL['name'])) {
                $this->headersBlackListed++;

                continue;
            }

            if (empty($header['Bytes'])) {
                $header['Bytes'] = (isset($this->header[':bytes']) ? $header[':bytes'] : 0);
            }

            $stdHeaders[] = $header;
        }

        unset($headers); // Reclaim memory now that headers are split.

        if (! empty($this->_binaryBlacklistIdsToUpdate)) {
            $this->updateBlacklistUsage();
        }

        if ($this->_echoCLI && ! $partRepair) {
            $this->outputHeaderInitial();
        }

        if (! empty($stdHeaders)) {
            $this->storeHeaders($stdHeaders);
        }
        unset($stdHeaders);

        // Start of part repair.
        $this->startPR = now();

        // End of inserting.
        $this->timeInsert = $this->startPR->diffInSeconds($this->startUpdate);

        if ($partRepair && \count($headersRepaired) > 0) {
            $this->removeRepairedParts($headersRepaired, $this->groupMySQL['id']);
        }
        unset($headersRepaired);

        if ($this->addToPartRepair) {
            $notInsertedCount = \count($this->headersNotInserted);
            if ($notInsertedCount > 0) {
                $this->addMissingParts($this->headersNotInserted, $this->groupMySQL['id']);

                $this->log(
                    $notInsertedCount.' articles failed to insert!',
                    __FUNCTION__,
                    'warning'
                );

                if (config('app.debug') === true) {
                    Log::warning($notInsertedCount.' articles failed to insert!');
                }
            }
            unset($this->headersNotInserted);

            // Check if we have any missing headers.
            if (($this->last - $this->first - $this->notYEnc - $this->headersBlackListed + 1) > \count($this->headersReceived)) {
                $rangeNotReceived = array_merge($rangeNotReceived, array_diff(range($this->first, $this->last), $this->headersReceived));
            }
            $notReceivedCount = \count($rangeNotReceived);
            if ($notReceivedCount > 0) {
                $this->addMissingParts($rangeNotReceived, $this->groupMySQL['id']);

                if ($this->_echoCLI) {
                    $this->colorCli->alternate(
                        'Server did not return '.$notReceivedCount.
                        ' articles from '.$this->groupMySQL['name'].'.'
                    );
                }
            }
            unset($rangeNotReceived);
        }

        $this->outputHeaderDuration();

        return $returnArray;
    }

    /**
     * Parse headers into collections/binaries and store header data as parts.
     *
     *
     *
     * @throws \Exception
     * @throws \Throwable
     */
    protected function storeHeaders(array $headers = []): void
    {
        $binariesUpdate = $collectionIDs = $articles = [];

        DB::beginTransaction();

        $partsQuery = $partsCheck = 'INSERT IGNORE INTO parts (binaries_id, number, messageid, partnumber, size) VALUES ';

        // Loop articles, figure out files/parts.
        foreach ($headers as $this->header) {
            // Set up the info for inserting into parts/binaries/collections tables.
            if (! isset($articles[$this->header['matches'][1]])) {
                // Attempt to find the file count. If it is not found, set it to 0.
                if (! preg_match('/[[(\s](\d{1,5})(\/|[\s_]of[\s_]|-)(\d{1,5})[])\s$:]/i', $this->header['matches'][1], $fileCount)) {
                    $fileCount[1] = $fileCount[3] = 0;
                }

                $collMatch = $this->_collectionsCleaning->collectionsCleaner(
                    $this->header['matches'][1]
                );

                // Used to group articles together when forming the release.
                $this->header['CollectionKey'] = $collMatch['name'].$fileCount[3];

                // If this header's collection key isn't in memory, attempt to insert the collection
                if (! isset($collectionIDs[$this->header['CollectionKey']])) {
                    /* Date from header should be a string this format:
                     * 31 Mar 2014 15:36:04 GMT or 6 Oct 1998 04:38:40 -0500
                     * Still make sure it's not unix time, convert it to unix time if it is.
                     */
                    $this->header['Date'] = (is_numeric($this->header['Date']) ? $this->header['Date'] : strtotime($this->header['Date']));

                    // Get the current unixtime from PHP.
                    $now = now()->timestamp;

                    $xrefsData = Collection::whereCollectionhash(sha1($this->header['CollectionKey']))->value('xref');

                    $tempHeaderXrefs = [];
                    foreach (explode(' ', $this->header['Xref']) as $headerXref) {
                        if (preg_match('/(^[a-zA-Z]{2,3}\.(bin(aries|arios|aer))\.[a-zA-Z0-9]?.+)(\:\d+)/', $headerXref, $hit) || preg_match('/(^[a-zA-Z]{2,3}\.(bin(aries|arios|aer))\.[a-zA-Z0-9]?.+)/', $headerXref, $hit)) {
                            $tempHeaderXrefs[] = $hit[0];
                        }
                    }

                    $tempXrefsData = [];

                    if ($xrefsData !== null) {
                        foreach (explode(' ', $xrefsData) as $xrefData) {
                            if (preg_match('/(^[a-zA-Z]{2,3}\.(bin(aries|arios|aer))\.[a-zA-Z0-9]?.+)(\:\d+)/', $xrefData, $match1) || preg_match('/(^[a-zA-Z]{2,3}\.(bin(aries|arios|aer))\.[a-zA-Z0-9]?.+)/', $xrefData, $match1)) {
                                $tempXrefsData[] = $match1[0];
                            }
                        }
                    }

                    $finalXrefArray = [];
                    foreach ($tempHeaderXrefs as $tempHeaderXref) {
                        if (! in_array($tempHeaderXref, $tempXrefsData, false)) {
                            $finalXrefArray[] = $tempHeaderXref;
                        }
                    }

                    $finaXref = implode(' ', $finalXrefArray);

                    $xref = sprintf('xref = CONCAT(xref, "\\n"%s ),', escapeString($finaXref));

                    $date = $this->header['Date'] > $now ? $now : $this->header['Date'];
                    $unixtime = is_numeric($this->header['Date']) ? $date : $now;

                    $random = random_bytes(16);

                    $collectionID = false;

                    try {
                        DB::insert(sprintf("
							INSERT INTO collections (subject, fromname, date, xref, groups_id,
								totalfiles, collectionhash, collection_regexes_id, dateadded)
							VALUES (%s, %s, FROM_UNIXTIME(%s), %s, %d, %d, '%s', %d, NOW())
							ON DUPLICATE KEY UPDATE %s dateadded = NOW(), noise = '%s'", escapeString(substr(mb_convert_encoding($this->header['matches'][1], 'UTF-8', mb_list_encodings()), 0, 255)), escapeString(mb_convert_encoding($this->header['From'], 'UTF-8', mb_list_encodings())), $unixtime, escapeString(implode(' ', $tempHeaderXrefs)), $this->groupMySQL['id'], $fileCount[3], sha1($this->header['CollectionKey']), $collMatch['id'], $xref, sodium_bin2hex($random)));
                        $collectionID = $this->_pdo->lastInsertId();
                        DB::commit();
                    } catch (\Throwable $e) {
                        if (config('app.debug') === true) {
                            Log::error($e->getMessage());
                        }
                        DB::rollBack();
                    }

                    if ($collectionID === false) {
                        if ($this->addToPartRepair) {
                            $this->headersNotInserted[] = $this->header['Number'];
                        }
                        DB::rollBack();
                        DB::beginTransaction();

                        continue;
                    }
                    $collectionIDs[$this->header['CollectionKey']] = $collectionID;
                } else {
                    $collectionID = $collectionIDs[$this->header['CollectionKey']];
                }

                // Binary Hash should be unique to the group
                $hash = md5($this->header['matches'][1].$this->header['From'].$this->groupMySQL['id']);

                $binaryID = false;

                try {
                    DB::insert(sprintf("
						INSERT INTO binaries (binaryhash, name, collections_id, totalparts, currentparts, filenumber, partsize)
						VALUES (UNHEX('%s'), %s, %d, %d, 1, %d, %d)
						ON DUPLICATE KEY UPDATE currentparts = currentparts + 1, partsize = partsize + %d", $hash, escapeString(mb_convert_encoding($this->header['matches'][1], 'UTF-8', mb_list_encodings())), $collectionID, $this->header['matches'][3], $fileCount[1], $this->header['Bytes'], $this->header['Bytes']));
                    $binaryID = $this->_pdo->lastInsertId();
                    DB::commit();
                } catch (\Throwable $e) {
                    if (config('app.debug') === true) {
                        Log::error($e->getMessage());
                    }
                    DB::rollBack();
                }

                if ($binaryID === false) {
                    if ($this->addToPartRepair) {
                        $this->headersNotInserted[] = $this->header['Number'];
                    }
                    DB::rollBack();
                    DB::beginTransaction();

                    continue;
                }

                $binariesUpdate[$binaryID]['Size'] = 0;
                $binariesUpdate[$binaryID]['Parts'] = 0;

                $articles[$this->header['matches'][1]]['CollectionID'] = $collectionID;
                $articles[$this->header['matches'][1]]['BinaryID'] = $binaryID;
            } else {
                $binaryID = $articles[$this->header['matches'][1]]['BinaryID'];
                $binariesUpdate[$binaryID]['Size'] += $this->header['Bytes'];
                $binariesUpdate[$binaryID]['Parts']++;
            }

            // In case there are quotes in the message id
            $this->header['Message-ID'] = addslashes($this->header['Message-ID']);

            // Strip the < and >, saves space in DB.
            $this->header['Message-ID'][0] = "'";

            $partsQuery .=
                '('.$binaryID.','.$this->header['Number'].','.rtrim($this->header['Message-ID'], '>')."',".
                $this->header['matches'][2].','.$this->header['Bytes'].'),';
        }

        unset($headers); // Reclaim memory.

        // Start of inserting into SQL.
        $this->startUpdate = now();

        // End of processing headers.
        $this->timeCleaning = $this->startUpdate->diffInSeconds($this->startCleaning);
        $binariesQuery = $binariesCheck = 'INSERT INTO binaries (id, partsize, currentparts) VALUES ';
        foreach ($binariesUpdate as $binaryID => $binary) {
            $binariesQuery .= '('.$binaryID.','.$binary['Size'].','.$binary['Parts'].'),';
        }
        $binariesEnd = ' ON DUPLICATE KEY UPDATE partsize = VALUES(partsize) + partsize, currentparts = VALUES(currentparts) + currentparts';
        $binariesQuery = rtrim($binariesQuery, ',').$binariesEnd;

        // Check if we got any binaries. If we did, try to insert them.
        if (\strlen($binariesCheck.$binariesEnd) === \strlen($binariesQuery) || $this->runQuery($binariesQuery)) {
            if (\strlen($partsQuery) === \strlen($partsCheck) || $this->runQuery(rtrim($partsQuery, ','))) {
                DB::commit();
            } else {
                if ($this->addToPartRepair) {
                    $this->headersNotInserted += $this->headersReceived;
                }
                DB::rollBack();
            }
        } else {
            if ($this->addToPartRepair) {
                $this->headersNotInserted += $this->headersReceived;
            }
            DB::rollBack();
        }
    }

    /**
     * Gets the First and Last Article Number and Date for the received headers.
     */
    protected function getHighLowArticleInfo(array &$returnArray, array $headers, int $msgCount): void
    {
        // Get highest and lowest article numbers/dates.
        $iterator1 = 0;
        $iterator2 = $msgCount - 1;
        while (true) {
            if (! isset($returnArray['firstArticleNumber']) && isset($headers[$iterator1]['Number'])) {
                $returnArray['firstArticleNumber'] = $headers[$iterator1]['Number'];
                $returnArray['firstArticleDate'] = $headers[$iterator1]['Date'];
            }

            if (! isset($returnArray['lastArticleNumber']) && isset($headers[$iterator2]['Number'])) {
                $returnArray['lastArticleNumber'] = $headers[$iterator2]['Number'];
                $returnArray['lastArticleDate'] = $headers[$iterator2]['Date'];
            }

            // Break if we found non empty articles.
            if (isset($returnArray['firstArticleNumber, lastArticleNumber'])) {
                break;
            }

            // Break out if we couldn't find anything.
            if ($iterator1++ >= $msgCount - 1 || $iterator2-- <= 0) {
                break;
            }
        }
    }

    /**
     * Updates Blacklist Regex Timers in DB to reflect last usage.
     */
    protected function updateBlacklistUsage(): void
    {
        BinaryBlacklist::query()->whereIn('id', $this->_binaryBlacklistIdsToUpdate)->update(['last_activity' => now()]);
        $this->_binaryBlacklistIdsToUpdate = [];
    }

    /**
     * Outputs the initial header scan results after yEnc check and blacklist routines.
     */
    protected function outputHeaderInitial(): void
    {
        $this->colorCli->primary(
            'Received '.\count($this->headersReceived).
            ' articles of '.number_format($this->last - $this->first + 1).' requested, '.
            $this->headersBlackListed.' blacklisted, '.$this->notYEnc.' not yEnc.'
        );
    }

    /**
     * Outputs speed metrics of the scan function to CLI.
     */
    protected function outputHeaderDuration(): void
    {
        $currentMicroTime = now();
        if ($this->_echoCLI) {
            $this->colorCli->alternateOver($this->timeHeaders.'s').
            $this->colorCli->primaryOver(' to download articles, ').
            $this->colorCli->alternateOver($this->timeCleaning.'s').
            $this->colorCli->primaryOver(' to process collections, ').
            $this->colorCli->alternateOver($this->timeInsert.'s').
            $this->colorCli->primaryOver(' to insert binaries/parts, ').
            $this->colorCli->alternateOver($currentMicroTime->diffInSeconds($this->startPR).'s').
            $this->colorCli->primaryOver(' for part repair, ').
            $this->colorCli->alternateOver($currentMicroTime->diffInSeconds($this->startLoop).'s').
            $this->colorCli->primary(' total.');
        }
    }

    /**
     * Attempt to get missing article headers.
     *
     * @param  array  $groupArr  The info for this group from mysql.
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function partRepair(array $groupArr): void
    {
        // Get all parts in part repair table.
        $missingParts = [];
        try {
            $missingParts = DB::select(sprintf('
				SELECT * FROM missed_parts
				WHERE groups_id = %d AND attempts < %d
				ORDER BY numberid ASC LIMIT %d', $groupArr['id'], $this->_partRepairMaxTries, $this->_partRepairLimit));
        } catch (\PDOException $e) {
            if ($e->getMessage() === 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction') {
                $this->colorCli->notice('Deadlock occurred');
                DB::rollBack();
            }
        }

        $missingCount = \count($missingParts);
        if ($missingCount > 0) {
            if ($this->_echoCLI) {
                $this->colorCli->primary(
                    'Attempting to repair '.
                    number_format($missingCount).
                    ' parts.'
                );
            }

            // Loop through each part to group into continuous ranges with a maximum range of messagebuffer/4.
            $ranges = $partList = [];
            $firstPart = $lastNum = $missingParts[0]->numberid;

            foreach ($missingParts as $part) {
                if (($part->numberid - $firstPart) > ($this->messageBuffer / 4)) {
                    $ranges[] = [
                        'partfrom' => $firstPart,
                        'partto' => $lastNum,
                        'partlist' => $partList,
                    ];

                    $firstPart = $part->numberid;
                    $partList = [];
                }
                $partList[] = $part->numberid;
                $lastNum = $part->numberid;
            }

            $ranges[] = [
                'partfrom' => $firstPart,
                'partto' => $lastNum,
                'partlist' => $partList,
            ];

            // Download missing parts in ranges.
            foreach ($ranges as $range) {
                $partFrom = $range['partfrom'];
                $partTo = $range['partto'];
                $partList = $range['partlist'];

                if ($this->_echoCLI) {
                    echo \chr(random_int(45, 46)).PHP_EOL;
                }

                // Get article headers from newsgroup.
                $this->scan($groupArr, $partFrom, $partTo, 'partrepair', $partList);
            }

            // Calculate parts repaired
            $result = DB::select(
                sprintf(
                    '
					SELECT COUNT(id) AS num
					FROM missed_parts
					WHERE groups_id = %d
					AND numberid <= %d',
                    $groupArr['id'],
                    $missingParts[$missingCount - 1]->numberid
                )
            );

            $partsRepaired = 0;
            if ($result > 0) {
                $partsRepaired = ($missingCount - $result[0]->num);
            }

            // Update attempts on remaining parts for active group
            if (isset($missingParts[$missingCount - 1]->id)) {
                DB::update(
                    sprintf(
                        '
						UPDATE missed_parts
						SET attempts = attempts + 1
						WHERE groups_id = %d
						AND numberid <= %d',
                        $groupArr['id'],
                        $missingParts[$missingCount - 1]->numberid
                    )
                );
            }

            if ($this->_echoCLI) {
                $this->colorCli->primary(
                    PHP_EOL.
                    number_format($partsRepaired).
                    ' parts repaired.'
                );
            }
        }

        // Remove articles that we cant fetch after x attempts.
        DB::transaction(function () use ($groupArr) {
            DB::delete(
                sprintf(
                    'DELETE FROM missed_parts WHERE attempts >= %d AND groups_id = %d',
                    $this->_partRepairMaxTries,
                    $groupArr['id']
                )
            );
        }, 10);
    }

    /**
     * Returns unix time for an article number.
     *
     * @param  int  $post  The article number to get the time from.
     * @param  array  $groupData  Usenet group info from NNTP selectGroup method.
     * @return int Timestamp.
     *
     * @throws \Exception
     */
    public function postdate(int $post, array $groupData): int
    {
        $currentPost = $post;

        $attempts = $date = 0;
        do {
            // Try to get the article date locally first.
            // Try to get locally.
            $local = DB::select(
                sprintf(
                    '
						SELECT c.date AS date
						FROM collections c
						INNER JOIN binaries b ON(c.id=b.collections_id)
						INNER JOIN parts p ON(b.id=p.binaries_id)
						WHERE p.number = %s',
                    $currentPost
                )
            );
            if (! empty($local) && \count($local) > 0) {
                $date = $local[0]->date;
                break;
            }

            // If we could not find it locally, try usenet.
            $header = $this->_nntp->getXOVER($currentPost);
            if (! $this->_nntp::isError($header) && isset($header[0]['Date']) && $header[0]['Date'] !== '') {
                $date = $header[0]['Date'];
                break;
            }

            // Try to get a different article number.
            if (abs($currentPost - $groupData['first']) > abs($groupData['last'] - $currentPost)) {
                $tempPost = round($currentPost / (random_int(1005, 1012) / 1000), 0, PHP_ROUND_HALF_UP);
                if ($tempPost < $groupData['first']) {
                    $tempPost = $groupData['first'];
                }
            } else {
                $tempPost = round((random_int(1005, 1012) / 1000) * $currentPost, 0, PHP_ROUND_HALF_UP);
                if ($tempPost > $groupData['last']) {
                    $tempPost = $groupData['last'];
                }
            }
            // If we got the same article number as last time, give up.
            if ($tempPost === $currentPost) {
                break;
            }
            $currentPost = $tempPost;
        } while ($attempts++ <= 20);

        // If we didn't get a date, set it to now.
        if (! $date) {
            $date = time();
        } else {
            $date = strtotime($date);
        }

        return $date;
    }

    /**
     * Returns article number based on # of days.
     *
     * @param  int  $days  How many days back we want to go.
     * @param  array  $data  Group data from usenet.
     *
     * @throws \Exception
     */
    public function daytopost(int $days, array $data): string
    {
        $goalTime = now()->subDays($days)->timestamp;
        // The time we want = current unix time (ex. 1395699114) - minus 86400 (seconds in a day)
        // times days wanted. (ie 1395699114 - 2592000 (30days)) = 1393107114

        // The servers oldest date.
        $firstDate = $this->postdate($data['first'], $data);
        if ($goalTime < $firstDate) {
            // If the date we want is older than the oldest date in the group return the groups oldest article.
            return $data['first'];
        }

        // The servers newest date.
        $lastDate = $this->postdate($data['last'], $data);
        if ($goalTime > $lastDate) {
            // If the date we want is newer than the groups newest date, return the groups newest article.
            return $data['last'];
        }

        if ($this->_echoCLI) {
            $this->colorCli->primary(
                'Searching for an approximate article number for group '.$data['group'].' '.$days.' days back.'
            );
        }

        // Pick the middle to start with
        $wantedArticle = round(($data['last'] + $data['first']) / 2);
        $aMax = $data['last'];
        $aMin = $data['first'];
        $oldArticle = $articleTime = null;

        while (true) {
            // Article exists outside available range, this shouldn't happen
            if ($wantedArticle <= $data['first'] || $wantedArticle >= $data['last']) {
                break;
            }

            // Keep a note of the last articles we checked
            $reallyOldArticle = $oldArticle;
            $oldArticle = $wantedArticle;

            // Get the date of this article
            $articleTime = $this->postdate($wantedArticle, $data);

            // Article doesn't exist, start again with something random
            if (! $articleTime) {
                $wantedArticle = random_int($aMin, $aMax);
                $articleTime = $this->postdate($wantedArticle, $data);
            }

            if ($articleTime < $goalTime) {
                // Article is older than we want
                $aMin = $oldArticle;
                $wantedArticle = round(($aMax + $oldArticle) / 2);
                if ($this->_echoCLI) {
                    echo '-';
                }
            } elseif ($articleTime > $goalTime) {
                // Article is newer than we want
                $aMax = $oldArticle;
                $wantedArticle = round(($aMin + $oldArticle) / 2);
                if ($this->_echoCLI) {
                    echo '+';
                }
            } elseif ($articleTime === $goalTime) {
                // Exact match. We did it! (this will likely never happen though)
                break;
            }

            // We seem to be flip-flopping between 2 articles, assume we're out of articles to check.
            // End on an article more recent than our oldest so that we don't miss any releases.
            if ($reallyOldArticle === $wantedArticle && ($goalTime - $articleTime) <= 0) {
                break;
            }
        }

        $wantedArticle = (int) $wantedArticle;
        if ($this->_echoCLI) {
            $this->colorCli->primary(
                PHP_EOL.'Found article #'.$wantedArticle.' which has a date of '.date('r', $articleTime).
                ', vs wanted date of '.date('r', $goalTime).'. Difference from goal is '.Carbon::createFromTimestamp($goalTime)->diffInDays(Carbon::createFromTimestamp($articleTime)).'days.'
            );
        }

        return $wantedArticle;
    }

    /**
     * Add article numbers from missing headers to DB.
     *
     * @param  array  $numbers  The article numbers of the missing headers.
     * @param  int  $groupID  The ID of this groups.
     */
    private function addMissingParts(array $numbers, int $groupID): void
    {
        $insertStr = 'INSERT INTO missed_parts (numberid, groups_id) VALUES ';
        foreach ($numbers as $number) {
            $insertStr .= '('.$number.','.$groupID.'),';
        }

        DB::insert(rtrim($insertStr, ',').' ON DUPLICATE KEY UPDATE attempts=attempts+1');
    }

    /**
     * Clean up part repair table.
     *
     * @param  array  $numbers  The article numbers.
     * @param  int  $groupID  The ID of the group.
     *
     * @throws \Throwable
     */
    private function removeRepairedParts(array $numbers, int $groupID): void
    {
        $sql = 'DELETE FROM missed_parts WHERE numberid in (';
        foreach ($numbers as $number) {
            $sql .= $number.',';
        }
        DB::transaction(static function () use ($groupID, $sql) {
            DB::delete(rtrim($sql, ',').') AND groups_id = '.$groupID);
        }, 10);
    }

    /**
     * Are white or black lists loaded for a group name?
     */
    protected array $_listsFound = [];

    /**
     * Get blacklist and cache it. Return if already cached.
     */
    protected function _retrieveBlackList(string $groupName): void
    {
        if (! isset($this->blackList[$groupName])) {
            $this->blackList[$groupName] = $this->getBlacklist(true, self::OPTYPE_BLACKLIST, $groupName, true);
        }
        if (! isset($this->whiteList[$groupName])) {
            $this->whiteList[$groupName] = $this->getBlacklist(true, self::OPTYPE_WHITELIST, $groupName, true);
        }
        $this->_listsFound[$groupName] = ($this->blackList[$groupName] || $this->whiteList[$groupName]);
    }

    /**
     * Check if an article is blacklisted.
     *
     * @param  array  $msg  The article header (OVER format).
     * @param  string  $groupName  The group name.
     */
    public function isBlackListed(array $msg, string $groupName): bool
    {
        if (! isset($this->_listsFound[$groupName])) {
            $this->_retrieveBlackList($groupName);
        }
        if (! $this->_listsFound[$groupName]) {
            return false;
        }

        $blackListed = false;

        $field = [
            self::BLACKLIST_FIELD_SUBJECT => $msg['Subject'],
            self::BLACKLIST_FIELD_FROM => $msg['From'],
            self::BLACKLIST_FIELD_MESSAGEID => $msg['Message-ID'],
        ];

        // Try white lists first.
        if ($this->whiteList[$groupName]) {
            // There are white lists for this group, so anything that doesn't match a white list should be considered black listed.
            $blackListed = true;
            foreach ($this->whiteList[$groupName] as $whiteList) {
                if (preg_match('/'.$whiteList['regex'].'/i', $field[$whiteList['msgcol']])) {
                    // This field matched a white list, so it might not be black listed.
                    $blackListed = false;
                    $this->_binaryBlacklistIdsToUpdate[$whiteList['id']] = $whiteList['id'];
                    break;
                }
            }
        }

        // Check if the field is blacklisted.

        if (! $blackListed && $this->blackList[$groupName]) {
            foreach ($this->blackList[$groupName] as $blackList) {
                if (preg_match('/'.$blackList->regex.'/i', $field[$blackList->msgcol])) {
                    $blackListed = true;
                    $this->_binaryBlacklistIdsToUpdate[$blackList->id] = $blackList->id;
                    break;
                }
            }
        }

        return $blackListed;
    }

    /**
     * Return all blacklists.
     *
     * @param  bool  $activeOnly  Only display active blacklists ?
     * @param  int|string  $opType  Optional, get white or black lists (use Binaries constants).
     * @param  string  $groupName  Optional, group.
     * @param  bool  $groupRegex  Optional Join groups / binary blacklist using regexp for equals.
     */
    public function getBlacklist(bool $activeOnly = true, int|string $opType = -1, string $groupName = '', bool $groupRegex = false): array
    {
        $opType = match ($opType) {
            self::OPTYPE_BLACKLIST => 'AND bb.optype = '.self::OPTYPE_BLACKLIST,
            self::OPTYPE_WHITELIST => 'AND bb.optype = '.self::OPTYPE_WHITELIST,
            default => '',
        };

        return DB::select(
            sprintf(
                '
				SELECT
					bb.id, bb.optype, bb.status, bb.description,
					bb.groupname AS groupname, bb.regex, g.id AS group_id, bb.msgcol,
					bb.last_activity as last_activity
				FROM binaryblacklist bb
				LEFT OUTER JOIN usenet_groups g ON g.name %s bb.groupname
				WHERE 1=1 %s %s %s
				ORDER BY coalesce(groupname,\'zzz\')',
                ($groupRegex ? 'REGEXP' : '='),
                ($activeOnly ? 'AND bb.status = 1' : ''),
                $opType,
                ($groupName ? ('AND g.name REGEXP '.escapeString($groupName)) : '')
            )
        );
    }

    /**
     * Return a blacklist by ID.
     *
     * @param  int  $id  The ID of the blacklist.
     */
    public function getBlacklistByID(int $id)
    {
        return BinaryBlacklist::query()->where('id', $id)->first();
    }

    /**
     * Delete a blacklist.
     *
     * @param  int  $id  The ID of the blacklist.
     */
    public function deleteBlacklist(int $id): void
    {
        BinaryBlacklist::query()->where('id', $id)->delete();
    }

    public function updateBlacklist(array $blacklistArray): void
    {
        BinaryBlacklist::query()->where('id', $blacklistArray['id'])->update(
            [
                'groupname' => $blacklistArray['groupname'] === '' ? 'null' : preg_replace('/a\.b\./i', 'alt.binaries.', $blacklistArray['groupname']),
                'regex' => $blacklistArray['regex'],
                'status' => $blacklistArray['status'],
                'description' => $blacklistArray['description'],
                'optype' => $blacklistArray['optype'],
                'msgcol' => $blacklistArray['msgcol'],
            ]
        );
    }

    /**
     * Adds a new blacklist from binary blacklist edit admin web page.
     */
    public function addBlacklist(array $blacklistArray): void
    {
        BinaryBlacklist::query()->insert(
            [
                'groupname' => $blacklistArray['groupname'] === '' ? 'null' : preg_replace('/a\.b\./i', 'alt.binaries.', $blacklistArray['groupname']),
                'regex' => $blacklistArray['regex'],
                'status' => $blacklistArray['status'],
                'description' => $blacklistArray['description'],
                'optype' => $blacklistArray['optype'],
                'msgcol' => $blacklistArray['msgcol'],
            ]
        );
    }

    /**
     * Delete Collections/Binaries/Parts for a Collection ID.
     *
     * @param  int  $collectionID  Collections table ID
     *
     * @note A trigger automatically deletes the parts/binaries.
     *
     * @throws \Throwable
     */
    public function delete(int $collectionID): void
    {
        DB::transaction(static function () use ($collectionID) {
            DB::delete(sprintf('DELETE FROM collections WHERE id = %d', $collectionID));
        }, 10);

        Collection::query()->where('id', $collectionID)->delete();
    }

    /**
     * Log / Echo message.
     *
     * @param  string  $message  Message to log.
     * @param  string  $method  Method that called this.
     * @param  string  $color  ColorCLI method name.
     */
    private function log(string $message, string $method, string $color): void
    {
        if ($this->_echoCLI) {
            $this->colorCli->$color($message.' ['.__CLASS__."::$method]");
        }
    }

    protected function runQuery($query): bool
    {
        try {
            return DB::insert($query);
        } catch (QueryException $e) {
            if (config('app.debug') === true) {
                Log::error($e->getMessage());
            }
            $this->colorCli->debug('Query error occurred.');
        } catch (\PDOException $e) {
            if (config('app.debug') === true) {
                Log::error($e->getMessage());
            }
            $this->colorCli->debug('Query error occurred.');
        } catch (\Throwable $e) {
            if (config('app.debug') === true) {
                Log::error($e->getMessage());
            }
            $this->colorCli->debug('Query error occurred.');
        }

        return false;
    }
}
