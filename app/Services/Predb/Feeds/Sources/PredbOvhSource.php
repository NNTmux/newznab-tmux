<?php

declare(strict_types=1);

namespace App\Services\Predb\Feeds\Sources;

use App\Models\Predb;
use App\Services\Predb\Feeds\FeedRateLimitedException;
use App\Services\Predb\Feeds\PredbFeedEntry;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * predb.ovh JSON API (v1).
 *
 * Written from the published v1 documentation (https://github.com/predbdotovh/pre-api/blob/master/docs/index.md):
 * base URL https://predb.ovh/api/v1/, parameters `count` and `page`, rows
 * {id, name, team, cat, genre, url, size (kB), files, preAt (unix), nuke: {typeId, type, reason, ...}|null}.
 * Nuke type ids: 1 nuke, 2 unnuke, 3 modnuke, 4 delpre, 5 undelpre. The service has not been reachable from our
 * network when this was written, so it is not verified against live responses. Opt-in only: add `predb_ovh`
 * to PREDB_FEED_SOURCES once the service is confirmed.
 */
final class PredbOvhSource extends HttpFeedSource
{
    public function key(): string
    {
        return 'predb_ovh';
    }

    public function fetch(int $page = 1): array
    {
        $response = $this->http()->get($this->endpoint, [
            'count' => $this->pageSize,
            'page' => max(1, $page),
        ])->throw();

        if ($response->json('status') === 'error') {
            $message = (string) $response->json('message', 'unknown');

            throw str_contains(strtolower($message), 'rate limit')
                ? new FeedRateLimitedException('predb.ovh rate limited: '.$message)
                : new RuntimeException('predb.ovh API error: '.$message);
        }

        $rows = $response->json('data.rows');
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new RuntimeException('predb.ovh returned an unexpected response (no data.rows).');
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
            if (! is_array($row) || ! is_string($row['name'] ?? null) || trim($row['name']) === '') {
                continue;
            }

            $nuke = is_array($row['nuke'] ?? null) ? $row['nuke'] : null;
            $typeId = $nuke !== null ? (int) ($nuke['typeId'] ?? 0) : 0;
            $reason = is_string($nuke['reason'] ?? null) && $nuke['reason'] !== '' ? $nuke['reason'] : null;

            $entries[] = new PredbFeedEntry(
                title: trim($row['name']),
                source: 'predb.ovh',
                category: is_string($row['cat'] ?? null) && $row['cat'] !== '' ? $row['cat'] : null,
                size: PredbFeedEntry::sizeFromMegabytes(is_numeric($row['size'] ?? null) ? (float) $row['size'] / 1024 : null),
                files: PredbFeedEntry::filesFromCount(is_numeric($row['files'] ?? null) ? (int) $row['files'] : null),
                predate: is_numeric($row['preAt'] ?? null) ? CarbonImmutable::createFromTimestampUTC((int) $row['preAt']) : null,
                nuked: match ($typeId) {
                    0 => Predb::PRE_NONUKE,
                    1 => Predb::PRE_NUKED,
                    2, 5 => Predb::PRE_UNNUKED,
                    3 => Predb::PRE_MODNUKE,
                    default => Predb::PRE_NUKED,
                },
                nukeReason: $typeId === 0 ? null : $reason,
            );
        }

        return $entries;
    }
}
