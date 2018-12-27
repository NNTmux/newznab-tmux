<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class UpdateNNTmuxDB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:db';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update NNTmux database with new patches';

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

    public function handle()
    {
        // also prevent web access.
        $this->output->writeln('<info>Updating database</info>');
        if (env('APP_ENV') !== 'production') {
            $this->call('migrate');
        } else {
            $process = new Process('php artisan migrate --force');
            $process->setTimeout(600);
            $process->run(function ($type, $buffer) {
                if (Process::ERR === $type) {
                    echo 'ERR > '.$buffer;
                } else {
                    echo $buffer;
                }
            });
        }
    }
}
