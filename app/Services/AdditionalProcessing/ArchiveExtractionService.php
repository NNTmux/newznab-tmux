<?php

namespace App\Services\AdditionalProcessing;

use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\DTO\ReleaseProcessingContext;
use Blacklight\Releases;
use dariusiii\rarinfo\ArchiveInfo;
use dariusiii\rarinfo\Par2Info;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Service for extracting and processing archive files (RAR, ZIP, 7z, gzip, bzip2, xz).
 * Handles password detection, file listing, and content extraction.
 */
class ArchiveExtractionService
{
    private ArchiveInfo $archiveInfo;
    private Par2Info $par2Info;

    public function __construct(
        private readonly ProcessingConfiguration $config
    ) {
        $this->archiveInfo = new ArchiveInfo();
        $this->par2Info = new Par2Info();

        // Configure external clients for ArchiveInfo
        if ($this->config->unrarPath) {
            $this->archiveInfo->setExternalClients([ArchiveInfo::TYPE_RAR => $this->config->unrarPath]);
        }
    }

    /**
     * Process compressed data and extract file information.
     *
     * @return array{success: bool, files: array, hasPassword: bool, passwordStatus: int}
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
            'passwordStatus' => Releases::PASSWD_NONE,
        ];

        $context->compressedFilesChecked++;

        // Detect archive type early
        $archiveType = $this->detectArchiveType($compressedData);

        // Handle 7z, gzip, bzip2, xz with external 7zip binary
        if (in_array($archiveType, ['7z', 'gzip', 'bzip2', 'xz'], true)) {
            if ($archiveType === '7z') {
                $sevenZipResult = $this->processSevenZipArchive($compressedData, $context, $tmpPath);
                if ($sevenZipResult['success'] || $sevenZipResult['hasPassword']) {
                    return $sevenZipResult;
                }
            }

            if ($this->config->sevenZipPath) {
                $extractResult = $this->extractViaSevenZip($compressedData, $archiveType, $tmpPath);
                if ($extractResult['success']) {
                    return $extractResult;
                }
            }
        }

        // Try ArchiveInfo for RAR/ZIP
        if (! $this->archiveInfo->setData($compressedData, true)) {
            // Handle standalone video detection
            $videoType = $this->detectStandaloneVideo($compressedData);
            if ($videoType !== null) {
                return [
                    'success' => false,
                    'files' => [],
                    'hasPassword' => false,
                    'passwordStatus' => Releases::PASSWD_NONE,
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
                'passwordStatus' => Releases::PASSWD_RAR,
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
            'passwordStatus' => Releases::PASSWD_NONE,
            'archiveMarker' => $archiveMarker,
            'dataSummary' => $dataSummary,
        ];
    }

    /**
     * Detect the archive type from binary signature.
     */
    public function detectArchiveType(string $data): ?string
    {
        $head6 = substr($data, 0, 6);
        $head4 = substr($data, 0, 4);

        // 7z signature
        if ($head6 === "\x37\x7A\xBC\xAF\x27\x1C" && $this->isLikely7z($data)) {
            return '7z';
        }
        // GZIP
        if (strncmp($head4, "\x1F\x8B\x08", 3) === 0) {
            return 'gzip';
        }
        // BZip2
        if (strncmp($head4, 'BZh', 3) === 0) {
            return 'bzip2';
        }
        // XZ
        if ($head6 === "\xFD7zXZ\x00") {
            return 'xz';
        }
        // PDF (skip)
        if ($head4 === '%PDF') {
            return 'pdf';
        }

        return null;
    }

    /**
     * Heuristic validation for 7z signature.
     */
    private function isLikely7z(string $data): bool
    {
        if (strlen($data) < 32) {
            return false;
        }
        $verMajor = ord($data[6]);
        $verMinor = ord($data[7]);
        if ($verMajor !== 0x00 || $verMinor < 0x02 || $verMinor > 0x09) {
            return false;
        }
        $crc = substr($data, 8, 4);
        if ($crc === "\x00\x00\x00\x00" || $crc === "\xFF\xFF\xFF\xFF") {
            return false;
        }
        return true;
    }

