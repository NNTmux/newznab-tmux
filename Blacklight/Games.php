<?php

namespace Blacklight;

use App\Models\Genre;
use Blacklight\db\DB;
use App\Models\Release;
use App\Models\Category;
use App\Models\Settings;
use App\Models\GamesInfo;
use Illuminate\Support\Carbon;
use DBorsatto\GiantBomb\Client;
use DBorsatto\GiantBomb\Config;
use Illuminate\Support\Facades\Cache;

class Games
{
    protected const GAME_MATCH_PERCENTAGE = 85;

    protected const GAMES_TITLE_PARSE_REGEX =
        '#(?P<title>[\w\s\.]+)(-(?P<relgrp>FLT|RELOADED|SKIDROW|PROPHET|RAZOR1911|CORE|REFLEX))?\s?(\s*(\(?('.
        '(?P<reltype>PROPER|MULTI\d|RETAIL|CRACK(FIX)?|ISO|(RE)?(RIP|PACK))|(?P<year>(19|20)\d{2})|V\s?'.
        '(?P<version>(\d+\.)+\d+)|(-\s)?(?P=relgrp))\)?)\s?)*\s?(\.\w{2,4})?#i';

    /**
     * @var bool
     */
    public $echoOutput;

    /**
     * @var int|null|string
     */
    public $gameQty;

    /**
     * @var string
     */
    public $imgSavePath;

    /**
     * @var int
     */
    public $matchPercentage;

    /**
     * @var bool
     */
    public $maxHitRequest;

    /**
     * @var \Blacklight\db\DB
     */
    public $pdo;

    /**
     * @var array|bool|string
     */
    public $publicKey;

    /**
     * @var string
     */
    public $renamed;

    /**
     * @var array|bool|int|string
     */
    public $sleepTime;

    /**
     * @var string
     */
    protected $_classUsed;

    /**
     * @var string
     */
    protected $_gameID;

    /**
     * @var array|bool
     */
    protected $_gameResults;

    /**
     * @var
     */
    protected $_getGame;

    /**
     * @var int
     */
    protected $_resultsFound = 0;

    /**
     * @var array|bool|int|string
     */
    public $catWhere;

    /**
     * @var \DBorsatto\GiantBomb\Config
     */
    protected $config;

    /**
     * @var \DBorsatto\GiantBomb\Client
     */
    protected $giantBomb;

