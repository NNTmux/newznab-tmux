<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Facades\Search;
use App\Models\Release;
use App\Services\NameFixing\ReleaseNameResetPayload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NntmuxResetReleasesBySearchname extends Command
{
    /**
     * The name and signature of the console command.
     *
     * searchname: Exact searchname to match.
     * --dry-run: Show the affected releases without modifying them.
     */
    protected $signature = 'nntmux:reset-releases-by-searchname
                            {searchname : Exact searchname to match}
                            {--dry-run : Show counts only, do not modify releases}';

    /**
     * The console command description.
     */
    protected $description = 'Restore searchname from name and reset rename/postprocessing flags for releases matching an exact searchname.';

    public function handle(): int
    {
        $searchname = (string) $this->argument('searchname');
        $dryRun = (bool) $this->option('dry-run');

        $baseQuery = Release::query()
            ->select(['id', 'name', 'searchname'])
            ->where('searchname', $searchname)
            ->orderBy('id');

        $total = (clone $baseQuery)->count();

        if ($total === 0) {
            $this->info("No releases found with searchname: {$searchname}");

            return self::SUCCESS;
        }

        $this->info("Found {$total} release(s) with searchname: {$searchname}");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made.');

            $previewRows = (clone $baseQuery)
                ->limit(10)
                ->get()
                ->map(fn (Release $release): array => [
                    'id' => $release->id,
                    'current_searchname' => $release->searchname,
                    'restored_searchname' => $release->name,
                ])
                ->all();

            $this->table(
                ['ID', 'Current searchname', 'Restored searchname'],
                $previewRows
            );

            if ($total > 10) {
                $remaining = $total - 10;
                $this->line("... and {$remaining} more release(s).");
            }

            return self::SUCCESS;
        }

        $this->info('Resetting matched releases...');

        $updated = 0;
        $searchSyncFailures = 0;
        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        $baseQuery->chunkById(500, function ($releases) use (&$updated, &$searchSyncFailures, $progressBar): void {
            foreach ($releases as $release) {
                Release::query()
                    ->where('id', $release->id)
                    ->update(ReleaseNameResetPayload::for($release));

                try {
                    Search::updateRelease($release->id);
                } catch (\Throwable $e) {
                    $searchSyncFailures++;

                    Log::warning('Failed to update search index while resetting release by searchname', [
                        'release_id' => $release->id,
                        'searchname' => $release->searchname,
                        'error' => $e->getMessage(),
                    ]);
                }

                $updated++;
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Matched', $total],
                ['Updated', $updated],
                ['Search sync failures', $searchSyncFailures],
            ]
        );

        if ($searchSyncFailures > 0) {
            $this->warn('Some releases were reset but could not be synced to the search index. Check logs for details.');
        }

        return self::SUCCESS;
    }
}
