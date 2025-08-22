<?php

namespace Blacklight;

use App\Models\Release;
use App\Models\ReleaseNfo;
use App\Models\Settings;
use App\Models\UsenetGroup;
use Blacklight\processing\PostProcess;
use Blacklight\utility\Utility;
use dariusiii\rarinfo\Par2Info;
use dariusiii\rarinfo\SfvInfo;
use getID3;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Class Nfo.
 */
class Nfo
{
    /**
     * Regex to detect common non-NFO file headers/signatures.
     */
    protected string $_nonNfoHeaderRegex = '/\A(\s*<\?xml|=newz\[NZB\]=|RIFF|\s*[RP]AR|.{0,10}(JFIF|matroska|ftyp|ID3))|;\s*Generated\s*by.*SF\w/i';

    /**
     * Regex to identify text encoding from the 'file' command output.
     */
    protected string $_textFileRegex = '/(ASCII|ISO-8859|UTF-(8|16|32).*?)\s*text/';

    /**
     * Regex to identify common binary file types from the 'file' command output.
     */
    protected string $_binaryFileRegex = '/^(JPE?G|Parity|PNG|RAR|XML|(7-)?[Zz]ip)/';

    /**
     * Regex to detect binary characters within the content.
     */
    protected string $_binaryCharsRegex = '/[\x00-\x08\x12-\x1F\x0B\x0E\x0F]/';

    /**
     * @var int
     */
    private $nzbs;

    /**
     * @var int
     */
    protected $maxSize;

    /**
     * @var int
     */
    private $maxRetries;

    /**
     * @var int
     */
    protected $minSize;

    /**
     * @var string
     */
    private $tmpPath;

    /**
     * @var bool
     */
    protected $echo;

    public const NFO_FAILED = -9; // We failed to get a NFO after admin set max retries.

    public const NFO_UNPROC = -1; // Release has not been processed yet.

    public const NFO_NONFO = 0; // Release has no NFO.

    public const NFO_FOUND = 1; // Release has an NFO.

    protected ColorCLI $colorCli;

    /**
     * Default constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $this->echo = config('nntmux.echocli');
        $this->nzbs = Settings::settingValue('maxnfoprocessed') !== '' ? (int) Settings::settingValue('maxnfoprocessed') : 100;
        $this->maxRetries = (int) Settings::settingValue('maxnforetries') >= 0 ? -((int) Settings::settingValue('maxnforetries') + 1) : self::NFO_UNPROC;
        $this->maxRetries = $this->maxRetries < -8 ? -8 : $this->maxRetries;
        $this->maxSize = (int) Settings::settingValue('maxsizetoprocessnfo');
        $this->minSize = (int) Settings::settingValue('minsizetoprocessnfo');
        $this->colorCli = new ColorCLI;

        $this->tmpPath = config('nntmux.tmp_unrar_path');
        if (! preg_match('/[\/\\\\]$/', $this->tmpPath)) {
            $this->tmpPath .= '/';
        }
    }

    /**
     * Look for a TV Show ID in a string.
     *
     * @param  string  $str  The string with a Show ID.
     * @return array|false Return array with show ID and site source or false on failure.
     */
    public function parseShowId(string $str)
    {
        $return = false;

        if (preg_match('/tvmaze\.com\/shows\/(\d{1,6})/i', $str, $hits)) {
            $return =
            [
                'showid' => trim($hits[1]),
                'site' => 'tvmaze',
            ];
        }

        if (preg_match('/imdb\.com\/title\/(tt\d{1,8})/i', $str, $hits)) {
            $return =
                [
                    'showid' => trim($hits[1]),
                    'site' => 'imdb',
                ];
        }

        if (preg_match('/thetvdb\.com\/\?tab=series&id=(\d{1,8})/i', $str, $hits)) {
            $return =
                [
                    'showid' => trim($hits[1]),
                    'site' => 'thetvdb',
                ];
        }

        return $return;
    }

    /**
     * Confirm this is an NFO file.
     *
     * @param  bool|string  $possibleNFO  The nfo content.
     * @param  string  $guid  The guid of the release.
     * @return bool True if it's likely an NFO, False otherwise.
     */
    public function isNFO(bool|string &$possibleNFO, string $guid): bool
    {
        if ($possibleNFO === false) {
            return false;
        }

        $size = \strlen($possibleNFO);

        // Basic size and signature checks
        if ($size >= 65535 || $size < 12 || preg_match($this->_nonNfoHeaderRegex, $possibleNFO)) {
            return false;
        }

        $tmpPath = $this->tmpPath.$guid.'.nfo';
        $isNfo = false; // Default assumption

        try {
            // File/GetId3 work with files, so save to disk.
            File::put($tmpPath, $possibleNFO);

            // Use 'file' command via Utility::fileInfo if available
            $result = Utility::fileInfo($tmpPath);
            if (! empty($result)) {
                if (preg_match($this->_textFileRegex, $result)) {
                    $isNfo = true; // It's text, likely NFO
                } elseif (preg_match($this->_binaryFileRegex, $result) || preg_match($this->_binaryCharsRegex, $possibleNFO)) {
                    $isNfo = false; // Detected binary format or characters
                }

                // If fileInfo gave a result, trust it and return
                return $isNfo;
            }

            // Fallback checks if 'file' command is unavailable or inconclusive
            // Check if it's a par2.
            $par2info = new Par2Info;
            $par2info->setData($possibleNFO);
            if (! $par2info->error) {
                // It's a PAR2 file
                return false;
            }

            // Check if it's an SFV.
            $sfv = new SfvInfo;
            $sfv->setData($possibleNFO);
            if (! $sfv->error) {
                // It's an SFV file
                return false;
            }

            // If it wasn't identified as a known non-NFO binary type by fileInfo,
            // and isn't PAR2 or SFV, assume it might be NFO (especially if fileInfo failed).
            // Further checks (like binary char check) could be added here if needed.
            $isNfo = ! preg_match($this->_binaryCharsRegex, $possibleNFO);

        } catch (Throwable $e) {
            // Log errors during file operations
            Log::error("Error processing potential NFO for GUID {$guid}: ".$e->getMessage());
            $isNfo = false; // Treat errors as non-NFO
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
     * Add an NFO from alternate sources. ex.: PreDB, rar, zip, etc...
     *
     * @param  string  $nfo  The nfo.
     * @param  NNTP  $nntp  Instance of class NNTP.
     * @return bool True on success, False on failure.
     *
     * @throws \Exception
     */
    public function addAlternateNfo(string &$nfo, $release, NNTP $nntp): bool
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
                        'PostProcess' => new PostProcess(['Echo' => $this->echo, 'Nfo' => $this]),
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
     * @param  NNTP  $nntp  The NNTP connection object
     * @param  string  $groupID  (optional) Group ID to filter releases by
     * @param  string  $guidChar  (optional) First character of the GUID for parallel processing
     * @param  bool  $processImdb  (optional) Process IMDB IDs (currently unused)
     * @param  bool  $processTv  (optional) Process TV IDs (currently unused)
     * @return int Count of successfully processed NFO files
     *
     * @throws \Exception If NNTP operations fail
     */
    public function processNfoFiles(NNTP $nntp, string $groupID = '', string $guidChar = '', bool $processImdb = true, bool $processTv = true): int
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
}
