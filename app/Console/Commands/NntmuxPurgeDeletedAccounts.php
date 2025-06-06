<?php

namespace App\Console\Commands;

use App\Jobs\PurgeDeletedAccounts;
use Illuminate\Console\Command;

class NntmuxPurgeDeletedAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:purge-deleted-accounts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently remove accounts that were soft deleted 6 months ago';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting to purge accounts that were soft deleted 6 months ago...');

        PurgeDeletedAccounts::dispatch();

        $this->info('Job dispatched to purge deleted accounts');
        return Command::SUCCESS;
    }
}
