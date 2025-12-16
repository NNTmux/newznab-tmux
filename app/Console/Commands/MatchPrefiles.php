<?php

namespace App\Console\Commands;

use App\Services\NameFixing\NameFixingService;
use Exception;
use Illuminate\Console\Command;

class MatchPrefiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'match:prefiles
                                 {limit=full : Number of releases to process or "full" for all}
                                 {--show : Display the changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tries to match release filenames to PreDB filenames';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $limit = $this->argument('limit');

        // Validate the limit argument
        if ($limit !== 'full' && ! is_numeric($limit)) {
            $this->error('Limit must be "full" or a numeric value.');

            return 1;
        }

        // Build the arguments array for NameFixingService
        $argv = [
            'match_prefiles.php',
            $limit,
        ];

        if ($this->option('show')) {
            $argv[] = 'show';
        }

        try {
            $nameFixingService = new NameFixingService;
            $nameFixingService->getPreFileNames($argv);

            return 0;
        } catch (Exception $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }
}
