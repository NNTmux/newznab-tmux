<?php
require_once(WWW_DIR . "/lib/framework/db.php");
require_once(WWW_DIR . "/lib/binaries.php");
require_once(WWW_DIR . "/lib/page.php");
require_once(WWW_DIR . "/lib/users.php");
require_once(WWW_DIR . "/lib/releaseregex.php");
require_once(WWW_DIR . "/lib/category.php");
require_once(WWW_DIR . "/lib/nzb.php");
require_once(WWW_DIR . "/lib/nzbinfo.php");
require_once(WWW_DIR . "/lib/nfo.php");
require_once(WWW_DIR . "/lib/zipfile.php");
require_once(WWW_DIR . "/lib/site.php");
require_once(WWW_DIR . "/lib/util.php");
require_once(WWW_DIR . "/lib/releasefiles.php");
require_once(WWW_DIR . "/lib/releaseextra.php");
require_once(WWW_DIR . "/lib/releaseimage.php");
require_once(WWW_DIR . "/lib/releasecomments.php");
require_once(WWW_DIR . "/lib/postprocess.php");
require_once(WWW_DIR . "/lib/sphinx.php");
require_once(WWW_DIR . "lib/Categorize.php");
require_once(NN_TMUX . 'lib' . DS . 'ReleaseCleaner.php');
require_once(NN_TMUX . 'lib' . DS . 'Pprocess.php');
require_once(NN_TMUX . 'lib' . DS . 'Enzebe.php');
require_once(NN_LIB . 'SphinxSearch.php');
require_once(NN_LIB . 'ReleaseSearch.php');
require_once(NN_LIB . 'ConsoleTools.php');
require_once(NN_LIB . 'RequestIDLocal.php');
require_once(NN_LIB . 'RequestIDWeb.php');

/**
 * This class handles storage and retrieval of releases rows and the main processing functions
 * for turning binaries into releases.
 */
class Releases
{
	/**
	 * @access public
	 * @var initial binary state after being added from usenet
	 */
	const PROCSTAT_NEW = 0;

	/**
	 * @access public
	 * @var after a binary has matched a releaseregex
	 */
	const PROCSTAT_TITLEMATCHED = 5;

	/**
	 * @access public
	 * @var after a binary has been confirmed as having the right number of parts
	 */
	const PROCSTAT_READYTORELEASE = 1;

	/**
	 * @access public
	 * @var binary has not matched a releaseregex
	 */
	const PROCSTAT_TITLENOTMATCHED = 3;

	/**
	 * @access public
	 * @var binary that has finished and successfully made it into a release
	 */
	const PROCSTAT_RELEASED = 4;

	/**
	 * @access public
	 * @var after a series of attempts to lookup the allfilled style reqid to get a name, its given up
	 */
	const PROCSTAT_NOREQIDNAMELOOKUPFOUND = 7;

	/**
	 * @access public
	 * @var release is not passworded
	 */
	const PASSWD_NONE = 0;

	/**
	 * @access public
	 * @var release may be passworded, ie contains inner rar/ace files
	 */
	const PASSWD_POTENTIAL = 1;

	/**
	 * @access public
	 * @var release is passworded
	 */
	const PASSWD_RAR = 2;

	/**
	 * @var SphinxSearch
	 */
	public $sphinxSearch;

	/**
	 * @var DB
	 */
	public $pdo;

	/**
	 * @var Groups
	 */
	public $groups;

	/**
	 * @var bool
	 */
	public $updategrabs;

	/**
	 * @var ReleaseSearch
	 */
	public $releaseSearch;

	/**
	 * @var bool
	 */
	public $tablePerGroup;

	/**
	 * @var int
	 */
	public $crossPostTime;

	/**
	 * @var int
	 */
	public $releaseCreationLimit;

	/**
	 * @var int
	 */
	public $completion;

	/**
	 * @var int
	 */
	public $processRequestIDs;

	/**
	 * @var bool
	 */
	public $echoCLI;

	/**
	 * @var \ConsoleTools
	 */
	public $consoleTools;

	/**
	 * @var \NZB
	 */
	public $nzb;

	/**
	 * @var \ReleaseCleaning
	 */
	public $releaseCleaning;

	/**
	 * @var \ReleaseImage
	 */
	public $releaseImage;

	/**
	 * @param array $options Class instances / Echo to cli ?
	 */
	public function __construct(array $options = [])
	{
		$defaults = [
			'Echo'            => true,
			'ConsoleTools'    => null,
			'Groups'          => null,
			'NZB'             => null,
			'ReleaseCleaning' => null,
			'ReleaseImage'    => null,
			'Releases'        => null,
			'Settings'        => null,
		];
		$options += $defaults;

		$this->echoCLI = ($options['Echo'] && NN_ECHOCLI);

		$this->pdo = ($options['Settings'] instanceof \DB ? $options['Settings'] : new \DB());
		$s = new Sites();
		$this->site = $s->get();
		$this->consoleTools = ($options['ConsoleTools'] instanceof \ConsoleTools ? $options['ConsoleTools'] : new \ConsoleTools(['ColorCLI' => $this->pdo->log]));
		$this->groups = ($options['Groups'] instanceof \Groups ? $options['Groups'] : new \Groups(['Settings' => $this->pdo]));
		$this->nzb = ($options['NZB'] instanceof \NZB ? $options['NZB'] : new \NZB());
		$this->releaseCleaning = ($options['ReleaseCleaning'] instanceof \ReleaseCleaning ? $options['ReleaseCleaning'] : new \ReleaseCleaning($this->pdo));
		$this->releaseImage = ($options['ReleaseImage'] instanceof \ReleaseImage ? $options['ReleaseImage'] : new \ReleaseImage($this->pdo));
		$this->updategrabs = ($this->site->grabstatus == '0' ? false : true);
		$this->passwordStatus = ($this->site->checkpasswordedrar == 1 ? -1 : 0);
		$this->sphinxSearch = new \SphinxSearch();
		$this->releaseSearch = new \ReleaseSearch($this->pdo, $this->sphinxSearch);
		$this->releaseRegex = new \ReleaseRegex();

		$this->tablePerGroup = ($this->site->tablepergroup == 0 ? false : true);
		$this->crossPostTime = ($this->site->crossposttime != '' ? (int)$this->site->crossposttime : 2);
		$this->releaseCreationLimit = ($this->site->maxnzbsprocessed != '' ? (int)$this->site->maxnzbsprocessed : 1000);
		$this->completion = ($this->site->completionpercent != '' ? (int)$this->site->completionpercent : 0);
		$this->processRequestIDs = (int)$this->site->lookup_reqids;
		if ($this->completion > 100) {
			$this->completion = 100;
			echo $this->pdo->log->error(PHP_EOL . 'You have an invalid setting for completion. It must be lower than 100.');
		}
	}


	/**
	 * Get a list of releases by an array of names
	 */
	public function getByNames($names)
	{

		$nsql = "1=2";
		if (count($names) > 0) {
			$n = array();
			foreach ($names as $nm)
				$n[] = " searchname = " . $this->pdo->escapeString($nm);

			$nsql = "( " . implode(' or ', $n) . " )";
		}

		$sql = sprintf(" SELECT releases.*, CONCAT(cp.title, ' > ', c.title) AS category_name,
							m.ID AS movie_id, m.title, m.rating, m.cover, m.plot, m.year, m.genre, m.director, m.actors, m.tagline,
							mu.ID AS music_id, mu.title AS mu_title, mu.cover AS mu_cover, mu.year AS mu_year, mu.artist AS mu_artist, mu.tracks AS mu_tracks, mu.review AS mu_review,
							ep.ID AS ep_id, ep.showtitle AS ep_showtitle, ep.airdate AS ep_airdate, ep.fullep AS ep_fullep, ep.overview AS ep_overview,
							tvrage.imgdata AS rage_imgdata, tvrage.ID AS rg_ID
							FROM releases
							LEFT OUTER JOIN category c ON c.ID = releases.categoryID
							LEFT OUTER JOIN category cp ON cp.ID = c.parentID
							LEFT OUTER JOIN movieinfo m ON m.imdbID = releases.imdbID
							LEFT OUTER JOIN musicinfo mu ON mu.ID = releases.musicinfoID
							LEFT OUTER JOIN episodeinfo ep ON ep.ID = releases.episodeinfoID
							LEFT OUTER JOIN tvrage ON tvrage.rageID = releases.rageID
						WHERE %s", $nsql
		);

		return $this->pdo->queryDirect($sql);
	}

	/**
	 * Get a count of releases for pager. used in admin manage list
	 */
	public function getCount()
	{

		$res = $this->pdo->queryOneRow("SELECT count(ID) AS num FROM releases");

		return $res["num"];
	}

	/**
	 * Get a range of releases. used in admin manage list
	 */
	public function getRange($start, $num)
	{


		if ($start === false)
			$limit = "";
		else
			$limit = " LIMIT " . $start . "," . $num;

		return $this->pdo->query(" SELECT releases.*, concat(cp.title, ' > ', c.title) AS category_name FROM releases LEFT OUTER JOIN category c ON c.ID = releases.categoryID LEFT OUTER JOIN category cp ON cp.ID = c.parentID ORDER BY postdate DESC" . $limit);
	}

	/**
	 * Get a count of previews for pager. used in admin manage list
	 */
	public function getPreviewCount($previewtype, $cat)
	{


		$catsrch = "";
		if (count($cat) > 0 && $cat[0] != -1) {
			$catsrch = " and (";
			foreach ($cat as $category) {
				if ($category != -1) {
					$categ = new Categorize();
					if ($categ->isParent($category)) {
						$children = $categ->getChildren($category);
						$chlist = "-99";
						foreach ($children as $child)
							$chlist .= ", " . $child["ID"];

						if ($chlist != "-99")
							$catsrch .= " releases.categoryID in (" . $chlist . ") or ";
					} else {
						$catsrch .= sprintf(" releases.categoryID = %d or ", $category);
					}
				}
			}
			$catsrch .= "1=2 )";
		}

		$sql = sprintf("SELECT count(ID) AS num FROM releases WHERE haspreview = %d %s ", $previewtype, $catsrch);
		$res = $this->pdo->queryOneRow($sql);

		return $res["num"];
	}

	/**
	 * Get a range of releases. used in admin manage list
	 */
	public function getPreviewRange($previewtype, $cat, $start, $num)
	{


		$catsrch = "";
		if (count($cat) > 0 && $cat[0] != -1) {
			$catsrch = " and (";
			foreach ($cat as $category) {
				if ($category != -1) {
					$categ = new Categorize();
					if ($categ->isParent($category)) {
						$children = $categ->getChildren($category);
						$chlist = "-99";
						foreach ($children as $child)
							$chlist .= ", " . $child["ID"];

						if ($chlist != "-99")
							$catsrch .= " releases.categoryID in (" . $chlist . ") or ";
					} else {
						$catsrch .= sprintf(" releases.categoryID = %d or ", $category);
					}
				}
			}
			$catsrch .= "1=2 )";
		}

		if ($start === false)
			$limit = "";
		else
			$limit = " LIMIT " . $start . "," . $num;

		$sql = sprintf(" SELECT releases.*, concat(cp.title, ' > ', c.title) AS category_name FROM releases LEFT OUTER JOIN category c ON c.ID = releases.categoryID LEFT OUTER JOIN category cp ON cp.ID = c.parentID WHERE haspreview = %d %s ORDER BY postdate DESC %s", $previewtype, $catsrch, $limit);

		return $this->pdo->query($sql);
	}


	/**
	 * Used for pager on browse page.
	 *
	 * @param array  $cat
	 * @param int    $maxAge
	 * @param array  $excludedCats
	 * @param string $groupName
	 *
	 * @return int
	 */
	public function getBrowseCount($cat, $maxAge = -1, $excludedCats = [], $groupName = '')
	{
		return $this->getPagerCount(
			sprintf(
				'SELECT r.id
				FROM releases r
				%s
				WHERE r.nzbstatus = %d
				AND r.passwordstatus %s
				%s %s %s %s',
				($groupName != '' ? 'INNER JOIN groups g ON g.ID = r.groupID' : ''),
				Enzebe::NZB_ADDED,
				$this->showPasswords(),
				($groupName != '' ? sprintf(' AND g.name = %s', $this->pdo->escapeString($groupName)) : ''),
				$this->categorySQL($cat),
				($maxAge > 0 ? (' AND r.postdate > NOW() - INTERVAL ' . $maxAge . ' DAY ') : ''),
				(count($excludedCats) ? (' AND r.categoryID NOT IN (' . implode(',', $excludedCats) . ')') : '')
			)
		);
	}

	/**
	 * Used for browse results.
	 *
	 * @param array  $cat
	 * @param        $start
	 * @param        $num
	 * @param string $orderBy
	 * @param int    $maxAge
	 * @param array  $excludedCats
	 * @param string $groupName
	 *
	 * @return array
	 */
	public function getBrowseRange($cat, $start, $num, $orderBy, $maxAge = -1, $excludedCats = [], $groupName = '')
	{
		$orderBy = $this->getBrowseOrder($orderBy);
		return $this->pdo->query(
			sprintf(
				"SELECT r.*,
					CONCAT(cp.title, ' > ', c.title) AS category_name,
					CONCAT(cp.id, ',', c.ID) AS category_ids,
					g.name AS group_name,
					rn.ID AS nfoid,
					re.releaseID AS reid
				FROM releases r
				STRAIGHT_JOIN groups g ON g.ID = r.groupID
				STRAIGHT_JOIN category c ON c.ID = r.categoryID
				INNER JOIN category cp ON cp.ID = c.parentID
				LEFT OUTER JOIN releasevideo re ON re.releaseID = r.ID
				LEFT OUTER JOIN releasenfo rn ON rn.releaseID = r.ID
				AND rn.nfo IS NOT NULL
				WHERE r.nzbstatus = %d
				AND r.passwordstatus %s
				%s %s %s %s
				ORDER BY %s %s %s",
				Enzebe::NZB_ADDED,
				$this->showPasswords(),
				$this->categorySQL($cat),
				($maxAge > 0 ? (" AND postdate > NOW() - INTERVAL " . $maxAge . ' DAY ') : ''),
				(count($excludedCats) ? (' AND r.categoryID NOT IN (' . implode(',', $excludedCats) . ')') : ''),
				($groupName != '' ? sprintf(' AND g.name = %s ', $this->pdo->escapeString($groupName)) : ''),
				$orderBy[0],
				$orderBy[1],
				($start === false ? '' : ' LIMIT ' . $num . ' OFFSET ' . $start)
			),
			true, NN_CACHE_EXPIRY_MEDIUM
		);
	}

