<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing;

use App\Facades\Search;
use App\Models\Category;
use App\Models\MediaInfo as MediaInfoModel;
use App\Models\ParHash;
use App\Models\Predb;
use App\Models\Release;
use App\Models\ReleaseFile;
use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\State\PersistenceMetricsCollector;
use App\Services\AdditionalProcessing\State\ReleaseProcessingContext;
use App\Services\NameFixing\FileNameCleaner;
use App\Services\NameFixing\NameFixingService;
use App\Services\NameFixing\ReleaseUpdateService;
use App\Services\NfoService;
use App\Services\NNTP\NNTPService;
use App\Services\Nzb\NzbService;
use App\Services\ReleaseImageService;
use App\Services\Releases\ReleaseBrowseService;
use dariusiii\rarinfo\Par2Info;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing release-related database operations.
 * Handles file info, release updates, deletions, and search index updates.
 */
class ReleaseFileManager
{
    private readonly ReleaseUpdateService $releaseUpdateService;

    private readonly FileNameCleaner $fileNameCleaner;

    private readonly ReleaseSearchSyncCoordinator $searchSyncCoordinator;

    public function __construct(
        private readonly ProcessingConfiguration $config,
        private readonly ReleaseImageService $releaseImage,
        private readonly NfoService $nfo,
        private readonly NzbService $nzb,
        private readonly NameFixingService $nameFixingService,
        ?ReleaseUpdateService $releaseUpdateService = null,
        ?FileNameCleaner $fileNameCleaner = null,
        ?ReleaseSearchSyncCoordinator $searchSyncCoordinator = null,
    ) {
        $this->searchSyncCoordinator = $searchSyncCoordinator
            ?? new ReleaseSearchSyncCoordinator(
                new PersistenceMetricsCollector,
            );
        $this->releaseUpdateService = $releaseUpdateService
            ?? new ReleaseUpdateService(searchSyncCoordinator: $this->searchSyncCoordinator);
        $this->fileNameCleaner = $fileNameCleaner ?? new FileNameCleaner;
    }

    /**
     * Add file information to the database.
     *
     * @param  array<string, mixed>  $file
     *
     * @throws \Exception
     */
    public function addFileInfo(
        array $file,
        ReleaseProcessingContext $context,
        string $supportFileRegex
    ): bool {
        if (isset($file['error'])) {
            if ($this->config->debugMode) {
                Log::debug("Error: {$file['error']} (in: {$file['source']})");
            }

            return false;
        }

        if (! isset($file['name'])) {
            return false;
        }

        // Check for password
        if (isset($file['pass']) && $file['pass'] === true) {
            $context->releaseHasPassword = true;
            $context->passwordStatus = ReleaseBrowseService::PASSWD_RAR;

            return false;
        }

        // Check inner file blacklist
        if ($this->config->innerFileBlacklist !== false
            && preg_match($this->config->innerFileBlacklist, $file['name'])
        ) {
            $context->releaseHasPassword = true;
            $context->passwordStatus = ReleaseBrowseService::PASSWD_RAR;

            return false;
        }

        // Skip support files
        if (preg_match(
            '/(?:'.$supportFileRegex.'|part\d+|[rz]\d{1,3}|zipr\d{2,3}|\d{2,3}|zipx?|zip|rar)(\s*\.rar)?$/i',
            $file['name']
        )) {
            return false;
        }

        $size = $this->normalizeFileSize($file['size'] ?? 0);
        if ($size === null) {
            Log::warning('Skipping release file with invalid size metadata.', [
                'release_id' => $context->release->id,
                'name' => $file['name'],
                'size_type' => get_debug_type($file['size'] ?? 0),
            ]);

            return false;
        }

        // Increment total file info count
        $context->totalFileInfo++;

        // Don't add too many files
        if ($context->addedFileInfo >= 11) {
            return false;
        }

        $queued = $this->queueReleaseFile(
            $context,
            $context->release->id,
            $file['name'],
            $size,
            $file['date'] ?? now(),
            $file['pass'] ?? 0,
            '',
            $file['crc32'] ?? ''
        );

        if ($queued) {
            // Check for codec spam
            if (preg_match('#(?:^|[/\\\\])Codec[/\\\\]Setup\.exe$#i', $file['name'])) {
                if ($this->config->debugMode) {
                    Log::debug('Codec spam found, setting release to potentially passworded.');
                }
                $context->releaseHasPassword = true;
                $context->passwordStatus = ReleaseBrowseService::PASSWD_RAR;
            } elseif ($file['name'] !== '' && ! str_starts_with($file['name'], '.')) {
                // Run PreDB filename check
                $context->release['filename'] = $file['name'];
                $context->release['releases_id'] = $context->release->id;
                $this->nameFixingService->matchPreDbFiles($context->release, true, true, true);
            }

            return true;
        }

        return false;
    }

