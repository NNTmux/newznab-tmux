<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Backfill\BackfillService;
use App\Services\BackgroundWorkPressureGate;
use App\Services\NNTP\NNTPService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class BackfillGroupBatch extends Command
{
    protected $signature = 'backfill:group-batch
                            {group : The group name to backfill}
                            {articles : Maximum articles to process in this batch}
                            {target-days=0 : Do not scan older than this many days}';

    protected $description = 'Process one bounded, pressure-aware safe-backfill batch';

    public function handle(BackgroundWorkPressureGate $pressureGate): int
    {
        $articles = (int) $this->argument('articles');
        $targetDays = (int) $this->argument('target-days');

        if ($articles < 1 || $articles > 1_000_000 || $targetDays < 0) {
            $this->error('Articles must be between 1 and 1000000 and target-days must be zero or greater.');

            return self::FAILURE;
        }

        try {
            $service = new BackfillService(nntp: $this->getNntp());
            $service->backfillBoundedGroup(
                (string) $this->argument('group'),
                $articles,
                $targetDays,
                $pressureGate,
            );

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
