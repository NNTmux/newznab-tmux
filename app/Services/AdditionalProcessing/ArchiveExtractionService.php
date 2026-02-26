<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing;

use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\DTO\ReleaseProcessingContext;
use App\Services\Releases\ReleaseBrowseService;
use dariusiii\rarinfo\ArchiveInfo;
use dariusiii\rarinfo\Par2Info;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Service for extracting and processing archive files (RAR, ZIP).
 * Handles password detection, file listing, and content extraction.
 */
class ArchiveExtractionService
{
    private ArchiveInfo $archiveInfo;

    private Par2Info $par2Info;

    public function __construct(
        private readonly ProcessingConfiguration $config
    ) {
        $this->archiveInfo = new ArchiveInfo;
        $this->par2Info = new Par2Info;

        // Configure external clients for ArchiveInfo
        if ($this->config->unrarPath) {
            $this->archiveInfo->setExternalClients([ArchiveInfo::TYPE_RAR => $this->config->unrarPath]);
        }
    }

    /**
     * Process compressed data and extract file information.
     *
     * @return array<string, mixed>
     */
    public function processCompressedData(
        string $compressedData,
        ReleaseProcessingContext $context,
        string $tmpPath
    ): array {
        $result = [
            'success' => false,
            'files' => [],
            'hasPassword' => false,
            'passwordStatus' => ReleaseBrowseService::PASSWD_NONE,
        ];

        $context->compressedFilesChecked++;

        // Try ArchiveInfo for RAR/ZIP
        if (! $this->archiveInfo->setData($compressedData, true)) {
            // Handle standalone video detection
            $videoType = $this->detectStandaloneVideo($compressedData);
            if ($videoType !== null) {
                return [
                    'success' => false,
                    'files' => [],
                    'hasPassword' => false,
                    'passwordStatus' => ReleaseBrowseService::PASSWD_NONE,
                    'standaloneVideoType' => $videoType,
                    'standaloneVideoData' => $compressedData,
                ];
            }

            return $result;
        }

        if ($this->archiveInfo->error !== '') {
            if ($this->config->debugMode) {
                Log::debug('ArchiveInfo Error: '.$this->archiveInfo->error);
            }

            return $result;
        }

        try {
            $dataSummary = $this->archiveInfo->getSummary(true);
        } catch (\Exception $e) {
            if ($this->config->debugMode) {
                Log::warning($e->getTraceAsString());
            }

            return $result;
        }

        // Check for encryption
        if (! empty($this->archiveInfo->isEncrypted)
            || (isset($dataSummary['is_encrypted']) && (int) $dataSummary['is_encrypted'] !== 0)
        ) {
            if ($this->config->debugMode) {
                Log::debug('ArchiveInfo: Compressed file has a password.');
            }

            return [
                'success' => false,
                'files' => [],
                'hasPassword' => true,
                'passwordStatus' => ReleaseBrowseService::PASSWD_RAR,
            ];
        }

        // Prepare extraction directories
        $this->prepareExtractionDirectories($tmpPath);

        // Process based on archive type
        $archiveMarker = $this->extractArchive($compressedData, $dataSummary, $tmpPath);

        // Get file list
        $files = $this->archiveInfo->getArchiveFileList();
        if (! is_array($files) || count($files) === 0) {
            return $result;
        }

        return [
            'success' => true,
            'files' => $files,
            'hasPassword' => false,
            'passwordStatus' => ReleaseBrowseService::PASSWD_NONE,
            'archiveMarker' => $archiveMarker,
            'dataSummary' => $dataSummary,
        ];
    }

    /**
     * Check if a file is an NFO or info file.
     *
     * @param  string  $filename  The filename to check.
     * @return bool True if it's an NFO-like file.
     */
    public function isNfoFile(string $filename): bool
    {
        $basename = strtolower(basename($filename));

        // Standard NFO extensions
        if (preg_match('/\.(nfo|diz|inf)$/i', $basename)) {
            return true;
        }

        // Common NFO alternative names
        $nfoNames = [
            'file_id.diz', 'fileid.diz', 'file-id.diz',
            'readme.txt', 'readme.1st', 'read.me', 'readmenow.txt',
            'info.txt', 'information.txt', 'about.txt', 'notes.txt',
            'release.txt', 'release.nfo',
        ];

        if (in_array($basename, $nfoNames, true)) {
            return true;
        }

        // Scene-style NFO naming: 00-groupname.nfo, group-release.nfo
        if (preg_match('/^(?:00?-[a-z0-9_-]+|[a-z0-9]+-[a-z0-9._-]+)\.(?:nfo|txt)$/i', $basename)) {
            return true;
        }

        return false;
    }

