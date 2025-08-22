<?php

namespace Blacklight;

use App\Models\Category;
use App\Models\GamesInfo;
use App\Models\Genre;
use App\Models\Release;
use App\Models\Settings;
use DBorsatto\GiantBomb\Client;
use DBorsatto\GiantBomb\Configuration;
use DBorsatto\GiantBomb\Exception\ApiCallerException;
use DBorsatto\GiantBomb\Exception\ModelException;
use DBorsatto\GiantBomb\Exception\SdkException;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use MarcReichel\IGDBLaravel\Exceptions\InvalidParamsException;
use MarcReichel\IGDBLaravel\Exceptions\MissingEndpointException;
use MarcReichel\IGDBLaravel\Models\Company;
use MarcReichel\IGDBLaravel\Models\Game;

/**
 * Class Games.
 */
class Games
{
    protected const GAME_MATCH_PERCENTAGE = 85;

    protected const GAMES_TITLE_PARSE_REGEX =
        '#(?P<title>[\w\s\.]+)(-(?P<relgrp>FLT|RELOADED|SKIDROW|PROPHET|RAZOR1911|CORE|REFLEX))?\s?(\s*(\(?('.
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

    protected Configuration $config;

    protected Client $giantBomb;

    protected int $igdbSleep;

    protected ColorCLI $colorCli;

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

