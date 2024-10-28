<?php

namespace Blacklight;

use App\Models\Settings;
use App\Models\UsenetGroup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Class Backfill.
 */
class Backfill
{
    protected Binaries $_binaries;

    protected NNTP $_nntp;

    /**
     * Should we use compression for headers?
     */
    protected bool $_compressedHeaders;

    /**
     * Log and or echo debug.
     */
    protected bool $_debug = false;

    /**
     * Echo to cli?
     */
    protected bool $_echoCLI;

    /**
     * How far back should we go on safe back fill?
     */
    protected string $_safeBackFillDate;

    protected string $_safePartRepair;

    /**
     * Should we disable the group if we have backfilled far enough?
     */
    protected bool $_disableBackfillGroup;

    protected ColorCLI $colorCli;

    public function __construct()
    {
        $this->_echoCLI = config('nntmux.echocli');

        $this->_nntp = new NNTP;
        $this->_binaries = new Binaries;

        $this->colorCli = new ColorCLI;

        $this->_compressedHeaders = config('nntmux_nntp.compressed_headers');
        $this->_safeBackFillDate = Settings::settingValue('safebackfilldate') !== '' ? (string) Settings::settingValue('safebackfilldate') : '2012-08-14';
        $this->_safePartRepair = (int) Settings::settingValue('safepartrepair') === 1 ? 'update' : 'backfill';
        $this->_disableBackfillGroup = (int) Settings::settingValue('disablebackfillgroup') === 1;
    }

    /**
     * @throws \Throwable
     */
    public function backfillAllGroups(string $groupName = '', int|string $articles = '', string $type = ''): void
    {
        if ($groupName !== '') {
            $grp[] = UsenetGroup::getByName($groupName);
        } else {
            $grp = UsenetGroup::getActiveBackfill($type);
        }

        $groupCount = \count($grp);
        if ($groupCount > 0) {
            $counter = 1;
            $allTime = now();
            $dMessage = (
                'Backfilling: '.
                $groupCount.
                ' group(s) - Using compression? '.
                ($this->_compressedHeaders ? 'Yes' : 'No')
            );

            if ($this->_echoCLI) {
                $this->colorCli->header($dMessage);
            }

            if ($articles !== '' && ! is_numeric($articles)) {
                $articles = 20000;
            }

            // Loop through groups.
            foreach ($grp as $groupArr) {
                if ($groupName === '') {
                    $dMessage = 'Starting group '.$counter.' of '.$groupCount;

                    if ($this->_echoCLI) {
                        $this->colorCli->header($dMessage);
                    }
                }
                $this->backfillGroup($groupArr->toArray(), $groupCount - $counter, $articles);
                $counter++;
            }

            $dMessage = 'Backfilling completed in '.now()->diffInSeconds($allTime, true).' seconds.';

            if ($this->_echoCLI) {
                $this->colorCli->primary($dMessage);
            }
        } else {
            $dMessage = 'No groups specified. Ensure groups are added to database for updating.';

            if ($this->_echoCLI) {
                $this->colorCli->warning($dMessage);
            }
        }
    }