    /**
     * Sort files to prioritize NFO files for processing.
     *
     * @param  array<string, mixed>  $files  Array of file info arrays.
     * @return array<string, mixed> Sorted array with NFO files first.
     */
    public function sortFilesWithNfoPriority(array $files): array
    {
        usort($files, function ($a, $b) {
            $aIsNfo = $this->isNfoFile($a['name'] ?? '');
            $bIsNfo = $this->isNfoFile($b['name'] ?? '');

            if ($aIsNfo && ! $bIsNfo) {
                return -1;
            }
            if (! $aIsNfo && $bIsNfo) {
                return 1;
            }

            return 0;
        });

        return $files;
    }

    /**
     * Prepare extraction directories.
     *
     * @return list<mixed>
     */
    private function prepareExtractionDirectories(string $tmpPath): void
    {
        if ($this->config->extractUsingRarInfo) {
            return;
        }

        try {
            if ($this->config->unrarPath) {
                $unrarDir = $tmpPath.'unrar/';
                if (! File::isDirectory($unrarDir)) {
                    File::makeDirectory($unrarDir, 0777, true, true);
                }
            }
            $unzipDir = $tmpPath.'unzip/';
            if (! File::isDirectory($unzipDir)) {
                File::makeDirectory($unzipDir, 0777, true, true);
            }
        } catch (\Throwable $e) {
            if ($this->config->debugMode) {
                Log::warning('Failed ensuring extraction subdirectories: '.$e->getMessage());
            }
        }
    }

    /**
     * Extract archive based on type.
     *
     * @param  array<string, mixed>  $dataSummary
     */
    private function extractArchive(string $compressedData, array $dataSummary, string $tmpPath): string
    {
        $killString = $this->config->getKillString();

        switch ($dataSummary['main_type']) {
            case ArchiveInfo::TYPE_RAR:
                if (! $this->config->extractUsingRarInfo && $this->config->unrarPath) {
                    $fileName = $tmpPath.uniqid('', true).'.rar';
                    File::put($fileName, $compressedData);
                    runCmd($killString.$this->config->unrarPath.'" e -ai -ep -c- -id -inul -kb -or -p- -r -y "'.$fileName.'" "'.$tmpPath.'unrar/"');
                    File::delete($fileName);
                }

                return 'r';

            case ArchiveInfo::TYPE_ZIP:
                if (! $this->config->extractUsingRarInfo && $this->config->unzipPath) {
                    $fileName = $tmpPath.uniqid('', true).'.zip';
                    File::put($fileName, $compressedData);
                    runCmd($this->config->unzipPath.' -o "'.$fileName.'" -d "'.$tmpPath.'unzip/"');
                    File::delete($fileName);
                }

                return 'z';
        }

        return '';
    }

    /**
     * Detect standalone video from binary data.
     */
    public function detectStandaloneVideo(string $data): ?string
    {
        $len = strlen($data);
        if ($len < 16) {
            return null;
        }

        // AVI
        if (strncmp($data, 'RIFF', 4) === 0 && substr($data, 8, 4) === 'AVI ') {
            return 'avi';
        }
        // Matroska / WebM
        if (strncmp($data, "\x1A\x45\xDF\xA3", 4) === 0) {
            return 'mkv';
        }
        // MPEG
        $sig4 = substr($data, 0, 4);
        if ($sig4 === "\x00\x00\x01\xBA" || $sig4 === "\x00\x00\x01\xB3") {
            return 'mpg';
        }
        // Transport Stream
        if ($len >= 188 * 5) {
            $isTs = true;
            for ($i = 0; $i < 5; $i++) {
                if (! isset($data[188 * $i]) || $data[188 * $i] !== "\x47") {
                    $isTs = false;
                    break;
                }
            }
            if ($isTs) {
                return 'mpg';
            }
        }
        // MP4/MOV
        if (substr($data, 4, 4) === 'ftyp') {
            $brands = ['isom', 'iso2', 'avc1', 'mp41', 'mp42', 'dash', 'MSNV', 'qt  ', 'M4V ', 'M4P ', 'M4B ', 'M4A '];
            if (in_array(substr($data, 8, 4), $brands, true)) {
                return 'mp4';
            }
        }

        return null;
    }

    /**
     * Get PAR2 info parser.
     */
    public function getPar2Info(): Par2Info
    {
        return $this->par2Info;
    }

