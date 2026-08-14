<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Reports the guid problems that block the releases normalization migration, and
 * repairs the one class of problem that can be fixed without side effects.
 *
 * `leftguid` is derived data that nothing outside the database depends on, so it is
 * rewritten in place. Oversized and duplicated `guid` values are only reported: a guid
 * determines the on-disk NZB path and is published in download links, so replacing one
 * is a migration of file and link state rather than a database repair.
 *
 * The migration narrows `guid` to `CHAR(40) ascii`, so legacy 40 character nZEDb SHA1
 * guids are still valid and are reported as informational only. Only values longer than
 * 40 characters, values containing non-printable-ASCII bytes, and case-insensitive
 * duplicates actually block the rebuild.
 *
 * Every scan here is either a single aggregate or a keyset (`id > ?`) walk. Never use
 * `chunk()`: its `LIMIT/OFFSET` paging degrades quadratically, and on a multi-million
 * row `releases` table the later batches re-read millions of rows each.
 */
#[Signature('releases:normalize-guids
    {--dry-run : Report the required changes without writing anything}
    {--chunk=1000 : Rows handled per batch}')]
#[Description('Report release guid problems and resync leftguid so the releases normalization migration can run')]
class ReleasesNormalizeGuids extends Command
{
    /** Values that survive the narrowing to `CHAR(40) ascii`. */
    private const string SUPPORTED_PATTERN = '/^[\x20-\x7E]{1,40}$/D';

    private const string SUPPORTED_REGEXP = '^[ -~]{1,40}$';

    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    private const string UUID_REGEXP = '^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}$';

    private const int SAMPLE_SIZE = 20;

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
        $unsupported = $this->countUnsupportedGuids();
        $duplicates = $this->countDuplicateGuids();
        $legacy = $this->countLegacyGuids();

        $this->table(['Issue', 'Releases', 'Blocks migration'], [
            ['leftguid out of sync with guid', (string) $leftGuids, 'no (repaired)'],
            ['guid longer than 40 chars or not ASCII', (string) $unsupported, 'yes'],
            ['guid duplicated case-insensitively', (string) $duplicates, 'yes'],
            ['guid is a legacy non-UUID', (string) $legacy, 'no'],
        ]);

        if ($unsupported + $duplicates === 0) {
            if ($legacy > 0) {
                $this->line(
                    $legacy.' releases still carry a legacy non-UUID guid. These fit CHAR(40) ascii and are kept '
                    .'as-is so their NZB paths and published download links keep working. Only newly created '
                    .'releases get a UUID, so this count decays on its own.'
                );
            }

            $this->info($this->dryRun
                ? 'No blocking guid problems found. Re-run without --dry-run to apply the leftguid sync.'
                : 'Release guids are consistent.');

            return self::SUCCESS;
        }

        $this->reportBlockingReleases($unsupported + $duplicates);

        return self::FAILURE;
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

    /**
     * On MySQL this is a single aggregate. Elsewhere it is a keyset walk that
     * applies the pattern in PHP, keeping memory flat regardless of table size.
     */
    private function countUnsupportedGuids(): int
    {
        if ($this->isMySql()) {
            return $this->unsupportedGuidQuery()->count();
        }

        $count = 0;
        $this->walkByKey(function (object $row) use (&$count): bool {
            if ($this->isUnsupportedGuid($row->guid)) {
                $count++;
            }

            return true;
        });

        return $count;
    }

    /**
     * Legacy guids fit the narrowed column, so this is reported for visibility only.
     * It is the count of releases whose download links predate UUID generation.
     */
    private function countLegacyGuids(): int
    {
        if ($this->isMySql()) {
            return DB::table('releases')
                ->whereRaw("`guid` NOT REGEXP '".self::UUID_REGEXP."'")
                ->count();
        }

        $count = 0;
        $this->walkByKey(function (object $row) use (&$count): bool {
            if (! is_string($row->guid) || preg_match(self::UUID_PATTERN, $row->guid) !== 1) {
                $count++;
            }

            return true;
        });

        return $count;
    }