    /**
     * Process a 7z archive using external binary and internal header parsing.
     */
    private function processSevenZipArchive(
        string $compressedData,
        ReleaseProcessingContext $context,
        string $tmpPath
    ): array {
        $result = [
            'success' => false,
            'files' => [],
            'hasPassword' => false,
            'passwordStatus' => Releases::PASSWD_NONE,
        ];

        if (! $this->config->sevenZipPath) {
            return $result;
        }

        // Try listing with external 7z binary
        $listed = $this->listSevenZipEntries($compressedData, $tmpPath);
        if (! empty($listed)) {
            if (! empty($listed[0]['__any_encrypted__'])) {
                return [
                    'success' => false,
                    'files' => [],
                    'hasPassword' => true,
                    'passwordStatus' => Releases::PASSWD_RAR,
                ];
            }

            $files = $this->filterSevenZipFiles($listed);
            if (! empty($files)) {
                return [
                    'success' => true,
                    'files' => $files,
                    'hasPassword' => false,
                    'passwordStatus' => Releases::PASSWD_NONE,
                    'archiveMarker' => '7z',
                ];
            }
        }

        // Fallback: scan for filenames in raw data
        $scannedNames = $this->scanSevenZipFilenames($compressedData);
        if (! empty($scannedNames)) {
            $files = array_map(fn($name) => [
                'name' => $name,
                'size' => 0,
                'date' => time(),
                'pass' => 0,
                'crc32' => '',
                'source' => '7z-scan',
            ], $scannedNames);

            return [
                'success' => true,
                'files' => $files,
                'hasPassword' => false,
                'passwordStatus' => Releases::PASSWD_NONE,
                'archiveMarker' => '7z',
            ];
        }

        return $result;
    }

    /**
     * List entries of a 7z archive using external 7z binary.
     */
    public function listSevenZipEntries(string $compressedData, string $tmpPath): array
    {
        if (! $this->config->sevenZipPath) {
            return [];
        }

        try {
            $tmpFile = $tmpPath.uniqid('7zlist_', true).'.7z';
            if (File::put($tmpFile, $compressedData) === false) {
                return [];
            }

            $cmd = [$this->config->sevenZipPath, 'l', '-slt', '-ba', '-bd', $tmpFile];
            $exitCode = 0;
            $stdout = null;
            $stderr = null;
            $ok = $this->execCommand($cmd, $exitCode, $stdout, $stderr);

            if (! $ok || $exitCode !== 0 || empty($stdout)) {
                // Try plain listing fallback
                $plainResult = $this->listSevenZipPlain($tmpFile);
                File::delete($tmpFile);
                return $plainResult;
            }

            File::delete($tmpFile);
            return $this->parseSevenZipStructuredOutput($stdout);
        } catch (\Throwable $e) {
            if ($this->config->debugMode) {
                Log::debug('Exception listing 7z: '.$e->getMessage());
            }
            return [];
        }
    }

