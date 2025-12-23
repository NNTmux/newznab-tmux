<?php

declare(strict_types=1);

namespace Blacklight;

use App\Models\Release;
use App\Models\ReleaseNfo;
use App\Models\Settings;
use App\Models\UsenetGroup;
use App\Services\NNTP\NNTPService;
use App\Services\PostProcessService;
use dariusiii\rarinfo\Par2Info;
use dariusiii\rarinfo\SfvInfo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Class Nfo - Handles NFO file processing, validation, and metadata extraction.
 *
 * NFO files are text files commonly used in the warez scene to provide information
 * about releases. This class handles detection, validation, parsing and storage of NFO content.
 */
class Nfo
{
    /**
     * Regex to detect common non-NFO file headers/signatures.
     * Matches XML, NZB, RIFF (media), PAR/RAR archives, and other binary formats.
     */
    protected string $_nonNfoHeaderRegex = '/\A(\s*<\?xml|=newz\[NZB\]=|RIFF|\s*[RP]AR|.{0,10}(JFIF|matroska|ftyp|ID3)|PK\x03\x04|\x1f\x8b\x08|MZ|%PDF|GIF8[79]a|\x89PNG)|;\s*Generated\s*by.*SF\w/i';

    /**
     * Regex to identify text encoding from the 'file' command output.
     */
    protected string $_textFileRegex = '/(ASCII|ISO-8859|UTF-(8|16|32).*?|Non-ISO extended-ASCII)\s*text/i';

    /**
     * Regex to identify common binary file types from the 'file' command output.
     */
    protected string $_binaryFileRegex = '/^(JPE?G|Parity|PNG|RAR|XML|(7-)?[Zz]ip|PDF|GIF|executable|archive|compressed|data|binary)/i';

    /**
     * Regex to detect binary characters within the content.
     * Excludes common control characters that may appear in NFOs (tab, newline, carriage return).
     */
    protected string $_binaryCharsRegex = '/[\x00-\x08\x0B\x0C\x0E-\x1F]/';

    /**
     * Common NFO keywords that help identify legitimate NFO files.
     */
    protected array $_nfoKeywords = [
        // Release information
        'release', 'group', 'date', 'size', 'format', 'source', 'genre', 'codec',
        'bitrate', 'resolution', 'language', 'subtitle', 'ripped', 'cracked',
        'keygen', 'serial', 'patch', 'trainer', 'install', 'notes', 'greets',
        'nfo', 'ascii', 'artwork', 'presents', 'proudly', 'brings', 'another',
        // Scene terminology
        'scene', 'rls', 'nuked', 'proper', 'repack', 'internal', 'retail',
        'webdl', 'webrip', 'bluray', 'bdrip', 'dvdrip', 'hdtv', 'pdtv',
        // Media info
        'video', 'audio', 'duration', 'runtime', 'aspect', 'fps', 'channels',
        'sample', 'encoder', 'x264', 'x265', 'hevc', 'avc', 'xvid', 'divx',
        'aac', 'ac3', 'dts', 'truehd', 'atmos', 'flac', 'mp3',
        // Content info
        'movie', 'film', 'episode', 'season', 'series', 'title', 'year',
        'director', 'cast', 'actors', 'plot', 'synopsis', 'imdb', 'rating',
        // Software
        'crack', 'readme', 'setup', 'installer', 'license', 'registration',
        'protection', 'requirements', 'platform', 'operating', 'system',
        // Contact/Group info
        'contact', 'irc', 'www', 'http', 'ftp', 'email', 'apply', 'join',
    ];

    /**
     * Scene group patterns for improved detection.
     */
    protected array $_sceneGroupPatterns = [
        '/(?:^|\n)\s*[-=*]{3,}.*?([A-Z0-9]{2,15})\s*[-=*]{3,}/i',
        '/(?:presents?|brought\s+(?:to\s+)?(?:you\s+)?by|from)\s*[:\-]?\s*([A-Z][A-Z0-9]{1,14})/i',
        '/(?:greets?\s+(?:go(?:es)?\s+)?(?:out\s+)?to|respect\s+to)\s*[:\-]?\s*([\w,\s&]+)/i',
        '/(?:^|\n)\s*([A-Z][A-Z0-9]{1,14})\s+(?:nfo|info|release)\s*(?:$|\n)/i',
        '/(?:released\s+by|rls\s+by)\s*[:\-]?\s*([A-Z][A-Z0-9]{1,14})/i',
    ];

    /**
     * Maximum NFO file size in bytes (64KB).
     */
    protected const MAX_NFO_SIZE = 65535;

    /**
     * Minimum NFO file size in bytes.
     */
    protected const MIN_NFO_SIZE = 12;

    /**
     * Cache TTL for settings in seconds.
     */
    protected const SETTINGS_CACHE_TTL = 300;

    /**
     * @var int Number of NFOs to process per batch.
     */
    private int $nzbs;

    /**
     * @var int Maximum release size to process NFO (in GB).
     */
    protected int $maxSize;

    /**
     * @var int Maximum retry attempts for failed NFO fetches.
     */
    private int $maxRetries;

    /**
     * @var int Minimum release size to process NFO (in MB).
     */
    protected int $minSize;

    /**
     * @var string Temporary path for processing files.
     */
    private string $tmpPath;

    /**
     * @var bool Whether to echo output to CLI.
     */
    protected bool $echo;

    public const NFO_FAILED = -9; // We failed to get a NFO after admin set max retries.

    public const NFO_UNPROC = -1; // Release has not been processed yet.

    public const NFO_NONFO = 0; // Release has no NFO.

    public const NFO_FOUND = 1; // Release has an NFO.

    protected ColorCLI $colorCli;

