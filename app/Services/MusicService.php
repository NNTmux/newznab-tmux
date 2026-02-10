<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Genre;
use App\Models\MusicInfo;
use App\Models\Release;
use App\Models\Settings;
use App\Services\Releases\ReleaseBrowseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Music Service - Handles music browsing and lookup operations.
 */
class MusicService
{
    protected const MATCH_PERCENT = 85;

    public bool $echooutput;

    public ?string $pubkey;

    public ?string $privkey;

    public ?string $asstag;

    public int $musicqty;

    public int $sleeptime;

    public string $imgSavePath;

    public mixed $renamed;

    /**
     * Store names of failed lookup items.
     */
    public array $failCache;

    public function __construct()
    {
        $this->echooutput = config('nntmux.echocli');

        $this->pubkey = Settings::settingValue('amazonpubkey');
        $this->privkey = Settings::settingValue('amazonprivkey');
        $this->asstag = Settings::settingValue('amazonassociatetag');
        $this->musicqty = Settings::settingValue('maxmusicprocessed') !== '' ? (int) Settings::settingValue('maxmusicprocessed') : 150;
        $this->sleeptime = Settings::settingValue('amazonsleep') !== '' ? (int) Settings::settingValue('amazonsleep') : 1000;
        $this->imgSavePath = config('nntmux_settings.covers_path').'/music/';
        $this->renamed = (int) Settings::settingValue('lookupmusic') === 2 ? 'AND isrenamed = 1' : '';

        $this->failCache = [];
    }

    /**
     * Get music info by ID.
     */
    public function getMusicInfo(int $id): ?MusicInfo
    {
        return MusicInfo::query()->with('genre')->where('id', $id)->first();
    }

