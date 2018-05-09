<?php

namespace App\Console\Commands;

use Blacklight\Tmux;
use App\Extensions\util\Git;
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
     * @throws \Exception
     */
    public function handle()
    {
        $this->initialiseGit();
        if (! \in_array($this->git->getBranch(), $this->git->getBranchesMain(), false)) {
            $this->error('Not on the stable or dev branch! Refusing to update repository');

            return;
        }

        $wasRunning = false;

        if ((new Tmux())->isRunning() === true) {
            $wasRunning = true;
            $this->call('tmux-ui:stop', ['type' => 'true']);
        }

        $this->info($this->git->gitPull());

        if ($wasRunning === true) {
            $this->call('tmux-ui:start');
        }
    }

    protected function initialiseGit()
    {
        if (! ($this->git instanceof Git)) {
            $this->git = new Git();
        }
    }
}
