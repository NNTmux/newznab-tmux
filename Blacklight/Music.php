<?php

namespace Blacklight;

use ApaiIO\ApaiIO;
use App\Models\Genre;
use Blacklight\db\DB;
use GuzzleHttp\Client;
use App\Models\Release;
use App\Models\Category;
use App\Models\Settings;
use App\Models\MusicInfo;
use ApaiIO\Operations\Search;
use Illuminate\Support\Carbon;
use ApaiIO\Request\GuzzleRequest;
use Illuminate\Support\Facades\Cache;
use ApaiIO\Configuration\GenericConfiguration;
use ApaiIO\ResponseTransformer\XmlToSimpleXmlObject;

/**
 * Class Music.
 */
class Music
{
    protected const MATCH_PERCENT = 60;
    /**
     * @var \Blacklight\db\DB
     */
    public $pdo;

    /**
     * @var bool
     */
    public $echooutput;

    /**
     * @var array|bool|string
     */
    public $pubkey;

    /**
     * @var array|bool|string
     */
    public $privkey;

    /**
     * @var array|bool|string
     */
    public $asstag;

    /**
     * @var array|bool|int|string
     */
    public $musicqty;

    /**
     * @var array|bool|int|string
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
     * @var array
     */
    public $failCache;

    /**
     * @param array $options Class instances/ echo to CLI.
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

        $this->pdo = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());
        $this->pubkey = Settings::settingValue('APIs..amazonpubkey');
        $this->privkey = Settings::settingValue('APIs..amazonprivkey');
        $this->asstag = Settings::settingValue('APIs..amazonassociatetag');
        $this->musicqty = Settings::settingValue('..maxmusicprocessed') !== '' ? (int) Settings::settingValue('..maxmusicprocessed') : 150;
        $this->sleeptime = Settings::settingValue('..amazonsleep') !== '' ? (int) Settings::settingValue('..amazonsleep') : 1000;
        $this->imgSavePath = NN_COVERS.'music'.DS;
        $this->renamed = (int) Settings::settingValue('..lookupmusic') === 2;

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
        $album = trim(preg_replace('/\s\s+/i', ' ', $album));
        $album = trim($album);
        $words = explode(' ', $album);

        foreach ($words as $word) {
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
     * @param       $page
     * @param       $cat
     * @param array $excludedcats
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|mixed
     * @throws \Exception
     */
    public function getMusicRange($page, $cat, array $excludedcats = [])
    {
        $sql = Release::query()
            ->where('r.nzbstatus', '=', 1)
            ->where('m.title', '!=', '')
            ->where('m.cover', '=', 1);
        Releases::showPasswords($sql, true);
        if (\count($excludedcats) > 0) {
            $sql->whereNotIn('r.categories_id', $excludedcats);
        }

        if (\count($cat) > 0 && $cat[0] !== -1) {
            Category::getCategorySearch($cat, $sql, true);
        }
        $sql->select(
                [
                    'm.*',
                    'r.musicinfo_id',
                    'g.name as group_name',
                    'gn.title as genre',
                    'rn.releases_id as nfoid',
                    \Illuminate\Support\Facades\DB::raw("GROUP_CONCAT(r.id ORDER BY r.postdate DESC SEPARATOR ',') as grp_release_id"),
                    \Illuminate\Support\Facades\DB::raw("GROUP_CONCAT(r.rarinnerfilecount ORDER BY r.postdate DESC SEPARATOR ',') as grp_rarinnerfilecount"),
                    \Illuminate\Support\Facades\DB::raw("GROUP_CONCAT(r.haspreview ORDER BY r.postdate DESC SEPARATOR ',') as grp_haspreview"),
                    \Illuminate\Support\Facades\DB::raw("GROUP_CONCAT(r.passwordstatus ORDER BY r.postdate DESC SEPARATOR ',') as grp_release_password"),
                    \Illuminate\Support\Facades\DB::raw("GROUP_CONCAT(r.guid ORDER BY r.postdate DESC SEPARATOR ',') as grp_release_guid"),
                    \Illuminate\Support\Facades\DB::raw("GROUP_CONCAT(rn.releases_id ORDER BY r.postdate DESC SEPARATOR ',') as grp_release_nfoid"),
                    \Illuminate\Support\Facades\DB::raw("GROUP_CONCAT(g.name ORDER BY r.postdate DESC SEPARATOR ',') as grp_release_grpname"),
                    \Illuminate\Support\Facades\DB::raw("GROUP_CONCAT(r.searchname ORDER BY r.postdate DESC SEPARATOR '#') as grp_release_name"),
                    \Illuminate\Support\Facades\DB::raw("GROUP_CONCAT(r.postdate ORDER BY r.postdate DESC SEPARATOR ',') as grp_release_postdate"),
                    \Illuminate\Support\Facades\DB::raw("GROUP_CONCAT(r.size ORDER BY r.postdate DESC SEPARATOR ',') as grp_release_size"),
                    \Illuminate\Support\Facades\DB::raw("GROUP_CONCAT(r.totalpart ORDER BY r.postdate DESC SEPARATOR ',') as grp_release_totalparts"),
                    \Illuminate\Support\Facades\DB::raw("GROUP_CONCAT(r.comments ORDER BY r.postdate DESC SEPARATOR ',') as grp_release_comments"),
                    \Illuminate\Support\Facades\DB::raw("GROUP_CONCAT(r.grabs ORDER BY r.postdate DESC SEPARATOR ',') as grp_release_grabs"),
                    \Illuminate\Support\Facades\DB::raw("GROUP_CONCAT(df.failed ORDER BY r.postdate DESC SEPARATOR ',') as grp_release_failed"),
                ]
            )
            ->from('releases as r')
            ->leftJoin('groups as g', 'g.id', '=', 'r.groups_id')
            ->leftJoin('release_nfos as rn', 'rn.releases_id', '=', 'r.id')
            ->leftJoin('dnzb_failures as df', 'df.release_id', '=', 'r.id')
            ->join('musicinfo as m', 'm.id', '=', 'r.musicinfo_id')
            ->join('genres as gn', 'm.genres_id', '=', 'gn.id')
            ->groupBy('m.id')
            ->orderBy('r.postdate', 'desc');

        $return = Cache::get(md5($page.implode('.', $cat).implode('.', $excludedcats)));
        if ($return !== null) {
            return $return;
        }

        $return = $sql->paginate(config('nntmux.items_per_cover_page'));

        $expiresAt = Carbon::now()->addSeconds(config('nntmux.cache_expiry_long'));
        Cache::put(md5($page.implode('.', $cat).implode('.', $excludedcats)), $return, $expiresAt);

        return $return;
    }

