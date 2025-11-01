<?php

namespace App\Console\Commands;

use Blacklight\libraries\Forking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessBinaries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'multiprocessing:binaries
                            {max=0 : Maximum new headers to download per group (0 = no limit)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download new headers for all active groups using multiprocessing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $max = (int) $this->argument('max');

        try {
            (new Forking)->processWorkType('binaries', [0 => $max]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}