    /**
     * Plain 7z listing fallback.
     */
    private function listSevenZipPlain(string $tmpFile): array
    {
        $cmd = [$this->config->sevenZipPath, 'l', '-ba', '-bd', $tmpFile];
        $exitCode = 0;
        $stdout = null;
        $stderr = null;
        $ok = $this->execCommand($cmd, $exitCode, $stdout, $stderr);

        if (! $ok || $exitCode !== 0 || empty($stdout)) {
            return [];
        }

        $files = [];
        $lines = preg_split('/\r?\n/', trim($stdout));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '-----')
                || str_contains($line, '   Date   ')
                || str_starts_with($line, 'Scanning ')
            ) {
                continue;
            }

            $name = null;
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\s+\S+\s+\d+\s+\d+\s+(\S.*)$/', $line, $m)) {
                $name = $m[1];
            } elseif (preg_match('/([A-Za-z0-9_#@()\[\]\-+&., ]+\.[A-Za-z0-9]{2,8})$/', $line, $m2)) {
                $name = trim($m2[1]);
            }

            if ($name && strlen($name) <= 300) {
                $files[] = ['name' => trim($name), 'size' => 0, 'encrypted' => false];
                if (count($files) >= 200) {
                    break;
                }
            }
        }

        return $files;
    }

    /**
     * Parse structured 7z output.
     */
    private function parseSevenZipStructuredOutput(string $output): array
    {
        $blocks = preg_split('/\n\n+/u', trim($output));
        $files = [];
        $anyEncrypted = false;

        foreach ($blocks as $block) {
            $lines = preg_split('/\r?\n/', trim($block));
            $row = [];
            foreach ($lines as $line) {
                $kv = explode(' = ', $line, 2);
                if (count($kv) === 2) {
                    $row[$kv[0]] = $kv[1];
                }
            }

            if (empty($row['Path'])) {
                continue;
            }

            $attr = $row['Attributes'] ?? '';
            if (str_contains($attr, 'D')) {
                continue; // directory
            }

            $encrypted = ($row['Encrypted'] ?? '') === '+';
            if ($encrypted) {
                $anyEncrypted = true;
            }

            $size = isset($row['Size']) && ctype_digit($row['Size']) ? (int) $row['Size'] : 0;
            $files[] = ['name' => $row['Path'], 'size' => $size, 'encrypted' => $encrypted];

            if (count($files) >= 200) {
                break;
            }
        }

        if ($anyEncrypted && isset($files[0])) {
            $files[0]['__any_encrypted__'] = true;
        }

        return $files;
    }

    /**
     * Filter 7z files using extension whitelist.
     */
    private function filterSevenZipFiles(array $files): array
    {
        $allowedExtensions = $this->getAllowedExtensions();
        $filtered = [];

        foreach ($files as $entry) {
            if (! empty($entry['__any_encrypted__'])) {
                continue;
            }

            $name = $entry['name'] ?? '';
            if ($name === '' || strlen($name) > 300) {
                continue;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (! in_array($ext, $allowedExtensions, true)) {
                continue;
            }

            $base = pathinfo($name, PATHINFO_FILENAME);
            $letterCount = preg_match_all('/[a-z]/i', $base);
            if ($letterCount <= 5) {
                continue;
            }

            $filtered[] = [
                'name' => $name,
                'size' => $entry['size'] ?? 0,
                'date' => time(),
                'pass' => 0,
                'crc32' => '',
                'source' => '7z-list',
            ];

            if (count($filtered) >= 50) {
                break;
            }
        }

        return $filtered;
    }

    /**
     * Scan for filenames in 7z raw data.
     */
    private function scanSevenZipFilenames(string $data): array
    {
        $slice = substr($data, 0, 8 * 1024 * 1024);
        $converted = preg_replace('/([\x20-\x7E])\x00/', '$1', $slice) ?? $slice;
        $converted = str_replace("\x00", ' ', $converted);

        $exts = '7z|rar|zip|gz|bz2|xz|tar|tgz|mp4|mkv|avi|mpg|mpeg|mov|ts|wmv|flv|m4v|mp3|flac|ogg|wav|aac|aiff|ape|mka|nfo|txt|diz|pdf|epub|mobi|jpg|jpeg|png|gif|sfv|par2|exe|dll|srt|sub|idx|iso|bin|cue|mds|mdf';
        $regex = '~(?:[A-Za-z0-9 _.+&@#,()!-]{0,120}[\\/])?([A-Za-z0-9 _.+&@#,()!-]{2,160}\.(?:'.$exts.'))~i';
        preg_match_all($regex, $converted, $matches, PREG_SET_ORDER);

        if (empty($matches)) {
            return [];
        }

        $names = [];
        foreach ($matches as $match) {
            $candidate = preg_replace('/ {2,}/', ' ', $match[1]);
            if ($candidate === '' || strlen($candidate) < 5 || substr_count($candidate, '.') > 10) {
                continue;
            }
            $candidate = trim($candidate, " .-\t\n\r");
            $lower = strtolower($candidate);
            if (! isset($names[$lower])) {
                $names[$lower] = $candidate;
                if (count($names) >= 80) {
                    break;
                }
            }
        }

        return array_values($names);
    }

    /**
     * Extract using 7zip binary.
     */
    public function extractViaSevenZip(string $compressedData, string $type, string $tmpPath): array
    {
        $result = [
            'success' => false,
            'files' => [],
            'hasPassword' => false,
            'passwordStatus' => Releases::PASSWD_NONE,
        ];

        if ($this->config->extractUsingRarInfo || ! $this->config->sevenZipPath) {
            return $result;
        }

        try {
            $extMap = ['7z' => '7z', 'gzip' => 'gz', 'bzip2' => 'bz2', 'xz' => 'xz'];
            $markerMap = ['7z' => '7z', 'gzip' => 'g', 'bzip2' => 'b', 'xz' => 'x'];
            $ext = $extMap[$type] ?? 'dat';
            $marker = $markerMap[$type] ?? $type;

            $extractDir = $tmpPath.'un7z/'.uniqid('', true).'/';
            if (! File::isDirectory($extractDir)) {
                File::makeDirectory($extractDir, 0777, true, true);
            }

            $fileName = $tmpPath.uniqid('', true).'.'.$ext;
            File::put($fileName, $compressedData);

            $cmd = [$this->config->sevenZipPath, 'e', '-y', '-bd', '-o'.$extractDir, $fileName];
            $exitCode = 0;
            $stdout = null;
            $stderr = null;
            $this->execCommand($cmd, $exitCode, $stdout, $stderr);

            $files = [];
            if (File::isDirectory($extractDir)) {
                foreach (File::allFiles($extractDir) as $f) {
                    $files[] = [
                        'name' => $f->getFilename(),
                        'size' => $f->getSize(),
                        'date' => time(),
                        'pass' => 0,
                        'crc32' => '',
                        'source' => $type,
                    ];
                }
            }

            File::delete($fileName);

            if (! empty($files)) {
                return [
                    'success' => true,
                    'files' => $this->filterExtractedFiles($files),
                    'hasPassword' => false,
                    'passwordStatus' => Releases::PASSWD_NONE,
                    'archiveMarker' => $marker,
                ];
            }
        } catch (\Throwable $e) {
            if ($this->config->debugMode) {
                Log::warning(strtoupper($type).' extraction exception: '.$e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Filter extracted files by allowed extensions.
     */
    private function filterExtractedFiles(array $files): array
    {
        $allowedExtensions = $this->getAllowedExtensions();
        $filtered = [];

        foreach ($files as $file) {
            $name = $file['name'] ?? '';
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (! in_array($ext, $allowedExtensions, true)) {
                continue;
            }

            $base = pathinfo($name, PATHINFO_FILENAME);
            $letterCount = preg_match_all('/[a-z]/i', $base);
            if ($letterCount <= 5) {
                continue;
            }

            $filtered[] = $file;
        }

        return $filtered;
    }

    /**
     * Get list of allowed file extensions.
     */
    private function getAllowedExtensions(): array
    {
        return [
            // NFO and info files (prioritized for extraction)
            'nfo', 'diz', 'inf', 'txt',
            // Subtitles
            'srt', 'sub', 'idx', 'ass', 'ssa', 'vtt',
            // Video
            'mkv', 'mpeg', 'avi', 'mp4', 'm4v', 'mov', 'wmv', 'flv', 'ts', 'vob', 'm2ts', 'webm',
            // Audio
            'mp3', 'm4a', 'flac', 'ogg', 'aac', 'wav', 'wma', 'opus', 'ape',
            // Images
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp',
            // Documents
            'epub', 'pdf', 'cbz', 'cbr', 'djvu', 'mobi', 'azw', 'azw3',
            // Executables (for software releases)
            'exe', 'msi',
        ];
    }

    /**
     * Check if a file is an NFO or info file.
     *
     * @param string $filename The filename to check.
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
     * @param array $files Array of file info arrays.
     * @return array Sorted array with NFO files first.
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
        if ($len >= 12 && substr($data, 4, 4) === 'ftyp') {
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
     * Execute a command with output capture.
     */
    private function execCommand(array $cmd, ?int &$exitCode, ?string &$stdout, ?string &$stderr): bool
    {
        $descriptorSpec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($cmd, $descriptorSpec, $pipes, null, null, ['bypass_shell' => true]);
        if (! is_resource($process)) {
            $exitCode = -1;
            return false;
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return $exitCode === 0;
    }
}

