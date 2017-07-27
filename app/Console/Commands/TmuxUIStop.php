<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class TmuxUIStop extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tmux-ui:stop';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stop tmux processing';

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
	 * @throws \Symfony\Component\Process\Exception\LogicException
	 * @throws \Symfony\Component\Process\Exception\RuntimeException
	 */
    public function handle()
    {
		$process = new Process('php misc/update/nix/tmux/stop.php');
		$process->run(function ($type, $buffer) {
			if (Process::ERR === $type) {
				echo 'ERR > '.$buffer;
			} else {
				echo $buffer;
			}
		});
    }
}
