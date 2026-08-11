<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing;

use App\Models\ReleaseFile;
use App\Services\AdditionalProcessing\DTO\ArchiveCandidate;
use App\Services\AdditionalProcessing\Enums\DownloadKind;
use App\Services\AdditionalProcessing\State\ReleaseProcessingContext;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\File;

/**
 * Shared fallback logic for extracting known files back out of downloaded archives.
 */
class ReleaseFilesArchiveFallback
{
    public function __construct(
        private readonly ArchiveExtractionService $archiveService,
        private readonly MediaExtractionService $mediaService,
        private readonly UsenetDownloadService $downloadService,
        private readonly ReleaseFileManager $releaseManager,
        private readonly ConsoleOutputService $output
    ) {}

    /**
     * Try JPG/PNG/WebP files listed inside an already-downloaded archive summary.
     *
     * @param  array<int, array<string, mixed>>  $files
     */
    public function processJpgFromArchiveFileList(
        string $compressedData,
        array $files,
        ReleaseProcessingContext $context
    ): void {
        $imageFiles = array_values(array_filter(
            $files,
            static fn (array $file): bool => preg_match('/\.(jpe?g|png|webp)$/i', (string) ($file['name'] ?? '')) === 1
        ));

        if ($imageFiles === []) {
            return;
        }

        usort($imageFiles, static fn (array $a, array $b): int => ((int) ($b['size'] ?? 0)) <=> ((int) ($a['size'] ?? 0)));

        $this->processExtractedCandidatesFromData(
            $compressedData,
            array_slice($imageFiles, 0, 3),
            $context,
            'extracted_',
            function (string $tempPath, ReleaseProcessingContext $context): bool {
                if (! $this->mediaService->isValidImage($tempPath)) {
                    return false;
                }

                if (! $this->mediaService->getJPGSample($tempPath, $context->release->guid)) {
                    return false;
                }

                $context->markFound('jpgSample');
                $this->output->echoJpgSaved();

                return true;
            }
        );
    }

    /**
     * Re-download archives and extract stored JPG/PNG/WebP candidates.
     */
    public function processJpgFromReleaseFiles(ReleaseProcessingContext $context): void
    {
        $imageFiles = ReleaseFile::query()
            ->where('releases_id', $context->release->id)
            ->where(function ($query) {
                $query->where('name', 'like', '%.jpg')
                    ->orWhere('name', 'like', '%.jpeg')
                    ->orWhere('name', 'like', '%.png')
                    ->orWhere('name', 'like', '%.webp');
            })
            ->orderByDesc('size')
            ->limit(3)
            ->get();

        if ($imageFiles->isEmpty()) {
            return;
        }

        $this->processStoredReleaseFiles(
            $context,
            $imageFiles,
            'release_file_',
            static fn (ReleaseProcessingContext $context): bool => $context->foundJPGSample,
            function (string $tempPath, ReleaseProcessingContext $context): bool {
                if (! $this->mediaService->isValidImage($tempPath)) {
                    return false;
                }

                if (! $this->mediaService->getJPGSample($tempPath, $context->release->guid)) {
                    return false;
                }

                $context->markFound('jpgSample');
                $this->output->echoJpgSaved();

                return true;
            }
        );
    }

    /**
     * Re-download archives and extract stored NFO-like candidates.
     */
    public function processNfoFromReleaseFiles(ReleaseProcessingContext $context): void
    {
        $nfoFiles = ReleaseFile::query()
            ->where('releases_id', $context->release->id)
            ->nfoFiles()
            ->limit(3)
            ->get();

        if ($nfoFiles->isEmpty()) {
            return;
        }

        $nntp = $this->downloadService->getNNTP();

        $this->processStoredReleaseFiles(
            $context,
            $nfoFiles,
            'release_file_',
            static fn (ReleaseProcessingContext $context): bool => ! $context->releaseHasNoNFO,
            function (string $tempPath, ReleaseProcessingContext $context) use ($nntp): bool {
                if (! $this->releaseManager->processNfoFile($tempPath, $context, $nntp)) {
                    return false;
                }

                $this->output->echoNfoFound();

                return true;
            }
        );
    }

