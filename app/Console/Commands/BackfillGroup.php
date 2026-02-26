<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Settings;
use App\Services\Backfill\BackfillService;
use App\Services\NNTP\NNTPService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackfillGroup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backfill:group
                            {group : The group name to backfill}
                            {type=1 : Backfill type (1=interval, 2=all)}
                            {quantity? : Number of articles to backfill}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill a specific usenet group';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $group = $this->argument('group');
        $type = (int) $this->argument('type');
        $quantity = $this->argument('quantity');

        if (! \in_array($type, [1, 2], true)) {
            $this->error('Invalid backfill type. Must be 1 (interval) or 2 (all).');

            return self::FAILURE;
        }

        try {
            $nntp = $this->getNntp();

            if ($quantity === null) {
                $value = Settings::settingValue('backfill_qty');
                $quantity = ($type === 1 ? '' : $value);
            }

            $this->info("Backfilling group: {$group}");
            (new BackfillService(nntp: $nntp))->backfillAllGroups($group, $quantity);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error($e->getTraceAsString());
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Get NNTP connection.
     */
    private function getNntp(): NNTPService
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
