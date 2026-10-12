<?php

declare(strict_types=1);

namespace App\Services\Releases\Verification;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** Durable reservations count retained files and in-flight growth across all workers. */
final class ScratchBudget
{
    public function __construct(private readonly ?int $entryId = null) {}

    public function forEntry(int $entryId): self
    {
        return new self($entryId);
    }

    /** Bootstrap old physical scratch once, while every verifier and audit is excluded. */
    public function initialize(): bool
    {
        if (! Schema::hasTable('release_verification_scratch_budget')) {
            throw new RuntimeException('Shared scratch budget migration must run before verification workers.');
        }
        $state = DB::table('release_verification_scratch_budget')->where('id', 1)->first();
        if ($state === null) {
            throw new RuntimeException('Shared scratch budget state is unavailable.');
        }
        if ($state->initialized) {
            return true;
        }
        $exclusive = new VerificationWorkerLocks;
        if (! $exclusive->tryExclusive()) {
            return false;
        }
        try {
            $root = (string) config('release_verification.storage_path').'/scratch';
            $total = $this->directoryBytes($root);
            DB::transaction(function () use ($total, $root): void {
                $state = DB::table('release_verification_scratch_budget')->where('id', 1)->lockForUpdate()->sole();
                if ($state->initialized) {
                    return;
                }
                foreach (DB::table('release_verification_scratch')->orderBy('id')->cursor() as $entry) {
                    $bytes = $this->directoryBytes($root.'/'.app(ScratchInventory::class)->directoryName($entry));
                    DB::table('release_verification_scratch')->where('id', $entry->id)->update(['reserved_bytes' => $bytes]);
                }
                DB::table('release_verification_scratch_budget')->where('id', 1)->update(['initialized' => true, 'reserved_bytes' => $total]);
            });

            return true;
        } finally {
            $exclusive->close();
        }
    }

    public function reserveOwned(int $bytes): void
    {
        if ($this->entryId === null) {
            throw new RuntimeException('Scratch reservation must be bound to its registered inventory.');
        }
        $this->reserve($this->entryId, $bytes);
    }

    public function reserve(int $entryId, int $bytes): void
    {
        if ($bytes < 0) {
            throw new RuntimeException('Scratch reservation cannot be negative.');
        }
        DB::transaction(function () use ($entryId, $bytes): void {
            $state = DB::table('release_verification_scratch_budget')->where('id', 1)->lockForUpdate()->sole();
            if (! $state->initialized) {
                throw new RuntimeException('Shared scratch budget is not initialized.');
            }
            $entry = DB::table('release_verification_scratch')->where('id', $entryId)->lockForUpdate()->sole();
            $total = (int) $state->reserved_bytes - (int) $entry->reserved_bytes + $bytes;
            $root = (string) config('release_verification.storage_path');
            $free = disk_free_space($root);
            // Counting retained bytes again in the free-space margin is deliberately conservative after crashes.
            if ($total > (int) config('release_verification.scratch_bytes') || $free === false
                || $free < (int) config('release_verification.free_bytes') + $total) {
                throw new RuntimeException('Verification is waiting for shared scratch capacity.');
            }
            DB::table('release_verification_scratch')->where('id', $entryId)->update(['reserved_bytes' => $bytes]);
            DB::table('release_verification_scratch_budget')->where('id', 1)->update(['reserved_bytes' => $total]);
        }, 5);
    }

    /** A stale lease never authorizes reclaiming a reservation from a still-running process. */
    public function reconcile(int $entryId, VerificationWorkerLocks $locks, ?callable $heartbeat = null): void
    {
        $entry = DB::table('release_verification_scratch')->where('id', $entryId)->sole();
        if ($entry->candidate_id === null || ! $locks->protects((int) $entry->candidate_id)) {
            throw new RuntimeException('Scratch reconciliation requires the stable candidate lock.');
        }
        $bytes = $this->directoryBytes((string) config('release_verification.storage_path').'/scratch/'.app(ScratchInventory::class)->directoryName($entry), $heartbeat);
        DB::transaction(function () use ($entryId, $bytes): void {
            $state = DB::table('release_verification_scratch_budget')->where('id', 1)->lockForUpdate()->sole();
            $entry = DB::table('release_verification_scratch')->where('id', $entryId)->lockForUpdate()->sole();
            DB::table('release_verification_scratch_budget')->where('id', 1)->update([
                'reserved_bytes' => (int) $state->reserved_bytes - (int) $entry->reserved_bytes + $bytes,
            ]);
            DB::table('release_verification_scratch')->where('id', $entryId)->update(['reserved_bytes' => $bytes]);
        }, 5);
    }

    /** Called only after indexed cleanup has proved the exact directory absent. */
    public function forget(int $entryId): void
    {
        DB::transaction(function () use ($entryId): void {
            $state = DB::table('release_verification_scratch_budget')->where('id', 1)->lockForUpdate()->first();
            $entry = DB::table('release_verification_scratch')->where('id', $entryId)->lockForUpdate()->first();
            if ($entry === null) {
                return;
            }
            if ($state !== null) {
                DB::table('release_verification_scratch_budget')->where('id', 1)->update([
                    'reserved_bytes' => max(0, (int) $state->reserved_bytes - (int) $entry->reserved_bytes),
                ]);
            }
            DB::table('release_verification_scratch')->where('id', $entryId)->delete();
        }, 5);
    }

    private function directoryBytes(string $directory, ?callable $heartbeat = null): int
    {
        if (is_link($directory)) {
            throw new RuntimeException('Scratch budget cannot measure linked directories.');
        }
        if (! file_exists($directory)) {
            $parent = dirname($directory);
            if (is_dir($parent) && (! is_readable($parent) || ! is_executable($parent))) {
                throw new RuntimeException('Scratch budget cannot prove an inaccessible path absent.');
            }

            return 0;
        }
        if (! is_dir($directory) || ! is_readable($directory) || ! is_executable($directory)) {
            throw new RuntimeException('Scratch budget directory is inaccessible.');
        }
        $bytes = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $entry) {
            if ($heartbeat !== null) {
                $heartbeat();
            }
            if ($entry->isLink()) {
                throw new RuntimeException('Scratch budget cannot measure linked files.');
            }
            if ($entry->isFile()) {
                $bytes += $entry->getSize();
            }
        }

        return $bytes;
    }
}
