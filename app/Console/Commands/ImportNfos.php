<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Nzb\NfoImportService;
use App\Services\Nzb\NzbUploadManifestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

final class ImportNfos extends Command
{
    protected $signature = 'nntmux:import-nfos
        {--folder= : Import folder path}
        {--delete : Delete NFO files after import}
        {--delete-failed : Delete NFO files after terminal or failed imports}';

    protected $description = 'Import paired NFO files for previously imported NZB releases';

    public function handle(NzbUploadManifestService $manifests, NfoImportService $importer): int
    {
        $folderOption = $this->option('folder');
        if (! is_string($folderOption) || trim($folderOption) === '') {
            $this->error('Folder path must not be empty');

            return self::FAILURE;
        }

        $folder = rtrim($folderOption, '/\\');
        if (! File::isDirectory($folder)) {
            $this->error('Folder path does not exist: '.$folder);

            return self::FAILURE;
        }

        try {
            $manifestPaths = $manifests->findManifests($folder);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $imported = $skipped = $failed = 0;
        foreach ($manifestPaths as $manifestPath) {
            $result = $importer->importManifest(
                $manifestPath,
                (bool) $this->option('delete'),
                (bool) $this->option('delete-failed'),
            );

            match ($result['status']) {
                'imported' => $imported++,
                'failed' => $failed++,
                default => $skipped++,
            };

            if ($result['status'] === 'failed') {
                $this->warn(basename(dirname($manifestPath)).': '.$result['message']);
            }
        }

        $this->info("Processed {$imported} NFOs, skipped {$skipped}, failed {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
