<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

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
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->composer();
    }

    protected function composer(): void
    {
        $this->info('Running composer install process...');
        $process = Process::timeout(360)->run('composer install');
        echo $process->output();
        echo $process->errorOutput();
    }
}
