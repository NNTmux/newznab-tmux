<?php

namespace App\Console\Commands;

use App\Models\Release;
use App\Services\MovieService;
use Illuminate\Console\Command;

class ProcessImdbTtReleases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:process-imdb-tt-releases
                            {--limit=100 : Number of releases to process per run}
                            {--dry-run : Show what would be processed without making changes}
                            {--force : Process releases even if they have an IMDB ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find releases with "imdb-tt" in searchname that lack IMDB IDs and reprocess them to fetch movie info';

    private ?MovieService $movie = null;

    /**
     * Built on first use: every Artisan command constructs all commands, and MovieService reads
     * configuration from the database, which must not be needed just to start the console.
     */
    protected function movie(): MovieService
    {
        return $this->movie ??= new MovieService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info("Processing releases with 'imdb-tt' in searchname...");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Build query to find releases with "imdb-tt" in searchname
        $query = Release::query()
            ->select(['id', 'searchname', 'imdbid', 'categories_id', 'groups_id', 'leftguid'])
            ->where('searchname', 'LIKE', '%imdb-tt%');

        // If not forcing, only get releases without IMDB IDs or with empty/zero IMDB IDs
        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('imdbid')
                    ->orWhere('imdbid', '')
                    ->orWhere('imdbid', '0000000')
                    ->orWhere('imdbid', '0');
            });
        }

        $releases = $query->limit($limit)->get();

        if ($releases->isEmpty()) {
            $this->info('No releases found matching criteria.');

            return Command::SUCCESS;
        }

        $this->info("Found {$releases->count()} releases to process.");

        $processed = 0;
        $updated = 0;
        $failed = 0;

        $progressBar = $this->output->createProgressBar($releases->count());
        $progressBar->start();

        foreach ($releases as $release) {
            $processed++;

            if ($dryRun) {
                $this->newLine();
                $this->line("Would process: [{$release->id}] {$release->searchname}");
                if ($release->imdbid) {
                    $this->line("  Current IMDB ID: {$release->imdbid}");
                } else {
                    $this->line('  Current IMDB ID: None');
                }

                // Show what IMDB ID we would extract
                $extractedImdbId = $this->extractImdbIdFromSearchname($release->searchname);
                if ($extractedImdbId) {
                    $this->line("  Would extract IMDB ID: {$extractedImdbId}");
                }

                $progressBar->advance();

                continue;
            }

            try {
                $this->newLine();
                $this->line("Processing: [{$release->id}] {$release->searchname}");

                // Extract potential IMDB ID from searchname
                $extractedImdbId = $this->extractImdbIdFromSearchname($release->searchname);

                if ($extractedImdbId) {
                    $this->line("  Extracted IMDB ID: {$extractedImdbId}");

                    // Try to update using the extracted IMDB ID directly
                    $result = $this->movie()->doMovieUpdate($extractedImdbId, 'ProcessImdbTt', $release->id);

                    if ($result !== false) {
                        $updated++;
                        $this->line('  ✓ Successfully updated with movie info');
                    } else {
                        $this->line('  Direct IMDB lookup failed, trying alternative methods...');

                        // Fallback: Use the existing movie processing pipeline
                        if ($this->processReleaseUsingMoviePipeline($release)) {
                            $updated++;
                            $this->line('  ✓ Successfully updated through movie pipeline');
                        } else {
                            $failed++;
                            $this->line('  ✗ Failed to find movie info');
                        }
                    }
                } else {
                    $this->line('  No clear IMDB ID pattern found, using movie processing pipeline...');

                    // Use the existing movie processing pipeline for releases without clear IMDB patterns
                    if ($this->processReleaseUsingMoviePipeline($release)) {
                        $updated++;
                        $this->line('  ✓ Successfully updated through movie pipeline');
                    } else {
                        $failed++;
                        $this->line('  ✗ Failed to find movie info');
                    }
                }
            } catch (\Exception $e) {
                $failed++;
                $this->newLine();
                $this->error("Error processing release {$release->id}: {$e->getMessage()}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('Processing completed!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Processed', $processed],
                ['Successfully Updated', $updated],
                ['Failed', $failed],
                ['Success Rate', $processed > 0 ? round(($updated / $processed) * 100, 2).'%' : '0%'],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Extract IMDB ID from searchname using various patterns
     */
    protected function extractImdbIdFromSearchname(string $searchname): ?string
    {
        // Common patterns for IMDB IDs in release names
        $patterns = [
            '/imdb-tt(\d{7,8})/i',           // imdb-tt1234567
            '/imdb\.tt(\d{7,8})/i',          // imdb.tt1234567
            '/imdb_tt(\d{7,8})/i',           // imdb_tt1234567
            '/\[tt(\d{7,8})\]/i',            // [tt1234567]
            '/\(tt(\d{7,8})\)/i',            // (tt1234567)
            '/(?:^|[\W_])tt(\d{7,8})(?=[\W_]|$)/i', // tt1234567 (standalone)
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $searchname, $matches)) {
                return 'tt'.$matches[1];
            }
        }

        return null;
    }

    /**
     * Process a release using the existing movie processing pipeline
     * This leverages the existing Movie::processMovieReleases logic
     */
    protected function processReleaseUsingMoviePipeline(Release $release): bool
    {
        try {
            // Store original movie quantity setting
            $originalQty = $this->movie()->movieqty;

            // Temporarily set quantity to 1 for processing just this release
            $this->movie()->movieqty = 1;

            // Use a direct database update to temporarily mark this release as needing processing
            // by clearing its imdbid so the movie processor will pick it up
            $originalImdbId = $release->imdbid;

            // Temporarily clear the IMDB ID so the movie processor will process it
            Release::where('id', $release->id)->update(['imdbid' => null]);

            // Create a temporary query that will return just this release
            // We'll simulate the movie processor's query logic
            $testQuery = Release::query()
                ->select(['searchname', 'id'])
                ->where('id', $release->id)
                ->whereNull('imdbid')
                ->limit(1);

            $testRelease = $testQuery->first();

            if (! $testRelease) {
                // Restore original IMDB ID and return false
                Release::where('id', $release->id)->update(['imdbid' => $originalImdbId]);
                $this->movie()->movieqty = $originalQty;

                return false;
            }

            // Process this single release using the movie processing logic
            // We'll call processMovieReleases with specific parameters to target just this release

            // Temporarily disable echo output to avoid cluttering our command output
            $originalEcho = $this->movie()->echooutput;
            $this->movie()->echooutput = false;

            // Use the group ID and guid char to narrow the processing to this specific release
            $this->movie()->processMovieReleases((string) $release->groups_id, '', 1);

            // Restore original settings
            $this->movie()->echooutput = $originalEcho;
            $this->movie()->movieqty = $originalQty;

            // Check if the release was successfully updated
            $updatedRelease = Release::find($release->id);
            $updatedImdbId = (string) ($updatedRelease->imdbid ?? '');
            $success = ! in_array($updatedImdbId, ['', '0', '0000000'], true);

            if (! $success) {
                // If processing failed, restore the original IMDB ID
                Release::where('id', $release->id)->update(['imdbid' => $originalImdbId]);
            }

            return $success;

        } catch (\Exception $e) {
            // Restore original settings in case of exception
            if (isset($originalQty)) {
                $this->movie()->movieqty = $originalQty;
            }
            if (isset($originalEcho)) {
                $this->movie()->echooutput = $originalEcho;
            }
            if (isset($originalImdbId)) {
                Release::where('id', $release->id)->update(['imdbid' => $originalImdbId]);
            }

            throw $e;
        }
    }
}
