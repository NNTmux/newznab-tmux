<?php

declare(strict_types=1);

namespace App\Services;

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

/**
 * GamesService - Comprehensive PC Games processing service.
 *
 * Features:
 * - Game info retrieval and management
 * - Release processing with Steam and IGDB lookup
 * - Title parsing and matching
 * - Browse/search functionality
 * - Cover image handling
 */
class GamesService
{
    protected const int GAME_MATCH_PERCENTAGE = 85;

    protected const int GAME_CACHE_TTL = 86400; // 24 hours

    protected const int FAILED_LOOKUP_CACHE_TTL = 3600; // 1 hour

    public bool $echoOutput;

    public string|int|null $gameQty;

    public string $imgSavePath;

    public int $matchPercentage;

    public bool $maxHitRequest;

    public string $renamed;

    public string $catWhere;

    protected SteamService $steamService;

    protected IGDBService $igdbService;

    protected GamesTitleParser $titleParser;

    protected ReleaseImageService $imageService;

    // Processing stats
    protected int $processedCount = 0;

    protected int $matchedCount = 0;

    protected int $failedCount = 0;

    protected int $cachedCount = 0;

    protected string $_classUsed = '';

    protected $igdbSleep;

    /**
     * @throws \Exception
     */
    public function __construct(
        ?SteamService $steamService = null,
        ?IGDBService $igdbService = null,
        ?GamesTitleParser $titleParser = null,
        ?ReleaseImageService $imageService = null
    ) {
        $this->echoOutput = config('nntmux.echocli');
        $this->steamService = $steamService ?? new SteamService;
        $this->igdbService = $igdbService ?? new IGDBService;
        $this->titleParser = $titleParser ?? new GamesTitleParser;
        $this->imageService = $imageService ?? new ReleaseImageService;

        $this->gameQty = Settings::settingValue('maxgamesprocessed') !== '' ? (int) Settings::settingValue('maxgamesprocessed') : 150;
        $this->imgSavePath = config('nntmux_settings.covers_path').'/games/';
        $this->renamed = (int) Settings::settingValue('lookupgames') === 2 ? 'AND isrenamed = 1' : '';
        $this->matchPercentage = 60;
        $this->maxHitRequest = false;
        $this->catWhere = 'AND categories_id = '.Category::PC_GAMES.' ';
    }

    // ========================================
    // Game Info Retrieval Methods
    // ========================================

    /**
     * Get game info by ID.
     */
    public function getGamesInfoById(int $id): ?Model
    {
        return GamesInfo::query()
            ->where('gamesinfo.id', $id)
            ->leftJoin('genres as g', 'g.id', '=', 'gamesinfo.genres_id')
            ->select(['gamesinfo.*', 'g.title as genres'])
            ->first();
    }

    /**
     * Get game info by name using full-text search.
     */
    public function getGamesInfoByName(string $title): bool|Model
    {
        $bestMatch = false;

        if (empty($title)) {
            return false;
        }

        $results = GamesInfo::search($title)->get();

        if ($results instanceof \Traversable) {
            $bestMatchPct = 0;
            $normQuery = $this->titleParser->normalizeForMatch($title);

            foreach ($results as $result) {
                $candidate = is_array($result) ? ($result['title'] ?? '') : ($result->title ?? '');
                if ($candidate === '') {
                    continue;
                }
                // Exact match fast-path
                if ($candidate === $title) {
                    $bestMatch = $result;
                    break;
                }
                $score = $this->titleParser->computeSimilarity($normQuery, $this->titleParser->normalizeForMatch($candidate));
                if ($score >= self::GAME_MATCH_PERCENTAGE && $score > $bestMatchPct) {
                    $bestMatch = $result;
                    $bestMatchPct = $score;
                }
            }
        }

        return $bestMatch;
    }

    /**
     * Get paginated list of all games.
     */
    public function getRange(?string $search = null): LengthAwarePaginator
    {
        $query = GamesInfo::query()
            ->select(['gi.*', 'g.title as genretitle'])
            ->from('gamesinfo as gi')
            ->join('genres as g', 'gi.genres_id', '=', 'g.id');

        if (! empty($search)) {
            $query->where('gi.title', 'like', '%'.$search.'%');
        }

        return $query->orderByDesc('created_at')
            ->paginate(config('nntmux.items_per_page'));
    }

