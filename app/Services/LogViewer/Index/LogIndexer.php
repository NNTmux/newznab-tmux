<?php

declare(strict_types=1);

namespace App\Services\LogViewer\Index;

use App\Models\LogIndexFile;
use App\Services\LogViewer\LogEntry;
use App\Services\LogViewer\LogFile;
use App\Services\LogViewer\LogFileRepository;
use App\Services\LogViewer\LogReader;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tails every file in the log directory into the Manticore log index.
 *
 * Each run reconciles the directory with `log_index_files` (new, renamed, truncated, replaced and vanished
 * files), then reads each file forward from where the previous run stopped, newest files first, within a
 * byte budget. Progress is saved after every bulk write, so an interrupted run loses at most one batch.
 */
class LogIndexer
{
    public const string STATE_INDEXED = 'indexed';

    public const string STATE_PARTIAL = 'partial';

    public const string STATE_NONE = 'none';

    private const int HEAD_BYTES = 1024;

    private const int MAX_BATCH_BYTES = 8_388_608;

    /**
     * Minutes without writes after which a file's last entry counts as finished.
     */
    private const int TAIL_FLUSH_MINUTES = 2;

    public function __construct(
        private readonly LogIndex $index,
        private readonly LogFileRepository $logs,
        private readonly LogReader $reader,
    ) {}

    /**
     * @param  list<string>  $only  relative paths to index (empty = every file)
     * @return array{files: int, entries: int, bytes: int, purged: int}
     */
    public function run(array $only = []): array
    {
        $this->index->ensureTable();
        $stats = ['files' => 0, 'entries' => 0, 'bytes' => 0, 'purged' => 0];
        $tracked = $this->reconcile($this->logs->all(), $stats);
        $this->resetIfIndexWasWiped($tracked);

        $remaining = max(1, (int) config('nntmux.log_viewer.index.max_bytes_per_run', 268_435_456));
        $perFile = max(1, (int) config('nntmux.log_viewer.index.max_bytes_per_file', 67_108_864));

        foreach ($tracked as [$row, $file]) {
            if ($remaining <= 0) {
                break;
            }

            if ($only !== [] && ! in_array($file->path, $only, true)) {
                continue;
            }

            [$entries, $bytes] = $this->indexFile($row, $file, min($perFile, $remaining));
            $remaining -= $bytes;
            $stats['entries'] += $entries;
            $stats['bytes'] += $bytes;
            $stats['files'] += $entries > 0 ? 1 : 0;
        }

        return $stats;
    }

    /**
     * Drop the index and all progress; the next run recreates the table and indexes every file from the start.
     */
    public function reset(): void
    {
        $this->index->drop();
        LogIndexFile::query()->delete();
    }

    /**
     * Remove a file's documents ahead of truncating or deleting it, so stale hits disappear immediately.
     * When Manticore cannot be reached the next run notices the change and cleans up instead.
     */
    public function forget(LogFile $file): void
    {
        if (! $this->index->isEnabled()) {
            return;
        }

        $row = $this->rowsFor([$file])[$file->path] ?? null;

        if ($row === null) {
            return;
        }

        try {
            $this->index->purgeFile($row->id);
            $row->delete();
        } catch (Throwable $exception) {
            Log::warning('Log index: could not purge '.$file->path.': '.$exception->getMessage());
        }
    }

    /**
     * Tracking rows for files, keyed by file path (files never indexed are left out).
     *
     * @param  list<LogFile>  $files
     * @return array<string, LogIndexFile>
     */
    public function rowsFor(array $files): array
    {
        $identities = [];

        foreach ($files as $file) {
            $identity = $this->identity($file);

            if ($identity !== null) {
                $identities[$file->path] = $identity;
            }
        }

        if ($identities === []) {
            return [];
        }

        $rows = LogIndexFile::query()
            ->whereIn('inode', array_column($identities, 1))
            ->get()
            ->keyBy(static fn (LogIndexFile $row): string => $row->device.':'.$row->inode);

        $matched = [];

        foreach ($identities as $path => [$device, $inode]) {
            $row = $rows->get($device.':'.$inode);

            if ($row instanceof LogIndexFile) {
                $matched[$path] = $row;
            }
        }

        return $matched;
    }

