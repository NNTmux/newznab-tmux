<?php

namespace App\Console\Commands;

use Blacklight\libraries\Forking;
use Illuminate\Console\Command;

class ProcessPostProcess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'multiprocessing:postprocess
                            {type : Type: ama, add, ani, mov, nfo, sha, or tv}
                            {renamed=false : For mov/tv: only post-process renamed releases (true/false)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '[DEPRECATED] Use update:postprocess instead. Post-process releases using multiprocessing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->warn('⚠️  WARNING: This command is DEPRECATED and will be removed in a future version.');
        $this->warn('    Please use "update:postprocess" instead.');
        $this->line('');

        $type = $this->argument('type');
        $renamed = $this->argument('renamed');

        if (! \in_array($type, ['ama', 'add', 'ani', 'mov', 'nfo', 'sha', 'tv'], true)) {
            $this->error('Type must be one of: ama, add, ani, mov, nfo, sha, tv');
            $this->line('');
            $this->line('ama => Do amazon/books processing');
            $this->line('add => Do additional (rar|zip) processing');
            $this->line('ani => Do anime processing');
            $this->line('mov => Do movie processing');
            $this->line('nfo => Do NFO processing');
            $this->line('sha => Do sharing processing (no multiprocessing)');
            $this->line('tv  => Do TV processing');

            return self::FAILURE;
        }

        try {
            $options = [];
            if ($renamed === 'true' || $renamed === true) {
                $options = [0 => true];
            }

            (new Forking)->processWorkType('postProcess_'.$type, $options);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
