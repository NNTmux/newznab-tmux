<?php

declare(strict_types=1);

namespace App\Services\Nzb;

use App\Enums\NzbImportStatus;
use App\Services\Releases\Verification\VerificationResetService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SplFileInfo;

final class NzbUploadManifestService
{
    public const FILENAME = 'nntmux-upload.json';

    public const STATE_STAGED = 'staged';

    public const STATE_NZB_IMPORTED = 'nzb_imported';

    public const STATE_NZB_DUPLICATE = 'nzb_duplicate';

    public const STATE_NZB_FAILED = 'nzb_failed';

    public const STATE_NZB_PENDING = 'nzb_pending';

    public const STATE_NZB_REJECTED = 'nzb_rejected';

    public const STATE_NFO_IMPORTED = 'nfo_imported';

    public const STATE_NFO_FAILED = 'nfo_failed';

    public function __construct(private readonly Filesystem $filesystem) {}

    /**
     * @return array<string, mixed>
     */
    public function create(string $directory, string $uploadId, string $nzbFilename, ?string $nfoFilename): array
    {
        $timestamp = now()->toIso8601String();
        $manifest = [
            'version' => 1,
            'upload_id' => $uploadId,
            'state' => self::STATE_STAGED,
            'nzb' => ['filename' => $nzbFilename],
            'nfo' => $nfoFilename === null ? null : ['filename' => $nfoFilename],
            'release_id' => null,
            'release_guid' => null,
            'candidate_identity' => null,
            'last_error' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];

        $this->write($directory.DIRECTORY_SEPARATOR.self::FILENAME, $manifest);

        return $manifest;
    }

    /**
     * @return array<string, mixed>
     *
     * @phpstan-impure
     */
    public function read(string $manifestPath): array
    {
        if (! $this->filesystem->isFile($manifestPath)) {
            throw new RuntimeException("Upload manifest does not exist: {$manifestPath}");
        }

        $contents = $this->filesystem->get($manifestPath);
        try {
            $manifest = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException("Invalid upload manifest JSON: {$manifestPath}", previous: $exception);
        }

        if (! is_array($manifest)
            || ($manifest['version'] ?? null) !== 1
            || ! is_string($manifest['upload_id'] ?? null)
            || ! is_string($manifest['state'] ?? null)
            || ! is_array($manifest['nzb'] ?? null)
            || ! is_string($manifest['nzb']['filename'] ?? null)) {
            throw new RuntimeException("Invalid upload manifest structure: {$manifestPath}");
        }

        $this->assertSafeFilename($manifest['nzb']['filename'], 'nzb');
        if (($manifest['nfo'] ?? null) !== null) {
            if (! is_array($manifest['nfo']) || ! is_string($manifest['nfo']['filename'] ?? null)) {
                throw new RuntimeException("Invalid NFO manifest entry: {$manifestPath}");
            }
            $this->assertSafeFilename($manifest['nfo']['filename'], 'nfo');
        }

        return $manifest;
    }

    /**
     * @return list<string>
     */
    public function findManifests(string $folder): array
    {
        if (! $this->filesystem->isDirectory($folder)) {
            return [];
        }

        return array_values(array_map(
            static fn (SplFileInfo $file): string => $file->getPathname(),
            array_filter(
                $this->filesystem->allFiles($folder),
                static fn (SplFileInfo $file): bool => $file->getFilename() === self::FILENAME
            )
        ));
    }

