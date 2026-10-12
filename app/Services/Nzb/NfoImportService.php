<?php

declare(strict_types=1);

namespace App\Services\Nzb;

use App\Models\Release;
use App\Services\NfoService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class NfoImportService
{
    private const MAX_NFO_SIZE = 65535;

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly NzbUploadManifestService $manifests,
        private readonly NfoService $nfoService,
    ) {}

    /**
     * @return array{status:'imported'|'skipped'|'failed',message:string}
     */
    public function importManifest(string $manifestPath, bool $delete = false, bool $deleteFailed = false): array
    {
        $nfoPath = null;

        try {
            $manifest = $this->manifests->reconcilePending($manifestPath);
            $state = $manifest['state'];

            if ($state === NzbUploadManifestService::STATE_NFO_IMPORTED) {
                return ['status' => 'skipped', 'message' => 'NFO was already imported'];
            }

            if (in_array($state, [
                NzbUploadManifestService::STATE_NZB_DUPLICATE,
                NzbUploadManifestService::STATE_NZB_FAILED,
                NzbUploadManifestService::STATE_NZB_REJECTED,
            ], true)) {
                if ($deleteFailed && is_array($manifest['nfo'] ?? null)) {
                    try {
                        $nfoPath = $this->manifests->resolvePayloadPath(
                            $manifestPath,
                            $manifest['nfo']['filename'],
                            'nfo'
                        );
                        $this->filesystem->delete($nfoPath);
                    } catch (Throwable) {
                        // The terminal NZB outcome remains authoritative even if its NFO is already gone.
                    }
                }

                return ['status' => 'skipped', 'message' => "NFO has no imported NZB release ({$state})"];
            }

            if (! in_array($state, [
                NzbUploadManifestService::STATE_NZB_IMPORTED,
                NzbUploadManifestService::STATE_NFO_FAILED,
            ], true)) {
                return ['status' => 'skipped', 'message' => "NFO is waiting for NZB import ({$state})"];
            }

            if (! is_array($manifest['nfo'] ?? null)) {
                return ['status' => 'skipped', 'message' => 'Upload has no paired NFO'];
            }

            $releaseId = $manifest['release_id'] ?? null;
            $releaseGuid = $manifest['release_guid'] ?? null;
            if (! is_int($releaseId) || $releaseId <= 0 || ! is_string($releaseGuid) || $releaseGuid === '') {
                throw new RuntimeException('Upload manifest has no valid release identity');
            }

            $releaseExists = Release::query()
                ->whereKey($releaseId)
                ->where('guid', $releaseGuid)
                ->exists();
            if (! $releaseExists) {
                throw new RuntimeException('The release recorded by the upload manifest no longer exists');
            }

            $nfoPath = $this->manifests->resolvePayloadPath(
                $manifestPath,
                $manifest['nfo']['filename'],
                'nfo'
            );
            $content = $this->filesystem->get($nfoPath);
            if ($content === '' || strlen($content) > self::MAX_NFO_SIZE) {
                throw new RuntimeException('NFO import must not be empty or exceed 65535 bytes');
            }

            if (! $this->nfoService->storeNfoContent($releaseId, $content)) {
                throw new RuntimeException('Failed to store NFO content for the release');
            }

            $this->manifests->recordNfoOutcome($manifestPath, true);
            if ($delete && ! $this->filesystem->delete($nfoPath)) {
                Log::channel('nzb_upload')->warning('Imported NFO could not be deleted', [
                    'manifest' => $manifestPath,
                    'filename' => basename($nfoPath),
                ]);
            }

            Log::channel('nzb_upload')->info('Paired NFO imported', [
                'upload_id' => $manifest['upload_id'],
                'release_id' => $releaseId,
                'release_guid' => $releaseGuid,
                'filename' => basename($nfoPath),
            ]);

            return ['status' => 'imported', 'message' => "Imported {$manifest['nfo']['filename']}"];
        } catch (Throwable $exception) {
            try {
                if (isset($manifest) && in_array($manifest['state'], [NzbUploadManifestService::STATE_NZB_IMPORTED, NzbUploadManifestService::STATE_NFO_FAILED], true)) {
                    $this->manifests->recordNfoOutcome($manifestPath, false, $exception->getMessage());
                }
            } catch (Throwable $manifestException) {
                Log::channel('nzb_upload')->error('Failed to record NFO import failure', [
                    'manifest' => $manifestPath,
                    'error' => $manifestException->getMessage(),
                ]);
            }

            if ($deleteFailed && $nfoPath !== null && $this->filesystem->isFile($nfoPath)) {
                $this->filesystem->delete($nfoPath);
            }

            Log::channel('nzb_upload')->warning('Paired NFO import failed', [
                'manifest' => $manifestPath,
                'error' => $exception->getMessage(),
            ]);

            return ['status' => 'failed', 'message' => $exception->getMessage()];
        }
    }
}
