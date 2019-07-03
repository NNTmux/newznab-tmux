<?php

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
    protected $signature = 'nntmux:update-expired-roles {--p|period=* : Update expired roles, add the switch to check user that will have their accounts expire in future}';

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
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Updating expired roles.');
        if (empty($this->option('period'))) {
            $this->info('Updating users who have their role expired');
            User::updateExpiredRoles();
        } else {
            $days = $this->option('period')[0];
            $this->info('Updating users that will have their role expire in '.$days.' days.');
            User::updateExpiredRoles($days);
        }
        $this->info('Expired roles updated.');
    }
}