    /**
     * Default constructor.
     *
     * Initializes NFO processing settings from database/config with caching.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $this->echo = (bool) config('nntmux.echocli');
        $this->colorCli = new ColorCLI();

        // Cache settings to reduce database queries
        // Note: Cast after Cache::remember as cached values may be stored as strings
        $this->nzbs = (int) Cache::remember('nfo_maxnfoprocessed', self::SETTINGS_CACHE_TTL, function () {
            $value = Settings::settingValue('maxnfoprocessed');

            return $value !== '' ? (int) $value : 100;
        });

        $maxRetries = (int) Cache::remember('nfo_maxnforetries', self::SETTINGS_CACHE_TTL, function () {
            return (int) Settings::settingValue('maxnforetries');
        });
        $this->maxRetries = $maxRetries >= 0 ? -($maxRetries + 1) : self::NFO_UNPROC;
        $this->maxRetries = max($this->maxRetries, -8);

        $this->maxSize = (int) Cache::remember('nfo_maxsizetoprocessnfo', self::SETTINGS_CACHE_TTL, function () {
            return (int) Settings::settingValue('maxsizetoprocessnfo');
        });

        $this->minSize = (int) Cache::remember('nfo_minsizetoprocessnfo', self::SETTINGS_CACHE_TTL, function () {
            return (int) Settings::settingValue('minsizetoprocessnfo');
        });

        $this->tmpPath = rtrim((string) config('nntmux.tmp_unrar_path'), '/\\') . '/';
    }

    /**
     * Look for a TV Show ID or Movie ID in a string.
     *
     * Supports: TVMaze, IMDB, TVDB (legacy & modern), TMDB, AniDB
     *
     * @param  string  $str  The string with a Show ID.
     * @return array{showid: string, site: string}|false Return array with show ID and site source or false on failure.
     */
    public function parseShowId(string $str): array|false
    {
        // TVMaze
        if (preg_match('/tvmaze\.com\/shows\/(\d{1,6})/i', $str, $hits)) {
            return ['showid' => trim($hits[1]), 'site' => 'tvmaze'];
        }

        // IMDB (movies and TV shows)
        if (preg_match('/imdb\.com\/title\/(tt\d{7,8})/i', $str, $hits)) {
            return ['showid' => trim($hits[1]), 'site' => 'imdb'];
        }

        // TVDB - Legacy URL format
        if (preg_match('/thetvdb\.com\/\?tab=series&id=(\d{1,8})/i', $str, $hits)) {
            return ['showid' => trim($hits[1]), 'site' => 'thetvdb'];
        }

        // TVDB - Modern URL format (series/slug or series/id)
        if (preg_match('/thetvdb\.com\/series\/(\d{1,8}|[\w-]+)/i', $str, $hits)) {
            return ['showid' => trim($hits[1]), 'site' => 'thetvdb'];
        }

        // TMDB - Movie
        if (preg_match('/themoviedb\.org\/movie\/(\d{1,8})/i', $str, $hits)) {
            return ['showid' => trim($hits[1]), 'site' => 'tmdb_movie'];
        }

        // TMDB - TV Show
        if (preg_match('/themoviedb\.org\/tv\/(\d{1,8})/i', $str, $hits)) {
            return ['showid' => trim($hits[1]), 'site' => 'tmdb_tv'];
        }

        // AniDB
        if (preg_match('/anidb\.net\/(?:perl-bin\/animedb\.pl\?show=anime&aid=|anime\/)(\d{1,6})/i', $str, $hits)) {
            return ['showid' => trim($hits[1]), 'site' => 'anidb'];
        }

        // Trakt.tv
        if (preg_match('/trakt\.tv\/(?:shows|movies)\/([\w-]+)/i', $str, $hits)) {
            return ['showid' => trim($hits[1]), 'site' => 'trakt'];
        }

        return false;
    }

    /**
     * Confirm this is an NFO file.
     *
     * Uses multiple validation strategies:
     * 1. Size validation (too large/small = not NFO)
     * 2. Binary header detection (known file signatures)
     * 3. File type detection via 'file' command
     * 4. PAR2/SFV structure detection
     * 5. Binary character content analysis
     * 6. NFO keyword/content heuristics
     *
     * @param  bool|string  $possibleNFO  The nfo content.
     * @param  string  $guid  The guid of the release.
     * @return bool True if it's likely an NFO, False otherwise.
     */
    public function isNFO(bool|string &$possibleNFO, string $guid): bool
    {
        if ($possibleNFO === false || $possibleNFO === '') {
            return false;
        }

        $size = \strlen($possibleNFO);

        // Basic size and signature checks using constants
        if ($size >= self::MAX_NFO_SIZE || $size < self::MIN_NFO_SIZE) {
            return false;
        }

        // Quick check for known non-NFO file signatures
        if (preg_match($this->_nonNfoHeaderRegex, $possibleNFO)) {
            return false;
        }

        // Additional binary format checks
        if ($this->detectBinaryFormat($possibleNFO)) {
            return false;
        }

        $tmpPath = $this->tmpPath.$guid.'.nfo';
        $isNfo = false;

        try {
            // File/GetId3 work with files, so save to disk.
            File::put($tmpPath, $possibleNFO);

            // Use 'file' command via fileInfo if available
            $result = fileInfo($tmpPath);
            if (! empty($result)) {
                if (preg_match($this->_textFileRegex, $result)) {
                    $isNfo = true;
                } elseif (preg_match($this->_binaryFileRegex, $result) || preg_match($this->_binaryCharsRegex, $possibleNFO)) {
                    $isNfo = false;
                }

                // If fileInfo gave a result, apply additional heuristics before returning
                if ($isNfo) {
                    // Additional content validation for text files
                    $isNfo = $this->validateNfoContent($possibleNFO);
                }

                return $isNfo;
            }

            // Fallback checks if 'file' command is unavailable or inconclusive
            // Check if it's a PAR2 file
            $par2info = new Par2Info;
            $par2info->setData($possibleNFO);
            if (! $par2info->error) {
                return false;
            }

            // Check if it's an SFV file
            $sfv = new SfvInfo;
            $sfv->setData($possibleNFO);
            if (! $sfv->error) {
                return false;
            }

            // Check for binary characters
            if (preg_match($this->_binaryCharsRegex, $possibleNFO)) {
                return false;
            }

            // Final content-based validation
            $isNfo = $this->validateNfoContent($possibleNFO);

        } catch (Throwable $e) {
            Log::error("Error processing potential NFO for GUID {$guid}: ".$e->getMessage());
            $isNfo = false;
        } finally {
            // Ensure temporary file is always deleted
            if (File::exists($tmpPath)) {
                try {
                    File::delete($tmpPath);
                } catch (Throwable $e) {
                    Log::error("Error deleting temporary NFO file {$tmpPath}: ".$e->getMessage());
                }
            }
        }

        return $isNfo;
    }

