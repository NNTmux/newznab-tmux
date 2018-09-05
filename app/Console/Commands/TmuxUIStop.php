<?php

namespace App\Console\Commands;

use App\Models\Settings;
use Blacklight\Tmux;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class TmuxUIStop extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tmux-ui:stop {type}';

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
        if ($this->argument('type') !== null) {
            (new Tmux())->stopIfRunning();

            if ($this->argument('type') === 'true') {
                $sessionName = Settings::settingValue('site.tmux.tmux_session');
                $tmuxSession = new Process('tmux kill-session -t '.$sessionName);
                $this->info('Killing active tmux session: '.$sessionName);
                $tmuxSession->run();
                if ($tmuxSession->isSuccessful()) {
                    $this->info('Tmux session killed successfully');
                }
            }
        } else {
            $this->error($this->description);
        }
    }
}
