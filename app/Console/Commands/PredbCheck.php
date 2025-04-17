<?php

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
    protected $signature = 'predb:check {limit?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check PreDB releases for matching';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = $this->argument('limit');

        Predb::checkPre(is_numeric($limit) ? (int) $limit : false);

        return self::SUCCESS;
    }
}
