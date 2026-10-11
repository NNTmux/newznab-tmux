<?php

declare(strict_types=1);

namespace App\Services\Predb\Feeds\Sources;

use App\Services\Predb\Feeds\PredbFeedEntry;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * apibay.org "top 100 recent" precompiled dump (The Pirate Bay).
 *
 * Row shape: {id, name, info_hash, leechers, seeders, num_files, size (bytes), username, added (unix), status, category, imdb}
 *
 * These are P2P names, not scene PREs. Only movie (201, 207) and TV (205, 208) categories are kept; every
 * other category, including adult, is dropped. The dump is a single file, so pages past the first are empty.
 * Opt-in only.
 */
final class ApibaySource extends HttpFeedSource
{
    /** @var array<int, string> */
    private const array CATEGORIES = [
        201 => 'MOVIE',
        207 => 'MOVIE-HD',
        205 => 'TV',
        208 => 'TV-HD',
    ];

    public function key(): string
    {
        return 'apibay';
    }

    public function fetch(int $page = 1): array
    {
        if ($page > 1) {
            return [];
        }

        $rows = $this->http()->get($this->endpoint)->throw()->json();
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new RuntimeException('apibay returned an unexpected response (not a JSON list).');
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

            $category = self::CATEGORIES[(int) ($row['category'] ?? 0)] ?? null;
            if ($category === null) {
                continue;
            }

            $entries[] = new PredbFeedEntry(
                title: trim($row['name']),
                source: 'apibay',
                category: $category,
                size: PredbFeedEntry::sizeFromMegabytes(is_numeric($row['size'] ?? null) ? (float) $row['size'] / 1048576 : null),
                files: PredbFeedEntry::filesFromCount(is_numeric($row['num_files'] ?? null) ? (int) $row['num_files'] : null),
                predate: is_numeric($row['added'] ?? null) ? CarbonImmutable::createFromTimestampUTC((int) $row['added']) : null,
            );

            if (count($entries) >= $this->pageSize) {
                break;
            }
        }

        return $entries;
    }
}
