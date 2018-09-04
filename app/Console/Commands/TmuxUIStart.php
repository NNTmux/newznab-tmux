<?php

namespace App\Console\Commands;

use App\Models\Settings;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

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
    protected $description = 'Start the processing of tmux scripts. This is functionally equivalent to setting the
\'tmux running\' setting in admin.';

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
    public function handle()
    {
        $tmux_session = Settings::settingValue('site.tmux.tmux_session') ?? 0;
        $process = new Process('php misc/update/tmux/start.php');
        $process->setPty(Process::isPtySupported());
        $process->run();
        if ($process->isSuccessful()) {
            $process->setCommandLine('tmux attach-session -t '.$tmux_session);
            $process->run();
        }
    }
}
