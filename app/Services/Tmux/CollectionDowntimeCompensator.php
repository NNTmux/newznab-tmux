<?php

declare(strict_types=1);

namespace App\Services\Tmux;

use App\Enums\CollectionFileCheckStatus;
use App\Services\Configuration\ConfigurationProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Gives incomplete collections back the time they lost while the indexer was down.
 *
 * Collections wait collectionDelayHours for missing parts before they are processed as
 * stale. When ingestion stops, that clock keeps running, so after a long outage
 * collections would be cut short. This used to be handled by resetting dateadded to now
 * for every collection older than the delay on every start, which on a busy indexer
 * rewrote millions of rows and, with frequent restarts, kept collections from ever
 * expiring. Instead the monitor records when it was last active, and on start only the
 * collections still waiting at that moment get their lost time back.
 */
final class CollectionDowntimeCompensator implements CollectionReconciliationBarrier
{
    /**
     * Restarts shorter than this (deploys, crash recovery) need no compensation.
     */
    public const int MIN_DOWNTIME_SECONDS = 300;

    private const int WINDOW_SECONDS = 60;

    private const int NOTE_INTERVAL_SECONDS = 30;

    private const int ROW_CHUNK = 5_000;

    /** Statuses whose reconciliation is gated by collectionDelayTime (10 is legacy). */
    private const array WAITING_FILECHECKS = [
        CollectionFileCheckStatus::Default->value,
        CollectionFileCheckStatus::CompleteCollection->value,
        CollectionFileCheckStatus::TempComplete->value,
        CollectionFileCheckStatus::ZeroPart->value,
        10,
    ];

    /**
     * Safety cap on UPDATE windows per start; each window holds at least one row.
     */
    private const int MAX_WINDOWS = 100_000;

    /**
     * Kept under storage/ rather than the cache: it must survive container restarts and
     * the Cache::flush() that the updater runs while tmux is stopped.
     */
    private readonly string $markerPath;

    public function __construct(?string $markerPath = null)
    {
        $this->markerPath = $markerPath ?? storage_path('app/tmux/last-ingestion-activity');
    }

    /**
     * Record that ingestion is running now. Called from the monitor loop.
     */
    public function recordActivity(): void
    {
        $this->withLockedMarker(function (): void {
            $this->compensateLocked(null, true);
        });
    }

    /**
     * Record activity from ingestion workers, at most every NOTE_INTERVAL_SECONDS (by the
     * marker's mtime) so many workers don't contend for the lock.
     */
    public function noteIngestion(): void
    {
        clearstatcache(true, $this->markerPath);
        $modified = @filemtime($this->markerPath);
        // mtime is filesystem (real) time, so compare against the real clock.
        if ($modified !== false && time() - $modified < self::NOTE_INTERVAL_SECONDS) {
            return;
        }
        $this->recordActivity();
    }

    public function lastActivity(): ?int
    {
        $value = is_file($this->markerPath) ? trim((string) @file_get_contents($this->markerPath)) : '';

        return ctype_digit($value) ? (int) $value : null;
    }