    /**
     * Detect binary file formats by magic bytes.
     *
     * @param  string  $data  The file content to check.
     * @return bool True if binary format detected.
     */
    protected function detectBinaryFormat(string $data): bool
    {
        if (strlen($data) < 4) {
            return false;
        }

        // Magic bytes for common binary formats
        $magicBytes = [
            "\x50\x4B\x03\x04" => 'ZIP',         // ZIP/DOCX/XLSX etc.
            "\x50\x4B\x05\x06" => 'ZIP_EMPTY',   // Empty ZIP
            "\x52\x61\x72\x21" => 'RAR',         // RAR
            "\x37\x7A\xBC\xAF" => '7Z',          // 7-Zip
            "\x1F\x8B\x08" => 'GZIP',            // GZip
            "\x42\x5A\x68" => 'BZIP2',           // BZip2
            "\xFD\x37\x7A\x58" => 'XZ',          // XZ
            "\x89\x50\x4E\x47" => 'PNG',         // PNG
            "\xFF\xD8\xFF" => 'JPEG',            // JPEG
            "\x47\x49\x46\x38" => 'GIF',         // GIF
            "\x25\x50\x44\x46" => 'PDF',         // PDF
            "\x49\x44\x33" => 'MP3_ID3',         // MP3 with ID3
            "\xFF\xFB" => 'MP3',                 // MP3
            "\x4F\x67\x67\x53" => 'OGG',         // OGG
            "\x66\x4C\x61\x43" => 'FLAC',        // FLAC
            "\x52\x49\x46\x46" => 'RIFF',        // WAV/AVI
            "\x00\x00\x01\xBA" => 'MPEG',        // MPEG video
            "\x00\x00\x01\xB3" => 'MPEG',        // MPEG video
            "\x1A\x45\xDF\xA3" => 'MKV',         // Matroska/WebM
            "\x4D\x5A" => 'EXE',                 // Windows EXE
            "\x7F\x45\x4C\x46" => 'ELF',         // Linux executable
            "\xCA\xFE\xBA\xBE" => 'JAVA',        // Java class
            "\xD0\xCF\x11\xE0" => 'OLE',         // MS Office old format
        ];

        foreach ($magicBytes as $magic => $type) {
            if (str_starts_with($data, $magic)) {
                return true;
            }
        }

        // Check for UTF-16 BOM (could be text, but unlikely NFO)
        if (str_starts_with($data, "\xFF\xFE") || str_starts_with($data, "\xFE\xFF")) {
            // UTF-16 - could be valid, let other checks handle it
            return false;
        }

        return false;
    }

    /**
     * Validate NFO content using heuristics.
     *
     * @param  string  $content  The content to validate.
     * @return bool True if content appears to be a valid NFO.
     */
    protected function validateNfoContent(string $content): bool
    {
        $length = strlen($content);

        // Too short to be meaningful
        if ($length < 50) {
            return false;
        }

        // Count printable ASCII characters
        $printableCount = preg_match_all('/[\x20-\x7E]/', $content);
        $printableRatio = $printableCount / $length;

        // NFOs should be mostly printable characters
        if ($printableRatio < 0.7) {
            return false;
        }

        // Check for minimum text content (words, not just symbols)
        $wordCount = preg_match_all('/[A-Za-z]{2,}/', $content);
        if ($wordCount < 5) {
            return false;
        }

        // Check for NFO-like content patterns
        $nfoIndicators = 0;

        // Look for common NFO keywords
        foreach ($this->_nfoKeywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                $nfoIndicators++;
                if ($nfoIndicators >= 3) {
                    return true; // High confidence if multiple keywords found
                }
            }
        }

        // Check for scene-style formatting
        if (preg_match('/[-=*]{5,}/', $content)) {
            $nfoIndicators++;
        }

        // Check for URL presence (common in NFOs)
        if (preg_match('/https?:\/\/|www\./i', $content)) {
            $nfoIndicators++;
        }

        // Check for media IDs
        if (preg_match('/imdb\.com|thetvdb\.com|themoviedb\.org|anidb\.net/i', $content)) {
            $nfoIndicators += 2;
        }

        // Check for field:value patterns
        if (preg_match_all('/^[A-Za-z\s]{2,20}\s*[:\.]\s*.+$/m', $content, $matches)) {
            $nfoIndicators += min(count($matches[0]) / 3, 2);
        }

