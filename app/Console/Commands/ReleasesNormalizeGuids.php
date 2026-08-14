<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Reports the guid problems that block the releases normalization migration, and
 * repairs the one class of problem that can be fixed without side effects.
 *
 * `leftguid` is derived data that nothing outside the database depends on, so it is
 * rewritten in place. Invalid and duplicated `guid` values are only reported: a guid
 * determines the on-disk NZB path and is published in download links, so replacing one
 * is a migration of file and link state rather than a database repair.
 */
#[Signature('releases:normalize-guids
    {--dry-run : Report the required changes without writing anything}
    {--chunk=1000 : Rows handled per batch}')]
#[Description('Report release guid problems and resync leftguid so the releases normalization migration can run')]
class ReleasesNormalizeGuids extends Command
{
    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    private bool $dryRun = false;

    public function handle(): int
    {
        if (! Schema::hasTable('releases')) {
            $this->error('The releases table does not exist.');

            return self::FAILURE;
        }

        $this->dryRun = (bool) $this->option('dry-run');
        if ($this->dryRun) {
            $this->info('Dry run: no rows are modified.');
        }

        $leftGuids = $this->syncLeftGuids();
        $invalid = $this->collectInvalidGuidIds();
        $duplicates = $this->collectDuplicateGuidIds();

        $this->table(['Issue', 'Releases'], [
            ['leftguid out of sync with guid', (string) $leftGuids],
            ['guid is not a UUID', (string) count($invalid)],
            ['guid duplicated case-insensitively', (string) count($duplicates)],
        ]);

        $blocking = array_values(array_unique([...$invalid, ...$duplicates]));
        if ($blocking === []) {
            $this->info($this->dryRun
                ? 'No guid problems found. Re-run without --dry-run to apply the leftguid sync.'
                : 'Release guids are consistent.');

            return self::SUCCESS;
        }

        $this->reportBlockingReleases($blocking);

        return self::FAILURE;
    }

    /**
     * Print the releases that must be resolved by hand before the migration can run.
     *
     * @param  list<int>  $ids
     */
    private function reportBlockingReleases(array $ids): void
    {
        $this->error(count($ids).' releases have a guid that blocks the normalization migration.');
        $this->warn(
            'These are not repaired automatically. A guid determines the release NZB path and is published in '
            .'download links, so a new guid orphans the existing NZB file and breaks every link and download_stats '
            .'row that references the old value. Resolve them deliberately: delete the affected releases, or assign '
            .'new guids and relocate the matching NZB files yourself.'
        );

        $sample = array_slice($ids, 0, 20);
        $rows = DB::table('releases')
            ->select(['id', 'guid', 'name'])
            ->whereIn('id', $sample)
            ->orderBy('id')
            ->get();

        $this->table(['Release ID', 'Guid', 'Name'], $rows->map(static fn ($row): array => [
            (string) $row->id,
            (string) $row->guid,
            Str::limit((string) $row->name, 60),
        ])->all());

        if (count($ids) > count($sample)) {
            $this->line('… and '.(count($ids) - count($sample)).' more.');
        }
    }

    /**
     * Point `leftguid` at the first character of `guid` wherever they disagree.
     * Safe on its own: no file or index state depends on `leftguid`.
     */
    private function syncLeftGuids(): int
    {
        $chunk = $this->chunkSize();
        $total = 0;
        $lastId = 0;

        do {
            $rows = DB::table('releases')
                ->select(['id', 'guid'])
                ->where('id', '>', $lastId)
                ->whereRaw('LOWER(leftguid) <> LOWER(SUBSTR(guid, 1, 1))')
                ->orderBy('id')
                ->limit($chunk)
                ->get();

            foreach ($rows as $row) {
                $lastId = max($lastId, (int) $row->id);
                $total++;
                if (! $this->dryRun) {
                    DB::table('releases')
                        ->where('id', (int) $row->id)
                        ->update(['leftguid' => substr((string) $row->guid, 0, 1)]);
                }
            }

            if ($rows->count() < $chunk) {
                break;
            }
        } while (true);

        return $total;
    }

    /** @return list<int> */
    private function collectInvalidGuidIds(): array
    {
        $ids = [];
        DB::table('releases')->select(['id', 'guid'])->orderBy('id')->chunk($this->chunkSize(), function ($rows) use (&$ids): void {
            foreach ($rows as $row) {
                if (! is_string($row->guid) || preg_match(self::UUID_PATTERN, $row->guid) !== 1) {
                    $ids[] = (int) $row->id;
                }
            }
        });

        return $ids;
    }

    /**
     * Every release that shares a case-insensitive guid with a lower-id release.
     * The lowest id is treated as the owner of the guid and is not reported.
     *
     * @return list<int>
     */
    private function collectDuplicateGuidIds(): array
    {
        $duplicated = DB::table('releases')
            ->selectRaw('LOWER(guid) AS normalized_guid')
            ->groupByRaw('LOWER(guid)')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('normalized_guid');

        $ids = [];
        foreach ($duplicated->chunk(500) as $batch) {
            $rows = DB::table('releases')
                ->select(['id', 'guid'])
                ->whereIn(DB::raw('LOWER(guid)'), $batch->all())
                ->orderBy('id')
                ->get();

            $seen = [];
            foreach ($rows as $row) {
                $key = strtolower((string) $row->guid);
                if (isset($seen[$key])) {
                    $ids[] = (int) $row->id;

                    continue;
                }
                $seen[$key] = true;
            }
        }

        sort($ids);

        return $ids;
    }

    private function chunkSize(): int
    {
        return max(100, min(10000, (int) $this->option('chunk')));
    }
}
