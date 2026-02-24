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
     *
     * @var array<string, mixed>
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
     *
     * @param  array<string, mixed>  $cat
     * @param  array<string, mixed>  $excludedCats
     */
    public function getMusicRange(int $page, array $cat, int $start, int $num, string $orderBy, array $excludedCats = []): mixed
    {
        $page = max(1, $page);
        $start = max(0, $start);

        $browseby = $this->getBrowseBy();
        $catsrch = '';
        if (\count($cat) > 0 && (int) $cat[0] !== -1) { // @phpstan-ignore offsetAccess.notFound
            $catsrch = Category::getCategorySearch($cat);
        }
        $exccatlist = '';
        if (\count($excludedCats) > 0) {
            $exccatlist = ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')';
        }
        $order = $this->getMusicOrder($orderBy);
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));

        $releaseBrowseService = new ReleaseBrowseService;
        $showPasswords = $releaseBrowseService->showPasswords();

        $baseWhere = "m.title != '' AND m.cover = 1 "
            ."AND r.passwordstatus {$showPasswords} "
            .$browseby.' '
            .$catsrch.' '
            .$exccatlist;

        $cacheKey = md5('music_range_'.$baseWhere.$order[0].$order[1].$start.$num.$page);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Step 1: Count total distinct music entities matching filters
        $countSql = 'SELECT COUNT(DISTINCT m.id) AS total '
            .'FROM musicinfo m '
            .'INNER JOIN releases r ON r.musicinfo_id = m.id '
            .'WHERE '.$baseWhere;

        $totalResult = DB::select($countSql);
        $totalCount = $totalResult[0]->total ?? 0;

        if ($totalCount === 0) {
            return collect();
        }

        // Step 2: Get paginated music entity list with only needed columns
        $musicSql = 'SELECT m.id, m.title, m.artist, m.cover, m.publisher, m.releasedate, m.review, m.url, m.year, '
            .'m.genres_id, '
            .'MAX(r.postdate) AS latest_postdate, '
            .'COUNT(r.id) AS total_releases '
            .'FROM musicinfo m '
            .'INNER JOIN releases r ON r.musicinfo_id = m.id '
            .'WHERE '.$baseWhere.' '
            .'GROUP BY m.id, m.title, m.artist, m.cover, m.publisher, m.releasedate, m.review, m.url, m.year, m.genres_id '
            ."ORDER BY {$order[0]} {$order[1]} "
            ."LIMIT {$num} OFFSET {$start}";

        $musicEntities = MusicInfo::fromQuery($musicSql);

        if ($musicEntities->isEmpty()) {
            return collect();
        }

        // Build list of music IDs for release query
        $musicIds = $musicEntities->pluck('id')->toArray();
        $inMusicIds = implode(',', array_map('intval', $musicIds));

        // Step 3: Get top 2 releases per music entity using ROW_NUMBER()
        $releasesSql = 'SELECT ranked.id, ranked.musicinfo_id, ranked.guid, ranked.searchname, '
            .'ranked.size, ranked.postdate, ranked.adddate, ranked.haspreview, ranked.grabs, '
            .'ranked.comments, ranked.totalpart, ranked.group_name, ranked.nfoid, ranked.failed_count '
            .'FROM ( '
            .'SELECT r.id, r.musicinfo_id, r.guid, r.searchname, r.size, r.postdate, r.adddate, '
            .'r.haspreview, r.grabs, r.comments, r.totalpart, g.name AS group_name, '
            .'rn.releases_id AS nfoid, df.failed AS failed_count, '
            .'ROW_NUMBER() OVER (PARTITION BY r.musicinfo_id ORDER BY r.postdate DESC) AS rn '
            .'FROM releases r '
            .'LEFT JOIN usenet_groups g ON g.id = r.groups_id '
            .'LEFT JOIN release_nfos rn ON rn.releases_id = r.id '
            .'LEFT JOIN dnzb_failures df ON df.release_id = r.id '
            ."WHERE r.musicinfo_id IN ({$inMusicIds}) "
            ."AND r.passwordstatus {$showPasswords} "
            .$catsrch.' '
            .$exccatlist
            .') ranked '
            .'WHERE ranked.rn <= 2 '
            .'ORDER BY ranked.musicinfo_id, ranked.postdate DESC';

        $releases = DB::select($releasesSql);

        // Group releases by musicinfo_id for fast lookup
        $releasesByMusic = [];
        foreach ($releases as $release) {
            $releasesByMusic[$release->musicinfo_id][] = $release;
        }

        // Attach releases to each music entity
        foreach ($musicEntities as $musicItem) {
            $musicItem->releases = $releasesByMusic[$musicItem->id] ?? []; // @phpstan-ignore property.notFound
        }

        // Set total count on first item
        if ($musicEntities->isNotEmpty()) {
            $musicEntities[0]->_totalcount = $totalCount;
        }

        Cache::put($cacheKey, $musicEntities, $expiresAt);

        return $musicEntities;
    }

    /**
     * Parse order by parameter and return order field and direction.
     *
     * @return array{0: string, 1: string}
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
     *
     * @return array<int, string>
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
     *
     * @return array<string, mixed>
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
     * @param  array<string, mixed>  $amazdata
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
     *
     * @return array<string, mixed>
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
     *
     * @return array<string, mixed>
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
