<?php

namespace App\Console\Commands;

use Blacklight\libraries\Forking;
use Illuminate\Console\Command;

class ProcessSafe extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'multiprocessing:safe
                            {type : Type: binaries or backfill}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safe binaries or backfill update using multiprocessing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->argument('type');

        if (! \in_array($type, ['backfill', 'binaries'], true)) {
            $this->error('Type must be either: binaries or backfill');
            $this->line('');
            $this->line('binaries => Do Safe Binaries update');
            $this->line('backfill => Do Safe Backfill update');

            return self::FAILURE;
        }

        try {
            (new Forking)->processWorkType('safe_'.$type);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
