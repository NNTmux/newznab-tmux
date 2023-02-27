<?php

namespace Blacklight;

use App\Models\Category;
use App\Models\Genre;
use App\Models\MusicInfo;
use App\Models\Release;
use App\Models\Settings;
use DariusIII\ItunesApi\Exceptions\AlbumNotFoundException;
use DariusIII\ItunesApi\Exceptions\ArtistNotFoundException;
use DariusIII\ItunesApi\Exceptions\SearchNoResultsException;
use DariusIII\ItunesApi\Exceptions\TrackNotFoundException;
use DariusIII\ItunesApi\iTunes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Class Music.
 */
class Music
{
    protected const MATCH_PERCENT = 85;

    /**
     * @var bool
     */
    public $echooutput;

    /**
     * @var null|string
     */
    public $pubkey;

    /**
     * @var null|string
     */
    public $privkey;

    /**
     * @var null|string
     */
    public $asstag;

    /**
     * @var int
     */
    public $musicqty;

    /**
     * @var int
     */
    public $sleeptime;

    /**
     * @var string
     */
    public $imgSavePath;

    /**
     * @var bool
     */
    public $renamed;

    /**
     * Store names of failed Amazon lookup items.
     *
     * @var array
     */
    public $failCache;

    /**
     * @var \Blacklight\ColorCLI
     */
    protected $colorCli;

