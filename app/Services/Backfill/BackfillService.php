<?php

declare(strict_types=1);

namespace App\Services\Backfill;

use App\Models\UsenetGroup;
use App\Services\Binaries\BinariesService;
use App\Services\NNTP\NNTPService;
use Blacklight\ColorCLI;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service for backfilling Usenet groups with historical articles.
 *
 * This service handles downloading older articles from Usenet groups
 * to fill in historical data. It supports:
 * - Backfilling by article count or target date
 * - Safe backfill with date-based targeting
 * - Automatic group disable when backfill limit is reached
 */
final class BackfillService
{
    private const DEFAULT_ARTICLE_COUNT = 20000;

    private BackfillConfig $config;

    private BinariesService $binaries;

    private NNTPService $nntp;

    private ColorCLI $colorCli;

    public function __construct(
        ?BackfillConfig $config = null,
        ?BinariesService $binaries = null,
        ?NNTPService $nntp = null,
        ?ColorCLI $colorCli = null,
    ) {
        $this->config = $config ?? BackfillConfig::fromSettings();
        $this->binaries = $binaries ?? new BinariesService;
        $this->nntp = $nntp ?? new NNTPService;
        $this->colorCli = $colorCli ?? new ColorCLI;
    }

    /**
     * Backfill all groups or a specific group.
     *
     * @param  string  $groupName  Optional specific group to backfill
     * @param  int|string  $articles  Number of articles to backfill, or empty for date-based
     * @param  string  $type  Backfill type filter
     *
     * @throws \Throwable
     */
    public function backfillAllGroups(string $groupName = '', int|string $articles = '', string $type = ''): void
    {
        $groups = $this->getGroupsToBackfill($groupName, $type);

        if ($groups === []) {
            $this->log('No groups specified. Ensure groups are added to database for updating.', 'warning');

            return;
        }

        $groupCount = \count($groups);
        $this->logBackfillStart($groupCount);

        $articles = $this->normalizeArticleCount($articles);
        $startTime = now();

        foreach ($groups as $index => $group) {
            $this->logGroupProgress($groupName, $index + 1, $groupCount);
            $this->backfillGroup($group->toArray(), $groupCount - $index - 1, $articles);
        }

        $this->logBackfillComplete($startTime);
    }

    /**
     * Backfill a single group.
     *
     * @param  array  $groupArr  Group data array
     * @param  int  $remainingGroups  Number of groups remaining after this one
     * @param  int|string  $articles  Number of articles to backfill, or empty for date-based
     *
     * @throws \Throwable
     */
    public function backfillGroup(array $groupArr, int $remainingGroups, int|string $articles = ''): void
    {
        $startTime = now();
        $this->binaries->logIndexerStart();

        $shortGroupName = $this->getShortGroupName($groupArr['name']);

        if (! $this->validateGroupState($groupArr, $shortGroupName)) {
            return;
        }

        $serverData = $this->selectNntpGroup($groupArr['name']);
        if ($serverData === null) {
            return;
        }

        $this->log("Processing {$shortGroupName}", 'primary');

        $targetPost = $this->calculateTargetPost($groupArr, $articles, $serverData);

        if (! $this->validateTargetPost($groupArr, $targetPost, $serverData, $shortGroupName)) {
            return;
        }

        $this->logGroupInfo($groupArr, $serverData, $targetPost, $shortGroupName);

        $this->processBackfillChunks($groupArr, $targetPost, $remainingGroups, $shortGroupName);

        $this->logGroupComplete($shortGroupName, $startTime);
    }

    /**
     * Safe backfill - backfill groups that haven't reached the safe backfill date.
     *
     * @param  int|string  $articles  Number of articles to backfill
     *
     * @throws \Throwable
     */
    public function safeBackfill(int|string $articles = ''): void
    {
        $group = UsenetGroup::query()
            ->whereBetween('first_record_postdate', [Carbon::createFromDate($this->config->safeBackFillDate), now()])
            ->where('backfill', '=', 1)
            ->select(['name'])
            ->orderBy('name')
            ->first();

        if ($group === null) {
            $message = sprintf(
                'No groups to backfill, they are all at the target date %s, or you have not enabled them to be backfilled in the groups page.',
                $this->config->safeBackFillDate
            );
            exit($message.PHP_EOL);
        }

        $this->backfillAllGroups($group->name, $articles);
    }

    /**
     * Get groups to backfill based on criteria.
     */
    private function getGroupsToBackfill(string $groupName, string $type): array
    {
        if ($groupName !== '') {
            $group = UsenetGroup::getByName($groupName);

            return $group ? [$group] : [];
        }

        return UsenetGroup::getActiveBackfill($type)->all();
    }

    /**
     * Normalize article count parameter.
     */
    private function normalizeArticleCount(int|string $articles): int|string
    {
        if ($articles !== '' && ! is_numeric($articles)) {
            return self::DEFAULT_ARTICLE_COUNT;
        }

        return $articles;
    }

    /**
     * Get shortened group name for display.
     */
    private function getShortGroupName(string $groupName): string
    {
        return str_replace('alt.binaries', 'a.b', $groupName);
    }

    /**
     * Validate that group is in a valid state for backfilling.
     */
    private function validateGroupState(array $groupArr, string $shortGroupName): bool
    {
        if ($groupArr['first_record'] <= 0) {
            $this->log(
                "You need to run update_binaries on {$shortGroupName}. Otherwise the group is dead, you must disable it.",
                'error'
            );

            return false;
        }

        return true;
    }

