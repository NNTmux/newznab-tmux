<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\NNTP\NNTPService;
use App\Services\Releases\Verification\AuditReleaseDeletion;
use App\Services\Releases\Verification\CandidateRepository;
use App\Services\Releases\Verification\ManifestStore;
use App\Services\Releases\Verification\NntpArticleSource;
use App\Services\Releases\Verification\PayloadVerifier;
use App\Services\Releases\Verification\ScratchInventory;
use App\Services\Releases\Verification\VerificationResult;
use App\Services\Releases\Verification\VerifiedReleasePublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class VerifyReleaseCandidates extends Command
{
    protected $signature = 'releases:verify-candidates {--limit=1 : Maximum candidates in this run}';

    protected $description = 'Download and verify pending candidates before publishing their NZBs';

    public function handle(CandidateRepository $repository, ManifestStore $store, VerifiedReleasePublisher $publisher, ScratchInventory $scratchInventory): int
    {
        $root = (string) config('release_verification.storage_path');
        File::ensureDirectoryExists($root, 0700);
        $lock = fopen($root.'/worker.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->info('Verification worker is already running.');

            return self::SUCCESS;
        }
        try {
            if (! $this->scratchRootIsSafe($root.'/scratch')) {
                $this->error('Verification scratch root must be an accessible directory, not a symlink.');

                return self::FAILURE;
            }
            $this->cleanupTerminalScratch($root);
            $repository->expire();
            $verifier = new PayloadVerifier(new NntpArticleSource(app(NNTPService::class)), (array) config('release_verification'));
            for ($index = 0; $index < max(1, (int) $this->option('limit')); $index++) {
                $claim = $repository->claim();
                if ($claim === null) {
                    break;
                }
                $directory = $root.'/scratch/'.$claim->id.'-'.$claim->version;
                try {
                    $heartbeat = static fn () => $repository->heartbeat((int) $claim->id, $claim->lease_token);
                    $heartbeat();
                    $scratchInventory->register((int) $claim->id, (int) $claim->version);
                    foreach ($scratchInventory->olderVersions((int) $claim->id, (int) $claim->version) as $old) {
                        $oldDirectory = $root.'/scratch/'.$old->candidate_id.'-'.$old->version;
                        $heartbeat();
                        if (is_dir($oldDirectory) || is_link($oldDirectory)) {
                            $this->deleteStaleScratch($oldDirectory, $heartbeat);
                        } elseif (file_exists($oldDirectory)) {
                            throw new RuntimeException('Stale verification scratch is not a directory.');
                        }
                        $scratchInventory->forget((int) $old->id);
                    }
                    $heartbeat();
                    $manifest = $store->read($claim->manifest_path);
                    $started = microtime(true);
                    $result = $verifier->verify($manifest, $directory, $heartbeat);
                    if ($result->passed()) {
                        if ($publisher->publish($claim, $result) === null) {
                            $result = $this->filteredResult($claim) ?? throw new RuntimeException('Publication did not produce a verified current release.');
                        }
                        File::deleteDirectory($directory);
                    } else {
                        $repository->finish($claim, $result);
                    }
                    $bytes = $result->passed() ? (int) ($result->manifest['verified_bytes'] ?? 0) : 0;
                    $snapshot = json_encode(['verified_bytes_per_second' => $bytes / max(0.001, microtime(true) - $started),
                        'scratch_bytes' => null, 'scratch_bytes_status' => 'unknown'], JSON_THROW_ON_ERROR);
                    if (file_put_contents($root.'/status.json.tmp', $snapshot, LOCK_EX) !== false) {
                        rename($root.'/status.json.tmp', $root.'/status.json');
                    }
                    $this->line($claim->id.': '.$result->verdict.' — '.$result->reason);
                } catch (Throwable $error) {
                    $filtered = $this->filteredResult($claim);
                    if ($filtered !== null) {
                        $this->line($claim->id.': filtered — '.$filtered->reason);
                    } else {
                        $repository->finish($claim, new VerificationResult('unknown', $error->getMessage()));
                        $this->warn($claim->id.': retry pending — '.$error->getMessage());
                    }
                }
            }
            $counts = DB::table('release_verification_candidates')->selectRaw('status, COUNT(*) AS count')->groupBy('status')->get();
            foreach ($counts as $count) {
                $this->line($count->status.': '.$count->count);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return self::SUCCESS;
    }

    /** Recover interrupted terminal cleanup without delaying claims indefinitely. */
    private function cleanupTerminalScratch(string $root): void
    {
        $scratch = $root.'/scratch';
        if (! $this->scratchRootIsSafe($scratch)) {
            throw new RuntimeException('Verification scratch root is inaccessible or linked; inventory retained.');
        }
        $cursorPath = $root.'/indexed-scratch.cursor';
        $cursor = is_file($cursorPath) ? json_decode((string) file_get_contents($cursorPath), true) : null;
        $after = max(0, (int) ($cursor['after'] ?? 0));
        $ceiling = max(0, (int) ($cursor['ceiling'] ?? 0));
        if ($ceiling === 0) {
            $ceiling = (int) DB::table('release_verification_scratch')->max('id');
        }
        $entries = DB::table('release_verification_scratch')->where('id', '>', $after)->where('id', '<=', $ceiling)
            ->orderBy('id')->limit(100)->get();
        if ($entries->isEmpty() && $ceiling === 0) {
            return;
        }
        $remaining = 1000;
        $exhausted = false;
        foreach ($entries as $entry) {
            $directory = $scratch.'/'.app(ScratchInventory::class)->directoryName($entry);
            if ($entry->delete_guid !== null) {
                try {
                    app(AuditReleaseDeletion::class)->cleanup($entry);
                } catch (Throwable $error) {
                    $this->warn('Terminal audit cleanup will retry: '.$error->getMessage());
                    $after = (int) $entry->id;

                    continue;
                }
            }
            if (! file_exists($directory) && ! is_link($directory)) {
                app(ScratchInventory::class)->forget((int) $entry->id);
            } elseif (is_dir($directory) && ! is_link($directory)) {
                $terminal = $entry->audit_release_id !== null ? (bool) $entry->terminal
                    : DB::table('release_verification_candidates')->where('id', $entry->candidate_id)
                        ->where('version', '>=', $entry->version)
                        ->where(function ($query): void {
                            $query->whereIn('status', ['expired', 'filtered'])
                                ->orWhere(fn ($published) => $published->whereNotNull('release_id')->whereIn('status', ['complete', 'repaired']));
                        })
                        ->where(fn ($query) => $query->whereNull('lease_until')->orWhere('lease_until', '<', now()))
                        ->exists();
                if ($terminal) {
                    try {
                        $this->deleteStaleScratch($directory, static function () use (&$remaining, &$exhausted): void {
                            if ($remaining-- <= 0) {
                                $exhausted = true;
                                throw new RuntimeException('Terminal scratch cleanup will resume on the next run.');
                            }
                        });
                        app(ScratchInventory::class)->forget((int) $entry->id);
                    } catch (Throwable $error) {
                        if ($exhausted) {
                            $after = (int) $entry->id;
                            break;
                        }
                        $this->warn('Terminal scratch cleanup will retry: '.$error->getMessage());
                    }
                }
            }
            $after = (int) $entry->id;
        }
        if ((! $exhausted && $entries->count() < 100) || $after >= $ceiling) {
            $after = 0;
            $ceiling = 0;
        }
        $checkpoint = json_encode(['after' => $after, 'ceiling' => $ceiling], JSON_THROW_ON_ERROR);
        if (file_put_contents($cursorPath.'.tmp', $checkpoint, LOCK_EX) === false || ! rename($cursorPath.'.tmp', $cursorPath)) {
            throw new RuntimeException('Cannot checkpoint terminal scratch cleanup.');
        }
    }

    private function scratchRootIsSafe(string $directory): bool
    {
        return ! is_link($directory) && (! file_exists($directory)
            || (is_dir($directory) && is_readable($directory) && is_executable($directory)));
    }

    private function deleteStaleScratch(string $directory, callable $heartbeat): void
    {
        $heartbeat();
        if (is_link($directory)) {
            throw new RuntimeException('Stale verification scratch cannot be a symlink.');
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $heartbeat();
            $removed = $entry->isDir() && ! $entry->isLink()
                ? rmdir($entry->getPathname())
                : File::delete($entry->getPathname());
            if (! $removed) {
                throw new RuntimeException('Cannot remove stale verification scratch.');
            }
        }
        $heartbeat();
        if (! rmdir($directory)) {
            throw new RuntimeException('Cannot remove stale verification scratch.');
        }
    }

    private function filteredResult(object $claim): ?VerificationResult
    {
        $candidate = DB::table('release_verification_candidates')->where('id', $claim->id)->where('version', $claim->version)
            ->where('manifest_hash', $claim->manifest_hash)->where('status', 'filtered')->whereNull('release_id')->first();

        return $candidate === null ? null : new VerificationResult('filtered', (string) $candidate->reason);
    }
}