    /**
     * Backfill single group.
     *
     *
     * @throws \Throwable
     */
    public function backfillGroup(array $groupArr, int $left, int|string $articles = ''): void
    {
        // Start time for this group.
        $startGroup = now()->timestamp;

        $this->_binaries->logIndexerStart();

        $groupName = str_replace('alt.binaries', 'a.b', $groupArr['name']);

        // If our local oldest article 0, it means we never ran update_binaries on the group.
        if ($groupArr['first_record'] <= 0) {
            $dMessage =
                'You need to run update_binaries on '.
                $groupName.
                '. Otherwise the group is dead, you must disable it.';

            if ($this->_echoCLI) {
                $this->colorCli->error($dMessage);
            }

            return;
        }

        // Select group, here, only once
        $data = $this->_nntp->selectGroup($groupArr['name']);
        if ($this->_nntp->isError($data)) {
            $data = $this->_nntp->dataError($this->_nntp, $groupArr['name']);
            if ($this->_nntp->isError($data)) {
                return;
            }
        }

        if ($this->_echoCLI) {
            $this->colorCli->primary('Processing '.$groupName);
        }

        // Check if this is days or post backfill.
        $postCheck = $articles !== '';

        // Get target post based on date or user specified number.
        $targetpost = (string) (
            $postCheck
            ?
            round($groupArr['first_record'] - $articles)
            :
            $this->_binaries->daytopost($groupArr['backfill_target'], $data)
        );

        // Check if target post is smaller than server's oldest, set it to oldest if so.
        if ($targetpost < $data['first']) {
            $targetpost = $data['first'];
        }

        // Check if our target post is newer than our oldest post or if our local oldest article is older than the servers oldest.
        if ($targetpost >= $groupArr['first_record'] || $groupArr['first_record'] <= $data['first']) {
            $dMessage =
                'We have hit the maximum we can backfill for '.
                $groupName.
                ($this->_disableBackfillGroup ? ', disabling backfill on it.' :
                ', skipping it, consider disabling backfill on it.');

            if ($this->_disableBackfillGroup) {
                UsenetGroup::updateGroupStatus($groupArr['id'], 'backfill', 0);
            }

            if ($this->_echoCLI) {
                $this->colorCli->notice($dMessage);
            }

            return;
        }

        if ($this->_echoCLI) {
            $this->colorCli->primary(
                'Group '.
                    $groupName.
                    "'s oldest article is ".
                    number_format($data['first']).
                    ', newest is '.
                    number_format($data['last']).
                    '.Our target article is '.
                    number_format($targetpost).
                    '. Our oldest article is article '.
                    number_format($groupArr['first_record']).
                    '.'
            );
        }

        // Set first and last, moving the window by max messages.
        $last = ($groupArr['first_record'] - 1);
        // Set the initial "chunk".
        $first = ($last - $this->_binaries->messageBuffer + 1);

        // Just in case this is the last chunk we needed.
        if ($targetpost > $first) {
            $first = $targetpost;
        }

        $done = false;
        while ($done === false) {
            if ($this->_echoCLI) {
                $this->colorCli->header('Getting '.
                    number_format($last - $first + 1).
                    ' articles from '.
                    $groupName.
                    ', '.
                    $left.
                    ' group(s) left. ('.
                    number_format($first - $targetpost).
                    ' articles in queue');
            }

            flush();
            $lastMsg = $this->_binaries->scan($groupArr, $first, $last, $this->_safePartRepair);

            // Get the oldest date.
            if (isset($lastMsg['firstArticleDate'])) {
                // Try to get it from the oldest pulled article.
                $newdate = strtotime($lastMsg['firstArticleDate']);
            } else {
                // If above failed, try to get it with postdate method.
                $newdate = $this->_binaries->postdate($first, $data);
            }

            DB::update(
                sprintf(
                    '
					UPDATE usenet_groups
					SET first_record_postdate = FROM_UNIXTIME(%s), first_record = %s, last_updated = NOW()
					WHERE id = %d',
                    $newdate,
                    escapeString($first),
                    $groupArr['id']
                )
            );
            if ($first === $targetpost) {
                $done = true;
            } else {
                // Keep going: set new last, new first, check for last chunk.
                $last = ($first - 1);
                $first = ($last - $this->_binaries->messageBuffer + 1);
                if ($targetpost > $first) {
                    $first = $targetpost;
                }
            }
        }

        if ($this->_echoCLI) {
            $this->colorCli->primary(
                PHP_EOL.
                    'Group '.
                    $groupName.
                    ' processed in '.
                    number_format(now()->timestamp - $startGroup, 2).
                    ' seconds.'
            );
        }
    }

    /**
     * @throws \Throwable
     */
    public function safeBackfill(int|string $articles = ''): void
    {
        $groupName = UsenetGroup::query()
            ->whereBetween('first_record_postdate', [Carbon::createFromDate($this->_safeBackFillDate), now()])
            ->where('backfill', '=', 1)
            ->select(['name'])
            ->orderBy('name')
            ->first();

        if ($groupName === null) {
            $dMessage =
                'No groups to backfill, they are all at the target date '.
                $this->_safeBackFillDate.
                ', or you have not enabled them to be backfilled in the groups page.'.PHP_EOL;
            exit($dMessage);
        }
        $this->backfillAllGroups($groupName['name'], $articles);
    }
}