    /**
     * Get archive info handler.
     */
    public function getArchiveInfo(): ArchiveInfo
    {
        return $this->archiveInfo;
    }

    /**
     * Extract a specific file from archive data by filename.
     *
     * @param  string  $compressedData  The raw archive data
     * @param  string  $filename  The filename to extract (exact match)
     * @param  string  $tmpPath  Temporary directory path
     * @return string|null The extracted file content, or null if extraction failed
     */
    public function extractSpecificFile(string $compressedData, string $filename, string $tmpPath): ?string
    {
        // Try using ArchiveInfo's built-in extraction
        if ($this->archiveInfo->setData($compressedData, true)) {
            try {
                $extracted = $this->archiveInfo->getFileData($filename);
                if ($extracted !== false && ! empty($extracted)) {
                    return $extracted;
                }
            } catch (\Throwable $e) {
                if ($this->config->debugMode) {
                    Log::debug('ArchiveInfo getFileData failed: '.$e->getMessage());
                }
            }
        }

        // Fallback: use external tools to extract to temp directory

        // Try using unrar for RAR files
        if ($this->config->unrarPath) {
            $extracted = $this->extractFileViaUnrar($compressedData, $filename, $tmpPath);
            if ($extracted !== null) {
                return $extracted;
            }
        }

        // Try using unzip for ZIP files
        if ($this->config->unzipPath) {
            $extracted = $this->extractFileViaUnzip($compressedData, $filename, $tmpPath);
            if ($extracted !== null) {
                return $extracted;
            }
        }

        return null;
    }

    /**
     * Extract a specific file using unrar.
     */
    private function extractFileViaUnrar(string $compressedData, string $filename, string $tmpPath): ?string
    {
        try {
            $extractDir = $tmpPath.'extract_'.uniqid('', true).'/';
            if (! File::isDirectory($extractDir)) {
                File::makeDirectory($extractDir, 0777, true, true);
            }

            $archiveFile = $tmpPath.'archive_'.uniqid('', true).'.rar';
            File::put($archiveFile, $compressedData);

            // Extract specific file using unrar
            $killString = $this->config->getKillString();
            runCmd($killString.$this->config->unrarPath.'" e -y -c- -inul -p- "'.$archiveFile.'" "'.$filename.'" "'.$extractDir.'"');

            File::delete($archiveFile);

            // Look for extracted file
            $extractedPath = $extractDir.basename($filename);
            if (File::isFile($extractedPath)) {
                $content = File::get($extractedPath);
                File::deleteDirectory($extractDir);

                return $content;
            }

            // Try to find it with glob
            $files = File::allFiles($extractDir);
            foreach ($files as $file) {
                if (strtolower($file->getFilename()) === strtolower(basename($filename))) {
                    $content = File::get($file->getPathname());
                    File::deleteDirectory($extractDir);

                    return $content;
                }
            }

            File::deleteDirectory($extractDir);
        } catch (\Throwable $e) {
            if ($this->config->debugMode) {
                Log::debug('Unrar extraction failed: '.$e->getMessage());
            }
        }

        return null;
    }

    /**
     * Extract a specific file using unzip.
     */
    private function extractFileViaUnzip(string $compressedData, string $filename, string $tmpPath): ?string
    {
        try {
            $extractDir = $tmpPath.'extract_'.uniqid('', true).'/';
            if (! File::isDirectory($extractDir)) {
                File::makeDirectory($extractDir, 0777, true, true);
            }

            $archiveFile = $tmpPath.'archive_'.uniqid('', true).'.zip';
            File::put($archiveFile, $compressedData);

            // Extract specific file using unzip
            runCmd($this->config->unzipPath.' -j "'.$archiveFile.'" "'.$filename.'" -d "'.$extractDir.'"');

            File::delete($archiveFile);

            // Look for extracted file
            $extractedPath = $extractDir.basename($filename);
            if (File::isFile($extractedPath)) {
                $content = File::get($extractedPath);
                File::deleteDirectory($extractDir);

                return $content;
            }

            // Try to find it with glob
            $files = File::allFiles($extractDir);
            foreach ($files as $file) {
                if (strtolower($file->getFilename()) === strtolower(basename($filename))) {
                    $content = File::get($file->getPathname());
                    File::deleteDirectory($extractDir);

                    return $content;
                }
            }

            File::deleteDirectory($extractDir);
        } catch (\Throwable $e) {
            if ($this->config->debugMode) {
                Log::debug('Unzip extraction failed: '.$e->getMessage());
            }
        }

        return null;
    }
}