    /**
     * Extract NFO candidates from archives already downloaded for this release.
     */
    public function processNfoFromDownloadedArchives(ReleaseProcessingContext $context): void
    {
        if ($context->downloadedArchives === [] || ! $context->releaseHasNoNFO) {
            return;
        }

        $nntp = $this->downloadService->getNNTP();

        foreach ($context->downloadedArchives as $archive) {
            $nfoFiles = array_values(array_filter(
                $this->archiveService->sortFilesWithNfoPriority($archive->files),
                fn (array $file): bool => $this->archiveService->isNfoFile((string) ($file['name'] ?? '')),
            ));

            $this->processExtractedCandidatesFromData(
                $archive->data,
                array_slice($nfoFiles, 0, 3),
                $context,
                'downloaded_nfo_',
                function (string $tempPath, ReleaseProcessingContext $context) use ($nntp): bool {
                    if (! $this->releaseManager->processNfoFile($tempPath, $context, $nntp)) {
                        return false;
                    }

                    $this->output->echoNfoFound();

                    return true;
                },
            );

            if (! $context->releaseHasNoNFO) {
                return;
            }
        }
    }

    /**
     * @param  EloquentCollection<int, ReleaseFile>  $candidates
     * @param  callable(ReleaseProcessingContext): bool  $shouldStop
     * @param  callable(string, ReleaseProcessingContext): bool  $handler
     */
    private function processStoredReleaseFiles(
        ReleaseProcessingContext $context,
        EloquentCollection $candidates,
        string $tempPrefix,
        callable $shouldStop,
        callable $handler
    ): void {
        $archiveCandidates = $this->archiveCandidates($context);
        if ($candidates->isEmpty() || $archiveCandidates === []) {
            return;
        }

        foreach ($archiveCandidates as $archiveCandidate) {
            if ($shouldStop($context)) {
                break;
            }

            $result = $this->downloadService->download(
                DownloadKind::Compressed,
                $archiveCandidate->messageIds,
                $context->releaseGroupName,
                $context->release->id,
                $archiveCandidate->title,
            );

            if ($result['groupUnavailable']) {
                $context->groupUnavailable = true;
                $this->output->echoGroupUnavailable();

                return;
            }

            if (! $result['success'] || empty($result['data'])) {
                continue;
            }

            $filenames = $candidates
                ->pluck('name')
                ->filter(static fn (mixed $filename): bool => is_string($filename) && $filename !== '')
                ->values()
                ->all();
            $extractedFiles = $this->extractCandidateFiles(
                $result['data'],
                $filenames,
                $context->tmpPath,
            );

            foreach ($candidates as $candidate) {
                $fileData = $extractedFiles[$candidate->name] ?? null;

                if ($fileData === null || $fileData === '') {
                    continue;
                }

                if ($this->withExtractedTempFile($candidate->name, $fileData, $context, $tempPrefix, $handler)) {
                    return;
                }
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @param  callable(string, ReleaseProcessingContext): bool  $handler
     */
    private function processExtractedCandidatesFromData(
        string $compressedData,
        array $candidates,
        ReleaseProcessingContext $context,
        string $tempPrefix,
        callable $handler
    ): void {
        $filenames = array_values(array_filter(array_map(
            static fn (array $candidate): string => (string) ($candidate['name'] ?? ''),
            $candidates,
        )));
        $extractedFiles = $this->extractCandidateFiles(
            $compressedData,
            $filenames,
            $context->tmpPath,
        );

        foreach ($candidates as $candidate) {
            $filename = (string) ($candidate['name'] ?? '');
            if ($filename === '') {
                continue;
            }

            $fileData = $extractedFiles[$filename] ?? null;
            if ($fileData === null || $fileData === '') {
                continue;
            }

            if ($this->withExtractedTempFile($filename, $fileData, $context, $tempPrefix, $handler)) {
                return;
            }
        }
    }

    /**
     * @return list<ArchiveCandidate>
     */
    private function archiveCandidates(ReleaseProcessingContext $context): array
    {
        if ($context->workPlan === null) {
            return [];
        }

        return array_values(array_filter(
            $context->workPlan->archiveCandidates,
            static fn (ArchiveCandidate $candidate): bool => $candidate->likelyFirstVolume,
        ));
    }

    /**
     * @param  list<string>  $filenames
     * @return array<string, string>
     */
    private function extractCandidateFiles(string $archiveData, array $filenames, string $tmpPath): array
    {
        return $this->archiveService->extractSpecificFiles($archiveData, $filenames, $tmpPath);
    }

    /**
     * @param  callable(string, ReleaseProcessingContext): bool  $handler
     */
    private function withExtractedTempFile(
        string $filename,
        string $fileData,
        ReleaseProcessingContext $context,
        string $tempPrefix,
        callable $handler
    ): bool {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $suffix = $extension === '' ? '' : '.'.$extension;
        $tempPath = $context->tmpPath.$tempPrefix.uniqid('', true).$suffix;
        File::put($tempPath, $fileData);

        try {
            return $handler($tempPath, $context);
        } finally {
            File::delete($tempPath);
        }
    }
}
