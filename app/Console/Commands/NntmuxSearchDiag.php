<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Release;
use App\Models\Settings;
use App\Services\Search\Drivers\ManticoreSearchDriver;
use App\Support\ReleaseSearchIndexDocument;
use Illuminate\Console\Command;
use Throwable;

class NntmuxSearchDiag extends Command
{
    protected $signature = 'nntmux:search-diag
                            {ids?* : Release numeric ids or guids}
                            {--name= : Match releases where name or searchname contains this substring (MySQL)}
                            {--limit=20 : Max rows when using --name}
                            {--show-fields : Dump DB row and index document as JSON lines}';

    protected $description = 'Compare releases table rows to Manticore releases_rt documents (diagnostics)';

    public function handle(ManticoreSearchDriver $manticore): int
    {
        if (config('search.default') !== 'manticore') {
            $this->error('This command only supports SEARCH_DRIVER=manticore.');

            return self::FAILURE;
        }

        if (! $manticore->isAvailable()) {
            $this->error('Manticore is not reachable (isAvailable() returned false).');

            return self::FAILURE;
        }

        $index = $manticore->getReleasesIndex();
        $nameFilter = (string) $this->option('name');
        $ids = $this->argument('ids');

        $releaseIds = [];
        if ($nameFilter !== '') {
            $limit = max(1, min(500, (int) $this->option('limit')));
            $like = '%'.addcslashes($nameFilter, '%_\\').'%';
            $releaseIds = Release::query()
                ->where(function ($q) use ($like): void {
                    $q->where('name', 'LIKE', $like)
                        ->orWhere('searchname', 'LIKE', $like);
                })
                ->orderByDesc('id')
                ->limit($limit)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
            if ($releaseIds === []) {
                $this->warn('No releases matched --name filter.');

                return self::SUCCESS;
            }
        } else {
            if ($ids === [] || $ids === null) {
                $this->error('Provide at least one id/guid or use --name=');

                return self::FAILURE;
            }
            foreach ($ids as $token) {
                $resolved = $this->resolveReleaseId((string) $token);
                if ($resolved === null) {
                    $this->warn("No release found for token: {$token}");

                    continue;
                }
                $releaseIds[] = $resolved;
            }
        }

        if ($releaseIds === []) {
            return self::FAILURE;
        }

        $showPasswords = (int) Settings::settingValue('showpasswordedrelease') === 1;
        $verbose = (bool) $this->option('show-fields');

        foreach ($releaseIds as $rid) {
            $this->diagnoseOne((int) $rid, $manticore, $index, $showPasswords, $verbose);
        }

        return self::SUCCESS;
    }

    private function resolveReleaseId(string $token): ?int
    {
        if (ctype_digit($token)) {
            $id = (int) $token;
            $exists = Release::query()->whereKey($id)->exists();

            return $exists ? $id : null;
        }

        return Release::query()->where('guid', $token)->value('id');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function manticoreSqlRows(ManticoreSearchDriver $driver, string $sql): array
    {
        try {
            $response = $driver->manticoreSearch->sql($sql, true);
        } catch (Throwable $e) {
            $this->error('Manticore SQL error: '.$e->getMessage());

            return [];
        }

        if (! \is_array($response)) {
            return [];
        }

        // In raw mode (sql($q, true)) the response goes through
        // Manticoresearch\Response\SqlToArray::getResponse(), which flattens a SELECT
        // result so each row is keyed by its `id` (and the `id` column is removed from
        // multi-column rows). Reconstruct row arrays here. The legacy
        // ['data' => [...]] shape is retained as a fallback.
        $rows = [];
        foreach ($response as $key => $value) {
            if (\is_int($key) || ctype_digit($key)) {
                if (\is_array($value)) {
                    $row = $value;
                    if (! isset($row['id'])) {
                        $row['id'] = (int) $key;
                    }
                    $rows[] = $row;
                } else {
                    // Single-column id projection: value mirrors the key.
                    $rows[] = ['id' => (int) $key];
                }
            }
        }

        if ($rows !== []) {
            return $rows;
        }

        if (isset($response['data']) && \is_array($response['data'])) {
            return array_values($response['data']);
        }

        return [];
    }

    private function diagnoseOne(
        int $releaseId,
        ManticoreSearchDriver $driver,
        string $index,
        bool $showPasswords,
        bool $verbose,
    ): void {
        $release = Release::query()->find($releaseId);
        if ($release === null) {
            $this->line(sprintf('%d | NOT_IN_DB', $releaseId));

            return;
        }

        $sql = sprintf('SELECT * FROM `%s` WHERE id = %d', str_replace('`', '``', $index), $releaseId);
        $rows = $this->manticoreSqlRows($driver, $sql);
        $indexRow = $rows[0] ?? null;

        $status = $this->classify($release, $indexRow, $showPasswords, $verbose);

        $this->line(sprintf(
            '%d | %s | searchname=%s | size=%s | passwordstatus=%s',
            $releaseId,
            $status,
            $release->searchname,
            (string) $release->size,
            (string) $release->passwordstatus
        ));

        if ($verbose) {
            $this->line('DB: '.json_encode($release->only([
                'id', 'name', 'searchname', 'categories_id', 'passwordstatus', 'nzbstatus',
                'groups_id', 'size', 'postdate', 'adddate',
            ])));
            $this->line('IX: '.json_encode($indexRow));
        }
    }

    /**
     * @param  array<string, mixed>|null  $indexRow
     */
    private function classify(Release $release, ?array $indexRow, bool $showPasswords, bool $verbose): string
    {
        if ($indexRow === null) {
            return 'MISSING_IN_INDEX';
        }

        $maxVisiblePasswordStatus = $showPasswords ? 1 : 0;
        $hidden = (int) $release->passwordstatus > $maxVisiblePasswordStatus;

        $expected = ReleaseSearchIndexDocument::normalize($release->toArray());
        unset($expected['id']);

        $drift = false;
        foreach (['searchname', 'name', 'categories_id', 'passwordstatus', 'size', 'groups_id', 'nzbstatus'] as $field) {
            $dbVal = $expected[$field] ?? null;
            $ixVal = $indexRow[$field] ?? null;
            if ((string) $dbVal !== (string) $ixVal) {
                $drift = true;
                if ($verbose) {
                    $this->line("  drift: {$field} db=".json_encode($dbVal).' ix='.json_encode($ixVal));
                }
            }
        }

        $dbPostTs = (int) ($expected['postdate_ts'] ?? 0);
        $ixPostTs = isset($indexRow['postdate_ts']) ? (int) $indexRow['postdate_ts'] : 0;
        if (abs($dbPostTs - $ixPostTs) > 2) {
            $drift = true;
        }

        $dbAddTs = (int) ($expected['adddate_ts'] ?? 0);
        $ixAddTs = isset($indexRow['adddate_ts']) ? (int) $indexRow['adddate_ts'] : 0;
        if (abs($dbAddTs - $ixAddTs) > 2) {
            $drift = true;
        }

        if ($drift) {
            return 'STALE_INDEX';
        }

        if ($hidden) {
            return 'STATUS_HIDDEN';
        }

        return 'OK';
    }
}