    public function recordNzbOutcome(
        string $nzbPath,
        NzbImportStatus $status,
        ?int $releaseId,
        ?string $releaseGuid,
        ?string $error = null,
        ?string $candidateIdentity = null,
    ): void {
        $manifestPath = dirname($nzbPath).DIRECTORY_SEPARATOR.self::FILENAME;
        if (! $this->filesystem->isFile($manifestPath)) {
            return;
        }

        $this->withManifestLock($manifestPath, function () use ($manifestPath, $nzbPath, $status, $releaseId, $releaseGuid, $error, $candidateIdentity): void {
            $manifest = $this->read($manifestPath);
            if (! hash_equals($manifest['nzb']['filename'], basename($nzbPath))) {
                throw new RuntimeException("NZB path does not match its upload manifest: {$nzbPath}");
            }

            if ($status === NzbImportStatus::Inserted) {
                if ($releaseId === null || $releaseGuid === null || $releaseGuid === '') {
                    throw new RuntimeException("Inserted NZB has no release identity: {$nzbPath}");
                }

                $manifest['state'] = self::STATE_NZB_IMPORTED;
                $manifest['release_id'] = $releaseId;
                $manifest['release_guid'] = $releaseGuid;
                $manifest['last_error'] = null;
            } elseif ($status === NzbImportStatus::Queued) {
                if ($candidateIdentity === null || preg_match('/\A[0-9a-f]{40}\z/', $candidateIdentity) !== 1) {
                    throw new RuntimeException('Queued upload has no valid verification candidate identity.');
                }
                if (($manifest['candidate_identity'] ?? null) === $candidateIdentity
                    && in_array($manifest['state'], [self::STATE_NZB_IMPORTED, self::STATE_NFO_IMPORTED, self::STATE_NFO_FAILED, self::STATE_NZB_REJECTED], true)) {
                    return;
                }
                $manifest['candidate_identity'] = $candidateIdentity;
                $manifest['state'] = self::STATE_NZB_PENDING;
                $manifest['release_id'] = null;
                $manifest['release_guid'] = null;
                $manifest['last_error'] = null;
            } elseif ($status === NzbImportStatus::Duplicate) {
                $manifest['state'] = self::STATE_NZB_DUPLICATE;
                $manifest['release_id'] = null;
                $manifest['release_guid'] = null;
                $manifest['last_error'] = $error ?? 'NZB import detected an existing duplicate release';
            } else {
                $manifest['state'] = self::STATE_NZB_FAILED;
                $manifest['release_id'] = null;
                $manifest['release_guid'] = null;
                $manifest['last_error'] = $error ?? "NZB import ended with status {$status->value}";
            }

            $manifest['updated_at'] = now()->toIso8601String();
            $this->write($manifestPath, $manifest);
        });
    }

    public function shouldImportNzb(string $nzbPath): bool
    {
        $manifestPath = dirname($nzbPath).DIRECTORY_SEPARATOR.self::FILENAME;
        if (! $this->filesystem->isFile($manifestPath)) {
            return true;
        }

        $manifest = $this->read($manifestPath);
        if (! hash_equals($manifest['nzb']['filename'], basename($nzbPath))) {
            throw new RuntimeException("NZB path does not match its upload manifest: {$nzbPath}");
        }

        $manifest = $this->reconcilePending($manifestPath);

        return in_array($manifest['state'], [self::STATE_STAGED, self::STATE_NZB_FAILED], true);
    }

    /**
     * Resolve a queued upload using its recorded article identity, even after the NZB was deleted.
     *
     * @return array<string, mixed>
     */
    public function reconcilePending(string $manifestPath): array
    {
        $manifest = $this->read($manifestPath);
        if ($manifest['state'] !== self::STATE_NZB_PENDING) {
            return $manifest;
        }

        return $this->withManifestLock($manifestPath, function () use ($manifestPath): array {
            $manifest = $this->read($manifestPath);
            if ($manifest['state'] !== self::STATE_NZB_PENDING) {
                return $manifest;
            }
            app(VerificationResetService::class)->withAdmission(function () use (&$manifest, $manifestPath): void {
                $resolved = DB::transaction(fn (): array => $this->resolveCandidate($manifest), 5);
                if (DB::transactionLevel() > 0) {
                    DB::afterCommit(fn () => $this->reconcilePending($manifestPath));

                    return;
                }
                if ($resolved !== $manifest) {
                    $this->write($manifestPath, $resolved);
                    $manifest = $resolved;
                }
            });

            return $manifest;
        });
    }

