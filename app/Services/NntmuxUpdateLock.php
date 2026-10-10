<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

#[Singleton]
class NntmuxUpdateLock
{
    /** @var resource|null */
    private $lock = null;

    /**
     * Hold the installation lock across all update steps, including nested Git updates.
     *
     * @param  Closure(): int  $update
     */
    public function run(Closure $update, bool $checkGit = true, int $gitLockTimeout = 10): int
    {
        if (is_resource($this->lock)) {
            if ($checkGit) {
                $this->ensureGitAvailable($gitLockTimeout);
            }

            return $update();
        }

        File::ensureDirectoryExists(storage_path('framework'));
        $lock = fopen(storage_path('framework/nntmux-update.lock'), 'c');
        if ($lock === false) {
            throw new RuntimeException('Unable to open the NNTmux update lock.');
        }

        try {
            if (! flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Another NNTmux update is already running. Retry after it finishes.');
            }

            $this->lock = $lock;

            if ($checkGit) {
                $this->ensureGitAvailable($gitLockTimeout);
            }

            return $update();
        } finally {
            if ($this->lock === $lock) {
                flock($lock, LOCK_UN);
            }
            $this->lock = null;
            fclose($lock);
        }
    }

    public function ensureGitAvailable(int $timeout): void
    {
        $result = Process::path(base_path())->run(['git', 'rev-parse', '--path-format=absolute', '--git-path', 'index.lock']);
        $path = trim($result->output());

        if (! $result->successful() || $path === '') {
            throw new RuntimeException('Unable to locate the Git index lock: '.$result->errorOutput());
        }

        $deadline = hrtime(true) + max(0, $timeout) * 1_000_000_000;
        while (true) {
            clearstatcache(true, $path);
            if (! File::exists($path)) {
                return;
            }

            if (hrtime(true) >= $deadline) {
                throw new RuntimeException('Git index is locked at '.$path.'. Wait for the active Git process to finish. If a process crashed, verify no Git process is using this repository before manually removing the stale lock and retrying.');
            }

            usleep(100_000);
        }
    }
}
