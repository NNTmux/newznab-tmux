<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Predb;
use Illuminate\Console\Command;

class PredbCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'predb:check {limit? : Maximum number of releases to check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check releases against PreDB for matches';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = $this->argument('limit');

        if ($limit !== null && ! is_numeric($limit)) {
            $this->error('Limit must be a number');

            return Command::FAILURE;
        }

        $this->info('Checking releases against PreDB...');

        try {
            Predb::checkPre($limit ? (int) $limit : false);

            $this->info('✅ PreDB check complete');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('PreDB check failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
