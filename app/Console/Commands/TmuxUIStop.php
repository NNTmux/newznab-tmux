<?php

namespace App\Console\Commands;

use App\Models\Tmux;
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
    protected $description = 'Stop the processing of tmux scripts. This is functionally equivalent to unsetting the
\'tmux running\' setting in admin. Usage: tmux-ui:stop false (does not kill tmux session), while using true will kill current tmux session';

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
     *
     * @return mixed
     * @throws \Symfony\Component\Process\Exception\InvalidArgumentException
     * @throws \Symfony\Component\Process\Exception\LogicException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     * @throws \RuntimeException
     */
    public function handle()
    {
        if ($this->argument('type') === 'false' || $this->argument('type') === 'true') {
            $process = new Process('php misc/update/tmux/stop.php');
            $process->setTimeout(600);
            $process->run(
                function ($type, $buffer) {
                    if (Process::ERR === $type) {
                        echo 'ERR > '.$buffer;
                    } else {
                        echo $buffer;
                    }
                }
            );

            if ($this->argument('type') === 'true') {
                $sessionName = Tmux::value('tmux_session');
                $tmuxSession = new Process('tmux kill-session -t '.$sessionName);
                $this->info('Killing active tmux session: '.$sessionName);
                $tmuxSession->run(
                    function ($type, $buffer) {
                        if (Process::ERR === $type) {
                            echo 'ERR > '.$buffer;
                        } else {
                            echo $buffer;
                        }
                    }
                );
            }
        } else {
            $this->error($this->description);
        }
    }
}
