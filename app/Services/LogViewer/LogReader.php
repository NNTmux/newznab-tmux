<?php

declare(strict_types=1);

namespace App\Services\LogViewer;

use Generator;
use RuntimeException;
use SplDoublyLinkedList;

/**
 * Reads log entries without loading whole files: the latest entries are collected by reading
 * backwards from a byte cursor in fixed-size chunks, and single entries are resolved around a byte offset.
 */
class LogReader
{
    public const int FULL_ENTRY_BYTES = 1_048_576;

    private const int HEADER_SEARCH_BYTES = 262_144;

    private const int TRAILING_SCAN_FACTOR = 8;

    public function __construct(
        private readonly LogEntryParser $parser,
        private readonly int $chunkSize = 65_536,
        private readonly int $maxEntryBytes = 32_768,
        private readonly int $scanBudget = 33_554_432,
    ) {}

    /**
     * Newest-first entries that start before the `$before` byte cursor (or end of file).
     *
     * @param  list<string>  $levels  only return entries with these levels (empty = all)
     * @return array{entries: list<LogEntry>, before: int|null, scanned_bytes: int, budget_exhausted: bool}
     */
    public function latest(LogFile $file, ?int $before, int $limit, array $levels = []): array
    {
        $handle = $this->open($file);

        try {
            $size = $this->size($handle);
            $end = $before === null ? $size : min(max($before, 0), $size);

            $entries = [];
            $entryEnd = $end;
            $resume = null;
            $stoppedEarly = false;
            $lastOffset = $end;

            /** @var SplDoublyLinkedList<string> $pending continuation lines, bottom-most first */
            $pending = new SplDoublyLinkedList;
            $pendingBytes = 0;
            $pendingTop = null;
            $truncated = false;

            $lines = $this->linesBackward($handle, $end, $this->scanBudget);

            foreach ($lines as [$offset, $rawLine]) {
                $lastOffset = $offset;
                $line = rtrim($rawLine, "\r");

                if ($file->structured && ! $this->parser->isHeader($line)) {
                    [$line, $lineTruncated] = $this->cap($line, $this->maxEntryBytes);
                    $pending->push($line);
                    $pendingBytes += strlen($line) + 1;
                    $pendingTop = $offset;
                    $truncated = $truncated || $lineTruncated;

                    while ($pendingBytes > $this->maxEntryBytes && $pending->count() > 1) {
                        $pendingBytes -= strlen($pending->shift()) + 1;
                        $truncated = true;
                    }

                    continue;
                }

                if (! $file->structured && trim($line) === '') {
                    $entryEnd = $offset;

                    continue;
                }

                [$first, $firstTruncated] = $this->cap($line, $this->maxEntryBytes);
                $entry = $this->parser->make($offset, $entryEnd, $first, $this->joinPending($pending), $truncated || $firstTruncated, $file->structured);

                $pending = new SplDoublyLinkedList;
                $pendingBytes = 0;
                $pendingTop = null;
                $truncated = false;
                $entryEnd = $offset;

                if ($levels !== [] && ! in_array($entry->level, $levels, true)) {
                    continue;
                }

                $entries[] = $entry;

                if (count($entries) >= $limit) {
                    $stoppedEarly = true;
                    $resume = $offset > 0 ? $offset : null;

                    break;
                }
            }

            $exhausted = ! $stoppedEarly && $lines->getReturn() === true;

            if ($exhausted) {
                // Resume from the last complete entry boundary; if not even one entry fit in the budget, skip past what was scanned.
                $resume = $entryEnd < $end ? $entryEnd : $lastOffset;
                $resume = $resume > 0 ? $resume : null;
            } elseif (! $stoppedEarly && $pendingTop !== null && $pending->count() > 0) {
                // Continuation lines above the first header (file rotated mid-entry): show them as an orphan entry.
                $orphanLines = $this->pendingTopFirst($pending);
                $first = array_shift($orphanLines);
                $entry = $this->parser->make($pendingTop, $entryEnd, (string) $first, implode("\n", $orphanLines), $truncated, false);

                if ($levels === []) {
                    $entries[] = $entry;
                }
            }

            return [
                'entries' => $entries,
                'before' => $resume,
                'scanned_bytes' => $end - ($stoppedEarly || $exhausted ? $lastOffset : 0),
                'budget_exhausted' => $exhausted,
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * The complete entry containing the byte at `$offset`, or null when the offset is past the end of the file.
     */
    public function entryAt(LogFile $file, int $offset, int $maxBytes = self::FULL_ENTRY_BYTES): ?LogEntry
    {
        $handle = $this->open($file);

        try {
            return $this->entryFromHandle($handle, $file, $offset, $maxBytes);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     */
    public function entryFromHandle($handle, LogFile $file, int $offset, ?int $maxBytes = null, ?int $lineNumber = null): ?LogEntry
    {
        $size = $this->size($handle);

        if ($offset < 0 || $offset >= $size) {
            return null;
        }

        $start = $this->lineStart($handle, $offset);

        if ($file->structured) {
            $start = $this->headerStart($handle, $start);
        }

        return $this->readEntryForward($handle, $file, $start, $size, $maxBytes ?? $this->maxEntryBytes, $lineNumber);
    }

    /**
     * @return resource
     */
    public function open(LogFile $file)
    {
        $handle = @fopen($file->absolutePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Log file could not be opened.');
        }

        return $handle;
    }

    /**
     * Yields `[offset, line]` pairs from the line ending at `$end` back to the start of the file.
     * Returns true when it stopped because more than `$budget` bytes were read.
     *
     * @param  resource  $handle
     * @return Generator<int, array{int, string}, mixed, bool>
     */
    private function linesBackward($handle, int $end, int $budget): Generator
    {
        $position = $end;
        /** @var list<string> $carry chunks (in file order) of the line whose start is not yet known */
        $carry = [];
        $atRegionEnd = true;

        while ($position > 0) {
            if ($end - $position >= $budget) {
                return true;
            }

            $read = min($this->chunkSize, $position);
            $position -= $read;
            fseek($handle, $position);
            $chunk = (string) fread($handle, $read);

            if (! str_contains($chunk, "\n")) {
                array_unshift($carry, $chunk);

                continue;
            }

            $parts = explode("\n", $chunk.implode('', $carry));
            $head = array_shift($parts);
            $carry = [$head];

            $offsets = [];
            $cursor = $position + strlen($head) + 1;

            foreach ($parts as $index => $part) {
                $offsets[$index] = $cursor;
                $cursor += strlen($part) + 1;
            }

            for ($index = count($parts) - 1; $index >= 0; $index--) {
                if ($atRegionEnd) {
                    $atRegionEnd = false;

                    if ($parts[$index] === '') {
                        continue;
                    }
                }

                yield [$offsets[$index], $parts[$index]];
            }
        }

        $top = implode('', $carry);

        if ($top !== '' || ! $atRegionEnd) {
            yield [0, $top];
        }

        return false;
    }

    /**
     * @param  resource  $handle
     */
    private function lineStart($handle, int $offset): int
    {
        if ($offset === 0) {
            return 0;
        }

        fseek($handle, $offset - 1);

        if (fread($handle, 1) === "\n") {
            return $offset;
        }

        foreach ($this->linesBackward($handle, $offset, self::HEADER_SEARCH_BYTES) as [$lineOffset]) {
            return $lineOffset;
        }

        return $offset;
    }

    /**
     * Walk back from a line start to the Monolog header that owns it.
     *
     * @param  resource  $handle
     */
    private function headerStart($handle, int $lineStart): int
    {
        fseek($handle, $lineStart);
        [$line] = $this->readLine($handle, $this->maxEntryBytes);

        if ($line !== null && $this->parser->isHeader(rtrim($line, "\r"))) {
            return $lineStart;
        }

        $lines = $this->linesBackward($handle, $lineStart, self::HEADER_SEARCH_BYTES);

        foreach ($lines as [$offset, $candidate]) {
            if ($this->parser->isHeader(rtrim($candidate, "\r"))) {
                return $offset;
            }
        }

        // No header before the start of the file: the hit sits in an orphan block that begins at 0.
        return $lines->getReturn() === true ? $lineStart : 0;
    }

    /**
     * @param  resource  $handle
     */
    private function readEntryForward($handle, LogFile $file, int $start, int $size, int $maxBytes, ?int $lineNumber): LogEntry
    {
        fseek($handle, $start);
        [$first, $firstComplete] = $this->readLine($handle, $maxBytes);
        $first = rtrim((string) $first, "\r");
        $truncated = ! $firstComplete;
        $end = (int) ftell($handle);

        $bodyLines = [];
        $bodyBytes = 0;

        if ($file->structured) {
            $scanLimit = $start + ($maxBytes * self::TRAILING_SCAN_FACTOR);

            while (($position = (int) ftell($handle)) < $size) {
                if ($position >= $scanLimit) {
                    $truncated = true;
                    $end = $position;

                    break;
                }

                [$line, $complete] = $this->readLine($handle, $maxBytes);

                if ($line === null) {
                    break;
                }

                $line = rtrim($line, "\r");

                if ($this->parser->isHeader($line)) {
                    $end = $position;

                    break;
                }

                $end = (int) ftell($handle);

                if ($bodyBytes + strlen($line) + 1 > $maxBytes) {
                    $truncated = true;

                    continue;
                }

                $bodyLines[] = $line;
                $bodyBytes += strlen($line) + 1;
                $truncated = $truncated || ! $complete;
            }
        }

        return $this->parser->make($start, $end, $first, implode("\n", $bodyLines), $truncated, $file->structured, $lineNumber);
    }

    /**
     * Read one line (without its newline), keeping at most `$maxBytes` and discarding the rest.
     *
     * @param  resource  $handle
     * @return array{string|null, bool} the line (null at EOF) and whether it fit within the cap
     */
    private function readLine($handle, int $maxBytes): array
    {
        $line = fgets($handle, $maxBytes + 1);

        if ($line === false) {
            return [null, true];
        }

        if (str_ends_with($line, "\n") || feof($handle)) {
            return [rtrim($line, "\n"), true];
        }

        while (($rest = fgets($handle, 65_536)) !== false && ! str_ends_with($rest, "\n")) {
            // Skip the remainder of an oversized line.
        }

        return [$line, false];
    }

    /**
     * @return array{string, bool}
     */
    private function cap(string $line, int $maxBytes): array
    {
        return strlen($line) > $maxBytes ? [substr($line, 0, $maxBytes), true] : [$line, false];
    }

    /**
     * @param  SplDoublyLinkedList<string>  $pending
     */
    private function joinPending(SplDoublyLinkedList $pending): string
    {
        return $pending->count() === 0 ? '' : implode("\n", $this->pendingTopFirst($pending));
    }

    /**
     * @param  SplDoublyLinkedList<string>  $pending
     * @return list<string>
     */
    private function pendingTopFirst(SplDoublyLinkedList $pending): array
    {
        return array_reverse(iterator_to_array($pending, false));
    }

    /**
     * @param  resource  $handle
     */
    private function size($handle): int
    {
        $stat = fstat($handle);

        return $stat === false ? 0 : (int) $stat['size'];
    }
}