    /**
     * Get music info by name using full-text search.
     */
    public function getMusicInfoByName(string $artist, string $album): ?MusicInfo
    {
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
     * Get paginated music range for browsing.
     */
    public function getMusicRange(int $page, array $cat, int $start, int $num, string $orderBy, array $excludedCats = []): mixed
    {
        $page = max(1, $page);
        $start = max(0, $start);

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

        $releaseBrowseService = new ReleaseBrowseService;
        $passwordClause = $releaseBrowseService->showPasswords();

        $musicSql = sprintf(
            "
            SELECT SQL_CALC_FOUND_ROWS
                m.id,
                GROUP_CONCAT(r.id ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_id
            FROM musicinfo m
            LEFT JOIN releases r ON r.musicinfo_id = m.id
            WHERE m.title != ''
            AND m.cover = 1
            AND r.passwordstatus %s
            %s %s %s
            GROUP BY m.id
            ORDER BY %s %s %s",
            $passwordClause,
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
            "
            SELECT
                GROUP_CONCAT(r.id ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_id,
                GROUP_CONCAT(r.rarinnerfilecount ORDER BY r.postdate DESC SEPARATOR ',') AS grp_rarinnerfilecount,
                GROUP_CONCAT(r.haspreview ORDER BY r.postdate DESC SEPARATOR ',') AS grp_haspreview,
                GROUP_CONCAT(r.passwordstatus ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_password,
                GROUP_CONCAT(r.guid ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_guid,
                GROUP_CONCAT(rn.releases_id ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_nfoid,
                GROUP_CONCAT(g.name ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_grpname,
                GROUP_CONCAT(r.searchname ORDER BY r.postdate DESC SEPARATOR '#') AS grp_release_name,
                GROUP_CONCAT(r.postdate ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_postdate,
                GROUP_CONCAT(r.adddate ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_adddate,
                GROUP_CONCAT(r.size ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_size,
                GROUP_CONCAT(r.totalpart ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_totalparts,
                GROUP_CONCAT(r.comments ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_comments,
                GROUP_CONCAT(r.grabs ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_grabs,
                GROUP_CONCAT(df.failed ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_failed,
                GROUP_CONCAT(cp.title, ' > ', c.title ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_catname,
                m.*,
                g.name AS group_name,
                rn.releases_id AS nfoid
            FROM releases r
            LEFT OUTER JOIN usenet_groups g ON g.id = r.groups_id
            LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
            LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
            LEFT OUTER JOIN categories c ON c.id = r.categories_id
            LEFT OUTER JOIN root_categories cp ON cp.id = c.root_categories_id
            INNER JOIN musicinfo m ON m.id = r.musicinfo_id
            %s %s %s
            GROUP BY m.id
            ORDER BY %s %s",
            ! empty($musicIDs) ? 'WHERE m.id IN ('.implode(',', $musicIDs).')' : 'AND 1=1',
            (! empty($releaseIDs)) ? 'AND r.id in ('.implode(',', $releaseIDs).')' : '',
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
     * Parse order by parameter and return order field and direction.
     */
    public function getMusicOrder(string $orderBy): array
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
     * Get available ordering options.
     */
    public function getMusicOrdering(): array
    {
        return [
            'artist_asc', 'artist_desc',
            'posted_asc', 'posted_desc',
            'size_asc', 'size_desc',
            'files_asc', 'files_desc',
            'stats_asc', 'stats_desc',
            'year_asc', 'year_desc',
            'genre_asc', 'genre_desc',
        ];
    }

    /**
     * Get browse by options.
     */
    public function getBrowseByOptions(): array
    {
        return ['artist' => 'artist', 'title' => 'title', 'genre' => 'genres_id', 'year' => 'year'];
    }

    /**
     * Build browse by SQL clause.
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
     * Update music info record.
     */
    public function update(
        int $id,
        string $title,
        ?string $asin,
        ?string $url,
        ?int $salesrank,
        ?string $artist,
        ?string $publisher,
        ?string $releasedate,
        ?string $year,
        ?string $tracks,
        int $cover,
        ?int $genres_id
    ): void {
        MusicInfo::query()->where('id', $id)->update([
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
        ]);
    }

    /**
     * Update or create music info from external data.
     *
     * @throws \Exception
     */
    public function updateMusicInfo(string $title, string $year, ?array $amazdata = null): int|false
    {
        $ri = new ReleaseImageService;

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
            $musicId = MusicInfo::query()->insertGetId([
                'title' => $mus['title'],
                'asin' => $mus['asin'],
                'url' => $mus['url'],
                'salesrank' => $mus['salesrank'],
                'artist' => $mus['artist'],
                'publisher' => $mus['publisher'],
                'releasedate' => $mus['releasedate'],
                'review' => $mus['review'],
                'year' => $year,
                'genres_id' => (int) $mus['musicgenres_id'] === -1 ? null : $mus['musicgenres_id'],
                'tracks' => $mus['tracks'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $mus['cover'] = $ri->saveImage((string) $musicId, $mus['coverurl'], $this->imgSavePath, 250, 250);
            MusicInfo::query()->where('id', $musicId)->update(['cover' => $mus['cover']]);
        } else {
            $musicId = $check['id'];
            $mus['cover'] = $ri->saveImage((string) $musicId, $mus['coverurl'], $this->imgSavePath, 250, 250);
            MusicInfo::query()->where('id', $musicId)->update([
                'title' => $mus['title'],
                'asin' => $mus['asin'],
                'url' => $mus['url'],
                'salesrank' => $mus['salesrank'],
                'artist' => $mus['artist'],
                'publisher' => $mus['publisher'],
                'releasedate' => $mus['releasedate'],
                'review' => $mus['review'],
                'year' => $year,
                'genres_id' => (int) $mus['musicgenres_id'] === -1 ? null : $mus['musicgenres_id'],
                'tracks' => $mus['tracks'],
                'cover' => $mus['cover'],
            ]);
        }

        if ($musicId) {
            if ($this->echooutput) {
                cli()->header(
                    PHP_EOL.'Added/updated album: '.PHP_EOL.
                    '   Artist: '.$mus['artist'].PHP_EOL.
                    '   Title:  '.$mus['title'].PHP_EOL.
                    '   Year:   '.$year
                );
            }
            $mus['cover'] = $ri->saveImage($musicId, $mus['coverurl'], $this->imgSavePath, 250, 250);
        } elseif ($this->echooutput) {
            if ($mus['artist'] === '') {
                $artist = '';
            } else {
                $artist = 'Artist: '.$mus['artist'].', Album: ';
            }

            cli()->headerOver(
                'Nothing to update: '.$artist.$mus['title'].' ('.$year.')'
            );
        }

        return $musicId;
    }

    /**
     * Process music releases and lookup metadata.
     *
     * @throws \Exception
     */
    public function processMusicReleases(bool $local = false): void
    {
        $res = DB::select(
            sprintf(
                '
                SELECT searchname, id
                FROM releases
                WHERE musicinfo_id IS NULL
                %s
                AND categories_id IN (%s, %s, %s)
                ORDER BY postdate DESC
                LIMIT %d',
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
                        cli()->info('Looking up: '.$newname);
                    }

                    // Do a local lookup first
                    $musicCheck = $this->getMusicInfoByName('', $album['name']);

                    if ($musicCheck === null && \in_array($album['name'].$album['year'], $this->failCache, false)) {
                        // Lookup recently failed, no point trying again
                        if ($this->echooutput) {
                            cli()->headerOver('Cached previous failure. Skipping.');
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
                } else {
                    // No album found.
                    Release::query()->where('id', $arr->id)->update(['musicinfo_id' => -2]);
                    echo '.';
                }

                // Sleep to not flood the API.
                $sleeptime = $this->sleeptime / 1000;
                $diff = now()->diffInSeconds($startTime, true);
                if ($sleeptime - $diff > 0 && $usedAmazon === true) {
                    sleep((int) ($sleeptime - $diff));
                }
            }

            if ($this->echooutput) {
                echo PHP_EOL;
            }
        } elseif ($this->echooutput) {
            cli()->header('No music releases to process.');
        }
    }

    /**
     * Parse artist and album name from release name.
     */
    public function parseArtist(string $releaseName): array|false
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
     * Match browse node ID to genre name.
     */
    public function matchBrowseNode(string $nodeId): string|false
    {
        $str = '';

        // music nodes above mp3 download nodes
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
            case '173429': // christian
            case '2231705011': // gospel
            case '624905011': // christian & gospel
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
            case '7': // dance & electronic
            case '624988011': // dance & dj
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
            case '33': // world music
            case '625021011': // international
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
     * Fetch music properties from iTunes.
     */
    protected function fetchItunesMusicProperties(string $title): array|false
    {
        // Load genres.
        $defaultGenres = (new GenreService)->loadGenres((string) GenreService::MUSIC_TYPE);

        $itunes = new ItunesService;

        // Try to find album first
        $album = $itunes->findAlbum($title);

        if ($album === null) {
            // Try finding a track instead
            $track = $itunes->findTrack($title);
            if ($track === null) {
                return false;
            }
            // Use track info to build album-like data
            $album = [
                'name' => $track['album'] ?? $track['name'],
                'id' => $track['album_id'] ?? $track['id'],
                'artist' => $track['artist'],
                'artist_id' => $track['artist_id'],
                'genre' => $track['genre'],
                'release_date' => $track['release_date'],
                'cover' => $track['cover'],
                'store_url' => $track['store_url'],
            ];
        }

        $genreName = $album['genre'] ?? '';

        if (! empty($genreName)) {
            if (\in_array(strtolower($genreName), $defaultGenres, false)) {
                $genreKey = array_search(strtolower($genreName), $defaultGenres, false);
            } else {
                $genreKey = Genre::query()->insertGetId(['title' => $genreName, 'type' => GenreService::MUSIC_TYPE]);
            }
        } else {
            $genreKey = -1;
        }

        // Get artist name - either from album data or lookup
        $artistName = $album['artist'] ?? '';
        if (empty($artistName) && ! empty($album['artist_id'])) {
            $artistData = $itunes->lookupArtist($album['artist_id']);
            $artistName = $artistData['artistName'] ?? '';
        }

        return [
            'title' => $album['name'],
            'asin' => $album['id'],
            'url' => $album['store_url'] ?? '',
            'salesrank' => '',
            'artist' => $artistName,
            'publisher' => $album['copyright'] ?? '',
            'releasedate' => $album['release_date'],
            'review' => '',
            'coverurl' => $album['cover'],
            'tracks' => $album['track_count'] ?? '',
            'musicgenre' => $genreName,
            'musicgenres_id' => $genreKey,
        ];
    }
}
