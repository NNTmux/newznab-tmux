<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Facades\Search;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteReleases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:delete-releases
                            {criteria?* : Advanced criteria in original format}
                            {--group= : Group name to filter by}
                            {--group-like= : Group name pattern to filter by (partial match)}
                            {--poster= : Poster name (fromname) to filter by}
                            {--poster-like= : Poster name pattern to filter by (partial match)}
                            {--name= : Release name to filter by}
                            {--name-like= : Release name pattern to filter by (partial match)}
                            {--search= : Search name to filter by}
                            {--search-like= : Search name pattern to filter by (partial match)}
                            {--category= : Category ID to filter by}
                            {--guid= : Specific GUID to filter by}
                            {--size-min= : Minimum size in bytes}
                            {--size-max= : Maximum size in bytes}
                            {--size= : Exact size in bytes}
                            {--hours-old= : Delete releases older than X hours}
                            {--hours-new= : Delete releases newer than X hours}
                            {--parts-min= : Minimum number of parts}
                            {--parts-max= : Maximum number of parts}
                            {--parts= : Exact number of parts}
                            {--completion-max= : Maximum completion percentage}
                            {--nzb-status= : NZB status to filter by}
                            {--imdb= : IMDB ID to filter by (use NULL for no IMDB)}
                            {--rage= : Rage ID to filter by}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete releases based on specified criteria';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Build criteria array from simple options
        $criteria = $this->buildCriteriaFromOptions();

        // Add any advanced criteria
        $advancedCriteria = $this->argument('criteria');
        if (! empty($advancedCriteria)) {
            $criteria = array_merge($criteria, $advancedCriteria);
        }

        if (empty($criteria)) {
            $this->showUsage();

            return 1;
        }

        $this->info('Delete Releases Command');
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No releases will actually be deleted');
            $this->newLine();

            return $this->performDryRun($criteria);
        }

        // Show confirmation unless force flag is set
        if (! $force) {
            $this->warn('You are about to delete releases matching the following criteria:');
            foreach ($criteria as $criterion) {
                $this->line("  - {$criterion}");
            }
            $this->newLine();

            if (! $this->confirm('Are you sure you want to proceed with the deletion?')) {
                $this->info('Operation cancelled.');

                return 0;
            }
        }

        try {
            $this->info('Starting release deletion process...');

            // Build the query to find releases to delete
            $query = $this->buildQueryFromCriteria($criteria);
            if (! $query) {
                $this->error('Could not build query from criteria.');

                return 1;
            }

            // Get releases to delete
            $releases = DB::select($query);
            if (empty($releases)) {
                $this->info('No releases found matching the specified criteria.');

                return 0;
            }

            $count = count($releases);
            $this->info("Found {$count} release(s) to delete...");

            $deleted = 0;
            foreach ($releases as $release) {
                try {
                    // Delete the release using direct database operations
                    $releaseId = $release->id;

                    $releaseName = $release->searchname ?: ($release->name ?: 'Unknown');
                    $this->line("Deleting: {$releaseName}");

                    // Delete related data first (to maintain referential integrity)
                    DB::delete('DELETE FROM user_downloads WHERE releases_id = ?', [$releaseId]);
                    DB::delete('DELETE FROM users_releases WHERE releases_id = ?', [$releaseId]);
                    DB::delete('DELETE FROM release_files WHERE releases_id = ?', [$releaseId]);
                    DB::delete('DELETE FROM release_comments WHERE releases_id = ?', [$releaseId]);
                    DB::delete('DELETE FROM release_nfos WHERE releases_id = ?', [$releaseId]);
                    DB::delete('DELETE FROM release_subtitles WHERE releases_id = ?', [$releaseId]);

                    // Delete the main release record
                    DB::delete('DELETE FROM releases WHERE id = ?', [$releaseId]);

                    // Delete from search indexes
                    Search::deleteRelease($releaseId);

                    $deleted++;

                    // Show progress for large deletions
                    if ($deleted % 100 == 0) {
                        $this->info("Deleted {$deleted}/{$count} releases...");
                    }

                } catch (\Exception $e) {
                    $releaseName = $release->searchname ?? 'Unknown';
                    $this->warn("Failed to delete release {$releaseName}: ".$e->getMessage());
                }
            }

            $this->info("Successfully deleted {$deleted} release(s).");

            return 0;

        } catch (\Exception $e) {
            $this->error('An error occurred: '.$e->getMessage());

            return 1;
        }
    }

    /**
     * Perform a dry run to show what releases would be deleted.
     *
     * @param  array<string, mixed>  $criteria
     */
    protected function performDryRun(array $criteria): int
    {
        try {
            $this->info('Analyzing releases matching the following criteria:');
            foreach ($criteria as $criterion) {
                $this->line("  - {$criterion}");
            }
            $this->newLine();

            // Get the query that would be executed
            $query = $this->buildQueryFromCriteria($criteria);

            if (! $query) {
                $this->error('Could not build query from criteria.');

                return 1;
            }

            // Execute the query to get preview results
            $releases = DB::select($query);

            if (empty($releases)) {
                $this->info('No releases found matching the specified criteria.');

                return 0;
            }

            $count = count($releases);
            $this->info("Found {$count} release(s) that would be deleted:");
            $this->newLine();

            // Show sample of releases (first 10)
            $displayCount = min(10, $count);
            for ($i = 0; $i < $displayCount; $i++) {
                $release = $releases[$i];
                $releaseName = isset($release->searchname) ? $release->searchname : (isset($release->name) ? $release->name : 'Unknown');
                $releaseGuid = isset($release->guid) ? $release->guid : $release->id;
                $this->line(sprintf('  [%s] %s', $releaseGuid, $releaseName));
            }

            if ($count > 10) {
                $this->line('  ... and '.($count - 10).' more releases');
            }

            $this->newLine();
            $this->warn("Total: {$count} release(s) would be deleted");
            $this->info('Use without --dry-run to actually delete these releases');

            return 0;

        } catch (\Exception $e) {
            $this->error('Error during dry run: '.$e->getMessage());

            return 1;
        }
    }

    /**
     * Build the SQL query from criteria array.
     *
     * @param  array<string, mixed>  $criteria
     */
    protected function buildQueryFromCriteria(array $criteria): ?string
    {
        // Start with base query
        $query = 'SELECT id, guid, searchname, name FROM releases WHERE 1=1';

        foreach ($criteria as $criterion) {
            if ($criterion === 'ignore') {
                continue;
            }

            $queryPart = $this->formatCriterionToSql($criterion);
            if ($queryPart) {
                $query .= $queryPart;
            }
        }

        return $this->cleanSpaces($query);
    }

    /**
     * Convert a single criterion to SQL WHERE clause.
     */
    protected function formatCriterionToSql(string $criterion): ?string
    {
        $args = explode('=', $criterion);
        if (count($args) !== 3) {
            return null;
        }

        $column = trim($args[0]);
        $modifier = trim($args[1]);
        $value = trim($args[2], '"\'');

        return match ($column) {
            'categories_id' => $modifier === 'equals' ? " AND categories_id = {$value}" : null,
            'imdbid' => $modifier === 'equals' ? ($value === 'NULL' ? ' AND imdbid IS NULL' : " AND imdbid = {$value}") : null,
            'nzbstatus' => $modifier === 'equals' ? " AND nzbstatus = {$value}" : null,
            'rageid' => $modifier === 'equals' ? " AND rageid = {$value}" : null,
            'totalpart' => match ($modifier) {
                'equals' => " AND totalpart = {$value}",
                'bigger' => " AND totalpart > {$value}",
                'smaller' => " AND totalpart < {$value}",
                default => null,
            },
            'completion' => $modifier === 'smaller' ? " AND completion < {$value}" : null,
            'size' => match ($modifier) {
                'equals' => " AND size = {$value}",
                'bigger' => " AND size > {$value}",
                'smaller' => " AND size < {$value}",
                default => null,
            },
            'adddate' => match ($modifier) {
                'bigger' => " AND adddate < (NOW() - INTERVAL {$value} HOUR)",
                'smaller' => " AND adddate > (NOW() - INTERVAL {$value} HOUR)",
                default => null,
            },
            'postdate' => match ($modifier) {
                'bigger' => " AND postdate < (NOW() - INTERVAL {$value} HOUR)",
                'smaller' => " AND postdate > (NOW() - INTERVAL {$value} HOUR)",
                default => null,
            },
            'fromname' => match ($modifier) {
                'equals' => ' AND fromname = '.DB::connection()->getPdo()->quote($value),
                'like' => ' AND fromname LIKE '.DB::connection()->getPdo()->quote('%'.str_replace(' ', '%', $value).'%'),
                default => null,
            },
            'groupname' => match ($modifier) {
                'equals' => (static function () use ($value) {
                    $group = DB::select('SELECT id FROM usenet_groups WHERE name = ?', [$value]);

                    return ! empty($group) ? " AND groups_id = {$group[0]->id}" : null;
                })(),
                'like' => (static function () use ($value) {
                    $groups = DB::select('SELECT id FROM usenet_groups WHERE name LIKE ?', ['%'.str_replace(' ', '%', $value).'%']);

                    return ! empty($groups) ? ' AND groups_id IN ('.implode(',', array_column($groups, 'id')).')' : null;
                })(),
                default => null,
            },
            'guid' => $modifier === 'equals' ? ' AND guid = '.DB::connection()->getPdo()->quote($value) : null,
            'name' => match ($modifier) {
                'equals' => ' AND name = '.DB::connection()->getPdo()->quote($value),
                'like' => ' AND name LIKE '.DB::connection()->getPdo()->quote('%'.str_replace(' ', '%', $value).'%'),
                default => null,
            },
            'searchname' => match ($modifier) {
                'equals' => ' AND searchname = '.DB::connection()->getPdo()->quote($value),
                'like' => ' AND searchname LIKE '.DB::connection()->getPdo()->quote('%'.str_replace(' ', '%', $value).'%'),
                default => null,
            },
            default => null,
        };
    }

    /**
     * Clean multiple spaces from a string.
     */
    protected function cleanSpaces(string $string): string
    {
        return preg_replace('/\s+/', ' ', trim($string));
    }

    /**
     * Build criteria array from simple command options.
     *
     * @return array<string, mixed>
     */
    protected function buildCriteriaFromOptions(): array
    {
        $criteria = [];

        // Group filters
        if ($this->option('group')) {
            $criteria[] = 'groupname=equals="'.$this->option('group').'"';
        }
        if ($this->option('group-like')) {
            $criteria[] = 'groupname=like="'.$this->option('group-like').'"';
        }

        // Poster filters
        if ($this->option('poster')) {
            $criteria[] = 'fromname=equals="'.$this->option('poster').'"';
        }
        if ($this->option('poster-like')) {
            $criteria[] = 'fromname=like="'.$this->option('poster-like').'"';
        }

        // Name filters
        if ($this->option('name')) {
            $criteria[] = 'name=equals="'.$this->option('name').'"';
        }
        if ($this->option('name-like')) {
            $criteria[] = 'name=like="'.$this->option('name-like').'"';
        }

        // Search name filters
        if ($this->option('search')) {
            $criteria[] = 'searchname=equals="'.$this->option('search').'"';
        }
        if ($this->option('search-like')) {
            $criteria[] = 'searchname=like="'.$this->option('search-like').'"';
        }

        // Category filter
        if ($this->option('category')) {
            $criteria[] = 'categories_id=equals='.$this->option('category');
        }

        // GUID filter
        if ($this->option('guid')) {
            $criteria[] = 'guid=equals="'.$this->option('guid').'"';
        }

        // Size filters
        if ($this->option('size')) {
            $criteria[] = 'size=equals='.$this->option('size');
        }
        if ($this->option('size-min')) {
            $criteria[] = 'size=bigger='.$this->option('size-min');
        }
        if ($this->option('size-max')) {
            $criteria[] = 'size=smaller='.$this->option('size-max');
        }

        // Age filters
        if ($this->option('hours-old')) {
            $criteria[] = 'adddate=bigger='.$this->option('hours-old');
        }
        if ($this->option('hours-new')) {
            $criteria[] = 'adddate=smaller='.$this->option('hours-new');
        }

        // Parts filters
        if ($this->option('parts')) {
            $criteria[] = 'totalpart=equals='.$this->option('parts');
        }
        if ($this->option('parts-min')) {
            $criteria[] = 'totalpart=bigger='.$this->option('parts-min');
        }
        if ($this->option('parts-max')) {
            $criteria[] = 'totalpart=smaller='.$this->option('parts-max');
        }

        // Completion filter
        if ($this->option('completion-max')) {
            $criteria[] = 'completion=smaller='.$this->option('completion-max');
        }

        // Status filters
        if ($this->option('nzb-status') !== null) {
            $criteria[] = 'nzbstatus=equals='.$this->option('nzb-status');
        }

        // IMDB filter
        if ($this->option('imdb') !== null) {
            $criteria[] = 'imdbid=equals='.$this->option('imdb');
        }

        // Rage filter
        if ($this->option('rage') !== null) {
            $criteria[] = 'rageid=equals='.$this->option('rage');
        }

        return $criteria;
    }

    /**
     * Show usage information for the command.
     */
    protected function showUsage(): void
    {
        $this->info('Delete releases based on various criteria.');
        $this->newLine();

        $this->info('Simple Usage Examples:');
        $this->line('# Delete releases from a specific group');
        $this->line('php artisan nntmux:delete-releases --group="alt.binaries.teevee"');
        $this->newLine();

        $this->line('# Delete releases by poster name pattern');
        $this->line('php artisan nntmux:delete-releases --poster-like="@spam.com"');
        $this->newLine();

        $this->line('# Delete small releases (under 100MB)');
        $this->line('php artisan nntmux:delete-releases --size-max=104857600');
        $this->newLine();

        $this->line('# Delete old releases (older than 30 days)');
        $this->line('php artisan nntmux:delete-releases --hours-old=720');
        $this->newLine();

        $this->line('# Delete releases from specific category');
        $this->line('php artisan nntmux:delete-releases --category=2999');
        $this->newLine();

        $this->line('# Combine multiple criteria');
        $this->line('php artisan nntmux:delete-releases --group-like="movies" --size-max=1000000 --hours-old=168');
        $this->newLine();

        $this->line('# Preview what would be deleted (dry run)');
        $this->line('php artisan nntmux:delete-releases --dry-run --group-like="spam"');
        $this->newLine();

        $this->line('# Skip confirmation prompt');
        $this->line('php artisan nntmux:delete-releases --force --poster-like="spammer"');
        $this->newLine();

        $this->info('Available Options:');
        $this->line('--group           : Exact group name');
        $this->line('--group-like      : Group name pattern');
        $this->line('--poster          : Exact poster name');
        $this->line('--poster-like     : Poster name pattern');
        $this->line('--name            : Exact release name');
        $this->line('--name-like       : Release name pattern');
        $this->line('--search          : Exact search name');
        $this->line('--search-like     : Search name pattern');
        $this->line('--category        : Category ID');
        $this->line('--guid            : Specific GUID');
        $this->line('--size            : Exact size in bytes');
        $this->line('--size-min        : Minimum size in bytes');
        $this->line('--size-max        : Maximum size in bytes');
        $this->line('--hours-old       : Delete releases older than X hours');
        $this->line('--hours-new       : Delete releases newer than X hours');
        $this->line('--parts           : Exact number of parts');
        $this->line('--parts-min       : Minimum number of parts');
        $this->line('--parts-max       : Maximum number of parts');
        $this->line('--completion-max  : Maximum completion percentage');
        $this->line('--nzb-status      : NZB status');
        $this->line('--imdb            : IMDB ID (use NULL for no IMDB)');
        $this->line('--rage            : Rage ID');
        $this->line('--dry-run         : Preview without deleting');
        $this->line('--force           : Skip confirmation');
        $this->newLine();

        $this->info('Advanced Usage:');
        $this->line('You can still use the original complex criteria format:');
        $this->line('php artisan nntmux:delete-releases groupname=equals="alt.binaries.teevee" searchname=like="olympics 2014"');
    }
}
