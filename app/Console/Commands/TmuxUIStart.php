<?php

namespace App\Console\Commands;

use Blacklight\Tmux;
use App\Models\Settings;
use Blacklight\ColorCLI;
use Illuminate\Console\Command;

class TmuxUIStart extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tmux-ui:start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the processing of tmux scripts.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tmux = new Tmux();
        $tmux_session = Settings::settingValue('site.tmux.tmux_session') ?? 0;

        // Set running value to on.
        $tmux->startRunning();

        // Create a placeholder session so tmux commands do not throw server not found errors.
        exec('tmux new-session -ds placeholder 2>/dev/null');

        //check if session exists
        $session = shell_exec("tmux list-session | grep $tmux_session");
        // Kill the placeholder
        exec('tmux kill-session -t placeholder');
        if ($session === null) {
            ColorCLI::info('Starting the tmux server and monitor script.');
            passthru('php '.app()->/* @scrutinizer ignore-call */ path().'/../misc/update/tmux/run.php');
        }
    }
}
