<?php

namespace App\Console\Commands;

use App\Extensions\util\Versions;
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
	private $versions;

	/**
	 * Create a new command instance.
	 *
	 */
    public function __construct()
    {
		$this->versions = new Versions();
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
			$this->git();
		} else if ($this->argument('type') === 'branch') {
			$this->branch();
		} else if ($this->argument('type') === 'sql') {
			$this->sql();
		} else if($this->argument('type') === 'all') {
			$this->all();
		} else {
			$this->error($this->description);
		}
    }

	public function all()
	{
		$this->git();
		$this->sql();
	}

	public function branch()
	{
		$this->output->writeln('<comment>' . 'Git branch: ' . $this->versions->getGitBranch() . '</comment>');
	}

	/**
	 * Fetch git tag for latest version.
	 */
	public function git()
	{

		$this->output->writeln('<comment>Looking up Git tag version(s)</comment>');

		$this->info('Hash: ' . $this->versions->getGitHeadHash());
		$this->info('XML version: ' . $this->versions->getGitTagInFile());
		$this->info('Git version: ' . $this->versions->getGitTagInRepo());
	}

	/**
	 * Fetch SQL latest patch version.
	 */
	public function sql()
	{

		$this->output->writeln('<comment>Looking up SQL patch version(s)</comment>');


		$latest = $this->versions->getSQLPatchFromFile();
		$this->info("XML version: $latest");

		try {
			$dbVersion = $this->versions->getSQLPatchFromDB();
			$this->info(' DB version: ' . $dbVersion);
		} catch (\Exception $e) {
			$this->error($e->getMessage());
		}

	}
}