        return $nfoIndicators >= 2;
    }

    /**
     * Add an NFO from alternate sources. ex.: PreDB, rar, zip, etc...
     *
     * @param  string  $nfo  The nfo.
     * @param  NNTPService  $nntp  Instance of class NNTPService.
     * @return bool True on success, False on failure.
     *
     * @throws \Exception
     */
    public function addAlternateNfo(string &$nfo, $release, NNTPService $nntp): bool
    {
        if ($release->id > 0 && $this->isNFO($nfo, $release->guid)) {
            $check = ReleaseNfo::whereReleasesId($release->id)->first(['releases_id']);

            if ($check === null) {
                ReleaseNfo::query()->insert(['releases_id' => $release->id, 'nfo' => "\x1f\x8b\x08\x00".gzcompress($nfo)]);
            }

            Release::whereId($release->id)->update(['nfostatus' => self::NFO_FOUND]);

            if (! isset($release->completion)) {
                $release->completion = 0;
            }

            if ($release->completion === 0) {
                $nzbContents = new NZBContents(
                    [
                        'Echo' => $this->echo,
                        'NNTP' => $nntp,
                        'Nfo' => $this,
                        'Settings' => null,
                        'PostProcess' => app(PostProcessService::class),
                    ]
                );
                $nzbContents->parseNZB($release->guid, $release->id, $release->guid);
            }

            return true;
        }

        return false;
    }

    /**
     * Attempt to find NFO files inside the NZB's of releases.
     *
     * @param  NNTPService  $nntp  The NNTP connection object
     * @param  string  $groupID  (optional) Group ID to filter releases by
     * @param  string  $guidChar  (optional) First character of the GUID for parallel processing
     * @param  bool  $processImdb  (optional) Process IMDB IDs (currently unused)
     * @param  bool  $processTv  (optional) Process TV IDs (currently unused)
     * @return int Count of successfully processed NFO files
     *
     * @throws \Exception If NNTP operations fail
     */
    public function processNfoFiles(NNTPService $nntp, string $groupID = '', string $guidChar = '', bool $processImdb = true, bool $processTv = true): int
    {
        $processedCount = 0;

        // Build base query with all filters
        $baseQuery = $this->buildNfoProcessingQuery($groupID, $guidChar);

        // Fetch releases to process
        $releases = $baseQuery->clone()
            ->orderBy('nfostatus')
            ->orderByDesc('postdate')
            ->limit($this->nzbs)
            ->get(['id', 'guid', 'groups_id', 'name']);

        $nfoCount = $releases->count();

        if ($nfoCount > 0) {
            // Display processing information
            $this->displayProcessingHeader($guidChar, $groupID, $nfoCount);

            // Show detailed stats if echo is enabled
            if ($this->echo) {
                $this->displayNfoStatusStats($baseQuery);
            }

            // Process each release
            $nzbContents = new NZBContents(['NNTP' => $nntp, 'Nfo' => $this]);

            foreach ($releases as $release) {
                try {
                    $groupName = UsenetGroup::getNameByID($release['groups_id']);
                    $fetchedBinary = $nzbContents->getNfoFromNZB($release['guid'], $release['id'], $release['groups_id'], $groupName);

                    if ($fetchedBinary !== false) {
                        DB::beginTransaction();
                        try {
                            // Only insert if not already present
                            $exists = ReleaseNfo::whereReleasesId($release['id'])->exists();
                            if (! $exists) {
                                ReleaseNfo::query()->insert([
                                    'releases_id' => $release['id'],
                                    'nfo' => "\x1f\x8b\x08\x00".gzcompress($fetchedBinary),
                                ]);
                            }

                            // Update status
                            Release::whereId($release['id'])->update(['nfostatus' => self::NFO_FOUND]);
                            DB::commit();
                            $processedCount++;
                        } catch (\Exception $e) {
                            DB::rollBack();
                            if ($this->echo) {
                                $this->colorCli->error("Error saving NFO for release {$release['id']}: {$e->getMessage()}");
                            }
                        }
                    }
                } catch (\Exception $e) {
                    if ($this->echo) {
                        $this->colorCli->error("Error processing release {$release['id']}: {$e->getMessage()}");
                    }
                }
            }
        }

        // Process failed NFO attempts
        $this->handleFailedNfoAttempts($groupID, $guidChar);

        // Output results
        if ($this->echo) {
            if ($nfoCount > 0) {
                echo PHP_EOL;
            }
            if ($processedCount > 0) {
                $this->colorCli->primary($processedCount.' NFO file(s) found/processed.');
            }
        }

        return $processedCount;
    }

    /**
     * Build base query for NFO processing with all common filters
     */
    private function buildNfoProcessingQuery(string $groupID, string $guidChar): \Illuminate\Database\Eloquent\Builder
    {
        $query = Release::query()
            ->whereBetween('nfostatus', [$this->maxRetries, self::NFO_UNPROC]);

        if ($guidChar !== '') {
            $query->where('leftguid', $guidChar);
        }

        if ($groupID !== '') {
            $query->where('groups_id', $groupID);
        }

        if ($this->maxSize > 0) {
            $query->where('size', '<', $this->maxSize * 1073741824);
        }

        if ($this->minSize > 0) {
            $query->where('size', '>', $this->minSize * 1048576);
        }

        return $query;
    }

    /**
     * Display header information about the NFO processing
     */
    private function displayProcessingHeader(string $guidChar, string $groupID, int $nfoCount): void
    {
        $this->colorCli->primary(
            PHP_EOL.
            ($guidChar === '' ? '' : '['.$guidChar.'] ').
            ($groupID === '' ? '' : '['.$groupID.'] ').
            'Processing '.$nfoCount.
            ' NFO(s), starting at '.$this->nzbs.
            ' * = hidden NFO, + = NFO, - = no NFO, f = download failed.'
        );
    }

    /**
     * Display statistics about NFO status counts
     */
    private function displayNfoStatusStats(\Illuminate\Database\Eloquent\Builder $baseQuery): void
    {
        $nfoStats = $baseQuery->clone()
            ->select(['nfostatus as status', DB::raw('COUNT(id) as count')])
            ->groupBy(['nfostatus'])
            ->orderBy('nfostatus')
            ->get();

        if ($nfoStats instanceof \Traversable && $nfoStats->count() > 0) {
            $outString = PHP_EOL.'Available to process';
            foreach ($nfoStats as $row) {
                $outString .= ', '.$row['status'].' = '.number_format($row['count']);
            }
            $this->colorCli->header($outString.'.');
        }
    }

    /**
     * Handle releases that have failed too many NFO fetch attempts
     */
    private function handleFailedNfoAttempts(string $groupID, string $guidChar): void
    {
        $failedQuery = Release::query()
            ->where('nfostatus', '<', $this->maxRetries)
            ->where('nfostatus', '>', self::NFO_FAILED);

        if ($guidChar !== '') {
            $failedQuery->where('leftguid', $guidChar);
        }

        if ($groupID !== '') {
            $failedQuery->where('groups_id', $groupID);
        }

        // Process in chunks to avoid memory issues with large result sets
        $failedQuery->select(['id'])->chunk(100, function ($releases) {
            DB::beginTransaction();
            try {
                foreach ($releases as $release) {
                    // Remove any releasenfo for failed attempts
                    ReleaseNfo::whereReleasesId($release->id)->delete();

                    // Set release.nfostatus to failed
                    Release::whereId($release->id)->update(['nfostatus' => self::NFO_FAILED]);
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                if ($this->echo) {
                    $this->colorCli->error("Error handling failed NFO attempts: {$e->getMessage()}");
                }
            }
        });
    }

    /**
     * Get a string like this:
     * "AND r.nfostatus BETWEEN -8 AND -1 AND r.size < 1073741824 AND r.size > 1048576"
     * To use in a query.
     *
     *
     * @throws \Exception
     *
     * @static
     */
    public static function NfoQueryString(): string
    {
        $maxSize = (int) Settings::settingValue('maxsizetoprocessnfo');
        $minSize = (int) Settings::settingValue('minsizetoprocessnfo');
        $dummy = (int) Settings::settingValue('maxnforetries');
        $maxRetries = ($dummy >= 0 ? -($dummy + 1) : self::NFO_UNPROC);

        return sprintf(
            'AND r.nfostatus BETWEEN %d AND %d %s %s',
            ($maxRetries < -8 ? -8 : $maxRetries),
            self::NFO_UNPROC,
            ($maxSize > 0 ? ('AND r.size < '.($maxSize * 1073741824)) : ''),
            ($minSize > 0 ? ('AND r.size > '.($minSize * 1048576)) : '')
        );
    }

    /**
     * Extract URLs from NFO content.
     *
     * @param  string  $nfoContent  The NFO content to parse.
     * @return array Array of found URLs.
     */
    public function extractUrls(string $nfoContent): array
    {
        $urls = [];

        // Match HTTP/HTTPS URLs
        if (preg_match_all('/https?:\/\/[^\s<>"\']+/i', $nfoContent, $matches)) {
            $urls = array_merge($urls, $matches[0]);
        }

        // Match www URLs without protocol
        if (preg_match_all('/(?<![\/\.])\bwww\.[a-z0-9][-a-z0-9]*\.[^\s<>"\']+/i', $nfoContent, $matches)) {
            foreach ($matches[0] as $url) {
                $urls[] = 'http://'.$url;
            }
        }

        return array_unique(array_filter($urls));
    }

    /**
     * Extract release group name from NFO content.
     *
     * Uses multiple detection strategies including:
     * - Common presentation phrases
     * - Scene-style headers with ASCII borders
     * - Greetings sections
     * - Footer signatures
     *
     * @param  string  $nfoContent  The NFO content to parse.
     * @return string|null The group name if found, null otherwise.
     */
    public function extractGroupName(string $nfoContent): ?string
    {
        // False positives to filter out
        $falsePositives = [
            'THE', 'AND', 'FOR', 'NFO', 'INFO', 'DVD', 'BLU', 'RAY', 'WEB', 'HDTV',
            'RELEASE', 'GROUP', 'DATE', 'SIZE', 'CODEC', 'VIDEO', 'AUDIO', 'FORMAT',
            'NOTES', 'INSTALL', 'GREETS', 'PRESENTS', 'TEAM', 'SCENE', 'FILE', 'FILES',
        ];

        // Use configured scene group patterns
        foreach ($this->_sceneGroupPatterns as $pattern) {
            if (preg_match($pattern, $nfoContent, $matches)) {
                $groupName = trim($matches[1]);
                if (! in_array(strtoupper($groupName), $falsePositives, true) && strlen($groupName) >= 2 && strlen($groupName) <= 20) {
                    return $groupName;
                }
            }
        }

        // Additional patterns for group name detection
        $additionalPatterns = [
            // "GROUP presents" or "GROUP brings you"
            '/\b([A-Z][A-Z0-9]{1,14})\s+(?:presents?|brings?\s+you)/i',
            // Common footer format: "--- GROUP ---"
            '/[-=]{2,}\s*([A-Z][A-Z0-9]{1,14})\s*[-=]{2,}$/mi',
            // Contact section: "irc.server.net #GROUP"
            '/irc\.[a-z0-9.-]+\s+#([A-Z][A-Z0-9]{1,14})/i',
            // Website: "www.GROUP.com/org/net"
            '/www\.([a-z][a-z0-9]{1,14})\.(?:com|org|net|info)/i',
            // ASCII art name extraction (common pattern at start)
            '/^\s*[^a-zA-Z0-9]*([A-Z][A-Z0-9]{2,14})[^a-zA-Z0-9]*\s*$/mi',
        ];

        foreach ($additionalPatterns as $pattern) {
            if (preg_match($pattern, $nfoContent, $matches)) {
                $groupName = trim($matches[1]);
                if (! in_array(strtoupper($groupName), $falsePositives, true) && strlen($groupName) >= 2 && strlen($groupName) <= 20) {
                    return strtoupper($groupName);
                }
            }
        }

        return null;
    }

    /**
     * Extract release date from NFO content.
     *
     * @param  string  $nfoContent  The NFO content to parse.
     * @return string|null ISO date string if found, null otherwise.
     */
    public function extractReleaseDate(string $nfoContent): ?string
    {
        $patterns = [
            // DD/MM/YYYY or MM/DD/YYYY
            '/(?:date|released?|rls)\s*[:\-]?\s*(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})/i',
            // YYYY-MM-DD
            '/(?:date|released?|rls)\s*[:\-]?\s*(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})/i',
            // Month DD, YYYY
            '/(?:date|released?|rls)\s*[:\-]?\s*(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?\s+(\d{1,2}),?\s+(\d{4})/i',
        ];

        foreach ($patterns as $index => $pattern) {
            if (preg_match($pattern, $nfoContent, $matches)) {
                try {
                    if ($index === 0) {
                        // Try both DD/MM and MM/DD formats
                        $year = strlen($matches[3]) === 2 ? '20'.$matches[3] : $matches[3];
                        // Assume DD/MM/YYYY format (more common internationally)
                        return sprintf('%04d-%02d-%02d', (int) $year, (int) $matches[2], (int) $matches[1]);
                    } elseif ($index === 1) {
                        // YYYY-MM-DD
                        return sprintf('%04d-%02d-%02d', (int) $matches[1], (int) $matches[2], (int) $matches[3]);
                    } else {
                        // Month name format
                        $months = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];
                        $month = $months[strtolower(substr($matches[1], 0, 3))] ?? 1;

                        return sprintf('%04d-%02d-%02d', (int) $matches[3], $month, (int) $matches[2]);
                    }
                } catch (Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Extract video/audio codec information from NFO content.
     *
     * @param  string  $nfoContent  The NFO content to parse.
     * @return array{video?: string, audio?: string, resolution?: string} Array with codec info.
     */
    public function extractCodecInfo(string $nfoContent): array
    {
        $result = [];

        // Video codecs
        $videoPatterns = [
            '/(?:video|codec)\s*[:\-]?\s*(x264|x265|hevc|h\.?264|h\.?265|xvid|divx|av1|vp9|mpeg[24]?)/i',
            '/\b(x264|x265|HEVC|H\.?264|H\.?265|XviD|DivX|AV1|VP9)\b/i',
        ];
        foreach ($videoPatterns as $pattern) {
            if (preg_match($pattern, $nfoContent, $matches)) {
                $result['video'] = strtoupper(str_replace('.', '', $matches[1]));
                break;
            }
        }

        // Audio codecs
        $audioPatterns = [
            '/(?:audio|sound)\s*[:\-]?\s*(aac|ac3|dts(?:-(?:hd|ma|x))?|truehd|atmos|flac|mp3|eac3|dd[+p]?|dolby)/i',
            '/\b(AAC|AC3|DTS(?:-(?:HD|MA|X))?|TrueHD|Atmos|FLAC|EAC3|DD[+P]?)\b/i',
        ];
        foreach ($audioPatterns as $pattern) {
            if (preg_match($pattern, $nfoContent, $matches)) {
                $result['audio'] = strtoupper($matches[1]);
                break;
            }
        }

        // Resolution
        $resolutionPatterns = [
            '/(?:resolution|quality)\s*[:\-]?\s*(\d{3,4}[xX×]\d{3,4}|\d{3,4}p|[48]K|UHD|FHD|HD)/i',
            '/\b(2160p|1080p|720p|480p|4K|UHD|FHD|HD)\b/i',
            '/\b(\d{3,4})\s*[xX×]\s*(\d{3,4})\b/',
        ];
        foreach ($resolutionPatterns as $index => $pattern) {
            if (preg_match($pattern, $nfoContent, $matches)) {
                if ($index === 2) {
                    $result['resolution'] = $matches[1].'x'.$matches[2];
                } else {
                    $result['resolution'] = strtoupper($matches[1]);
                }
                break;
            }
        }

        return $result;
    }

    /**
     * Extract file size information from NFO content.
     *
     * @param  string  $nfoContent  The NFO content to parse.
     * @return int|null File size in bytes if found, null otherwise.
     */
    public function extractFileSize(string $nfoContent): ?int
    {
        $patterns = [
            '/(?:size|file\s*size)\s*[:\-]?\s*(\d+(?:[.,]\d+)?)\s*(bytes?|[KMGTP]B|[KMGTP]iB)/i',
            '/\b(\d+(?:[.,]\d+)?)\s*(GB|GiB|MB|MiB|TB|TiB)\b/i',
        ];

        $multipliers = [
            'B' => 1, 'BYTE' => 1, 'BYTES' => 1,
            'KB' => 1024, 'KIB' => 1024,
            'MB' => 1024 * 1024, 'MIB' => 1024 * 1024,
            'GB' => 1024 * 1024 * 1024, 'GIB' => 1024 * 1024 * 1024,
            'TB' => 1024 * 1024 * 1024 * 1024, 'TIB' => 1024 * 1024 * 1024 * 1024,
            'PB' => 1024 * 1024 * 1024 * 1024 * 1024, 'PIB' => 1024 * 1024 * 1024 * 1024 * 1024,
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $nfoContent, $matches)) {
                $value = (float) str_replace(',', '.', $matches[1]);
                $unit = strtoupper($matches[2]);

                if (isset($multipliers[$unit])) {
                    return (int) ($value * $multipliers[$unit]);
                }
            }
        }

        return null;
    }

    /**
     * Extract all media IDs (IMDB, TVDB, TMDB, etc.) from NFO content.
     *
     * @param  string  $nfoContent  The NFO content to parse.
     * @return array Array of media IDs with their sources.
     */
    public function extractAllMediaIds(string $nfoContent): array
    {
        $ids = [];

        // IMDB
        if (preg_match_all('/imdb\.com\/title\/(tt\d{7,8})/i', $nfoContent, $matches)) {
            foreach ($matches[1] as $id) {
                $ids[] = ['id' => $id, 'source' => 'imdb'];
            }
        }

        // TVDB
        if (preg_match_all('/thetvdb\.com\/(?:\?tab=series&id=|series\/)(\d{1,8})/i', $nfoContent, $matches)) {
            foreach ($matches[1] as $id) {
                $ids[] = ['id' => $id, 'source' => 'thetvdb'];
            }
        }

        // TMDB Movie
        if (preg_match_all('/themoviedb\.org\/movie\/(\d{1,8})/i', $nfoContent, $matches)) {
            foreach ($matches[1] as $id) {
                $ids[] = ['id' => $id, 'source' => 'tmdb_movie'];
            }
        }

        // TMDB TV
        if (preg_match_all('/themoviedb\.org\/tv\/(\d{1,8})/i', $nfoContent, $matches)) {
            foreach ($matches[1] as $id) {
                $ids[] = ['id' => $id, 'source' => 'tmdb_tv'];
            }
        }

        // TVMaze
        if (preg_match_all('/tvmaze\.com\/shows\/(\d{1,6})/i', $nfoContent, $matches)) {
            foreach ($matches[1] as $id) {
                $ids[] = ['id' => $id, 'source' => 'tvmaze'];
            }
        }

        // AniDB
        if (preg_match_all('/anidb\.net\/(?:perl-bin\/animedb\.pl\?show=anime&aid=|anime\/)(\d{1,6})/i', $nfoContent, $matches)) {
            foreach ($matches[1] as $id) {
                $ids[] = ['id' => $id, 'source' => 'anidb'];
            }
        }

        // MyAnimeList (MAL)
        if (preg_match_all('/myanimelist\.net\/anime\/(\d{1,6})/i', $nfoContent, $matches)) {
            foreach ($matches[1] as $id) {
                $ids[] = ['id' => $id, 'source' => 'mal'];
            }
        }

        return $ids;
    }

    /**
     * Parse and extract comprehensive metadata from NFO content.
     *
     * @param  string  $nfoContent  The NFO content to parse.
     * @return array Associative array with extracted metadata.
     */
    public function parseNfoMetadata(string $nfoContent): array
    {
        return [
            'urls' => $this->extractUrls($nfoContent),
            'group' => $this->extractGroupName($nfoContent),
            'release_date' => $this->extractReleaseDate($nfoContent),
            'codec_info' => $this->extractCodecInfo($nfoContent),
            'file_size' => $this->extractFileSize($nfoContent),
            'media_ids' => $this->extractAllMediaIds($nfoContent),
            'show_id' => $this->parseShowId($nfoContent),
            'language' => $this->extractLanguage($nfoContent),
            'runtime' => $this->extractRuntime($nfoContent),
            'genre' => $this->extractGenre($nfoContent),
            'software_info' => $this->extractSoftwareInfo($nfoContent),
            'release_title' => $this->extractReleaseTitle($nfoContent),
        ];
    }

    /**
     * Extract language information from NFO content.
     *
     * @param  string  $nfoContent  The NFO content to parse.
     * @return array Array of detected languages.
     */
    public function extractLanguage(string $nfoContent): array
    {
        $languages = [];

        // Common language patterns in NFOs
        $patterns = [
            '/(?:language|audio|spoken?|dialogue)\s*[:\-]?\s*([A-Za-z]+(?:\s*[,\/&]\s*[A-Za-z]+)*)/i',
            '/(?:subs?|subtitles?)\s*[:\-]?\s*([A-Za-z]+(?:\s*[,\/&]\s*[A-Za-z]+)*)/i',
        ];

        // Known language names
        $knownLanguages = [
            'english', 'german', 'french', 'spanish', 'italian', 'dutch', 'portuguese',
            'russian', 'japanese', 'korean', 'chinese', 'mandarin', 'cantonese',
            'swedish', 'norwegian', 'danish', 'finnish', 'polish', 'czech', 'hungarian',
            'turkish', 'arabic', 'hindi', 'thai', 'vietnamese', 'indonesian', 'malay',
            'multi', 'dual', 'english/german', 'eng', 'ger', 'fre', 'spa', 'ita',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $nfoContent, $matches)) {
                $langs = preg_split('/[\s,\/&]+/', strtolower($matches[1]));
                foreach ($langs as $lang) {
                    $lang = trim($lang);
                    if (in_array($lang, $knownLanguages, true) && ! in_array($lang, $languages, true)) {
                        $languages[] = ucfirst($lang);
                    }
                }
            }
        }

        return $languages;
    }

    /**
     * Extract runtime/duration from NFO content.
     *
     * @param  string  $nfoContent  The NFO content to parse.
     * @return int|null Runtime in minutes, or null if not found.
     */
    public function extractRuntime(string $nfoContent): ?int
    {
        $patterns = [
            // "Runtime: 1h 30m" or "Duration: 90min"
            '/(?:runtime|duration|length|playtime)\s*[:\-]?\s*(?:(\d{1,2})\s*h(?:ours?)?\s*)?(\d{1,3})\s*m(?:in(?:utes?)?)?/i',
            // "Runtime: 01:30:00" or "1:30:00"
            '/(?:runtime|duration|length|playtime)\s*[:\-]?\s*(\d{1,2}):(\d{2})(?::(\d{2}))?/i',
            // "90 minutes" standalone
            '/\b(\d{2,3})\s*(?:min(?:utes?)?|mins)\b/i',
        ];

        foreach ($patterns as $index => $pattern) {
            if (preg_match($pattern, $nfoContent, $matches)) {
                if ($index === 0) {
                    $hours = ! empty($matches[1]) ? (int) $matches[1] : 0;
                    $minutes = (int) $matches[2];
                    return ($hours * 60) + $minutes;
                } elseif ($index === 1) {
                    $hours = (int) $matches[1];
                    $minutes = (int) $matches[2];
                    return ($hours * 60) + $minutes;
                } else {
                    return (int) $matches[1];
                }
            }
        }

        return null;
    }

    /**
     * Extract genre information from NFO content.
     *
     * @param  string  $nfoContent  The NFO content to parse.
     * @return array Array of detected genres.
     */
    public function extractGenre(string $nfoContent): array
    {
        $genres = [];

        if (preg_match('/(?:genre|category|type)\s*[:\-]?\s*([^\n\r]+)/i', $nfoContent, $matches)) {
            $genreString = $matches[1];
            // Split on common separators
            $parts = preg_split('/[\s,\/&|]+/', $genreString);

            // Known valid genres
            $validGenres = [
                'action', 'adventure', 'animation', 'biography', 'comedy', 'crime',
                'documentary', 'drama', 'family', 'fantasy', 'history', 'horror',
                'music', 'musical', 'mystery', 'romance', 'sci-fi', 'scifi', 'sport',
                'thriller', 'war', 'western', 'adult', 'xxx', 'erotic', 'anime',
                'rpg', 'fps', 'strategy', 'simulation', 'puzzle', 'racing', 'sports',
                'rock', 'pop', 'electronic', 'hip-hop', 'rap', 'classical', 'jazz',
            ];

            foreach ($parts as $part) {
                $part = strtolower(trim($part));
                if (in_array($part, $validGenres, true) && ! in_array(ucfirst($part), $genres, true)) {
                    $genres[] = ucfirst($part);
                }
            }
        }

        return $genres;
    }

    /**
     * Extract software-specific information from NFO content.
     *
     * @param  string  $nfoContent  The NFO content to parse.
     * @return array Software info including platform, version, protection, etc.
     */
    public function extractSoftwareInfo(string $nfoContent): array
    {
        $info = [];

        // Platform/OS detection
        $platformPatterns = [
            '/(?:platform|os|system|requires?)\s*[:\-]?\s*(windows?|linux|mac(?:os)?|unix|android|ios)/i',
        ];
        foreach ($platformPatterns as $pattern) {
            if (preg_match($pattern, $nfoContent, $matches)) {
                $info['platform'] = ucfirst(strtolower($matches[1]));
                break;
            }
        }

        // Version detection
        if (preg_match('/(?:version|ver|v)\s*[:\-]?\s*(\d+(?:\.\d+)*(?:\s*(?:build|b)\s*\d+)?)/i', $nfoContent, $matches)) {
            $info['version'] = trim($matches[1]);
        }

        // Protection type
        $protectionPatterns = [
            '/(?:protection|drm|copy[ -]?protection)\s*[:\-]?\s*([^\n\r]+)/i',
        ];
        foreach ($protectionPatterns as $pattern) {
            if (preg_match($pattern, $nfoContent, $matches)) {
                $protection = trim($matches[1]);
                if (strlen($protection) > 2 && strlen($protection) < 50) {
                    $info['protection'] = $protection;
                }
                break;
            }
        }

        // Crack/Keygen/Serial info
        if (preg_match('/\b(cracked|keygen|serial|patch|loader|activator)\b/i', $nfoContent)) {
            $info['has_crack'] = true;
        }

        return $info;
    }

    /**
     * Extract release title from NFO content.
     *
     * @param  string  $nfoContent  The NFO content to parse.
     * @return string|null The release title if found.
     */
    public function extractReleaseTitle(string $nfoContent): ?string
    {
        $patterns = [
            // "Title: Movie Name" or "Release: Title.Goes.Here"
            '/(?:title|release|name)\s*[:\-]?\s*([^\n\r]{5,100})/i',
            // Scene-style title in header
            '/(?:^|\n)\s*(?:[\-=*~]{3,}\s*)?([A-Za-z0-9][\w.\-\s]{10,80}?)(?:\s*[\-=*~]{3,})?\s*(?:\n|$)/m',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $nfoContent, $matches)) {
                $title = trim($matches[1]);
                // Filter out common non-title content
                if (! preg_match('/^(?:date|size|codec|format|video|audio|language|runtime|genre)\s*:/i', $title)
                    && strlen($title) >= 5 && strlen($title) <= 100) {
                    return $title;
                }
            }
        }

        return null;
    }

    /**
     * Clean and normalize NFO content.
     *
     * @param  string  $nfoContent  Raw NFO content.
     * @return string Cleaned NFO content.
     */
    public function cleanNfoContent(string $nfoContent): string
    {
        // Convert to UTF-8 if needed (CP437 is common for NFOs)
        $content = cp437toUTF($nfoContent);

        // Normalize line endings
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        // Remove excessive whitespace while preserving NFO art
        $lines = explode("\n", $content);
        $cleanedLines = [];
        $emptyLineCount = 0;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                $emptyLineCount++;
                // Allow max 2 consecutive empty lines
                if ($emptyLineCount <= 2) {
                    $cleanedLines[] = '';
                }
            } else {
                $emptyLineCount = 0;
                $cleanedLines[] = rtrim($line);
            }
        }

        return implode("\n", $cleanedLines);
    }

    /**
     * Calculate an NFO quality score based on content analysis.
     *
     * Scoring factors:
     * - Content length (too short or too long penalized)
     * - Keyword presence (scene terminology, media info)
     * - Media ID presence (IMDB, TVDB, etc.)
     * - URL presence
     * - Codec information
     * - ASCII art detection (scene NFOs often have artistic headers)
     * - Structural elements (proper formatting)
     *
     * @param  string  $nfoContent  The NFO content to analyze.
     * @return int Quality score from 0-100.
     */
    public function calculateNfoQuality(string $nfoContent): int
    {
        $score = 50; // Base score

        $length = strlen($nfoContent);

        // Length bonus/penalty
        if ($length < 100) {
            $score -= 20;
        } elseif ($length > 500 && $length < 20000) {
            $score += 15;
        } elseif ($length >= 20000) {
            $score += 5; // Longer NFOs might have too much filler
        }

        // Keyword matching
        $keywordMatches = 0;
        foreach ($this->_nfoKeywords as $keyword) {
            if (stripos($nfoContent, $keyword) !== false) {
                $keywordMatches++;
            }
        }
        $score += min($keywordMatches * 2, 20);

        // Media ID presence bonus
        $mediaIds = $this->extractAllMediaIds($nfoContent);
        if (! empty($mediaIds)) {
            $score += min(count($mediaIds) * 5, 15);
        }

        // URL presence
        $urls = $this->extractUrls($nfoContent);
        if (! empty($urls)) {
            $score += min(count($urls) * 2, 10);
        }

        // Codec info presence
        $codecInfo = $this->extractCodecInfo($nfoContent);
        $score += count(array_filter($codecInfo)) * 3;

        // ASCII art detection (scene NFOs often have decorative borders)
        if ($this->hasAsciiArt($nfoContent)) {
            $score += 10;
        }

        // Structural elements bonus
        $structuralScore = $this->analyzeStructure($nfoContent);
        $score += $structuralScore;

        // Group name detection bonus
        if ($this->extractGroupName($nfoContent) !== null) {
            $score += 8;
        }

        // Release date detection bonus
        if ($this->extractReleaseDate($nfoContent) !== null) {
            $score += 5;
        }

        // Language info bonus
        $languages = $this->extractLanguage($nfoContent);
        if (! empty($languages)) {
            $score += min(count($languages) * 2, 6);
        }

        // Runtime detection bonus
        if ($this->extractRuntime($nfoContent) !== null) {
            $score += 4;
        }

        // Penalty for binary content remnants
        if (preg_match_all('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $nfoContent, $binaryMatches)) {
            $score -= min(count($binaryMatches[0]) * 5, 20);
        }

        return max(0, min(100, $score));
    }

    /**
     * Detect ASCII art in NFO content.
     *
     * @param  string  $nfoContent  The NFO content to analyze.
     * @return bool True if ASCII art is detected.
     */
    protected function hasAsciiArt(string $nfoContent): bool
    {
        // Check for common ASCII art characters in repeated sequences
        $asciiArtPatterns = [
            // Decorative borders
            '/[-=*~#@]{5,}/',
            // Box drawing characters
            '/[┌┐└┘├┤┬┴┼│─╔╗╚╝║═]{3,}/',
            // Extended ASCII art characters
            '/[░▒▓█▄▀■□▪▫]{3,}/',
            // Common ASCII art patterns
            '/[\/\\|_]{3,}.*[\/\\|_]{3,}/',
            // Repeated special chars in artistic patterns
            '/(\S)\1{4,}/',
        ];

        foreach ($asciiArtPatterns as $pattern) {
            if (preg_match($pattern, $nfoContent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Analyze structural elements of NFO content.
     *
     * @param  string  $nfoContent  The NFO content to analyze.
     * @return int Score based on structural quality (0-15).
     */
    protected function analyzeStructure(string $nfoContent): int
    {
        $score = 0;

        // Check for section headers
        $sectionPatterns = [
            '/^[ \t]*[-=*]{2,}.*[-=*]{2,}[ \t]*$/m', // Decorative section dividers
            '/^[ \t]*\[.*\][ \t]*$/m',                 // [Section Name]
            '/^[ \t]*<.*>[ \t]*$/m',                   // <Section Name>
        ];

        foreach ($sectionPatterns as $pattern) {
            if (preg_match_all($pattern, $nfoContent, $matches)) {
                $score += min(count($matches[0]), 3);
            }
        }

        // Check for labeled fields (Field: Value format)
        if (preg_match_all('/^[ \t]*[A-Za-z][A-Za-z\s]{2,20}\s*[:\.].*$/m', $nfoContent, $matches)) {
            $score += min(count($matches[0]) / 2, 5);
        }

        // Check for consistent line endings and formatting
        $lines = explode("\n", $nfoContent);
        $nonEmptyLines = array_filter($lines, fn($line) => trim($line) !== '');

        if (count($nonEmptyLines) >= 10) {
            $score += 2;
        }

        return min(15, (int) $score);
    }

    /**
     * Decompress and retrieve NFO content from a release.
     *
     * @param  int  $releaseId  The release ID.
     * @return string|null The NFO content or null if not found.
     */
    public function getNfoContent(int $releaseId): ?string
    {
        $nfoRecord = ReleaseNfo::getReleaseNfo($releaseId);

        if ($nfoRecord === null || empty($nfoRecord->nfo)) {
            return null;
        }

        return $nfoRecord->nfo;
    }

    /**
     * Store NFO content for a release.
     *
     * @param  int  $releaseId  The release ID.
     * @param  string  $nfoContent  The NFO content to store.
     * @param  bool  $compress  Whether to compress the content.
     * @return bool True on success, false on failure.
     */
    public function storeNfoContent(int $releaseId, string $nfoContent, bool $compress = true): bool
    {
        try {
            $data = $compress ? "\x1f\x8b\x08\x00".gzcompress($nfoContent) : $nfoContent;

            ReleaseNfo::updateOrCreate(
                ['releases_id' => $releaseId],
                ['nfo' => $data]
            );

            Release::whereId($releaseId)->update(['nfostatus' => self::NFO_FOUND]);

            return true;
        } catch (Throwable $e) {
            Log::error("Failed to store NFO for release {$releaseId}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Clear the settings cache.
     *
     * Useful when settings have been updated and need to be reloaded.
     */
    public function clearSettingsCache(): void
    {
        Cache::forget('nfo_maxnfoprocessed');
        Cache::forget('nfo_maxnforetries');
        Cache::forget('nfo_maxsizetoprocessnfo');
        Cache::forget('nfo_minsizetoprocessnfo');
    }
}