    /**
     * Update search indexes after adding file info.
     */
    public function updateSearchIndex(int $releaseId): void
    {
        $this->searchSyncCoordinator->request($releaseId);
    }

    /**
     * Rename NZBSPLIT-wrapped releases directly from parsed NZB titles.
     *
     * @param  list<array<string, mixed>>  $nzbContents
     */
    public function processReleaseNameFromNzbContents(array $nzbContents, ReleaseProcessingContext $context): bool
    {
        if (! $this->releaseHasNzbSplitWrapper($context->release)) {
            return false;
        }

        foreach ($nzbContents as $nzbFile) {
            $title = (string) ($nzbFile['title'] ?? '');

            if ($title === '') {
                continue;
            }

            $candidate = $this->fileNameCleaner->extractNzbSplitName($title);

            if ($candidate === null) {
                continue;
            }

            $candidate = $this->fileNameCleaner->normalizeCandidateTitle($candidate);

            $isPlausible = $this->fileNameCleaner->isPlausibleReleaseTitle($candidate);
            if (! $isPlausible) {
                continue;
            }

            $this->releaseUpdateService->updateRelease(
                $context->release,
                $candidate,
                'NZBSPLIT wrapper',
                true,
                'Filenames, ',
                true,
                true
            );

            $context->release->searchname = $candidate;

            return true;
        }

        return false;
    }

    /**
     * Finalize release processing with status updates.
     */
    public function finalizeRelease(ReleaseProcessingContext $context, bool $processPasswords): void
    {
        $updateRows = ['haspreview' => 0];

        // Check for existing samples
        if ($this->releaseImage->imageExists($this->releaseImage->imgSavePath, $context->release->guid.'_thumb')) {
            $updateRows = ['haspreview' => 1];
        }

        if (File::isFile($this->releaseImage->vidSavePath.$context->release->guid.'.ogv')) {
            $updateRows['videostatus'] = 1;
        }

        if ($this->releaseImage->imageExists($this->releaseImage->jpgSavePath, $context->release->guid.'_thumb')) {
            $updateRows['jpgstatus'] = 1;
        }

        $passwordStatus = $context->passwordStatus;

        // Set to no password if processing is off
        if (! $processPasswords) {
            $context->releaseHasPassword = false;
        }

        $updateRows = array_merge($updateRows, AdditionalCandidateQuery::claimResetValues());

        $pendingReleaseFiles = array_values($context->pendingReleaseFiles);
        $pendingParHashes = array_values($context->pendingParHashes);

        $insertedReleaseFiles = DB::transaction(function () use (
            $context,
            $pendingReleaseFiles,
            $pendingParHashes,
            $updateRows,
            $processPasswords,
            $passwordStatus,
        ): int {
            $inserted = $pendingReleaseFiles === []
                ? 0
                : ReleaseFile::query()->insertOrIgnore($pendingReleaseFiles);

            if ($pendingParHashes !== []) {
                ParHash::query()->insertOrIgnore($pendingParHashes);
            }

            $releaseFilesCount = ReleaseFile::whereReleasesId($context->release->id)->count('releases_id') ?? 0;

            if (! $context->releaseHasPassword && $context->nzbHasCompressedFile && $releaseFilesCount === 0) {
                Release::query()->where('id', $context->release->id)->update($updateRows);
            } else {
                $updateRows['passwordstatus'] = $processPasswords ? $passwordStatus : ReleaseBrowseService::PASSWD_NONE;
                $updateRows['rarinnerfilecount'] = $releaseFilesCount;
                Release::query()->where('id', $context->release->id)->update($updateRows);
            }

            return $inserted;
        }, 3);

        $context->pendingReleaseFiles = [];
        $context->pendingParHashes = [];
        $context->releaseFilesChanged = $context->releaseFilesChanged || $insertedReleaseFiles > 0;

        $this->searchSyncCoordinator->request((int) $context->release->id);
    }

