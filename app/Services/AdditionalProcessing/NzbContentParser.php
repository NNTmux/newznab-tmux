<?php

namespace App\Services\AdditionalProcessing;

use App\Services\Nzb\NzbParserService;
use App\Services\Nzb\NzbService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Service for parsing NZB file contents and extracting file metadata.
 * Handles NZB repair, file listing, and message ID extraction.
 */
class NzbContentParser
{
    public function __construct(
        private readonly NzbService $nzb,
        private readonly NzbParserService $nzbParser,
        private readonly bool $debugMode = false,
        private readonly bool $echoCLI = false
    ) {}

    /**
     * Parse an NZB file and return its contents as an array of files.
     *
     * @param  string  $guid  The release GUID to find the NZB for
     * @return array{contents: array<string, mixed>, error: string|null}
     */
    public function parseNzb(string $guid): array
    {
        $nzbPath = $this->nzb->nzbPath($guid);
        if ($nzbPath === false) {
            return ['contents' => [], 'error' => 'NZB not found for GUID: '.$guid];
        }

        $nzbContents = unzipGzipFile($nzbPath);
        if (! $nzbContents) {
            // Try repair on raw file contents
            $nzbContents = $this->attemptRawRepair($nzbPath);
            if (! $nzbContents) {
                return ['contents' => [], 'error' => 'NZB is empty or broken for GUID: '.$guid];
            }
        }

        // Get a list of files in the NZB
        $fileList = $this->nzbParser->parseNzbFileList($nzbContents, ['no-file-key' => false, 'strip-count' => true]);
        if (count($fileList) === 0) {
            // Attempt repair if initial parse yielded no files
            $repaired = $this->repairNzb($nzbContents, $nzbPath, $guid);
            if ($repaired !== null) {
                $fileList = $this->nzbParser->parseNzbFileList($repaired, ['no-file-key' => false, 'strip-count' => true]);
            }
            if (count($fileList) === 0) {
                return ['contents' => [], 'error' => 'NZB is potentially broken for GUID: '.$guid];
            }
        }

        // Sort keys naturally
        ksort($fileList, SORT_NATURAL);

        return ['contents' => $fileList, 'error' => null];
    }

    /**
     * Attempt to repair raw file contents before XML parsing.
     */
    private function attemptRawRepair(string $nzbPath): ?string
    {
        try {
            $rawFile = @File::get($nzbPath);
            if (! $rawFile) {
                return null;
            }

            // If gzipped, attempt decompress
            if (str_ends_with(strtolower($nzbPath), '.gz')) {
                $decompressed = @gzdecode($rawFile);
                if ($decompressed !== false) {
                    return $this->repairNzb($decompressed, $nzbPath, '');
                }
            } else {
                return $this->repairNzb($rawFile, $nzbPath, '');
            }
        } catch (\Throwable) {
            // Ignore
        }

        return null;
    }

    /**
     * Attempt to repair a potentially broken NZB XML string.
     */
    public function repairNzb(string $raw, string $originalPath, string $guid): ?string
    {
        // Remove common binary / control chars except tab, newline, carriage return
        $fixed = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $raw);

        // If missing opening <nzb ...> tag, wrap content
        if (! str_contains(strtolower($fixed), '<nzb')) {
            $fixed = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<nzb xmlns=\"http://www.newzbin.com/DTD/2003/nzb\">\n".$fixed."\n</nzb>";
        } else {
            // Ensure closing tag
            if (! preg_match('/<\/nzb>\s*$/i', $fixed)) {
                $fixed .= "\n</nzb>";
            }
        }

        // Try to parse using libxml recovery
        $opts = LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT | LIBXML_NONET | LIBXML_NOCDATA | LIBXML_PARSEHUGE;
        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($fixed, 'SimpleXMLElement', $opts);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if ($xml === false || empty($xml->file)) {
            if ($this->debugMode && $this->echoCLI) {
                $guidInfo = $guid ? ' for GUID: '.$guid : '';
                echo 'NZB repair failed'.$guidInfo.' ('.count($errors).' XML errors)'.PHP_EOL;
            }

            return null;
        }

        // Persist a repaired version if content changed
        try {
            if ($fixed !== $raw) {
                if (str_ends_with(strtolower($originalPath), '.gz')) {
                    @File::put($originalPath, gzencode($fixed));
                } else {
                    @File::put($originalPath, $fixed);
                }
            }
        } catch (\Throwable $e) {
            if ($this->debugMode) {
                Log::debug('Failed to persist repaired NZB: '.$e->getMessage());
            }
        }

