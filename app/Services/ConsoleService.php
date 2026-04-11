<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SecondarySearchIndex;
use App\Facades\Search;
use App\Models\Category;
use App\Models\ConsoleInfo;
use App\Models\Genre;
use App\Models\Release;
use App\Models\Settings;
use App\Services\IGDB\Exceptions\IgdbHttpException;
use App\Support\MetadataSearchLookup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ConsoleService - Console/Game processing service.
 *
 * Features:
 * - Console game info retrieval and management
 * - Release processing with IGDB lookup
 * - Title parsing and matching
 * - Browse/search functionality
 * - Cover image handling
 */
class ConsoleService
{
    public const int CONS_UPROC = 0; // Release has not been processed.

    public const int CONS_NTFND = -2;

    public bool $echoOutput;

    public int $gameQty;

    public int $lookupThrottleMs;

    public string $imgSavePath;

    public bool $renamed;

    /**
     * @var array<string, mixed>
     */
    public array $failCache;

    protected IGDBService $igdbService;

    protected ReleaseImageService $imageService;

    public function __construct(?ReleaseImageService $imageService = null, ?IGDBService $igdbService = null)
    {
        $this->echoOutput = config('nntmux.echocli');
        $this->imageService = $imageService ?? new ReleaseImageService;
        $this->igdbService = $igdbService ?? new IGDBService;

        $this->gameQty = (Settings::settingValue('maxgamesprocessed') !== '') ? (int) Settings::settingValue('maxgamesprocessed') : 150;
        $this->lookupThrottleMs = (Settings::settingValue('amazonsleep') !== '') ? (int) Settings::settingValue('amazonsleep') : 1000;
        $this->imgSavePath = config('nntmux_settings.covers_path').'/console/';
        $this->renamed = (int) Settings::settingValue('lookupgames') === 2;

        $this->failCache = [];
    }

    // ========================================
    // Console Info Retrieval Methods
    // ========================================

    /**
     * Get console info by ID.
     */
    public function getConsoleInfo(int $id): ?Model
    {
        return ConsoleInfo::query()
            ->where('consoleinfo.id', $id)
            ->select('consoleinfo.*', 'genres.title as genres')
            ->leftJoin('genres', 'genres.id', '=', 'consoleinfo.genres_id')
            ->first();
    }

    /**
     * Get console info by name using full-text search.
     */
    public function getConsoleInfoByName(string $title, string $platform): Model|false
    {
        $searchWords = '';

        $title = preg_replace('/( - | -|\(.+\)|\(|\))/', ' ', $title);
        $title = preg_replace('/[^\w ]+/', '', $title);
        $title = trim(trim(preg_replace('/\s\s+/i', ' ', $title)));

        foreach (explode(' ', $title) as $word) {
            $word = trim(rtrim(trim($word), '-'));
            if ($word !== '' && $word !== '-') {
                $word = '+'.$word;
                $searchWords .= sprintf('%s ', $word);
            }
        }
        $searchWords = trim($searchWords.'+'.$platform);

        if (Search::isAvailable()) {
            $q = MetadataSearchLookup::normalizeBooleanSearchWords($searchWords);
            if ($q !== '') {
                $hits = Search::searchSecondary(SecondarySearchIndex::Console, $q, 25);
                $consoleIds = array_values(array_map('intval', $hits['id'] ?? []));
                if ($consoleIds !== []) {
                    $rowsById = ConsoleInfo::query()
                        ->whereIn('id', $consoleIds)
                        ->get()
                        ->keyBy('id');

                    foreach ($consoleIds as $consoleId) {
                        if ($rowsById->has($consoleId)) {
                            /** @var ConsoleInfo $console */
                            $console = $rowsById->get($consoleId);

                            return $console;
                        }
                    }
                }
            }

            return false;
        }

        $row = ConsoleInfo::query()
            ->whereRaw('MATCH (title, platform) AGAINST (? IN BOOLEAN MODE)', [$searchWords])
            ->first();

        return $row ?? false;
    }

    // ========================================
    // Browse/Range Methods
    // ========================================

