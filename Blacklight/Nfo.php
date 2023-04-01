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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Class Nfo.
 */
class Nfo
{
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

    /**
     * @var \Blacklight\ColorCLI
     */
    protected $colorCli;

    /**
     * Default constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $this->echo = config('nntmux.echocli');
        $this->nzbs = Settings::settingValue('..maxnfoprocessed') !== '' ? (int) Settings::settingValue('..maxnfoprocessed') : 100;
        $this->maxRetries = (int) Settings::settingValue('..maxnforetries') >= 0 ? -((int) Settings::settingValue('..maxnforetries') + 1) : self::NFO_UNPROC;
        $this->maxRetries = $this->maxRetries < -8 ? -8 : $this->maxRetries;
        $this->maxSize = (int) Settings::settingValue('..maxsizetoprocessnfo');
        $this->minSize = (int) Settings::settingValue('..minsizetoprocessnfo');
        $this->colorCli = new ColorCLI();

        $this->tmpPath = (string) Settings::settingValue('..tmpunrarpath');
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
     * @param  string|bool  $possibleNFO  The nfo.
     * @param  string  $guid  The guid of the release.
     * @return bool True on success, False on failure.
     *
     * @throws \Exception
     */
    public function isNFO(&$possibleNFO, string $guid): bool
    {
        if ($possibleNFO === false) {
            return false;
        }

        // Make sure it's not too big or small, size needs to be at least 12 bytes for header checking. Ignore common file types.
        $size = \strlen($possibleNFO);
        if ($size < 65535 &&
            $size > 11 &&
            ! preg_match(
                '/\A(\s*<\?xml|=newz\[NZB\]=|RIFF|\s*[RP]AR|.{0,10}(JFIF|matroska|ftyp|ID3))|;\s*Generated\s*by.*SF\w/i',
                $possibleNFO
            )) {
            // File/GetId3 work with files, so save to disk.
            $tmpPath = $this->tmpPath.$guid.'.nfo';
            File::put($tmpPath, $possibleNFO);

            // Linux boxes have 'file' (so should Macs), Windows *can* have it too: see GNUWIN.txt in docs.
            $result = Utility::fileInfo($tmpPath);
            if (! empty($result)) {
                // Check if it's text.
                if (preg_match('/(ASCII|ISO-8859|UTF-(8|16|32).*?)\s*text/', $result)) {
                    @File::delete($tmpPath);

                    return true;

                    // Or binary.
                }

                if (preg_match('/^(JPE?G|Parity|PNG|RAR|XML|(7-)?[Zz]ip)/', $result) || preg_match('/[\x00-\x08\x12-\x1F\x0B\x0E\x0F]/', $possibleNFO)) {
                    @File::delete($tmpPath);

                    return false;
                }
            }

            // If above checks couldn't  make a categorical identification, Use GetId3 to check if it's an image/video/rar/zip etc..
            $check = (new \getID3())->analyze($tmpPath);
            @File::delete($tmpPath);
            if (isset($check['error'])) {
                // Check if it's a par2.
                $par2info = new Par2Info();
                $par2info->setData($possibleNFO);
                if ($par2info->error) {
                    // Check if it's an SFV.
                    $sfv = new SfvInfo();
                    $sfv->setData($possibleNFO);
                    if ($sfv->error) {
                        return true;
                    }
                }
            }
        }

        return false;
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
     * @param  string  $groupID  (optional) Group ID.
     * @param  string  $guidChar  (optional) First character of the release GUID (used for multi-processing).
     * @param  int  $processImdb  (optional) Attempt to find IMDB id's in the NZB?
     * @param  int  $processTv  (optional) Attempt to find Tv id's in the NZB?
     * @return int How many NFO's were processed?
     *
     * @throws \Exception
     */
    public function processNfoFiles($nntp, string $groupID = '', string $guidChar = '', int $processImdb = 1, int $processTv = 1): int
    {
        $ret = 0;

        $qry = Release::query()
            ->where('nzbstatus', '=', NZB::NZB_ADDED)
            ->whereBetween('nfostatus', [$this->maxRetries, self::NFO_UNPROC]);

        if ($guidChar !== '') {
            $qry->where('leftguid', $guidChar);
        }
        if ($groupID !== '') {
            $qry->where('groups_id', $groupID);
        }

        if ($this->maxSize > 0) {
            $qry->where('size', '<', $this->maxSize * 1073741824);
        }

        if ($this->minSize > 0) {
            $qry->where('size', '>', $this->minSize * 1048576);
        }

        $res = $qry
            ->orderBy('nfostatus')
            ->orderByDesc('postdate')
            ->limit($this->nzbs)
            ->get(['id', 'guid', 'groups_id', 'name']);

        $nfoCount = $res->count();

        if ($nfoCount > 0) {
            $this->colorCli->primary(
                PHP_EOL.
                    ($guidChar === '' ? '' : '['.$guidChar.'] ').
                    ($groupID === '' ? '' : '['.$groupID.'] ').
                    'Processing '.$nfoCount.
                    ' NFO(s), starting at '.$this->nzbs.
                    ' * = hidden NFO, + = NFO, - = no NFO, f = download failed.'
            );

            if ($this->echo) {
                // Get count of releases per nfo status
                $qry = Release::query()
                    ->where('nzbstatus', '=', NZB::NZB_ADDED)
                    ->whereBetween('nfostatus', [$this->maxRetries, self::NFO_UNPROC])
                    ->select(['nfostatus as status', DB::raw('COUNT(id) as count')])
                    ->groupBy(['nfostatus'])
                    ->orderBy('nfostatus');

                if ($guidChar !== '') {
                    $qry->where('leftguid', $guidChar);
                }
                if ($groupID !== '') {
                    $qry->where('groups_id', $groupID);
                }

                if ($this->maxSize > 0) {
                    $qry->where('size', '<', $this->maxSize * 1073741824);
                }

                if ($this->minSize > 0) {
                    $qry->where('size', '>', $this->minSize * 1048576);
                }

                $nfoStats = $qry->get();

                if ($nfoStats instanceof \Traversable) {
                    $outString = PHP_EOL.'Available to process';
                    foreach ($nfoStats as $row) {
                        $outString .= ', '.$row['status'].' = '.number_format($row['count']);
                    }
                    $this->colorCli->header($outString.'.');
                }
            }

            $nzbContents = new NZBContents(
                [
                    'Echo' => $this->echo,
                    'NNTP' => $nntp,
                    'Nfo' => $this,
                    'Settings' => null,
                    'PostProcess' => new PostProcess(['Echo' => $this->echo, 'Nfo' => $this]),
                ]
            );
            $movie = new Movie(['Echo' => $this->echo]);

            foreach ($res as $arr) {
                $fetchedBinary = $nzbContents->getNfoFromNZB($arr['guid'], $arr['id'], $arr['groups_id'], UsenetGroup::getNameByID($arr['groups_id']));
                if ($fetchedBinary !== false) {
                    // Insert nfo into database.

                    $ckReleaseId = ReleaseNfo::whereReleasesId($arr['id'])->first(['releases_id']);
                    if ($ckReleaseId === null) {
                        ReleaseNfo::query()->insert(['releases_id' => $arr['id'], 'nfo' => "\x1f\x8b\x08\x00".gzcompress($fetchedBinary)]);
                    }
                    Release::whereId($arr['id'])->update(['nfostatus' => self::NFO_FOUND]);
                    $ret++;
                    $movie->doMovieUpdate($fetchedBinary, 'nfo', $arr['id'], $processImdb);

                    // If set scan for tv info.
                    if ($processTv === 1) {
                        (new PostProcess(['Echo' => $this->echo]))->processTv($groupID, $guidChar, $processTv);
                    }
                }
            }
        }

        // Remove nfo that we cant fetch after 5 attempts.
        $qry = Release::query()
            ->where('nzbstatus', NZB::NZB_ADDED)
            ->where('nfostatus', '<', $this->maxRetries)
            ->where('nfostatus', '>', self::NFO_FAILED);

        if ($guidChar !== '') {
            $qry->where('leftguid', $guidChar);
        }
        if ($groupID !== '') {
            $qry->where('groups_id', $groupID);
        }

        foreach ($qry->get(['id']) as $release) {
            // remove any releasenfo for failed
            ReleaseNfo::whereReleasesId($release['id'])->delete();

            // set release.nfostatus to failed
            Release::whereId($release['id'])->update(['nfostatus' => self::NFO_FAILED]);
        }

        if ($this->echo) {
            if ($nfoCount > 0) {
                echo PHP_EOL;
            }
            if ($ret > 0) {
                $this->colorCli->primary($ret.' NFO file(s) found/processed.');
            }
        }

        return $ret;
    }

    /**
     * Get a string like this:
     * "AND r.nzbstatus = 1 AND r.nfostatus BETWEEN -8 AND -1 AND r.size < 1073741824 AND r.size > 1048576"
     * To use in a query.
     *
     *
     * @throws \Exception
     *
     * @static
     */
    public static function NfoQueryString(): string
    {
        $maxSize = (int) Settings::settingValue('..maxsizetoprocessnfo');
        $minSize = (int) Settings::settingValue('..minsizetoprocessnfo');
        $dummy = (int) Settings::settingValue('..maxnforetries');
        $maxRetries = ($dummy >= 0 ? -($dummy + 1) : self::NFO_UNPROC);

        return sprintf(
            'AND r.nzbstatus = %d AND r.nfostatus BETWEEN %d AND %d %s %s',
            NZB::NZB_ADDED,
            ($maxRetries < -8 ? -8 : $maxRetries),
            self::NFO_UNPROC,
            ($maxSize > 0 ? ('AND r.size < '.($maxSize * 1073741824)) : ''),
            ($minSize > 0 ? ('AND r.size > '.($minSize * 1048576)) : '')
        );
    }
}
