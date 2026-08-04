<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TmuxPaneRole;
use App\Models\Settings;
use App\Services\Tmux\TmuxPaneManager;
use App\Services\Tmux\TmuxSessionManager;
use Illuminate\Console\Command;
use RuntimeException;

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

        // Select monitor pane before attaching so user lands there
        $paneManager = new TmuxPaneManager($sessionName);
        try {
            $monitorPane = $paneManager->paneForRole(TmuxPaneRole::Monitor, '0.0');
        } catch (RuntimeException $exception) {
            $this->error('❌ '.$exception->getMessage());

            return Command::FAILURE;
        }

        if (! $paneManager->selectWindow(0) || ! $paneManager->selectPane($monitorPane)) {
            $this->error('❌ Unable to select the tmux monitor pane.');

            return Command::FAILURE;
        }

        return $sessionManager->attachSession() ? Command::SUCCESS : Command::FAILURE;
    }
}
