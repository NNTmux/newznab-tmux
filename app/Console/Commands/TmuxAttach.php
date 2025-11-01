<?php

namespace App\Console\Commands;

use App\Models\Settings;
use App\Services\Tmux\TmuxSessionManager;
use Illuminate\Console\Command;

class TmuxAttach extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tmux:attach
                            {--session= : Tmux session name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Attach to the tmux processing session';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sessionName = $this->option('session')
            ?? Settings::settingValue('tmux_session')
            ?? config('tmux.session.default_name', 'nntmux');

        $sessionManager = new TmuxSessionManager($sessionName);

        if (! $sessionManager->sessionExists()) {
            $this->error("❌ Session '{$sessionName}' does not exist");
            $this->info("💡 Run 'php artisan tmux:start' to create it");

            return Command::FAILURE;
        }

        $this->info("📎 Attaching to session '{$sessionName}'...");
        $this->info('💡 Press Ctrl+A then D to detach');

        // Execute tmux attach
        passthru("tmux attach -t {$sessionName}");

        return Command::SUCCESS;
    }
}
