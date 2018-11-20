<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Extensions\util\Versions;

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
     * VerifyNNTmuxVersion constructor.
     *
     * @throws \Cz\Git\GitException
     */
    public function __construct()
    {
        $this->versions = new Versions();
        parent::__construct();
    }

    /**
     *
     * @throws \Cz\Git\GitException
     */
    public function handle()
    {
        if ($this->argument('type') === 'git') {
            $this->git();
        } elseif ($this->argument('type') === 'branch') {
            $this->branch();
        } elseif ($this->argument('type') === 'sql') {
            $this->sql();
        } elseif ($this->argument('type') === 'all') {
            $this->all();
        } else {
            $this->error($this->description);
        }
    }

    /**
     *
     * @throws \Cz\Git\GitException
     */
    public function all()
    {
        $this->git();
        $this->sql();
    }

    public function branch()
    {
        $this->output->writeln('<comment>'.'Git branch: '.$this->versions->getGitBranch().'</comment>');
    }

    /**
     * @throws \Cz\Git\GitException
     */
    public function git()
    {
        $this->output->writeln('<comment>Looking up Git tag version(s)</comment>');

        $this->info('Hash: '.$this->versions->getGitHeadHash());
        $this->info('XML version: '.$this->versions->getGitTagInFile());
        $this->info('Git version: '.$this->versions->getGitTagInRepo());
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
            $this->info(' DB version: '.$dbVersion);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
