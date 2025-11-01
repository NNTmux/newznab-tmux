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
    protected $description = 'Restart processing of tmux scripts (deprecated - use tmux:stop && tmux:start)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->warn('⚠️  This command is deprecated. Use "php artisan tmux:stop && php artisan tmux:start" instead.');

        $this->call('tmux:stop', ['--force' => true]);
        sleep(2);

        return $this->call('tmux:start');
    }
}