    /**
     * Resolve current admission state while holding the capacity ledger lock.
     *
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function resolveCandidate(array $manifest): array
    {
        $identity = $manifest['candidate_identity'] ?? null;
        if (! is_string($identity) || preg_match('/\A[0-9a-f]{40}\z/', $identity) !== 1) {
            throw new RuntimeException('Pending upload has no valid verification candidate identity.');
        }
        $state = DB::table('release_verification_state')->where('id', 1)->lockForUpdate()->first();
        if ($state === null) {
            throw new RuntimeException('Verification capacity state is unavailable; upload reconciliation deferred.');
        }
        $candidate = DB::table('release_verification_candidates as candidates')
            ->leftJoin('releases as published', 'published.id', '=', 'candidates.release_id')
            ->where('candidates.identity', $identity)->lockForUpdate()
            ->first(['candidates.status', 'candidates.release_id', 'candidates.verified_at', 'published.guid']);
        if ($candidate !== null && ! in_array($candidate->status, ['complete', 'repaired', 'expired', 'unrepairable'], true)) {
            return $manifest;
        }
        if ($candidate !== null && in_array($candidate->status, ['complete', 'repaired'], true) && $candidate->verified_at !== null
            && (int) $candidate->release_id > 0 && is_string($candidate->guid) && $candidate->guid !== '') {
            $manifest['state'] = self::STATE_NZB_IMPORTED;
            $manifest['release_id'] = (int) $candidate->release_id;
            $manifest['release_guid'] = $candidate->guid;
            $manifest['last_error'] = null;
        } else {
            $manifest['state'] = self::STATE_NZB_REJECTED;
            $manifest['release_id'] = null;
            $manifest['release_guid'] = null;
            $manifest['last_error'] = match ($candidate->status ?? null) {
                null => 'Download verification candidate no longer exists.',
                'expired' => 'Download verification expired before publication.',
                'unrepairable' => 'Download verification found the payload unrepairable.',
                default => 'Verified publication has no current release identity.',
            };
        }
        $manifest['updated_at'] = now()->toIso8601String();

        return $manifest;
    }

    public function recordNfoOutcome(string $manifestPath, bool $imported, ?string $error = null): void
    {
        $manifest = $this->read($manifestPath);
        $manifest['state'] = $imported ? self::STATE_NFO_IMPORTED : self::STATE_NFO_FAILED;
        $manifest['last_error'] = $imported ? null : ($error ?? 'NFO import failed');
        $manifest['updated_at'] = now()->toIso8601String();
        $this->write($manifestPath, $manifest);
    }

    /**
     * Resolve a payload filename without allowing a manifest to escape its upload directory.
     */
    public function resolvePayloadPath(string $manifestPath, string $filename, string $extension): string
    {
        $this->assertSafeFilename($filename, $extension);

        $directory = realpath(dirname($manifestPath));
        if ($directory === false) {
            throw new RuntimeException("Upload directory does not exist: {$manifestPath}");
        }

        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        $realPath = realpath($path);
        if ($realPath === false || ! is_file($realPath) || dirname($realPath) !== $directory) {
            throw new RuntimeException("Upload payload is missing or outside its manifest directory: {$filename}");
        }

        return $realPath;
    }

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @return TValue
     */
    private function withManifestLock(string $manifestPath, callable $callback): mixed
    {
        $lock = fopen(dirname($manifestPath).DIRECTORY_SEPARATOR.'.nntmux-upload.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Cannot lock upload manifest.');
        }
        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('Cannot lock upload manifest.');
            }

            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function write(string $manifestPath, array $manifest): void
    {
        try {
            $json = json_encode(
                $manifest,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ).PHP_EOL;
        } catch (\JsonException $exception) {
            throw new RuntimeException('Failed to encode upload manifest', previous: $exception);
        }

        $temporaryPath = $manifestPath.'.tmp.'.bin2hex(random_bytes(6));
        try {
            if ($this->filesystem->put($temporaryPath, $json) === false
                || ! $this->filesystem->move($temporaryPath, $manifestPath)) {
                throw new RuntimeException("Failed to write upload manifest: {$manifestPath}");
            }
        } finally {
            if ($this->filesystem->exists($temporaryPath)) {
                $this->filesystem->delete($temporaryPath);
            }
        }
    }

    private function assertSafeFilename(string $filename, string $extension): void
    {
        if ($filename === ''
            || $filename === '.'
            || $filename === '..'
            || basename($filename) !== $filename
            || str_contains($filename, "\0")
            || strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) !== $extension
            || trim((string) pathinfo($filename, PATHINFO_FILENAME)) === '') {
            throw new RuntimeException("Unsafe {$extension} filename in upload manifest");
        }
    }
}
