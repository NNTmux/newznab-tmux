<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TmuxUIRestart extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tmux-ui:restart';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restart processing of tmux scripts completely';

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
        $this->call('tmux-ui:stop', ['--kill' => true]);
        $this->call('tmux-ui:start');
    }
}
