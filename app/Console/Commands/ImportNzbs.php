<?php

namespace App\Console\Commands;

use Blacklight\NZBImport;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;

class ImportNzbs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:import-nzbs
        {--folder= : Import folder path}
        {--filename : Use filename true or false}
        {--delete : Delete files after import}
        {--delete-failed : Delete files after failed import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import nzb files for indexing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('folder')) {
            if ($this->option('filename')) {
                $useNzbName = $this->option('filename');
            } else {
                $useNzbName = false;
            }
            if ($this->option('delete')) {
                $deleteNZB = $this->option('delete');
            } else {
                $deleteNZB = false;
            }
            if ($this->option('delete-failed')) {
                $deleteFailedNZB = $this->option('delete-failed');
            } else {
                $deleteFailedNZB = false;
            }
            $importFolder = $this->option('folder');
            $folders = File::directories($importFolder);
            if (empty($folders)) {
                $this->info('Importing NZB files from '.$importFolder);
                $files = File::allFiles($importFolder);
                $NZBImport = new NZBImport();

                try {
                    $NZBImport->beginImport($files, $useNzbName, $deleteNZB, $deleteFailedNZB);
                } catch (FileNotFoundException $e) {
                    $this->error($e->getMessage());
                }
            } else {
                foreach ($folders as $folder) {
                    $this->info('Importing NZB files from '.$folder);
                    $files = File::allFiles($folder);
                    $NZBImport = new NZBImport();

                    try {
                        $NZBImport->beginImport($files, $useNzbName, $deleteNZB, $deleteFailedNZB);
                    } catch (FileNotFoundException $e) {
                        $this->error($e->getMessage());
                    }
                }
            }
        } else {
            $this->error('Folder path must not be empty');
            exit();
        }
    }
}