    /**
     * @param  array  $options  Class instances/ echo to CLI.
     *
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Echo'     => false,
            'Settings' => null,
        ];
        $options += $defaults;

        $this->echooutput = ($options['Echo'] && config('nntmux.echocli'));

        $this->colorCli = new ColorCLI();

        $this->pubkey = Settings::settingValue('APIs..amazonpubkey');
        $this->privkey = Settings::settingValue('APIs..amazonprivkey');
        $this->asstag = Settings::settingValue('APIs..amazonassociatetag');
        $this->musicqty = Settings::settingValue('..maxmusicprocessed') !== '' ? (int) Settings::settingValue('..maxmusicprocessed') : 150;
        $this->sleeptime = Settings::settingValue('..amazonsleep') !== '' ? (int) Settings::settingValue('..amazonsleep') : 1000;
        $this->imgSavePath = NN_COVERS.'music'.DS;
        $this->renamed = (int) Settings::settingValue('..lookupmusic') === 2 ? 'AND isrenamed = 1' : '';

        $this->failCache = [];
    }

    /**
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getMusicInfo($id)
    {
        return MusicInfo::query()->with('genre')->where('id', $id)->first();
    }

    /**
     * @param $artist
     * @param $album
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getMusicInfoByName($artist, $album)
    {
        //only used to get a count of words
        $searchwords = '';
        $album = preg_replace('/( - | -|\(.+\)|\(|\))/', ' ', $album);
        $album = preg_replace('/[^\w ]+/', '', $album);
        $album = preg_replace('/(WEB|FLAC|CD)/', '', $album);
        $album = trim(trim(preg_replace('/\s\s+/i', ' ', $album)));
        foreach (explode(' ', $album) as $word) {
            $word = trim(rtrim(trim($word), '-'));
            if ($word !== '' && $word !== '-') {
                $word = '+'.$word;
                $searchwords .= sprintf('%s ', $word);
            }
        }
        $searchwords = trim($searchwords);

        return MusicInfo::search($searchwords)->first();
    }

    /**
     * @param $page
     * @param $cat
     * @param $start
     * @param $num
     * @param $orderBy
     * @param array $excludedCats
     * @return \Illuminate\Cache\|\Illuminate\Database\Eloquent\Collection|mixed
     */
    public function getMusicRange($page, $cat, $start, $num, $orderBy, array $excludedCats = []): mixed
    {
        $browseby = $this->getBrowseBy();
        $catsrch = '';
        if (\count($cat) > 0 && (int) $cat[0] !== -1) {
            $catsrch = Category::getCategorySearch($cat);
        }
        $exccatlist = '';
        if (\count($excludedCats) > 0) {
            $exccatlist = ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')';
        }
        $order = $this->getMusicOrder($orderBy);
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        $musicSql =
            sprintf(
                "
				SELECT SQL_CALC_FOUND_ROWS
					m.id,
					GROUP_CONCAT(r.id ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_id
				FROM musicinfo m
				LEFT JOIN releases r ON r.musicinfo_id = m.id
				WHERE r.nzbstatus = 1
				AND m.title != ''
				AND m.cover = 1
				AND r.passwordstatus %s
				%s %s %s
				GROUP BY m.id
				ORDER BY %s %s %s",
                (new Releases())->showPasswords(),
                $browseby,
                $catsrch,
                $exccatlist,
                $order[0],
                $order[1],
                ($start === false ? '' : ' LIMIT '.$num.' OFFSET '.$start)
            );
        $musicCache = Cache::get(md5($musicSql.$page));
        if ($musicCache !== null) {
            $music = $musicCache;
        } else {
            $data = DB::select($musicSql);
            $music = ['total' => DB::select('SELECT FOUND_ROWS() AS total'), 'result' => $data];
            Cache::put(md5($musicSql.$page), $music, $expiresAt);
        }
        $musicIDs = $releaseIDs = [];
        if (\is_array($music['result'])) {
            foreach ($music['result'] as $mus => $id) {
                $musicIDs[] = $id->id;
                $releaseIDs[] = $id->grp_release_id;
            }
        }
        if (empty($musicIDs) && empty($releaseIDs)) {
            return collect();
        }
        $sql = sprintf(
            '
			SELECT
				r.id, r.rarinnerfilecount, r.grabs, r.comments, r.totalpart, r.size, r.postdate, r.searchname, r.haspreview, r.passwordstatus, r.guid, df.failed AS failed,
				m.*,
				r.musicinfo_id, r.haspreview,
				g.name AS group_name,
				rn.releases_id AS nfoid
			FROM releases r
			LEFT OUTER JOIN usenet_groups g ON g.id = r.groups_id
			LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
			LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
			INNER JOIN musicinfo m ON m.id = r.musicinfo_id
			%s %s %s
			GROUP BY m.id
			ORDER BY %s %s',
            ! empty($musicIDs) ? 'WHERE m.id IN ('.implode(',', $musicIDs).')' : 'AND 1=1',
            (! empty($releaseIDs)) ? 'AND r.id in ('. implode(',', $releaseIDs).')' : '',
            $catsrch,
            $order[0],
            $order[1]
        );
        $return = Cache::get(md5($sql.$page));
        if ($return !== null) {
            return $return;
        }
        $return = MusicInfo::fromQuery($sql);
        if ($return->isNotEmpty()) {
            $return[0]->_totalcount = $music['total'][0]->total ?? 0;
        }
        Cache::put(md5($sql.$page), $return, $expiresAt);

        return $return;
    }

    /**
     * @param $orderBy
     * @return array
     */
    public function getMusicOrder($orderBy): array
    {
        $order = ($orderBy === '') ? 'r.postdate' : $orderBy;
        $orderArr = explode('_', $order);
        switch ($orderArr[0]) {
            case 'artist':
                $orderfield = 'm.artist';
                break;
            case 'size':
                $orderfield = 'r.size';
                break;
            case 'files':
                $orderfield = 'r.totalpart';
                break;
            case 'stats':
                $orderfield = 'r.grabs';
                break;
            case 'year':
                $orderfield = 'm.year';
                break;
            case 'genre':
                $orderfield = 'm.genres_id';
                break;
            case 'posted':
            default:
                $orderfield = 'r.postdate';
                break;
        }
        $ordersort = (isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1])) ? $orderArr[1] : 'desc';