    /**
     * Handle a release that has timed out during post-processing.
     *
     * Increments the timeout counter on the release. If the counter reaches
     * the configured maximum, the release is deleted entirely. Otherwise,
     * the release is marked as processed (haspreview=0, passwordstatus=0)
     * to remove it from the re-selection query.
     *
     * @return bool True if the release was deleted, false if it was skipped
     */
    public function handleReleaseTimeout(Release $release, int $maxTimeoutCount): bool
    {
        $currentCount = (int) ($release->pp_timeout_count ?? 0);
        $newCount = $currentCount + 1;

        if ($newCount >= $maxTimeoutCount) {
            Log::warning('Release '.$release->id.' deleted after '.$newCount.' post-processing timeout(s)');
            $this->deleteRelease($release);

            return true;
        }

        // Increment the timeout counter and mark as processed to skip re-selection
        Release::query()->where('id', $release->id)->update(array_merge([
            'pp_timeout_count' => $newCount,
            'haspreview' => 0,
            'passwordstatus' => ReleaseBrowseService::PASSWD_NONE,
        ], AdditionalCandidateQuery::claimResetValues()));

        $this->searchSyncCoordinator->request((int) $release->id);

        Log::warning('Release '.$release->id.' skipped after post-processing timeout ('.$newCount.'/'.$maxTimeoutCount.')');

        return false;
    }

    /**
     * Delete a broken release completely.
     */
    public function deleteRelease(Release $release): void
    {
        try {
            if (empty($release->id)) {
                return;
            }

            $id = (int) $release->id;
            $guid = $release->guid ?? '';
            $this->searchSyncCoordinator->discard($id);

            // Delete NZB file
            try {
                $nzbPath = $this->nzb->nzbPath($guid);
                if ($nzbPath && File::exists($nzbPath)) {
                    File::delete($nzbPath);
                }
            } catch (\Throwable) {
                // Ignore
            }

            // Delete preview assets
            try {
                $files = [$this->releaseImage->vidSavePath.$guid.'.ogv'];
                foreach (['webp', 'jpg', 'jpeg'] as $extension) {
                    $files[] = $this->releaseImage->imgSavePath.$guid.'_thumb.'.$extension;
                    $files[] = $this->releaseImage->jpgSavePath.$guid.'_thumb.'.$extension;
                }
                foreach ($files as $file) {
                    if (File::exists($file)) {
                        File::delete($file);
                    }
                }
            } catch (\Throwable) {
                // Ignore
            }

            // Delete related database rows
            try {
                ReleaseFile::where('releases_id', $id)->delete();
            } catch (\Throwable) {
            }

            try {
                MediaInfoModel::where('releases_id', $id)->delete();
            } catch (\Throwable) {
            }

            // Delete from search index
            try {
                Search::deleteRelease($id);
            } catch (\Throwable) {
                // Ignore
            }

            // Delete release row
            try {
                Release::where('id', $id)->delete();
            } catch (\Throwable) {
            }
        } catch (\Throwable) {
            // Last resort: swallow any exception
        }
    }

