<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class NntmuxUpdateExpiredRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:update-expired-roles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update users who\'s user role has expired';

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
        $this->info('Updating expired roles.');
        $this->info('Updating users that will have their roles expire or are already expired');
        User::updateExpiredRoles();
        $this->info('Expired roles updated.');
    }
}