	/**
	 * Get a column names browse list to be ordered by
	 */
	public function getBrowseOrder($orderby)
	{
		$order = ($orderby == '') ? 'posted_desc' : $orderby;
		$orderArr = explode("_", $order);
		switch ($orderArr[0]) {
			case 'cat':
				$orderfield = 'categoryID';
				break;
			case 'name':
				$orderfield = 'searchname';
				break;
			case 'size':
				$orderfield = 'size';
				break;
			case 'files':
				$orderfield = 'totalpart';
				break;
			case 'stats':
				$orderfield = 'grabs';
				break;
			case 'posted':
			default:
				$orderfield = 'postdate';
				break;
		}
		$ordersort = (isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1])) ? $orderArr[1] : 'desc';

		return array($orderfield, $ordersort);
	}

	/**
	 * Return ordering types usable on site.
	 *
	 * @return array
	 */
	public function getBrowseOrdering()
	{
		return [
			'name_asc',
			'name_desc',
			'cat_asc',
			'cat_desc',
			'posted_asc',
			'posted_desc',
			'size_asc',
			'size_desc',
			'files_asc',
			'files_desc',
			'stats_asc',
			'stats_desc'
		];
	}

	/**
	 * Get a range of releases. Used in nzb export
	 */
	public function getForExport($postfrom, $postto, $group, $cat)
	{

		if ($postfrom != "") {
			$dateparts = explode("/", $postfrom);
			if (count($dateparts) == 3)
				$postfrom = sprintf(" and postdate > %s ", $this->pdo->escapeString($dateparts[2] . "-" . $dateparts[1] . "-" . $dateparts[0] . " 00:00:00"));
			else
				$postfrom = "";
		}

		if ($postto != "") {
			$dateparts = explode("/", $postto);
			if (count($dateparts) == 3)
				$postto = sprintf(" and postdate < %s ", $this->pdo->escapeString($dateparts[2] . "-" . $dateparts[1] . "-" . $dateparts[0] . " 23:59:59"));
			else
				$postto = "";
		}

		if ($group != "" && $group != "-1")
			$group = sprintf(" and groupID = %d ", $group);
		else
			$group = "";

		if ($cat != "" && $cat != "-1")
			$cat = sprintf(" and categoryID = %d ", $cat);
		else
			$cat = "";

		return $this->pdo->queryDirect(sprintf("SELECT searchname, guid, CONCAT(cp.title,'_',category.title) AS catName FROM releases INNER JOIN category ON releases.categoryID = category.ID LEFT OUTER JOIN category cp ON cp.ID = category.parentID WHERE 1 = 1 %s %s %s %s", $postfrom, $postto, $group, $cat));
	}

	/**
	 * Get the earliest release
	 */
	public function getEarliestUsenetPostDate()
	{

		$row = $this->pdo->queryOneRow("SELECT DATE_FORMAT(min(postdate), '%d/%m/%Y') AS postdate FROM releases");

		return $row["postdate"];
	}

	/**
	 * Get the most recent release
	 */
	public function getLatestUsenetPostDate()
	{

		$row = $this->pdo->queryOneRow("SELECT DATE_FORMAT(max(postdate), '%d/%m/%Y') AS postdate FROM releases");

		return $row["postdate"];
	}

	/**
	 * Get all groups for which there is a release for a html select
	 */
	public function getReleasedGroupsForSelect($blnIncludeAll = true)
	{

		$groups = $this->pdo->query("SELECT DISTINCT groups.ID, groups.name FROM releases INNER JOIN groups ON groups.ID = releases.groupID");
		$temp_array = array();

		if ($blnIncludeAll)
			$temp_array[-1] = "--All Groups--";

		foreach ($groups as $group)
			$temp_array[$group["ID"]] = $group["name"];

		return $temp_array;
	}

	/**
	 * Get releases for all types of rss feeds
	 */
	public function getRss($cat, $num, $uid = 0, $rageid, $anidbid, $airdate = -1)
	{


		$limit = " LIMIT 0," . ($num > 100 ? 100 : $num);

		$cartsrch = "";
		$catsrch = "";

		if (count($cat) > 0) {
			if ($cat[0] == -2) {
				$cartsrch = sprintf(" inner join usercart on usercart.userID = %d and usercart.releaseID = releases.ID ", $uid);
			} elseif ($cat[0] == -1) {
			} else {
				$catsrch = " and (";
				foreach ($cat as $category) {
					if ($category != -1) {
						$categ = new Categorize();
						if ($categ->isParent($category)) {
							$children = $categ->getChildren($category);
							$chlist = "-99";
							foreach ($children as $child)
								$chlist .= ", " . $child["ID"];

							if ($chlist != "-99")
								$catsrch .= " releases.categoryID in (" . $chlist . ") or ";
						} else {
							$catsrch .= sprintf(" releases.categoryID = %d or ", $category);
						}
					}
				}
				$catsrch .= "1=2 )";
			}
		}

		$rage = ($rageid > -1) ? sprintf(" and releases.rageID = %d ", $rageid) : '';
		$anidb = ($anidbid > -1) ? sprintf(" and releases.anidbID = %d ", $anidbid) : '';
		$airdate = ($airdate > -1) ? sprintf(" and releases.tvairdate >= DATE_SUB(CURDATE(), INTERVAL %d DAY) ", $airdate) : '';

		$sql = sprintf(" SELECT releases.*, rn.ID AS nfoID, m.title AS imdbtitle, m.cover, m.imdbID, m.rating, m.plot, m.year, m.genre, m.director, m.actors, g.name AS group_name, concat(cp.title, ' > ', c.title) AS category_name, concat(cp.ID, ',', c.ID) AS category_ids, coalesce(cp.ID,0) AS parentCategoryID, mu.title AS mu_title, mu.url AS mu_url, mu.artist AS mu_artist, mu.publisher AS mu_publisher, mu.releasedate AS mu_releasedate, mu.review AS mu_review, mu.tracks AS mu_tracks, mu.cover AS mu_cover, mug.title AS mu_genre, co.title AS co_title, co.url AS co_url, co.publisher AS co_publisher, co.releasedate AS co_releasedate, co.review AS co_review, co.cover AS co_cover, cog.title AS co_genre,   bo.title AS bo_title, bo.url AS bo_url, bo.publisher AS bo_publisher, bo.author AS bo_author, bo.publishdate AS bo_publishdate, bo.review AS bo_review, bo.cover AS bo_cover  FROM releases LEFT OUTER JOIN category c ON c.ID = releases.categoryID LEFT OUTER JOIN category cp ON cp.ID = c.parentID LEFT OUTER JOIN groups g ON g.ID = releases.groupID LEFT OUTER JOIN releasenfo rn ON rn.releaseID = releases.ID AND rn.nfo IS NOT NULL LEFT OUTER JOIN movieinfo m ON m.imdbID = releases.imdbID AND m.title != '' LEFT OUTER JOIN musicinfo mu ON mu.ID = releases.musicinfoID LEFT OUTER JOIN genres mug ON mug.ID = mu.genreID LEFT OUTER JOIN bookinfo bo ON bo.ID = releases.bookinfoID LEFT OUTER JOIN consoleinfo co ON co.ID = releases.consoleinfoID LEFT OUTER JOIN genres cog ON cog.ID = co.genreID %s WHERE releases.passwordstatus <= (SELECT value FROM site WHERE setting='showpasswordedrelease') %s %s %s %s ORDER BY postdate DESC %s", $cartsrch, $catsrch, $rage, $anidb, $airdate, $limit);

		return $this->pdo->query($sql, true);
	}

	/**
	 * Get releases in users 'my tv show' rss feed
	 */
	public function getShowsRss($num, $uid = 0, $excludedcats = array(), $airdate = -1)
	{


		$exccatlist = "";
		if (count($excludedcats) > 0)
			$exccatlist = " and releases.categoryID not in (" . implode(",", $excludedcats) . ")";

		$usershows = $this->pdo->query(sprintf("SELECT rageID, categoryID FROM userseries WHERE userID = %d", $uid), true);
		$usql = '(1=2 ';
		foreach ($usershows as $ushow) {
			$usql .= sprintf('or (releases.rageID = %d', $ushow['rageID']);
			if ($ushow['categoryID'] != '') {
				$catsArr = explode('|', $ushow['categoryID']);
				if (count($catsArr) > 1)
					$usql .= sprintf(' and releases.categoryID in (%s)', implode(',', $catsArr));
				else
					$usql .= sprintf(' and releases.categoryID = %d', $catsArr[0]);
			}
			$usql .= ') ';
		}
		$usql .= ') ';

		$airdate = ($airdate > -1) ? sprintf(" and releases.tvairdate >= DATE_SUB(CURDATE(), INTERVAL %d DAY) ", $airdate) : '';

		$limit = " LIMIT 0," . ($num > 100 ? 100 : $num);

		$sql = sprintf(" SELECT releases.*, tvr.rageID, tvr.releasetitle, epinfo.overview, epinfo.director, epinfo.gueststars, epinfo.writer, epinfo.rating, epinfo.fullep, epinfo.showtitle, epinfo.tvdbID AS ep_tvdbID, g.name AS group_name, concat(cp.title, '-', c.title) AS category_name, concat(cp.ID, ',', c.ID) AS category_ids, coalesce(cp.ID,0) AS parentCategoryID
						FROM releases FORCE INDEX (ix_releases_rageID)
						LEFT OUTER JOIN category c ON c.ID = releases.categoryID
						LEFT OUTER JOIN category cp ON cp.ID = c.parentID
						LEFT OUTER JOIN groups g ON g.ID = releases.groupID
						LEFT OUTER JOIN (SELECT ID, releasetitle, rageid FROM tvrage GROUP BY rageid) tvr ON tvr.rageID = releases.rageID
						LEFT OUTER JOIN episodeinfo epinfo ON epinfo.ID = releases.episodeinfoID
						INNER JOIN
						(   SELECT ID FROM
								( SELECT id, rageID, categoryID, season, episode FROM releases WHERE %s ORDER BY season DESC, episode DESC, postdate ASC ) releases
							GROUP BY rageID, season, episode, categoryID
						) z ON z.ID = releases.ID
						WHERE %s %s %s
						AND releases.passwordstatus <= (SELECT VALUE FROM site WHERE setting='showpasswordedrelease')
						ORDER BY postdate DESC %s", $usql, $usql, $exccatlist, $airdate, $limit
		);

		return $this->pdo->query($sql);
	}

	/**
	 * Get releases in users 'my movies' rss feed
	 */
	public function getMyMoviesRss($num, $uid = 0, $excludedcats = array())
	{


		$exccatlist = "";
		if (count($excludedcats) > 0)
			$exccatlist = " and releases.categoryID not in (" . implode(",", $excludedcats) . ")";

		$usermovies = $this->pdo->query(sprintf("SELECT imdbID, categoryID FROM usermovies WHERE userID = %d", $uid), true);
		$usql = '(1=2 ';
		foreach ($usermovies as $umov) {
			$usql .= sprintf('or (releases.imdbID = %d', $umov['imdbID']);
			if ($umov['categoryID'] != '') {
				$catsArr = explode('|', $umov['categoryID']);
				if (count($catsArr) > 1)
					$usql .= sprintf(' and releases.categoryID in (%s)', implode(',', $catsArr));
				else
					$usql .= sprintf(' and releases.categoryID = %d', $catsArr[0]);
			}
			$usql .= ') ';
		}
		$usql .= ') ';

		$limit = " LIMIT 0," . ($num > 100 ? 100 : $num);

		$sql = sprintf(" SELECT releases.*, mi.title AS releasetitle, g.name AS group_name, concat(cp.title, '-', c.title) AS category_name, concat(cp.ID, ',', c.ID) AS category_ids, coalesce(cp.ID,0) AS parentCategoryID
						FROM releases
						LEFT OUTER JOIN category c ON c.ID = releases.categoryID
						LEFT OUTER JOIN category cp ON cp.ID = c.parentID
						LEFT OUTER JOIN groups g ON g.ID = releases.groupID
						LEFT OUTER JOIN movieinfo mi ON mi.imdbID = releases.imdbID
						WHERE %s %s
						AND releases.passwordstatus <= (SELECT VALUE FROM site WHERE setting='showpasswordedrelease')
						ORDER BY postdate DESC %s", $usql, $exccatlist, $limit
		);

		return $this->pdo->query($sql);
	}

	/**
	 * Get range of releases in users 'my tvshows'
	 */
	public function getShowsRange($usershows, $start, $num, $orderby, $maxage = -1, $excludedcats = array())
	{


		if ($start === false)
			$limit = "";
		else
			$limit = " LIMIT " . $start . "," . $num;

		$exccatlist = "";
		if (count($excludedcats) > 0)
			$exccatlist = " and releases.categoryID not in (" . implode(",", $excludedcats) . ")";

		$usql = '(1=2 ';
		foreach ($usershows as $ushow) {
			$usql .= sprintf('or (releases.rageID = %d', $ushow['rageID']);
			if ($ushow['categoryID'] != '') {
				$catsArr = explode('|', $ushow['categoryID']);
				if (count($catsArr) > 1)
					$usql .= sprintf(' and releases.categoryID in (%s)', implode(',', $catsArr));
				else
					$usql .= sprintf(' and releases.categoryID = %d', $catsArr[0]);
			}
			$usql .= ') ';
		}
		$usql .= ') ';

		$maxagesql = "";
		if ($maxage > 0)
			$maxagesql = sprintf(" and releases.postdate > now() - interval %d day ", $maxage);

		$order = $this->getBrowseOrder($orderby);
		$sql = sprintf(" SELECT releases.*, concat(cp.title, '-', c.title) AS category_name, concat(cp.ID, ',', c.ID) AS category_ids, groups.name AS group_name, pre.ctime, pre.nuketype, rn.ID AS nfoID, re.releaseID AS reID FROM releases LEFT OUTER JOIN releasevideo re ON re.releaseID = releases.ID LEFT OUTER JOIN groups ON groups.ID = releases.groupID LEFT OUTER JOIN releasenfo rn ON rn.releaseID = releases.ID AND rn.nfo IS NOT NULL LEFT OUTER JOIN category c ON c.ID = releases.categoryID LEFT OUTER JOIN predb pre ON pre.ID = releases.preID LEFT OUTER JOIN category cp ON cp.ID = c.parentID WHERE %s %s AND releases.passwordstatus <= (SELECT VALUE FROM site WHERE setting='showpasswordedrelease') %s ORDER BY %s %s" . $limit, $usql, $exccatlist, $maxagesql, $order[0], $order[1]);

		return $this->pdo->query($sql, true);
	}

	/**
	 * Get count of releases in users 'my tvshows' for pager
	 */
	public function getShowsCount($usershows, $maxage = -1, $excludedcats = array())
	{


		$exccatlist = "";
		if (count($excludedcats) > 0)
			$exccatlist = " and releases.categoryID not in (" . implode(",", $excludedcats) . ")";

		$usql = '(1=2 ';
		foreach ($usershows as $ushow) {
			$usql .= sprintf('or (releases.rageID = %d', $ushow['rageID']);
			if ($ushow['categoryID'] != '') {
				$catsArr = explode('|', $ushow['categoryID']);
				if (count($catsArr) > 1)
					$usql .= sprintf(' and releases.categoryID in (%s)', implode(',', $catsArr));
				else
					$usql .= sprintf(' and releases.categoryID = %d', $catsArr[0]);
			}
			$usql .= ') ';
		}
		$usql .= ') ';

		$maxagesql = "";
		if ($maxage > 0)
			$maxagesql = sprintf(" and releases.postdate > now() - interval %d day ", $maxage);

		$res = $this->pdo->queryOneRow(sprintf(" SELECT count(releases.ID) AS num FROM releases WHERE %s %s AND releases.passwordstatus <= (SELECT VALUE FROM site WHERE setting='showpasswordedrelease') %s", $usql, $exccatlist, $maxagesql), true);

		return $res["num"];
	}

	/**
	 * Delete a preview associated with a release and update the release to indicate it doesnt have one.
	 */
	public function deletePreview($guid)
	{
		$this->updateHasPreview($guid, 0);
		$ri = new ReleaseImage();
		$ri->delete($guid);
	}

	/**
	 * Update whether a release has a preview.
	 */
	public function updateHasPreview($guid, $haspreview)
	{

		$this->pdo->queryExec(sprintf("UPDATE releases SET haspreview = %d WHERE guid = %s", $haspreview, $this->pdo->escapeString($guid)));
	}

	/**
	 * Update a release.
	 */
	public function update($id, $name, $searchname, $fromname, $category, $parts, $grabs, $size, $posteddate, $addeddate, $rageid, $seriesfull, $season, $episode, $imdbid, $anidbid, $tvdbid, $consoleinfoid)
	{


		$this->pdo->queryExec(sprintf("UPDATE releases SET name=%s, searchname=%s, fromname=%s, categoryID=%d, totalpart=%d, grabs=%d, size=%s, postdate=%s, adddate=%s, rageID=%d, seriesfull=%s, season=%s, episode=%s, imdbID=%d, anidbID=%d, tvdbID=%d,consoleinfoID=%d WHERE id = %d",
				$this->pdo->escapeString($name), $this->pdo->escapeString($searchname), $this->pdo->escapeString($fromname), $category, $parts, $grabs, $this->pdo->escapeString($size), $this->pdo->escapeString($posteddate), $this->pdo->escapeString($addeddate), $rageid, $this->pdo->escapeString($seriesfull), $this->pdo->escapeString($season), $this->pdo->escapeString($episode), $imdbid, $anidbid, $tvdbid, $consoleinfoid, $id
			)
		);
	}

	/**
	 * Update multiple releases.
	 */
	public function updatemulti($guids, $category, $grabs, $rageid, $season, $imdbid)
	{
		if (!is_array($guids) || sizeof($guids) < 1)
			return false;

		$update = array(
			'categoryID' => (($category == '-1') ? '' : $category),
			'grabs'      => $grabs,
			'rageID'     => $rageid,
			'season'     => $season,
			'imdbID'     => $imdbid
		);


		$updateSql = array();
		foreach ($update as $updk => $updv) {
			if ($updv != '')
				$updateSql[] = sprintf($updk . '=%s', $this->pdo->escapeString($updv));
		}

		if (sizeof($updateSql) < 1) {
			//echo 'no field set to be changed';
			return -1;
		}

		$updateGuids = array();
		foreach ($guids as $guid) {
			$updateGuids[] = $this->pdo->escapeString($guid);
		}

		$sql = sprintf('UPDATE releases SET ' . implode(', ', $updateSql) . ' WHERE guid IN (%s)', implode(', ', $updateGuids));

		return $this->pdo->queryExec($sql);
	}


	/**
	 * Search for releases by rage id. Used by API/Sickbeard.
	 */
	public function searchbyRageId($rageId, $series = "", $episode = "", $offset = 0, $limit = 100, $name = "", $cat = array(-1), $maxage = -1)
	{
		$s = new Sites();
		$site = $s->get();

		// Sphinx appears much slower than searching mysql directly when you have a rage ID already
		if ($site->sphinxenabled && $rageId == "-1") {
			$sphinx = new Sphinx();
			$results = $sphinx->searchbyRageId($rageId, $series, $episode, $offset, $limit, $name, $cat, $maxage, array(), true);
			if (is_array($results))
				return $results;
		}


		if ($rageId != "-1")
			$rageId = sprintf(" and rageID = %d ", $rageId);
		else
			$rageId = "";

		if ($series != "") {
			//
			// Exclude four digit series, which will be the year 2010 etc
			//
			if (is_numeric($series) && strlen($series) != 4)
				$series = sprintf('S%02d', $series);

			$series = sprintf(" and releases.season = %s", $this->pdo->escapeString($series));
		}
		if ($episode != "") {
			if (is_numeric($episode))
				$episode = sprintf('E%02d', $episode);

			$episode = sprintf(" and releases.episode like %s", $this->pdo->escapeString('%' . $episode . '%'));
		}

		//
		// if the query starts with a ^ it indicates the search is looking for items which start with the term
		// still do the fulltext match, but mandate that all items returned must start with the provided word
		//
		$words = explode(" ", $name);
		$searchsql = "";
		$intwordcount = 0;
		if (count($words) > 0) {
			foreach ($words as $word) {
				if ($word != "") {
					//
					// see if the first word had a caret, which indicates search must start with term
					//
					if ($intwordcount == 0 && (strpos($word, "^") === 0))
						$searchsql .= sprintf(" and releases.searchname like %s", $this->pdo->escapeString(substr($word, 1) . "%"));
					elseif (substr($word, 0, 2) == '--')
						$searchsql .= sprintf(" and releases.searchname not like %s", $this->pdo->escapeString("%" . substr($word, 2) . "%"));
					else
						$searchsql .= sprintf(" and releases.searchname like %s", $this->pdo->escapeString("%" . $word . "%"));

					$intwordcount++;
				}
			}
		}

		$catsrch = "";
		$usecatindex = "";
		if (count($cat) > 0 && $cat[0] != -1) {
			$catsrch = " and (";
			foreach ($cat as $category) {
				if ($category != -1) {
					$categ = new Categorize();
					if ($categ->isParent($category)) {
						$children = $categ->getChildren($category);
						$chlist = "-99";
						foreach ($children as $child)
							$chlist .= ", " . $child["ID"];

						if ($chlist != "-99")
							$catsrch .= " releases.categoryID in (" . $chlist . ") or ";
					} else {
						$catsrch .= sprintf(" releases.categoryID = %d or ", $category);
					}
				}
			}
			$catsrch .= "1=2 )";
			$usecatindex = " use index (ix_releases_categoryID) ";
		}

		if ($maxage > 0)
			$maxage = sprintf(" and postdate > now() - interval %d day ", $maxage);
		else
			$maxage = "";

		$sql = sprintf("SELECT releases.*, concat(cp.title, ' > ', c.title) AS category_name, concat(cp.ID, ',', c.ID) AS category_ids, groups.name AS group_name, rn.ID AS nfoID, re.releaseID AS reID FROM releases %s LEFT OUTER JOIN category c ON c.ID = releases.categoryID LEFT OUTER JOIN groups ON groups.ID = releases.groupID LEFT OUTER JOIN releasevideo re ON re.releaseID = releases.ID LEFT OUTER JOIN releasenfo rn ON rn.releaseID = releases.ID AND rn.nfo IS NOT NULL LEFT OUTER JOIN category cp ON cp.ID = c.parentID WHERE releases.passwordstatus <= (SELECT VALUE FROM site WHERE setting='showpasswordedrelease') %s %s %s %s %s %s ORDER BY postdate DESC LIMIT %d, %d ", $usecatindex, $rageId, $series, $episode, $searchsql, $catsrch, $maxage, $offset, $limit);
		$orderpos = strpos($sql, "order by");
		$wherepos = strpos($sql, "where");
		$sqlcount = "SELECT count(releases.ID) AS num FROM releases " . substr($sql, $wherepos, $orderpos - $wherepos);

		$countres = $this->pdo->queryOneRow($sqlcount, true);
		$res = $this->pdo->query($sql, true);
		if (count($res) > 0)
			$res[0]["_totalrows"] = $countres["num"];

		return $res;
	}

	/**
	 * Search for releases by anidb id. Used by API/Sickbeard.
	 */
	public function searchbyAnidbId($anidbID, $epno = '', $offset = 0, $limit = 100, $name = '', $maxage = -1)
	{
		$s = new Sites();
		$site = $s->get();
		if ($site->sphinxenabled) {
			$sphinx = new Sphinx();
			$results = $sphinx->searchbyAnidbId($anidbID, $epno, $offset, $limit, $name, $maxage, array(), true);
			if (is_array($results))
				return $results;
		}


		$anidbID = ($anidbID > -1) ? sprintf(" AND anidbID = %d ", $anidbID) : '';

		$epno = is_numeric($epno) ? sprintf(" AND releases.episode LIKE '%s' ", $this->pdo->escapeString('%' . $epno . '%')) : '';

		//
		// if the query starts with a ^ it indicates the search is looking for items which start with the term
		// still do the fulltext match, but mandate that all items returned must start with the provided word
		//
		$words = explode(" ", $name);
		$searchsql = "";
		$intwordcount = 0;
		if (count($words) > 0) {
			foreach ($words as $word) {
				if ($word != "") {
					//
					// see if the first word had a caret, which indicates search must start with term
					//
					if ($intwordcount == 0 && (strpos($word, "^") === 0))
						$searchsql .= sprintf(" AND releases.searchname LIKE '%s' ", $this->pdo->escapeString(substr($word, 1) . "%"));
					elseif (substr($word, 0, 2) == '--')
						$searchsql .= sprintf(" AND releases.searchname NOT LIKE '%s' ", $this->pdo->escapeString("%" . substr($word, 2) . "%"));
					else
						$searchsql .= sprintf(" AND releases.searchname LIKE '%s' ", $this->pdo->escapeString("%" . $word . "%"));

					$intwordcount++;
				}
			}
		}

		$maxage = ($maxage > 0) ? sprintf(" and postdate > now() - interval %d day ", $maxage) : '';

		$sql = sprintf("SELECT releases.*, concat(cp.title, ' > ', c.title)
			AS category_name, concat(cp.ID, ',', c.ID) AS category_ids, groups.name AS group_name, rn.ID AS nfoID
			FROM releases LEFT OUTER JOIN category c ON c.ID = releases.categoryID LEFT OUTER JOIN groups ON groups.ID = releases.groupID
			LEFT OUTER JOIN releasenfo rn ON rn.releaseID = releases.ID AND rn.nfo IS NOT NULL LEFT OUTER JOIN category cp ON cp.ID = c.parentID
			WHERE releases.passwordstatus <= (SELECT value FROM site WHERE setting='showpasswordedrelease') %s %s %s %s ORDER BY postdate DESC LIMIT %d, %d ",
			$anidbID, $epno, $searchsql, $maxage, $offset, $limit
		);
		$orderpos = strpos($sql, "ORDER BY");
		$wherepos = strpos($sql, "WHERE");
		$sqlcount = "SELECT count(releases.ID) AS num FROM releases " . substr($sql, $wherepos, $orderpos - $wherepos);

		$countres = $this->pdo->queryOneRow($sqlcount, true);
		$res = $this->pdo->query($sql, true);
		if (count($res) > 0)
			$res[0]["_totalrows"] = $countres["num"];

		return $res;
	}

	/**
	 * Search for releases by album/artist/musicinfo. Used by API.
	 */
	public function searchAudio($artist, $album, $label, $track, $year, $genre = array(-1), $offset = 0, $limit = 100, $cat = array(-1), $maxage = -1)
	{
		$s = new Sites();
		$site = $s->get();
		if ($site->sphinxenabled) {
			$sphinx = new Sphinx();
			$results = $sphinx->searchAudio($artist, $album, $label, $track, $year, $genre, $offset, $limit, $cat, $maxage, array(), true);
			if (is_array($results))
				return $results;
		}


		$searchsql = "";

		if ($artist != "")
			$searchsql .= sprintf(" and musicinfo.artist like %s ", $this->pdo->escapeString("%" . $artist . "%"));
		if ($album != "")
			$searchsql .= sprintf(" and musicinfo.title like %s ", $this->pdo->escapeString("%" . $album . "%"));
		if ($label != "")
			$searchsql .= sprintf(" and musicinfo.publisher like %s ", $this->pdo->escapeString("%" . $label . "%"));
		if ($track != "")
			$searchsql .= sprintf(" and musicinfo.tracks like %s ", $this->pdo->escapeString("%" . $track . "%"));
		if ($year != "")
			$searchsql .= sprintf(" and musicinfo.year = %d ", $year);


		$catsrch = "";
		$usecatindex = "";
		if (count($cat) > 0 && $cat[0] != -1) {
			$catsrch = " and (";
			foreach ($cat as $category) {
				if ($category != -1) {
					$categ = new Categorize();
					if ($categ->isParent($category)) {
						$children = $categ->getChildren($category);
						$chlist = "-99";
						foreach ($children as $child)
							$chlist .= ", " . $child["ID"];

						if ($chlist != "-99")
							$catsrch .= " releases.categoryID in (" . $chlist . ") or ";
					} else {
						$catsrch .= sprintf(" releases.categoryID = %d or ", $category);
					}
				}
			}
			$catsrch .= "1=2 )";
			$usecatindex = " use index (ix_releases_categoryID) ";
		}

		if ($maxage > 0)
			$maxage = sprintf(" and postdate > now() - interval %d day ", $maxage);
		else
			$maxage = "";

		$genresql = "";
		if (count($genre) > 0 && $genre[0] != -1) {
			$genresql = " and (";
			foreach ($genre as $g) {
				$genresql .= sprintf(" musicinfo.genreID = %d or ", $g);
			}
			$genresql .= "1=2 )";
		}

		$sql = sprintf("SELECT releases.*, musicinfo.cover AS mi_cover, musicinfo.review AS mi_review, musicinfo.tracks AS mi_tracks, musicinfo.publisher AS mi_publisher, musicinfo.title AS mi_title, musicinfo.artist AS mi_artist, genres.title AS music_genrename, concat(cp.title, ' > ', c.title) AS category_name, concat(cp.ID, ',', c.ID) AS category_ids, groups.name AS group_name, rn.ID AS nfoID FROM releases %s LEFT OUTER JOIN musicinfo ON musicinfo.ID = releases.musicinfoID LEFT JOIN genres ON genres.ID = musicinfo.genreID LEFT OUTER JOIN groups ON groups.ID = releases.groupID LEFT OUTER JOIN category c ON c.ID = releases.categoryID LEFT OUTER JOIN releasenfo rn ON rn.releaseID = releases.ID AND rn.nfo IS NOT NULL LEFT OUTER JOIN category cp ON cp.ID = c.parentID WHERE releases.passwordstatus <= (SELECT VALUE FROM site WHERE setting='showpasswordedrelease') %s %s %s %s ORDER BY postdate DESC LIMIT %d, %d ", $usecatindex, $searchsql, $catsrch, $maxage, $genresql, $offset, $limit);
		$orderpos = strpos($sql, "order by");
		$wherepos = strpos($sql, "where");
		$sqlcount = "SELECT count(releases.ID) AS num FROM releases INNER JOIN musicinfo ON musicinfo.ID = releases.musicinfoID " . substr($sql, $wherepos, $orderpos - $wherepos);

		$countres = $this->pdo->queryOneRow($sqlcount, true);
		$res = $this->pdo->query($sql, true);
		if (count($res) > 0)
			$res[0]["_totalrows"] = $countres["num"];

		return $res;
	}

	/**
	 * Search for releases by author/bookinfo. Used by API.
	 */
	public function searchBook($author, $title, $offset = 0, $limit = 100, $maxage = -1)
	{
		$s = new Sites();
		$site = $s->get();
		if ($site->sphinxenabled) {
			$sphinx = new Sphinx();
			$results = $sphinx->searchBook($author, $title, $offset, $limit, $maxage, array(), true);
			if (is_array($results))
				return $results;
		}


		$searchsql = "";

		if ($author != "")
			$searchsql .= sprintf(" and bookinfo.author like %s ", $this->pdo->escapeString("%" . $author . "%"));
		if ($title != "")
			$searchsql .= sprintf(" and bookinfo.title like %s ", $this->pdo->escapeString("%" . $title . "%"));

		if ($maxage > 0)
			$maxage = sprintf(" and postdate > now() - interval %d day ", $maxage);
		else
			$maxage = "";

		$sql = sprintf("SELECT releases.*, bookinfo.cover AS bi_cover, bookinfo.review AS bi_review, bookinfo.publisher AS bi_publisher, bookinfo.pages AS bi_pages, bookinfo.publishdate AS bi_publishdate, bookinfo.title AS bi_title, bookinfo.author AS bi_author, genres.title AS book_genrename, concat(cp.title, ' > ', c.title) AS category_name, concat(cp.ID, ',', c.ID) AS category_ids, groups.name AS group_name, rn.ID AS nfoID FROM releases LEFT OUTER JOIN bookinfo ON bookinfo.ID = releases.bookinfoID LEFT JOIN genres ON genres.ID = bookinfo.genreID LEFT OUTER JOIN groups ON groups.ID = releases.groupID LEFT OUTER JOIN category c ON c.ID = releases.categoryID LEFT OUTER JOIN releasenfo rn ON rn.releaseID = releases.ID AND rn.nfo IS NOT NULL LEFT OUTER JOIN category cp ON cp.ID = c.parentID WHERE releases.passwordstatus <= (SELECT value FROM site WHERE setting='showpasswordedrelease') %s %s ORDER BY postdate DESC LIMIT %d, %d ", $searchsql, $maxage, $offset, $limit);
		$orderpos = strpos($sql, "order by");
		$wherepos = strpos($sql, "where");
		$sqlcount = "SELECT count(releases.ID) AS num FROM releases INNER JOIN bookinfo ON bookinfo.ID = releases.bookinfoID " . substr($sql, $wherepos, $orderpos - $wherepos);

		$countres = $this->pdo->queryOneRow($sqlcount, true);
		$res = $this->pdo->query($sql, true);
		if (count($res) > 0)
			$res[0]["_totalrows"] = $countres["num"];

		return $res;
	}

	/**
	 * Search for releases by imdbID/movieinfo. Used by API/Couchpotato.
	 */
	public function searchbyImdbId($imdbId, $offset = 0, $limit = 100, $name = "", $cat = array(-1), $genre = "", $maxage = -1)
	{
		$s = new Sites();
		$site = $s->get();
		if ($site->sphinxenabled) {
			$sphinx = new Sphinx();
			$results = $sphinx->searchbyImdbId($imdbId, $offset, $limit, $name, $cat, $genre, $maxage, array(), true);
			if (is_array($results))
				return $results;
		}


		if ($imdbId != "-1" && is_numeric($imdbId)) {
			//pad id with zeros just in case
			$imdbId = str_pad($imdbId, 7, "0", STR_PAD_LEFT);
			$imdbId = sprintf(" and releases.imdbID = %d ", $imdbId);
		} else {
			$imdbId = "";
		}

		//
		// if the query starts with a ^ it indicates the search is looking for items which start with the term
		// still do the fulltext match, but mandate that all items returned must start with the provided word
		//
		$words = explode(" ", $name);
		$searchsql = "";
		$intwordcount = 0;
		if (count($words) > 0) {
			foreach ($words as $word) {
				if ($word != "") {
					//
					// see if the first word had a caret, which indicates search must start with term
					//
					if ($intwordcount == 0 && (strpos($word, "^") === 0))
						$searchsql .= sprintf(" and releases.searchname like %s", $this->pdo->escapeString(substr($word, 1) . "%"));
					elseif (substr($word, 0, 2) == '--')
						$searchsql .= sprintf(" and releases.searchname not like %s", $this->pdo->escapeString("%" . substr($word, 2) . "%"));
					else
						$searchsql .= sprintf(" and releases.searchname like %s", $this->pdo->escapeString("%" . $word . "%"));

					$intwordcount++;
				}
			}
		}

		$catsrch = "";
		if (count($cat) > 0 && $cat[0] != -1) {
			$catsrch = " and (";
			foreach ($cat as $category) {
				if ($category != -1) {
					$categ = new Categorize();
					if ($categ->isParent($category)) {
						$children = $categ->getChildren($category);
						$chlist = "-99";
						foreach ($children as $child)
							$chlist .= ", " . $child["ID"];

						if ($chlist != "-99")
							$catsrch .= " releases.categoryID in (" . $chlist . ") or ";
					} else {
						$catsrch .= sprintf(" releases.categoryID = %d or ", $category);
					}
				}
			}
			$catsrch .= "1=2 )";
		}

		if ($maxage > 0)
			$maxage = sprintf(" and releases.postdate > now() - interval %d day ", $maxage);
		else
			$maxage = "";

		if ($genre != "") {
			$genre = sprintf(" and movieinfo.genre like %s", $this->pdo->escapeString("%" . $genre . "%"));
		}

		$sql = sprintf("SELECT releases.*, movieinfo.title AS moi_title, movieinfo.tagline AS moi_tagline, movieinfo.rating AS moi_rating, movieinfo.plot AS moi_plot, movieinfo.year AS moi_year, movieinfo.genre AS moi_genre, movieinfo.director AS moi_director, movieinfo.actors AS moi_actors, movieinfo.cover AS moi_cover, movieinfo.backdrop AS moi_backdrop, concat(cp.title, ' > ', c.title) AS category_name, concat(cp.ID, ',', c.ID) AS category_ids, groups.name AS group_name, rn.ID AS nfoID FROM releases LEFT OUTER JOIN groups ON groups.ID = releases.groupID LEFT OUTER JOIN category c ON c.ID = releases.categoryID LEFT OUTER JOIN releasenfo rn ON rn.releaseID = releases.ID AND rn.nfo IS NOT NULL LEFT OUTER JOIN category cp ON cp.ID = c.parentID LEFT OUTER JOIN movieinfo ON releases.imdbID = movieinfo.imdbID WHERE releases.passwordstatus <= (SELECT value FROM site WHERE setting='showpasswordedrelease') %s %s %s %s %s ORDER BY postdate DESC LIMIT %d, %d ", $searchsql, $imdbId, $catsrch, $maxage, $genre, $offset, $limit);
		$orderpos = strpos($sql, "order by");
		$wherepos = strpos($sql, "where");
		$sqlcount = "SELECT count(releases.ID) AS num FROM releases LEFT OUTER JOIN movieinfo ON releases.imdbID = movieinfo.imdbID " . substr($sql, $wherepos, $orderpos - $wherepos);

		$countres = $this->pdo->queryOneRow($sqlcount, true);
		$res = $this->pdo->query($sql, true);
		if (count($res) > 0)
			$res[0]["_totalrows"] = $countres["num"];

		return $res;
	}

	/**
	 * Return a list of releases with a similar name to that provided.
	 */
	public function searchSimilar($currentid, $name, $limit = 6, $excludedcats = array())
	{
		$name = $this->getSimilarName($name);
		$results = $this->search($name, array(-1), 0, $limit, '', -1, $excludedcats);
		if (!$results)
			return $results;

		//
		// Get the category for the parent of this release
		//
		$currRow = $this->getById($currentid);
		$cat = new Categorize();
		$catrow = $cat->getById($currRow["categoryID"]);
		$parentCat = $catrow["parentID"];

		$ret = array();
		foreach ($results as $res)
			if ($res["ID"] != $currentid && $res["categoryParentID"] == $parentCat)
				$ret[] = $res;

		return $ret;
	}

	/**
	 * Return a similar release name.
	 */
	public function getSimilarName($name)
	{
		$words = str_word_count(str_replace(array(".", "_"), " ", $name), 2);
		$firstwords = array_slice($words, 0, 2);

		return implode(' ', $firstwords);
	}

	/**
	 * Function for searching on the site (by subject, searchname or advanced).
	 *
	 * @param string $searchName
	 * @param string $usenetName
	 * @param string $posterName
	 * @param string $groupName
	 * @param array  $cat
	 * @param int    $sizeFrom
	 * @param int    $sizeTo
	 * @param int    $hasNfo
	 * @param int    $hasComments
	 * @param int    $daysNew
	 * @param int    $daysOld
	 * @param int    $offset
	 * @param int    $limit
	 * @param string $orderBy
	 * @param int    $maxAge
	 * @param array  $excludedCats
	 * @param string $type
	 *
	 * @return array
	 */
	public function search(
		$searchName, $usenetName, $posterName, $groupName, $cat = [-1], $sizeFrom,
		$sizeTo, $hasNfo, $hasComments, $daysNew, $daysOld, $offset = 0, $limit = 1000,
		$orderBy = '', $maxAge = -1, $excludedCats = [], $type = 'basic'
	)
	{

		$sphinxSearch = new SphinxSearch();
		$releaseSearch = new ReleaseSearch($this->pdo, $sphinxSearch);
		$sizeRange = range(1, 11);
		$groups = new Groups();

		if ($orderBy == '') {
			$orderBy = [];
			$orderBy[0] = 'postdate ';
			$orderBy[1] = 'desc ';
		} else {
			$orderBy = $this->getBrowseOrder($orderBy);
		}

		$searchOptions = [];
		if ($searchName != -1) {
			$searchOptions['searchname'] = $searchName;
		}
		if ($usenetName != -1) {
			$searchOptions['name'] = $usenetName;
		}
		if ($posterName != -1) {
			$searchOptions['fromname'] = $posterName;
		}

		$whereSql = sprintf(
			"%s
			WHERE r.passwordstatus <= (select value from site where setting='showpasswordedrelease') AND r.nzbstatus = %d %s %s %s %s %s %s %s %s %s %s %s",
			$releaseSearch->getFullTextJoinString(),
			Enzebe::NZB_ADDED,
			($maxAge > 0 ? sprintf(' AND r.postdate > (NOW() - INTERVAL %d DAY) ', $maxAge) : ''),
			($groupName != -1 ? sprintf(' AND r.groupID = %d ', $groups->getIDByName($groupName)) : ''),
			(in_array($sizeFrom, $sizeRange) ? ' AND r.size > ' . (string)(104857600 * (int)$sizeFrom) . ' ' : ''),
			(in_array($sizeTo, $sizeRange) ? ' AND r.size < ' . (string)(104857600 * (int)$sizeTo) . ' ' : ''),
			($hasNfo != 0 ? ' AND r.nfostatus = 1 ' : ''),
			($hasComments != 0 ? ' AND r.comments > 0 ' : ''),
			($type !== 'advanced' ? $this->categorySQL($cat) : ($cat[0] != '-1' ? sprintf(' AND (r.categoryID = %d) ', $cat[0]) : '')),
			($daysNew != -1 ? sprintf(' AND r.postdate < (NOW() - INTERVAL %d DAY) ', $daysNew) : ''),
			($daysOld != -1 ? sprintf(' AND r.postdate > (NOW() - INTERVAL %d DAY) ', $daysOld) : ''),
			(count($excludedCats) > 0 ? ' AND r.categoryID NOT IN (' . implode(',', $excludedCats) . ')' : ''),
			(count($searchOptions) > 0 ? $releaseSearch->getSearchSQL($searchOptions) : '')
		);

		$baseSql = sprintf(
			"SELECT r.*,
				CONCAT(cp.title, ' > ', c.title) AS category_name,
				CONCAT(cp.ID, ',', c.ID) AS category_ids,
				groups.name AS group_name,
				rn.ID AS nfoid,
				re.releaseID AS reid,
				cp.ID AS categoryparentid
			FROM releases r
			LEFT OUTER JOIN releasevideo re ON re.releaseID = r.ID
			LEFT OUTER JOIN releasenfo rn ON rn.releaseID = r.ID
			INNER JOIN groups ON groups.ID = r.groupID
			INNER JOIN category c ON c.ID = r.categoryID
			INNER JOIN category cp ON cp.ID = c.parentID
			%s",
			$whereSql
		);

		$sql = sprintf(
			"SELECT * FROM (
				%s
			) r
			ORDER BY r.%s %s
			LIMIT %d OFFSET %d",
			$baseSql,
			$orderBy[0],
			$orderBy[1],
			$limit,
			$offset
		);
		$releases = $this->pdo->query($sql);
		if ($releases && count($releases)) {
			$releases[0]['_totalrows'] = $this->getPagerCount($baseSql);
		}

		return $releases;
	}

	/**
	 * Get count of releases for pager.
	 *
	 * @param string $query The query to get the count from.
	 *
	 * @return int
	 */
	private function getPagerCount($query)
	{

		$count = $this->pdo->queryOneRow(
			sprintf(
				'SELECT COUNT(*) AS count FROM (%s LIMIT %s) z',
				preg_replace('/SELECT.+?FROM\s+releases/is', 'SELECT r.ID FROM releases', $query),
				NN_MAX_PAGER_RESULTS
			)
		);
		if (isset($count['count']) && is_numeric($count['count'])) {
			return $count['count'];
		}

		return 0;
	}

	/**
	 * Creates part of a query for searches requiring the categoryID's.
	 *
	 * @param array $categories
	 *
	 * @return string
	 */
	public function categorySQL($categories)
	{
		$sql = '';
		if (count($categories) > 0 && $categories[0] != -1) {
			$Category = new \Category();
			$sql = ' AND (';
			foreach ($categories as $category) {
				if ($category != -1) {
					if ($Category->isParent($category)) {
						$children = $Category->getChildren($category);
						$childList = '-99';
						foreach ($children as $child) {
							$childList .= ', ' . $child['ID'];
						}

						if ($childList != '-99') {
							$sql .= ' r.categoryID IN (' . $childList . ') OR ';
						}
					} else {
						$sql .= sprintf(' r.categoryID = %d OR ', $category);
					}
				}
			}
			$sql .= '1=2 )';
		}

		return $sql;
	}

	public function getById($id)
	{


		return $this->pdo->queryOneRow(sprintf("SELECT releases.*, groups.name AS group_name FROM releases LEFT OUTER JOIN groups ON groups.ID = releases.groupID WHERE releases.ID = %d ", $id));
	}

	/**
	 * Writes a zip file of an array of release guids directly to the stream
	 */
	public function getZipped($guids)
	{
		$s = new Sites();
		$this->nzb = new NZB;
		$site = $s->get();
		$zipfile = new zipfile();

		foreach ($guids as $guid) {
			$nzbpath = $this->nzb->getNZBPath($guid, $site->nzbpath);

			if (file_exists($nzbpath)) {
				ob_start();
				@readgzfile($nzbpath);
				$nzbfile = ob_get_contents();
				ob_end_clean();

				$filename = $guid;
				$r = $this->getByGuid($guid);
				if ($r)
					$filename = $r["searchname"];

				$zipfile->addFile($nzbfile, $filename . ".nzb");
			}
		}

		return $zipfile->file();
	}

	/**
	 * Retrieve one or more releases by guid.
	 */
	public function getByGuid($guid)
	{

		if (is_array($guid)) {
			$tmpguids = array();
			foreach ($guid as $g)
				$tmpguids[] = $this->pdo->escapeString($g);
			$gsql = sprintf('guid in (%s)', implode(',', $tmpguids));
		} else {
			$gsql = sprintf('guid = %s', $this->pdo->escapeString($guid));
		}
		$sql = sprintf("SELECT releases.*, musicinfo.cover AS mi_cover, musicinfo.review AS mi_review, musicinfo.tracks AS mi_tracks, musicinfo.publisher AS mi_publisher, musicinfo.title AS mi_title, musicinfo.artist AS mi_artist, music_genre.title AS music_genrename,    bookinfo.cover AS bi_cover, bookinfo.review AS bi_review, bookinfo.publisher AS bi_publisher, bookinfo.publishdate AS bi_publishdate, bookinfo.title AS bi_title, bookinfo.author AS bi_author, bookinfo.pages AS bi_pages,  bookinfo.isbn AS bi_isbn, concat(cp.title, ' > ', c.title) AS category_name, concat(cp.ID, ',', c.ID) AS category_ids, groups.name AS group_name, movieinfo.title AS movietitle, movieinfo.year AS movieyear, (SELECT releasetitle FROM tvrage WHERE rageid = releases.rageid AND rageid > 0 LIMIT 1) AS tvreleasetitle FROM releases LEFT OUTER JOIN groups ON groups.ID = releases.groupID LEFT OUTER JOIN category c ON c.ID = releases.categoryID LEFT OUTER JOIN category cp ON cp.ID = c.parentID LEFT OUTER JOIN musicinfo ON musicinfo.ID = releases.musicinfoID LEFT OUTER JOIN bookinfo ON bookinfo.ID = releases.bookinfoID LEFT OUTER JOIN movieinfo ON movieinfo.imdbID = releases.imdbID LEFT JOIN genres music_genre ON music_genre.ID = musicinfo.genreID WHERE %s", $gsql);

		return (is_array($guid)) ? $this->pdo->query($sql) : $this->pdo->queryOneRow($sql);
	}

	/**
	 * Removes an associated tvrage id from all releases using it.
	 */
	public function removeRageIdFromReleases($rageid)
	{

		$res = $this->pdo->queryOneRow(sprintf("SELECT count(ID) AS num FROM releases WHERE rageID = %d", $rageid));
		$ret = $res["num"];
		$this->pdo->queryExec(sprintf("UPDATE releases SET rageID = -1, seriesfull = NULL, season = NULL, episode = NULL WHERE rageID = %d", $rageid));

		return $ret;
	}

	/**
	 * Removes an associated tvdb id from all releases using it.
	 */
	public function removeThetvdbIdFromReleases($tvdbID)
	{

		$res = $this->pdo->queryOneRow(sprintf("SELECT count(ID) AS num FROM releases WHERE tvdbID = %d", $tvdbID));
		$ret = $res["num"];
		$res = $this->pdo->queryExec(sprintf("UPDATE releases SET tvdbID = -1 WHERE tvdbID = %d", $tvdbID));

		return $ret;
	}

	public function removeAnidbIdFromReleases($anidbID)
	{

		$res = $this->pdo->queryOneRow(sprintf("SELECT count(ID) AS num FROM releases WHERE anidbID = %d", $anidbID));
		$ret = $res["num"];
		$this->pdo->queryExec(sprintf("UPDATE releases SET anidbID = -1, episode = NULL, tvtitle = NULL, tvairdate = NULL WHERE anidbID = %d", $anidbID));

		return $ret;
	}

	public function getReleaseNfo($id, $incnfo = true)
	{

		$selnfo = ($incnfo) ? ', uncompress(nfo) as nfo' : '';

		return $this->pdo->queryOneRow(sprintf("SELECT ID, releaseID" . $selnfo . " FROM releasenfo where releaseID = %d AND nfo IS NOT NULL", $id));
	}

	public function updateGrab($guid)
	{

		$this->pdo->queryExec(sprintf("UPDATE releases SET grabs = grabs + 1 WHERE guid = %s", $this->pdo->escapeString($guid)));
	}


	/**
	 * @param $categorize
	 * @param $postProcess
	 * @param $groupName
	 * @param $nntp
	 * @param $echooutput
	 *
	 * @return int
	 */
	public function processReleases($categorize, $postProcess, $groupName, &$nntp, $echooutput)
	{
		$retcount = 0;
		$this->echoCLI = ($echooutput && NN_ECHOCLI);
		$page = new Page();
		$groupID = '';
		$s = new Sites();
		echo $s->getLicense();

		if (!empty($groupName)) {
			$groupInfo = $this->groups->getByName($groupName);
			$groupID = $groupInfo['ID'];
		}

		$this->pdo->log->doEcho($this->pdo->log->primary('Starting release update process' . date("Y-m-d H:i:s")), true);

		if (!file_exists($page->site->nzbpath)) {
			$this->pdo->log->doEcho($this->pdo->log->primary('Bad or missing nzb directory - ' . $page->site->nzbpath), true);

			return -1;
		}

		$this->checkRegexesUptoDate($page->site->latestregexurl, $page->site->latestregexrevision, $page->site->newznabID);

		$this->applyRegex($groupID);
		$this->processIncompleteBinaries($groupID);

		$DIR = NN_MISC;
		$PYTHON = shell_exec('which python3 2>/dev/null');
		$PYTHON = (empty($PYTHON) ? 'python -OOu' : 'python3 -OOu');
		$processRequestIDs = (int)$page->site->lookup_reqids;
		$consoleTools = new ConsoleTools(['ColorCLI' => $this->pdo->log]);
		$totalReleasesAdded = $loops = 0;
		do {
			$releasesAdded = $nzbFilesAdded = $this->createReleases($groupID);
			$totalReleasesAdded += $releasesAdded;

			if ($processRequestIDs === 0) {
				$this->processRequestIDs($groupID, 5000, true);
			} else if ($processRequestIDs === 1) {
				$this->processRequestIDs($groupID, 5000, true);
				$this->processRequestIDs($groupID, 1000, false);
			} else if ($processRequestIDs === 2) {
				$requestIDTime = time();
				if ($this->echoCLI) {
					$this->pdo->log->doEcho($this->pdo->log->header("Process Releases -> Request ID Threaded lookup."));
					}
				passthru("$PYTHON ${DIR}update_scripts/nix_scripts/tmux/python/requestid_threaded.py");
				if ($this->echoCLI) {
					$this->pdo->log->doEcho(
						$this->pdo->log->primary(
							"\nReleases updated in " .
							$consoleTools->convertTime(time() - $requestIDTime)
						)
					);
				}
			}
			$this->categorizeReleases($categorize, $groupID);
			$this->postProcessReleases($postProcess, $nntp);
			$this->deleteBinaries($groupID);
			// This loops as long as there were releases created or 3 loops, otherwise, you could loop indefinately
		} while (($nzbFilesAdded > 0 || $releasesAdded > 0) && $loops++ < 3);

		$this->deletedReleasesByGroup($groupID);
		$this->deleteReleases();

		//
		// User/Request housekeeping, should ideally move this to its own section, but it needs to be done automatically.
		//
		$users = new Users;
		$users->pruneRequestHistory($page->site->userdownloadpurgedays);

		$this->pdo->log->doEcho($this->pdo->log->primary('Done    : Added ' . $retcount . ' releases'));

		return $retcount;
	}

	public function deleteBinaries($groupID)
	{
		$group = $this->groups->getCBPTableNames($this->tablePerGroup, $groupID);
		$page = new Page();
		$currTime_ori = $this->pdo->queryOneRow("SELECT NOW() as now");
		//
		// aggregate the releasefiles upto the releases.
		//
		$this->pdo->log->doEcho($this->pdo->log->primary('Aggregating Files'));
		$this->pdo->queryExec("UPDATE releases INNER JOIN (SELECT releaseID, COUNT(ID) AS num FROM releasefiles GROUP BY releaseID) b ON b.releaseID = releases.ID AND releases.rarinnerfilecount = 0 SET rarinnerfilecount = b.num");

		// Remove the binaries and parts used to form releases, or that are duplicates.
		//
		if ($page->site->partsdeletechunks > 0) {
			$this->pdo->log->doEcho($this->pdo->log->primary('Chunk deleting unused binaries and parts'));
			$query = sprintf("SELECT p.ID AS partsID,b.ID AS binariesID FROM %s p
						LEFT JOIN %s b ON b.ID = p.binaryID
						WHERE b.dateadded < %s - INTERVAL %d HOUR LIMIT 0,%d",
				$group['pname'],
				$group['bname'],
				$this->pdo->escapeString($currTime_ori["now"]),
				ceil($page->site->rawretentiondays * 24),
				$page->site->partsdeletechunks
			);

			$cc = 0;
			$done = false;
			while (!$done) {
				$dd = $cc;
				$result = $this->pdo->query($query);
				if (count($result) > 0) {
					$pID = array();
					$bID = array();
					foreach ($result as $row) {
						$pID[] = $row['partsID'];
						$bID[] = $row['binariesID'];
					}
					$pID = '(' . implode(',', $pID) . ')';
					$bID = '(' . implode(',', $bID) . ')';
					$fr = $this->pdo->queryExec("DELETE FROM %s WHERE ID IN {$pID}", $group['pname']);
					if ($fr > 0) {
						$cc += $fr;
						$cc += $this->pdo->queryExec("DELETE FROM %s WHERE ID IN {$bID}", $group['bname']);
					}
					unset($pID);
					unset($bID);
					if ($cc == $dd) {
						$done = true;
					}
					echo($cc % 10000 ? '.' : '');
				} else {
					$done = true;
				}
			}
			$this->pdo->log->doEcho($this->pdo->log->primary('Complete - ' . $cc . ' rows affected'));
		} else {
			$this->pdo->log->doEcho($this->pdo->log->primary('Deleting unused binaries and parts'));
			$this->pdo->queryExec(sprintf("DELETE %s, %s FROM %s JOIN %s  ON %s.ID = %s.binaryID
			WHERE %s.dateadded < %s - INTERVAL %d HOUR",
					$group['pname'],
					$group['bname'],
					$group['pname'],
					$group['bname'],
					$group['bname'],
					$group['pname'],
					$group['bname'],
					$this->pdo->escapeString($currTime_ori["now"]),
					ceil($page->site->rawretentiondays * 24)
				)
			);
		}
	}

	/**
	 * @param $groupID
	 */
	public function processIncompleteBinaries($groupID)
	{
		//
		// Move all binaries from releases which have the correct number of files on to the next stage.
		//
		$retcount = 0;
		$currTime_ori = $this->pdo->queryOneRow("SELECT NOW() as now");
		$page = new Page();
		$group = $this->groups->getCBPTableNames($this->tablePerGroup, $groupID);
		$this->pdo->log->doEcho($this->pdo->log->primary('Marking binaries where all parts are available'));
		$result = $this->pdo->queryDirect(sprintf("SELECT relname, date, SUM(reltotalpart) AS reltotalpart, groupID, reqID, fromname, SUM(num) AS num, coalesce(g.minfilestoformrelease, s.minfilestoformrelease) AS minfilestoformrelease FROM ( SELECT relname, reltotalpart, groupID, reqID, fromname, max(date) AS date, COUNT(ID) AS num FROM %s WHERE procstat = %s GROUP BY relname, reltotalpart, groupID, reqID, fromname ORDER BY NULL ) x LEFT OUTER JOIN groups g ON g.ID = x.groupID INNER JOIN ( SELECT value AS minfilestoformrelease FROM site WHERE setting = 'minfilestoformrelease' ) s GROUP BY relname, groupID, reqID, fromname, minfilestoformrelease ORDER BY NULL", $group['bname'], Releases::PROCSTAT_TITLEMATCHED));

		while ($row = $this->pdo->getAssocArray($result)) {
			$retcount++;

			//
			// Less than the site permitted number of files in a release. Dont discard it, as it may
			// be part of a set being uploaded.
			//
			if ($row["num"] < $row["minfilestoformrelease"]) {
				//echo "Number of files in release ".$row["relname"]." less than site/group setting (".$row['num']."/".$row["minfilestoformrelease"].")\n";
				$this->pdo->queryExec(sprintf("UPDATE %s SET procattempts = procattempts + 1 WHERE relname = %s AND procstat = %d AND groupID = %d AND fromname = %s", $group['bname'], $this->pdo->escapeString($row["relname"]), Releases::PROCSTAT_TITLEMATCHED, $groupID, $this->pdo->escapeString($row["fromname"])));
			}

			//
			// There are the same or more files in our release than the number of files specified
			// in the message subject so go ahead and make a release
			//
			elseif ($row["num"] >= $row["reltotalpart"]) {
				$incomplete = false;

				if ($row['reltotalpart'] == 0 && strtotime($currTime_ori['now']) - strtotime($row['date']) < 14400) {
					$incomplete = true;
				} else {
					// Check that the binary is complete
					$binlist = $this->pdo->query(sprintf('SELECT %s.ID, totalParts, date, COUNT(DISTINCT %s.messageID) AS num FROM %s,
					%s WHERE %s.ID=%s.binaryID AND %s.relname = %s
					AND %s.procstat = %d AND %s.groupID = %d AND %s.fromname = %s
					GROUP BY %s.ID ORDER BY NULL',
					$group['bname'],
					$group['pname'],
					$group['bname'],
					$group['pname'],
					$group['bname'],
					$group['pname'],
					$group['bname'],
					$this->pdo->escapeString($row["relname"]),
					$group['bname'],
					Releases::PROCSTAT_TITLEMATCHED,
					$group['bname'],
					$row['groupID'],
					$group['bname'],
					$this->pdo->escapeString($row["fromname"]),
					$group['bname']
						)
					);

					foreach ($binlist as $rowbin) {
						if ($rowbin['num'] < $rowbin['totalParts']) {
							// Allow to binary to release if posted to usenet longer than four hours ago and we still don't have all the parts
							if (!(strtotime($currTime_ori['now']) - strtotime($rowbin['date']) > 14400)) {
								$incomplete = true;
								break;
							}
						}
					}
				}

				if (!$incomplete) {
					//
					// Right number of files, but see if the binary is a allfilled/reqid post, in which case it needs its name looked up
					//
					if ($row['reqID'] != '' && $page->site->reqidurl != "") {
						//
						// Try and get the name using the group
						//
						$binGroup = $this->groups->getByNameById($groupID);
						$newtitle = $this->getReleaseNameForReqId($page->site->reqidurl, $page->site->newznabID, $binGroup, $row["reqID"]);

						//
						// if the feed/group wasnt supported by the scraper, then just use the release name as the title.
						//
						if ($newtitle == "no feed") {
							$newtitle = $row["relname"];
						}

						//
						// Valid release with right number of files and title now, so move it on
						//
						if ($newtitle != "") {
							$this->pdo->queryExec(sprintf("UPDATE %s SET relname = %s, procstat=%d WHERE relname = %s AND procstat = %d AND groupID = %d AND fromname=%s",
									$group['bname'], $this->pdo->escapeString($newtitle), Releases::PROCSTAT_READYTORELEASE, $this->pdo->escapeString($row["relname"]), Releases::PROCSTAT_TITLEMATCHED, $groupID, $this->pdo->escapeString($row["fromname"])
								)
							);
						} else {
							//
							// Item not found, if the binary was added to the index yages ago, then give up.
							//
							$maxaddeddate = $this->pdo->queryOneRow(sprintf("SELECT NOW() AS now, MAX(dateadded) AS dateadded FROM %s WHERE relname = %s AND procstat = %d AND groupID = %d AND fromname=%s",
									$group['bname'], $this->pdo->escapeString($row["relname"]), Releases::PROCSTAT_TITLEMATCHED, $groupID, $this->pdo->escapeString($row["fromname"])
								)
							);

							//
							// If added to the index over 48 hours ago, give up trying to determine the title
							//
							if (strtotime($maxaddeddate['now']) - strtotime($maxaddeddate['dateadded']) > (60 * 60 * 48)) {
								$this->pdo->queryExec(sprintf("UPDATE %s SET procstat=%d WHERE relname = %s AND procstat = %d AND groupID = %d AND fromname=%s",
										$group['bname'], Releases::PROCSTAT_NOREQIDNAMELOOKUPFOUND, $this->pdo->escapeString($row["relname"]), Releases::PROCSTAT_TITLEMATCHED, $groupID, $this->pdo->escapeString($row["fromname"])
									)
								);
							}
						}
					} else {
						$this->pdo->queryExec(sprintf("UPDATE %s SET procstat=%d WHERE relname = %s AND procstat = %d AND groupID = %d AND fromname=%s",
								$group['bname'], Releases::PROCSTAT_READYTORELEASE, $this->pdo->escapeString($row["relname"]), Releases::PROCSTAT_TITLEMATCHED, $groupID, $this->pdo->escapeString($row["fromname"])
							)
						);
					}
				}
			}

			//
			// Theres less than the expected number of files, so update the attempts and move on.
			//
			else {
				// pointless updating of attempts, as was creating a lot of database writes for people regex groups with posts without part numbering.
				//$this->pdo->exec(sprintf("update binaries set procattempts = procattempts + 1 where relname = %s and procstat = %d and groupID = %d and fromname=%s", $this->pdo->escapeString($row["relname"]), Releases::PROCSTAT_TITLEMATCHED, $groupID, $this->pdo->escapeString($row["fromname"]) ));
			}
			if ($retcount % 100 == 0)
				echo ".";
		}
	}

	/**
	 * @param $groupID
	 */
	public function createReleases($groupID)
	{
		$group = $this->groups->getCBPTableNames($this->tablePerGroup, $groupID);
		$page = new Page();
		$this->pdo->log->doEcho($this->pdo->log->primary('Creating releases from complete binaries'));
		//
		// Get out all distinct relname, group from binaries of STAGE2
		//
		$categorize = new \Categorize(['Settings' => $this->pdo]);
		$result = $this->pdo->queryDirect(sprintf("SELECT relname, groupID, g.name AS group_name, fromname, max(categoryID) AS categoryID, max(regexID) AS regexID, max(reqID) AS reqID, MAX(date) AS date, count(%s.ID) AS parts FROM %s INNER JOIN groups g ON g.ID = %s.groupID WHERE procstat = %d AND relname IS NOT NULL GROUP BY relname, g.name, groupID, fromname ORDER BY COUNT(%s.ID) DESC", $group['bname'], $group['bname'], $group['bname'], Releases::PROCSTAT_READYTORELEASE, $group['bname']));
		while ($row = $this->pdo->getAssocArray($result)) {
			$relguid = md5(uniqid());
			// Clean release name
			$releaseCleaning = new ReleaseCleaning();
			$cleanRelName = $this->cleanReleaseName($row['relname']);
			$cleanedName = $releaseCleaning->releaseCleaner(
				$row['relname'], $row['fromname'], $row['group_name']
			);

			if (is_array($cleanedName)) {
				$properName = $cleanedName['properlynamed'];
				$prehashID = (isset($cleanerName['predb']) ? $cleanerName['predb'] : false);
				$isReqID = (isset($cleanerName['requestID']) ? $cleanerName['requestID'] : false);
				$cleanedName = $cleanedName['cleansubject'];
			} else {
				$properName = true;
				$isReqID = $prehashID = false;
			}

			if ($prehashID === false && $cleanedName !== '') {
				// try to match the cleaned searchname to predb title or filename here
				$preHash = new PreHash();
				$preMatch = $preHash->matchPre($cleanedName);
				if ($preMatch !== false) {
					$cleanedName = $preMatch['title'];
					$prehashID = $preMatch['prehashID'];
					$properName = true;
				}
			}
			$relid = $this->insertRelease(
				[
					'name'           => $this->pdo->escapeString($cleanRelName),
					'searchname'     => $this->pdo->escapeString(utf8_encode($cleanedName)),
					'totalpart'      => $row["parts"],
					'groupID'        => $row["groupID"],
					'guid'           => $this->pdo->escapeString($relguid),
					'categoryID'     => $categorize->determineCategory($groupID, $cleanedName),
					'regexID'        => $row["regexID"],
					'postdate'       => $this->pdo->escapeString($row['date']),
					'fromname'       => $this->pdo->escapeString($row['fromname']),
					'reqID'          => $row["reqID"],
					'passwordstatus' => ($page->site->checkpasswordedrar > 0 ? -1 : 0),
					'nzbstatus'      => \Enzebe::NZB_NONE,
					'isrenamed'      => ($properName === true ? 1 : 0),
					'reqidstatus'    => ($isReqID === true ? 1 : 0),
					'prehashID'      => ($prehashID === false ? 0 : $prehashID)
				]
			);
			//
			// Tag every binary for this release with its parent release id
			//
			$this->pdo->queryExec(sprintf("UPDATE %s SET procstat = %d, releaseID = %d WHERE relname = %s AND procstat = %d AND groupID = %d AND fromname=%s",
					$group['bname'], Releases::PROCSTAT_RELEASED, $relid, $this->pdo->escapeString($row["relname"]), Releases::PROCSTAT_READYTORELEASE, $groupID, $this->pdo->escapeString($row["fromname"])
				)
			);
		$cat = new \Categorize(['Settings' => $this->pdo]);
		//
		// Write the nzb to disk
		//
			//
			// Write the nzb to disk
			//
			$catId = $cat->determineCategory($groupID, $cleanRelName);
			$nzbfile = $this->nzb->getNZBPath($relguid, $page->site->nzbpath, true);
			$this->nzb->writeNZBforreleaseID($relid, $cleanRelName, $catId, $nzbfile, $groupID);

			//
			// Remove used binaries
			//
			$this->pdo->queryExec(sprintf("DELETE %s, %s FROM %s JOIN %s ON %s.ID = %s.binaryID WHERE releaseID = %d ",
					$group['pname'],
					$group['bname'],
					$group['pname'],
					$group['bname'],
					$group['bname'],
					$group['pname'],
					$relid
				)
			);

			//
			// If nzb successfully written, then load it and get size completion from it
			//
			$nzbInfo = new nzbInfo;
			if (!$nzbInfo->loadFromFile($nzbfile)) {
				$this->pdo->log->doEcho($this->pdo->log->primary('Failed to write nzb file (bad perms?) ' . $nzbfile . ''));
				//copy($nzbfile, "./ERRORNZB_".$relguid);
				$this->delete($relid);
			} else {
				// Check if gid already exists
				$dupes = $this->pdo->queryOneRow(sprintf("SELECT EXISTS(SELECT 1 FROM releases WHERE gid = %s) AS total", $this->pdo->escapeString($nzbInfo->gid)));
				if ($dupes['total'] > 0) {
					$this->pdo->log->doEcho($this->pdo->log->primary('Duplicate - ' . $cleanRelName . ''));
					$this->delete($relid);
				} else {
					$this->pdo->queryExec(sprintf("UPDATE releases SET totalpart = %d, size = %s, COMPLETION = %d, GID=%s , nzb_guid = %s WHERE ID = %d",
							$nzbInfo->filecount,
							$nzbInfo->filesize,
							$nzbInfo->completion,
							$this->pdo->escapeString($nzbInfo->gid),
							$this->pdo->escapeString($nzbInfo->gid),
							$relid
						)
					);
					$this->pdo->log->doEcho($this->pdo->log->primary('Added release ' . $cleanRelName . ''));

				}
             }
		}
	}
	/**
	 * @param $url
	 * @param $rev
	 * @param $nnid
	 */
	public function checkRegexesUptoDate($url, $rev, $nnid)
	{
		if ($url != "") {
			if ($nnid != "")
				$nnid = "?newznabID=" . $nnid . "&rev=" . $rev;
			$regfile = Utility::getUrl(['url' => $url . $nnid, 'method' => 'get', 'enctype' => 'gzip']);
			if ($regfile !== false && $regfile != "") {
				/*$Rev: 728 $*/
				if (preg_match('/\/\*\$Rev: (\d{3,4})/i', $regfile, $matches)) {
					$serverrev = intval($matches[1]);
					if ($serverrev > $rev) {

						$site = new Sites;

						$queries = explode(";", $regfile);
						$queries = array_map("trim", $queries);
						foreach ($queries as $q) {
							if ($q) {
								$this->pdo->queryExec($q);
							}
						}

						$site->updateLatestRegexRevision($serverrev);
						echo "Updated regexes to revision " . $serverrev . "\n";
					} else {
						echo "Using latest regex revision " . $rev . "\n";
					}
				} else {
					echo "Error Processing Regex File\n";
				}
			} else {
				echo "Error Regex File Does Not Exist or Unable to Connect\n";
			}
		}
	}

	/**
	 * Post-process releases.
	 *
	 * @param int        $postProcess
	 * @param NNTP       $nntp
	 *
	 * @void
	 * @access public
	 */
	public function postProcessReleases($postProcess, &$nntp)
	{
		if ($postProcess == 1) {
			(new PProcess(['Echo' => $this->echoCLI, 'Settings' => $this->pdo, 'Groups' => $this->groups]))->processAll($nntp);
		} else {
			if ($this->echoCLI) {
				$this->pdo->log->doEcho(
					$this->pdo->log->info(
						"\nPost-processing is not running inside the Releases class.\n" .
						"If you are using tmux or screen they might have their own scripts running Post-processing."
					)
				);
			}
		}
	}

	/**
	 * Apply regexes to groups
	 *
	 * @param $groupID
	 */
	public function applyRegex($groupID)
	{
		//
		// Get all regexes for all groups which are to be applied to new binaries
		// in order of how they should be applied
		//
		$group = $this->groups->getCBPTableNames($this->tablePerGroup, $groupID);
		$groups = $this->groups->getByID($groupID);
		$activeGroups = $this->groups->getActive(false);
		$this->releaseRegex->get();
		$this->pdo->log->doEcho($this->pdo->log->primary('Applying regex to binaries'), true);
	   foreach($activeGroups as $groupArr) {
			//check if regexes have already been applied during update binaries
			if ($groupArr['regexmatchonly'] == 1)
				continue;

			$groupRegexes = $this->releaseRegex->getForGroup($groupArr['name']);

			$this->pdo->log->doEcho($this->pdo->log->primary('Applying ' . sizeof($groupRegexes) . ' regexes to group ' . $groupArr['name']), true);

			// Get out all binaries of STAGE0 for current group
			$newUnmatchedBinaries = array();
			$ressql = sprintf('SELECT ID, name, date, totalParts, procstat, fromname FROM %s b
 								WHERE groupID = %d AND procstat IN (%d, %d) AND regexID IS NULL ORDER BY b.date ASC',
								$group['bname'],
								$groupArr['ID'],
								Releases::PROCSTAT_NEW,
								Releases::PROCSTAT_TITLENOTMATCHED
								);
			$resbin = $this->pdo->queryDirect($ressql);

			$matchedbins = 0;
			while ($rowbin = $this->pdo->getAssocArray($resbin)) {
				$regexMatches = array();
				foreach ($groupRegexes as $groupRegex) {
					$regexCheck = $this->releaseRegex->performMatch($groupRegex, $rowbin['name']);
					if ($regexCheck !== false) {
						$regexMatches = $regexCheck;
						break;
					}
				}

				if (!empty($regexMatches)) {
					$matchedbins++;
					$relparts = explode("/", $regexMatches['parts']);
					$this->pdo->queryExec(sprintf("UPDATE %s SET relname = replace(%s, '_', ' '), relpart = %d, reltotalpart = %d, procstat=%d, categoryID=%s, regexID=%d, reqID=%s WHERE ID = %d",
							$group['bname'], $this->pdo->escapeString($regexMatches['name']), $relparts[0], $relparts[1], Releases::PROCSTAT_TITLEMATCHED, $regexMatches['regcatid'], $regexMatches['regexID'], $this->pdo->escapeString($regexMatches['reqID']), $rowbin["ID"]
						)
					);
				} else {
					if ($rowbin['procstat'] == Releases::PROCSTAT_NEW)
						$newUnmatchedBinaries[] = $rowbin['ID'];
				}

			}

			//mark as not matched
			if (!empty($newUnmatchedBinaries))
				$this->pdo->queryExec(sprintf("UPDATE %s SET procstat=%d WHERE ID IN (%s)", $group['bname'], Releases::PROCSTAT_TITLENOTMATCHED, implode(',', $newUnmatchedBinaries)));

		}
	}

	public function getReleaseNameForReqId($url, $nnid, $groupname, $reqid)
	{
		if ($reqid == " null " || $reqid == "0" || $reqid == "")
			return "";

		$url = str_ireplace("[GROUP]", urlencode($groupname), $url);
		$url = str_ireplace("[REQID]", urlencode($reqid), $url);

		if ($nnid != "") {
			$url = $url . "&newznabID=" . $nnid;
		}


		$xml = Utility::getUrl([$url]);

		if ($xml === false || preg_match('/no feed/i', $xml))
			return "no feed";
		else {
			if ($xml != "") {
				$xmlObj = @simplexml_load_string($xml);
				$arrXml = objectsIntoArray($xmlObj);

				if (isset($arrXml["item"]) && is_array($arrXml["item"])) {
					foreach ($arrXml["item"] as $item) {
						$title = (array_key_exists("@attributes", $item) ? $item["@attributes"]["title"] : $item["title"]);

						return $title;
					}
				}
			}
		}

		return "";
	}

	public function cleanReleaseName($relname)
	{
		$cleanArr = array('#', '@', '$', '%', '^', '§', '¨', '©', 'Ö');

		$relname = str_replace($cleanArr, '', $relname);
		$relname = str_replace('_', ' ', $relname);

		return $relname;
	}

	public function insertRelease(array $parameters = [])
	{

		if ($parameters['regexID'] == "")
			$parameters['regexID'] = " null ";

		if ($parameters['reqID'] != "")
			$parameters['reqID'] = $this->pdo->escapeString('reqID');
		else
			$parameters['reqID'] = " null ";

		$parameters['id'] = $this->pdo->queryInsert(sprintf("INSERT INTO releases (name, searchname, totalpart, groupID, adddate, guid, categoryID, regexID, rageID, postdate, fromname, size, reqID, passwordstatus, completion, haspreview, nfostatus, nzbstatus,
					isrenamed, iscategorized, reqidstatus, prehashID)
                    VALUES (%s, %s, %d, %d, now(), %s, %d, %s, -1, %s, %s, 0, %s, %d, 100,-1, -1, %d, %d, 1, %d, %d)",
				$parameters['name'],
				$parameters['searchname'],
				$parameters['totalpart'],
				$parameters['groupID'],
				$parameters['guid'],
				$parameters['categoryID'],
				$parameters['regexID'],
				$parameters['postdate'],
				$parameters['fromname'],
				$parameters['reqID'],
				$parameters['passwordstatus'],
				$parameters['nzbstatus'],
				$parameters['isrenamed'],
				$parameters['reqidstatus'],
				$parameters['prehashID']
			)
		);

		$this->sphinxSearch->insertRelease($parameters);

		return $parameters['id'];
	}

	/**
	 * Delete one or more releases.
	 */
	public function delete($id, $isGuid = false)
	{

		$users = new Users();
		$s = new Sites();
		$nfo = new Nfo();
		$site = $s->get();
		$rf = new ReleaseFiles();
		$re = new ReleaseExtra();
		$rc = new ReleaseComments();
		$ri = new ReleaseImage();

		if (!is_array($id))
			$id = array($id);

		foreach ($id as $identifier) {
			//
			// delete from disk.
			//
			$rel = ($isGuid) ? $this->getByGuid($identifier) : $this->getById($identifier);

			$nzbpath = "";
			if ($isGuid)
				$nzbpath = $site->nzbpath . substr($identifier, 0, 1) . "/" . $identifier . ".nzb.gz";
			elseif ($rel)
				$nzbpath = $site->nzbpath . substr($rel["guid"], 0, 1) . "/" . $rel["guid"] . ".nzb.gz";

			if ($nzbpath != "" && file_exists($nzbpath))
				unlink($nzbpath);

			$audiopreviewpath = "";
			if ($isGuid)
				$audiopreviewpath = WWW_DIR . 'covers/audio/' . $identifier . ".mp3";
			elseif ($rel)
				$audiopreviewpath = WWW_DIR . 'covers/audio/' . $rel["guid"] . ".mp3";

			if ($audiopreviewpath && file_exists($audiopreviewpath))
				unlink($audiopreviewpath);

			if ($rel) {
				$nfo->deleteReleaseNfo($rel['ID']);
				$rc->deleteCommentsForRelease($rel['ID']);
				$users->delCartForRelease($rel['ID']);
				$users->delDownloadRequestsForRelease($rel['ID']);
				$rf->delete($rel['ID']);
				$re->delete($rel['ID']);
				$re->deleteFull($rel['ID']);
				$ri->delete($rel['guid']);
				$this->pdo->queryExec(sprintf("DELETE FROM releases WHERE id = %d", $rel['ID']));
			}
		}
	}

	/**
	 * Deletes a single release by GUID, and all the corresponding files.
	 *
	 * @param array        $identifiers ['g' => Release GUID(mandatory), 'id => ReleaseID(optional, pass false)]
	 * @param NZB          $nzb
	 * @param ReleaseImage $releaseImage
	 */
	public function deleteSingle($identifiers, $nzb, $releaseImage)
	{
		// Delete NZB from disk.
		$nzbPath = $nzb->getNZBPath($identifiers['g']);
		if ($nzbPath) {
			@unlink($nzbPath);
		}


		// Delete images.
		$releaseImage->delete($identifiers['g']);

		//Delete from sphinx.
		$this->sphinxSearch->deleteRelease($identifiers, $this->pdo);

		// Delete from DB.
		$this->pdo->queryExec(
			sprintf('
				DELETE r, rn, rc, uc, rf, ra, rs, rv, re
				FROM releases r
				LEFT OUTER JOIN releasenfo rn ON rn.releaseID = r.ID
				LEFT OUTER JOIN releasecomment rc ON rc.releaseID = r.ID
				LEFT OUTER JOIN usercart uc ON uc.releaseID = r.ID
				LEFT OUTER JOIN releasefiles rf ON rf.releaseID = r.ID
				LEFT OUTER JOIN releaseaudio ra ON ra.releaseID = r.ID
				LEFT OUTER JOIN releasesubs rs ON rs.releaseID = r.ID
				LEFT OUTER JOIN releasevideo rv ON rv.releaseID = r.ID
				LEFT OUTER JOIN releaseextrafull re ON re.releaseID = r.ID
				WHERE r.guid = %s',
				$this->pdo->escapeString($identifiers['g'])
			)
		);
	}

	public function getTopDownloads()
	{


		return $this->pdo->query("SELECT ID, searchname, guid, adddate, grabs FROM releases
							WHERE grabs > 0
							ORDER BY grabs DESC
							LIMIT 10"
		);
	}

	public function getTopComments()
	{


		return $this->pdo->query("SELECT ID, guid, searchname, adddate, comments FROM releases
							WHERE comments > 0
							ORDER BY comments DESC
							LIMIT 10"
		);
	}

	public function getRecentlyAdded()
	{


		return $this->pdo->query("SELECT concat(cp.title, ' > ', category.title) AS title, COUNT(*) AS count
                            FROM category
                            LEFT OUTER JOIN category cp ON cp.ID = category.parentID
                            INNER JOIN releases ON releases.categoryID = category.ID
                            WHERE releases.adddate > NOW() - INTERVAL 1 WEEK
                            GROUP BY concat(cp.title, ' > ', category.title)
                            ORDER BY COUNT(*) DESC"
		);
	}

	/**
	 * Get all newest movies with coves for poster wall.
	 *
	 * @return array
	 */
	public function getNewestMovies()
	{


		return $this->pdo->query(
			"SELECT DISTINCT (a.imdbID),
				guid, name, b.title, searchname, size, completion,
				postdate, categoryID, comments, grabs, c.cover
			FROM releases a, category b, movieinfo c
			WHERE a.categoryID BETWEEN 2000 AND 2999
			AND b.title = 'Movies'
			AND a.imdbID = c.imdbID
			AND a.imdbID !='NULL'
			AND a.imdbID != 0
			AND c.cover = 1
			GROUP BY a.imdbID
			ORDER BY a.postdate
			DESC LIMIT 24"
		);
	}

	/**
	 * Get all newest console games with covers for poster wall.
	 *
	 * @return array
	 */
	public function getNewestConsole()
	{


		return $this->pdo->query(
			"SELECT DISTINCT (a.consoleinfoID),
				guid, name, b.title, searchname, size, completion,
				postdate, categoryID, comments, grabs, c.cover
			FROM releases a, category b, consoleinfo c
			WHERE c.cover > 0
			AND a.categoryID BETWEEN 1000 AND 1999
			AND b.title = 'Console'
			AND a.consoleinfoID = c.ID
			AND a.consoleinfoID != -2
			AND a.consoleinfoID != 0
			GROUP BY a.consoleinfoID
			ORDER BY a.postdate
			DESC LIMIT 35"
		);
	}

	/**
	 * Get all newest PC games with covers for poster wall.
	 *
	 * @return array
	 */
	public function getNewestGames()
	{


		return $this->pdo->query(
			"SELECT DISTINCT (a.gamesinfo_id),
				guid, name, b.title, searchname, size, completion,
				postdate, categoryID, comments, grabs, c.cover
			FROM releases a, category b, gamesinfo c
			WHERE c.cover > 0
			AND a.categoryID = 4050
			AND b.title = 'Games'
			AND a.gamesinfo_id = c.ID
			AND a.gamesinfo_id != -2
			AND a.gamesinfo_id != 0
			GROUP BY a.gamesinfo_id
			ORDER BY a.postdate
			DESC LIMIT 35"
		);
	}

	/**
	 * Get all newest music with covers for poster wall.
	 *
	 * @return array
	 */
	public function getNewestMP3s()
	{


		return $this->pdo->query(
			"SELECT DISTINCT (a.musicinfoID),
				guid, name, b.title, searchname, size, completion,
				 postdate, categoryID, comments, grabs, c.cover
			FROM releases a, category b, musicinfo c
			WHERE c.cover > 0
			AND a.categoryID BETWEEN 3000 AND 3999
			AND a.categoryID != 3030
			AND b.title = 'Audio'
			AND a.musicinfoID = c.ID
			AND a.musicinfoID != -2
			GROUP BY a.musicinfoID
			ORDER BY a.postdate
			DESC LIMIT 24"
		);
	}

	/**
	 * Get all newest books with covers for poster wall.
	 *
	 * @return array
	 */
	public function getNewestBooks()
	{


		return $this->pdo->query(
			"SELECT DISTINCT (a.bookinfoID),
				guid, name, b.title, searchname, size, completion,
				postdate, categoryID, comments, grabs, url, c.cover, c.title AS booktitle, c.author
			FROM releases a, category b, bookinfo c
			WHERE c.cover > 0
			AND (a.categoryID BETWEEN 7000 AND 7999 OR a.categoryID = 3030)
			AND (b.title = 'Books' OR b.title = 'Audiobook')
			AND a.bookinfoID = c.ID
			AND a.bookinfoID != -2
			GROUP BY a.bookinfoID
			ORDER BY a.postdate
			DESC LIMIT 24"
		);
	}

	/**
	 * Get all newest xxx with covers for poster wall.
	 *
	 * @return array
	 */
	public function getNewestXXX()
	{

		return $this->pdo->queryDirect(
			"SELECT r.xxxinfo_id, r.guid, r.name, r.searchname, r.size, r.completion,
				r.postdate, r.categoryID, r.comments, r.grabs,
				xxx.cover, xxx.title
			FROM releases r
			INNER JOIN xxxinfo xxx ON r.xxxinfo_id = xxx.ID
			WHERE r.categoryID BETWEEN 6000 AND 6040
			AND xxx.ID > 0
			AND xxx.cover = 1
			GROUP BY xxx.ID
			ORDER BY r.postdate DESC
			LIMIT 24"
		);
	}

	/**
	 * Get all newest TV with covers for poster wall.
	 *
	 * @return array
	 */
	public function getNewestTV()
	{

		return $this->pdo->queryDirect(
			"SELECT r.rageID, r.guid, r.name, r.searchname, r.size, r.completion,
				r.postdate, r.categoryID, r.comments, r.grabs,
				tv.ID AS tvid, tv.imgdata, tv.releasetitle AS tvtitle
			FROM releases r
			INNER JOIN tvrage tv USING (rageID)
			WHERE r.categoryID BETWEEN 5000 AND 5999
			AND tv.rageID > 0
			AND length(tv.imgdata) > 0
			GROUP BY tv.rageID
			ORDER BY r.postdate DESC
			LIMIT 24"
		);
	}

	/**
	 * Process RequestID's.
	 *
	 * @param int|string $groupID
	 * @param int        $limit
	 * @param bool       $local
	 *
	 * @access public
	 * @void
	 */
	public function processRequestIDs($groupID = '', $limit = 5000, $local = true)
	{

		$echoCLI = NN_ECHOCLI;
		$groups = new Groups();
		$s = new Sites();
		$site = $s->get();
		$consoleTools = new ConsoleTools(['ColorCLI' => $this->pdo->log]);
		if ($local === false && $site->lookup_reqids == 0) {
			return;
		}

		$startTime = time();
		if ($echoCLI) {
			$this->pdo->log->doEcho(
				$this->pdo->log->header(
					sprintf(
						"Process Releases -> Request ID %s lookup -- limit %s",
						($local === true ? 'local' : 'web'),
						$limit
					)
				)
			);
		}

		if ($local === true) {
			$foundRequestIDs = (
			new \RequestIDLocal(
				['Echo'   => $echoCLI, 'ConsoleTools' => $consoleTools,
				 'Groups' => $groups, 'Settings' => $this->pdo
				]
			)
			)->lookupRequestIDs(['GroupID' => $groupID, 'limit' => $limit, 'time' => 168]);
		} else {
			$foundRequestIDs = (
			new \RequestIDWeb(
				['Echo'   => $echoCLI, 'ConsoleTools' => $consoleTools,
				 'Groups' => $groups, 'Settings' => $this->pdo
				]
			)
			)->lookupRequestIDs(['GroupID' => $groupID, 'limit' => $limit, 'time' => 168]);
		}
		if ($echoCLI) {
			$this->pdo->log->doEcho(
				$this->pdo->log->primary(
					number_format($foundRequestIDs) .
					' releases updated in ' .
					$consoleTools->convertTime(time() - $startTime)
				), true
			);
		}
	}

	/**
	 * Delete unwanted releases based on admin settings.
	 * This deletes releases based on group.
	 *
	 * @param int|string $groupID (optional)
	 *
	 * @void
	 * @access public
	 */
	public function deletedReleasesByGroup($groupID = '')
	{
		$startTime = time();
		$minSizeDeleted = $maxSizeDeleted = $minFilesDeleted = 0;

		if ($this->echoCLI) {
			echo $this->pdo->log->header("Process Releases -> Delete releases smaller/larger than minimum size/file count from group/site setting.");
		}

		if ($groupID == '') {
			$groupIDs = $this->groups->getActiveIDs();
		} else {
			$groupIDs = [['ID' => $groupID]];
		}

		$maxSizeSetting = $this->site->maxsizetoformrelease;
		$minSizeSetting = $this->site->minsizetoformrelease;
		$minFilesSetting = $this->site->minfilestoformrelease;

		foreach ($groupIDs as $groupID) {
			$releases = $this->pdo->queryDirect(
				sprintf("
					SELECT r.guid, r.ID
					FROM releases r
					INNER JOIN groups g ON g.ID = r.groupID
					WHERE r.groupID = %d
					AND greatest(IFNULL(g.minsizetoformrelease, 0), %d) > 0
					AND r.size < greatest(IFNULL(g.minsizetoformrelease, 0), %d)",
					$groupID['ID'],
					$minSizeSetting,
					$minSizeSetting
				)
			);
			if ($releases instanceof \Traversable) {
				foreach ($releases as $release) {
					$this->deleteSingle(['g' => $release['guid'], 'i' => $release['ID']], $this->nzb, $this->releaseImage);
					$minSizeDeleted++;
				}
			}

			if ($maxSizeSetting > 0) {
				$releases = $this->pdo->queryDirect(
					sprintf('
						SELECT ID, guid
						FROM releases
						WHERE groupID = %d
						AND size > %d',
						$groupID['ID'],
						$maxSizeSetting
					)
				);
				if ($releases instanceof \Traversable) {
					foreach ($releases as $release) {
						$this->deleteSingle(['g' => $release['guid'], 'i' => $release['ID']], $this->nzb, $this->releaseImage);
						$maxSizeDeleted++;
					}
				}
			}

			$releases = $this->pdo->queryDirect(
				sprintf("
					SELECT r.ID, r.guid
					FROM releases r
					INNER JOIN groups g ON g.ID = r.groupID
					WHERE r.groupID = %d
					AND greatest(IFNULL(g.minfilestoformrelease, 0), %d) > 0
					AND r.totalpart < greatest(IFNULL(g.minfilestoformrelease, 0), %d)",
					$groupID['ID'],
					$minFilesSetting,
					$minFilesSetting
				)
			);
			if ($releases instanceof \Traversable) {
				foreach ($releases as $release) {
					$this->deleteSingle(['g' => $release['guid'], 'i' => $release['ID']], $this->nzb, $this->releaseImage);
					$minFilesDeleted++;
				}
			}
		}

		if ($this->echoCLI) {
			$this->pdo->log->doEcho(
				$this->pdo->log->primary(
					'Deleted ' . ($minSizeDeleted + $maxSizeDeleted + $minFilesDeleted) .
					' releases: ' . PHP_EOL .
					$minSizeDeleted . ' smaller than, ' . $maxSizeDeleted . ' bigger than, ' . $minFilesDeleted .
					' with less files than site/groups setting in: ' .
					$this->consoleTools->convertTime(time() - $startTime)
				), true
			);
		}
	}

	/**
	 * Delete releases using admin settings.
	 * This deletes releases, regardless of group.
	 *
	 * @void
	 * @access public
	 */
	public function deleteReleases()
	{
		$startTime = time();
		$category = new \Category(['Settings' => $this->pdo]);
		$genres = new \Genres(['Settings' => $this->pdo]);
		$passwordDeleted = $duplicateDeleted = $retentionDeleted = $completionDeleted = $disabledCategoryDeleted = 0;
		$disabledGenreDeleted = $miscRetentionDeleted = $miscHashedDeleted = $categoryMinSizeDeleted = 0;

		// Delete old releases and finished collections.
		if ($this->echoCLI) {
			$this->pdo->log->doEcho($this->pdo->log->header("Process Releases -> Delete old releases and passworded releases."));
		}

		// Releases past retention.
		if ($this->site->releaseretentiondays != 0) {
			$releases = $this->pdo->queryDirect(
				sprintf(
					'SELECT ID, guid FROM releases WHERE postdate < (NOW() - INTERVAL %d DAY)',
					$this->site->releaseretentiondays
				)
			);
			if ($releases instanceof \Traversable) {
				foreach ($releases as $release) {
					$this->deleteSingle(['g' => $release['guid'], 'i' => $release['ID']], $this->nzb, $this->releaseImage);
					$retentionDeleted++;
				}
			}
		}

		// Passworded releases.
		if ($this->site->deletepasswordedrelease == 1) {
			$releases = $this->pdo->queryDirect(
				sprintf(
					'SELECT ID, guid FROM releases WHERE passwordstatus = %d',
					\Releases::PASSWD_RAR
				)
			);
			if ($releases instanceof \Traversable) {
				foreach ($releases as $release) {
					$this->deleteSingle(['g' => $release['guid'], 'i' => $release['ID']], $this->nzb, $this->releaseImage);
					$passwordDeleted++;
				}
			}
		}

		// Possibly passworded releases.
		if ($this->site->deletepossiblerelease == 1) {
			$releases = $this->pdo->queryDirect(
				sprintf(
					'SELECT ID, guid FROM releases WHERE passwordstatus = %d',
					\Releases::PASSWD_POTENTIAL
				)
			);
			if ($releases instanceof \Traversable) {
				foreach ($releases as $release) {
					$this->deleteSingle(['g' => $release['guid'], 'i' => $release['ID']], $this->nzb, $this->releaseImage);
					$passwordDeleted++;
				}
			}
		}

		if ($this->crossPostTime != 0) {
			// Crossposted releases.
			do {
				$releases = $this->pdo->queryDirect(
					sprintf(
						'SELECT ID, guid FROM releases WHERE adddate > (NOW() - INTERVAL %d HOUR) GROUP BY name HAVING COUNT(name) > 1',
						$this->crossPostTime
					)
				);
				$total = 0;
				if ($releases && $releases->rowCount()) {
					$total = $releases->rowCount();
					foreach ($releases as $release) {
						$this->deleteSingle(['g' => $release['guid'], 'i' => $release['ID']], $this->nzb, $this->releaseImage);
						$duplicateDeleted++;
					}
				}
			} while ($total > 0);
		}

		if ($this->completion > 0) {
			$releases = $this->pdo->queryDirect(
				sprintf('SELECT ID, guid FROM releases WHERE completion < %d AND completion > 0', $this->completion)
			);
			if ($releases instanceof \Traversable) {
				foreach ($releases as $release) {
					$this->deleteSingle(['g' => $release['guid'], 'i' => $release['ID']], $this->nzb, $this->releaseImage);
					$completionDeleted++;
				}
			}
		}

		// Disabled categories.
		$disabledCategories = $category->getDisabledIDs();
		if (count($disabledCategories) > 0) {
			foreach ($disabledCategories as $disabledCategory) {
				$releases = $this->pdo->queryDirect(
					sprintf('SELECT ID, guid FROM releases WHERE categoryID = %d', $disabledCategory['ID'])
				);
				if ($releases instanceof \Traversable) {
					foreach ($releases as $release) {
						$disabledCategoryDeleted++;
						$this->deleteSingle(['g' => $release['guid'], 'i' => $release['ID']], $this->nzb, $this->releaseImage);
					}
				}
			}
		}

		// Delete smaller than category minimum sizes.
		$categories = $this->pdo->queryDirect('
			SELECT c.ID AS id,
			CASE WHEN c.minsizetoformrelease = 0 THEN cp.minsizetoformrelease ELSE c.minsizetoformrelease END AS minsize
			FROM category c
			INNER JOIN category cp ON cp.ID = c.parentID
			WHERE c.parentID IS NOT NULL'
		);

		if ($categories instanceof \Traversable) {
			foreach ($categories as $category) {
				if ($category['minsize'] > 0) {
					$releases = $this->pdo->queryDirect(
						sprintf('
							SELECT r.ID, r.guid
							FROM releases r
							WHERE r.categoryID = %d
							AND r.size < %d',
							$category['ID'],
							$category['minsize']
						)
					);
					if ($releases instanceof \Traversable) {
						foreach ($releases as $release) {
							$this->deleteSingle(['g' => $release['guid'], 'i' => $release['ID']], $this->nzb, $this->releaseImage);
							$categoryMinSizeDeleted++;
						}
					}
				}
			}
		}

		// Disabled music genres.
		$genrelist = $genres->getDisabledIDs();
		if (count($genrelist) > 0) {
			foreach ($genrelist as $genre) {
				$releases = $this->pdo->queryDirect(
					sprintf('
						SELECT ID, guid
						FROM releases
						INNER JOIN (SELECT ID AS mid FROM musicinfo WHERE musicinfo.genreID = %d) mi
						ON musicinfoID = mid',
						$genre['ID']
					)
				);
				if ($releases instanceof \Traversable) {
					foreach ($releases as $release) {
						$disabledGenreDeleted++;
						$this->deleteSingle(['g' => $release['guid'], 'i' => $release['ID']], $this->nzb, $this->releaseImage);
					}
				}
			}
		}

		// Misc other.
		if ($this->site->miscotherretentionhours > 0) {
			$releases = $this->pdo->queryDirect(
				sprintf('
					SELECT ID, guid
					FROM releases
					WHERE categoryID = %d
					AND adddate <= NOW() - INTERVAL %d HOUR',
					\Category::CAT_MISC_OTHER,
					$this->site->miscotherretentionhours
				)
			);
			if ($releases instanceof \Traversable) {
				foreach ($releases as $release) {
					$this->deleteSingle(['g' => $release['guid'], 'i' => $release['ID']], $this->nzb, $this->releaseImage);
					$miscRetentionDeleted++;
				}
			}
		}

		// Misc hashed.
		if ($this->site->mischashedretentionhours > 0) {
			$releases = $this->pdo->queryDirect(
				sprintf('
					SELECT ID, guid
					FROM releases
					WHERE categoryID = %d
					AND adddate <= NOW() - INTERVAL %d HOUR',
					\Category::CAT_MISC_HASHED,
					$this->site->mischashedretentionhours
				)
			);
			if ($releases instanceof \Traversable) {
				foreach ($releases as $release) {
					$this->deleteSingle(['g' => $release['guid'], 'i' => $release['ID']], $this->nzb, $this->releaseImage);
					$miscHashedDeleted++;
				}
			}
		}

		if ($this->echoCLI) {
			$this->pdo->log->doEcho(
				$this->pdo->log->primary(
					'Removed releases: ' .
					number_format($retentionDeleted) .
					' past retention, ' .
					number_format($passwordDeleted) .
					' passworded, ' .
					number_format($duplicateDeleted) .
					' crossposted, ' .
					number_format($disabledCategoryDeleted) .
					' from disabled categories, ' .
					number_format($categoryMinSizeDeleted) .
					' smaller than category settings, ' .
					number_format($disabledGenreDeleted) .
					' from disabled music genres, ' .
					number_format($miscRetentionDeleted) .
					' from misc->other' .
					number_format($miscHashedDeleted) .
					' from misc->hashed' .
					($this->completion > 0
						? ', ' . number_format($completionDeleted) . ' under ' . $this->completion . '% completion.'
						: '.'
					)
				)
			);

			$totalDeleted = (
				$retentionDeleted + $passwordDeleted + $duplicateDeleted + $disabledCategoryDeleted +
				$disabledGenreDeleted + $miscRetentionDeleted + $miscHashedDeleted + $completionDeleted +
				$categoryMinSizeDeleted
			);
			if ($totalDeleted > 0) {
				$this->pdo->log->doEcho(
					$this->pdo->log->primary(
						"Removed " . number_format($totalDeleted) . ' releases in ' .
						$this->consoleTools->convertTime(time() - $startTime)
					)
				);
			}
		}
	}

	/**
	 * Categorizes releases.
	 *
	 * @param string $type  name or searchname | Categorize using the search name or subject.
	 * @param string $where Optional "where" query parameter.
	 *
	 * @return int Quantity of categorized releases.
	 * @access public
	 */
	public function categorizeRelease($type, $where = '')
	{
		$cat = new \Categorize(['Settings' => $this->pdo]);
		$categorized = $total = 0;
		$releases = $this->pdo->queryDirect(sprintf('SELECT ID, %s, groupID FROM releases %s', $type, $where));
		if ($releases && $releases->rowCount()) {
			$total = $releases->rowCount();
			foreach ($releases as $release) {
				$catId = $cat->determineCategory($release['groupID'], $release[$type]);
				$this->pdo->queryExec(
					sprintf('UPDATE releases SET categoryID = %d, iscategorized = 1 WHERE ID = %d', $catId, $release['ID'])
				);
				$categorized++;
				if ($this->echoCLI) {
					$this->consoleTools->overWritePrimary(
						'Categorizing: ' . $this->consoleTools->percentString($categorized, $total)
					);
				}
			}
		}
		if ($this->echoCLI !== false && $categorized > 0) {
			echo PHP_EOL;
		}

		return $categorized;
	}

	/**
	 * Categorize releases.
	 *
	 * @param int        $categorize
	 * @param int|string $groupID (optional)
	 *
	 * @void
	 * @access public
	 */
	public function categorizeReleases($categorize, $groupID = '')
	{
		$startTime = time();
		if ($this->echoCLI) {
			echo $this->pdo->log->header("Process Releases -> Categorize releases.");
		}
		switch ((int)$categorize) {
			case 2:
				$type = 'searchname';
				break;
			case 1:
			default:

				$type = 'name';
				break;
		}
		$this->categorizeRelease(
			$type,
			(!empty($groupID) ? 'WHERE iscategorized = 0 AND groupID = ' . $groupID : 'WHERE iscategorized = 0')
		);

		if ($this->echoCLI) {
			$this->pdo->log->doEcho($this->pdo->log->primary($this->consoleTools->convertTime(time() - $startTime)), true);
		}
	}

	/**
	 * Return all releases to other->misc category.
	 *
	 * @param string $where Optional "where" query parameter.
	 *
	 * @void
	 * @access public
	 */
	public function resetCategorize($where = '')
	{
		$this->pdo->queryExec(
			sprintf('UPDATE releases SET categoryID = %d, iscategorized = 0 %s', \Category::CAT_MISC_OTHER, $where)
		);
	}

	/**
	 * Create a GUID for a release.
	 * @return string
	 */
	public function createGUID()
	{
		return sha1(uniqid('', true) . mt_rand());
	}

	/**
	 * Buffer of the password status string to form part of the query.
	 * @var null|string
	 */
	private $passwordSettingBuffer = null;

	/**
	 * Return site setting for hiding/showing passworded releases.
	 *
	 * @return int
	 */
	public function showPasswords()
	{
		if (!is_null($this->passwordSettingBuffer)) {
			return $this->passwordSettingBuffer;
		}
		$setting = $this->pdo->queryOneRow(
			"SELECT value
			FROM site
			WHERE setting = 'showpasswordedrelease'"
		);
		$passwordStatus = ('= ' . \Releases::PASSWD_NONE);
		if ($setting !== false) {
			switch ($setting['value']) {
				case 1:
					$passwordStatus = ('<= ' . \Releases::PASSWD_POTENTIAL);
					break;
				case 10:
					$passwordStatus = ('<= ' . \Releases::PASSWD_RAR);
					break;
			}
		}
		$this->passwordSettingBuffer = $passwordStatus;
		return $passwordStatus;
	}
}