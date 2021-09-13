<?php

namespace App\Console\Commands;

use App\Extensions\util\Git;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class UpdateNNTmuxComposer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:composer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update composer libraries for NNTmux';

    /**
     * @var \app\extensions\util\Git object.
     */
    protected $git;

    private $gitBranch;

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
        $this->composer();
    }

    /**
     * Issues the command to 'install' the composer package.
     *
     * It first checks the current branch for stable versions. If found then the '--no-dev'
     * option is added to the command to prevent development packages being also downloaded.
     *
     * @return string
     *
     * @throws \Cz\Git\GitException
     */
    protected function composer()
    {
        $this->initialiseGit();
        $command = 'composer install';
        if (\in_array($this->gitBranch, $this->git->getBranchesStable(), false)) {
            $command .= ' --prefer-dist --no-dev';
        } else {
            $command .= ' --prefer-dist';
        }
        $this->output->writeln('<comment>Running composer install process...</comment>');
        $process = Process::fromShellCommandline('exec '.$command);
        $process->setTimeout(360);
        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                echo $buffer;
            }
        });

        return $process->getOutput();
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
