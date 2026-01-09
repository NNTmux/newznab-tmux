<?php

namespace App\Console\Commands;

use App\Models\Release;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class FetchSamples extends Command
{
    protected $signature = 'releases:fetch-samples
                                {--category= : Category id or comma-separated list of category ids (required)}
                                {--limit=0 : Max number of releases to process (0 = all)}
                                {--chunk=500 : Chunk size when iterating releases}
                                {--dry-run : Show how many and which GUIDs would be processed without running}
                                {--show-output : Display output from each releases:additional invocation}';

    protected $description = 'Fetch/generate samples by running additional postprocessing (with reset) for releases in the supplied category / categories having jpgstatus = 0.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $chunkSize = (int) $this->option('chunk');
        $dryRun = (bool) $this->option('dry-run');
        $showOutput = (bool) $this->option('show-output');
        $categoryOpt = $this->option('category');

        // Validate the required category option.
        if ($categoryOpt === null || trim((string) $categoryOpt) === '') {
            $this->info('Category option is empty. Provide --category with one or more numeric category IDs. Command will not run.');

            return self::SUCCESS;
        }

        // Parse categories: allow comma or whitespace separated values.
        $catIds = collect(preg_split('/[\s,]+/', trim((string) $categoryOpt), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($v) => trim($v))
            ->filter(fn ($v) => ctype_digit($v))
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        if ($catIds->isEmpty()) {
            $this->info('Category option provided but no valid numeric IDs parsed. Command will not run.');

            return self::SUCCESS;
        }

        if ($limit < 0) {
            $this->error('Limit must be >= 0');

            return self::FAILURE;
        }
        if ($chunkSize < 1) {
            $this->error('Chunk size must be >= 1');

            return self::FAILURE;
        }

        // Build base query now
        $baseQuery = Release::query()
            ->whereIn('categories_id', $catIds->all())
            ->where('jpgstatus', 0)
            ->orderBy('id', 'desc');

        $totalAll = $baseQuery->count();
        if ($totalAll === 0) {
            $this->info('No matching releases found (categories_id IN ['.implode(',', $catIds->all()).'] AND jpgstatus = 0).');

            return self::SUCCESS;
        }

        $effectiveTotal = $limit > 0 ? min($limit, $totalAll) : $totalAll;
        $this->info('Categories: ['.implode(',', $catIds->all()).']');
        $this->info("Found {$totalAll} matching release(s). Processing {$effectiveTotal}.".($dryRun ? ' (dry-run)' : ''));

        if ($dryRun) {
            $previewQuery = clone $baseQuery;
            if ($limit > 0) {
                $previewQuery->limit($limit);
            }
            $previewGuids = $previewQuery->pluck('guid');
            $this->line('Dry run: GUIDs to process (with --reset):');
            foreach ($previewGuids as $g) {
                $this->line($g);
            }
            $this->info('Dry run complete.');

            return self::SUCCESS;
        }

        $processed = 0;
        $failed = 0;
        $remaining = $effectiveTotal;

        $bar = $this->output->createProgressBar($effectiveTotal);
        $bar->start();

        $query = clone $baseQuery;

        // Use chunkByIdDesc to process newest releases first
        $query->chunkByIdDesc($chunkSize, function ($releases) use (&$processed, &$failed, &$remaining, $bar, $showOutput) {
            foreach ($releases as $release) {
                if ($remaining <= 0) {
                    return false; // stop chunking
                }

                $guid = $release->guid;
                try {
                    // Call the existing single-release additional processing command with this GUID.
                    $exitCode = Artisan::call('releases:additional', [
                        'guid' => $guid, // pass GUID explicitly
                        '--reset' => true,
                    ]);
                    $subOutput = trim(Artisan::output());

                    if ($exitCode === 0) {
                        $processed++;
                        if ($showOutput && $subOutput !== '') {
                            $this->getOutput()->writeln("\n<info>{$guid}</info> -> {$subOutput}");
                        }
                    } else {
                        $failed++;
                        $this->getOutput()->writeln("\n<error>Non-zero exit code ({$exitCode}) for GUID {$guid}</error>".($showOutput && $subOutput !== '' ? "\n  Output: {$subOutput}" : ''));
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $this->getOutput()->writeln("\n<error>Error processing GUID {$guid}: {$e->getMessage()}</error>");
                }

                $remaining--;
                $bar->advance();

                if ($remaining <= 0) {
                    break; // exit foreach to stop further work
                }
            }

            if ($remaining <= 0) {
                return false; // signal chunkById to stop
            }

            return true; // continue chunking
        }, 'id');

        $bar->finish();
        $this->newLine();
        $this->info("Processing complete. Success: {$processed}, Failed: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