    /**
     * Games constructor.
     *
     * @param array $options
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Echo'     => false,
            'ColorCLI' => null,
            'Settings' => null,
        ];
        $options += $defaults;
        $this->echoOutput = ($options['Echo'] && config('nntmux.echocli'));

        $this->pdo = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());

        $this->publicKey = Settings::settingValue('APIs..giantbombkey');
        $this->gameQty = Settings::settingValue('..maxgamesprocessed') !== '' ? (int) Settings::settingValue('..maxgamesprocessed') : 150;
        $this->imgSavePath = NN_COVERS.'games'.DS;
        $this->renamed = (int) Settings::settingValue('..lookupgames') === 2 ? 'AND isrenamed = 1' : '';
        $this->matchPercentage = 60;
        $this->maxHitRequest = false;
        $this->catWhere = 'AND categories_id = '.Category::PC_GAMES.' ';
        if ($this->publicKey !== '') {
            $this->config = new Config($this->publicKey);
            $this->giantBomb = new Client($this->config);
        }
    }

    /**
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getGamesInfoById($id)
    {
        return GamesInfo::query()
            ->where('gamesinfo.id', $id)
            ->leftJoin('genres as g', 'g.id', '=', 'gamesinfo.genres_id')
            ->select(['gamesinfo.*', 'g.title as genres'])
            ->first();
    }

    /**
     * @param string $title
     *
     * @return array|bool
     */
    public function getGamesInfoByName($title)
    {
        $bestMatch = false;

        if (empty($title)) {
            return $bestMatch;
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
                if ($percent === 100) {
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
     * @param $start
     * @param $num
     *
     * @return array
     */
    public function getRange($start, $num): array
    {
        return $this->pdo->query(
            sprintf(
                'SELECT gi.*, g.title AS genretitle FROM gamesinfo gi INNER JOIN genres g ON gi.genres_id = g.id ORDER BY created_at DESC %s',
                ($start === false ? '' : 'LIMIT '.$num.' OFFSET '.$start)
            )
        );
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        $res = GamesInfo::query()->count(['id']);

        return $res ?? 0;
    }

    /**
     * @param       $page
     * @param       $cat
     * @param array $excludedcats
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|mixed
     * @throws \Exception
     */
    public function getGamesRange($page, $cat, array $excludedcats = [])
    {
        $sql = Release::query()->selectRaw("GROUP_CONCAT(r.id ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_id,
					GROUP_CONCAT(r.rarinnerfilecount ORDER BY r.postdate DESC SEPARATOR ',') as grp_rarinnerfilecount,
					GROUP_CONCAT(r.haspreview ORDER BY r.postdate DESC SEPARATOR ',') AS grp_haspreview,
					GROUP_CONCAT(r.passwordstatus ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_password,
					GROUP_CONCAT(r.guid ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_guid,
					GROUP_CONCAT(rn.releases_id ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_nfoid,
					GROUP_CONCAT(g.name ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_grpname,
					GROUP_CONCAT(r.searchname ORDER BY r.postdate DESC SEPARATOR '#') AS grp_release_name,
					GROUP_CONCAT(r.postdate ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_postdate,
					GROUP_CONCAT(r.size ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_size,
					GROUP_CONCAT(r.totalpart ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_totalparts,
					GROUP_CONCAT(r.comments ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_comments,
					GROUP_CONCAT(r.grabs ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_grabs,
					GROUP_CONCAT(df.failed ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_failed")
            ->select(
                [
                    'gi.*',
                    'releases.gamesinfo_id',
                    'g.name as group_name',
                    'gn.title as genre',
                    'rn.releases_id as nfoid',
                ]
            )
            ->from('releases as r')
            ->leftJoin('groups as g', 'g.id', '=', 'r.groups_id')
            ->leftJoin('release_nfos as rn', 'rn.releases_id', '=', 'r.id')
            ->leftJoin('dnzb_failures as df', 'df.release_id', '=', 'r.id')
            ->join('gamesinfo as gi', 'gi.id', '=', 'r.gamesinfo_id')
            ->join('genres as gn', 'gi.genres_id', '=', 'gn.id')
            ->where('r.nzbstatus', '=', 1)
            ->where('gi.title', '!=', '')
            ->where('gi.cover', '=', 1);
        Releases::showPasswords($sql, true);
        if (\count($excludedcats) > 0) {
            $sql->whereNotIn('r.categories_id', $excludedcats);
        }

        if (\count($cat) > 0 && $cat[0] !== -1) {
            Category::getCategorySearch($cat, $sql, true);
        }

        $sql->groupBy('gi.id')
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
     * @param string|array $orderBy
     *
     * @return array
     */
    public function getGamesOrder($orderBy): array
    {
        $order = $orderBy === '' ? 'r.postdate' : $orderBy;
        $orderArr = explode('_', $order);
        switch ($orderArr[0]) {
            case 'title':
                $orderField = 'gi.title';
                break;
            case 'releasedate':
                $orderField = 'gi.releasedate';
                break;
            case 'genre':
                $orderField = 'gi.genres_id';
                break;
            case 'size':
                $orderField = 'r.size';
                break;
            case 'files':
                $orderField = 'r.totalpart';
                break;
            case 'stats':
                $orderField = 'r.grabs';
                break;
            case 'posted':
            default:
                $orderField = 'r.postdate';
                break;
        }
        $orderSort = (isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1])) ? $orderArr[1] : 'desc';

        return [$orderField, $orderSort];
    }

    /**
     * @return array
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
     * @return array
     */
    public function getBrowseByOptions(): array
    {
        return ['title' => 'title', 'genre' => 'genres_id', 'year' => 'year'];
    }

    /**
     * @return string
     */
    public function getBrowseBy(): string
    {
        $browseBy = ' ';
        $browseByArr = $this->getBrowseByOptions();

        foreach ($browseByArr as $bbk => $bbv) {
            if (isset($_REQUEST[$bbk]) && ! empty($_REQUEST[$bbk])) {
                $bbs = stripslashes($_REQUEST[$bbk]);
                if ($bbk === 'year') {
                    $browseBy .= 'AND YEAR (gi.releasedate) '.$this->pdo->likeString($bbs, true, true);
                } else {
                    $browseBy .= 'AND gi.'.$bbv.' '.$this->pdo->likeString($bbs, true, true);
                }
            }
        }

        return $browseBy;
    }

    /**
     * Updates the game for game-edit.php.
     *
     * @param $id
     * @param $title
     * @param $asin
     * @param $url
     * @param $publisher
     * @param $releaseDate
     * @param $esrb
     * @param $cover
     * @param $trailerUrl
     * @param $genreID
     */
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
                    'genres_id' =>$genreID,
                ]
            );
    }

    /**
     * Process each game, updating game information from Steam and Giantbomb.
     *
     * @param $gameInfo
     *
     * @return bool
     * @throws \Exception
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function updateGamesInfo($gameInfo): bool
    {
        //wait 10 seconds before proceeding (steam api limit)
        sleep(10);
        $gen = new Genres(['Settings' => $this->pdo]);
        $ri = new ReleaseImage();

        $game = [];

        // Process Steam first before GiantBomb as Steam has more details
        $this->_gameResults = false;
        $genreName = '';
        $this->_getGame = new Steam(['DB' => $this->pdo]);
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
                    $dateReleased = $this->_gameResults['releasedate'];
                    $date = Carbon::createFromFormat('M j, Y', Carbon::parse($dateReleased)->toFormattedDateString());
                    if ($date instanceof \DateTime) {
                        $game['releasedate'] = $date->format('Y-m-d');
                    }
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

        if ($this->publicKey !== '') {
            if ($steamGameID === false || $this->_gameResults === false) {
                $bestMatch = false;
                $this->_classUsed = 'GiantBomb';
                $result = $this->giantBomb->search($gameInfo['title'], 'Game');

                if (! \is_object($result)) {
                    foreach ($result as $res) {
                        similar_text(strtolower($gameInfo['title']), strtolower($res->name), $percent1);
                        similar_text(strtolower($gameInfo['title']), strtolower($res->aliases), $percent2);
                        if ($percent1 >= self::GAME_MATCH_PERCENTAGE || $percent2 >= self::GAME_MATCH_PERCENTAGE) {
                            $bestMatch = $res->id;
                        }
                    }

                    if ($bestMatch !== false) {
                        $this->_gameResults = $this->giantBomb->findOne('Game', '3030-'.$bestMatch);

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
                            $game['publisher'] = (string) $this->_gameResults->publishers[0]['name'];
                        } else {
                            $game['publisher'] = 'Unknown';
                        }

                        if (! empty($this->_gameResults->original_game_rating[0]['name'])) {
                            $game['esrb'] = (string) $this->_gameResults->original_game_rating[0]['name'];
                        } else {
                            $game['esrb'] = 'Not Rated';
                        }

                        if ($this->_gameResults->original_release_date !== '') {
                            $dateReleased = $this->_gameResults->original_release_date;
                            $date = $dateReleased !== null ? Carbon::createFromFormat('Y-m-d H:i:s', $dateReleased) : Carbon::now();
                            if ($date instanceof \DateTime) {
                                $game['releasedate'] = (string) $date->format('Y-m-d');
                            }
                        }

                        if ($this->_gameResults->deck !== '') {
                            $game['review'] = (string) $this->_gameResults->deck;
                        }
                    } else {
                        ColorCLI::doEcho(ColorCLI::notice('GiantBomb returned no valid results'), true);

                        return false;
                    }
                } else {
                    ColorCLI::doEcho(ColorCLI::notice('GiantBomb found no valid results'), true);

                    return false;
                }
            }
        }

        // Load genres.
        $defaultGenres = $gen->getGenres(Genres::GAME_TYPE);
        $genreAssoc = [];
        foreach ($defaultGenres as $dg) {
            $genreAssoc[$dg['id']] = strtolower($dg['title']);
        }

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

        if (\in_array(strtolower($genreName), $genreAssoc, false)) {
            $genreKey = array_search(strtolower($genreName), $genreAssoc, false);
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
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
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
                ColorCLI::doEcho(
                    ColorCLI::header('Added/updated game: ').
                    ColorCLI::alternateOver('   Title:    ').
                    ColorCLI::primary($game['title']).
                    ColorCLI::alternateOver('   Source:   ').
                    ColorCLI::primary($this->_classUsed), true
                );
            }
            if ($game['cover'] === 1) {
                $game['cover'] = $ri->saveImage($gamesId, $game['coverurl'], $this->imgSavePath, 250, 250);
            }
            if ($game['backdrop'] === 1) {
                $game['backdrop'] = $ri->saveImage($gamesId.'-backdrop', $game['backdropurl'], $this->imgSavePath, 1920, 1024);
            }
        } else {
            if ($this->echoOutput) {
                ColorCLI::doEcho(
                    ColorCLI::headerOver('Nothing to update: ').
                    ColorCLI::primary($game['title'].' (PC)'), true
                );
            }
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
        $res = $this->pdo->queryDirect(
            sprintf(
                '
				SELECT searchname, id
				FROM releases
				WHERE nzbstatus = 1 %s
				AND gamesinfo_id = 0 %s
				ORDER BY postdate DESC
				LIMIT %d',
                $this->renamed,
                $this->catWhere,
                $this->gameQty
            )
        );

        if ($res instanceof \Traversable && $res->rowCount() > 0) {
            if ($this->echoOutput) {
                ColorCLI::doEcho(ColorCLI::header('Processing '.$res->rowCount().' games release(s).'), true);
            }

            foreach ($res as $arr) {

                // Reset maxhitrequest
                $this->maxHitRequest = false;

                $gameInfo = $this->parseTitle($arr['searchname']);
                if ($gameInfo !== false) {
                    if ($this->echoOutput) {
                        ColorCLI::doEcho(
                            ColorCLI::headerOver('Looking up: ').
                            ColorCLI::primary($gameInfo['title'].' (PC)'), true
                        );
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
                    $this->pdo->queryExec(sprintf('UPDATE releases SET gamesinfo_id = %d WHERE id = %d %s', $gameId, $arr['id'], $this->catWhere));
                } else {
                    // Could not parse release title.
                    $this->pdo->queryExec(sprintf('UPDATE releases SET gamesinfo_id = %d WHERE id = %d %s', -2, $arr['id'], $this->catWhere));

                    if ($this->echoOutput) {
                        echo '.';
                    }
                }
            }
        } else {
            if ($this->echoOutput) {
                ColorCLI::doEcho(ColorCLI::header('No games releases to process.'), true);
            }
        }
    }

    /**
     * Parse the game release title.
     *
     * @param string $releaseName
     *
     * @return array|bool
     */
    public function parseTitle($releaseName)
    {

        // Get name of the game from name of release.
        if (preg_match(self::GAMES_TITLE_PARSE_REGEX, preg_replace('/\sMulti\d?\s/i', '', $releaseName), $matches)) {
            // Replace dots, underscores, colons, or brackets with spaces.
            $result = [];
            $result['title'] = str_replace(' RF ', ' ', preg_replace('/(\-|\:|\.|_|\%20|\[|\])/', ' ', $matches['title']));
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
     *
     * @param $gameGenre
     *
     * @return bool|string
     */
    public function matchGenreName($gameGenre)
    {
        $str = '';

        //Game genres
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

    /**
     * Matches Genres.
     *
     * @param string $genre
     *
     * @return string
     */
    protected function _matchGenre($genre = ''): string
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
