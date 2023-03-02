<?php

namespace App\Console\Commands;

use App\Models\Settings;
use Blacklight\Tmux;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

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
    protected $description = 'Stop the processing of tmux scripts.';

    /**
     * @throws \Exception
     */
    public function handle()
    {
        $tmux = new Tmux();
        $tmux->stopIfRunning();
        if ($this->option('kill') === true) {
            $sessionName = Settings::settingValue('site.tmux.tmux_session');
            $tmuxSession = Process::run('tmux kill-session -t '.$sessionName);
            $this->info('Killing active tmux session: '.$sessionName);
            if ($tmuxSession->successful()) {
                $this->info('Tmux session killed successfully');
            } else {
                $this->info('No valid tmux sessions found');
            }
        }
    }
}