    /**
     * The number of rows that share a guid with a lower-id row, which is exactly
     * the total row count minus the number of distinct guids.
     */
    private function countDuplicateGuids(): int
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS total, COUNT(DISTINCT LOWER(`guid`)) AS distinct_guids FROM '.$this->prefixedTable('releases')
        );

        return max(0, (int) ($row->total ?? 0) - (int) ($row->distinct_guids ?? 0));
    }

    /**
     * Print a bounded sample of the releases that must be resolved by hand before
     * the migration can run.
     */
    private function reportBlockingReleases(int $total): void
    {
        $this->error($total.' releases have a guid that blocks the normalization migration.');
        $this->warn(
            'These are not repaired automatically. A guid determines the release NZB path and is published in '
            .'download links, so a new guid orphans the existing NZB file and breaks every link and download_stats '
            .'row that references the old value. Resolve them deliberately: delete the affected releases, or assign '
            .'new guids and relocate the matching NZB files yourself.'
        );
        $sample = $this->sampleBlockingReleases();
        if ($sample === []) {
            return;
        }

        $this->table(['Release ID', 'Guid', 'Name'], array_map(static fn (object $row): array => [
            (string) $row->id,
            (string) $row->guid,
            Str::limit((string) ($row->name ?? ''), 60),
        ], $sample));

        if ($total > count($sample)) {
            $this->line('… and '.($total - count($sample)).' more.');
        }
    }

    /** @return list<object> */
    private function sampleBlockingReleases(): array
    {
        if ($this->isMySql()) {
            $unsupported = $this->unsupportedGuidQuery()
                ->select(['id', 'guid', 'name'])
                ->orderBy('id')
                ->limit(self::SAMPLE_SIZE)
                ->get()
                ->all();

            if (count($unsupported) >= self::SAMPLE_SIZE) {
                return $unsupported;
            }

            return [...$unsupported, ...$this->sampleDuplicateGuids(self::SAMPLE_SIZE - count($unsupported), $unsupported)];
        }

        $sample = [];
        $this->walkByKey(function (object $row) use (&$sample): bool {
            if ($this->isUnsupportedGuid($row->guid)) {
                $sample[] = $row;
            }

            return count($sample) < self::SAMPLE_SIZE;
        }, ['id', 'guid', 'name']);

        return $sample;
    }

    /**
     * @param  list<object>  $exclude
     * @return list<object>
     */
    private function sampleDuplicateGuids(int $limit, array $exclude): array
    {
        if ($limit <= 0) {
            return [];
        }

        $excludedIds = array_map(static fn (object $row): int => (int) $row->id, $exclude);
        $releases = $this->prefixedTable('releases');

        return DB::select(
            "SELECT r.`id` AS id, r.`guid` AS guid, r.`name` AS name
             FROM {$releases} r
             JOIN (SELECT LOWER(`guid`) AS normalized_guid, MIN(`id`) AS keep_id
                   FROM {$releases} GROUP BY LOWER(`guid`) HAVING COUNT(*) > 1) d
               ON LOWER(r.`guid`) = d.normalized_guid AND r.`id` > d.keep_id
             ".($excludedIds === [] ? '' : 'WHERE r.`id` NOT IN ('.implode(', ', $excludedIds).')')."
             ORDER BY r.`id` LIMIT {$limit}"
        );
    }

    /**
     * Walk `releases` in primary key order. The callback returns false to stop.
     *
     * @param  callable(object): bool  $callback
     * @param  list<string>  $columns
     */
    private function walkByKey(callable $callback, array $columns = ['id', 'guid']): void
    {
        $chunk = $this->chunkSize();
        $lastId = 0;

        do {
            $rows = DB::table('releases')
                ->select($columns)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($chunk)
                ->get();

            foreach ($rows as $row) {
                $lastId = max($lastId, (int) $row->id);
                if (! $callback($row)) {
                    return;
                }
            }
        } while ($rows->count() === $chunk);
    }

    private function unsupportedGuidQuery(): Builder
    {
        return DB::table('releases')->where(function ($query): void {
            $query->whereNull('guid')
                ->orWhereRaw("`guid` NOT REGEXP '".self::SUPPORTED_REGEXP."'");
        });
    }

    private function isUnsupportedGuid(mixed $guid): bool
    {
        return ! is_string($guid) || preg_match(self::SUPPORTED_PATTERN, $guid) !== 1;
    }

    private function isMySql(): bool
    {
        return in_array(DB::getDriverName(), ['mariadb', 'mysql'], true);
    }

    private function prefixedTable(string $name): string
    {
        return '`'.DB::getTablePrefix().$name.'`';
    }

    private function chunkSize(): int
    {
        return max(100, min(10000, (int) $this->option('chunk')));
    }
}