    /**
     * Push back the collections that were still waiting when the indexer stopped.
     *
     * The marker stays exclusively locked from reading it to the last update, so two
     * overlapping starts can't both claim the same outage: the second one waits, then
     * sees a short downtime.
     *
     * @return int The number of collections whose dateadded moved.
     */
    public function compensate(int $delayHours): int
    {
        return $this->withLockedMarker(fn (): int => $this->compensateLocked($delayHours, false));
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $work
     * @return TResult
     */
    public function withReconciliationBarrier(int $delayHours, callable $work): mixed
    {
        return $this->withLockedMarker(function () use ($delayHours, $work): mixed {
            $this->compensateLocked($delayHours, false);

            return $work();
        });
    }

    /**
     * Claim any stale interval before recording resumed activity, including direct
     * header workers that run before tmux starts. The caller holds the stable lock.
     */
    private function compensateLocked(?int $delayHours, bool $refreshRecent): int
    {
        $lastActive = $this->lastActivity();
        $now = Carbon::now('UTC')->getTimestamp();
        $stale = $lastActive !== null && $now - $lastActive >= self::MIN_DOWNTIME_SECONDS;
        if (! $stale && ! $refreshRecent && $lastActive !== null) {
            return 0;
        }
        $upperId = 0;
        if ($stale) {
            $delayHours ??= app(ConfigurationProvider::class)->ingestion()->collectionDelayHours;
            // Capture the ID ceiling before the recovery time. Active header workers
            // may insert rows while a widened DST scan walks subsequent chunks.
            $upperId = (int) DB::table('collections')->max('id');
            $now = Carbon::now('UTC')->getTimestamp();
        }

        // Claim before moving rows: an interrupted run can under-compensate rows,
        // but a retry cannot shift them twice. Failed publication leaves the old claim.
        $this->writeMarker($now);
        if (! $stale || $lastActive === null) {
            return 0;
        }

        return $this->shiftDateAdded($lastActive, $now, max(0, $delayHours ?? 0) * 3600, $upperId);
    }

    /**
     * Each collection that was waiting when ingestion stopped gets back the waiting time
     * it lost: the full downtime for those added before the last recorded activity (which
     * can't move them past the restart), and only the time since they were added for
     * those written in the moments after it.
     */
    private function shiftDateAdded(int $lastActive, int $now, int $delaySeconds, int $upperId): int
    {
        if ($delaySeconds <= 0) {
            return 0;
        }

        // The downtime comes from epochs, so a DST change can't distort or negate it.
        $downtime = max(0, $now - $lastActive);
        $lastActiveAt = $this->at($lastActive);
        $nowAt = $this->at($now);
        $waitingFrom = $lastActive - $delaySeconds;
        // dateadded holds wall-clock time, so adding seconds to it is only right while the
        // UTC offset stays put; across a DST change, shift each distinct value in PHP.
        $perValue = $this->crossesOffsetChange($waitingFrom, $lastActive, $now);
        $shifted = DB::getDriverName() === 'sqlite'
            ? DB::raw("datetime(dateadded, '+' || (CASE WHEN dateadded <= '{$lastActiveAt}' THEN {$downtime} ELSE MAX(0, MIN(strftime('%s', '{$nowAt}') - strftime('%s', dateadded), {$downtime})) END) || ' seconds')")
            : DB::raw("dateadded + INTERVAL (CASE WHEN dateadded <= '{$lastActiveAt}' THEN {$downtime} ELSE GREATEST(0, LEAST(TIMESTAMPDIFF(SECOND, dateadded, '{$nowAt}'), {$downtime})) END) SECOND");
        if ($perValue) {
            // Wall-clock values from a repeated fall-back hour don't say which occurrence
            // they were, so widen both bounds by the offset change to include them; each
            // row's real age is then checked after resolving its timestamp.
            $fold = $this->largestOffsetChange($waitingFrom, $lastActive, $now);

            return $this->shiftRowsById(
                $this->at($waitingFrom - $fold),
                $this->at(max($now, $lastActive) + $fold),
                $waitingFrom,
                $lastActive,
                $now,
                $downtime,
                $upperId,
            );
        }
        $moved = 0;

        // One dateadded window at a time, newest first, jumping straight to the next
        // occupied window: each UPDATE is a range on the dateadded index, and a moved row
        // lands in a later window that is already done (or at now), so nothing is shifted
        // twice, and the work follows the waiting rows rather than the id range or the
        // length of the outage. Start from the later wall-clock value: after a DST
        // fall-back, now can read earlier than the last activity.
        $windowEnd = max($nowAt, $lastActiveAt);
        $inclusive = true;
        for ($window = 0; $window < self::MAX_WINDOWS; $window++) {
            $newest = $this->newestWaiting($this->at($waitingFrom), $windowEnd, $inclusive, $upperId);
            if ($newest === null) {
                break;
            }
            $windowStartAt = $this->at(max($this->timestamp($newest) - self::WINDOW_SECONDS, $waitingFrom));
            $moved += DB::table('collections')
                ->whereIn('filecheck', self::WAITING_FILECHECKS)
                ->where('id', '<=', $upperId)
                ->where('dateadded', '>=', $windowStartAt)
                ->where('dateadded', '<=', $newest)
                ->update(['dateadded' => $shifted]);
            $windowEnd = $windowStartAt;
            $inclusive = false;
        }

        return $moved;
    }

    /**
     * Across a DST change, wall-clock values can move backwards even though real time
     * moves forward, so the newest-first windows could meet a moved row again. Walk the
     * waiting rows by id instead (a stable key), computing each new value with real
     * timezone arithmetic; every row is read and moved exactly once. This only runs for
     * a restart whose span crosses an offset change.
     */
    private function shiftRowsById(string $from, string $to, int $waitingFrom, int $lastActive, int $now, int $downtime, int $upperId): int
    {
        $moved = 0;
        $lastId = 0;
        do {
            $rows = DB::table('collections')
                ->whereIn('filecheck', self::WAITING_FILECHECKS)
                ->where('id', '<=', $upperId)
                ->where('dateadded', '>=', $from)
                ->where('dateadded', '<=', $to)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(self::ROW_CHUNK)
                ->get(['id', 'dateadded']);
            $byValue = [];
            foreach ($rows as $row) {
                $lastId = (int) $row->id;
                $added = $this->timestampNotAfter((string) $row->dateadded, $now);
                if ($added < $waitingFrom) {
                    continue; // Picked up only by the widened bound: it had already expired.
                }
                $shift = $added <= $lastActive ? $downtime : max(0, min($now - $added, $downtime));
                if ($shift > 0) {
                    $byValue[$this->at($added + $shift)][] = $lastId;
                }
            }
            foreach ($byValue as $value => $ids) {
                $moved += DB::table('collections')->whereIn('filecheck', self::WAITING_FILECHECKS)
                    ->where('id', '<=', $upperId)->whereIn('id', $ids)->update(['dateadded' => $value]);
            }
        } while ($rows->count() === self::ROW_CHUNK);

        return $moved;
    }

    /**
     * A wall-clock value from a repeated fall-back hour names two instants; take the
     * later one unless it is after $notAfter (the restart: no row was added after it).
     */
    private function timestampNotAfter(string $dateTime, int $notAfter): int
    {
        $first = $this->timestamp($dateTime);
        $value = substr($dateTime, 0, 19);
        $zone = new \DateTimeZone(date_default_timezone_get());
        $candidates = [$first];
        $transitions = $zone->getTransitions($first - 7 * 86400, $first + 7 * 86400) ?: [];
        for ($i = 1, $count = \count($transitions); $i < $count; $i++) {
            $fold = $transitions[$i - 1]['offset'] - $transitions[$i]['offset'];
            if ($fold <= 0) {
                continue;
            }
            foreach ([$first - $fold, $first + $fold] as $candidate) {
                if ((new \DateTimeImmutable('@'.$candidate))->setTimezone($zone)->format('Y-m-d H:i:s') === $value) {
                    $candidates[] = $candidate;
                }
            }
        }
        $beforeRestart = array_filter($candidates, static fn (int $candidate): bool => $candidate <= $notAfter);

        return $beforeRestart === [] ? $first : max($beforeRestart);
    }

    /**
     * The largest backward UTC-offset change (a fall-back, which repeats wall-clock
     * times), in seconds, in the span. Spring-forward changes repeat nothing.
     */
    private function largestOffsetChange(int ...$timestamps): int
    {
        $zone = new \DateTimeZone(date_default_timezone_get());
        $offsets = array_column($zone->getTransitions(min($timestamps), max($timestamps)) ?: [], 'offset');
        $largest = 0;
        for ($i = 1, $n = \count($offsets); $i < $n; $i++) {
            $largest = max($largest, $offsets[$i - 1] - $offsets[$i]);
        }

        return $largest;
    }

    /**
     * Whether the application timezone changes its UTC offset anywhere in the span.
     */
    private function crossesOffsetChange(int ...$timestamps): bool
    {
        $zone = new \DateTimeZone(date_default_timezone_get());
        $transitions = $zone->getTransitions(min($timestamps), max($timestamps));

        // The first entry describes the offset at the start; any more are changes.
        return \is_array($transitions) && \count($transitions) > 1;
    }

    /**
     * The newest dateadded at or after $from and before (or at) $before, found through
     * the dateadded index.
     */
    private function newestWaiting(string $from, string $before, bool $inclusive, int $upperId): ?string
    {
        $newest = DB::table('collections')
            ->whereIn('filecheck', self::WAITING_FILECHECKS)
            ->where('id', '<=', $upperId)
            ->where('dateadded', '>=', $from)
            ->where('dateadded', $inclusive ? '<=' : '<', $before)
            ->orderByDesc('dateadded')
            ->value('dateadded');

        return $newest === null ? null : (string) $newest;
    }

    /**
     * Datetime columns hold application-timezone wall-clock values, so convert both ways
     * in that timezone.
     */
    private function at(int $timestamp): string
    {
        return Carbon::createFromTimestamp($timestamp, date_default_timezone_get())->format('Y-m-d H:i:s');
    }

    private function timestamp(string $dateTime): int
    {
        return Carbon::createFromFormat('Y-m-d H:i:s', substr($dateTime, 0, 19), date_default_timezone_get())->getTimestamp();
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $work
     * @return TResult
     */
    private function withLockedMarker(callable $work): mixed
    {
        $directory = \dirname($this->markerPath);
        $parents = [\dirname($directory)];
        $missing = $directory;
        while (! is_dir($missing)) {
            $parent = \dirname($missing);
            if ($parent === $missing) {
                throw new \RuntimeException('Unable to create marker directory.');
            }
            $parents[] = $parent;
            $missing = $parent;
        }
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Unable to create marker directory.');
        }
        $marker = fopen($this->markerPath.'.lock', 'c+');
        if ($marker === false) {
            throw new \RuntimeException("Unable to open {$this->markerPath}.");
        }

        try {
            if (! flock($marker, LOCK_EX)) {
                throw new \RuntimeException("Unable to lock {$this->markerPath}.");
            }

            // Always repeat the parent sync: a prior failed creator, or a concurrent
            // creator, can leave an existing directory with an unsynced parent entry.
            foreach (array_unique($parents) as $parent) {
                $parentStream = fopen($parent, 'r');
                if ($parentStream === false) {
                    throw new \RuntimeException('Unable to sync marker parent directory.');
                }
                try {
                    if (! $this->syncDirectory($parentStream)) {
                        throw new \RuntimeException('Unable to sync marker parent directory.');
                    }
                } finally {
                    fclose($parentStream);
                }
            }
            $this->recoverPublication();

            return $work();
        } finally {
            flock($marker, LOCK_UN);
            fclose($marker);
        }
    }

    /**
     * Publish only a complete, flushed timestamp. The separate lock file is never
     * replaced, so waiters still serialize against the same inode after publication.
     */
    private function writeMarker(int $timestamp): void
    {
        $directoryStream = fopen(\dirname($this->markerPath), 'r');
        if ($directoryStream === false) {
            throw new \RuntimeException('Unable to sync marker directory.');
        }
        $temporary = null;
        $recoveryTemporary = null;
        $published = false;
        $recoveryPath = $this->markerPath.'.recovery';
        try {
            $previous = is_file($this->markerPath) ? file_get_contents($this->markerPath) : null;
            if ($previous === false) {
                throw new \RuntimeException('Unable to read marker before replacement.');
            }
            $temporary = $this->prepareMarker((string) $timestamp);
            $record = json_encode(['previous' => $previous === null ? null : base64_encode($previous)], JSON_THROW_ON_ERROR)."\n";
            $recoveryTemporary = $this->prepareMarker($record);
            if (! rename($recoveryTemporary, $recoveryPath)) {
                throw new \RuntimeException('Unable to replace marker recovery record.');
            }
            // Make recovery evidence durable while the original marker is still intact.
            if (! $this->syncDirectory($directoryStream)) {
                throw new \RuntimeException('Unable to sync marker recovery directory.');
            }
            if (! rename($temporary, $this->markerPath)) {
                throw new \RuntimeException('Unable to replace marker.');
            }
            $published = true;
            if (! $this->syncDirectory($directoryStream)) {
                throw new \RuntimeException('Unable to sync marker directory.');
            }
            $checkpoint = fopen($recoveryPath, 'ab');
            if ($checkpoint === false) {
                throw new \RuntimeException('Unable to open marker recovery checkpoint.');
            }
            try {
                $committed = "COMMITTED\n";
                if (fwrite($checkpoint, $committed) !== \strlen($committed) || ! fflush($checkpoint) || ! fsync($checkpoint)) {
                    throw new \RuntimeException('Unable to persist marker recovery checkpoint.');
                }
            } finally {
                fclose($checkpoint);
            }
            // The committed record is durable before rows move. If this unlink is lost
            // on a crash, recovery sees COMMITTED and never restores the old timestamp.
            @unlink($recoveryPath);
        } catch (\Throwable $exception) {
            if ($published) {
                $this->recoverPublication(forceRollback: true);
            }
            throw $exception;
        } finally {
            fclose($directoryStream);
            foreach ([$temporary, $recoveryTemporary] as $path) {
                if ($path !== null && is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    /**
     * Under the stable lock, recover a publication that never reached its durable
     * checkpoint. Persistent storage errors retain the previously synced evidence.
     */
    private function recoverPublication(bool $forceRollback = false): void
    {
        $recoveryPath = $this->markerPath.'.recovery';
        $forcedRecord = file_get_contents($this->markerPath.'.lock');
        if ($forcedRecord === false) {
            throw new \RuntimeException('Unrecoverable marker publication: pending lock record is unreadable.');
        }
        if ($forcedRecord === '' && ! is_file($recoveryPath)) {
            return;
        }
        $record = $forcedRecord !== '' ? $forcedRecord : file_get_contents($recoveryPath);
        if ($record === false) {
            throw new \RuntimeException('Unrecoverable marker publication: recovery record is unreadable.');
        }
        if (! $forceRollback && $forcedRecord === '' && str_contains($record, "\nCOMMITTED\n")) {
            @unlink($recoveryPath);

            return;
        }
        $pendingRecord = explode("\n", $record, 2)[0]."\n";
        if ($forceRollback && $forcedRecord === '') {
            // A failed checkpoint can leave a visible COMMITTED suffix. Persist an
            // overriding pending record in the already durable, stable lock inode
            // before attempting any rollback rename that might itself be lost.
            $this->writeLockRecovery($pendingRecord);
        }
        $metadata = json_decode(trim($pendingRecord), true, 512, JSON_THROW_ON_ERROR);
        if (! \is_array($metadata) || ! array_key_exists('previous', $metadata) || ($metadata['previous'] !== null && ! \is_string($metadata['previous']))) {
            throw new \RuntimeException('Unrecoverable marker publication: invalid recovery record.');
        }
        $previous = $metadata['previous'] === null ? null : base64_decode($metadata['previous'], true);
        if ($previous === false) {
            throw new \RuntimeException('Unrecoverable marker publication: invalid recovery timestamp.');
        }
        $directoryStream = fopen(\dirname($this->markerPath), 'r');
        if ($directoryStream === false) {
            throw new \RuntimeException('Unrecoverable marker publication: directory is unavailable.');
        }
        $temporary = null;
        try {
            if ($previous !== null) {
                $temporary = $this->prepareMarker($previous);
                if (! rename($temporary, $this->markerPath)) {
                    throw new \RuntimeException('Unrecoverable marker publication: timestamp cannot be restored.');
                }
            } elseif (is_file($this->markerPath) && ! unlink($this->markerPath)) {
                throw new \RuntimeException('Unrecoverable marker publication: unknown timestamp cannot be removed.');
            }
            if (! $this->syncDirectory($directoryStream)) {
                throw new \RuntimeException('Unrecoverable marker publication: rollback cannot be synced; recovery evidence retained.');
            }
            $this->writeLockRecovery('');
            @unlink($recoveryPath);
        } finally {
            fclose($directoryStream);
            if ($temporary !== null && is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /**
     * Persist pending rollback evidence without changing a directory entry.
     * Called only while the same stable lock inode is exclusively held.
     */
    private function writeLockRecovery(string $record): void
    {
        $stream = fopen($this->markerPath.'.lock', 'c+');
        if ($stream === false) {
            throw new \RuntimeException('Unrecoverable marker publication: pending lock record cannot be opened.');
        }
        try {
            if (! rewind($stream) || fwrite($stream, $record) !== \strlen($record)
                || ! ftruncate($stream, \strlen($record)) || ! fflush($stream) || ! fsync($stream)) {
                throw new \RuntimeException('Unrecoverable marker publication: pending lock record cannot be synced.');
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  resource  $stream
     *
     * @phpstan-impure
     */
    private function syncDirectory($stream): bool
    {
        return fsync($stream);
    }

    /**
     * Prepare a complete file before either publication or rollback changes the marker.
     */
    private function prepareMarker(string $value): string
    {
        $directory = \dirname($this->markerPath);
        $temporary = tempnam($directory, 'activity-');
        if ($temporary === false) {
            throw new \RuntimeException("Unable to write {$this->markerPath}.");
        }
        try {
            if (\dirname($temporary) !== realpath($directory)) {
                throw new \RuntimeException("Unable to write {$this->markerPath} in its directory.");
            }
            $stream = fopen($temporary, 'wb');
            if ($stream === false) {
                throw new \RuntimeException("Unable to write {$this->markerPath}.");
            }
            try {
                if (fwrite($stream, $value) !== \strlen($value) || ! fflush($stream) || ! fsync($stream)) {
                    throw new \RuntimeException("Unable to write {$this->markerPath}.");
                }
            } finally {
                fclose($stream);
            }

            return $temporary;
        } catch (\Throwable $exception) {
            unlink($temporary);
            throw $exception;
        }
    }
}