    /**
     * Get console games range with pagination.
     *
     * @param  array<int|string, mixed>  $cat  Category IDs (list or associative)
     * @param  array<string, mixed>  $excludedCats
     *
     * @throws \Exception
     */
    public function getConsoleRange(int $page, array $cat, int $start, int $num, string $orderBy, array $excludedCats = []): mixed
    {
        $page = max(1, $page);
        $start = max(0, $start);

        $useIndexForTitlePlatform = Search::isAvailable()
            && (! empty($_REQUEST['title']) || ! empty($_REQUEST['platform']));
        $consoleIdsFromSearch = null;
        if ($useIndexForTitlePlatform) {
            $q = trim(
                stripslashes((string) ($_REQUEST['title'] ?? '')).' '
                .stripslashes((string) ($_REQUEST['platform'] ?? ''))
            );
            if ($q === '') {
                $consoleIdsFromSearch = [];
            } else {
                $consoleIdsFromSearch = Search::searchSecondary(SecondarySearchIndex::Console, $q, 5000)['id'];
            }
            if ($consoleIdsFromSearch === []) {
                return collect();
            }
        }

        $browseBy = $this->getBrowseBy($useIndexForTitlePlatform);
        $consoleInClause = '';
        if (is_array($consoleIdsFromSearch) && $consoleIdsFromSearch !== []) {
            $consoleInClause = ' AND con.id IN ('.implode(',', array_map('intval', $consoleIdsFromSearch)).')';
        }
        $catsrch = '';
        if (\count($cat) > 0 && (int) $cat[0] !== -1) {
            $catsrch = Category::getCategorySearch($cat);
        }
        $exccatlist = '';
        if (\count($excludedCats) > 0) {
            $exccatlist = ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')';
        }
        $order = $this->getConsoleOrder($orderBy);
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        $showPasswords = app(Releases\ReleaseBrowseService::class)->showPasswords();

        $baseWhere = "con.title != '' AND con.cover = 1 "
            ."AND r.passwordstatus {$showPasswords} "
            .$browseBy.' '
            .$consoleInClause.' '
            .$catsrch.' '
            .$exccatlist;

        $cacheKey = md5('console_range_'.$baseWhere.$order[0].$order[1].$start.$num.$page);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Step 1: Count total distinct consoles matching filters
        $countSql = 'SELECT COUNT(DISTINCT con.id) AS total '
            .'FROM consoleinfo con '
            .'INNER JOIN releases r ON con.id = r.consoleinfo_id '
            .'WHERE '.$baseWhere;

        $totalResult = DB::select($countSql);
        $totalCount = $totalResult[0]->total ?? 0;

        if ($totalCount === 0) {
            return collect();
        }

        // Step 2: Get paginated console entity list with only needed columns
        $consoleSql = 'SELECT con.id, con.title, con.cover, con.publisher, con.releasedate, con.review, con.url, '
            .'con.genres_id, genres.title AS genre, '
            .'MAX(r.postdate) AS latest_postdate, '
            .'COUNT(r.id) AS total_releases '
            .'FROM consoleinfo con '
            .'INNER JOIN releases r ON con.id = r.consoleinfo_id '
            .'LEFT JOIN genres ON con.genres_id = genres.id '
            .'WHERE '.$baseWhere.' '
            .'GROUP BY con.id, con.title, con.cover, con.publisher, con.releasedate, con.review, con.url, con.genres_id, genres.title '
            ."ORDER BY {$order[0]} {$order[1]} "
            ."LIMIT {$num} OFFSET {$start}";

        $consoles = ConsoleInfo::fromQuery($consoleSql);

        if ($consoles->isEmpty()) {
            return collect();
        }

        // Build list of console IDs for release query
        $consoleIds = $consoles->pluck('id')->toArray();
        $inConsoleIds = implode(',', array_map('intval', $consoleIds));

        // Step 3: Get top 2 releases per console using ROW_NUMBER()
        $releasesSql = 'SELECT ranked.id, ranked.consoleinfo_id, ranked.guid, ranked.searchname, '
            .'ranked.size, ranked.postdate, ranked.adddate, ranked.haspreview, ranked.grabs, '
            .'ranked.comments, ranked.totalpart, ranked.group_name, ranked.nfoid, ranked.failed_count '
            .'FROM ( '
            .'SELECT r.id, r.consoleinfo_id, r.guid, r.searchname, r.size, r.postdate, r.adddate, '
            .'r.haspreview, r.grabs, r.comments, r.totalpart, g.name AS group_name, '
            .'rn.releases_id AS nfoid, df.failed AS failed_count, '
            .'ROW_NUMBER() OVER (PARTITION BY r.consoleinfo_id ORDER BY r.postdate DESC) AS rn '
            .'FROM releases r '
            .'LEFT JOIN usenet_groups g ON g.id = r.groups_id '
            .'LEFT JOIN release_nfos rn ON rn.releases_id = r.id '
            .'LEFT JOIN dnzb_failures df ON df.release_id = r.id '
            ."WHERE r.consoleinfo_id IN ({$inConsoleIds}) "
            ."AND r.passwordstatus {$showPasswords} "
            .$catsrch.' '
            .$exccatlist
            .') ranked '
            .'WHERE ranked.rn <= 2 '
            .'ORDER BY ranked.consoleinfo_id, ranked.postdate DESC';

        $releases = DB::select($releasesSql);

        // Group releases by consoleinfo_id for fast lookup
        $releasesByConsole = [];
        foreach ($releases as $release) {
            $releasesByConsole[$release->consoleinfo_id][] = $release;
        }

        // Attach releases to each console entity
        foreach ($consoles as $console) {
            $console->releases = $releasesByConsole[$console->id] ?? []; // @phpstan-ignore assign.propertyReadOnly
        }

        // Set total count on first item
        if ($consoles->isNotEmpty()) {
            $consoles[0]->_totalcount = $totalCount; // @phpstan-ignore property.notFound
        }

        Cache::put($cacheKey, $consoles, $expiresAt);

        return $consoles;
    }

