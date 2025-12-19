<?php

namespace App\Console\Commands;

use App\Services\ForkingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessBackfill extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'multiprocessing:backfill
                            {limit? : Limit article count (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill all backfill-enabled groups using multiprocessing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = $this->argument('limit');

        $options = [];
        if (isset($limit) && is_numeric($limit) && $limit > 0) {
            $options = [0 => (int) $limit];
        } else {
            $options = [0 => false];
        }

        try {
            (new ForkingService)->backfill($options);

            return self::SUCCESS;
        } catch (\Exception $e) {
            Log::error($e->getTraceAsString());
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
