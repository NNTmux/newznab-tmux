<?php
namespace nntmux;

use app\models\Settings;
use DBorsatto\GiantBomb\Config;
use DBorsatto\GiantBomb\Client;
use nntmux\db\DB;


class Games
{
	const GAME_MATCH_PERCENTAGE = 85;

	const GAMES_TITLE_PARSE_REGEX =
		'#(?P<title>[\w\s\.]+)(-(?P<relgrp>FLT|RELOADED|SKIDROW|PROPHET|RAZOR1911|CORE|REFLEX))?\s?(\s*(\(?(' .
		'(?P<reltype>PROPER|MULTI\d|RETAIL|CRACK(FIX)?|ISO|(RE)?(RIP|PACK))|(?P<year>(19|20)\d{2})|V\s?' .
		'(?P<version>(\d+\.)+\d+)|(-\s)?(?P=relgrp))\)?)\s?)*\s?(\.\w{2,4})?#i';

/**
	 * @var bool
	 */
	public $echoOutput;

	/**
	 * @var array|bool|int|string
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
	 * @var DB
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
	 * @var array
	 */
	protected $_gameResults;

	/**
	 * @var Steam
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
	 * @param array $options Class instances / Echo to cli.
	 */
	public function __construct(array $options = [])
	{
		$defaults = [
			'Echo'     => false,
			'ColorCLI' => null,
			'Settings' => null,
		];
		$options += $defaults;
		$this->echoOutput = ($options['Echo'] && NN_ECHOCLI);

		$this->pdo = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());

