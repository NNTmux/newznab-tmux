<?php

declare(strict_types=1);

namespace App\Services\Binaries;

use App\Models\Settings;
use App\Models\UsenetGroup;
use App\Services\NNTP\NNTPService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service for downloading and processing of Usenet binary headers.
 *
 * This service orchestrates the header processing workflow using:
 * - HeaderParser for parsing and filtering headers
 * - HeaderStorageService for storing headers to database
 * - MissedPartHandler for part repair tracking
 * - BinariesConfig for configuration
 */
class BinariesService
{
    private BinariesConfig $config;

    private HeaderParser $headerParser;

    private HeaderStorageService $headerStorage;

    private MissedPartHandler $missedPartHandler;

    private ?NNTPService $nntp = null;

    // Timing metrics
    private float $timeHeaders = 0;

    private float $timeCleaning = 0;

    private float $timeInsert = 0;

    private \DateTime $startLoop;

    private \DateTime $startCleaning;

    private \DateTime $startPR;

    private \DateTime $startUpdate;

    // Scan state
    private array $groupMySQL = [];

    private int $first = 0;

    private int $last = 0;

    private int $notYEnc = 0;

    private int $headersBlackListed = 0;

    private array $headersReceived = [];

    public function __construct(
        ?BinariesConfig $config = null,
        ?HeaderParser $headerParser = null,
        ?HeaderStorageService $headerStorage = null,
        ?MissedPartHandler $missedPartHandler = null,
        ?NNTPService $nntp = null
    ) {
        $this->config = $config ?? BinariesConfig::fromSettings();
        $this->headerParser = $headerParser ?? new HeaderParser;
        $this->headerStorage = $headerStorage ?? new HeaderStorageService(config: $this->config);
        $this->missedPartHandler = $missedPartHandler ?? new MissedPartHandler(
            $this->config->partRepairLimit,
            $this->config->partRepairMaxTries
        );
        $this->nntp = $nntp;
        $this->startUpdate = now();
    }

    /**
     * Set NNTP connection (for external injection).
     */
    public function setNntp(NNTPService $nntp): void
    {
        $this->nntp = $nntp;
    }

    /**
     * Get the NNTP connection, creating one if needed.
     */
    public function getNntp(): NNTPService
    {
        if ($this->nntp === null) {
            $this->nntp = new NNTPService;
        }

        return $this->nntp;
    }

    /**
     * Get the configuration object.
     */
    public function getConfig(): BinariesConfig
    {
        return $this->config;
    }

    /**
     * Get the message buffer size.
     */
    public function getMessageBuffer(): int
    {
        return $this->config->messageBuffer;
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

        if ($groupCount === 0) {
            $this->log(
                'No groups specified. Ensure groups are added to NNTmux\'s database for updating.',
                __FUNCTION__,
                'warning'
            );

            return;
        }

        $counter = 1;
        $allTime = now();

        $this->log(
            'Updating: '.$groupCount.' group(s) - Using compression? '.($this->config->compressedHeaders ? 'Yes' : 'No'),
            __FUNCTION__,
            'header'
        );

        foreach ($groups as $group) {
            $this->log(
                'Starting group '.$counter.' of '.$groupCount,
                __FUNCTION__,
                'header'
            );

            try {
                $this->updateGroup($group, $maxHeaders);
            } catch (\Throwable $e) {
                $this->logError('Error updating group '.$group['name'].': '.$e->getMessage());
            }

            $counter++;
        }

        $endTime = now()->diffInSeconds($allTime, true);
        $this->log(
            'Updating completed in '.$endTime.Str::plural(' second', (int) $endTime),
            __FUNCTION__,
            'primary'
        );
    }

