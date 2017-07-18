<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Settings;

class VerifyNNTmuxSettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:settings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify settings table data';

	/**
	 * Create a new command instance.
	 *
	 */
    public function __construct()
    {
        parent::__construct();
    }

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 * @throws \Exception
	 */
    public function handle()
    {
		Settings::hasAllEntries($this);
    }
}