    /**
     * Get total count of games.
     */
    public function getCount(): int
    {
        return GamesInfo::query()->count('id') ?? 0;
    }

    /**
     * Get games range for browsing with filtering.
     *
     * @throws \Exception
     */
    public function getGamesRange($page, $cat, $start, $num, array|string $orderBy = '', string $maxAge = '', array $excludedCats = []): array
    {
        $page = max(1, $page);
        $start = max(0, $start);

        $browseBy = $this->getBrowseBy();
        $catsrch = '';
        if (count($cat) > 0 && $cat[0] !== -1) {
            $catsrch = Category::getCategorySearch($cat);
        }
        if ($maxAge > 0) {
            $maxAge = sprintf(' AND r.postdate > NOW() - INTERVAL %d DAY ', $maxAge);
        }
        $exccatlist = '';
        if (count($excludedCats) > 0) {
            $exccatlist = ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')';
        }
        $order = $this->getGamesOrder($orderBy);
        $gamesSql =
            "SELECT SQL_CALC_FOUND_ROWS gi.id, GROUP_CONCAT(r.id ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_id FROM gamesinfo gi LEFT JOIN releases r ON gi.id = r.gamesinfo_id WHERE gi.title != '' AND gi.cover = 1 AND r.passwordstatus "
            .app(\App\Services\Releases\ReleaseBrowseService::class)->showPasswords().
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

        $gameIDs = $releaseIDs = [];
        if (is_array($games['result'])) {
            foreach ($games['result'] as $game => $id) {
                $gameIDs[] = $id->id;
                $releaseIDs[] = $id->grp_release_id;
            }
        }

        $returnSql = sprintf(
            "SELECT
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
                gi.*, YEAR(gi.releasedate) as year, r.gamesinfo_id, rn.releases_id AS nfoid, g.name AS group_name
            FROM releases r
            LEFT OUTER JOIN usenet_groups g ON g.id = r.groups_id
            LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
            LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
            LEFT OUTER JOIN categories c ON c.id = r.categories_id
            LEFT OUTER JOIN root_categories cp ON cp.id = c.root_categories_id
            INNER JOIN gamesinfo gi ON gi.id = r.gamesinfo_id
            WHERE gi.id IN (%s)
            AND r.id IN (%s)
            %s
            GROUP BY gi.id
            ORDER BY %s %s",
            (! empty($gameIDs) ? implode(',', $gameIDs) : -1),
            (! empty($releaseIDs) ? implode(',', $releaseIDs) : -1),
            $catsrch,
            $order[0],
            $order[1]
        );

        $return = Cache::get(md5($returnSql.$page));
        if ($return !== null) {
            return $return;
        }
        $return = DB::select($returnSql);
        if (count($return) > 0) {
            $return[0]->_totalcount = $games['total'][0]->total ?? 0;
        }
        Cache::put(md5($returnSql.$page), $return, $expiresAt);

        return $return;
    }

