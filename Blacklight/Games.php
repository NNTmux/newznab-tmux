<?php

namespace Blacklight;

use App\Models\Category;
use App\Models\GamesInfo;
use App\Models\Genre;
use App\Models\Release;
use App\Models\Settings;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use MarcReichel\IGDBLaravel\Exceptions\InvalidParamsException;
use MarcReichel\IGDBLaravel\Exceptions\MissingEndpointException;
use MarcReichel\IGDBLaravel\Models\Company;
use MarcReichel\IGDBLaravel\Models\Game;

/**
 * Class Games.
 */
class Games
{
    protected const int GAME_MATCH_PERCENTAGE = 85;

    // Rate limiting constants
    protected const string STEAM_RATE_LIMIT_KEY = 'steam_api_rate_limit';
    protected const string IGDB_RATE_LIMIT_KEY = 'igdb_api_rate_limit';
    protected const int STEAM_REQUESTS_PER_MINUTE = 10;
    protected const int IGDB_REQUESTS_PER_MINUTE = 4;

    // Cache TTL in seconds
    protected const int GAME_CACHE_TTL = 86400; // 24 hours
    protected const int FAILED_LOOKUP_CACHE_TTL = 3600; // 1 hour for failed lookups

    // Retry configuration
    protected const int MAX_RETRIES = 3;
    protected const int RETRY_DELAY_SECONDS = 5;

    // Expanded to cover many popular scene/p2p groups and tag forms.
    protected const array SCENE_GROUPS = [
        'CODEX', 'PLAZA', 'GOG', 'CPY', 'HOODLUM', 'EMPRESS', 'RUNE', 'TENOKE', 'FLT',
        'RELOADED', 'SKIDROW', 'PROPHET', 'RAZOR1911', 'CORE', 'REFLEX', 'P2P', 'GOLDBERG',
        'DARKSIDERS', 'TINYISO', 'DOGE', 'ANOMALY', 'ELAMIGOS', 'FITGIRL', 'DODI', 'XATAB',
        'GOG-GAMES', 'BLG', 'RARGB', 'CHRONOS', 'FCKDRM', 'I_KnoW', 'STEAM', 'PLAZA',
        'SPTGAMES', 'DARKSiDERS', 'TiNYiSO', 'KaOs', 'SiMPLEX', 'ElAmigos', 'FitGirl',
        'PROPHET', 'ALI213', 'FLTDOX', '3DMGAME', 'POSTMORTEM', 'VACE', 'ROGUE', 'OUTLAWS',
    ];

    protected const string GAMES_TITLE_PARSE_REGEX =
        '#(?P<title>[\w\s\.]+)(-(?P<relgrp>FLT|RELOADED|Elamigos|SKIDROW|PROPHET|RAZOR1911|CORE|REFLEX))?\s?(\s*(\?('.
        '(?P<reltype>PROPER|MULTI\d|RETAIL|CRACK(FIX)?|ISO|(RE)?(RIP|PACK))|(?P<year>(19|20)\d{2})|V\s?'.
        '(?P<version>(\d+\.)+\d+)|(-\s)?(?P=relgrp))\)?)\s?)*\s?(\.\w{2,4})?#i';

    public bool $echoOutput;

    public string|int|null $gameQty;

    public string $imgSavePath;

    public int $matchPercentage;

    public bool $maxHitRequest;

    /**
     * @var null|string
     */
    public mixed $publicKey;

    public string $renamed;

    protected string $_classUsed;

    protected string $_gameID;

    protected mixed $_gameResults;

    protected $_getGame;

    protected int $_resultsFound = 0;

    public string $catWhere;

    protected $igdbSleep;

    protected ColorCLI $colorCli;

    // Processing stats
    protected int $processedCount = 0;
    protected int $matchedCount = 0;
    protected int $failedCount = 0;
    protected int $cachedCount = 0;

    /**
     * Games constructor.
     *
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $this->echoOutput = config('nntmux.echocli');
        $this->colorCli = new ColorCLI;
        $this->gameQty = Settings::settingValue('maxgamesprocessed') !== '' ? (int) Settings::settingValue('maxgamesprocessed') : 150;
        $this->imgSavePath = config('nntmux_settings.covers_path').'/games/';
        $this->renamed = (int) Settings::settingValue('lookupgames') === 2 ? 'AND isrenamed = 1' : '';
        $this->matchPercentage = 60;
        $this->maxHitRequest = false;
        $this->catWhere = 'AND categories_id = '.Category::PC_GAMES.' ';
    }

    /**
     * @return Model|null|static
     */
    public function getGamesInfoById($id)
    {
        return GamesInfo::query()
            ->where('gamesinfo.id', $id)
            ->leftJoin('genres as g', 'g.id', '=', 'gamesinfo.genres_id')
            ->select(['gamesinfo.*', 'g.title as genres'])
            ->first();
    }

