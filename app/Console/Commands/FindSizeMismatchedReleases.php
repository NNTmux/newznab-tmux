<?php

namespace App\Console\Commands;

use App\Models\Release;
use App\Services\NameFixing\NameFixingService;
use App\Services\NameFixing\ReleaseUpdateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FindSizeMismatchedReleases extends Command
{
    protected $signature = 'nntmux:find-size-mismatches {--threshold=20} {--limit=100} {--season-pack} {--direction=any} {--rename}';

    protected $description = 'Find releases where size differs significantly from release_files total. Use --direction=bigger|smaller|any';

    public function handle()
    {
        $threshold = $this->option('threshold'); // Percentage difference threshold
        $limit = $this->option('limit');
        $checkSeasonPack = $this->option('season-pack');
        $direction = $this->option('direction');
        $shouldRename = $this->option('rename');
        $nameFixingService = new NameFixingService;

        $query = Release::query()
            ->select([
                'releases.id',
                'releases.searchname',
                'releases.name',
                'releases.groups_id',
                'releases.categories_id',
                DB::raw('releases.size / POW(1024, 3) as release_size'),
                DB::raw('SUM(release_files.size) / POW(1024, 3) as files_total_size'),
                DB::raw('(releases.size - SUM(release_files.size)) / POW(1024, 3) as size_diff'),
                DB::raw('((releases.size - SUM(release_files.size)) / releases.size * 100) as diff_percent'),
            ])
            ->join('release_files', 'releases.id', '=', 'release_files.releases_id')
            ->where('releases.searchname', 'REGEXP', 'S[0-9]{1,3}E[0-9]{1,3}')
            ->groupBy('releases.id');

        // Apply direction filter
        if ($direction === 'bigger') {
            $query->having('size_diff', '>', 0)
                ->having('diff_percent', '>', $threshold);
        } elseif ($direction === 'smaller') {
            $query->having('size_diff', '<', 0)
                ->having('diff_percent', '<', -$threshold);
        } else {
            $query->having(DB::raw('ABS(diff_percent)'), '>', $threshold);
        }

        // Order by ID if renaming, otherwise by diff_percent
        $query->orderBy($shouldRename ? 'releases.id' : 'diff_percent', $shouldRename ? 'asc' : 'desc');

        if ($limit > 0) {
            $query->limit((int) $limit);
        }

        $mismatches = $query->get();

        if ($checkSeasonPack) {
            $mismatches = $mismatches->filter(function ($release) use ($nameFixingService) {
                return $nameFixingService->isSeasonPack($release->name);
            });
        }

        if ($mismatches->isEmpty()) {
            $this->info('No releases found with size mismatches above '.$threshold.'%'
                .($checkSeasonPack ? ' that are season packs' : ''));

            return;
        }

        if ($shouldRename) {
            $this->info("\nAttempting to rename ".$mismatches->count()." releases...\n");

            foreach ($mismatches as $release) {
                $this->attemptRename($release, $nameFixingService);
            }

            $this->outputReleaseIdsAsCsv($mismatches);

            return;
        }

        // Regular table output for non-rename mode
        $headers = ['Release ID', 'Searchname', 'Release Size', 'Files Total', 'Difference', 'Diff %'];
        $rows = $mismatches->map(function ($release) {
            return [
                $release->id,
                $release->searchname,
                number_format($release->release_size, 2).' GiB',
                number_format($release->files_total_size, 2).' GiB',
                number_format($release->size_diff, 2).' GiB',
                number_format($release->diff_percent, 2).'%',
            ];
        });

        $this->table($headers, $rows);
        $this->info("\nFound ".$mismatches->count().' releases with size mismatches above '.$threshold.'%');

        $this->outputReleaseIdsAsCsv($mismatches);
    }

    private function attemptRename(Release $release, NameFixingService $nameFixingService): ?string
    {
        if (preg_match(ReleaseUpdateService::PREDB_REGEX, $this->stripDomainFromString($release->name), $matches)) {
            $newName = $matches[1];
            $nameFixingService->getUpdateService()->updateRelease(
                release: $release,
                name: $newName,
                method: 'size-mismatch / season pack',
                echo: true,
                type: '',
                nameStatus: true,
                show: true,
                preId: 0
            );

            return $newName;
        }

        return null;
    }

    private function outputReleaseIdsAsCsv($mismatches): void
    {
        $releaseIds = $mismatches->pluck('id')->join(',');
        $this->line("\nRelease IDs in CSV format:");
        $this->line($releaseIds);
    }

    private function stripDomainFromString(string $str): string
    {
        return preg_replace("/www\.[^\s]+\.[a-z]{2,4}/i", '', $str);
    }
}