    /**
     * How far each file is indexed, keyed by path.
     *
     * @param  list<LogFile>  $files
     * @return array<string, array{state: string, indexed_bytes: int, indexed_at: string|null}>
     */
    public function status(array $files): array
    {
        $rows = $this->rowsFor($files);
        $status = [];

        foreach ($files as $file) {
            $row = $rows[$file->path] ?? null;

            $status[$file->path] = [
                'state' => $this->state($row, $file),
                'indexed_bytes' => $row === null ? 0 : min($row->indexed_offset, $file->size),
                'indexed_at' => $row?->indexed_at?->toIso8601String(),
            ];
        }

        return $status;
    }

    /**
     * Whether searches over the file can be answered from the index: it has been indexed at least once
     * and its unindexed tail is within the lag tolerance.
     */
    public function isCaughtUp(LogIndexFile $row, LogFile $file): bool
    {
        return $this->state($row, $file) === self::STATE_INDEXED;
    }

    private function state(?LogIndexFile $row, LogFile $file): string
    {
        if ($row === null || $row->indexed_at === null) {
            return self::STATE_NONE;
        }

        $lagTolerance = max(0, (int) config('nntmux.log_viewer.index.lag_tolerance_bytes', 1_048_576));

        return $file->size - $row->indexed_offset <= $lagTolerance && $file->size >= $row->indexed_offset
            ? self::STATE_INDEXED
            : self::STATE_PARTIAL;
    }

    /**
     * @param  list<LogFile>  $files  newest first
     * @param  array{files: int, entries: int, bytes: int, purged: int}  $stats
     * @return list<array{LogIndexFile, LogFile}>
     */
    private function reconcile(array $files, array &$stats): array
    {
        $rows = LogIndexFile::query()->get()->keyBy(static fn (LogIndexFile $row): string => $row->device.':'.$row->inode);
        $tracked = [];
        $seen = [];

        foreach ($files as $file) {
            $identity = $this->identity($file);

            if ($identity === null) {
                continue;
            }

            [$device, $inode] = $identity;
            $row = $rows->get($device.':'.$inode);

            if ($row instanceof LogIndexFile && isset($seen[$row->id])) {
                continue;
            }

            if (! $row instanceof LogIndexFile) {
                $row = LogIndexFile::query()->create([
                    'path' => $file->path,
                    'device' => $device,
                    'inode' => $inode,
                    'structured' => $file->structured,
                    ...$this->head($file),
                ]);
            } elseif ($file->size < $row->indexed_offset || $row->structured !== $file->structured || ! $this->headMatches($row, $file)) {
                // Truncated, replaced under a reused inode, or now parsed differently: index it again.
                $this->index->purgeFile($row->id);
                $row->forceFill(['path' => $file->path, 'structured' => $file->structured, ...$this->head($file)]);
                $row->resetProgress();
                $stats['purged']++;
            } else {
                $row->path = $file->path;

                if ($row->head_length < self::HEAD_BYTES && $file->size > $row->head_length) {
                    $row->forceFill($this->head($file));
                }

                $row->save();
            }

            $seen[$row->id] = true;
            $tracked[] = [$row, $file];
        }

        foreach ($rows as $row) {
            if (! isset($seen[$row->id])) {
                $this->index->purgeFile($row->id);
                $row->delete();
                $stats['purged']++;
            }
        }

        return $tracked;
    }

    /**
     * The table was dropped or truncated behind our back (for example a Manticore rebuild): start over.
     *
     * @param  list<array{LogIndexFile, LogFile}>  $tracked
     */
    private function resetIfIndexWasWiped(array $tracked): void
    {
        $hasProgress = array_any($tracked, static fn (array $pair): bool => $pair[0]->indexed_offset > 0);

        if (! $hasProgress || $this->index->count() > 0) {
            return;
        }

        foreach ($tracked as [$row]) {
            $row->resetProgress();
        }
    }