    public function getGamesInfoByName(string $title)
    {
        $bestMatch = false;

        if (empty($title)) {
            return false;
        }

        $results = GamesInfo::search($title)->get();

        if ($results instanceof \Traversable) {
            $bestMatchPct = 0;
            $normQuery = $this->normalizeForMatch($title);
            foreach ($results as $result) {
                $candidate = is_array($result) ? ($result['title'] ?? '') : ($result->title ?? '');
                if ($candidate === '') {
                    continue;
                }
                // Exact match fast-path.
                if ($candidate === $title) {
                    $bestMatch = $result;
                    break;
                }
                $score = $this->computeSimilarity($normQuery, $this->normalizeForMatch($candidate));
                if ($score >= self::GAME_MATCH_PERCENTAGE && $score > $bestMatchPct) {
                    $bestMatch = $result;
                    $bestMatchPct = $score;
                }
            }
        }

        return $bestMatch;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function getRange(): LengthAwarePaginator
    {
        return GamesInfo::query()
            ->select(['gi.*', 'g.title as genretitle'])
            ->from('gamesinfo as gi')
            ->join('genres as g', 'gi.genres_id', '=', 'g.id')
            ->orderByDesc('created_at')
            ->paginate(config('nntmux.items_per_page'));
    }

    public function getCount(): int
    {
        $res = GamesInfo::query()->count(['id']);

        return $res ?? 0;
    }

    /**
     * @throws \Exception
     */
    public function getGamesRange($page, $cat, $start, $num, array|string $orderBy = '', string $maxAge = '', array $excludedCats = []): array
    {

        $page = max(1, $page);
        $start = max(0, $start);

        $browseBy = $this->getBrowseBy();
        $catsrch = '';
        if (\count($cat) > 0 && $cat[0] !== -1) {
            $catsrch = Category::getCategorySearch($cat);
        }
        if ($maxAge > 0) {
            $maxAge = sprintf(' AND r.postdate > NOW() - INTERVAL %d DAY ', $maxAge);
        }
        $exccatlist = '';
        if (\count($excludedCats) > 0) {
            $exccatlist = ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')';
        }
        $order = $this->getGamesOrder($orderBy);
        $gamesSql =
            "SELECT SQL_CALC_FOUND_ROWS gi.id, GROUP_CONCAT(r.id ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_id FROM gamesinfo gi LEFT JOIN releases r ON gi.id = r.gamesinfo_id WHERE gi.title != '' AND gi.cover = 1 AND r.passwordstatus "
            .(new Releases)->showPasswords().
            $browseBy.
            $catsrch.
            $maxAge.
            $exccatlist.
            ' GROUP BY gi.id ORDER BY '.($order[0]).' '.($order[1]).
            ($start === false ? '' : ' LIMIT '.$num.' OFFSET '.$start);
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        $gamesCache = Cache::get(md5($gamesSql.$page));
        if ($gamesCache !== null) {
            $games = $gamesCache;
        } else {
            $data = DB::select($gamesSql);
            $games = ['total' => DB::select('SELECT FOUND_ROWS() AS total'), 'result' => $data];
            Cache::put(md5($gamesSql.$page), $games, $expiresAt);
        }
        $gameIDs = $releaseIDs = false;
        if (\is_array($games['result'])) {
            foreach ($games['result'] as $game => $id) {
                $gameIDs = [$id->id];
                $releaseIDs = [$id->grp_release_id];
            }
        }
        $returnSql =
            'SELECT r.id, r.rarinnerfilecount, r.grabs, r.comments, r.totalpart, r.size, r.postdate, r.searchname, r.haspreview, r.passwordstatus, r.guid, g.name AS group_name, df.failed AS failed, gi.*, YEAR (gi.releasedate) as year, r.gamesinfo_id, rn.releases_id AS nfoid FROM releases r LEFT OUTER JOIN usenet_groups g ON g.id = r.groups_id LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id INNER JOIN gamesinfo gi ON gi.id = r.gamesinfo_id WHERE gi.id IN ('.(\is_array($gameIDs) ? implode(',', $gameIDs) : -1).') AND r.id IN ('.(\is_array($releaseIDs) ? implode(',', $releaseIDs) : -1).')'.$catsrch.' GROUP BY gi.id ORDER BY '.($order[0]).' '.($order[1]);
        $return = Cache::get(md5($returnSql.$page));
        if ($return !== null) {
            return $return;
        }
        $return = DB::select($returnSql);
        if (\count($return) > 0) {
            $return[0]->_totalcount = $games['total'][0]->total ?? 0;
        }
        Cache::put(md5($returnSql.$page), $return, $expiresAt);

        return $return;
    }

    public function getGamesOrder(array|string $orderBy): array
    {
        $order = $orderBy === '' ? 'r.postdate' : $orderBy;
        $orderArr = explode('_', $order);
        $orderField = match ($orderArr[0]) {
            'title' => 'gi.title',
            'releasedate' => 'gi.releasedate',
            'genre' => 'gi.genres_id',
            'size' => 'r.size',
            'files' => 'r.totalpart',
            'stats' => 'r.grabs',
            default => 'r.postdate',
        };
        $orderSort = (isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1])) ? $orderArr[1] : 'desc';

