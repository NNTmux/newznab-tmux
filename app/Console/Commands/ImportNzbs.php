<?php

namespace App\Console\Commands;

use App\Services\Nzb\NzbImportService;
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
        {--delete-failed : Delete files after failed import}
        {--source= : Source of the NZB files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import nzb files for indexing';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if ($this->option('folder')) {
            if ($this->option('filename')) {
                $useNzbName = true;
            } else {
                $useNzbName = false;
            }
            if ($this->option('delete')) {
                $deleteNZB = true;
            } else {
                $deleteNZB = false;
            }
            if ($this->option('delete-failed')) {
                $deleteFailedNZB = true;
            } else {
                $deleteFailedNZB = false;
            }
            if ($this->option('source')) {
                $source = $this->option('source');
            } else {
                $source = 1;
            }
            $importFolder = $this->option('folder');
            $folders = File::directories($importFolder);
            if (empty($folders)) {
                $this->info('Importing NZB files from '.$importFolder);
                $files = File::allFiles($importFolder);
                $NZBImport = new NzbImportService;

                try {
                    $NZBImport->beginImport($files, $useNzbName, $deleteNZB, $deleteFailedNZB, $source);
                } catch (FileNotFoundException $e) {
                    $this->error($e->getMessage());
                }
            } else {
                foreach ($folders as $folder) {
                    $this->info('Importing NZB files from '.$folder);
                    $files = File::allFiles($folder);
                    $NZBImport = new NzbImportService;

                    try {
                        $NZBImport->beginImport($files, $useNzbName, $deleteNZB, $deleteFailedNZB, $source);
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