        return [$orderfield, $ordersort];
    }

    /**
     * @return array
     */
    public function getMusicOrdering(): array
    {
        return ['artist_asc', 'artist_desc', 'posted_asc', 'posted_desc', 'size_asc', 'size_desc', 'files_asc', 'files_desc', 'stats_asc', 'stats_desc', 'year_asc', 'year_desc', 'genre_asc', 'genre_desc'];
    }

    /**
     * @return array
     */
    public function getBrowseByOptions(): array
    {
        return ['artist' => 'artist', 'title' => 'title', 'genre' => 'genres_id', 'year' => 'year'];
    }

    /**
     * @return string
     */
    public function getBrowseBy(): string
    {
        $browseby = ' ';
        foreach ($this->getBrowseByOptions() as $bbk => $bbv) {
            if (! empty($_REQUEST[$bbk])) {
                $bbs = stripslashes($_REQUEST[$bbk]);
                if (stripos($bbv, 'id') !== false) {
                    $browseby .= ' AND m.'.$bbv.' = '.$bbs;
                } else {
                    $browseby .= ' AND m.'.$bbv.' '.'LIKE '.escapeString('%'.$bbs.'%');
                }
            }
        }

        return $browseby;
    }

    /**
     * @param $id
     * @param $title
     * @param $asin
     * @param $url
     * @param $salesrank
     * @param $artist
     * @param $publisher
     * @param $releasedate
     * @param $year
     * @param $tracks
     * @param $cover
     * @param $genres_id
     */
    public function update($id, $title, $asin, $url, $salesrank, $artist, $publisher, $releasedate, $year, $tracks, $cover, $genres_id): void
    {
        MusicInfo::query()->where('id', $id)->update(
            [
                'title' => $title,
                'asin' => $asin,
                'url' => $url,
                'salesrank' => $salesrank,
                'artist' => $artist,
                'publisher' => $publisher,
                'releasedate' => $releasedate,
                'year' => $year,
                'tracks' => $tracks,
                'cover' => $cover,
                'genres_id' => $genres_id,
            ]
        );
    }

    /**
     * @param    $title
     * @param    $year
     * @param  null  $amazdata
     * @return int|mixed
     *
     * @throws \Exception
     */
    public function updateMusicInfo($title, $year, $amazdata = null)
    {
        $ri = new ReleaseImage();

        $mus = [];
        if ($amazdata !== null) {
            $mus = $amazdata;
        } elseif ($title !== '') {
            $mus = $this->fetchItunesMusicProperties($title);
        }

        if ($mus === false) {
            return false;
        }

        $check = MusicInfo::query()->where('asin', $mus['asin'])->first(['id']);
        if ($check === null) {
            $musicId = MusicInfo::query()->insertGetId(
                [
                    'title' => $mus['title'],
                    'asin' =>$mus['asin'],
                    'url' => $mus['url'],
                    'salesrank' => $mus['salesrank'],
                    'artist' => $mus['artist'],
                    'publisher' => $mus['publisher'],
                    'releasedate' => $mus['releasedate'],
                    'review' => $mus['review'],
                    'year' => $year,
                    'genres_id' => (int) $mus['musicgenres_id'] === -1 ? 'null' : $mus['musicgenres_id'],
                    'tracks' => $mus['tracks'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $mus['cover'] = $ri->saveImage($musicId, $mus['coverurl'], $this->imgSavePath, 250, 250);
            MusicInfo::query()->where('id', $musicId)->update(['cover' => $mus['cover']]);
        } else {
            $musicId = $check['id'];
            $mus['cover'] = $ri->saveImage($musicId, $mus['coverurl'], $this->imgSavePath, 250, 250);
            MusicInfo::query()->where('id', $musicId)->update(
                [
                    'title' => $mus['title'],
                    'asin' => $mus['asin'],
                    'url' => $mus['url'],
                    'salesrank' => $mus['salesrank'],
                    'artist' => $mus['artist'],
                    'publisher' => $mus['publisher'],
                    'releasedate' => $mus['releasedate'],
                    'review' => $mus['review'],
                    'year' => $year,
                    'genres_id' => (int) $mus['musicgenres_id'] === -1 ? 'null' : $mus['musicgenres_id'],
                    'tracks' => $mus['tracks'],
                    'cover' => $mus['cover'],
                ]
            );
        }

        if ($musicId) {
            if ($this->echooutput) {
                $this->colorCli->header(
                    PHP_EOL.'Added/updated album: '.PHP_EOL.
                    '   Artist: '.
                    $mus['artist'].PHP_EOL.
                    '   Title:  '.
                    $mus['title'].PHP_EOL.
                    '   Year:   '.
                    $year
                );
            }
            $mus['cover'] = $ri->saveImage($musicId, $mus['coverurl'], $this->imgSavePath, 250, 250);
        } elseif ($this->echooutput) {
            if ($mus['artist'] === '') {
                $artist = '';
            } else {
                $artist = 'Artist: '.$mus['artist'].', Album: ';
            }

            $this->colorCli->headerOver(
                'Nothing to update: '.
                    $artist.
                    $mus['title'].
                    ' ('.
                    $year.
                    ')'
            );
        }

        return $musicId;
    }

    /**
     * @param  bool  $local
     *
     * @throws \Exception
     */
    public function processMusicReleases($local = false)
    {
        $res = DB::select(
            sprintf(
                '
					SELECT searchname, id
					FROM releases
					WHERE musicinfo_id IS NULL
					AND nzbstatus = %d %s
					AND categories_id IN (%s, %s, %s)
					ORDER BY postdate DESC
					LIMIT %d',
                NZB::NZB_ADDED,
                $this->renamed,
                Category::MUSIC_MP3,
                Category::MUSIC_LOSSLESS,
                Category::MUSIC_OTHER,
                $this->musicqty
        )
        );

        if (! empty($res)) {
            foreach ($res as $arr) {
                $startTime = now();
                $usedAmazon = false;
                $album = $this->parseArtist($arr->searchname);
                if ($album !== false) {
                    $newname = $album['name'].' ('.$album['year'].')';

                    if ($this->echooutput) {
                        $this->colorCli->header('Looking up: '.$newname);
                    }

                    // Do a local lookup first
                    $musicCheck = $this->getMusicInfoByName('', $album['name']);

                    if ($musicCheck === null && \in_array($album['name'].$album['year'], $this->failCache, false)) {
                        // Lookup recently failed, no point trying again
                        if ($this->echooutput) {
                            $this->colorCli->headerOver('Cached previous failure. Skipping.');
                        }
                        $albumId = -2;
                    } elseif ($musicCheck === null && $local === false) {
                        $albumId = $this->updateMusicInfo($album['name'], $album['year']);
                        $usedAmazon = true;
                        if ($albumId === false) {
                            $albumId = -2;
                            $this->failCache[] = $album['name'].$album['year'];
                        }
                    } else {
                        $albumId = $musicCheck['id'];
                    }
                    Release::query()->where('id', $arr->id)->update(['musicinfo_id' => $albumId]);
                } // No album found.
                else {
                    Release::query()->where('id', $arr->id)->update(['musicinfo_id' => -2]);
                    echo '.';
                }

                // Sleep to not flood amazon.
                $sleeptime = $this->sleeptime / 1000;
                $diff = now()->diffInSeconds($startTime);
                if ($sleeptime - $diff > 0 && $usedAmazon === true) {
                    sleep($sleeptime - $diff);
                }
            }

            if ($this->echooutput) {
                echo PHP_EOL;
            }
        } elseif ($this->echooutput) {
            $this->colorCli->header('No music releases to process.');
        }
    }

    /**
     * @param  string  $releaseName
     * @return array|false
     */
    public function parseArtist($releaseName)
    {
        if (preg_match('/(.+?)(\d{1,2} \d{1,2} )?\(?(19\d{2}|20[0-1][\d])\b/', $releaseName, $name)) {
            $result = [];
            $result['year'] = $name[3];

            $a = preg_replace('/([ |-])(\d{1,2} \d{1,2} )?(Bootleg|Boxset|Clean.+Version|Compiled by.+|\dCD|Digipak|DIRFIX|DVBS|FLAC|(Ltd )?(Deluxe|Limited|Special).+Edition|Promo|PROOF|Reissue|Remastered|REPACK|RETAIL(.+UK)?|SACD|Sampler|SAT|Summer.+Mag|UK.+Import|Deluxe.+Version|VINYL|WEB)/i', ' ', $name[1]);
            $b = preg_replace('/([ |-])([a-z]+[\d]+[a-z]+[\d]+.+|[a-z]{2,}[\d]{2,}?.+|3FM|B00[a-z0-9]+|BRC482012|H056|UXM1DW086|(4WCD|ATL|bigFM|CDP|DST|ERE|FIM|MBZZ|MSOne|MVRD|QEDCD|RNB|SBD|SFT|ZYX)([ |-])\d.+)/i', ' ', $a);
            $c = preg_replace('/([ |-])(\d{1,2} \d{1,2} )?([A-Z])( ?$)|\(?[\d]{8,}\)?|([ |-])(CABLE|FREEWEB|LINE|MAG|MCD|YMRSMILES)|\(([a-z]{2,}[\d]{2,}|ost)\)|-web-/i', ' ', $b);
            $d = preg_replace('/VA([ |-])/', 'Various Artists ', $c);
            $e = preg_replace('/([ |-])(\d{1,2} \d{1,2} )?(DAB|DE|DVBC|EP|FIX|IT|Jap|NL|PL|(Pure )?FM|SSL|VLS)([ |-])/i', ' ', $d);
            $f = preg_replace('/([ |-])(\d{1,2} \d{1,2} )?(CABLE|CD(A|EP|M|R|S)?|QEDCD|SAT|SBD)([ |-])/i', ' ', $e);
            $g = str_replace(['_', '-'], ' ', $f);
            $h = trim(preg_replace('/\s\s+/', ' ', $g));
            $newname = trim(preg_replace('/ [a-z]{2}$| [a-z]{3} \d{2,}$|\d{5,} \d{5,}$|-WEB$/i', '', $h));

            if (! preg_match('/^[a-z0-9]+$/i', $newname) && strlen($newname) > 10) {
                $result['name'] = $newname;

                return $result;
            }

            return false;
        }

        return false;
    }

    /**
     * @param $nodeId
     * @return bool|string
     */
    public function matchBrowseNode($nodeId)
    {
        $str = '';

        //music nodes above mp3 download nodes
        switch ($nodeId) {
            case '163420':
                $str = 'Music Video & Concerts';
                break;
            case '30':
            case '624869011':
                $str = 'Alternative Rock';
                break;
            case '31':
            case '624881011':
                $str = 'Blues';
                break;
            case '265640':
            case '624894011':
                $str = 'Broadway & Vocalists';
                break;
            case '173425':
            case '624899011':
                $str = "Children's Music";
                break;
            case '173429': //christian
            case '2231705011': //gospel
            case '624905011': //christian & gospel
                $str = 'Christian & Gospel';
                break;
            case '67204':
            case '624916011':
                $str = 'Classic Rock';
                break;
            case '85':
            case '624926011':
                $str = 'Classical';
                break;
            case '16':
            case '624976011':
                $str = 'Country';
                break;
            case '7': //dance & electronic
            case '624988011': //dance & dj
                $str = 'Dance & Electronic';
                break;
            case '32':
            case '625003011':
                $str = 'Folk';
                break;
            case '67207':
            case '625011011':
                $str = 'Hard Rock & Metal';
                break;
            case '33': //world music
            case '625021011': //international
                $str = 'World Music';
                break;
            case '34':
            case '625036011':
                $str = 'Jazz';
                break;
            case '289122':
            case '625054011':
                $str = 'Latin Music';
                break;
            case '36':
            case '625070011':
                $str = 'New Age';
                break;
            case '625075011':
                $str = 'Opera & Vocal';
                break;
            case '37':
            case '625092011':
                $str = 'Pop';
                break;
            case '39':
            case '625105011':
                $str = 'R&B';
                break;
            case '38':
            case '625117011':
                $str = 'Rap & Hip-Hop';
                break;
            case '40':
            case '625129011':
                $str = 'Rock';
                break;
            case '42':
            case '625144011':
                $str = 'Soundtracks';
                break;
            case '35':
            case '625061011':
                $str = 'Miscellaneous';
                break;
        }

        return ($str !== '') ? $str : false;
    }

    /**
     * @param  string  $title
     * @return array|bool
     *
     * @throws \DariusIII\ItunesApi\Exceptions\InvalidProviderException
     * @throws \Exception
     */
    protected function fetchItunesMusicProperties($title)
    {
        $mus = true;
        // Load genres.
        $defaultGenres = (new Genres())->loadGenres(Genres::MUSIC_TYPE);

        try {
            $album = iTunes::load('album')->fetchOneByName($title);
            $track = iTunes::load('track')->fetchOneByName($title);
            $artist = iTunes::load('artist')->fetchById($track->getArtistId());
            $genreName = $album->getGenre();
        } catch (AlbumNotFoundException $e) {
            try {
                $track = iTunes::load('track')->fetchOneByName($title);
                $album = iTunes::load('album')->fetchById($track->getAlbumId());
                $artist = iTunes::load('artist')->fetchById($track->getArtistId());
                $genreName = $album->getGenre();
            } catch (TrackNotFoundException $e) {
                $mus = false;
            } catch (SearchNoResultsException $e) {
                $mus = false;
            } catch (ArtistNotFoundException $e) {
                $mus = false;
            }
        } catch (SearchNoResultsException $e) {
            $mus = false;
        } catch (ArtistNotFoundException $e) {
            $mus = false;
        }
        if ($mus !== false) {
            if (\in_array(strtolower($genreName), $defaultGenres, false)) {
                $genreKey = array_search(strtolower($genreName), $defaultGenres, false);
            } else {
                $genreKey = Genre::query()->insertGetId(['title' => $genreName, 'type' => Genres::MUSIC_TYPE]);
            }
            $mus = [
                'title' => $album->getName(),
                'asin' => $album->getItunesId(),
                'url' => $album->getStoreUrl(),
                'salesrank' => '',
                'artist' => $artist->getName(),
                'publisher' => $album->getPublisher(),
                'releasedate' => $album->getReleaseDate(),
                'review' => '',
                'coverurl' => str_replace('100x100', '800x800', $album->getCover()),
                'tracks' => '',
                'musicgenre' => $genreName,
                'musicgenres_id' => $genreKey,
            ];
        }

        return $mus;
    }
}
