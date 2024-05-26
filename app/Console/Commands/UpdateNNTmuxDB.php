<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateNNTmuxDB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:db';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update NNTmux database with new patches';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): void
    {
        // also prevent web access.
        $this->info('Updating database');
        if (config('app.env') !== 'production') {
            $this->call('migrate');
        } else {
            $this->call('migrate', ['--force' => true]);
        }
    }
}