    /**
     * Process PAR2 file for file info and release name matching.
     */
    public function processPar2File(
        string $fileLocation,
        ReleaseProcessingContext $context,
        Par2Info $par2Info
    ): bool {
        $par2Info->open($fileLocation);

        if ($par2Info->error) {
            return false;
        }

        $releaseInfo = Release::query()
            ->where('id', $context->release->id)
            ->select(['postdate', 'proc_pp'])
            ->first();

        if ($releaseInfo === null) {
            return false;
        }

        $postDate = Carbon::createFromFormat('Y-m-d H:i:s', $releaseInfo->postdate)->getTimestamp();

        // Only get new name if category is OTHER
        $foundName = true;
        if ((int) $releaseInfo->proc_pp === 0 && $this->config->renamePar2
            && in_array((int) $context->release->categories_id, Category::OTHERS_GROUP, false)
        ) {
            $foundName = false;
        }

        $filesAdded = 0;

        foreach ($par2Info->getFileList() as $file) {
            if (! isset($file['name'])) {
                continue;
            }

            if ($foundName && $filesAdded > 10) {
                break;
            }

            // Add to release files
            if ($this->config->addPAR2Files) {
                if ($filesAdded < 11) {
                    if ($this->queueReleaseFile(
                        $context,
                        $context->release->id,
                        $file['name'],
                        $file['size'] ?? 0,
                        $postDate,
                        0,
                        $file['hash_16K'] ?? ''
                    )) {
                        $filesAdded++;
                    }
                }
            } else {
                $filesAdded++;
            }

            // Try to get a new name
            if (! $foundName) {
                $context->release->textstring = $file['name'];
                $context->release->releases_id = $context->release->id;
                if ($this->nameFixingService->checkName($context->release, $this->config->echoCLI, 'PAR2, ', true, true)) {
                    $foundName = true;
                }
            }
        }

        $context->foundPAR2Info = true;

        return true;
    }

    /**
     * Process NFO file with enhanced detection capabilities.
     *
     * Supports multiple NFO naming conventions:
     * - Standard: .nfo, .diz, .info
     * - Alternative: file_id.diz, readme.txt, info.txt
     * - Scene-style: 00-groupname.nfo, groupname-releasename.nfo
     */
    public function processNfoFile(
        string $fileLocation,
        ReleaseProcessingContext $context,
        NNTPService $nntp
    ): bool {
        try {
            $data = File::get($fileLocation);

            // Try to detect and convert encoding
            $data = $this->normalizeNfoEncoding($data);

            if ($this->nfo->isNFO($data, $context->release->guid)
                && $this->nfo->addAlternateNfo($data, $context->release, $nntp)
            ) {
                $context->releaseHasNoNFO = false;

                return true;
            }
        } catch (FileNotFoundException $e) {
            Log::warning("Could not read potential NFO file: {$fileLocation} - {$e->getMessage()}");
        }

        return false;
    }