    /**
     * Get order array for games.
     */
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
     * Get ordering options.
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
     * Get browse by options.
     */
    public function getBrowseByOptions(): array
    {
        return ['title' => 'title', 'genre' => 'genres_id', 'year' => 'year'];
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
                if ($bbk === 'year') {
                    $browseBy .= ' AND YEAR (gi.releasedate) '.'LIKE '.escapeString('%'.$bbs.'%');
                } else {
                    $browseBy .= ' AND gi.'.$bbv.' '.'LIKE '.escapeString('%'.$bbs.'%');
                }
            }
        }

        return $browseBy;
    }

    // ========================================
    // Game Update Methods
    // ========================================

    /**
     * Update a game record.
     */
    public function update(
        int $id,
        string $title,
        ?string $asin,
        ?string $url,
        ?string $publisher,
        $releaseDate,
        ?string $esrb,
        int $cover,
        ?string $trailerUrl,
        ?int $genreID
    ): void {
        GamesInfo::query()
            ->where('id', $id)
            ->update([
                'title' => $title,
                'asin' => $asin,
                'url' => $url,
                'publisher' => $publisher,
                'releasedate' => $releaseDate,
                'esrb' => $esrb,
                'cover' => $cover,
                'trailer' => $trailerUrl,
                'genres_id' => $genreID,
            ]);
    }

    /**
     * Update game info from external APIs (Steam/IGDB).
     */
    public function updateGamesInfo(array $gameInfo): bool|int
    {
        $gen = new GenreService;

        $game = [];
        $titleKey = $this->generateCacheKey($gameInfo['title']);

        // Check if we've already failed to find this game recently
        if (Cache::has("game_lookup_failed:{$titleKey}")) {
            Log::debug('GamesService: Skipping previously failed lookup', ['title' => $gameInfo['title']]);
            $this->cachedCount++;

            return false;
        }

        // Check cache first for existing lookup
        $cachedResult = Cache::get("game_lookup:{$titleKey}");
        if ($cachedResult !== null) {
            Log::debug('GamesService: Using cached lookup result', ['title' => $gameInfo['title']]);
            $this->cachedCount++;

            return $this->saveGameInfoFromCache($cachedResult, $gen, $gameInfo);
        }

        // Try Steam first (has more details)
        $this->_classUsed = 'Steam';
        $genreName = '';

        $steamGameID = $this->steamService->search($gameInfo['title']);

        if ($steamGameID !== null) {
            $steamResults = $this->steamService->getAll($steamGameID);

            if ($steamResults !== false) {
                if (empty($steamResults['title'])) {
                    return false;
                }

                $game = $this->buildGameFromSteam($steamResults, $genreName);
            }
        }

        // Fall back to IGDB if Steam didn't find anything
        if ($this->igdbService->isConfigured()) {
            try {
                if ($steamGameID === null || empty($game)) {
                    $igdbGame = $this->igdbService->search($gameInfo['title']);
                    if ($igdbGame !== null) {
                        $this->_classUsed = 'IGDB';
                        $game = $this->igdbService->buildGameData($igdbGame, $genreName);
                    } else {
                        Cache::put("game_lookup_failed:{$titleKey}", true, self::FAILED_LOOKUP_CACHE_TTL);

                        return false;
                    }
                }
            } catch (ClientException $e) {
                if ($e->getCode() === 429) {
                    $this->igdbSleep = now()->endOfMonth();
                    Log::warning('GamesService: IGDB rate limit exceeded');
                }
            }
        }

        if (empty($game)) {
            Cache::put("game_lookup_failed:{$titleKey}", true, self::FAILED_LOOKUP_CACHE_TTL);

            return false;
        }

        // Save to database
        return $this->saveGameToDatabase($game, $genreName, $gen, $gameInfo, $titleKey);
    }

    /**
     * Build game array from Steam data.
     */
    protected function buildGameFromSteam(array $steamResults, string &$genreName): array
    {
        $game = [];

        if (! empty($steamResults['cover'])) {
            $game['coverurl'] = (string) $steamResults['cover'];
        }

        if (! empty($steamResults['backdrop'])) {
            $game['backdropurl'] = (string) $steamResults['backdrop'];
        }

        $game['title'] = (string) $steamResults['title'];
        $game['asin'] = $steamResults['steamid'];
        $game['url'] = (string) $steamResults['directurl'];
        $game['publisher'] = ! empty($steamResults['publisher']) ? (string) $steamResults['publisher'] : 'Unknown';
        $game['esrb'] = ! empty($steamResults['rating']) ? (string) $steamResults['rating'] : 'Not Rated';

        if (! empty($steamResults['releasedate'])) {
            $dateReleased = strtotime($steamResults['releasedate']) === false ? '' : $steamResults['releasedate'];
            $game['releasedate'] = (strtotime($steamResults['releasedate']) === false)
                ? null
                : Carbon::createFromFormat('M j, Y', Carbon::parse($dateReleased)->toFormattedDateString())->format('Y-m-d');
        }

        if (! empty($steamResults['description'])) {
            $game['review'] = (string) $steamResults['description'];
        }

        if (! empty($steamResults['genres'])) {
            $genreName = $this->igdbService->matchGenre($steamResults['genres']);
        }

        return $game;
    }

    /**
     * Save game to database.
     */
    protected function saveGameToDatabase(array $game, string $genreName, GenreService $gen, array $gameInfo, string $titleKey): bool|int
    {
        // Load genres
        $defaultGenres = $gen->loadGenres((string) GenreService::GAME_TYPE);

        // Prepare database values
        $game['cover'] = isset($game['coverurl']) ? 1 : 0;
        $game['backdrop'] = isset($game['backdropurl']) ? 1 : 0;
        if (! isset($game['trailer'])) {
            $game['trailer'] = 0;
        }
        if (empty($game['title'])) {
            $game['title'] = $gameInfo['title'];
        }
        if (! isset($game['releasedate'])) {
            $game['releasedate'] = '';
        }
        if (! isset($game['review'])) {
            $game['review'] = 'No Review';
        }
        $game['classused'] = $this->_classUsed;

        if (empty($genreName)) {
            $genreName = 'Unknown';
        }

        if (in_array(strtolower($genreName), $defaultGenres, false)) {
            $genreKey = array_search(strtolower($genreName), $defaultGenres, false);
        } else {
            $genreKey = Genre::query()->insertGetId(['title' => $genreName, 'type' => GenreService::GAME_TYPE]);
        }

        $game['gamesgenre'] = $genreName;
        $game['gamesgenreID'] = $genreKey;

        $gamesId = false;

        if (! empty($game['asin'])) {
            try {
                DB::beginTransaction();

                $check = GamesInfo::query()->where('asin', $game['asin'])->first();
                if ($check === null) {
                    $gamesId = GamesInfo::query()
                        ->insertGetId([
                            'title' => $game['title'],
                            'asin' => $game['asin'],
                            'url' => $game['url'],
                            'publisher' => $game['publisher'],
                            'genres_id' => $game['gamesgenreID'] === -1 ? null : $game['gamesgenreID'],
                            'esrb' => $game['esrb'],
                            'releasedate' => $game['releasedate'] !== '' ? $game['releasedate'] : null,
                            'review' => substr($game['review'], 0, 3000),
                            'cover' => $game['cover'],
                            'backdrop' => $game['backdrop'],
                            'trailer' => $game['trailer'],
                            'classused' => $game['classused'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                    Log::info('GamesService: Added new game', ['id' => $gamesId, 'title' => $game['title'], 'source' => $this->_classUsed]);
                } else {
                    $gamesId = $check['id'];
                    GamesInfo::query()
                        ->where('id', $gamesId)
                        ->update([
                            'title' => $game['title'],
                            'asin' => $game['asin'],
                            'url' => $game['url'],
                            'publisher' => $game['publisher'],
                            'genres_id' => $game['gamesgenreID'] === -1 ? null : $game['gamesgenreID'],
                            'esrb' => $game['esrb'],
                            'releasedate' => $game['releasedate'] !== '' ? $game['releasedate'] : null,
                            'review' => substr($game['review'], 0, 3000),
                            'cover' => $game['cover'],
                            'backdrop' => $game['backdrop'],
                            'trailer' => $game['trailer'],
                            'classused' => $game['classused'],
                        ]);

                    Log::debug('GamesService: Updated existing game', ['id' => $gamesId, 'title' => $game['title']]);
                }

                DB::commit();

                // Cache the successful lookup
                Cache::put("game_lookup:{$titleKey}", $game, self::GAME_CACHE_TTL);
                $this->matchedCount++;

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('GamesService: Database error saving game', [
                    'title' => $game['title'],
                    'error' => $e->getMessage(),
                ]);

                return false;
            }
        }

        if (! empty($gamesId)) {
            if ($this->echoOutput) {
                cli()->header('Added/updated game: ').
                cli()->alternateOver('   Title:    ').
                cli()->primary($game['title']).
                cli()->alternateOver('   Source:   ').
                cli()->primary($this->_classUsed);
            }

            // Save cover image
            if ($game['cover'] === 1 && isset($game['coverurl'])) {
                $game['cover'] = $this->imageService->saveImage((string) $gamesId, $game['coverurl'], $this->imgSavePath, 250, 250);
            }

            // Save backdrop image
            if ($game['backdrop'] === 1 && isset($game['backdropurl'])) {
                $game['backdrop'] = $this->imageService->saveImage($gamesId.'-backdrop', $game['backdropurl'], $this->imgSavePath, 1920, 1024);
            }
        } elseif ($this->echoOutput) {
            cli()->headerOver('Nothing to update: ').
            cli()->primary($game['title'].' (PC)');
        }

        return $gamesId !== false ? $gamesId : false;
    }

    /**
     * Save game info from cached data.
     */
    protected function saveGameInfoFromCache(array $game, GenreService $gen, array $gameInfo): bool|int
    {
        $defaultGenres = $gen->loadGenres((string) GenreService::GAME_TYPE);
        $genreName = $game['gamesgenre'] ?? 'Unknown';

        if (in_array(strtolower($genreName), $defaultGenres, false)) {
            $genreKey = array_search(strtolower($genreName), $defaultGenres, false);
        } else {
            $genreKey = Genre::query()->insertGetId(['title' => $genreName, 'type' => GenreService::GAME_TYPE]);
        }

        $game['gamesgenreID'] = $genreKey;

        if (! empty($game['asin'])) {
            $check = GamesInfo::query()->where('asin', $game['asin'])->first();
            if ($check !== null) {
                return $check['id'];
            }
        }

        return false;
    }

    // ========================================
    // Release Processing Methods
    // ========================================

    /**
     * Process game releases.
     *
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
                cli()->header('Processing '.$res->count().' games release(s).');
            }

            Log::info('GamesService: Starting processing', ['count' => $res->count()]);

            $releaseUpdates = [];

            foreach ($res as $arr) {
                $this->processedCount++;
                $this->maxHitRequest = false;

                $gameInfo = $this->parseTitle($arr['searchname']);

                if ($gameInfo !== false) {
                    if ($this->echoOutput) {
                        cli()->info('Looking up: '.$gameInfo['title'].' (PC)');
                    }

                    // Check for existing games entry
                    $gameCheck = $this->getGamesInfoByName($gameInfo['title']);

                    if ($gameCheck === false) {
                        $gameId = $this->updateGamesInfo($gameInfo);
                        if ($gameId === false) {
                            $gameId = -2;
                            $this->failedCount++;
                        }
                    } else {
                        $gameId = $gameCheck['id'];
                        $this->cachedCount++;
                    }

                    $releaseUpdates[$arr['id']] = $gameId;
                } else {
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

            Log::info('GamesService: Processing completed', [
                'processed' => $this->processedCount,
                'matched' => $this->matchedCount,
                'cached' => $this->cachedCount,
                'failed' => $this->failedCount,
                'elapsed_seconds' => $elapsed,
            ]);

            if ($this->echoOutput) {
                cli()->header(sprintf(
                    'Games processing complete: %d processed, %d matched, %d cached, %d failed (%.2fs)',
                    $this->processedCount,
                    $this->matchedCount,
                    $this->cachedCount,
                    $this->failedCount,
                    $elapsed
                ));
            }
        } elseif ($this->echoOutput) {
            cli()->header('No games releases to process.');
        }
    }

    /**
     * Parse release title.
     *
     * @return array{title: string, release: string}|false
     */
    public function parseTitle(string $releaseName): array|false
    {
        return $this->titleParser->parse($releaseName);
    }

    /**
     * Batch update release records with game IDs.
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

            Log::debug('GamesService: Batch updated releases', ['count' => count($updates)]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('GamesService: Failed to batch update releases', ['error' => $e->getMessage()]);

            // Fall back to individual updates
            foreach ($updates as $releaseId => $gameId) {
                try {
                    Release::query()
                        ->where('id', '=', $releaseId)
                        ->where('categories_id', '=', Category::PC_GAMES)
                        ->update(['gamesinfo_id' => $gameId]);
                } catch (\Exception $e) {
                    Log::error('GamesService: Failed to update release', [
                        'release_id' => $releaseId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    // ========================================
    // Utility Methods
    // ========================================

    /**
     * Generate a consistent cache key from a game title.
     */
    protected function generateCacheKey(string $title): string
    {
        return md5(mb_strtolower(trim($title)));
    }

    /**
     * Get processing statistics from the last run.
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
     */
    public function clearLookupCaches(): void
    {
        Log::info('GamesService: Lookup caches cleared');
    }

    /**
     * Match genre name to known genres.
     */
    public function matchGenreName(string $gameGenre): bool|string
    {
        return $this->igdbService->isKnownGenre($gameGenre) ? $gameGenre : false;
    }
}
