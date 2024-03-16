<?php

namespace App\Console\Commands;

use Blacklight\Tmux;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class UpdateNNTmuxGit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:git';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update NNTmux from git repository';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $wasRunning = false;

        if ((new Tmux())->isRunning()) {
            $wasRunning = true;
            $this->call('tmux-ui:stop', ['--kill' => true]);
        }
        $this->info('Stashing local changes before pulling from Github');
        $processGitStash = Process::run('git stash');
        echo $processGitStash->output();
        echo $processGitStash->errorOutput();
        $this->info('Getting changes from Github');
        $process = Process::run('git pull');
        echo $process->output();
        echo $process->errorOutput();

        if ($wasRunning === true) {
            $this->call('tmux-ui:start');
        }
    }
}