    /**
     * Select NNTP group and return server data.
     */
    private function selectNntpGroup(string $groupName): ?array
    {
        $data = $this->nntp->selectGroup($groupName);

        if ($this->nntp->isError($data)) {
            $data = $this->nntp->dataError($this->nntp, $groupName);
            if ($this->nntp->isError($data)) {
                return null;
            }
        }

        return $data;
    }

    /**
     * Calculate target post number based on articles count or date.
     */
    private function calculateTargetPost(array $groupArr, int|string $articles, array $serverData): int
    {
        $isArticleBased = $articles !== '';

        $targetPost = $isArticleBased
            ? (int) round($groupArr['first_record'] - (int) $articles)
            : (int) $this->binaries->daytopost($groupArr['backfill_target'], $serverData);

        // Ensure target is not below server's oldest article
        return max($targetPost, (int) $serverData['first']);
    }

    /**
     * Validate that target post is achievable.
     */
    private function validateTargetPost(array $groupArr, int $targetPost, array $serverData, string $shortGroupName): bool
    {
        if ($targetPost >= $groupArr['first_record'] || $groupArr['first_record'] <= $serverData['first']) {
            $message = "We have hit the maximum we can backfill for {$shortGroupName}";
            $message .= $this->config->disableBackfillGroup
                ? ', disabling backfill on it.'
                : ', skipping it, consider disabling backfill on it.';

            if ($this->config->disableBackfillGroup) {
                UsenetGroup::updateGroupStatus($groupArr['id'], 'backfill', 0);
            }

            $this->log($message, 'notice');

            return false;
        }

        return true;
    }

    /**
     * Process backfill in chunks.
     */
    private function processBackfillChunks(array $groupArr, int $targetPost, int $remainingGroups, string $shortGroupName): void
    {
        $messageBuffer = $this->binaries->getMessageBuffer();
        $last = $groupArr['first_record'] - 1;
        $first = max($last - $messageBuffer + 1, $targetPost);

        while (true) {
            $this->logChunkProgress($first, $last, $shortGroupName, $remainingGroups, $targetPost);

            flush();
            $scanResult = $this->binaries->scan($groupArr, $first, $last, $this->config->safePartRepair);

            $this->updateGroupRecord($groupArr, $first, $scanResult);

            if ($first === $targetPost) {
                break;
            }

            // Move to next chunk
            $last = $first - 1;
            $first = max($last - $messageBuffer + 1, $targetPost);
        }
    }

    /**
     * Update group record with new first_record and postdate.
     */
    private function updateGroupRecord(array $groupArr, int $first, ?array $scanResult): void
    {
        $newDate = isset($scanResult['firstArticleDate'])
            ? strtotime($scanResult['firstArticleDate'])
            : $this->binaries->postdate($first, $this->nntp->selectGroup($groupArr['name']));

        DB::update(
            'UPDATE usenet_groups SET first_record_postdate = FROM_UNIXTIME(?), first_record = ?, last_updated = NOW() WHERE id = ?',
            [$newDate, $first, $groupArr['id']]
        );
    }

    /**
     * Log message with appropriate styling.
     */
    private function log(string $message, string $type = 'primary'): void
    {
        if (! $this->config->echoCli) {
            return;
        }

        match ($type) {
            'header' => $this->colorCli->header($message),
            'warning' => $this->colorCli->warning($message),
            'error' => $this->colorCli->error($message),
            'notice' => $this->colorCli->notice($message),
            default => $this->colorCli->primary($message),
        };
    }

    /**
     * Log backfill start information.
     */
    private function logBackfillStart(int $groupCount): void
    {
        $compressionStatus = $this->config->compressedHeaders ? 'Yes' : 'No';
        $this->log("Backfilling: {$groupCount} group(s) - Using compression? {$compressionStatus}", 'header');
    }

    /**
     * Log group progress.
     */
    private function logGroupProgress(string $groupName, int $current, int $total): void
    {
        if ($groupName === '') {
            $this->log("Starting group {$current} of {$total}", 'header');
        }
    }

    /**
     * Log backfill completion.
     */
    private function logBackfillComplete(\Illuminate\Support\Carbon $startTime): void
    {
        $duration = now()->diffInSeconds($startTime, true);
        $this->log("Backfilling completed in {$duration} seconds.");
    }

    /**
     * Log group info before processing.
     */
    private function logGroupInfo(array $groupArr, array $serverData, int $targetPost, string $shortGroupName): void
    {
        $this->log(sprintf(
            "Group %s's oldest article is %s, newest is %s. Our target article is %s. Our oldest article is article %s.",
            $shortGroupName,
            number_format((float) $serverData['first']),
            number_format((float) $serverData['last']),
            number_format($targetPost),
            number_format((float) $groupArr['first_record'])
        ));
    }

    /**
     * Log chunk progress.
     */
    private function logChunkProgress(int $first, int $last, string $shortGroupName, int $remainingGroups, int $targetPost): void
    {
        $this->log(sprintf(
            'Getting %s articles from %s, %d group(s) left. (%s articles in queue)',
            number_format($last - $first + 1),
            $shortGroupName,
            $remainingGroups,
            number_format($first - $targetPost)
        ), 'header');
    }

    /**
     * Log group completion.
     */
    private function logGroupComplete(string $shortGroupName, \Illuminate\Support\Carbon $startTime): void
    {
        $duration = number_format(now()->timestamp - $startTime->timestamp, 2);
        $this->log(PHP_EOL."Group {$shortGroupName} processed in {$duration} seconds.");
    }
}

