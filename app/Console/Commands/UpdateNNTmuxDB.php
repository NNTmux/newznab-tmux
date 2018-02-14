<?php

namespace App\Console\Commands;

use Blacklight\db\DbUpdate;
use App\Extensions\util\Git;
use Illuminate\Console\Command;
use App\Extensions\util\Versions;

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

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \RuntimeException
     */
    public function handle()
    {
        // TODO Add check to determine if the indexer or other scripts are running. Hopefully
        // also prevent web access.
        $this->output->writeln('<info>Checking database version</info>');

        $versions = new Versions(['git' => ($this->git instanceof Git) ? $this->git : null]);

        try {
            $currentDb = $versions->getSQLPatchFromDB();
            $currentXML = $versions->getSQLPatchFromFile();
        } catch (\PDOException $e) {
            $this->error('Error fetching patch versions!');

            return 1;
        }

        $this->info("Db: $currentDb,\tFile: $currentXML");

        if ($currentDb < $currentXML) {
            $db = new DbUpdate(['backup' => false]);
            $db->processPatches(['safe' => false]);
        } else {
            $this->info('Up to date.');
        }
    }
}