    /**
     * @param $orderby
     *
     * @return array
     */
    public function getMusicOrder($orderby): array
    {
        $order = ($orderby === '') ? 'r.postdate' : $orderby;
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
        $browsebyArr = $this->getBrowseByOptions();
        foreach ($browsebyArr as $bbk => $bbv) {
            if (isset($_REQUEST[$bbk]) && ! empty($_REQUEST[$bbk])) {
                $bbs = stripslashes($_REQUEST[$bbk]);
                if (stripos($bbv, 'id') !== false) {
                    $browseby .= 'AND m.'.$bbv.' = '.$bbs;
                } else {
                    $browseby .= 'AND m.'.$bbv.' '.$this->pdo->likeString($bbs, true, true);
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
     * @param      $title
     * @param      $year
     * @param null $amazdata
     *
     * @return int|mixed
     * @throws \Exception
     */
    public function updateMusicInfo($title, $year, $amazdata = null)
    {
        $gen = new Genres(['Settings' => $this->pdo]);
        $ri = new ReleaseImage();
        $titlepercent = 0;

        $mus = [];
        if ($title !== '') {
            $amaz = $this->fetchAmazonProperties($title);
        } elseif ($amazdata !== null) {
            $amaz = $amazdata;
        } else {
            $amaz = false;
        }

        if (! $amaz) {
            return false;
        }

        if (isset($amaz->ItemAttributes->Title)) {
            $mus['title'] = (string) $amaz->ItemAttributes->Title;
            if (empty($mus['title'])) {
                return false;
            }
        } else {
            return false;
        }

        // Load genres.
        $defaultGenres = $gen->getGenres(Genres::MUSIC_TYPE);
        $genreassoc = [];
        foreach ($defaultGenres as $dg) {
            $genreassoc[$dg['id']] = strtolower($dg['title']);
        }

        // Get album properties.
        $mus['coverurl'] = (string) $amaz->LargeImage->URL;
        if ($mus['coverurl'] !== '') {
            $mus['cover'] = 1;
        } else {
            $mus['cover'] = 0;
        }

        $mus['asin'] = (string) $amaz->ASIN;

        $mus['url'] = (string) $amaz->DetailPageURL;
        $mus['url'] = str_replace('%26tag%3Dws', '%26tag%3Dopensourceins%2D21', $mus['url']);

        $mus['salesrank'] = (string) $amaz->SalesRank;
        if ($mus['salesrank'] === '') {
            $mus['salesrank'] = 'null';
        }

        $mus['artist'] = (string) $amaz->ItemAttributes->Artist;
        if (empty($mus['artist'])) {
            $mus['artist'] = (string) $amaz->ItemAttributes->Creator;
            if (empty($mus['artist'])) {
                $mus['artist'] = '';
            }
        }

        $mus['publisher'] = (string) $amaz->ItemAttributes->Publisher;

        $mus['releasedate'] = $this->pdo->escapeString((string) $amaz->ItemAttributes->ReleaseDate);
        if ($mus['releasedate'] === "''") {
            $mus['releasedate'] = 'null';
        }

        $mus['review'] = '';
        if (isset($amaz->EditorialReviews)) {
            $mus['review'] = trim(strip_tags((string) $amaz->EditorialReviews->EditorialReview->Content));
        }

        $mus['year'] = $year;
        if ($mus['year'] === '') {
            $mus['year'] = ($mus['releasedate'] !== 'null' ? substr($mus['releasedate'], 1, 4) : date('Y'));
        }

        $mus['tracks'] = '';
        if (isset($amaz->Tracks)) {
            $tmpTracks = (array) $amaz->Tracks->Disc;
            $tracks = $tmpTracks['Track'];
            $mus['tracks'] = (\is_array($tracks) && ! empty($tracks)) ? implode('|', $tracks) : '';
        }

        similar_text($mus['artist'].' '.$mus['title'], $title, $titlepercent);
        if ($titlepercent < 60) {
            return false;
        }

        $genreKey = -1;
        $genreName = '';
        if (isset($amaz->BrowseNodes)) {
            // Had issues getting this out of the browsenodes obj.
            // Workaround is to get the xml and load that into its own obj.
            $amazGenresXml = $amaz->BrowseNodes->asXml();
            $amazGenresObj = simplexml_load_string($amazGenresXml);
            $amazGenres = $amazGenresObj->xpath('//BrowseNodeId');

            foreach ($amazGenres as $amazGenre) {
                $currNode = trim($amazGenre[0]);
                if (empty($genreName)) {
                    $genreMatch = $this->matchBrowseNode($currNode);
                    if ($genreMatch !== false) {
                        $genreName = $genreMatch;
                        break;
                    }
                }
            }

            if (\in_array(strtolower($genreName), $genreassoc, false)) {
                $genreKey = array_search(strtolower($genreName), $genreassoc, false);
            } else {
                $genreKey = Genre::query()->insertGetId(['title' => $genreName, 'type' => Genres::MUSIC_TYPE]);
            }
        }
        $mus['musicgenre'] = $genreName;
        $mus['musicgenres_id'] = $genreKey;

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
                    'year' => $mus['year'],
                    'genres_id' => (int) $mus['musicgenres_id'] === -1 ? 'null' : $mus['musicgenres_id'],
                    'tracks' => $mus['tracks'],
                    'cover' => $mus['cover'],
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]
            );
        } else {
            $musicId = $check['id'];
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
                    'year' => $mus['year'],
                    'genres_id' => (int) $mus['musicgenres_id'] === -1 ? 'null' : $mus['musicgenres_id'],
                    'tracks' => $mus['tracks'],
                    'cover' => $mus['cover'],
                ]
            );
        }

