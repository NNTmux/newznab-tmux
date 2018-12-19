<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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
        $this->call('migrate');
    }
}
