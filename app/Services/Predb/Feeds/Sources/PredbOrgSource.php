<?php

declare(strict_types=1);

namespace App\Services\Predb\Feeds\Sources;

use App\Models\Predb;
use App\Services\Predb\Feeds\PredbFeedEntry;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * predb.org JSON API (anonymous, full history, newest first).
 *
 * Row shape: {id, rlsname, section, grp, ctime (unix), size (MB), files, status, nukereason, genre}
 *
 * The site's newest entries can trail the scene by a day, so this source is opt-in: use it with
 * `predb:import-feed --source=predb_org --since=...` to fill in history, not as a live feed.
 */
final class PredbOrgSource extends HttpFeedSource
{
    /** predb.org caps `limit` at 100. */
    private const int MAX_PAGE_SIZE = 100;

    public function key(): string
    {
        return 'predb_org';
    }

    public function fetch(int $page = 1): array
    {
        $response = $this->http()->get($this->endpoint, [
            // `q` is required; `*` lists every release, newest first.
            'q' => '*',
            'limit' => min($this->pageSize, self::MAX_PAGE_SIZE),
            'page' => max(1, $page),
        ])->throw();

        $rows = $response->json('values');
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new RuntimeException('predb.org returned an unexpected response (no values).');
        }

        return $this->parseRows($rows);
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<PredbFeedEntry>
     */
    public function parseRows(array $rows): array
    {
        $entries = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! is_string($row['rlsname'] ?? null) || trim($row['rlsname']) === '') {
                continue;
            }

            $reason = is_string($row['nukereason'] ?? null) && $row['nukereason'] !== '' ? $row['nukereason'] : null;
            // `status` 1 is a nuke (the reason can be empty); 0 is a normal release.
            $nuked = (string) ($row['status'] ?? '0') === '1' || $reason !== null;

            $entries[] = new PredbFeedEntry(
                title: trim($row['rlsname']),
                source: 'predb.org',
                category: is_string($row['section'] ?? null) && $row['section'] !== '' ? $row['section'] : null,
                size: PredbFeedEntry::sizeFromMegabytes(is_numeric($row['size'] ?? null) ? (float) $row['size'] : null),
                files: PredbFeedEntry::filesFromCount(is_numeric($row['files'] ?? null) ? (int) $row['files'] : null),
                predate: is_numeric($row['ctime'] ?? null) ? CarbonImmutable::createFromTimestampUTC((int) $row['ctime']) : null,
                nuked: $nuked ? Predb::PRE_NUKED : Predb::PRE_NONUKE,
                nukeReason: $reason,
            );
        }

        return $entries;
    }
}