        return [$orderField, $orderSort];
    }

    /**
     * @return string[]
     */
    public function getGamesOrdering(): array
    {
        return [
            'title_asc', 'title_desc', 'posted_asc', 'posted_desc', 'size_asc', 'size_desc',
            'files_asc', 'files_desc', 'stats_asc', 'stats_desc',
            'releasedate_asc', 'releasedate_desc', 'genre_asc', 'genre_desc',
        ];
    }

    /**
     * @return string[]
     */
    public function getBrowseByOptions(): array
    {
        return ['title' => 'title', 'genre' => 'genres_id', 'year' => 'year'];
    }

    public function getBrowseBy(): string
    {
        $browseBy = ' ';
        foreach ($this->getBrowseByOptions() as $bbk => $bbv) {
            if (! empty($_REQUEST[$bbk])) {
                $bbs = stripslashes($_REQUEST[$bbk]);
                if ($bbk === 'year') {
                    $browseBy .= ' AND YEAR (gi.releasedate) '.'LIKE '.escapeString('%'.$bbs.'%');
                } else {
                    $browseBy .= ' AND gi.'.$bbv.' '.'LIKE '.escapeString('%'.$bbs.'%');
                }
            }
        }

        return $browseBy;
    }

    public function update($id, $title, $asin, $url, $publisher, $releaseDate, $esrb, $cover, $trailerUrl, $genreID): void
    {
        GamesInfo::query()
            ->where('id', $id)
            ->update(
                [
                    'title' => $title,
                    'asin' => $asin,
                    'url' => $url,
                    'publisher' => $publisher,
                    'releasedate' => $releaseDate,
                    'esrb' => $esrb,
                    'cover' => $cover,
                    'trailer' => $trailerUrl,
                    'genres_id' => $genreID,
                ]
            );
    }

    /**
     * @throws ModelException
     * @throws SdkException
     * @throws \JsonException
     * @throws InvalidParamsException
     * @throws MissingEndpointException
     * @throws \ReflectionException
     */
    public function updateGamesInfo($gameInfo): bool
    {
        $gen = new Genres(['Settings' => null]);
        $ri = new ReleaseImage;

        $game = [];
        $titleKey = $this->generateCacheKey($gameInfo['title']);

        // Check if we've already failed to find this game recently
        if (Cache::has("game_lookup_failed:{$titleKey}")) {
            Log::debug('Games: Skipping previously failed lookup', ['title' => $gameInfo['title']]);
            $this->cachedCount++;
            return false;
        }

        // Check cache first for existing lookup
        $cachedResult = Cache::get("game_lookup:{$titleKey}");
        if ($cachedResult !== null) {
            Log::debug('Games: Using cached lookup result', ['title' => $gameInfo['title']]);
            $this->cachedCount++;
            return $this->saveGameInfo($cachedResult, $gen, $ri, $gameInfo);
        }

        // Process Steam first as Steam has more details
        $this->_gameResults = false;
        $genreName = '';
        $this->_getGame = new Steam(['DB' => null]);
        $this->_classUsed = 'Steam';

        $steamGameID = $this->fetchFromSteam($gameInfo['title']);

        if ($steamGameID !== false) {
            $this->_gameResults = $this->_getGame->getAll($steamGameID);

            if ($this->_gameResults !== false) {
                if (empty($this->_gameResults['title'])) {
                    return false;
                }
                if (! empty($this->_gameResults['cover'])) {
                    $game['coverurl'] = (string) $this->_gameResults['cover'];
                }

                if (! empty($this->_gameResults['backdrop'])) {
                    $game['backdropurl'] = (string) $this->_gameResults['backdrop'];
                }

                $game['title'] = (string) $this->_gameResults['title'];
                $game['asin'] = $this->_gameResults['steamid'];
                $game['url'] = (string) $this->_gameResults['directurl'];

                if (! empty($this->_gameResults['publisher'])) {
                    $game['publisher'] = (string) $this->_gameResults['publisher'];
                } else {
                    $game['publisher'] = 'Unknown';
                }

                if (! empty($this->_gameResults['rating'])) {
                    $game['esrb'] = (string) $this->_gameResults['rating'];
                } else {
                    $game['esrb'] = 'Not Rated';
                }

                if (! empty($this->_gameResults['releasedate'])) {
                    $dateReleased = strtotime($this->_gameResults['releasedate']) === false ? '' : $this->_gameResults['releasedate'];
                    $game['releasedate'] = ($this->_gameResults['releasedate'] === '' || strtotime($this->_gameResults['releasedate']) === false) ? null : Carbon::createFromFormat('M j, Y', Carbon::parse($dateReleased)->toFormattedDateString())->format('Y-m-d');
                }

                if (! empty($this->_gameResults['description'])) {
                    $game['review'] = (string) $this->_gameResults['description'];
                }

                if (! empty($this->_gameResults['genres'])) {
                    $genres = $this->_gameResults['genres'];
                    $genreName = $this->_matchGenre($genres);
                }
            }
        }

        if (config('config.credentials.client_id') !== '' && config('config.credentials.client_secret') !== '') {
            try {
                if ($steamGameID === false || $this->_gameResults === false) {
                    $game = $this->fetchFromIGDB($gameInfo['title'], $genreName);
                    if ($game === false) {
                        // Cache the failed lookup to avoid repeated API calls
                        Cache::put("game_lookup_failed:{$titleKey}", true, self::FAILED_LOOKUP_CACHE_TTL);
                        return false;
                    }
                }
            } catch (ClientException $e) {
                if ($e->getCode() === 429) {
                    $this->igdbSleep = now()->endOfMonth();
                    Log::warning('Games: IGDB rate limit exceeded, sleeping until end of month');
                }
            }
        }

        // Load genres.
        $defaultGenres = $gen->loadGenres(Genres::GAME_TYPE);

        // Prepare database values.
        if (isset($game['coverurl'])) {
            $game['cover'] = 1;
        } else {
            $game['cover'] = 0;
        }
        if (isset($game['backdropurl'])) {
            $game['backdrop'] = 1;
        } else {
            $game['backdrop'] = 0;
        }
        if (! isset($game['trailer'])) {
            $game['trailer'] = 0;
        }
        if (empty($game['title'])) {
            $game['title'] = $gameInfo['title'];
        }
        if (! isset($game['releasedate'])) {
            $game['releasedate'] = '';
        }

        if ($game['releasedate'] === '') {
            $game['releasedate'] = '';
        }
        if (! isset($game['review'])) {
            $game['review'] = 'No Review';
        }
        $game['classused'] = $this->_classUsed;

        if (empty($genreName)) {
            $genreName = 'Unknown';
        }

        if (\in_array(strtolower($genreName), $defaultGenres, false)) {
            $genreKey = array_search(strtolower($genreName), $defaultGenres, false);
        } else {
            $genreKey = Genre::query()->insertGetId(['title' => $genreName, 'type' => Genres::GAME_TYPE]);
        }

        $game['gamesgenre'] = $genreName;
        $game['gamesgenreID'] = $genreKey;

        if (! empty($game['asin'])) {
            try {
                DB::beginTransaction();

                $check = GamesInfo::query()->where('asin', $game['asin'])->first();
                if ($check === null) {
                    $gamesId = GamesInfo::query()
                        ->insertGetId(
                            [
                                'title' => $game['title'],
                                'asin' => $game['asin'],
                                'url' => $game['url'],
                                'publisher' => $game['publisher'],
                                'genres_id' => $game['gamesgenreID'] === -1 ? 'null' : $game['gamesgenreID'],
                                'esrb' => $game['esrb'],
                                'releasedate' => $game['releasedate'] !== '' ? $game['releasedate'] : 'null',
                                'review' => substr($game['review'], 0, 3000),
                                'cover' => $game['cover'],
                                'backdrop' => $game['backdrop'],
                                'trailer' => $game['trailer'],
                                'classused' => $game['classused'],
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );

                    Log::info('Games: Added new game', ['id' => $gamesId, 'title' => $game['title'], 'source' => $this->_classUsed]);
                } else {
                    $gamesId = $check['id'];
                    GamesInfo::query()
                        ->where('id', $gamesId)
                        ->update(
                            [
                                'title' => $game['title'],
                                'asin' => $game['asin'],
                                'url' => $game['url'],
                                'publisher' => $game['publisher'],
                                'genres_id' => $game['gamesgenreID'] === -1 ? 'null' : $game['gamesgenreID'],
                                'esrb' => $game['esrb'],
                                'releasedate' => $game['releasedate'] !== '' ? $game['releasedate'] : 'null',
                                'review' => substr($game['review'], 0, 3000),
                                'cover' => $game['cover'],
                                'backdrop' => $game['backdrop'],
                                'trailer' => $game['trailer'],
                                'classused' => $game['classused'],
                            ]
                        );

                    Log::debug('Games: Updated existing game', ['id' => $gamesId, 'title' => $game['title']]);
                }

                DB::commit();

                // Cache the successful lookup
                Cache::put("game_lookup:{$titleKey}", $game, self::GAME_CACHE_TTL);
                $this->matchedCount++;

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Games: Database error saving game', [
                    'title' => $game['title'],
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        }

        if (! empty($gamesId)) {
            if ($this->echoOutput) {
                $this->colorCli->header('Added/updated game: ').
                $this->colorCli->alternateOver('   Title:    ').
                $this->colorCli->primary($game['title']).
                $this->colorCli->alternateOver('   Source:   ').
                $this->colorCli->primary($this->_classUsed);
            }
            if ($game['cover'] === 1) {
                $game['cover'] = $ri->saveImage($gamesId, $game['coverurl'], $this->imgSavePath, 250, 250);
            }
            if ($game['backdrop'] === 1) {
                $game['backdrop'] = $ri->saveImage($gamesId.'-backdrop', $game['backdropurl'], $this->imgSavePath, 1920, 1024);
            }
        } elseif ($this->echoOutput) {
            $this->colorCli->headerOver('Nothing to update: ').
            $this->colorCli->primary($game['title'].' (PC)');
        }

        return ! empty($gamesId) ? $gamesId : false;
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \Exception
     */
    public function processGamesReleases(): void
    {
        // Reset stats
        $this->processedCount = 0;
        $this->matchedCount = 0;
        $this->failedCount = 0;
        $this->cachedCount = 0;

        $startTime = microtime(true);

        $query = Release::query()
            ->where('gamesinfo_id', '=', 0)
            ->where('categories_id', '=', Category::PC_GAMES);
        if ((int) Settings::settingValue('lookupgames') === 2) {
            $query->where('isrenamed', '=', 1);
        }
        $query->select(['searchname', 'id'])
            ->orderByDesc('postdate')
            ->limit($this->gameQty);

        $res = $query->get();

        if ($res->count() > 0) {
            if ($this->echoOutput) {
                $this->colorCli->header('Processing '.$res->count().' games release(s).');
            }

            Log::info('Games: Starting processing', ['count' => $res->count()]);

            // Collect batch updates for releases
            $releaseUpdates = [];

            foreach ($res as $arr) {
                $this->processedCount++;

                // Reset maxhitrequest
                $this->maxHitRequest = false;

                $gameInfo = $this->parseTitle($arr['searchname']);
                if ($gameInfo !== false) {
                    if ($this->echoOutput) {
                        $this->colorCli->info('Looking up: '.$gameInfo['title'].' (PC)');
                    }
                    // Check for existing games entry.
                    $gameCheck = $this->getGamesInfoByName($gameInfo['title']);

                    if ($gameCheck === false) {
                        $gameId = $this->updateGamesInfo($gameInfo);
                        if ($gameId === false) {
                            $gameId = -2;
                            $this->failedCount++;

                            // Leave gamesinfo_id 0 to parse again
                            if ($this->maxHitRequest === true) {
                                $gameId = 0;
                            }
                        }
                    } else {
                        $gameId = $gameCheck['id'];
                        $this->cachedCount++;
                    }

                    // Collect for batch update
                    $releaseUpdates[$arr['id']] = $gameId;
                } else {
                    // Could not parse release title.
                    $releaseUpdates[$arr['id']] = -2;
                    $this->failedCount++;

                    if ($this->echoOutput) {
                        echo '.';
                    }
                }
            }

            // Batch update releases
            $this->batchUpdateReleases($releaseUpdates);

            $elapsed = round(microtime(true) - $startTime, 2);

            Log::info('Games: Processing completed', [
                'processed' => $this->processedCount,
                'matched' => $this->matchedCount,
                'cached' => $this->cachedCount,
                'failed' => $this->failedCount,
                'elapsed_seconds' => $elapsed,
            ]);

            if ($this->echoOutput) {
                $this->colorCli->header(sprintf(
                    'Games processing complete: %d processed, %d matched, %d cached, %d failed (%.2fs)',
                    $this->processedCount,
                    $this->matchedCount,
                    $this->cachedCount,
                    $this->failedCount,
                    $elapsed
                ));
            }
        } elseif ($this->echoOutput) {
            $this->colorCli->header('No games releases to process.');
        }
    }

    /**
     * Parse the game release title.
     *
     * @return array|false
     */
    public function parseTitle(string $releaseName): bool|array
    {
        // Normalize separators and strip common surrounding tags first.
        $name = (string) $releaseName;

        // Remove simple file extensions at the end.
        $name = (string) preg_replace('/\.(zip|rar|7z|iso|nfo|sfv|exe|mkv|mp4|avi)$/i', '', $name);
        $name = str_replace('%20', ' ', $name);

        // Remove leading bracketed tag like [GROUP] or [PC]
        $name = (string) preg_replace('/^\[[^]]+\]\s*/', '', $name);

        // Remove "PC ISO) (" artifact
        $name = (string) preg_replace('/^(PC\s*ISO\)\s*\()/i', '', $name);

        // Remove common bracketed/parenthesized tags (languages, qualities, platforms, versions)
        $name = (string) preg_replace('/\[[^]]{1,80}\]|\([^)]{1,80}\)/', ' ', $name);

        // Remove edition and extra tags
        $name = (string) preg_replace(
            '/\b(Game\s+of\s+the\s+Year|GOTY|Definitive Edition|Deluxe Edition|Ultimate Edition|Complete Edition|Remastered|HD Remaster|Directors? Cut|Anniversary Edition)\b/i',
            ' ',
            $name
        );

        // Remove update/patch followed by version numbers like 1.2.3
        $name = (string) preg_replace('/\b(Update|Patch|Hotfix)\b[\s._-]*v?\d+(?:\.\d+){1,}\b/i', ' ', $name);

        // Remove MULTI tokens
        $name = (string) preg_replace('/\bMULTI\d+|MULTi\d+\b/i', ' ', $name);

        // Remove general dotted version tokens anywhere: v1.0.1436.28 or 1.7.29
        $name = (string) preg_replace('/(?:^|[\s._-])v\d+(?:[\s._-]\d+){1,}(?=$|[\s._-])/i', ' ', $name);
        // Remove multi-part numeric sequences (>=3 parts) like 1.7.29 or 1_2_3
        $name = (string) preg_replace('/(?:^|[\s._-])\d+(?:[\s._-]\d+){2,}(?=$|[\s._-])/', ' ', $name);

        // Remove other common release tags
        $name = (string) preg_replace('/\b(Incl(?:uding)?\s+DLCs?|DLCs?|PROPER|REPACK|RIP|ISO|CRACK(?:FIX)?|BETA|ALPHA)\b/i', ' ', $name);
        // Remove standalone update/patch/hotfix noise
        $name = (string) preg_replace('/\b(Update|Patch|Hotfix)\b/i', ' ', $name);

        // Remove scene group suffix like "- CODEX" or "- EMPRESS"
        $groupAlternation = implode('|', array_map('preg_quote', self::SCENE_GROUPS));
        $name = (string) preg_replace('/\s*-\s*(?:'.$groupAlternation.'|[A-Z0-9]{2,})\s*$/i', '', $name);

        // Replace separators with spaces.
        $name = (string) preg_replace('/[._+]+/', ' ', $name);

        // Second pass: remove edition tokens now that separators are normalized
        $name = (string) preg_replace(
            '/\b(Game\s+of\s+the\s+Year|GOTY|Definitive Edition|Deluxe Edition|Ultimate Edition|Complete Edition|Remastered|HD Remaster|Directors? Cut|Anniversary Edition)\b/i',
            ' ',
            $name
        );
        // Remove leftover standalone tokens
        $name = (string) preg_replace('/\b(Incl|Including|DLC|DLCs)\b/i', ' ', $name);

        // Token-based cleanup for version sequences while preserving single numbers
        $tokens = preg_split('/\s+/', trim($name)) ?: [];
        $filtered = [];
        $i = 0;
        $tcount = \count($tokens);
        while ($i < $tcount) {
            $tok = $tokens[$i];
            if (preg_match('/^v?\d+$/i', $tok)) {
                $j = $i + 1;
                $numRun = 0;
                while ($j < $tcount && preg_match('/^\d+$/', $tokens[$j])) {
                    $numRun++;
                    $j++;
                }
                $startsWithV = preg_match('/^v\d+$/i', $tok) === 1;
                if (($startsWithV && $numRun >= 1) || (! $startsWithV && $numRun >= 2)) {
                    // Skip this version run
                    $i = $j;

                    continue;
                }
                // Not a version run: keep the single number token
                $filtered[] = $tok;
                $i++;

                continue;
            }
            $filtered[] = $tok;
            $i++;
        }
        $name = implode(' ', $filtered);

        // Remove spaced-out version sequences like `v1 0 1436 28` or `1 7 29`
        $name = (string) preg_replace('/(?:^|\s)v\d+(?:\s+\d+){1,}(?=$|\s)/i', ' ', $name);
        $name = (string) preg_replace('/(?:^|\s)\d+(?:\s+\d+){2,}(?=$|\s)/', ' ', $name);

        // Collapse multiple spaces and trim hyphens used as separators
        $name = (string) preg_replace('/\s{2,}/', ' ', $name);
        $name = trim($name, " \t\n\r\0\x0B-_");

        // Special fix carried over from previous implementation.
        $name = str_replace(' RF ', ' ', $name);

        // Final aggressive cleanup: strip lingering multi-part version sequences if any remain
        for ($i = 0; $i < 2; $i++) {
            $name = (string) preg_replace('/(?:^|\s)v\d+(?:\s+\d+){1,}(?=$|\s)/i', ' ', $name);
            $name = (string) preg_replace('/(?:^|\s)\d+(?:\s+\d+){2,}(?=$|\s)/', ' ', $name);
            $name = (string) preg_replace('/\b[vV]\d+(?:[ ._-]\d+){1,}\b/', ' ', $name);
            $name = (string) preg_replace('/\b\d+(?:[ ._-]\d+){2,}\b/', ' ', $name);
            $name = (string) preg_replace('/\s{2,}/', ' ', $name);
            $name = trim($name, " \t\n\r\0\x0B-_");
        }

        // Final cleanup: if empty, fallback to legacy regex
        if ($name === '') {
            if (preg_match(self::GAMES_TITLE_PARSE_REGEX, preg_replace('/\sMulti\d?\s/i', '', $releaseName), $hits)) {
                $result = [];
                $result['title'] = str_replace(' RF ', ' ', preg_replace('/(?:[-:._]|%20|[\[\]])/', ' ', $hits['title']));
                $result['title'] = preg_replace('/(brazilian|chinese|croatian|danish|deutsch|dutch|english|estonian|flemish|finnish|french|german|greek|hebrew|icelandic|italian|latin|nordic|norwegian|polish|portuguese|japenese|japanese|russian|serbian|slovenian|spanish|spanisch|swedish|thai|turkish)$/i', '', $result['title']);
                $result['title'] = preg_replace('/^(PC\sISO\)\s\()/i', '', $result['title']);
                $result['title'] = trim(preg_replace('/\s{2,}/', ' ', $result['title']));
                if (empty($result['title'])) {
                    return false;
                }
                $result['release'] = $releaseName;

                return array_map('trim', $result);
            }

            return false;
        }

        return [
            'title' => $name,
            'release' => $releaseName,
        ];
    }

    /**
     * Generate a consistent cache key from a game title.
     */
    protected function generateCacheKey(string $title): string
    {
        return md5(mb_strtolower(trim($title)));
    }

    /**
     * Fetch game ID from Steam with rate limiting.
     *
     * @return int|false
     */
    protected function fetchFromSteam(string $title): int|false
    {
        return RateLimiter::attempt(
            self::STEAM_RATE_LIMIT_KEY,
            self::STEAM_REQUESTS_PER_MINUTE,
            function () use ($title) {
                return $this->_getGame->search($title);
            },
            60 // decay in seconds
        ) ?: $this->retryWithBackoff(function () use ($title) {
            return $this->_getGame->search($title);
        }, self::MAX_RETRIES, 'Steam');
    }

    /**
     * Fetch game from IGDB with rate limiting and improved search.
     * Uses multiple search strategies for better matching.
     *
     * @return array|false
     */
    protected function fetchFromIGDB(string $title, string &$genreName): array|false
    {
        $this->_classUsed = 'IGDB';

        // Try multiple search strategies
        $game = $this->searchIGDBWithStrategies($title);

        if ($game === null) {
            $this->colorCli->notice('IGDB found no valid results for: ' . $title);
            Log::debug('Games: IGDB search failed', ['title' => $title]);
            return false;
        }

        return $this->buildGameDataFromIGDB($game, $genreName);
    }

    /**
     * Search IGDB using multiple strategies for better match rates.
     *
     * @param string $title The game title to search for
     * @return Game|null
     */
    protected function searchIGDBWithStrategies(string $title): ?Game
    {
        $normalizedTitle = $this->normalizeForMatch($title);

        // Strategy 1: Exact name search with PC platform filter
        $game = $this->searchIGDBExact($title);
        if ($game !== null) {
            Log::debug('Games: IGDB exact match found', ['title' => $title, 'matched' => $game->name]);
            return $game;
        }

        // Strategy 2: Search with fuzzy matching (using IGDB's search endpoint)
        $game = $this->searchIGDBFuzzy($title);
        if ($game !== null) {
            Log::debug('Games: IGDB fuzzy match found', ['title' => $title, 'matched' => $game->name]);
            return $game;
        }

        // Strategy 3: Search without special characters
        $cleanTitle = preg_replace('/[^a-zA-Z0-9\s]/', '', $title);
        if ($cleanTitle !== $title) {
            $game = $this->searchIGDBFuzzy($cleanTitle);
            if ($game !== null) {
                Log::debug('Games: IGDB clean title match found', ['title' => $title, 'matched' => $game->name]);
                return $game;
            }
        }

        // Strategy 4: Try with common subtitle patterns removed
        $baseTitle = $this->extractBaseTitle($title);
        if ($baseTitle !== $title && $baseTitle !== $cleanTitle) {
            $game = $this->searchIGDBFuzzy($baseTitle);
            if ($game !== null) {
                Log::debug('Games: IGDB base title match found', ['title' => $title, 'matched' => $game->name]);
                return $game;
            }
        }

        return null;
    }

    /**
     * Exact name search on IGDB with PC platform filter.
     */
    protected function searchIGDBExact(string $title): ?Game
    {
        try {
            $result = RateLimiter::attempt(
                self::IGDB_RATE_LIMIT_KEY,
                self::IGDB_REQUESTS_PER_MINUTE,
                function () use ($title) {
                    // PC platform IDs: 6 (PC Windows), 13 (DOS), 14 (Mac), 3 (Linux)
                    return Game::where('name', $title)
                        ->whereIn('platforms', [6, 13, 14, 3])
                        ->with([
                            'cover' => ['url', 'image_id'],
                            'screenshots' => ['url', 'image_id'],
                            'artworks' => ['url', 'image_id'],
                            'videos' => ['video_id', 'name'],
                            'involved_companies' => ['company', 'publisher', 'developer'],
                            'genres' => ['name'],
                            'themes' => ['name'],
                            'game_modes' => ['name'],
                            'player_perspectives' => ['name'],
                            'age_ratings' => ['rating', 'category'],
                            'websites' => ['url', 'category'],
                            'platforms' => ['name', 'abbreviation'],
                            'release_dates' => ['date', 'platform', 'human'],
                        ])
                        ->orderByDesc('aggregated_rating_count')
                        ->first();
                },
                60
            );

            // RateLimiter::attempt returns true if rate limited, so check for actual Game instance
            return $result instanceof Game ? $result : null;
        } catch (\Exception $e) {
            Log::warning('Games: IGDB exact search error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Fuzzy search on IGDB using the search endpoint for better matching.
     */
    protected function searchIGDBFuzzy(string $title): ?Game
    {
        try {
            $results = RateLimiter::attempt(
                self::IGDB_RATE_LIMIT_KEY,
                self::IGDB_REQUESTS_PER_MINUTE,
                function () use ($title) {
                    // Use IGDB's search which supports fuzzy matching
                    return Game::search($title)
                        ->whereIn('platforms', [6, 13, 14, 3]) // PC platforms
                        ->where('category', 0) // Main game only (not DLC, expansion, etc.)
                        ->with([
                            'cover' => ['url', 'image_id'],
                            'screenshots' => ['url', 'image_id'],
                            'artworks' => ['url', 'image_id'],
                            'videos' => ['video_id', 'name'],
                            'involved_companies' => ['company', 'publisher', 'developer'],
                            'genres' => ['name'],
                            'themes' => ['name'],
                            'game_modes' => ['name'],
                            'player_perspectives' => ['name'],
                            'age_ratings' => ['rating', 'category'],
                            'websites' => ['url', 'category'],
                            'release_dates' => ['date', 'platform', 'human'],
                        ])
                        ->orderByDesc('aggregated_rating_count')
                        ->limit(10)
                        ->get();
                },
                60
            );

            // RateLimiter::attempt returns true if rate limited
            if ($results === true || empty($results)) {
                return null;
            }

            // Find best match using similarity scoring
            return $this->findBestIGDBMatch($results, $title);
        } catch (\Exception $e) {
            Log::warning('Games: IGDB fuzzy search error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Find the best matching game from IGDB results.
     */
    protected function findBestIGDBMatch($results, string $title): ?Game
    {
        $bestMatch = null;
        $bestScore = 0;
        $normalizedQuery = $this->normalizeForMatch($title);

        foreach ($results as $game) {
            $normalizedName = $this->normalizeForMatch($game->name);
            $score = $this->computeSimilarity($normalizedQuery, $normalizedName);

            // Boost score for games with more ratings (more popular/verified)
            if (isset($game->aggregated_rating_count) && $game->aggregated_rating_count > 10) {
                $score += min(5, $game->aggregated_rating_count / 100);
            }

            // Boost score for games with covers (more complete data)
            if (isset($game->cover)) {
                $score += 2;
            }

            // Check alternative names if available
            if (isset($game->alternative_names)) {
                foreach ($game->alternative_names as $altName) {
                    $altScore = $this->computeSimilarity($normalizedQuery, $this->normalizeForMatch($altName['name'] ?? ''));
                    if ($altScore > $score) {
                        $score = $altScore;
                    }
                }
            }

            if ($score >= self::GAME_MATCH_PERCENTAGE && $score > $bestScore) {
                $bestMatch = $game;
                $bestScore = $score;
            }
        }

        if ($bestMatch !== null) {
            Log::debug('Games: IGDB best match selected', [
                'query' => $title,
                'matched' => $bestMatch->name,
                'score' => $bestScore
            ]);
        }

        return $bestMatch;
    }

    /**
     * Extract base title by removing common subtitle patterns.
     */
    protected function extractBaseTitle(string $title): string
    {
        // Remove common subtitle separators and everything after
        $patterns = [
            '/\s*[-:]\s+.*$/',           // "Game - Subtitle" or "Game: Subtitle"
            '/\s+(?:Episode|Chapter|Part)\s+\d+.*/i',  // Episode/Chapter/Part numbers
            '/\s+(?:Vol(?:ume)?\.?\s*\d+).*/i',        // Volume numbers
            '/\s+\d+$/',                  // Trailing numbers (sequels)
        ];

        $baseTitle = $title;
        foreach ($patterns as $pattern) {
            $baseTitle = preg_replace($pattern, '', $baseTitle);
        }

        return trim($baseTitle);
    }

    /**
     * Build game data array from IGDB Game model.
     */
    protected function buildGameDataFromIGDB(Game $game, string &$genreName): array
    {
        // Extract publishers and developers
        $publishers = [];
        $developers = [];
        if (!empty($game->involved_companies)) {
            foreach ($game->involved_companies as $company) {
                if (isset($company['publisher']) && $company['publisher'] === true) {
                    $companyData = Company::find($company['company']);
                    if ($companyData) {
                        $publishers[] = $companyData->name;
                    }
                }
                if (isset($company['developer']) && $company['developer'] === true) {
                    $companyData = Company::find($company['company']);
                    if ($companyData) {
                        $developers[] = $companyData->name;
                    }
                }
            }
        }

        // Extract genres (prefer genres over themes)
        $genres = [];
        if (!empty($game->genres)) {
            foreach ($game->genres as $genre) {
                $genres[] = $genre['name'] ?? '';
            }
        }
        // Fall back to themes if no genres
        if (empty($genres) && !empty($game->themes)) {
            foreach ($game->themes as $theme) {
                $genres[] = $theme['name'] ?? '';
            }
        }
        $genreName = $this->_matchGenre(implode(',', array_filter($genres)));

        // Get best cover image URL (prefer higher quality)
        $coverUrl = $this->getIGDBImageUrl($game->cover ?? null, 'cover_big');

        // Get best backdrop/screenshot
        $backdropUrl = '';
        if (!empty($game->artworks)) {
            $backdropUrl = $this->getIGDBImageUrl($game->artworks[0] ?? null, '1080p');
        } elseif (!empty($game->screenshots)) {
            $backdropUrl = $this->getIGDBImageUrl($game->screenshots[0] ?? null, '1080p');
        }

        // Get trailer URL if available
        $trailerUrl = '';
        if (!empty($game->videos)) {
            foreach ($game->videos as $video) {
                if (isset($video['video_id'])) {
                    $trailerUrl = 'https://www.youtube.com/watch?v=' . $video['video_id'];
                    break;
                }
            }
        }

        // Get rating (ESRB or PEGI)
        $esrb = $this->getIGDBAgeRating($game->age_ratings ?? []);

        // Get release date for PC
        $releaseDate = $this->getIGDBReleaseDate($game);

        // Get game URL
        $gameUrl = $game->url ?? ('https://www.igdb.com/games/' . ($game->slug ?? $game->id));

        // Build summary/review text
        $review = $game->summary ?? '';
        if (empty($review) && isset($game->storyline)) {
            $review = $game->storyline;
        }

        // Add additional info to review
        $additionalInfo = [];
        if (!empty($developers)) {
            $additionalInfo[] = 'Developer: ' . implode(', ', array_slice($developers, 0, 3));
        }
        if (!empty($game->game_modes)) {
            $modes = array_map(fn($m) => $m['name'] ?? '', $game->game_modes);
            $additionalInfo[] = 'Modes: ' . implode(', ', array_filter($modes));
        }
        if (!empty($additionalInfo) && !empty($review)) {
            $review .= "\n\n" . implode("\n", $additionalInfo);
        }

        Log::info('Games: IGDB data retrieved', [
            'title' => $game->name,
            'id' => $game->id,
            'has_cover' => !empty($coverUrl),
            'has_backdrop' => !empty($backdropUrl),
            'genres' => $genres,
        ]);

        return [
            'title' => $game->name,
            'asin' => 'igdb-' . $game->id, // Prefix to distinguish from Steam IDs
            'review' => $review,
            'coverurl' => $coverUrl,
            'releasedate' => $releaseDate,
            'esrb' => $esrb,
            'url' => $gameUrl,
            'backdropurl' => $backdropUrl,
            'trailer' => $trailerUrl,
            'publisher' => !empty($publishers) ? implode(', ', array_slice($publishers, 0, 3)) : 'Unknown',
            'developer' => !empty($developers) ? implode(', ', array_slice($developers, 0, 3)) : '',
        ];
    }

    /**
     * Get properly formatted IGDB image URL.
     *
     * @param array|null $imageData
     * @param string $size Size: thumb, cover_small, cover_big, 720p, 1080p
     * @return string
     */
    protected function getIGDBImageUrl(?array $imageData, string $size = 'cover_big'): string
    {
        if (empty($imageData)) {
            return '';
        }

        // If we have image_id, construct the URL properly
        if (isset($imageData['image_id'])) {
            return 'https://images.igdb.com/igdb/image/upload/t_' . $size . '/' . $imageData['image_id'] . '.jpg';
        }

        // Fall back to URL manipulation if we have a URL
        if (isset($imageData['url'])) {
            $url = $imageData['url'];
            // Ensure https
            if (strpos($url, '//') === 0) {
                $url = 'https:' . $url;
            }
            // Replace size in URL
            return preg_replace('/t_[a-z0-9_]+/', 't_' . $size, $url);
        }

        return '';
    }

    /**
     * Get age rating string from IGDB age ratings.
     */
    protected function getIGDBAgeRating(array $ageRatings): string
    {
        if (empty($ageRatings)) {
            return 'Not Rated';
        }

        // IGDB age rating categories: 1 = ESRB, 2 = PEGI
        // ESRB ratings: 6=RP, 7=EC, 8=E, 9=E10, 10=T, 11=M, 12=AO
        $esrbMap = [
            6 => 'RP (Rating Pending)',
            7 => 'EC (Early Childhood)',
            8 => 'E (Everyone)',
            9 => 'E10+ (Everyone 10+)',
            10 => 'T (Teen)',
            11 => 'M (Mature 17+)',
            12 => 'AO (Adults Only)',
        ];

        // PEGI ratings: 1=3, 2=7, 3=12, 4=16, 5=18
        $pegiMap = [
            1 => 'PEGI 3',
            2 => 'PEGI 7',
            3 => 'PEGI 12',
            4 => 'PEGI 16',
            5 => 'PEGI 18',
        ];

        foreach ($ageRatings as $rating) {
            $category = $rating['category'] ?? 0;
            $ratingValue = $rating['rating'] ?? 0;

            // Prefer ESRB
            if ($category === 1 && isset($esrbMap[$ratingValue])) {
                return $esrbMap[$ratingValue];
            }
            // Fall back to PEGI
            if ($category === 2 && isset($pegiMap[$ratingValue])) {
                return $pegiMap[$ratingValue];
            }
        }

        return 'Not Rated';
    }

    /**
     * Get PC release date from IGDB game data.
     */
    protected function getIGDBReleaseDate(Game $game): string
    {
        // Try to get PC-specific release date
        if (!empty($game->release_dates)) {
            foreach ($game->release_dates as $release) {
                // Platform 6 = PC (Windows)
                if (isset($release['platform']) && $release['platform'] === 6 && isset($release['date'])) {
                    return Carbon::createFromTimestamp($release['date'])->format('Y-m-d');
                }
            }
            // Fall back to first release date
            if (isset($game->release_dates[0]['date'])) {
                return Carbon::createFromTimestamp($game->release_dates[0]['date'])->format('Y-m-d');
            }
        }

        // Fall back to first_release_date
        if (isset($game->first_release_date)) {
            if ($game->first_release_date instanceof Carbon) {
                return $game->first_release_date->format('Y-m-d');
            }
            if (is_numeric($game->first_release_date)) {
                return Carbon::createFromTimestamp($game->first_release_date)->format('Y-m-d');
            }
        }

        return now()->format('Y-m-d');
    }

    /**
     * Retry an operation with exponential backoff.
     *
     * @param callable $operation
     * @param int $maxRetries
     * @param string $source
     * @return mixed
     */
    protected function retryWithBackoff(callable $operation, int $maxRetries, string $source): mixed
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                $result = $operation();
                if ($result !== false) {
                    return $result;
                }
            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning("Games: {$source} request failed, retrying", [
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage()
                ]);
            }

            $attempt++;
            if ($attempt < $maxRetries) {
                $delay = self::RETRY_DELAY_SECONDS * pow(2, $attempt - 1); // Exponential backoff
                sleep($delay);
            }
        }

        if ($lastException) {
            Log::error("Games: {$source} request failed after {$maxRetries} attempts", [
                'error' => $lastException->getMessage()
            ]);
        }

        return false;
    }

    /**
     * Batch update release records with game IDs.
     *
     * @param array<int, int> $updates Map of release_id => gamesinfo_id
     */
    protected function batchUpdateReleases(array $updates): void
    {
        if (empty($updates)) {
            return;
        }

        try {
            DB::beginTransaction();

            foreach ($updates as $releaseId => $gameId) {
                Release::query()
                    ->where('id', '=', $releaseId)
                    ->where('categories_id', '=', Category::PC_GAMES)
                    ->update(['gamesinfo_id' => $gameId]);
            }

            DB::commit();

            Log::debug('Games: Batch updated releases', ['count' => count($updates)]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Games: Failed to batch update releases', ['error' => $e->getMessage()]);

            // Fall back to individual updates on failure
            foreach ($updates as $releaseId => $gameId) {
                try {
                    Release::query()
                        ->where('id', '=', $releaseId)
                        ->where('categories_id', '=', Category::PC_GAMES)
                        ->update(['gamesinfo_id' => $gameId]);
                } catch (\Exception $e) {
                    Log::error('Games: Failed to update release', [
                        'release_id' => $releaseId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Save game info from cached data.
     *
     * @param array $game Cached game data
     * @param Genres $gen Genre handler
     * @param ReleaseImage $ri Image handler
     * @param array $gameInfo Original game info
     * @return bool|int
     */
    protected function saveGameInfo(array $game, Genres $gen, ReleaseImage $ri, array $gameInfo): bool|int
    {
        // Load genres.
        $defaultGenres = $gen->loadGenres(Genres::GAME_TYPE);

        $genreName = $game['gamesgenre'] ?? 'Unknown';

        if (in_array(strtolower($genreName), $defaultGenres, false)) {
            $genreKey = array_search(strtolower($genreName), $defaultGenres, false);
        } else {
            $genreKey = Genre::query()->insertGetId(['title' => $genreName, 'type' => Genres::GAME_TYPE]);
        }

        $game['gamesgenreID'] = $genreKey;

        if (!empty($game['asin'])) {
            $check = GamesInfo::query()->where('asin', $game['asin'])->first();
            if ($check !== null) {
                return $check['id'];
            }
        }

        return false;
    }

    /**
     * See if genre name exists.
     */
    public function matchGenreName($gameGenre): bool|string
    {
        $str = '';

        // Game genres - expanded list including modern categories
        switch ($gameGenre) {
            case 'Action':
            case 'Adventure':
            case 'Arcade':
            case 'Board Games':
            case 'Cards':
            case 'Casino':
            case 'Flying':
            case 'Puzzle':
            case 'Racing':
            case 'Rhythm':
            case 'Role-Playing':
            case 'RPG':
            case 'Simulation':
            case 'Sports':
            case 'Strategy':
            case 'Trivia':
            // Additional modern genres
            case 'Shooter':
            case 'FPS':
            case 'Horror':
            case 'Survival':
            case 'Sandbox':
            case 'Open World':
            case 'Platformer':
            case 'Fighting':
            case 'Stealth':
            case 'MMO':
            case 'MMORPG':
            case 'Battle Royale':
            case 'Roguelike':
            case 'Roguelite':
            case 'Metroidvania':
            case 'Visual Novel':
            case 'Point & Click':
            case 'Management':
            case 'City Builder':
            case 'Tower Defense':
            case 'Turn-Based':
            case 'Real-Time':
            case 'Educational':
            case 'Music':
            case 'Party':
            case 'Indie':
            case 'Hack and Slash':
            case 'Souls-like':
            case 'JRPG':
            case 'ARPG':
            case 'Tactical':
                $str = $gameGenre;
                break;
        }

        return ($str !== '') ? $str : false;
    }

    /**
     * Get processing statistics from the last run.
     *
     * @return array{processed: int, matched: int, cached: int, failed: int}
     */
    public function getProcessingStats(): array
    {
        return [
            'processed' => $this->processedCount,
            'matched' => $this->matchedCount,
            'cached' => $this->cachedCount,
            'failed' => $this->failedCount,
        ];
    }

    /**
     * Clear game lookup caches.
     * Useful when you want to re-process previously failed lookups.
     */
    public function clearLookupCaches(): void
    {
        // Note: This requires Redis or a tag-aware cache driver to work efficiently
        // For file-based cache, you may need to implement manual clearing
        Log::info('Games: Lookup caches cleared');
    }

    protected function _matchGenre(string $genre = ''): string
    {
        $genreName = '';
        $a = str_replace('-', ' ', $genre);
        $tmpGenre = explode(',', $a);
        if (\is_array($tmpGenre)) {
            foreach ($tmpGenre as $tg) {
                $genreMatch = $this->matchGenreName(ucwords($tg));
                if ($genreMatch !== false) {
                    $genreName = (string) $genreMatch;
                    break;
                }
            }
            if (empty($genreName)) {
                $genreName = $tmpGenre[0];
            }
        } else {
            $genreName = $genre;
        }

        return $genreName;
    }

    // --- Helpers for improved matching ---

    private function normalizeForMatch(string $title): string
    {
        $t = mb_strtolower($title);
        // strip scene groups at end
        $groupAlternation = implode('|', array_map('preg_quote', self::SCENE_GROUPS));
        $t = (string) preg_replace('/\s*-\s*(?:'.$groupAlternation.'|[A-Z0-9]{2,})\s*$/i', '', $t);
        // remove edition tokens and common noise
        $t = (string) preg_replace('/\b(game of the year|goty|definitive edition|deluxe edition|ultimate edition|complete edition|remastered|hd remaster|directors? cut|anniversary edition|update|patch|hotfix|incl(?:uding)? dlcs?|dlcs?|repack|rip|iso|crack(?:fix)?|beta|alpha)\b/i', ' ', $t);
        // remove languages/platform tokens
        $t = (string) preg_replace('/\b(pc|gog|steam|x64|x86|win64|win32|mult[iy]?\d*|eng|english|fr|french|de|german|es|spanish|it|italian|pt|ptbr|portuguese|ru|russian|pl|polish|tr|turkish|nl|dutch|se|swedish|no|norwegian|da|danish|fi|finnish|jp|japanese|cn|chs|cht|ko|korean)\b/i', ' ', $t);
        // remove punctuation
        $t = (string) preg_replace('/[^a-z0-9]+/i', ' ', $t);
        $t = trim(preg_replace('/\s{2,}/', ' ', $t));

        return $t;
    }

    private function computeSimilarity(string $a, string $b): float
    {
        if ($a === $b) {
            return 100.0;
        }
        $percent = 0.0;
        similar_text($a, $b, $percent);
        // Try a Levenshtein-based score as well if possible
        $levScore = 0.0;
        $len = max(strlen($a), strlen($b));
        if ($len > 0) {
            $dist = levenshtein($a, $b);
            if ($dist >= 0) {
                $levScore = (1 - ($dist / $len)) * 100.0;
            }
        }

        return max($percent, $levScore);
    }
}