    /**
     * Get console order array.
     *
     * @return array<string, mixed>
     */
    /**
     * @return array{0: string, 1: string}
     */
    public function getConsoleOrder(string $orderBy): array
    {
        $order = ($orderBy === '') ? 'r.postdate' : $orderBy;
        $orderArr = explode('_', $order);

        $orderfield = match ($orderArr[0]) {
            'title' => 'con.title',
            'platform' => 'con.platform',
            'releasedate' => 'con.releasedate',
            'genre' => 'con.genres_id',
            'size' => 'r.size',
            'files' => 'r.totalpart',
            'stats' => 'r.grabs',
            default => 'r.postdate',
        };

        $ordersort = (isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1])) ? $orderArr[1] : 'desc';

        return [$orderfield, $ordersort];
    }

    /**
     * Get console ordering options.
     *
     * @return array<int, string>
     */
    public function getConsoleOrdering(): array
    {
        return [
            'title_asc', 'title_desc',
            'posted_asc', 'posted_desc',
            'size_asc', 'size_desc',
            'files_asc', 'files_desc',
            'stats_asc', 'stats_desc',
            'platform_asc', 'platform_desc',
            'releasedate_asc', 'releasedate_desc',
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
        return ['platform' => 'platform', 'title' => 'title', 'genre' => 'genres_id'];
    }

    /**
     * Get browse by SQL clause.
     */
    public function getBrowseBy(bool $skipTitlePlatformLike = false): string
    {
        $browseBy = ' ';
        foreach ($this->getBrowseByOptions() as $bbk => $bbv) {
            if ($skipTitlePlatformLike && ($bbk === 'title' || $bbk === 'platform')) {
                continue;
            }
            if (! empty($_REQUEST[$bbk])) {
                $bbs = stripslashes($_REQUEST[$bbk]);
                if (stripos($bbv, 'id') !== false) {
                    $browseBy .= ' AND con.'.$bbv.' = '.(int) $bbs;
                } else {
                    $browseBy .= ' AND con.'.$bbv.' LIKE '.escapeString('%'.$bbs.'%');
                }
            }
        }

        return $browseBy;
    }

    // ========================================
    // Update Methods
    // ========================================

    /**
     * Update console info record.
     */
    public function update(
        int $id,
        string $title,
        ?string $asin,
        ?string $url,
        ?int $salesrank,
        ?string $platform,
        ?string $publisher,
        ?string $releasedate,
        ?string $esrb,
        int $cover,
        ?int $genresId,
        string $review = 'review'
    ): void {
        $releasedate = $releasedate !== '' ? $releasedate : null;
        $review = $review === 'review' ? $review : substr($review, 0, 3000);

        ConsoleInfo::query()
            ->where('id', $id)
            ->update([
                'title' => $title,
                'asin' => $asin,
                'url' => $url,
                'salesrank' => $salesrank,
                'platform' => $platform,
                'publisher' => $publisher,
                'releasedate' => $releasedate,
                'esrb' => $esrb,
                'cover' => $cover,
                'genres_id' => $genresId,
                'review' => $review,
            ]);
    }

    // ========================================
    // IGDB Integration Methods
    // ========================================

    /**
     * Update console info from IGDB.
     *
     *
     * @param  array<string, mixed>  $gameInfo
     *
     * @throws \Exception
     */
    public function updateConsoleInfo(array $gameInfo): int
    {
        $consoleId = self::CONS_NTFND;

        $igdb = $this->fetchIGDBProperties($gameInfo['title'], $gameInfo['platform']);
        if ($igdb !== false) {
            if ($igdb['coverurl'] !== '') {
                $igdb['cover'] = 1;
            } else {
                $igdb['cover'] = 0;
            }

            $consoleId = $this->updateConsoleTable($igdb);

            if ($this->echoOutput && $consoleId !== -2) {
                cli()->header('Added/updated game: ').
                    cli()->alternateOver('   Title:    ').
                    cli()->primary($igdb['title']).
                    cli()->alternateOver('   Platform: ').
                    cli()->primary($igdb['platform']).
                    cli()->alternateOver('   Genre: ').
                    cli()->primary($igdb['consolegenre']);
            }
        }

        return $consoleId;
    }

    /**
     * Fetch IGDB properties for a game.
     *
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    public function fetchIGDBProperties(string $gameInfo, string $gamePlatform): bool|array|\StdClass
    {
        $gamePlatform = $this->replacePlatform($gamePlatform);

        if (! $this->igdbService->isConfigured()) {
            return false;
        }

        try {
            $game = $this->igdbService->searchConsole($gameInfo, $gamePlatform);
            if ($game === null) {
                cli()->notice('IGDB found no valid results');

                return false;
            }

            $igdb = $this->igdbService->buildConsoleData($game, $gamePlatform);
            $igdb['consolegenreid'] = $this->getGenreKey($igdb['consolegenre']);

            return $igdb;
        } catch (IgdbHttpException $e) {
            if ($e->getStatusCode() === 429) {
                return false;
            }
        } catch (\Exception $e) {
            cli()->error('Error fetching IGDB properties: '.$e->getMessage());

            return false;
        }

        return false;
    }

    // ========================================
    // Release Processing Methods
    // ========================================

    /**
     * Process console releases.
     *
     * @throws \Exception
     */
    public function processConsoleReleases(): void
    {
        $query = Release::query()
            ->select(['searchname', 'id'])
            ->whereBetween('categories_id', [Category::GAME_ROOT, Category::GAME_OTHER])
            ->whereNull('consoleinfo_id');

        if ($this->renamed === true) {
            $query->where('isrenamed', '=', 1);
        }

        $res = $query->limit($this->gameQty)->orderBy('postdate')->get();

        $releaseCount = $res->count();
        if ($res instanceof \Traversable && $releaseCount > 0) {
            if ($this->echoOutput) {
                cli()->header('Processing '.$releaseCount.' console release(s).');
            }

            foreach ($res as $arr) {
                $startTime = now()->timestamp;
                $usedExternalLookup = false;
                $gameId = self::CONS_NTFND;
                $gameInfo = $this->parseTitle($arr['searchname']);

                if ($gameInfo !== false) {
                    if ($this->echoOutput) {
                        cli()->info('Looking up: '.$gameInfo['title'].' ('.$gameInfo['platform'].')');
                    }

                    // Check for existing console entry.
                    $gameCheck = $this->getConsoleInfoByName($gameInfo['title'], $gameInfo['platform']);

                    if ($gameCheck === false && \in_array($gameInfo['title'].$gameInfo['platform'], $this->failCache, true)) {
                        // Lookup recently failed, no point trying again
                        if ($this->echoOutput) {
                            cli()->info('Cached previous failure. Skipping.');
                        }
                        $gameId = -2;
                    } elseif ($gameCheck === false) {
                        $gameId = $this->updateConsoleInfo($gameInfo);
                        $usedExternalLookup = true;
                        if ($gameId === self::CONS_NTFND) {
                            $this->failCache[] = $gameInfo['title'].$gameInfo['platform'];
                        }
                    } else {
                        if ($this->echoOutput) {
                            cli()->headerOver('Found Local: ').
                                cli()->primary("{$gameInfo['title']} - {$gameInfo['platform']}");
                        }
                        $gameId = $gameCheck['id'] ?? -2;
                    }
                } elseif ($this->echoOutput) {
                    echo '.';
                }

                // Update release.
                Release::query()->where('id', $arr['id'])->update(['consoleinfo_id' => $gameId]);

                // Throttle external lookups using the legacy amazonsleep setting.
                $diff = floor((now()->timestamp - $startTime) * 1000000);
                if ($this->lookupThrottleMs * 1000 - $diff > 0 && $usedExternalLookup === true) {
                    usleep((int) ($this->lookupThrottleMs * 1000 - $diff));
                }
            }
        } elseif ($this->echoOutput) {
            cli()->header('No console releases to process.');
        }
    }

    // ========================================
    // Title Parsing Methods
    // ========================================

    /**
     * Parse release title for game info.
     *
     * @return array<string, mixed>
     */
    public function parseTitle(string $releaseName): array|false
    {
        $releaseName = preg_replace('/\sMulti\d?\s/i', '', $releaseName);
        $result = [];

        // Get name of the game from name of release.
        if (preg_match('/^(.+((abgx360EFNet|EFNet\sFULL|FULL\sabgxEFNet|abgx\sFULL|abgxbox360EFNet)\s|illuminatenboard\sorg|Place2(hom|us)e.net|united-forums? co uk|\(\d+\)))?(?P<title>.*?)[\.\-_ ](v\.?\d\.\d|PAL|NTSC|EUR|USA|JP|ASIA|JAP|JPN|AUS|MULTI(\.?\d{1,2})?|PATCHED|FULLDVD|DVD5|DVD9|DVDRIP|PROPER|REPACK|RETAIL|DEMO|DISTRIBUTION|REGIONFREE|[\. ]RF[\. ]?|READ\.?NFO|NFOFIX|PSX(2PSP)?|PS[2-4]|PSP|PSVITA|WIIU|WII|X\-?BOX|XBLA|X360|3DS|NDS|N64|NGC)/i', $releaseName, $hits)) {
            $title = $hits['title'];

            // Replace dots, underscores, or brackets with spaces.
            $result['title'] = str_replace(['.', '_', '%20', '[', ']'], ' ', $title);
            $result['title'] = str_replace([' RF ', '.RF.', '-RF-', '_RF_'], ' ', $result['title']);
            // Remove format tags from release title for match
            $result['title'] = trim(preg_replace('/PAL|MULTI(\d)?|NTSC-?J?|\(JAPAN\)/i', '', $result['title']));
            // Remove disc tags from release title for match
            $result['title'] = trim(preg_replace('/Dis[ck] \d.*$/i', '', $result['title']));

            // Needed to add code to handle DLC Properly.
            if (stripos('dlc', $result['title']) !== false) {
                $result['dlc'] = '1';
                if (stripos('Rock Band Network', $result['title']) !== false) {
                    $result['title'] = 'Rock Band';
                } elseif (strpos('-', $result['title']) !== false) {
                    $dlc = explode('-', $result['title']);
                    $result['title'] = $dlc[0];
                } elseif (preg_match('/(.*? .*?) /i', $result['title'], $dlc)) {
                    $result['title'] = $dlc[0];
                }
            }
        } else {
            $title = '';
        }

        // Get the platform of the release.
        if (preg_match('/[\.\-_ ](?P<platform>XBLA|WiiWARE|N64|SNES|NES|PS[2-4]|PS 3|PSX(2PSP)?|PSP|WIIU|WII|XBOX360|XBOXONE|X\-?BOX|X360|3DS|NDS|N?GC)/i', $releaseName, $hits)) {
            $platform = $hits['platform'];

            if (preg_match('/^N?GC$/i', $platform)) {
                $platform = 'NGC';
            }

            if (stripos('PSX2PSP', $platform) === 0) {
                $platform = 'PSX';
            }

            if (! empty($title) && stripos('XBLA', $platform) === 0 && stripos('dlc', $title) !== false) {
                $platform = 'XBOX360';
            }

            $result['platform'] = $platform;
        }

        $result['release'] = $releaseName;
        array_map('trim', $result);

        return (isset($result['title'], $result['platform']) && ! empty($result['title'])) ? $result : false;
    }

    // ========================================
    // Platform Methods
    // ========================================

    /**
     * Normalize a parsed release platform name to the external lookup equivalent.
     */
    public function replacePlatform(string $platform): string
    {
        return match (strtoupper($platform)) {
            'X360', 'XBOX360' => 'Xbox 360',
            'XBOXONE', 'XBOX ONE' => 'Xbox One',
            'DSI', 'NDS' => 'Nintendo DS',
            '3DS' => 'Nintendo 3DS',
            'PS2' => 'PlayStation2',
            'PS3' => 'PlayStation 3',
            'PS4' => 'PlayStation 4',
            'PSP' => 'Sony PSP',
            'PSVITA' => 'PlayStation Vita',
            'PSX', 'PSX2PSP' => 'PlayStation',
            'WIIU' => 'Nintendo Wii U',
            'WII' => 'Nintendo Wii',
            'NGC' => 'GameCube',
            'N64' => 'Nintendo 64',
            'NES' => 'Nintendo NES',
            'SUPER NINTENDO', 'NINTENDO SUPER NES', 'SNES' => 'SNES',
            default => $platform,
        };
    }

    // ========================================
    // Protected Helper Methods
    // ========================================

    /**
     * Update or create console info in the database.
     *
     * @param  array<string, mixed>  $con
     */
    protected function updateConsoleTable(array $con = []): int
    {
        $asin = isset($con['asin']) ? (string) $con['asin'] : null;
        $check = ConsoleInfo::query()->where('asin', $asin)->first();

        if ($check === null) {
            $consoleId = ConsoleInfo::query()
                ->insertGetId([
                    'title' => $con['title'],
                    'asin' => $asin,
                    'url' => $con['url'],
                    'salesrank' => $con['salesrank'],
                    'platform' => $con['platform'],
                    'publisher' => $con['publisher'],
                    'genres_id' => (int) $con['consolegenreid'] === -1 ? null : $con['consolegenreid'],
                    'esrb' => $con['esrb'],
                    'releasedate' => $con['releasedate'] !== '' ? $con['releasedate'] : null,
                    'review' => substr($con['review'], 0, 3000),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($con['cover'] === 1) {
                $con['cover'] = $this->imageService->saveImage((string) $consoleId, $con['coverurl'], $this->imgSavePath, 250, 250);
            }
        } else {
            $consoleId = $check['id'];

            if ($con['cover'] === 1) {
                $con['cover'] = $this->imageService->saveImage((string) $consoleId, $con['coverurl'], $this->imgSavePath, 250, 250);
            }

            $this->update(
                $consoleId,
                $con['title'],
                isset($con['asin']) ? (string) $con['asin'] : null,
                $con['url'],
                isset($con['salesrank']) && $con['salesrank'] !== '' ? (int) $con['salesrank'] : null,
                $con['platform'],
                $con['publisher'],
                $con['releasedate'] ?? null,
                $con['esrb'],
                $con['cover'],
                $con['consolegenreid'],
                $con['review'] ?? null
            );
        }

        return $consoleId;
    }

    /**
     * Get or create genre key.
     *
     *
     * @throws \Exception
     */
    protected function getGenreKey(string $genreName): false|int|string
    {
        $genreassoc = $this->loadGenres();

        if (\in_array(strtolower($genreName), $genreassoc, true)) {
            $genreKey = array_search(strtolower($genreName), $genreassoc, true);
        } else {
            $genreKey = Genre::query()->insertGetId(['title' => $genreName, 'type' => GenreService::CONSOLE_TYPE]);
        }

        return $genreKey;
    }

    /**
     * Load genres from database.
     *
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    protected function loadGenres(): array
    {
        $gen = new GenreService;

        return $gen->loadGenres((string) GenreService::CONSOLE_TYPE);
    }
}