        $this->publicKey = config('nntmux_api.giantbomb_api_key');
        $this->gameQty = Settings::settingValue('maxgamesprocessed') !== '' ? (int) Settings::settingValue('maxgamesprocessed') : 150;
        $this->imgSavePath = config('nntmux_settings.covers_path').'/games/';
        $this->renamed = (int) Settings::settingValue('lookupgames') === 2 ? 'AND isrenamed = 1' : '';
        $this->matchPercentage = 60;
        $this->maxHitRequest = false;
        $this->catWhere = 'AND categories_id = '.Category::PC_GAMES.' ';
        if ($this->publicKey !== '') {
            $this->config = new Configuration($this->publicKey);
            $this->giantBomb = new Client($this->config);
        }
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
            foreach ($results as $result) {
                // If we have an exact string match set best match and break out
                if ($result['title'] === $title) {
                    $bestMatch = $result;
                    break;
                }
                similar_text(strtolower($result['title']), strtolower($title), $percent);
                // If similar_text reports an exact match set best match and break out
                if ((int) $percent === 100) {
                    $bestMatch = $result;
                    break;
                }
                if ($percent >= self::GAME_MATCH_PERCENTAGE && $percent > $bestMatchPct) {
                    $bestMatch = $result;
                    $bestMatchPct = $percent;
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
            sprintf(
                "
				SELECT SQL_CALC_FOUND_ROWS gi.id,
					GROUP_CONCAT(r.id ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_id
				FROM gamesinfo gi
				LEFT JOIN releases r ON gi.id = r.gamesinfo_id
				WHERE gi.title != ''
				AND gi.cover = 1
				AND r.passwordstatus %s
				%s %s %s %s
				GROUP BY gi.id
				ORDER BY %s %s %s",
                (new Releases)->showPasswords(),
                $browseBy,
                $catsrch,
                $maxAge,
                $exccatlist,
                $order[0],
                $order[1],
                ($start === false ? '' : ' LIMIT '.$num.' OFFSET '.$start)
            );
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
            sprintf(
                '
				SELECT
					r.id, r.rarinnerfilecount, r.grabs, r.comments, r.totalpart, r.size, r.postdate, r.searchname, r.haspreview, r.passwordstatus, r.guid, g.name AS group_name, df.failed AS failed,
				gi.*, YEAR (gi.releasedate) as year, r.gamesinfo_id,
				rn.releases_id AS nfoid
				FROM releases r
				LEFT OUTER JOIN usenet_groups g ON g.id = r.groups_id
				LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
				LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
				INNER JOIN gamesinfo gi ON gi.id = r.gamesinfo_id
				WHERE gi.id IN (%s)
				AND r.id IN (%s)
				%s
				GROUP BY gi.id
				ORDER BY %s %s',
                (\is_array($gameIDs) ? implode(',', $gameIDs) : -1),
                (\is_array($releaseIDs) ? implode(',', $releaseIDs) : -1),
                $catsrch,
                $order[0],
                $order[1]
            );
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
        // wait 10 seconds before proceeding (steam api limit)
        sleep(10);
        $gen = new Genres(['Settings' => null]);
        $ri = new ReleaseImage;

        $game = [];

        // Process Steam first before GiantBomb as Steam has more details
        $this->_gameResults = false;
        $genreName = '';
        $this->_getGame = new Steam(['DB' => null]);
        $this->_classUsed = 'Steam';

        $steamGameID = $this->_getGame->search($gameInfo['title']);

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

        if (! empty($this->publicKey)) {
            if ($steamGameID === false || $this->_gameResults === false) {
                $bestMatch = false;
                $this->_classUsed = 'GiantBomb';
                try {
                    $result = $this->giantBomb->search($gameInfo['title'], 'Game');
                    if (! \is_object($result)) {
                        $bestMatchPct = 0;
                        foreach ($result as $res) {
                            similar_text(strtolower($gameInfo['title']), strtolower($res->name), $percent1);
                            if ($percent1 >= self::GAME_MATCH_PERCENTAGE && $percent1 > $bestMatchPct) {
                                $bestMatch = $res->id;
                                $bestMatchPct = $percent1;
                            }
                        }

                        if ($bestMatch !== false) {
                            $this->_gameResults = $this->giantBomb->findWithResourceID('Game', '3030-'.$bestMatch);

                            if (! empty($this->_gameResults->image['medium_url'])) {
                                $game['coverurl'] = (string) $this->_gameResults->image['medium_url'];
                            }

                            if (! empty($this->_gameResults->image['screen_url'])) {
                                $game['backdropurl'] = (string) $this->_gameResults->image['screen_url'];
                            }

                            $game['title'] = (string) $this->_gameResults->get('name');
                            $game['asin'] = $this->_gameResults->get('id');
                            if (! empty($this->_gameResults->get('site_detail_url'))) {
                                $game['url'] = (string) $this->_gameResults->get('site_detail_url');
                            } else {
                                $game['url'] = '';
                            }

                            if ($this->_gameResults->get('publishers') !== '') {
                                $game['publisher'] = $this->_gameResults->publishers[0]['name'] ?? 'Unknown';
                            } else {
                                $game['publisher'] = 'Unknown';
                            }

                            if (! empty($this->_gameResults->original_game_rating[0]['name'])) {
                                $game['esrb'] = $this->_gameResults->original_game_rating[0]['name'] ?? 'Not Rated';
                            } else {
                                $game['esrb'] = 'Not Rated';
                            }

                            if ($this->_gameResults->original_release_date !== '' && strtotime($this->_gameResults->original_release_date !== false)) {
                                $dateReleased = $this->_gameResults->original_release_date;
                                $date = $dateReleased !== null ? Carbon::createFromFormat('Y-m-d H:i:s', $dateReleased) : now();
                                $game['releasedate'] = (string) $date->format('Y-m-d');
                            }

                            if ($this->_gameResults->deck !== '') {
                                $game['review'] = (string) $this->_gameResults->deck;
                            }
                        } else {
                            $this->colorCli->notice('GiantBomb returned no valid results');

                            return false;
                        }
                    } else {
                        $this->colorCli->notice('GiantBomb found no valid results');

                        return false;
                    }
                } catch (ApiCallerException $e) {
                    return false;
                }
            }
        }

        if (config('config.credentials.client_id') !== '' && config('config.credentials.client_secret') !== '') {
            try {
                if ($steamGameID === false || $this->_gameResults === false) {
                    $bestMatch = false;
                    $this->_classUsed = 'IGDB';
                    $result = Game::where('name', $gameInfo['title'])->get();
                    if (! empty($result)) {
                        $bestMatchPct = 0;
                        foreach ($result as $res) {
                            similar_text(strtolower($gameInfo['title']), strtolower($res->name), $percent1);
                            if ($percent1 >= self::GAME_MATCH_PERCENTAGE && $percent1 > $bestMatchPct) {
                                $bestMatch = $res->id;
                                $bestMatchPct = $percent1;
                            }
                        }
                        if ($bestMatch !== false) {
                            $this->_gameResults = Game::with([
                                'cover' => ['url'],
                                'screenshots' => ['url'],
                                'involved_companies' => ['company', 'publisher'],
                                'themes',
                            ])->where('id', $bestMatch)->first();

                            $publishers = [];
                            if (! empty($this->_gameResults->involved_companies)) {
                                foreach ($this->_gameResults->involved_companies as $publisher) {
                                    if ($publisher['publisher'] === true) {
                                        $company = Company::find($publisher['company']);
                                        $publishers[] = $company['name'];
                                    }
                                }
                            }

                            $genres = [];

                            if (! empty($this->_gameResults->themes)) {
                                foreach ($this->_gameResults->themes as $theme) {
                                    $genres[] = $theme['name'];
                                }
                            }

                            $genreName = $this->_matchGenre(implode(',', $genres));

                            $releaseDate = now()->format('Y-m-d');
                            if (isset($this->_gameResults->first_release_date) && strtotime($this->_gameResults->first_release_date) !== false) {
                                $releaseDate = $this->_gameResults->first_release_date->format('Y-m-d');
                            }

                            $game = [
                                'title' => $this->_gameResults->name,
                                'asin' => $this->_gameResults->id,
                                'review' => $this->_gameResults->summary ?? '',
                                'coverurl' => isset($this->_gameResults->cover) ? 'https:'.$this->_gameResults->cover['url'] : '',
                                'releasedate' => $releaseDate,
                                'esrb' => isset($this->_gameResults->aggregated_rating) ? round($this->_gameResults->aggregated_rating).'%' : 'Not Rated',
                                'url' => $this->_gameResults->url ?? '',
                                'backdropurl' => isset($this->_gameResults->screenshots) ? 'https:'.str_replace('t_thumb', 't_cover_big', $this->_gameResults->screenshots[0]['url']) : '',
                                'publisher' => ! empty($publishers) ? implode(',', $publishers) : 'Unknown',
                            ];
                        } else {
                            $this->colorCli->notice('IGDB returned no valid results');

                            return false;
                        }
                    } else {
                        $this->colorCli->notice('IGDB found no valid results');

                        return false;
                    }
                }
            } catch (ClientException $e) {
                if ($e->getCode() === 429) {
                    $this->igdbSleep = now()->endOfMonth();
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
        $query = Release::query()
            ->where('nzbstatus', '=', 1)
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

            foreach ($res as $arr) {
                // Reset maxhitrequest
                $this->maxHitRequest = false;

                $gameInfo = $this->parseTitle($arr['searchname']);
                if ($gameInfo !== false) {
                    if ($this->echoOutput) {
                        $this->colorCli->climate()->info('Looking up: '.$gameInfo['title'].' (PC)');
                    }
                    // Check for existing games entry.
                    $gameCheck = $this->getGamesInfoByName($gameInfo['title']);

                    if ($gameCheck === false) {
                        $gameId = $this->updateGamesInfo($gameInfo);
                        if ($gameId === false) {
                            $gameId = -2;

                            // Leave gamesinfo_id 0 to parse again
                            if ($this->maxHitRequest === true) {
                                $gameId = 0;
                            }
                        }
                    } else {
                        $gameId = $gameCheck['id'];
                    }
                    // Update release.
                    Release::query()->where('id', '=', $arr['id'])->where('categories_id', '=', Category::PC_GAMES)->update(['gamesinfo_id' => $gameId]);
                } else {
                    // Could not parse release title.
                    Release::query()->where('id', '=', $arr['id'])->where('categories_id', '=', Category::PC_GAMES)->update(['gamesinfo_id' => -2]);

                    if ($this->echoOutput) {
                        echo '.';
                    }
                }
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
        // Get name of the game from name of release.
        if (preg_match(self::GAMES_TITLE_PARSE_REGEX, preg_replace('/\sMulti\d?\s/i', '', $releaseName), $hits)) {
            // Replace dots, underscores, colons, or brackets with spaces.
            $result = [];
            $result['title'] = str_replace(' RF ', ' ', preg_replace('/(\-|\:|\.|_|\%20|\[|\])/', ' ', $hits['title']));
            // Replace any foreign words at the end of the release
            $result['title'] = preg_replace('/(brazilian|chinese|croatian|danish|deutsch|dutch|english|estonian|flemish|finnish|french|german|greek|hebrew|icelandic|italian|latin|nordic|norwegian|polish|portuguese|japenese|japanese|russian|serbian|slovenian|spanish|spanisch|swedish|thai|turkish)$/i', '', $result['title']);
            // Remove PC ISO) ( from the beginning bad regex from Games category?
            $result['title'] = preg_replace('/^(PC\sISO\)\s\()/i', '', $result['title']);
            // Finally remove multiple spaces and trim leading spaces.
            $result['title'] = trim(preg_replace('/\s{2,}/', ' ', $result['title']));
            if (empty($result['title'])) {
                return false;
            }
            $result['release'] = $releaseName;

            return array_map('trim', $result);
        }

        return false;
    }

    /**
     * See if genre name exists.
     */
    public function matchGenreName($gameGenre): bool|string
    {
        $str = '';

        // Game genres
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
            case 'Simulation':
            case 'Sports':
            case 'Strategy':
            case 'Trivia':
                $str = $gameGenre;
                break;
        }

        return ($str !== '') ? $str : false;
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
}