        if ($musicId) {
            if ($this->echooutput) {
                ColorCLI::doEcho(
                    ColorCLI::header(PHP_EOL.'Added/updated album: ').
                    ColorCLI::alternateOver('   Artist: ').
                    ColorCLI::primary($mus['artist']).
                    ColorCLI::alternateOver('   Title:  ').
                    ColorCLI::primary($mus['title']).
                    ColorCLI::alternateOver('   Year:   ').
                    ColorCLI::primary($mus['year']),
                    true
                );
            }
            $mus['cover'] = $ri->saveImage($musicId, $mus['coverurl'], $this->imgSavePath, 250, 250);
        } else {
            if ($this->echooutput) {
                if ($mus['artist'] === '') {
                    $artist = '';
                } else {
                    $artist = 'Artist: '.$mus['artist'].', Album: ';
                }
                ColorCLI::doEcho(
                    ColorCLI::headerOver('Nothing to update: ').
                    ColorCLI::primaryOver(
                        $artist.
                        $mus['title'].
                        ' ('.
                        $mus['year'].
                        ')'
                    ),
                    true
                );
            }
        }

        return $musicId;
    }

    /**
     * @param $title
     *
     * @return bool|mixed
     * @throws \Exception
     */
    public function fetchAmazonProperties($title)
    {
        $responses = false;
        $conf = new GenericConfiguration();
        $client = new Client();
        $request = new GuzzleRequest($client);

        try {
            $conf
                ->setCountry('com')
                ->setAccessKey($this->pubkey)
                ->setSecretKey($this->privkey)
                ->setAssociateTag($this->asstag)
                ->setRequest($request)
                ->setResponseTransformer(new XmlToSimpleXmlObject());
        } catch (\Exception $e) {
            echo $e->getMessage();
        }

        $apaiIo = new ApaiIO($conf);
        // Try Music category.
        try {
            $search = new Search();
            $search->setCategory('Music');
            $search->setKeywords($title);
            $search->setResponseGroup(['Large']);
            $responses = $apaiIo->runOperation($search);
        } catch (\Exception $e) {
            // Empty because we try another method.
        }

        // Try MP3 category.
        if ($responses === false) {
            usleep(700000);
            try {
                $search = new Search();
                $search->setCategory('MP3Downloads');
                $search->setKeywords($title);
                $search->setResponseGroup(['Large']);
                $responses = $apaiIo->runOperation($search);
            } catch (\Exception $e) {
                // Empty because we try another method.
            }
        }

        // Try Digital Music category.
        if ($responses === false) {
            usleep(700000);
            try {
                $search = new Search();
                $search->setCategory('DigitalMusic');
                $search->setKeywords($title);
                $search->setResponseGroup(['Large']);
                $responses = $apaiIo->runOperation($search);
            } catch (\Exception $e) {
                // Empty because we try another method.
            }
        }

        // Try Music Tracks category.
        if ($responses === false) {
            usleep(700000);
            try {
                $search = new Search();
                $search->setCategory('MusicTracks');
                $search->setKeywords($title);
                $search->setResponseGroup(['Large']);
                $responses = $apaiIo->runOperation($search);
            } catch (\Exception $e) {
                // Empty because we exhausted all possibilities.
            }
        }
        if ($responses === false) {
            throw new \RuntimeException('Could not connect to Amazon');
        }
        foreach ($responses->Items->Item as $response) {
            similar_text($title, $response->ItemAttributes->Title, $percent);
            if ($percent > self::MATCH_PERCENT && isset($response->ItemAttributes->Title)) {
                return $response;
            }
        }

        return false;
    }

    /**
     * @param bool $local
     * @throws \Exception
     */
    public function processMusicReleases($local = false)
    {
        $res = Release::query()->where(['musicinfo_id' => null, 'nzbstatus' => NZB::NZB_ADDED])->when($this->renamed === true, function ($query) {
            return $query->where('isrenamed', '=', 1);
        })->whereIn('categories_id', [Category::MUSIC_MP3, Category::MUSIC_LOSSLESS, Category::MUSIC_OTHER])->orderBy('postdate', 'DESC')->limit($this->musicqty)->get(['searchname', 'id']);
        if ($res instanceof \Traversable && ! empty($res)) {
            if ($this->echooutput) {
                ColorCLI::doEcho(
                    ColorCLI::header(
                        'Processing '.$res->count().' music release(s).'
                    ),
                    true
                );
            }

            foreach ($res as $arr) {
                $startTime = microtime(true);
                $usedAmazon = false;
                $album = $this->parseArtist($arr['searchname']);
                if ($album !== false) {
                    $newname = $album['name'].' ('.$album['year'].')';

                    if ($this->echooutput) {
                        ColorCLI::doEcho(ColorCLI::headerOver('Looking up: ').ColorCLI::primary($newname), true);
                    }

                    // Do a local lookup first
                    $musicCheck = $this->getMusicInfoByName('', $album['name']);

                    if ($musicCheck === null && \in_array($album['name'].$album['year'], $this->failCache, false)) {
                        // Lookup recently failed, no point trying again
                        if ($this->echooutput) {
                            ColorCLI::doEcho(ColorCLI::headerOver('Cached previous failure. Skipping.'), true);
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
                    Release::query()->where('id', $arr['id'])->update(['musicinfo_id' => $albumId]);
                } // No album found.
                else {
                    Release::query()->where('id', $arr['id'])->update(['musicinfo_id' => -2]);
                    echo '.';
                }

                // Sleep to not flood amazon.
                $diff = floor((microtime(true) - $startTime) * 1000000);
                if ($this->sleeptime * 1000 - $diff > 0 && $usedAmazon === true) {
                    usleep($this->sleeptime * 1000 - $diff);
                }
            }

            if ($this->echooutput) {
                echo "\n";
            }
        } else {
            if ($this->echooutput) {
                ColorCLI::doEcho(ColorCLI::header('No music releases to process.'), true);
            }
        }
    }

    /**
     * @param $releasename
     *
     * @return array|bool
     */
    public function parseArtist($releasename)
    {
        if (preg_match('/(.+?)(\d{1,2} \d{1,2} )?\(?(19\d{2}|20[0-1][\d])\b/', $releasename, $name)) {
            $result = [];
            $result['year'] = $name[3];

            $a = preg_replace('/( |-)(\d{1,2} \d{1,2} )?(Bootleg|Boxset|Clean.+Version|Compiled by.+|\dCD|Digipak|DIRFIX|DVBS|FLAC|(Ltd )?(Deluxe|Limited|Special).+Edition|Promo|PROOF|Reissue|Remastered|REPACK|RETAIL(.+UK)?|SACD|Sampler|SAT|Summer.+Mag|UK.+Import|Deluxe.+Version|VINYL|WEB)/i', ' ', $name[1]);
            $b = preg_replace('/( |-)([a-z]+[\d]+[a-z]+[\d]+.+|[a-z]{2,}[\d]{2,}?.+|3FM|B00[a-z0-9]+|BRC482012|H056|UXM1DW086|(4WCD|ATL|bigFM|CDP|DST|ERE|FIM|MBZZ|MSOne|MVRD|QEDCD|RNB|SBD|SFT|ZYX)( |-)\d.+)/i', ' ', $a);
            $c = preg_replace('/( |-)(\d{1,2} \d{1,2} )?([A-Z])( ?$)|\(?[\d]{8,}\)?|( |-)(CABLE|FREEWEB|LINE|MAG|MCD|YMRSMILES)|\(([a-z]{2,}[\d]{2,}|ost)\)|-web-/i', ' ', $b);
            $d = preg_replace('/VA( |-)/', 'Various Artists ', $c);
            $e = preg_replace('/( |-)(\d{1,2} \d{1,2} )?(DAB|DE|DVBC|EP|FIX|IT|Jap|NL|PL|(Pure )?FM|SSL|VLS)( |-)/i', ' ', $d);
            $f = preg_replace('/( |-)(\d{1,2} \d{1,2} )?(CABLE|CD(A|EP|M|R|S)?|QEDCD|SAT|SBD)( |-)/i', ' ', $e);
            $g = str_replace(['_', '-'], ' ', $f);
            $h = trim(preg_replace('/\s\s+/', ' ', $g));
            $newname = trim(preg_replace('/ [a-z]{2}$| [a-z]{3} \d{2,}$|\d{5,} \d{5,}$|-WEB$/i', '', $h));

            if (! preg_match('/^[a-z0-9]+$/i', $newname) && strlen($newname) > 10) {
                $result['name'] = $newname;

                return $result;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * @param $nodeId
     *
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
}
