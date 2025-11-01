<?php

namespace App\Console\Commands;

use Blacklight\libraries\Forking;
use Illuminate\Console\Command;

class ProcessReleasesMulti extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'multiprocessing:releases';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create releases, delete unwanted releases, and categorize using multiprocessing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            (new Forking)->processWorkType('releases');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
