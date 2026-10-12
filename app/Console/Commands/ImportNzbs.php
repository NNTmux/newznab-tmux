<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\NzbImportStatus;
use App\Services\Nzb\NzbImportService;
use App\Services\Nzb\NzbUploadManifestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SplFileInfo;
use Throwable;

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
    public function handle(NzbUploadManifestService $manifests): void
    {
        $folderOption = $this->option('folder');
        if (! is_string($folderOption) || trim($folderOption) === '') {
            $this->error('Folder path must not be empty');

            return;
        }

        $importFolder = rtrim($folderOption, '/\\');
        if (! File::isDirectory($importFolder)) {
            $this->error('Folder path does not exist: '.$importFolder);

            return;
        }

        $useNzbName = (bool) $this->option('filename');
        $deleteNZB = (bool) $this->option('delete');
        $deleteFailedNZB = (bool) $this->option('delete-failed');
        $source = $this->option('source') ? (int) $this->option('source') : 1;

        try {
            $files = array_values(array_filter(
                File::allFiles($importFolder),
                static function (SplFileInfo $file) use ($manifests): bool {
                    $path = $file->getPathname();
                    if ($file->getFilename() === NzbUploadManifestService::FILENAME) {
                        $manifests->reconcilePending($path);

                        return false;
                    }
                    if (! Str::endsWith(strtolower($path), ['.nzb', '.nzb.gz'])) {
                        return true;
                    }

                    return $manifests->shouldImportNzb($path);
                }
            ));

            $this->info('Importing NZB files from '.$importFolder);
            $importer = new NzbImportService;
            $importer->beginImport(
                $files,
                $useNzbName,
                $deleteNZB,
                $deleteFailedNZB,
                $source,
                static function (array $result) use ($manifests): void {
                    /** @var NzbImportStatus $status */
                    $status = $result['status'];
                    $manifests->recordNzbOutcome(
                        $result['path'],
                        $status,
                        $result['release_id'],
                        $result['release_guid'],
                        candidateIdentity: $result['candidate_identity'] ?? null,
                    );
                }
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
        }
    }
}
