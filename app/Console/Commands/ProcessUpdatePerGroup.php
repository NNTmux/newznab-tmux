<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ForkingService;
use Illuminate\Console\Command;

class ProcessUpdatePerGroup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'multiprocessing:update-per-group';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download headers, backfill, create releases, and post-process per group using multiprocessing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            (new ForkingService)->updatePerGroup();

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