    /**
     * @return array{int, int} entries written and bytes consumed
     */
    private function indexFile(LogIndexFile $row, LogFile $file, int $budget): array
    {
        $start = $row->indexed_offset;

        if ($start >= $file->size) {
            if ($row->indexed_at === null) {
                $this->saveProgress($row, $start, $row->indexed_line, $file);
            }

            return [0, 0];
        }

        $batchSize = max(1, (int) config('nntmux.log_viewer.index.batch_size', 1000));
        $entries = $this->reader->entriesForward($file, $start, $row->indexed_line, $budget, ! $file->isActive(self::TAIL_FLUSH_MINUTES));
        $batch = [];
        $batchBytes = 0;
        $written = 0;

        foreach ($entries as [$entry, $firstLine, $linesThrough]) {
            $batch[] = $this->document($row, $entry, $firstLine);
            $batchBytes += strlen($firstLine) + strlen($entry->body);

            if (count($batch) >= $batchSize || $batchBytes >= self::MAX_BATCH_BYTES) {
                $this->index->replace($batch);
                $written += count($batch);
                $this->saveProgress($row, $entry->end, $linesThrough, $file);
                $batch = [];
                $batchBytes = 0;
            }
        }

        $resume = $entries->getReturn();
        $this->index->replace($batch);
        $written += count($batch);
        $this->saveProgress($row, $resume['offset'], $resume['line'], $file);

        return [$written, max(0, $resume['offset'] - $start)];
    }

    private function saveProgress(LogIndexFile $row, int $offset, int $line, LogFile $file): void
    {
        $row->forceFill([
            'indexed_offset' => $offset,
            'indexed_line' => $line,
            'indexed_size' => $file->size,
            'indexed_at' => now(),
        ])->save();
    }

    /**
     * @return array<string, int|string>
     */
    private function document(LogIndexFile $row, LogEntry $entry, string $firstLine): array
    {
        return [
            'id' => LogIndex::documentId($row->id, $entry->offset),
            'file_id' => $row->id,
            'byte_offset' => $entry->offset,
            'byte_end' => $entry->end,
            'line_no' => $entry->lineNumber ?? 0,
            'logged_at' => $entry->loggedAt() ?? 0,
            'level' => $entry->level ?? '',
            'channel' => $entry->channel ?? '',
            'structured' => $entry->channel !== null ? 1 : 0,
            'truncated' => $entry->truncated ? 1 : 0,
            'first_line' => mb_scrub($firstLine, 'UTF-8'),
            'body' => mb_scrub($entry->body, 'UTF-8'),
        ];
    }

    /**
     * Device and inode of the file; files on filesystems without inodes fall back to a hash of the path.
     *
     * @return array{int, int}|null
     */
    private function identity(LogFile $file): ?array
    {
        clearstatcache(true, $file->absolutePath);
        $stat = @stat($file->absolutePath);

        if ($stat === false) {
            return null;
        }

        return (int) $stat['ino'] > 0 ? [(int) $stat['dev'], (int) $stat['ino']] : [0, crc32($file->path)];
    }

    /**
     * @return array{head_hash: string, head_length: int}
     */
    private function head(LogFile $file, ?int $length = null): array
    {
        $length ??= min(self::HEAD_BYTES, $file->size);
        $bytes = $length > 0 ? (string) @file_get_contents($file->absolutePath, false, null, 0, $length) : '';

        return ['head_hash' => sha1($bytes), 'head_length' => strlen($bytes)];
    }

    private function headMatches(LogIndexFile $row, LogFile $file): bool
    {
        return $file->size >= $row->head_length && $this->head($file, $row->head_length)['head_hash'] === $row->head_hash;
    }
}
