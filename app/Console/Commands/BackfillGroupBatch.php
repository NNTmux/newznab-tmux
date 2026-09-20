<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Settings;
use App\Models\UsenetGroup;
use App\Services\Backfill\SafeBackfillPlanner;
use App\Services\BackgroundWorkPressureGate;
use App\Services\Binaries\BinariesService;
use App\Services\NNTP\NNTPService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

final class BackfillGroupBatch extends Command
{
    protected $signature = 'backfill:group-batch
                            {group : The group name to backfill}
                            {articles : Maximum articles to process in this batch}
                            {target-days=0 : Do not scan older than this many days}';

    protected $description = 'Process one bounded safe-backfill batch through sequential articles:get-range chunks';

    public function handle(
        BackgroundWorkPressureGate $pressureGate,
        SafeBackfillPlanner $planner,
    ): int {
        $articles = (int) $this->argument('articles');
        $targetDays = (int) $this->argument('target-days');

        if ($articles < 1 || $articles > 1_000_000 || $targetDays < 0) {
            $this->error('Articles must be between 1 and 1000000 and target-days must be zero or greater.');

            return self::FAILURE;
        }

        try {
            $groupName = (string) $this->argument('group');
            $group = UsenetGroup::query()->where('name', $groupName)->first();
            if ($group === null) {
                $this->error('Backfill group not found: '.$groupName);

                return self::FAILURE;
            }

            $nntp = $this->getNntp();
            try {
                $serverData = $nntp->selectGroup($groupName);
                if (NNTPService::isError($serverData)) {
                    $serverData = $nntp->dataError($nntp, $groupName);
                }
                if (NNTPService::isError($serverData)) {
                    throw new \RuntimeException($serverData->getMessage());
                }

                $dateTarget = 0;
                if ($targetDays > 0) {
                    $binaries = new BinariesService;
                    $binaries->setNntp($nntp);
                    $dateTarget = (int) $binaries->daytopost($targetDays, $serverData);
                }
            } finally {
                $nntp->doQuit();
            }

            $targetPost = $planner->targetPost(
                (int) $group->first_record,
                $articles,
                (int) $serverData['first'],
                $dateTarget,
            );
            $configuredMaxMessages = (int) Settings::settingValue('maxmssgs');
            $maxMessages = max(1_000, min(100_000, $configuredMaxMessages > 0 ? $configuredMaxMessages : 10_000));

            foreach ($planner->ranges((int) $group->first_record, $targetPost, $maxMessages) as $range) {
                $pressureGate->awaitPermission(fn (string $reason) => $this->warn('Safe backfill paused: '.$reason));

                $process = new Process([
                    PHP_BINARY,
                    'artisan',
                    'articles:get-range',
                    'backfill',
                    $groupName,
                    (string) $range['first'],
                    (string) $range['last'],
                ], base_path());
                $process->setTimeout((int) config('nntmux.multiprocessing_max_child_time', 1800));
                $process->run(static function (string $type, string $buffer): void {
                    echo $buffer;
                });

                if (! $process->isSuccessful()) {
                    throw new \RuntimeException(sprintf(
                        'articles:get-range failed for %s (%d-%d): %s',
                        $groupName,
                        $range['first'],
                        $range['last'],
                        trim($process->getErrorOutput()),
                    ));
                }
            }

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            Log::error($exception->getMessage(), ['exception' => $exception]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function getNntp(): NNTPService
    {
        $nntp = new NNTPService;
        $connectResult = config('nntmux_nntp.use_alternate_nntp_server') === true
            ? $nntp->doConnect(false, true)
            : $nntp->doConnect();

        if ($connectResult !== true) {
            $message = 'Unable to connect to usenet.';
            if (NNTPService::isError($connectResult)) {
                $message .= ' Error: '.$connectResult->getMessage();
            }

            throw new \RuntimeException($message);
        }

        return $nntp;
    }
}
