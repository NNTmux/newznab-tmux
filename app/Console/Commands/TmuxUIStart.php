<?php

namespace App\Console\Commands;

use App\Models\Settings;
use Blacklight\Tmux;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

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
    public function handle(): void
    {
        $tmux = new Tmux;
        $tmux_session = Settings::settingValue('tmux_session') ?? 0;

        // Set running value to on.
        $tmux->startRunning();

        //check if session exists
        $session = Process::run("tmux list-session | grep $tmux_session");
        if ($session->exitCode() === 1) {
            $this->info('Starting the tmux server and monitor script');
            Process::forever()->tty()->run('php '.app()->/* @scrutinizer ignore-call */ path().'/../misc/update/tmux/run.php')->output();
        } else {
            $this->error("tmux session: '".$tmux_session."' is already running, aborting.");
            exit();
        }
    }
}
