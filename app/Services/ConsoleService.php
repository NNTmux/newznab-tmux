<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\ConsoleInfo;
use App\Models\Genre;
use App\Models\Release;
use App\Models\Settings;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use MarcReichel\IGDBLaravel\Models\Company;
use MarcReichel\IGDBLaravel\Models\Game;
use MarcReichel\IGDBLaravel\Models\Platform;

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

    protected const int MATCH_PERCENT = 60;

    public bool $echoOutput;

    public ?string $pubkey;

    public ?string $privkey;

    public ?string $asstag;

    public int $gameQty;

    public int $sleepTime;

    public string $imgSavePath;

    public bool $renamed;

    public array $failCache;

    protected ReleaseImageService $imageService;

    protected $igdbSleep;

    public function __construct(?ReleaseImageService $imageService = null)
    {
        $this->echoOutput = config('nntmux.echocli');
        $this->imageService = $imageService ?? new ReleaseImageService;

        $this->pubkey = Settings::settingValue('amazonpubkey');
        $this->privkey = Settings::settingValue('amazonprivkey');
        $this->asstag = Settings::settingValue('amazonassociatetag');
        $this->gameQty = (Settings::settingValue('maxgamesprocessed') !== '') ? (int) Settings::settingValue('maxgamesprocessed') : 150;
        $this->sleepTime = (Settings::settingValue('amazonsleep') !== '') ? (int) Settings::settingValue('amazonsleep') : 1000;
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

        return ConsoleInfo::search($searchWords)->first() ?? false;
    }

    // ========================================
    // Browse/Range Methods
    // ========================================

    /**
     * Get console games range with pagination.
     *
     * @throws \Exception
     */
    public function getConsoleRange(int $page, array $cat, int $start, int $num, string $orderBy, array $excludedCats = []): array
    {
        $page = max(1, $page);
        $start = max(0, $start);

        $browseBy = $this->getBrowseBy();
        $catsrch = '';
        if (\count($cat) > 0 && (int) $cat[0] !== -1) {
            $catsrch = Category::getCategorySearch($cat);
        }
        $exccatlist = '';
        if (\count($excludedCats) > 0) {
            $exccatlist = ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')';
        }
        $order = $this->getConsoleOrder($orderBy);
        $calcSql = sprintf(
            "
                SELECT SQL_CALC_FOUND_ROWS
                    con.id,
                    GROUP_CONCAT(r.id ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_id
                FROM consoleinfo con
                LEFT JOIN releases r ON con.id = r.consoleinfo_id
                WHERE con.title != ''
                AND con.cover = 1
                AND r.passwordstatus %s
                %s %s %s
                GROUP BY con.id
                ORDER BY %s %s %s",
            app(Releases\ReleaseBrowseService::class)->showPasswords(),
            $browseBy,
            $catsrch,
            $exccatlist,
            $order[0],
            $order[1],
            ($start === false ? '' : ' LIMIT '.$num.' OFFSET '.$start)
        );

        $cached = Cache::get(md5($calcSql.$page));
        if ($cached !== null) {
            $consoles = $cached;
        } else {
            $data = DB::select($calcSql);
            $consoles = ['total' => DB::select('SELECT FOUND_ROWS() AS total'), 'result' => $data];
            $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
            Cache::put(md5($calcSql.$page), $consoles, $expiresAt);
        }

        $consoleIDs = $releaseIDs = [];
        if (\is_array($consoles['result'])) {
            foreach ($consoles['result'] as $console => $id) {
                $consoleIDs[] = $id->id;
                $releaseIDs[] = $id->grp_release_id;
            }
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
                    GROUP_CONCAT(r.fromname ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_fromname,
                con.*,
                r.consoleinfo_id,
                g.name AS group_name,
                genres.title AS genre,
                rn.releases_id AS nfoid
                FROM releases r
                LEFT OUTER JOIN usenet_groups g ON g.id = r.groups_id
                LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
                LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
                LEFT OUTER JOIN categories c ON c.id = r.categories_id
                LEFT OUTER JOIN root_categories cp ON cp.id = c.root_categories_id
                INNER JOIN consoleinfo con ON con.id = r.consoleinfo_id
                INNER JOIN genres ON con.genres_id = genres.id
                WHERE con.id IN (%s)
                AND r.id IN (%s)
                %s
                GROUP BY con.id
                ORDER BY %s %s",
            (! empty($consoleIDs) ? implode(',', $consoleIDs) : -1),
            (! empty($releaseIDs) ? implode(',', $releaseIDs) : -1),
            $catsrch,
            $order[0],
            $order[1]
        );

        $return = Cache::get(md5($sql.$page));
        if ($return !== null) {
            return $return;
        }

        $return = DB::select($sql);
        if (\count($return) > 0) {
            $return[0]->_totalcount = $consoles['total'][0]->total ?? 0;
        }

        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_long'));
        Cache::put(md5($sql.$page), $return, $expiresAt);

        return $return;
    }

    /**
     * Get console order array.
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
     */
    public function getBrowseByOptions(): array
    {
        return ['platform' => 'platform', 'title' => 'title', 'genre' => 'genres_id'];
    }

    /**
     * Get browse by SQL clause.
     */
    public function getBrowseBy(): string
    {
        $browseBy = ' ';
        foreach ($this->getBrowseByOptions() as $bbk => $bbv) {
            if (! empty($_REQUEST[$bbk])) {
                $bbs = stripslashes($_REQUEST[$bbk]);
                $browseBy .= ' AND con.'.$bbv.' LIKE '.escapeString('%'.$bbs.'%');
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
     * @return int|mixed
     *
     * @throws \Exception
     */
    public function updateConsoleInfo(array $gameInfo): int
    {
        $consoleId = self::CONS_NTFND;

        $igdb = $this->fetchIGDBProperties($gameInfo['title'], $gameInfo['node']);
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
     * @throws \Exception
     */
    public function fetchIGDBProperties(string $gameInfo, string $gamePlatform): bool|array|\StdClass
    {
        $bestMatch = false;

        $gamePlatform = $this->replacePlatform($gamePlatform);

        if (config('config.credentials.client_id') !== '' && config('config.credentials.client_secret') !== '') {
            try {
                $result = Game::where('name', $gameInfo)->get();
                if (! empty($result)) {
                    $bestMatchPct = 0;
                    foreach ($result as $res) {
                        similar_text(strtolower($gameInfo), strtolower($res->name), $percent);
                        if ($percent >= 90 && $percent > $bestMatchPct) {
                            $bestMatch = $res->id;
                            $bestMatchPct = $percent;
                        }
                    }

                    if ($bestMatch !== false) {
                        $game = Game::with([
                            'cover' => ['url'],
                            'screenshots' => ['url'],
                            'involved_companies' => ['company', 'publisher'],
                            'themes',
                        ])->where('id', $bestMatch)->first();

                        $publishers = [];
                        if (! empty($game->involved_companies)) {
                            foreach ($game->involved_companies as $publisher) {
                                if ($publisher['publisher'] === true) {
                                    $company = Company::find($publisher['company']);
                                    $publishers[] = $company['name'];
                                }
                            }
                        }

                        $genres = [];
                        if (! empty($game->themes)) {
                            foreach ($game->themes as $theme) {
                                $genres[] = $theme['name'];
                            }
                        }

                        $genreKey = $this->getGenreKey(implode(',', $genres));

                        $platform = '';
                        if (! empty($game->platforms)) {
                            foreach ($game->platforms as $platforms) {
                                $percentCurrent = 0;
                                $gamePlatforms = Platform::where('id', $platforms)->get();
                                foreach ($gamePlatforms as $gamePlat) {
                                    similar_text($gamePlat['name'], $gamePlatform, $percent);
                                    if ($percent >= 85 && $percent > $percentCurrent) {
                                        $percentCurrent = $percent;
                                        $platform = $gamePlat['name'];
                                        break;
                                    }
                                }
                            }
                        }

                        return [
                            'title' => $game->name,
                            'asin' => (string) $game->id,
                            'review' => $game->summary ?? '',
                            'coverurl' => ! empty($game->cover->url) ? 'https:'.$game->cover->url : '',
                            'releasedate' => ! empty($game->first_release_date) ? $game->first_release_date->format('Y-m-d') : now()->format('Y-m-d'),
                            'esrb' => ! empty($game->aggregated_rating) ? round($game->aggregated_rating).'%' : 'Not Rated',
                            'url' => $game->url ?? '',
                            'publisher' => ! empty($publishers) ? implode(',', $publishers) : 'Unknown',
                            'platform' => $platform ?? '',
                            'consolegenre' => ! empty($genres) ? implode(',', $genres) : 'Unknown',
                            'consolegenreid' => $genreKey ?? '',
                            'salesrank' => '',
                        ];
                    }

                    cli()->notice('IGDB returned no valid results');

                    return false;
                }

                cli()->notice('IGDB found no valid results');

                return false;
            } catch (ClientException $e) {
                if ($e->getCode() === 429) {
                    $this->igdbSleep = now()->endOfMonth();
                }
            } catch (\Exception $e) {
                cli()->error('Error fetching IGDB properties: '.$e->getMessage());

                return false;
            }
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
                $usedAmazon = false;
                $gameId = self::CONS_NTFND;
                $gameInfo = $this->parseTitle($arr['searchname']);

                if ($gameInfo !== false) {
                    if ($this->echoOutput) {
                        cli()->info('Looking up: '.$gameInfo['title'].' ('.$gameInfo['platform'].')');
                    }

                    // Check for existing console entry.
                    $gameCheck = $this->getConsoleInfoByName($gameInfo['title'], $gameInfo['platform']);

                    if ($gameCheck === false && \in_array($gameInfo['title'].$gameInfo['platform'], $this->failCache, false)) {
                        // Lookup recently failed, no point trying again
                        if ($this->echoOutput) {
                            cli()->info('Cached previous failure. Skipping.');
                        }
                        $gameId = -2;
                    } elseif ($gameCheck === false) {
                        $gameId = $this->updateConsoleInfo($gameInfo);
                        $usedAmazon = true;
                        if ($gameId === null) {
                            $gameId = -2;
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

                // Sleep to not flood amazon.
                $diff = floor((now()->timestamp - $startTime) * 1000000);
                if ($this->sleepTime * 1000 - $diff > 0 && $usedAmazon === true) {
                    usleep($this->sleepTime * 1000 - $diff);
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

            $browseNode = $this->getBrowseNode($platform);
            $result['platform'] = $platform;
            $result['node'] = $browseNode;
        }

        $result['release'] = $releaseName;
        array_map('trim', $result);

        return (isset($result['title'], $result['platform']) && ! empty($result['title'])) ? $result : false;
    }

    // ========================================
    // Platform/Node Methods
    // ========================================

    /**
     * Replace platform name to Amazon equivalent.
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

    /**
     * Get Amazon browse node ID for platform.
     */
    public function getBrowseNode(string $platform): string
    {
        return match ($platform) {
            'PS2' => '301712',
            'PS3' => '14210751',
            'PS4' => '6427814011',
            'PSP' => '11075221',
            'PSVITA' => '3010556011',
            'PSX' => '294940',
            'WII', 'Wii' => '14218901',
            'WIIU', 'WiiU' => '3075112011',
            'XBOX360', 'X360' => '14220161',
            'XBOXONE' => '6469269011',
            'XBOX', 'X-BOX' => '537504',
            'NDS' => '11075831',
            '3DS' => '2622269011',
            'GC', 'NGC' => '541022',
            'N64' => '229763',
            'SNES' => '294945',
            'NES' => '566458',
            default => '468642',
        };
    }

    // ========================================
    // Genre Methods
    // ========================================

    /**
     * Match browse node to genre name.
     */
    public function matchBrowseNode(string $nodeName): bool|string
    {
        $str = match ($nodeName) {
            'Action_shooter', 'Action_Games', 'Action_games' => 'Action',
            'Action/Adventure', 'Action\\Adventure', 'Adventure_games' => 'Adventure',
            'Boxing_games', 'Sports_games' => 'Sports',
            'Fantasy_action_games' => 'Fantasy',
            'Fighting_action_games' => 'Fighting',
            'Flying_simulation_games' => 'Flying',
            'Horror_action_games' => 'Horror',
            'Kids & Family' => 'Family',
            'Role_playing_games' => 'Role-Playing',
            'Shooter_action_games' => 'Shooter',
            'Singing_games' => 'Music',
            'Action', 'Adventure', 'Arcade', 'Board Games', 'Cards', 'Casino',
            'Collections', 'Family', 'Fantasy', 'Fighting', 'Flying', 'Horror',
            'Music', 'Puzzle', 'Racing', 'Rhythm', 'Role-Playing', 'Simulation',
            'Shooter', 'Shooting', 'Sports', 'Strategy', 'Trivia' => $nodeName,
            default => '',
        };

        return ($str !== '') ? $str : false;
    }

    // ========================================
    // Protected Helper Methods
    // ========================================

    /**
     * Update or create console info in the database.
     *
     * @return int|mixed
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

        if (\in_array(strtolower($genreName), $genreassoc, false)) {
            $genreKey = array_search(strtolower($genreName), $genreassoc, false);
        } else {
            $genreKey = Genre::query()->insertGetId(['title' => $genreName, 'type' => GenreService::CONSOLE_TYPE]);
        }

        return $genreKey;
    }

    /**
     * Load genres from database.
     *
     * @throws \Exception
     */
    protected function loadGenres(): array
    {
        $gen = new GenreService;

        return $gen->loadGenres((string) GenreService::CONSOLE_TYPE);
    }
}
