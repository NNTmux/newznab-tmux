<?php

namespace App\Services\AdditionalProcessing;

use App\Models\MediaInfo as MediaInfoModel;
use App\Models\Predb;
use App\Models\Release;
use App\Models\ReleaseFile;
use Blacklight\Releases;
use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\DTO\ReleaseProcessingContext;
use App\Services\NameFixing\NameFixingService;
use App\Services\NameFixing\ReleaseUpdateService;
use Blacklight\ElasticSearchSiteSearch;
use Blacklight\ManticoreSearch;
use Blacklight\Nfo;
use Blacklight\NZB;
use Blacklight\ReleaseExtra;
use Blacklight\ReleaseImage;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing release-related database operations.
 * Handles file info, release updates, deletions, and search index updates.
 */
class ReleaseFileManager
{
    private ?ManticoreSearch $manticore = null;
    private ?ElasticSearchSiteSearch $elasticsearch = null;

    public function __construct(
        private readonly ProcessingConfiguration $config,
        private readonly ReleaseExtra $releaseExtra,
        private readonly ReleaseImage $releaseImage,
        private readonly Nfo $nfo,
        private readonly NZB $nzb,
        private readonly NameFixingService $nameFixingService
    ) {}

    /**
     * Add file information to the database.
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
            $context->passwordStatus = Releases::PASSWD_RAR;
            return false;
        }

        // Check inner file blacklist
        if ($this->config->innerFileBlacklist !== false
            && preg_match($this->config->innerFileBlacklist, $file['name'])
        ) {
            $context->releaseHasPassword = true;
            $context->passwordStatus = Releases::PASSWD_RAR;
            return false;
        }

        // Skip support files
        if (preg_match(
            '/(?:'.$supportFileRegex.'|part\d+|[rz]\d{1,3}|zipr\d{2,3}|\d{2,3}|zipx?|zip|rar|7z|gz|bz2|xz)(\s*\.rar)?$/i',
            $file['name']
        )) {
            return false;
        }

        // Increment total file info count
        $context->totalFileInfo++;

        // Don't add too many files
        if ($context->addedFileInfo >= 11) {
            return false;
        }

        // Check if a file already exists
        $exists = ReleaseFile::query()
            ->where([
                'releases_id' => $context->release->id,
                'name' => $file['name'],
                'size' => $file['size'],
            ])
            ->first();

        if ($exists !== null) {
            return false;
        }

        // Add the file
        $added = ReleaseFile::addReleaseFiles(
            $context->release->id,
            $file['name'],
            $file['size'],
            $file['date'],
            $file['pass'] ?? 0,
            '',
            $file['crc32'] ?? ''
        );

        if (! empty($added)) {
            $context->addedFileInfo++;

            // Check for codec spam
            if (preg_match('#(?:^|[/\\\\])Codec[/\\\\]Setup\.exe$#i', $file['name'])) {
                if ($this->config->debugMode) {
                    Log::debug('Codec spam found, setting release to potentially passworded.');
                }
                $context->releaseHasPassword = true;
                $context->passwordStatus = Releases::PASSWD_RAR;
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
        if ($this->config->elasticsearchEnabled) {
            $this->elasticsearch()->updateRelease($releaseId);
        } else {
            $this->manticore()->updateRelease($releaseId);
        }
    }

    /**
     * Finalize release processing with status updates.
     */
    public function finalizeRelease(ReleaseProcessingContext $context, bool $processPasswords): void
    {
        $updateRows = ['haspreview' => 0];

        // Check for existing samples
        if (File::isFile($this->releaseImage->imgSavePath.$context->release->guid.'_thumb.jpg')) {
            $updateRows = ['haspreview' => 1];
        }

        if (File::isFile($this->releaseImage->vidSavePath.$context->release->guid.'.ogv')) {
            $updateRows['videostatus'] = 1;
        }

        if (File::isFile($this->releaseImage->jpgSavePath.$context->release->guid.'_thumb.jpg')) {
            $updateRows['jpgstatus'] = 1;
        }

        // Get file count
        $releaseFilesCount = ReleaseFile::whereReleasesId($context->release->id)->count('releases_id') ?? 0;

        $passwordStatus = max([$context->passwordStatus]);

        // Set to no password if processing is off
        if (! $processPasswords) {
            $context->releaseHasPassword = false;
        }

        // Update based on conditions
        if (! $context->releaseHasPassword && $context->nzbHasCompressedFile && $releaseFilesCount === 0) {
            Release::query()->where('id', $context->release->id)->update($updateRows);
        } else {
            $updateRows['passwordstatus'] = $processPasswords ? $passwordStatus : Releases::PASSWD_NONE;
            $updateRows['rarinnerfilecount'] = $releaseFilesCount;
            Release::query()->where('id', $context->release->id)->update($updateRows);
        }
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

            // Delete NZB file
            try {
                $nzbPath = $this->nzb->NZBPath($guid);
                if ($nzbPath && File::exists($nzbPath)) {
                    File::delete($nzbPath);
                }
            } catch (\Throwable) {
                // Ignore
            }

            // Delete preview assets
            try {
                $files = [
                    $this->releaseImage->imgSavePath.$guid.'_thumb.jpg',
                    $this->releaseImage->jpgSavePath.$guid.'_thumb.jpg',
                    $this->releaseImage->vidSavePath.$guid.'.ogv',
                ];
                foreach ($files as $file) {
                    if ($file && File::exists($file)) {
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
                if ($this->config->elasticsearchEnabled) {
                    $this->elasticsearch()->deleteRelease($id);
                } else {
                    $this->manticore()->deleteRelease(['i' => $id, 'g' => $guid]);
                }
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
        \dariusiii\rarinfo\Par2Info $par2Info
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
            && in_array((int) $context->release->categories_id, \App\Models\Category::OTHERS_GROUP, false)
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
                if ($filesAdded < 11
                    && ReleaseFile::query()
                        ->where(['releases_id' => $context->release->id, 'name' => $file['name']])
                        ->first() === null
                ) {
                    if (ReleaseFile::addReleaseFiles(
                        $context->release->id,
                        $file['name'],
                        $file['size'],
                        $postDate,
                        0,
                        $file['hash_16K']
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

        // Update file count
        Release::query()->where('id', $context->release->id)->increment('rarinnerfilecount', $filesAdded);
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
        \Blacklight\NNTP $nntp
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
     * @param string $filename The filename to check.
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
     * @param string $data Raw NFO data.
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
        // Use the utility function if available
        if (class_exists('\Blacklight\utility\Utility') && method_exists('\Blacklight\utility\Utility', 'cp437toUTF')) {
            return \Blacklight\utility\Utility::cp437toUTF($data);
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
            $context->release->preid = $preCheck !== null ? $preCheck->value('id') : 0;
            $candidate = $preCheck->title ?? $extractedName;
            $candidate = $this->normalizeCandidateTitle($candidate);

            if ($this->isPlausibleReleaseTitle($candidate)) {
                (new ReleaseUpdateService())->updateRelease(
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
                $context->release->preid = $preCheck !== null ? $preCheck->value('id') : 0;
                $candidate = $preCheck->title ?? $extractedName;
                $candidate = $this->normalizeCandidateTitle($candidate);

                if ($this->isPlausibleReleaseTitle($candidate)) {
                    (new ReleaseUpdateService())->updateRelease(
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
        $t = trim($title);
        $t = preg_replace('/\.(mkv|avi|mp4|m4v|mpg|mpeg|wmv|flv|mov|ts|vob|iso|divx)$/i', '', $t) ?? $t;
        $t = preg_replace('/\.(par2?|nfo|sfv|nzb|rar|zip|r\d{2,3}|pkg|exe|msi|jpe?g|png|gif|bmp)$/i', '', $t) ?? $t;
        $t = preg_replace('/[.\-_ ](?:part|vol|r)\d+(?:\+\d+)?$/i', '', $t) ?? $t;
        $t = preg_replace('/[\s_]+/', ' ', $t) ?? $t;
        return trim($t, " .-_\t\r\n");
    }

    /**
     * Check if a title is plausible for release naming.
     */
    private function isPlausibleReleaseTitle(string $title): bool
    {
        $t = trim($title);
        if ($t === '' || strlen($t) < 12) {
            return false;
        }

        $wordCount = preg_match_all('/[A-Za-z0-9]{3,}/', $t);
        if ($wordCount < 2) {
            return false;
        }

        if (preg_match('/(?:^|[.\-_ ])(?:part|vol|r)\d+(?:\+\d+)?$/i', $t)) {
            return false;
        }

        if (preg_match('/^(setup|install|installer|patch|update|crack|keygen)\d*[\s._-]/i', $t)) {
            return false;
        }

        $hasGroupSuffix = (bool) preg_match('/[-.][A-Za-z0-9]{2,}$/', $t);
        $hasYear = (bool) preg_match('/\b(19|20)\d{2}\b/', $t);
        $hasQuality = (bool) preg_match('/\b(480p|720p|1080p|2160p|4k|webrip|web[ .-]?dl|bluray|bdrip|dvdrip|hdtv|hdrip|xvid|x264|x265|hevc|h\.?264|ts|cam|r5|proper|repack)\b/i', $t);
        $hasTV = (bool) preg_match('/\bS\d{1,2}[Eex]\d{1,3}\b/i', $t);
        $hasXXX = (bool) preg_match('/\bXXX\b/i', $t);

        return $hasGroupSuffix || ($hasTV && $hasQuality) || ($hasYear && ($hasQuality || $hasTV)) || $hasXXX;
    }

    private function manticore(): ManticoreSearch
    {
        if ($this->manticore === null) {
            $this->manticore = new ManticoreSearch();
        }
        return $this->manticore;
    }

    private function elasticsearch(): ElasticSearchSiteSearch
    {
        if ($this->elasticsearch === null) {
            $this->elasticsearch = new ElasticSearchSiteSearch();
        }
        return $this->elasticsearch;
    }
}

