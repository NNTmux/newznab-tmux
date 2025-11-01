<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TmuxUIStop extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tmux-ui:stop {--k|kill : kill the session}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stop the processing of tmux scripts (deprecated - use tmux:stop)';

    /**
     * Execute the console command
     */
    public function handle(): int
    {
        $this->warn('⚠️  This command is deprecated. Use "php artisan tmux:stop" instead.');

        return $this->call('tmux:stop', [
            '--force' => $this->option('kill'),
        ]);
    }
}