        return $fixed;
    }

    /**
     * Process NZB contents to extract message IDs for different file types.
     *
     * @param  array<string, mixed>  $nzbContents
     * @return array<string, mixed>
     *                              hasCompressedFile: bool,
     *                              sampleMessageIDs: array,
     *                              jpgMessageIDs: array,
     *                              mediaInfoMessageID: string,
     *                              audioInfoMessageID: string,
     *                              audioInfoExtension: string,
     *                              bookFileCount: int
     *                              }
     */
    public function extractMessageIDs(
        array $nzbContents,
        string $groupName,
        int $segmentsToDownload,
        bool $processThumbnails,
        bool $processJPGSample,
        bool $processMediaInfo,
        bool $processAudioInfo,
        string $audioFileRegex,
        string $videoFileRegex,
        string $supportFileRegex,
        string $ignoreBookRegex
    ): array {
        $result = [
            'hasCompressedFile' => false,
            'sampleMessageIDs' => [],
            'jpgMessageIDs' => [],
            'mediaInfoMessageID' => '',
            'audioInfoMessageID' => '',
            'audioInfoExtension' => '',
            'bookFileCount' => 0,
        ];

        foreach ($nzbContents as $file) {
            try {
                $title = $file['title'] ?? '';
                $segments = $file['segments'] ?? [];

                // Skip support/nfo files
                if (preg_match('/(?:'.$supportFileRegex.'|nfo\\b|inf\\b|ofn\\b)($|[ ")]|-])(?!.{20,})/i', $title)) {
                    continue;
                }

                // Compressed file detection
                if (! $result['hasCompressedFile'] && preg_match(
                    '/(\\.(part\\d+|[rz]\\d+|rar|0+|0*10?|zipr\\d{2,3}|zipx?|7z(?:\\.\\d{3})?|(?:tar\\.)?(?:gz|bz2|xz))("|\\s*\\.rar)*($|[ ")]|-])|"[a-f0-9]{32}\\.[1-9]\\d{1,2}".*\\(\\d+\\/\\d{2,}\\)$)/i',
                    $title
                )) {
                    $result['hasCompressedFile'] = true;
                }

                // Look for a video sample (not an image)
                if ($processThumbnails && empty($result['sampleMessageIDs']) && ! empty($segments)
                    && stripos($title, 'sample') !== false
                    && ! preg_match('/\.jpe?g$/i', $title)
                ) {
                    $result['sampleMessageIDs'] = $this->extractSegments($segments, $segmentsToDownload);
                }

                // Look for a JPG picture (not a CD cover)
                if ($processJPGSample && empty($result['jpgMessageIDs']) && ! empty($segments)
                    && ! preg_match('/flac|lossless|mp3|music|inner-sanctum|sound/i', $groupName)
                    && preg_match('/\.jpe?g[. ")\]]/i', $title)
                ) {
                    $result['jpgMessageIDs'] = $this->extractSegments($segments, $segmentsToDownload);
                }

                // Look for a video file for MediaInfo (sample video)
                if ($processMediaInfo && empty($result['mediaInfoMessageID']) && ! empty($segments[0])
                    && stripos($title, 'sample') !== false
                    && preg_match('/'.$videoFileRegex.'[. ")\]]/i', $title)
                ) {
                    $result['mediaInfoMessageID'] = (string) $segments[0];
                }

                // Look for an audio file
                if ($processAudioInfo && empty($result['audioInfoMessageID']) && ! empty($segments)
                    && preg_match('/'.$audioFileRegex.'[. ")\]]/i', $title, $type)
                ) {
                    $result['audioInfoExtension'] = $type[1];
                    $result['audioInfoMessageID'] = (string) $segments[0];
                }

                // Count book files
                if (preg_match($ignoreBookRegex, $title)) {
                    $result['bookFileCount']++;
                }
            } catch (\ErrorException $e) {
                Log::debug($e->getTraceAsString());
            }
        }

        return $result;
    }

    /**
     * Extract segment message IDs up to a limit.
     *
     * @param  array<string, mixed>  $segments
     * @return array<string, mixed>
     */
    private function extractSegments(array $segments, int $limit): array
    {
        $ids = [];
        $segCount = count($segments) - 1;
        for ($i = 0; $i < $limit; $i++) {
            if ($i > $segCount) {
                break;
            }
            $ids[] = (string) $segments[$i]; // @phpstan-ignore offsetAccess.notFound
        }

        return $ids;
    }

    /**
     * Get the NZB path for a GUID.
     *
     * @return list<string>
     */
    public function getNzbPath(string $guid): string|false
    {
        return $this->nzb->nzbPath($guid);
    }
}
