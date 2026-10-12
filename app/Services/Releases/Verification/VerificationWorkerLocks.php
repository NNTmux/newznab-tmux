<?php

declare(strict_types=1);

namespace App\Services\Releases\Verification;

use Illuminate\Support\Facades\File;
use RuntimeException;

/** Stable file locks outlive database leases and manifest generations. */
final class VerificationWorkerLocks
{
    /** @var resource|null */
    private $global = null;

    /** @var resource|null */
    private $slot = null;

    /** @var resource|null */
    private $candidate = null;

    /** @var resource|null */
    private $gc = null;

    private ?int $candidateId = null;

    private bool $exclusive = false;

    public function start(int $slot = 0): bool
    {
        $this->close();
        $this->global = $this->acquire('worker.lock', LOCK_SH);
        if ($this->global === null) {
            return false;
        }
        $maximum = max(1, min(8, (int) config('release_verification.candidate_workers', 2)));
        foreach ($slot > 0 ? [$slot] : range(1, $maximum) as $number) {
            if ($number > $maximum) {
                break;
            }
            $this->slot = $this->acquire('locks/worker-'.$number.'.lock', LOCK_EX);
            if ($this->slot !== null) {
                return true;
            }
        }
        $this->close();

        return false;
    }

    public function tryExclusive(): bool
    {
        $this->close();
        $this->global = $this->acquire('worker.lock', LOCK_EX);
        $this->exclusive = $this->global !== null;

        return $this->exclusive;
    }

    public function tryCandidate(int $id): bool
    {
        if ($this->candidateId === $id && $this->candidate !== null) {
            return true;
        }
        if ($this->candidate !== null || $this->global === null) {
            throw new RuntimeException('Candidate lock requires an active worker and no other candidate lock.');
        }
        $this->candidate = $this->acquire('locks/candidate-'.$id.'.lock', LOCK_EX);
        if ($this->candidate === null) {
            return false;
        }
        $this->candidateId = $id;

        return true;
    }

    public function protects(int $id): bool
    {
        return $this->exclusive || ($this->candidate !== null && $this->candidateId === $id);
    }

    public function tryGc(): bool
    {
        $this->gc = $this->acquire('locks/gc.lock', LOCK_EX);

        return $this->gc !== null;
    }

    public function releaseCandidate(): void
    {
        $this->release($this->candidate);
        $this->candidateId = null;
    }

    public function releaseGc(): void
    {
        $this->release($this->gc);
    }

    public function close(): void
    {
        $this->releaseCandidate();
        $this->releaseGc();
        $this->release($this->slot);
        $this->release($this->global);
        $this->exclusive = false;
    }

    public function __destruct()
    {
        $this->close();
    }

    /** @return resource|null */
    private function acquire(string $name, int $mode)
    {
        $root = (string) config('release_verification.storage_path');
        foreach ([$root, $root.'/locks'] as $directory) {
            if (is_link($directory)) {
                throw new RuntimeException('Verification lock directory cannot be a symlink.');
            }
            File::ensureDirectoryExists($directory, 0700);
        }
        $path = $root.'/'.$name;
        if (is_link($path)) {
            throw new RuntimeException('Verification lock cannot be a symlink.');
        }
        $handle = fopen($path, 'c');
        if ($handle === false) {
            throw new RuntimeException('Verification lock is unavailable.');
        }
        if (! flock($handle, $mode | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }

    /**
     * @param  resource|null  $handle
     *
     * @param-out null $handle
     */
    private function release(&$handle): void
    {
        if (is_resource($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
        $handle = null;
    }
}