    /**
     * Log the indexer start time.
     */
    public function logIndexerStart(): void
    {
        Settings::query()->where('name', '=', 'last_run_time')->update(['value' => now()]);
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

        $nntp = $this->getNntp();

        // Select the group on the NNTP server
        $groupNNTP = $this->selectNntpGroup($groupMySQL, $nntp);
        if ($groupNNTP === null) {
            return;
        }

        if ($this->config->echoCli) {
            cli()->primary('Processing '.$groupMySQL['name']);
        }

        // Attempt to repair any missing parts before grabbing new ones
        if ((int) $groupMySQL['last_record'] !== 0 && $this->config->partRepair) {
            if ($this->config->echoCli) {
                cli()->primary('Part repair enabled. Checking for missing parts.');
            }
            $this->partRepair($groupMySQL);
        } elseif ($this->config->echoCli && (int) $groupMySQL['last_record'] !== 0) {
            cli()->primary('Part repair disabled by user.');
        }

        // Generate postdate for first record, for those that upgraded
        if ($groupMySQL['first_record_postdate'] === null && (int) $groupMySQL['first_record'] !== 0) {
            $groupMySQL['first_record_postdate'] = $this->postdate($groupMySQL['first_record'], $groupNNTP);
            UsenetGroup::query()->where('id', $groupMySQL['id'])->update([
                'first_record_postdate' => Carbon::createFromTimestamp($groupMySQL['first_record_postdate'], date_default_timezone_get()),
            ]);
        }

        // Calculate article range
        $range = $this->calculateArticleRange($groupMySQL, $groupNNTP, $maxHeaders);

        if ($range['total'] <= 0) {
            $this->outputNoNewArticles($groupMySQL, $groupNNTP, $range);

            return;
        }

        $this->outputNewArticlesInfo($groupMySQL, $groupNNTP, $range);
        $this->processArticleRange($groupMySQL, $groupNNTP, $range);

        if ($this->config->echoCli) {
            $endGroup = now()->diffInSeconds($startGroup, true);
            cli()->primary(
                PHP_EOL.'Group '.$groupMySQL['name'].' processed in '.$endGroup.Str::plural(' second', (int) $endGroup)
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
    public function scan(array $groupMySQL, int $first, int $last, string $type = 'update', ?array $missingParts = null): array
    {
        $this->startLoop = now();
        $this->groupMySQL = $groupMySQL;
        $this->last = $last;
        $this->first = $first;
        $this->notYEnc = $this->headersBlackListed = 0;
        $this->headersReceived = [];

        $returnArray = [];
        $partRepair = ($type === 'partrepair');
        $addToPartRepair = ($type === 'update' && $this->config->partRepair);

        // Download headers from NNTP
        $headers = $this->downloadHeaders($partRepair);
        if ($headers === null) {
            if ($partRepair) {
                $this->missedPartHandler->incrementRangeAttempts($groupMySQL['id'], $first, $last);
            }

            return $returnArray;
        }

        $this->startCleaning = now();
        $this->timeHeaders = $this->startCleaning->diffInSeconds($this->startLoop, true);

        $msgCount = \count($headers);
        if ($msgCount < 1) {
            return $returnArray;
        }

        // Extract article range info
        $returnArray = $this->headerParser->getArticleRange($headers);

        // Parse and filter headers
        $this->headerParser->reset();
        $parseResult = $this->headerParser->parse($headers, $groupMySQL['name'], $partRepair, $missingParts);

        $this->headersReceived = array_column($headers, 'Number');
        $this->headersReceived = array_filter($this->headersReceived);
        $this->notYEnc = $parseResult['notYEnc'];
        $this->headersBlackListed = $parseResult['blacklisted'];

        // Update blacklist last_activity
        $this->headerParser->flushBlacklistUpdates();

        unset($headers);

        if ($this->config->echoCli && ! $partRepair) {
            $this->outputHeaderInitial();
        }

        // Store headers
        $this->startUpdate = now();  // Reset before storage begins
        $this->timeCleaning = $this->startUpdate->diffInSeconds($this->startCleaning, true);

        $headersNotInserted = [];
        if (! empty($parseResult['headers'])) {
            try {
                $headersNotInserted = $this->headerStorage->store($parseResult['headers'], $groupMySQL, $addToPartRepair);
            } catch (\Throwable $e) {
                $this->logError('storeHeaders failed: '.$e->getMessage());
            }
        }

        $this->startPR = now();
        $this->timeInsert = $this->startPR->diffInSeconds($this->startUpdate, true);

        // Handle repaired parts
        if ($partRepair && ! empty($parseResult['repaired'])) {
            $this->missedPartHandler->removeRepairedParts($parseResult['repaired'], $groupMySQL['id']);
        }

        // Handle part repair tracking
        if ($addToPartRepair) {
            $this->handlePartRepairTracking($headersNotInserted, $parseResult['headers']);
        }

        $this->outputHeaderDuration();

        return $returnArray;
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
        $missingParts = $this->missedPartHandler->getMissingParts($groupArr['id']);
        $missingCount = \count($missingParts);

        if ($missingCount === 0) {
            $this->missedPartHandler->cleanupExhaustedParts($groupArr['id']);

            return;
        }

        if ($this->config->echoCli) {
            cli()->primary('Attempting to repair '.number_format($missingCount).' parts.');
        }

        // Group into continuous ranges
        $ranges = $this->groupMissingPartsIntoRanges($missingParts);

        // Download missing parts in ranges
        foreach ($ranges as $range) {
            if ($this->config->echoCli) {
                echo \chr(random_int(45, 46)).PHP_EOL;
            }

            $this->scan($groupArr, $range['partfrom'], $range['partto'], 'partrepair', $range['partlist']);
        }

        // Calculate parts repaired
        $lastPartNumber = $missingParts[$missingCount - 1]->numberid;
        $remainingCount = $this->missedPartHandler->getCount($groupArr['id'], $lastPartNumber);
        $partsRepaired = $missingCount - $remainingCount;

        // Update attempts on remaining parts
        if (isset($missingParts[$missingCount - 1]->id)) {
            $this->missedPartHandler->incrementAttempts($groupArr['id'], $lastPartNumber);
        }

        if ($this->config->echoCli) {
            cli()->primary(PHP_EOL.number_format($partsRepaired).' parts repaired.');
        }

        // Remove articles that exceeded max tries
        $this->missedPartHandler->cleanupExhaustedParts($groupArr['id']);
    }

    /**
     * Returns unix time for an article number.
     *
     * @param  int|string  $post  The article number to get the time from.
     * @param  array  $groupData  Usenet group info from NNTP selectGroup method.
     * @return int Timestamp.
     *
     * @throws \Exception
     */
    public function postdate(int|string $post, array $groupData): int
    {
        $nntp = $this->getNntp();
        $currentPost = (int) $post;
        $attempts = 0;
        $date = 0;

        do {
            // Try to get the article date locally first
            $local = DB::select(
                sprintf(
                    'SELECT c.date AS date FROM collections c
                    INNER JOIN binaries b ON(c.id=b.collections_id)
                    INNER JOIN parts p ON(b.id=p.binaries_id)
                    WHERE p.number = %s',
                    $currentPost
                )
            );

            if (! empty($local)) {
                $date = $local[0]->date;
                break;
            }

            // Try usenet
            $header = $nntp->getXOVER((string) $currentPost);
            if (! NNTPService::isError($header) && isset($header[0]['Date']) && $header[0]['Date'] !== '') {
                $date = $header[0]['Date'];
                break;
            }

            // Try a different article number
            $currentPost = $this->getNextArticleToTry($currentPost, $groupData);
            if ($currentPost === null) {
                break;
            }
        } while ($attempts++ <= 20);

        if (! $date) {
            return time();
        }

        $timestamp = strtotime($date);

        return $timestamp !== false ? $timestamp : time();
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

        $firstDate = $this->postdate($data['first'], $data);
        if ($goalTime < $firstDate) {
            return $data['first'];
        }

        $lastDate = $this->postdate($data['last'], $data);
        if ($goalTime > $lastDate) {
            return $data['last'];
        }

        if ($this->config->echoCli) {
            cli()->primary(
                'Searching for an approximate article number for group '.$data['group'].' '.$days.' days back.'
            );
        }

        return $this->binarySearchArticleByDate($goalTime, $data);
    }

    // ==================== Private Helper Methods ====================

    private function selectNntpGroup(array &$groupMySQL, NNTPService $nntp): ?array
    {
        $groupNNTP = $nntp->selectGroup($groupMySQL['name']);

        if (NNTPService::isError($groupNNTP)) {
            $groupNNTP = $nntp->dataError($nntp, $groupMySQL['name']);

            if (isset($groupNNTP['code']) && (int) $groupNNTP['code'] === 411) {
                UsenetGroup::disableIfNotExist($groupMySQL['id']);
            }

            if (NNTPService::isError($groupNNTP)) {
                return null;
            }
        }

        return $groupNNTP;
    }

    private function calculateArticleRange(array $groupMySQL, array $groupNNTP, int $maxHeaders): array
    {
        if ((int) $groupMySQL['last_record'] === 0) {
            return $this->calculateNewGroupRange($groupNNTP);
        }

        return $this->calculateExistingGroupRange($groupMySQL, $groupNNTP, $maxHeaders);
    }

    private function calculateNewGroupRange(array $groupNNTP): array
    {
        if ($this->config->newGroupScanByDays) {
            $first = (int) $this->daytopost($this->config->newGroupDaysToScan, $groupNNTP);
        } elseif ($groupNNTP['first'] >= ($groupNNTP['last'] - ($this->config->newGroupMessagesToScan + $this->config->messageBuffer))) {
            $first = (int) $groupNNTP['first'];
        } else {
            $first = (int) ($groupNNTP['last'] - ($this->config->newGroupMessagesToScan + $this->config->messageBuffer));
        }

        $leaveOver = $this->config->messageBuffer;
        $last = $groupLast = (int) ($groupNNTP['last'] - $leaveOver);

        if ($last < $first) {
            $last = $groupLast = $first;
        }

        $total = (int) ($groupLast - $first);
        $realTotal = (int) ($groupNNTP['last'] - $first);

        return [
            'first' => $first,
            'last' => $last,
            'groupLast' => $groupLast,
            'total' => $total,
            'realTotal' => $realTotal,
            'leaveOver' => $leaveOver,
            'isNew' => true,
        ];
    }

    private function calculateExistingGroupRange(array $groupMySQL, array $groupNNTP, int $maxHeaders): array
    {
        $first = (int) $groupMySQL['last_record'];
        $totalCount = (int) ($groupNNTP['last'] - $first);

        if ($totalCount > ($this->config->messageBuffer * 2)) {
            $leaveOver = (int) round($totalCount % $this->config->messageBuffer, 0, PHP_ROUND_HALF_DOWN) + $this->config->messageBuffer;
        } else {
            $leaveOver = (int) round($totalCount / 2, 0, PHP_ROUND_HALF_DOWN);
        }

        $last = $groupLast = (int) ($groupNNTP['last'] - $leaveOver);

        if ($last < $first) {
            $last = $groupLast = $first;
        }

        $total = (int) ($groupLast - $first);
        $realTotal = (int) ($groupNNTP['last'] - $first);

        // Apply max headers limit
        if ($maxHeaders > 0 && $maxHeaders < ($groupLast - $first)) {
            $groupLast = $last = (int) ($first + $maxHeaders);
            $total = (int) ($groupLast - $first);
        }

        return [
            'first' => $first,
            'last' => $last,
            'groupLast' => $groupLast,
            'total' => $total,
            'realTotal' => $realTotal,
            'leaveOver' => $leaveOver,
            'isNew' => false,
        ];
    }

    private function processArticleRange(array &$groupMySQL, array $groupNNTP, array $range): void
    {
        $first = (int) $range['first'];
        $last = (int) $range['last'];
        $groupLast = (int) $range['groupLast'];
        $done = false;

        while (! $done) {
            // Calculate chunk bounds
            if ($range['total'] > $this->config->messageBuffer) {
                $last = (int) min($first + $this->config->messageBuffer, $groupLast);
            }

            $first++;

            if ($this->config->echoCli) {
                cli()->header(
                    PHP_EOL.'Getting '.number_format($last - $first + 1).' articles ('.number_format($first).
                    ' to '.number_format($last).') from '.$groupMySQL['name'].' - ('.
                    number_format($groupLast - $last).' articles in queue).'
                );
            }

            // Scan this chunk
            $scanSummary = $this->scan($groupMySQL, $first, $last);

            // Update group record
            $this->updateGroupAfterScan($groupMySQL, $groupNNTP, $scanSummary, $last);

            if ($last === $groupLast) {
                $done = true;
            } else {
                $first = $last;
            }
        }
    }

    private function updateGroupAfterScan(array &$groupMySQL, array $groupNNTP, array $scanSummary, int $last): void
    {
        if (! empty($scanSummary)) {
            // New group - update first record
            if ($groupMySQL['first_record_postdate'] === null && (int) $groupMySQL['first_record'] === 0) {
                $groupMySQL['first_record'] = $scanSummary['firstArticleNumber'];
                $firstArticleTimestamp = isset($scanSummary['firstArticleDate'])
                    ? strtotime($scanSummary['firstArticleDate'])
                    : $this->postdate($groupMySQL['first_record'], $groupNNTP);
                $groupMySQL['first_record_postdate'] = $firstArticleTimestamp !== false ? $firstArticleTimestamp : time();

                UsenetGroup::query()->where('id', $groupMySQL['id'])->update([
                    'first_record' => $scanSummary['firstArticleNumber'],
                    'first_record_postdate' => Carbon::createFromTimestamp($groupMySQL['first_record_postdate'], date_default_timezone_get()),
                ]);
            }

            $lastArticleTimestamp = isset($scanSummary['lastArticleDate'])
                ? strtotime($scanSummary['lastArticleDate'])
                : $this->postdate($scanSummary['lastArticleNumber'], $groupNNTP);
            $lastArticleDate = $lastArticleTimestamp !== false ? $lastArticleTimestamp : time();

            UsenetGroup::query()->where('id', $groupMySQL['id'])->update([
                'last_record' => $scanSummary['lastArticleNumber'],
                'last_record_postdate' => Carbon::createFromTimestamp($lastArticleDate, date_default_timezone_get()),
                'last_updated' => now(),
            ]);
        } else {
            UsenetGroup::query()->where('id', $groupMySQL['id'])->update([
                'last_record' => $last,
                'last_updated' => now(),
            ]);
        }
    }

    private function downloadHeaders(bool $partRepair): ?array
    {
        $nntp = $this->getNntp();

        if ($partRepair) {
            $headers = $nntp->getOverview($this->first.'-'.$this->last, true, false);
        } else {
            $headers = $nntp->getXOVER($this->first.'-'.$this->last);
        }

        if (NNTPService::isError($headers) || ! \is_array($headers)) {
            if ($partRepair) {
                return null;
            }

            // Retry without compression
            $nntp->doQuit();
            if ($nntp->doConnect(false) !== true) {
                return null;
            }

            $nntp->selectGroup($this->groupMySQL['name']);
            $headers = $nntp->getXOVER($this->first.'-'.$this->last);
            $nntp->enableCompression();

            if (NNTPService::isError($headers) || ! \is_array($headers)) {
                if (\is_object($headers) && isset($headers->code, $headers->message)) {
                    $message = ((int) $headers->code === 0 ? 'Unknown error' : $headers->message);
                    $this->log("Code {$headers->code}: $message\nSkipping group: {$this->groupMySQL['name']}", __FUNCTION__, 'error');
                } else {
                    $this->log("Unknown error\nSkipping group: {$this->groupMySQL['name']}", __FUNCTION__, 'error');
                }

                return null;
            }
        }

        return $headers;
    }

    private function handlePartRepairTracking(array $headersNotInserted, array $parsedHeaders): void
    {
        $notInsertedCount = \count($headersNotInserted);
        if ($notInsertedCount > 0) {
            $this->missedPartHandler->addMissingParts($headersNotInserted, $this->groupMySQL['id']);
            $this->log($notInsertedCount.' articles failed to insert!', __FUNCTION__, 'warning');
        }

        // Check for missing headers in range
        $expectedCount = $this->last - $this->first - $this->notYEnc - $this->headersBlackListed + 1;
        if ($expectedCount > \count($this->headersReceived)) {
            $rangeNotReceived = array_diff(range($this->first, $this->last), $this->headersReceived);
            $notReceivedCount = \count($rangeNotReceived);

            if ($notReceivedCount > 0) {
                $this->missedPartHandler->addMissingParts($rangeNotReceived, $this->groupMySQL['id']);

                if ($this->config->echoCli) {
                    cli()->alternate(
                        'Server did not return '.$notReceivedCount.' articles from '.$this->groupMySQL['name'].'.'
                    );
                }
            }
        }
    }

    private function groupMissingPartsIntoRanges(array $missingParts): array
    {
        $ranges = [];
        $partList = [];
        $firstPart = $lastNum = $missingParts[0]->numberid;

        foreach ($missingParts as $part) {
            if (($part->numberid - $firstPart) > ($this->config->messageBuffer / 4)) {
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

        return $ranges;
    }

    private function getNextArticleToTry(int $currentPost, array $groupData): ?int
    {
        if (abs($currentPost - $groupData['first']) > abs($groupData['last'] - $currentPost)) {
            $tempPost = (int) round($currentPost / (random_int(1005, 1012) / 1000), 0, PHP_ROUND_HALF_UP);
            if ($tempPost < $groupData['first']) {
                $tempPost = $groupData['first'];
            }
        } else {
            $tempPost = (int) round((random_int(1005, 1012) / 1000) * $currentPost, 0, PHP_ROUND_HALF_UP);
            if ($tempPost > $groupData['last']) {
                $tempPost = $groupData['last'];
            }
        }

        // If we got the same article number, give up
        if ($tempPost === $currentPost) {
            return null;
        }

        return $tempPost;
    }

    private function binarySearchArticleByDate(int $goalTime, array $data): string
    {
        $wantedArticle = (int) round(($data['last'] + $data['first']) / 2);
        $aMax = $data['last'];
        $aMin = $data['first'];
        $oldArticle = $articleTime = null;

        while (true) {
            if ($wantedArticle <= $data['first'] || $wantedArticle >= $data['last']) {
                break;
            }

            $reallyOldArticle = $oldArticle;
            $oldArticle = $wantedArticle;

            $articleTime = $this->postdate($wantedArticle, $data);

            if (! $articleTime) {
                $wantedArticle = random_int($aMin, $aMax);
                $articleTime = $this->postdate($wantedArticle, $data);
            }

            if ($articleTime < $goalTime) {
                $aMin = $oldArticle;
                $wantedArticle = (int) round(($aMax + $oldArticle) / 2);
                if ($this->config->echoCli) {
                    echo '-';
                }
            } elseif ($articleTime > $goalTime) {
                $aMax = $oldArticle;
                $wantedArticle = (int) round(($aMin + $oldArticle) / 2);
                if ($this->config->echoCli) {
                    echo '+';
                }
            } else {
                break;
            }

            if ($reallyOldArticle === $wantedArticle && ($goalTime - $articleTime) <= 0) {
                break;
            }
        }

        if ($this->config->echoCli) {
            $goalCarbon = Carbon::createFromTimestamp($goalTime, date_default_timezone_get());
            $articleCarbon = Carbon::createFromTimestamp($articleTime, date_default_timezone_get());
            $diffDays = $goalCarbon->diffInDays($articleCarbon, true);
            cli()->primary(
                PHP_EOL.'Found article #'.$wantedArticle.' which has a date of '.date('r', $articleTime).
                ', vs wanted date of '.date('r', $goalTime).'. Difference from goal is '.$diffDays.' days.'
            );
        }

        return (string) $wantedArticle;
    }

    // ==================== Output Methods ====================

    private function outputNoNewArticles(array $groupMySQL, array $groupNNTP, array $range): void
    {
        if ($this->config->echoCli) {
            cli()->primary(
                'No new articles for '.$groupMySQL['name'].' (first '.number_format((int) $range['first']).
                ', last '.number_format((int) $range['last']).', grouplast '.number_format((int) $groupMySQL['last_record']).
                ', total '.number_format((int) $range['total']).")\n".'Server oldest: '.number_format((int) $groupNNTP['first']).
                ' Server newest: '.number_format((int) $groupNNTP['last']).' Local newest: '.number_format((int) $groupMySQL['last_record'])
            );
        }
    }

    private function outputNewArticlesInfo(array $groupMySQL, array $groupNNTP, array $range): void
    {
        if (! $this->config->echoCli) {
            return;
        }

        $message = $range['isNew']
            ? 'New group '.$groupNNTP['group'].' starting with '.
              ($this->config->newGroupScanByDays
                  ? $this->config->newGroupDaysToScan.' days'
                  : number_format($this->config->newGroupMessagesToScan).' messages').' worth.'
            : 'Group '.$groupNNTP['group'].' has '.number_format((int) $range['realTotal']).' new articles.';

        cli()->primary(
            $message.
            ' Leaving '.number_format((int) $range['leaveOver']).
            " for next pass.\nServer oldest: ".number_format((int) $groupNNTP['first']).
            ' Server newest: '.number_format((int) $groupNNTP['last']).
            ' Local newest: '.number_format((int) $groupMySQL['last_record'])
        );
    }

    private function outputHeaderInitial(): void
    {
        cli()->primary(
            'Received '.\count($this->headersReceived).
            ' articles of '.number_format($this->last - $this->first + 1).' requested, '.
            $this->headersBlackListed.' blacklisted, '.$this->notYEnc.' not yEnc.'
        );
    }

    private function outputHeaderDuration(): void
    {
        if (! $this->config->echoCli) {
            return;
        }

        $currentMicroTime = now();
        cli()->alternateOver(number_format($this->timeHeaders, 2).'s').
        cli()->primaryOver(' to download articles, ').
        cli()->alternateOver(number_format($this->timeCleaning, 2).'s').
        cli()->primaryOver(' to process collections, ').
        cli()->alternateOver(number_format($this->timeInsert, 2).'s').
        cli()->primaryOver(' to insert binaries/parts, ').
        cli()->alternateOver(number_format($currentMicroTime->diffInSeconds($this->startPR, true), 2).'s').
        cli()->primaryOver(' for part repair, ').
        cli()->alternateOver(number_format($currentMicroTime->diffInSeconds($this->startLoop, true), 2).'s').
        cli()->primary(' total.');
    }

    // ==================== Logging Methods ====================

    private function log(string $message, string $method, string $color): void
    {
        if ($this->config->echoCli) {
            cli()->$color($message.' ['.__CLASS__."::$method]");
        }
    }

    private function logError(string $message): void
    {
        if ($this->config->echoCli) {
            cli()->error($message);
        }
        if (config('app.debug')) {
            Log::error($message);
        }
    }
}