    /**
     * Check if a filename looks like an NFO file.
     *
     * @param  string  $filename  The filename to check.
     * @return bool True if the filename matches NFO patterns.
     */
    public function isNfoFilename(string $filename): bool
    {
        // Standard NFO extensions
        if (preg_match('/\.(?:nfo|diz|info?)$/i', $filename)) {
            return true;
        }

        // Alternative NFO filenames
        $nfoPatterns = [
            '/^(?:file[_-]?id|readme|release|info(?:rmation)?|about|notes?)\.(?:txt|diz)$/i',
            '/^00-[a-z0-9_-]+\.nfo$/i',           // Scene: 00-group.nfo
            '/^0+-[a-z0-9_-]+\.nfo$/i',           // Scene variations
            '/^[a-z0-9_-]+-[a-z0-9_.-]+\.nfo$/i', // Scene: group-release.nfo
            '/info\.txt$/i',                      // info.txt (common alternative)
        ];

        $basename = basename($filename);
        foreach ($nfoPatterns as $pattern) {
            if (preg_match($pattern, $basename)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize NFO encoding to UTF-8.
     *
     * NFO files often use CP437 (DOS) encoding for ASCII art.
     * This method attempts to detect and convert various encodings.
     *
     * @param  string  $data  Raw NFO data.
     * @return string UTF-8 encoded NFO data.
     */
    protected function normalizeNfoEncoding(string $data): string
    {
        // Check for UTF-8 BOM and remove it
        if (str_starts_with($data, "\xEF\xBB\xBF")) {
            $data = substr($data, 3);
        }

        // Check for UTF-16 BOM
        if (str_starts_with($data, "\xFF\xFE")) {
            // UTF-16 LE
            $data = mb_convert_encoding(substr($data, 2), 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($data, "\xFE\xFF")) {
            // UTF-16 BE
            $data = mb_convert_encoding(substr($data, 2), 'UTF-8', 'UTF-16BE');
        }

        // If already valid UTF-8, return as-is
        if (mb_check_encoding($data, 'UTF-8')) {
            return $data;
        }

        // Try CP437 (DOS encoding - common for scene NFOs with ASCII art)
        // Use the cp437toUTF helper function
        if (function_exists('cp437toUTF')) {
            return cp437toUTF($data);
        }

        // Fallback: try ISO-8859-1 (Latin-1)
        $converted = @mb_convert_encoding($data, 'UTF-8', 'ISO-8859-1');
        if ($converted !== false) {
            return $converted;
        }

        // Last resort: force UTF-8 with error handling
        return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
    }

    /**
     * Handle release name extraction from RAR file content.
     *
     * @param  array<string, mixed>  $dataSummary
     */
    public function processReleaseNameFromRar(
        array $dataSummary,
        ReleaseProcessingContext $context
    ): void {
        $fileData = $dataSummary['file_list'] ?? [];
        if (empty($fileData)) {
            return;
        }

        $rarFileName = array_column($fileData, 'name');
        if (empty($rarFileName[0])) {
            return;
        }

        $extractedName = $this->extractReleaseNameFromFile($rarFileName[0]);

        if ($extractedName !== null) {
            $preCheck = Predb::whereTitle($extractedName)->first();
            $context->release->preid = $preCheck?->id ?? 0;
            $candidate = $preCheck?->title ?? $extractedName;
            $candidate = $this->normalizeCandidateTitle($candidate);

            if ($this->isPlausibleReleaseTitle($candidate)) {
                $this->releaseUpdateService->updateRelease(
                    $context->release,
                    $candidate,
                    'RarInfo FileName Match',
                    true,
                    'Filenames, ',
                    true,
                    true,
                    $context->release->preid
                );
            } elseif ($this->config->debugMode) {
                Log::debug('RarInfo: Ignored low-quality candidate "'.$candidate.'" from inner file name.');
            }
        } elseif (! empty($dataSummary['archives'][$rarFileName[0]]['file_list'])) {
            // Try nested archive
            $archiveData = $dataSummary['archives'][$rarFileName[0]]['file_list'];
            $archiveFileName = array_column($archiveData, 'name');
            $extractedName = $this->extractReleaseNameFromFile($archiveFileName[0] ?? '');

            if ($extractedName !== null) {
                $preCheck = Predb::whereTitle($extractedName)->first();
                $context->release->preid = $preCheck?->id ?? 0;
                $candidate = $preCheck?->title ?? $extractedName;
                $candidate = $this->normalizeCandidateTitle($candidate);

                if ($this->isPlausibleReleaseTitle($candidate)) {
                    $this->releaseUpdateService->updateRelease(
                        $context->release,
                        $candidate,
                        'RarInfo FileName Match',
                        true,
                        'Filenames, ',
                        true,
                        true,
                        $context->release->preid
                    );
                }
            }
        }
    }

    /**
     * Extract the release name from a filename.
     */
    private function extractReleaseNameFromFile(string $filename): ?string
    {
        $basename = basename($filename);

        $unwrapped = $this->fileNameCleaner->extractNzbSplitName($basename);
        if ($unwrapped !== null) {
            return $unwrapped;
        }

        $cleaned = preg_replace(
            '/\.(mkv|avi|mp4|m4v|mpg|mpeg|wmv|flv|mov|ts|vob|iso|divx|par2?|nfo|sfv|nzb|rar|zip|r\d{2,3}|pkg|exe|msi)$/i',
            '',
            $basename
        );

        if (preg_match('/^(.+[-.][A-Za-z0-9_]{2,})$/i', $cleaned, $match)) {
            return ucwords($match[1], '.-_ ');
        }

        if (preg_match(ReleaseUpdateService::PREDB_REGEX, $cleaned, $hit)) {
            return ucwords($hit[0], '.');
        }

        return null;
    }

    /**
     * Normalize a candidate title.
     */
    private function normalizeCandidateTitle(string $title): string
    {
        return $this->fileNameCleaner->normalizeCandidateTitle($title);
    }

    private function queueReleaseFile(
        ReleaseProcessingContext $context,
        int|string $releaseId,
        string $name,
        int|string $size,
        mixed $createdTime,
        mixed $hasPassword,
        string $hash = '',
        string $crc = ''
    ): bool {
        if ($name === '') {
            return false;
        }

        $this->loadExistingReleaseFileNames($context);
        if (isset($context->existingReleaseFileNames[$name]) || isset($context->pendingReleaseFiles[$name])) {
            return false;
        }

        $context->pendingReleaseFiles[$name] = [
            'releases_id' => (int) $releaseId,
            'name' => $name,
            'size' => (int) $size,
            'created_at' => $this->normalizeCreatedTime($createdTime),
            'updated_at' => now()->timestamp,
            'passworded' => (int) $hasPassword,
            'crc32' => $crc,
        ];
        $context->existingReleaseFileNames[$name] = true;
        $context->addedFileInfo++;

        if (\strlen($hash) === 32) {
            $context->pendingParHashes[$hash] = [
                'releases_id' => (int) $releaseId,
                'hash' => $hash,
            ];
        }

        return true;
    }

    private function loadExistingReleaseFileNames(ReleaseProcessingContext $context): void
    {
        if ($context->existingReleaseFileNames !== null) {
            return;
        }

        $existingNames = ReleaseFile::query()
            ->where('releases_id', $context->release->id)
            ->pluck('name')
            ->all();

        $context->existingReleaseFileNames = [];
        foreach ($existingNames as $name) {
            $context->existingReleaseFileNames[(string) $name] = true;
        }
    }

    private function normalizeCreatedTime(mixed $createdTime): mixed
    {
        if (! is_int($createdTime)) {
            return $createdTime;
        }

        if ($createdTime === 0) {
            return now()->format('Y-m-d H:i:s');
        }

        return Carbon::createFromTimestamp($createdTime, date_default_timezone_get())->format('Y-m-d H:i:s');
    }

    private function normalizeFileSize(mixed $size): ?int
    {
        if (is_int($size)) {
            return $size >= 0 ? $size : null;
        }

        if (is_float($size)) {
            return is_finite($size) && $size >= 0 && $size < PHP_INT_MAX
                ? (int) $size
                : null;
        }

        if (! is_string($size) || preg_match('/^\d+$/D', $size) !== 1) {
            return null;
        }

        $normalizedSize = ltrim($size, '0');
        if ($normalizedSize === '') {
            return 0;
        }

        $maximumSize = (string) PHP_INT_MAX;
        if (strlen($normalizedSize) > strlen($maximumSize)
            || (strlen($normalizedSize) === strlen($maximumSize) && strcmp($normalizedSize, $maximumSize) > 0)
        ) {
            return null;
        }

        return (int) $normalizedSize;
    }

    private function releaseHasNzbSplitWrapper(Release $release): bool
    {
        $name = (string) ($release->name ?? '');
        $searchName = (string) ($release->searchname ?? '');

        return str_contains(strtoupper($name), 'NZBSPLIT')
            || str_contains(strtoupper($searchName), 'NZBSPLIT');
    }

    /**
     * Check if a title is plausible for release naming.
     */
    private function isPlausibleReleaseTitle(string $title): bool
    {
        return $this->fileNameCleaner->isPlausibleReleaseTitle($title);
    }
}
