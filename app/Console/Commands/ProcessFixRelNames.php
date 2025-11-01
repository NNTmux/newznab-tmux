<?php

namespace App\Console\Commands;

use Blacklight\libraries\Forking;
use Illuminate\Console\Command;

class ProcessFixRelNames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'multiprocessing:fixrelnames
                            {type : Type: standard or predbft}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix release names using multiprocessing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->argument('type');

        if (! \in_array($type, ['standard', 'predbft'], true)) {
            $this->error('Type must be either: standard or predbft');
            $this->line('');
            $this->line('standard => Attempt to fix release name using standard methods');
            $this->line('predbft  => Attempt to fix release name using Predb full text matching');

            return self::FAILURE;
        }

        try {
            (new Forking)->processWorkType('fixRelNames_'.$type, [0 => $type]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}

