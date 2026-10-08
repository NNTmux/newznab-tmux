<?php

declare(strict_types=1);

namespace App\Services\LogViewer\Index;

use App\Models\LogIndexFile;
use App\Services\LogViewer\LogEntry;
use App\Services\LogViewer\LogEntryParser;
use App\Services\LogViewer\LogFile;
use App\Services\LogViewer\LogReader;
use App\Services\LogViewer\LogSearcher;
use App\Services\LogViewer\SearchQuery;

/**
 * Answers log searches from the Manticore log index, with the same per-file result shape as
 * {@see LogSearcher}.
 *
 * Manticore supplies newest-first candidates; each is re-checked with {@see SearchQuery::matches()} so
 * results keep grep's substring and case semantics. The part of the file written since the last indexer
 * run (at most the lag tolerance) is scanned directly, so the newest entries are never missing.
 */
class ManticoreLogSearcher
{
    private const int CANDIDATE_FACTOR = 2;

    private const int MAX_ROUNDS = 5;

    private const int LEVEL_FACET_SIZE = 20;

    private const int CHANNEL_FACET_SIZE = 200;

    public function __construct(
        private readonly LogIndex $index,
        private readonly LogReader $reader,
        private readonly LogEntryParser $parser,
    ) {}

    /**
     * @return array{file: string, total_matches: int, total_is_estimate: bool, has_more: bool, before: int|null, timed_out: bool, error: string|null, entries: list<array<string, mixed>>}
     */
    public function searchFile(SearchQuery $query, LogFile $file, LogIndexFile $row, ?int $before, int $limit): array
    {
        $tail = $this->tailMatches($query, $file, $row, $before);
        $shown = array_slice($tail, 0, $limit);
        $cursor = $shown === [] ? null : $shown[array_key_last($shown)]->offset;

        $indexedBefore = $before === null ? $row->indexed_offset : min($before, $row->indexed_offset);
        $indexed = $this->indexedMatches($query, $row, $indexedBefore, $limit - count($shown));

        $hasMore = count($tail) > count($shown) || $indexed['has_more'];

        if ($indexed['cursor'] !== null) {
            $cursor = $indexed['cursor'];
        }

        return [
            'file' => $file->path,
            'total_matches' => count($tail) + $indexed['total'],
            'total_is_estimate' => ! $indexed['exact'],
            'has_more' => $hasMore,
            'before' => $hasMore ? $cursor : null,
            'timed_out' => false,
            'error' => null,
            'entries' => array_map(
                static fn (LogEntry $entry): array => $entry->toArray($query),
                [...$shown, ...$indexed['entries']],
            ),
        ];
    }

    /**
     * Level and channel counts over the indexed part of the given files. Each facet ignores its own
     * filter, so the other values stay visible while some are selected.
     *
     * @param  list<LogIndexFile>  $rows
     * @return array{levels: array<string, int>, channels: array<string, int>}
     */
    public function facets(SearchQuery $query, array $rows): array
    {
        if ($rows === []) {
            return ['levels' => [], 'channels' => []];
        }

        $files = ['in' => ['file_id' => array_map(static fn (LogIndexFile $row): int => $row->id, $rows)]];

        return [
            'levels' => $this->index->facet(['bool' => ['must' => [...LogIndexQuery::clauses($query, withLevels: false), $files]]], 'level', self::LEVEL_FACET_SIZE),
            'channels' => $this->index->facet(['bool' => ['must' => [...LogIndexQuery::clauses($query, withChannels: false), $files]]], 'channel', self::CHANNEL_FACET_SIZE),
        ];
    }

    /**
     * Matching entries that start before `$before` in the region the index already covers, newest first.
     *
     * @return array{entries: list<LogEntry>, total: int, exact: bool, has_more: bool, cursor: int|null}
     */
    private function indexedMatches(SearchQuery $query, LogIndexFile $row, int $before, int $want): array
    {
        $clauses = [...LogIndexQuery::clauses($query), ['equals' => ['file_id' => $row->id]]];
        $verify = $query->hasTerm();

        if ($want <= 0) {
            $result = $this->index->search(['bool' => ['must' => [...$clauses, $this->below($before)]]], [['byte_offset' => 'desc']], 1);

            return ['entries' => [], 'total' => $result['total'], 'exact' => $result['exact'] && ! $verify, 'has_more' => $result['total'] > 0, 'cursor' => null];
        }

        $fetch = $verify ? $want * self::CANDIDATE_FACTOR : $want;
        $cursor = $before;
        $entries = [];
        $total = 0;
        $exact = true;
        $exhausted = false;

        for ($round = 0; $round < self::MAX_ROUNDS && count($entries) < $want; $round++) {
            $result = $this->index->search(['bool' => ['must' => [...$clauses, $this->below($cursor)]]], [['byte_offset' => 'desc']], $fetch);

            if ($round === 0) {
                $total = $result['total'];
                $exact = $result['exact'];
            }

            $hits = $result['hits'];

            foreach ($hits as $position => $hit) {
                $cursor = (int) $hit['byte_offset'];
                $entry = $this->entryFromHit($hit);

                if (! $query->acceptsEntry($entry) || ($verify && ! $this->textMatches($query, (string) ($hit['first_line'] ?? ''), $entry->body))) {
                    continue;
                }

                $entries[] = $entry;

                if (count($entries) >= $want) {
                    $exhausted = $position === count($hits) - 1 && count($hits) < $fetch;

                    break 2;
                }
            }

            if (count($hits) < $fetch) {
                $exhausted = true;

                break;
            }
        }

        if ($exhausted && $verify) {
            // Every candidate was re-checked, so the verified count is exact.
            return ['entries' => $entries, 'total' => count($entries), 'exact' => true, 'has_more' => false, 'cursor' => null];
        }

        return [
            'entries' => $entries,
            'total' => $total,
            'exact' => $exact && ! $verify,
            'has_more' => ! $exhausted,
            'cursor' => $exhausted ? null : $cursor,
        ];
    }

    /**
     * Matching entries written after the indexed offset (and before `$before`), newest first.
     *
     * @return list<LogEntry>
     */
    private function tailMatches(SearchQuery $query, LogFile $file, LogIndexFile $row, ?int $before): array
    {
        $end = min($before ?? PHP_INT_MAX, $file->size);

        if ($row->indexed_offset >= $end) {
            return [];
        }

        $matches = [];

        foreach ($this->reader->entriesForward($file, $row->indexed_offset, $row->indexed_line, PHP_INT_MAX, true) as [$entry, $firstLine]) {
            if ($entry->offset >= $end) {
                break;
            }

            if ($query->acceptsEntry($entry) && (! $query->hasTerm() || $this->textMatches($query, $firstLine, $entry->body))) {
                $matches[] = $entry;
            }
        }

        return array_reverse($matches);
    }

    private function textMatches(SearchQuery $query, string $firstLine, string $body): bool
    {
        return $query->matches($body === '' ? $firstLine : $firstLine."\n".$body);
    }

    /**
     * @param  array<string, mixed>  $hit
     */
    private function entryFromHit(array $hit): LogEntry
    {
        $line = (int) ($hit['line_no'] ?? 0);

        return $this->parser->make(
            (int) $hit['byte_offset'],
            (int) ($hit['byte_end'] ?? $hit['byte_offset']),
            (string) ($hit['first_line'] ?? ''),
            (string) ($hit['body'] ?? ''),
            (bool) ($hit['truncated'] ?? false),
            (bool) ($hit['structured'] ?? false),
            $line > 0 ? $line : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function below(int $offset): array
    {
        return ['range' => ['byte_offset' => ['lt' => $offset]]];
    }
}
