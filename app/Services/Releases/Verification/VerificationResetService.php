<?php

declare(strict_types=1);

namespace App\Services\Releases\Verification;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class VerificationResetService
{
    /** Hold both locks across legacy TRUNCATE statements, which implicitly commit on MySQL. */
    public function run(callable $reset): void
    {
        if (DB::transactionLevel() !== 0) {
            throw new RuntimeException('Database reset cannot run inside an open transaction.');
        }
        $admissionLock = $this->lock('reset.lock', LOCK_EX);
        try {
            $workerLock = $this->lock('worker.lock', LOCK_EX);
            try {
                $this->clearVerification();
                $reset();
            } finally {
                $this->unlock($workerLock);
            }
        } finally {
            $this->unlock($admissionLock);
        }
    }

    /** Retain admission protection until the caller's outer transaction finishes. */
    public function withAdmission(callable $admit): void
    {
        $lock = $this->lock('reset.lock', LOCK_SH);
        try {
            $admit();
        } finally {
            if (DB::transactionLevel() > 0) {
                $release = fn () => $this->unlock($lock);
                DB::afterCommit($release);
                DB::afterRollBack($release);
            } else {
                $this->unlock($lock);
            }
        }
    }

    private function clearVerification(): void
    {
        DB::transaction(function (): void {
            if (Schema::hasTable('release_verification_state')) {
                DB::table('release_verification_state')->where('id', 1)->lockForUpdate()->first();
            }
            foreach (['release_verification_scratch', 'release_verification_candidates', 'release_verification_audit_retries'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }
            if (Schema::hasTable('release_verification_scratch_budget')) {
                DB::table('release_verification_scratch_budget')->delete();
                DB::table('release_verification_scratch_budget')->insert(['id' => 1]);
            }
            if (Schema::hasTable('release_verification_state')) {
                DB::table('release_verification_state')->delete();
                $state = ['id' => 1, 'pending_bytes' => 0, 'audit_cursor' => 0];
                if (Schema::hasColumn('release_verification_state', 'audit_retry_turn')) {
                    $state['audit_retry_turn'] = false;
                }
                DB::table('release_verification_state')->insert($state);
            }
        });

        $root = (string) config('release_verification.storage_path');
        foreach (new \FilesystemIterator($root, \FilesystemIterator::SKIP_DOTS) as $entry) {
            if (in_array($entry->getFilename(), ['worker.lock', 'reset.lock', 'locks', '.gitignore'], true)) {
                continue;
            }
            $deleted = $entry->isDir() && ! $entry->isLink()
                ? File::deleteDirectory($entry->getPathname())
                : File::delete($entry->getPathname());
            if (! $deleted) {
                throw new RuntimeException('Cannot remove verification reset artifacts.');
            }
        }
    }

    /** @return resource */
    private function lock(string $name, int $mode)
    {
        $root = (string) config('release_verification.storage_path');
        File::ensureDirectoryExists($root, 0700);
        $lock = fopen($root.'/'.$name, 'c');
        if ($lock === false) {
            throw new RuntimeException('Verification reset lock is unavailable.');
        }
        if (! flock($lock, $mode | LOCK_NB)) {
            fclose($lock);
            throw new RuntimeException('Verification worker, admission or reset is active; retry reset later.');
        }

        return $lock;
    }

    /** @param resource $lock */
    private function unlock($lock): void
    {
        if (is_resource($lock)) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
