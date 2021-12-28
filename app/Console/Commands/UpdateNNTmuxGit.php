<?php

namespace App\Console\Commands;

use App\Extensions\util\Git;
use Blacklight\Tmux;
use Illuminate\Console\Command;

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
     * @var \app\extensions\util\Git object.
     */
    protected $git;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @throws \Cz\Git\GitException
     */
    public function handle()
    {
        $this->initialiseGit();

        $wasRunning = false;

        if ((new Tmux())->isRunning() === true) {
            $wasRunning = true;
            $this->call('tmux-ui:stop', ['--kill' => true]);
        }
        $this->info('Getting changes from Github');
        $result = $this->git->gitPull();
        $this->info($result[0]);

        if ($wasRunning === true) {
            $this->call('tmux-ui:start');
        }
    }

    /**
     * @throws \Cz\Git\GitException
     */
    protected function initialiseGit()
    {
        if (! ($this->git instanceof Git)) {
            $this->git = new Git();
        }
    }
}