		$this->publicKey = Settings::value('APIs..giantbombkey');
		$this->gameQty = Settings::value('..maxgamesprocessed') !== '' ? Settings::value('..maxgamesprocessed') : 150;
		$this->imgSavePath = NN_COVERS . 'games' . DS;
		$this->renamed = Settings::value('..lookupgames') === 2 ? 'AND isrenamed = 1' : '';
		$this->matchPercentage = 60;
		$this->maxHitRequest = false;
		$this->catWhere = 'AND categories_id = ' . Category::PC_GAMES . ' ';
		$this->config = new Config($this->publicKey);
		$this->giantbomb = new Client($this->config);
	}

	/**
	 * @param $id
	 *
	 * @return array|bool
	 */
	public function getGamesInfoById($id)
	{
		return $this->pdo->queryOneRow(
			sprintf('
				SELECT gi.*, g.title AS genres
				FROM gamesinfo gi
				LEFT OUTER JOIN genres g ON g.id = gi.genres_id
				WHERE gi.id = %d',
				$id
			)
		);
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

		$results = $this->pdo->queryDirect("
			SELECT *
			FROM gamesinfo
			WHERE MATCH(title) AGAINST({$this->pdo->escapeString($title)})
			LIMIT 20"
		);

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
				'SELECT gi.*, g.title AS genretitle FROM gamesinfo gi INNER JOIN genres g ON gi.genres_id = g.id ORDER BY createddate DESC %s',
				($start === false ? '' : 'LIMIT ' . $num . ' OFFSET ' . $start)
			)
		);
	}

	/**
	 * @return int
	 */
	public function getCount(): int
	{
		$res = $this->pdo->queryOneRow('SELECT COUNT(id) AS num FROM gamesinfo');
		return ($res === false ? 0 : $res['num']);
	}

	/**
	 * @param       $cat
	 * @param       $start
	 * @param       $num
	 * @param       $orderBy
	 * @param int|string   $maxAge
	 * @param array $excludedCats
	 *
	 * @return array
	 */
	public function getGamesRange($cat, $start, $num, $orderBy, $maxAge = '', array $excludedCats = []): array
	{
		$browseBy = $this->getBrowseBy();

		$catsrch = '';
		if (count($cat) > 0 && $cat[0] !== -1) {
			$catsrch = (new Category(['Settings' => $this->pdo]))->getCategorySearch($cat);
		}

		if ($maxAge > 0) {
			$maxAge = sprintf(' AND r.postdate > NOW() - INTERVAL %d DAY ', $maxAge);
		}

		$exccatlist = '';
		if (count($excludedCats) > 0) {
			$exccatlist = ' AND r.categories_id NOT IN (' . implode(',', $excludedCats) . ')';
		}

		$order = $this->getGamesOrder($orderBy);

		$games = $this->pdo->queryCalc(
				sprintf("
				SELECT SQL_CALC_FOUND_ROWS gi.id,
					GROUP_CONCAT(r.id ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_id
				FROM gamesinfo gi
				LEFT JOIN releases r ON gi.id = r.gamesinfo_id
				WHERE r.nzbstatus = 1
				AND gi.title != ''
				AND gi.cover = 1
				AND r.passwordstatus %s
				%s %s %s %s
				GROUP BY gi.id
				ORDER BY %s %s %s",
						Releases::showPasswords(),
						$browseBy,
						$catsrch,
						$maxAge,
						$exccatlist,
						$order[0],
						$order[1],
						($start === false ? '' : ' LIMIT ' . $num . ' OFFSET ' . $start)
				), true, NN_CACHE_EXPIRY_MEDIUM
		);

		$gameIDs = $releaseIDs = false;

		if (is_array($games['result'])) {
			foreach ($games['result'] AS $game => $id) {
				$gameIDs[] = $id['id'];
				$releaseIDs[] = $id['grp_release_id'];
			}
		}

		$return = $this->pdo->query(
				sprintf("
				SELECT
					GROUP_CONCAT(r.id ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_id,
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
					GROUP_CONCAT(df.failed ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_failed,
				gi.*, YEAR (gi.releasedate) as year, r.gamesinfo_id, g.name AS group_name,
				rn.releases_id AS nfoid
				FROM releases r
				LEFT OUTER JOIN groups g ON g.id = r.groups_id
				LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
				LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
				INNER JOIN gamesinfo gi ON gi.id = r.gamesinfo_id
				WHERE gi.id IN (%s)
				AND r.id IN (%s)
				%s
				GROUP BY gi.id
				ORDER BY %s %s",
						(is_array($gameIDs) ? implode(',', $gameIDs) : -1),
						(is_array($releaseIDs) ? implode(',', $releaseIDs) : -1),
						$catsrch,
						$order[0],
						$order[1]
				), true, NN_CACHE_EXPIRY_MEDIUM
		);
		if (!empty($return)) {
			$return[0]['_totalcount'] = $games['total'] ?? 0;
		}
		return $return;
	}

	/**
	 * @param $orderBy
	 *
	 * @return array
	 */
	public function getGamesOrder($orderBy): array
	{
		$order = ($orderBy === '') ? 'r.postdate' : $orderBy;
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
			'releasedate_asc', 'releasedate_desc', 'genre_asc', 'genre_desc'
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
			if (isset($_REQUEST[$bbk]) && !empty($_REQUEST[$bbk])) {
				$bbs = stripslashes($_REQUEST[$bbk]);
				if ($bbk === 'year') {
					$browseBy .= 'AND YEAR (gi.releasedate) ' . $this->pdo->likeString($bbs, true, true);
				} else {
					$browseBy .= 'AND gi.' . $bbv . ' ' .  $this->pdo->likeString($bbs, true, true);
				}
			}
		}

		return $browseBy;
	}

	/**
	 * @param $data
	 * @param $field
	 *
	 * @return string
	 */
	public function makeFieldLinks($data, $field): string
	{
		$tmpArr = explode(', ', $data[$field]);
		$newArr = [];
		$i = 0;
		foreach ($tmpArr as $ta) {
			if (trim($ta) === '') {
				continue;
			}
			// Only use first 6.
			if ($i > 5) {
				break;
			}
			$newArr[] =
				'<a href="' . WWW_TOP . '/games?' . $field . '=' . urlencode($ta) . '" title="' .
				$ta . '">' . $ta . '</a>';
			$i++;
		}

		return implode(', ', $newArr);
	}

	/**
	 * Updates the game for game-edit.php
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

		$this->pdo->queryExec(
			sprintf('
				UPDATE gamesinfo
				SET title = %s, asin = %s, url = %s, publisher = %s,
					releasedate = %s, esrb = %s, cover = %d, trailer = %s, genres_id = %d, updateddate = NOW()
				WHERE id = %d',
				$this->pdo->escapeString($title),
				$this->pdo->escapeString($asin),
				$this->pdo->escapeString($url),
				$this->pdo->escapeString($publisher),
				$this->pdo->escapeString($releaseDate),
				$this->pdo->escapeString($esrb),
				$cover,
				$this->pdo->escapeString($trailerUrl),
				$genreID,
				$id
			)
		);
	}

	/**
	 * Process each game, updating game information from Steam, Giantbomb, Desura and GreenLight
	 *
	 * @param $gameInfo
	 *
	 * @return bool
	 */
	public function updateGamesInfo($gameInfo): bool
	{
		//wait 10 seconds before proceeding (steam api limit)
		sleep(10);
		$gen = new Genres(['Settings' => $this->pdo]);
		$ri = new ReleaseImage($this->pdo);

		$game = [];

		// Process Steam first before giantbomb
		// Steam has more details
		$this->_gameResults = [];
		$this->_getGame = new Steam(['DB' => $this->pdo]);
		$this->_classUsed = 'steam';
		$this->_getGame->searchTerm = $gameInfo['title'];
		$steamGameID = $this->_getGame->search($gameInfo['title']);
		if ($steamGameID !== false){
			$result = $this->_getGame->getAll($steamGameID);
			if ($result !== false) {
				$this->_gameResults[] = $result;
			}
		}

		if (count($this->_gameResults) < 1) {
			$bestMatch = false;
			$this->_classUsed = 'giantbomb';
			$result = $this->giantbomb->search($gameInfo['title'], 'game');
			if (!empty($result)) {
				foreach ($result as $res) {
					similar_text(strtolower($gameInfo['title']), strtolower($res->name), $percent);
					if ($percent >= self::GAME_MATCH_PERCENTAGE) {
						$bestMatch = $res->id;
					}
				}
				if ($bestMatch !== false) {
					$this->_gameResults[] = $this->giantbomb->findOne('Game', '3030-' . $bestMatch);
				}
			}
		}
		if (empty($this->_gameResults->name) || empty($this->_gameResults['title'])){
			return false;
		}
		if (!is_array($this->_gameResults)){
			return false;
		}
		if (count($this->_gameResults) > 1) {
			$genreName = '';
			switch ($this->_classUsed) {
				case 'steam':
					if (!empty($this->_gameResults['cover'])) {
						$game['coverurl'] = (string)$this->_gameResults['cover'];
					}

					if (!empty($this->_gameResults['backdrop'])) {
						$game['backdropurl'] = (string)$this->_gameResults['backdrop'];
					}

					$game['title'] = (string)$this->_gameResults['title'];
					$game['asin'] = $this->_gameResults['steamid'];
					$game['url'] = (string)$this->_gameResults['directurl'];

					if (!empty($this->_gameResults['publisher'])) {
						$game['publisher'] = (string)$this->_gameResults['publisher'];
					} else {
						$game['publisher'] = 'Unknown';
					}

					if (!empty($this->_gameResults['rating'])) {
						$game['esrb'] = (string)$this->_gameResults['rating'];
					} else {
						$game['esrb'] = 'Not Rated';
					}

					if (!empty($this->_gameResults['releasedate'])) {
						$dateReleased = $this->_gameResults['releasedate'];
						$date = \DateTime::createFromFormat('M/j/Y', $dateReleased);
						if ($date instanceof \DateTime) {
							$game['releasedate'] = (string)$date->format('Y-m-d');
						}
					}

					if (!empty($this->_gameResults['description'])) {
						$game['review'] = (string)$this->_gameResults['description'];
					}

					if (!empty($this->_gameResults['genres'])) {
						$genres = $this->_gameResults['genres'];
						$genreName = $this->_matchGenre($genres);
					}
					break;
				case 'giantbomb':
					if (!empty($this->_gameResults->image['medium_url'])) {
						$game['coverurl'] = (string)$this->_gameResults->image['medium_url'];
					}

					if (!empty($this->_gameResults->image['screen_url'])) {
						$game['backdropurl'] = (string)$this->_gameResults->image['screen_url'];
					}

					$game['title'] = (string)$this->_gameResults->name;
					$game['asin'] = $this->_gameResults->id;
					$game['url'] = (string)$this->_gameResults->site_detail_url;

					if (!empty($this->_gameResults->publishers)) {
						$game['publisher'] = (string)$this->_gameResults->publishers[0]['name'];
					} else {
						$game['publisher'] = 'Unknown';
					}


					if (!empty($this->_gameResults->original_game_rating[0]['name'])) {
						$game['esrb'] = (string)$this->_gameResults->original_game_rating[0]['name'];
					} else {
						$game['esrb'] = 'Not Rated';
					}

					if (!empty($this->_gameResults->original_release_date)) {
						$dateReleased = $this->_gameResults->original_release_date;
						$date = \DateTime::createFromFormat('Y-m-d H:i:s', $dateReleased);
						if ($date instanceof \DateTime) {
							$game['releasedate'] = (string)$date->format('Y-m-d');
						}
					}

					if (!empty($this->_gameResults->deck)) {
						$game['review'] = (string)$this->_gameResults->deck;
					}

					if (!empty($this->_gameResults->genres)) {
						$genres = implode(',', array_column($this->_gameResults->genres, 'name'));
						$genreName = $this->_matchGenre($genres);
					}
					break;
				default:
					return false;
			}
		} else {
			return false;
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
		if (!isset($game['trailer'])) {
			$game['trailer'] = 0;
		}
		if (empty($game['title'])) {
			$game['title'] = $gameInfo['title'];
		}
		if(!isset($game['releasedate'])){
			$game['releasedate'] = '';
		}

		if ($game['releasedate'] === '') {
			$game['releasedate'] = '';
		}
		if(!isset($game['review'])){
			$game['review'] = 'No Review';
		}
		$game['classused'] = $this->_classUsed;

		if (empty($genreName)) {
			$genreName = 'Unknown';
		}

		if (in_array(strtolower($genreName), $genreAssoc, false)) {
			$genreKey = array_search(strtolower($genreName), $genreAssoc, false);
		} else {
			$genreKey = $this->pdo->queryInsert(
				sprintf('
					INSERT INTO genres (title, type)
					VALUES (%s, %d)',
					$this->pdo->escapeString($genreName),
					Genres::GAME_TYPE
				)
			);
		}

		$game['gamesgenre'] = $genreName;
		$game['gamesgenreID'] = $genreKey;

		$check = $this->pdo->queryOneRow(
			sprintf('
				SELECT id
				FROM gamesinfo
				WHERE asin = %s',
				$this->pdo->escapeString($game['asin'])
			)
		);
		if ($check === false) {
			$gamesId = $this->pdo->queryInsert(
				sprintf('
					INSERT INTO gamesinfo
						(title, asin, url, publisher, genres_id, esrb, releasedate, review, cover, backdrop, trailer, classused, createddate, updateddate)
					VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %d, %d, %s, %s, NOW(), NOW())',
					$this->pdo->escapeString($game['title']),
					$this->pdo->escapeString($game['asin']),
					$this->pdo->escapeString($game['url']),
					$this->pdo->escapeString($game['publisher']),
					($game['gamesgenreID'] === -1 ? 'null' : $game['gamesgenreID']),
					$this->pdo->escapeString($game['esrb']),
					($game['releasedate'] !== '' ? $this->pdo->escapeString($game['releasedate']) : 'null'),
					$this->pdo->escapeString(substr($game['review'], 0, 3000)),
					$game['cover'],
					$game['backdrop'],
					$this->pdo->escapeString($game['trailer']),
					$this->pdo->escapeString($game['classused'])
				)
			);
		} else {
			$gamesId = $check['id'];
			$this->pdo->queryExec(
				sprintf('
					UPDATE gamesinfo
					SET
						title = %s, asin = %s, url = %s, publisher = %s, genres_id = %s,
						esrb = %s, releasedate = %s, review = %s, cover = %d, backdrop = %d, trailer = %s, classused = %s, updateddate = NOW()
					WHERE id = %d',
					$this->pdo->escapeString($game['title']),
					$this->pdo->escapeString($game['asin']),
					$this->pdo->escapeString($game['url']),
					$this->pdo->escapeString($game['publisher']),
					($game['gamesgenreID'] === -1 ? 'null' : $game['gamesgenreID']),
					$this->pdo->escapeString($game['esrb']),
					($game['releasedate'] !== '' ? $this->pdo->escapeString($game['releasedate']) : 'null'),
					$this->pdo->escapeString(substr($game['review'], 0, 3000)),
					$game['cover'],
					$game['backdrop'],
					$this->pdo->escapeString($game['trailer']),
					$this->pdo->escapeString($game['classused']),
					$gamesId
				)
			);
		}

		if ($gamesId) {
			if ($this->echoOutput) {
				ColorCLI::doEcho(
					ColorCLI::header('Added/updated game: ') .
					ColorCLI::alternateOver('   Title:    ') .
					ColorCLI::primary($game['title'])
				);
			}
			if($game['cover'] === 1){
				$game['cover'] = $ri->saveImage($gamesId, $game['coverurl'], $this->imgSavePath, 250, 250);
			}
			if($game['backdrop'] === 1){
				$game['backdrop'] = $ri->saveImage($gamesId . '-backdrop', $game['backdropurl'], $this->imgSavePath, 1920, 1024);
			}
		} else {
			if ($this->echoOutput) {
				ColorCLI::doEcho(
					ColorCLI::headerOver('Nothing to update: ') .
					ColorCLI::primary($game['title'] . ' (PC)' )
				);
			}
		}

		return $gamesId;
	}

	/**
	 *
	 */
	public function processGamesReleases(): void
	{
		$res = $this->pdo->queryDirect(
			sprintf('
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
				ColorCLI::doEcho(ColorCLI::header('Processing ' . $res->rowCount() . ' games release(s).'));
			}

			foreach ($res as $arr) {

				// Reset maxhitrequest
				$this->maxHitRequest = false;

				$gameInfo = $this->parseTitle($arr['searchname']);
				if ($gameInfo !== false) {

					if ($this->echoOutput) {
						ColorCLI::doEcho(
							ColorCLI::headerOver('Looking up: ') .
							ColorCLI::primary($gameInfo['title'] . ' (PC)' )
						);
					}

					// Check for existing games entry.
					$gameCheck = $this->getGamesInfoByName($gameInfo['title']);

					if ($gameCheck === false) {
						$gameId = $this->updateGamesInfo($gameInfo);
						if ($gameId === false) {
							$gameId = -2;

							// Leave gamesinfo_id 0 to parse again
							if($this->maxHitRequest === true){
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
				ColorCLI::doEcho(ColorCLI::header('No games releases to process.'));
			}
		}
	}

	/**
	 * Parse the game release title
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
	 * See if genre name exists
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
	 * Matches Genres
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
		if (is_array($tmpGenre)) {
			foreach ($tmpGenre as $tg) {
				$genreMatch = $this->matchGenreName(ucwords($tg));
				if ($genreMatch !== false) {
					$genreName = (string)$genreMatch;
					break;
				}
			}
			if(empty($genreName)){
				$genreName = $tmpGenre[0];
			}
		} else {
			$genreName = $genre;
		}

		return $genreName;
	}
}
