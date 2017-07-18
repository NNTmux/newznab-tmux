<?php

namespace App\Console\Commands;

use App\Extensions\command\Version;
use Illuminate\Console\Command;

class VerifyNNTmuxVersion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:version {type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Returns the current version (or branch) of the indexer.
						Actions:
						all		Show all of following info.
						branch		Show git branch name.
  						git		Show git tag for current version.
						sql		Show SQL patch level';
	private $version;

	/**
	 * Create a new command instance.
	 *
	 */
    public function __construct()
    {
    	$this->version = new Version();
    	parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
		if ($this->argument('type') === 'git') {
			$this->call($this->version->git());
		} else if ($this->argument('type') === 'branch') {
			$this->call($this->version->branch());
		} else if ($this->argument('type') === 'sql') {
			$this->call($this->version->sql());
		} else if($this->argument('type') === 'all') {
			$this->call($this->version->all());
		} else {
			$this->error('Wrong argument used');
		}
    }
}
