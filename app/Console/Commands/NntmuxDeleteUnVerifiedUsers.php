<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class NntmuxDeleteUnVerifiedUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:delete-unverified-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes unverified users from site';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Deleting unverified users.');
        User::deleteUnVerified();
        $this->info('Unverified users deleted.');
    }
}
