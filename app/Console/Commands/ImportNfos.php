<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\NfoImportService as LegacyNfoImportService;
use App\Services\Nzb\NfoImportService as ManifestNfoImportService;
use App\Services\Nzb\NzbUploadManifestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

final class ImportNfos extends Command
{
    protected $signature = 'nntmux:import-nfos
        {--folder= : Import folder path containing NFO files}
        {--process-imdb : Process IMDB IDs found in NFOs (default: true)}
        {--no-process-imdb : Skip processing IMDB IDs}
        {--process-tv : Process TV show IDs found in NFOs (default: true)}
        {--no-process-tv : Skip processing TV show IDs}
        {--delete : Delete NFO files after successful import}
        {--delete-failed : Delete NFO files after failed import}
        {--stats : Show import statistics without processing}';

    protected $description = 'Import staged or standalone NFO files and link them to releases';

    public function handle(
        NzbUploadManifestService $manifests,
        ManifestNfoImportService $manifestImporter,
    ): int {
        $folderOption = $this->option('folder');
        if (! is_string($folderOption) || trim($folderOption) === '') {
            $this->error('You must specify a folder path using --folder option');
            $this->line('Example: php artisan nntmux:import-nfos --folder="/path/to/nfo/files"');

            return self::FAILURE;
        }

        $folder = rtrim($folderOption, '/\\');
        if (! File::isDirectory($folder)) {
            $this->error("Directory does not exist or is not accessible: {$folder}");

            return self::FAILURE;
        }

        if ((bool) $this->option('stats')) {
            $this->showImportStats(app(LegacyNfoImportService::class), $folder);

            return self::SUCCESS;
        }

        try {
            $manifestPaths = $manifests->findManifests($folder);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($manifestPaths !== []) {
            return $this->importManifests($manifestPaths, $manifestImporter);
        }

        return $this->importStandaloneNfos(app(LegacyNfoImportService::class), $folder);
    }

    /**
     * @param  list<string>  $manifestPaths
     */
    private function importManifests(
        array $manifestPaths,
        ManifestNfoImportService $importer,
    ): int {
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

    private function importStandaloneNfos(LegacyNfoImportService $importer, string $folder): int
    {
        $processImdb = ! (bool) $this->option('no-process-imdb');
        $processTv = ! (bool) $this->option('no-process-tv');
        $delete = (bool) $this->option('delete');
        $deleteFailed = (bool) $this->option('delete-failed');

        $this->info('NFO Import Configuration:');
        $this->line("Import Path: {$folder}");
        $this->line('Process IMDB IDs: '.($processImdb ? 'Yes' : 'No'));
        $this->line('Process TV IDs: '.($processTv ? 'Yes' : 'No'));
        $this->line('Delete Successful: '.($delete ? 'Yes' : 'No'));
        $this->line('Delete Failed: '.($deleteFailed ? 'Yes' : 'No'));
        $this->newLine();
        $this->info('Starting NFO import...');

        $startTime = now();
        try {
            $result = $importer->beginImport(
                $folder,
                $processImdb,
                $processTv,
                $delete,
                $deleteFailed,
            );
        } catch (Throwable $exception) {
            $this->error('An error occurred during NFO import: '.$exception->getMessage());
            $this->line('Check the logs for more detailed error information.');

            return self::FAILURE;
        }

        if (! $result) {
            $this->error('NFO import failed. Check logs for details.');

            return self::FAILURE;
        }

        $duration = $startTime->diffInSeconds(now());
        $this->newLine();
        $this->info("NFO import completed successfully in {$duration} seconds.");

        return self::SUCCESS;
    }

    private function showImportStats(LegacyNfoImportService $importer, string $folder): void
    {
        $this->info('Analyzing NFO import candidates...');

        $stats = $importer->getImportStats($folder);
        if (isset($stats['error'])) {
            $this->error($stats['error']);

            return;
        }

        $this->newLine();
        $this->info('NFO Import Statistics:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total NFO Files Found', $stats['nfo_files']],
                ['Potential Matches', $stats['potential_matches']],
                ['Import Path', $stats['import_path']],
            ]
        );

        if ($stats['nfo_files'] <= 0) {
            $this->warn('No NFO files found in the specified directory.');

            return;
        }

        $matchPercent = round(($stats['potential_matches'] / $stats['nfo_files']) * 100, 2);
        $this->newLine();
        $this->line("Match Rate: {$matchPercent}% of NFO files have matching releases");

        if ($stats['potential_matches'] > 0) {
            $this->info("Ready to import {$stats['potential_matches']} NFO files.");
            $this->line('Run without --stats to begin the import process.');

            return;
        }

        $this->warn('No matching releases found for any NFO files.');
        $this->line('Ensure NFO filenames exactly match release searchnames in the database.');
    }
}
