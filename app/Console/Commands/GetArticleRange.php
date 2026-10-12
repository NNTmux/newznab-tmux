<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\UsenetGroup;
use App\Services\Binaries\BinariesService;
use App\Services\Binaries\MissedPartHandler;
use App\Services\Configuration\ConfigurationProvider;
use App\Services\NNTP\NNTPService;
use App\Services\Releases\Verification\CandidateRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetArticleRange extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:get-range
                            {mode : Mode: binaries or backfill}
                            {group : Group name}
                            {first : First article number}
                            {last : Last article number}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get a range of article headers for a group';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $mode = $this->argument('mode');
        $groupName = $this->argument('group');
        $firstArticle = filter_var($this->argument('first'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $lastArticle = filter_var($this->argument('last'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (! \in_array($mode, ['binaries', 'backfill'], true)) {
            $this->error('Mode must be either "binaries" or "backfill".');

            return self::FAILURE;
        }

        if ($firstArticle === false || $lastArticle === false || $lastArticle < $firstArticle) {
            $this->error('Article range must contain positive integers with first no greater than last.');

            return self::FAILURE;
        }

        if (! app(CandidateRepository::class)->capacityAvailable()) {
            if (! $this->queueRangeForRepair($groupName, $firstArticle, $lastArticle)) {
                $this->error('Verification backlog is full and this range could not be queued for repair; retry this worker.');

                return self::FAILURE;
            }
            $this->warn('Verification backlog is full; this range was deferred to part repair.');

            return self::SUCCESS;
        }

        // Ranges run in parallel and each success moves the group's cursor to its own end, so a range
        // that fails before it has been scanned would be skipped for good; queue it for part repair.
        $scanned = false;

        try {
            $nntp = $this->getNntp();
            $group = UsenetGroup::getByName($groupName);
            $groupMySQL = $group?->toArray();

            if ($groupMySQL === null) {
                $this->error("Group not found: {$groupName}");

                return self::FAILURE;
            }

            if (NNTPService::isError($nntp->selectGroup($groupMySQL['name']))
                && NNTPService::isError($nntp->dataError($nntp, $groupMySQL['name']))) {
                $this->queueRangeForRepair($groupName, $firstArticle, $lastArticle);

                return self::FAILURE;
            }

            $binaries = new BinariesService;
            $binaries->setNntp($nntp);
            $scanned = true;
            $return = $binaries->scan(
                $groupMySQL,
                $firstArticle,
                $lastArticle,
                ((int) app(ConfigurationProvider::class)->ingestion()->safePartRepair === 1 ? 'update' : 'backfill')
            );

            if (empty($return)) {
                return self::SUCCESS;
            }

            $this->updateGroupRecords($mode, $groupMySQL, $return);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error($e->getTraceAsString());
            $this->error($e->getMessage());
            if (! $scanned) {
                $this->queueRangeForRepair($groupName, $firstArticle, $lastArticle);
            }

            return self::FAILURE;
        }
    }

    /**
     * Queue the whole range in the group's part repair list so a later pass fetches it.
     */
    private function queueRangeForRepair(string $groupName, int $first, int $last): bool
    {
        try {
            $groupId = (int) (UsenetGroup::query()->where('name', $groupName)->value('id') ?? 0);
            if ($groupId > 0) {
                (new MissedPartHandler)->addMissingRange($first, $last, $groupId);
                $this->warn("Queued articles {$first}-{$last} of {$groupName} for part repair.");

                return true;
            }
        } catch (\Throwable $e) {
            Log::error('Unable to queue a failed article range for part repair: '.$e->getMessage());
        }

        return false;
    }

    /**
     * Update group records based on mode.
     *
     * @param  array<string, mixed>  $groupMySQL
     * @param  array<string, mixed>  $return
     */
    private function updateGroupRecords(string $mode, array $groupMySQL, array $return): void
    {
        $columns = [];

        switch ($mode) {
            case 'binaries':
                if ($return['lastArticleNumber'] <= $groupMySQL['last_record']) {
                    return;
                }
                $unixTime = is_numeric($return['lastArticleDate'])
                    ? $return['lastArticleDate']
                    : strtotime($return['lastArticleDate']);
                $columns[1] = sprintf('last_record_postdate = FROM_UNIXTIME(%s)', $unixTime);
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
                    return;
                }
                $unixTime = is_numeric($return['firstArticleDate'])
                    ? $return['firstArticleDate']
                    : strtotime($return['firstArticleDate']);
                $columns[1] = sprintf('first_record_postdate = FROM_UNIXTIME(%s)', $unixTime);
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
                return;
        }

        DB::update($query);
    }

    /**
     * Get NNTP connection.
     */
    protected function getNntp(): NNTPService
    {
        $nntp = new NNTPService;

        $connectResult = config('nntmux_nntp.use_alternate_nntp_server') === true
            ? $nntp->doConnect(false, true)
            : $nntp->doConnect();

        if ($connectResult !== true) {
            $errorMessage = 'Unable to connect to usenet.';
            if (NNTPService::isError($connectResult)) {
                $errorMessage .= ' Error: '.$connectResult->getMessage();
            }
            throw new \Exception($errorMessage);
        }

        return $nntp;
    }
}
