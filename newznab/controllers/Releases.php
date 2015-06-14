<?php

use newznab\db\Settings;
use newznab\utility\Utility;
use newznab\processing\PProcess;

/**
 * This class handles storage and retrieval of releases rows and the main processing functions
 * for turning binaries into releases.
 */
class Releases
{
	// RAR/ZIP Passworded indicator.
	const PASSWD_NONE      =  0; // No password.
	const PASSWD_POTENTIAL =  1; // Might have a password.
	const BAD_FILE         =  2; // Possibly broken RAR/ZIP.
	const PASSWD_RAR       = 10; // Definitely passworded.

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
	 * @var SphinxSearch
	 */
	public $sphinxSearch;

	/**
	 * @var newznab\db\Settings
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
	 * @var string
	 */
	private $showPasswords;

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

		$this->pdo = ($options['Settings'] instanceof Settings ? $options['Settings'] : new Settings());
		$this->consoleTools = ($options['ConsoleTools'] instanceof \ConsoleTools ? $options['ConsoleTools'] : new \ConsoleTools(['ColorCLI' => $this->pdo->log]));
		$this->groups = ($options['Groups'] instanceof \Groups ? $options['Groups'] : new \Groups(['Settings' => $this->pdo]));
		$this->nzb = ($options['NZB'] instanceof \NZB ? $options['NZB'] : new \NZB());
		$this->releaseCleaning = ($options['ReleaseCleaning'] instanceof \ReleaseCleaning ? $options['ReleaseCleaning'] : new \ReleaseCleaning($this->pdo));
		$this->releaseImage = ($options['ReleaseImage'] instanceof \ReleaseImage ? $options['ReleaseImage'] : new \ReleaseImage($this->pdo));
		$this->updategrabs = ($this->pdo->getSetting('grabstatus') == '0' ? false : true);
		$this->passwordStatus = ($this->pdo->getSetting('checkpasswordedrar') == 1 ? -1 : 0);
		$this->sphinxSearch = new \SphinxSearch();
		$this->releaseSearch = new \ReleaseSearch($this->pdo, $this->sphinxSearch);
		$this->releaseRegex = new \ReleaseRegex();

		$this->tablePerGroup = ($this->pdo->getSetting('tablepergroup') == 0 ? false : true);
		$this->crossPostTime = ($this->pdo->getSetting('crossposttime') != '' ? (int)$this->pdo->getSetting('crossposttime') : 2);
		$this->releaseCreationLimit = ($this->pdo->getSetting('maxnzbsprocessed') != '' ? (int)$this->pdo->getSetting('maxnzbsprocessed') : 1000);
		$this->completion = ($this->pdo->getSetting('completionpercent') != '' ? (int)$this->pdo->getSetting('completionpercent') : 0);
		$this->processRequestIDs = (int)$this->pdo->getSetting('lookup_reqids');
		if ($this->completion > 100) {
			$this->completion = 100;
			echo $this->pdo->log->error(PHP_EOL . 'You have an invalid setting for completion. It must be lower than 100.');
		}
		$this->showPasswords = self::showPasswords($this->pdo);
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
							m.id AS movie_id, m.title, m.rating, m.cover, m.plot, m.year, m.genre, m.director, m.actors, m.tagline,
							mu.id AS music_id, mu.title AS mu_title, mu.cover AS mu_cover, mu.year AS mu_year, mu.artist AS mu_artist, mu.tracks AS mu_tracks, mu.review AS mu_review,
							ep.id AS ep_id, ep.showtitle AS ep_showtitle, ep.airdate AS ep_airdate, ep.fullep AS ep_fullep, ep.overview AS ep_overview,
							tvrage.imgdata AS rage_imgdata, tvrage.id AS rg_ID
							FROM releases
							LEFT OUTER JOIN category c ON c.id = releases.categoryid
							LEFT OUTER JOIN category cp ON cp.id = c.parentid
							LEFT OUTER JOIN movieinfo m ON m.imdbid = releases.imdbid
							LEFT OUTER JOIN musicinfo mu ON mu.id = releases.musicinfoid
							LEFT OUTER JOIN episodeinfo ep ON ep.id = releases.episodeinfoid
							LEFT OUTER JOIN tvrage ON tvrage.rageid = releases.rageid
						WHERE %s", $nsql
		);

		return $this->pdo->queryDirect($sql);
	}

	/**
	 * Get a count of releases for pager. used in admin manage list
	 */
	public function getCount()
	{

		$res = $this->pdo->queryOneRow("SELECT count(id) AS num FROM releases");

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

		return $this->pdo->query(" SELECT releases.*, concat(cp.title, ' > ', c.title) AS category_name FROM releases LEFT OUTER JOIN category c ON c.id = releases.categoryid LEFT OUTER JOIN category cp ON cp.id = c.parentid ORDER BY postdate DESC" . $limit);
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
							$chlist .= ", " . $child["id"];

						if ($chlist != "-99")
							$catsrch .= " releases.categoryid in (" . $chlist . ") or ";
					} else {
						$catsrch .= sprintf(" releases.categoryid = %d or ", $category);
					}
				}
			}
			$catsrch .= "1=2 )";
		}

		$sql = sprintf("SELECT count(id) AS num FROM releases WHERE haspreview = %d %s ", $previewtype, $catsrch);
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
							$chlist .= ", " . $child["id"];

						if ($chlist != "-99")
							$catsrch .= " releases.categoryid in (" . $chlist . ") or ";
					} else {
						$catsrch .= sprintf(" releases.categoryid = %d or ", $category);
					}
				}
			}
			$catsrch .= "1=2 )";
		}

		if ($start === false)
			$limit = "";
		else
			$limit = " LIMIT " . $start . "," . $num;

		$sql = sprintf(" SELECT releases.*, concat(cp.title, ' > ', c.title) AS category_name FROM releases LEFT OUTER JOIN category c ON c.id = releases.categoryid LEFT OUTER JOIN category cp ON cp.id = c.parentid WHERE haspreview = %d %s ORDER BY postdate DESC %s", $previewtype, $catsrch, $limit);

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
				($groupName != '' ? 'INNER JOIN groups g ON g.id = r.groupid' : ''),
				Enzebe::NZB_ADDED,
				$this->showPasswords,
				($groupName != '' ? sprintf(' AND g.name = %s', $this->pdo->escapeString($groupName)) : ''),
				$this->categorySQL($cat),
				($maxAge > 0 ? (' AND r.postdate > NOW() - INTERVAL ' . $maxAge . ' DAY ') : ''),
				(count($excludedCats) ? (' AND r.categoryid NOT IN (' . implode(',', $excludedCats) . ')') : '')
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
					CONCAT(cp.id, ',', c.id) AS category_ids,
					g.name AS group_name,
					rn.id AS nfoid,
					re.releaseid AS reid
				FROM releases r
				STRAIGHT_JOIN groups g ON g.id = r.groupid
				STRAIGHT_JOIN category c ON c.id = r.categoryid
				INNER JOIN category cp ON cp.id = c.parentid
				LEFT OUTER JOIN releasevideo re ON re.releaseid = r.id
				LEFT OUTER JOIN releasenfo rn ON rn.releaseid = r.id
				AND rn.nfo IS NOT NULL
				WHERE r.nzbstatus = %d
				AND r.passwordstatus %s
				%s %s %s %s
				ORDER BY %s %s %s",
				Enzebe::NZB_ADDED,
				$this->showPasswords,
				$this->categorySQL($cat),
				($maxAge > 0 ? (" AND postdate > NOW() - INTERVAL " . $maxAge . ' DAY ') : ''),
				(count($excludedCats) ? (' AND r.categoryid NOT IN (' . implode(',', $excludedCats) . ')') : ''),
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
				$orderfield = 'categoryid';
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
	 * Get list of releases available for export.
	 *
	 * @param string $postFrom (optional) Date in this format : 01/01/2014
	 * @param string $postTo   (optional) Date in this format : 01/01/2014
	 * @param string $groupID  (optional) Group ID.
	 *
	 * @return array
	 */
	public function getForExport($postFrom = '', $postTo = '', $groupID = '')
	{
		return $this->pdo->query(
			sprintf(
				"SELECT searchname, guid, groups.name AS gname, CONCAT(cp.title,'_',category.title) AS catName
				FROM releases r
				INNER JOIN category ON r.categoryid = category.id
				INNER JOIN groups ON r.groupid = groups.id
				INNER JOIN category cp ON cp.id = category.parentid
				WHERE r.nzbstatus = %d
				%s %s %s",
				Enzebe::NZB_ADDED,
				$this->exportDateString($postFrom),
				$this->exportDateString($postTo, false),
				(($groupID != '' && $groupID != '-1') ? sprintf(' AND group_id = %d ', $groupID) : '')
			)
		);
	}

	/**
	 * Create a date query string for exporting.
	 *
	 * @param string $date
	 * @param bool   $from
	 *
	 * @return string
	 */
	private function exportDateString($date, $from = true)
	{
		if ($date != '') {
			$dateParts = explode('/', $date);
			if (count($dateParts) === 3) {
				$date = sprintf(
					' AND postdate %s %s ',
					($from ? '>' : '<'),
					$this->pdo->escapeString(
						$dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0] .
						($from ? ' 00:00:00' : ' 23:59:59')
					)
				);
			} else {
				$date = '';
			}
		}
		return $date;
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

		$groups = $this->pdo->query("SELECT DISTINCT groups.id, groups.name FROM releases INNER JOIN groups ON groups.id = releases.groupid");
		$temp_array = array();

		if ($blnIncludeAll)
			$temp_array[-1] = "--All Groups--";

		foreach ($groups as $group)
			$temp_array[$group["id"]] = $group["name"];

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
				$cartsrch = sprintf(" inner join usercart on usercart.userid = %d and usercart.releaseid = releases.id ", $uid);
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
								$chlist .= ", " . $child["id"];

							if ($chlist != "-99")
								$catsrch .= " releases.categoryid in (" . $chlist . ") or ";
						} else {
							$catsrch .= sprintf(" releases.categoryid = %d or ", $category);
						}
					}
				}
				$catsrch .= "1=2 )";
			}
		}

		$rage = ($rageid > -1) ? sprintf(" and releases.rageid = %d ", $rageid) : '';
		$anidb = ($anidbid > -1) ? sprintf(" and releases.anidbid = %d ", $anidbid) : '';
		$airdate = ($airdate > -1) ? sprintf(" and releases.tvairdate >= DATE_SUB(CURDATE(), INTERVAL %d DAY) ", $airdate) : '';

		$sql = sprintf(" SELECT releases.*, rn.id AS nfoid, m.title AS imdbtitle, m.cover, m.imdbid, m.rating, m.plot, m.year, m.genre, m.director, m.actors, g.name AS group_name, concat(cp.title, ' > ', c.title) AS category_name, concat(cp.id, ',', c.id) AS category_ids, coalesce(cp.id,0) AS parentCategoryID, mu.title AS mu_title, mu.url AS mu_url, mu.artist AS mu_artist, mu.publisher AS mu_publisher, mu.releasedate AS mu_releasedate, mu.review AS mu_review, mu.tracks AS mu_tracks, mu.cover AS mu_cover, mug.title AS mu_genre, co.title AS co_title, co.url AS co_url, co.publisher AS co_publisher, co.releasedate AS co_releasedate, co.review AS co_review, co.cover AS co_cover, cog.title AS co_genre,   bo.title AS bo_title, bo.url AS bo_url, bo.publisher AS bo_publisher, bo.author AS bo_author, bo.publishdate AS bo_publishdate, bo.review AS bo_review, bo.cover AS bo_cover  FROM releases LEFT OUTER JOIN category c ON c.id = releases.categoryid LEFT OUTER JOIN category cp ON cp.id = c.parentid LEFT OUTER JOIN groups g ON g.id = releases.groupid LEFT OUTER JOIN releasenfo rn ON rn.releaseid = releases.id AND rn.nfo IS NOT NULL LEFT OUTER JOIN movieinfo m ON m.imdbid = releases.imdbid AND m.title != '' LEFT OUTER JOIN musicinfo mu ON mu.id = releases.musicinfoid LEFT OUTER JOIN genres mug ON mug.id = mu.genreID LEFT OUTER JOIN bookinfo bo ON bo.id = releases.bookinfoid LEFT OUTER JOIN consoleinfo co ON co.id = releases.consoleinfoid LEFT OUTER JOIN genres cog ON cog.id = co.genreID %s WHERE releases.passwordstatus <= (SELECT value FROM settings WHERE setting='showpasswordedrelease') %s %s %s %s ORDER BY postdate DESC %s", $cartsrch, $catsrch, $rage, $anidb, $airdate, $limit);

		return $this->pdo->query($sql, true, NN_CACHE_EXPIRY_MEDIUM);
	}

	/**
	 * Get releases in users 'my tv show' rss feed
	 */
	public function getShowsRss($num, $uid = 0, $excludedcats = array(), $airdate = -1)
	{


		$exccatlist = "";
		if (count($excludedcats) > 0)
			$exccatlist = " and releases.categoryid not in (" . implode(",", $excludedcats) . ")";

		$usershows = $this->pdo->query(sprintf("SELECT rageid, categoryid FROM userseries WHERE userid = %d", $uid), true);
		$usql = '(1=2 ';
		foreach ($usershows as $ushow) {
			$usql .= sprintf('or (releases.rageid = %d', $ushow['rageid']);
			if ($ushow['categoryid'] != '') {
				$catsArr = explode('|', $ushow['categoryid']);
				if (count($catsArr) > 1)
					$usql .= sprintf(' and releases.categoryid in (%s)', implode(',', $catsArr));
				else
					$usql .= sprintf(' and releases.categoryid = %d', $catsArr[0]);
			}
			$usql .= ') ';
		}
		$usql .= ') ';

		$airdate = ($airdate > -1) ? sprintf(" and releases.tvairdate >= DATE_SUB(CURDATE(), INTERVAL %d DAY) ", $airdate) : '';

		$limit = " LIMIT 0," . ($num > 100 ? 100 : $num);

		$sql = sprintf(" SELECT releases.*, tvr.rageid, tvr.releasetitle, epinfo.overview, epinfo.director, epinfo.gueststars, epinfo.writer, epinfo.rating, epinfo.fullep, epinfo.showtitle, epinfo.tvdbid AS ep_tvdbID, g.name AS group_name, concat(cp.title, '-', c.title) AS category_name, concat(cp.id, ',', c.id) AS category_ids, coalesce(cp.id,0) AS parentCategoryID
						FROM releases FORCE INDEX (ix_releases_rageID)
						LEFT OUTER JOIN category c ON c.id = releases.categoryid
						LEFT OUTER JOIN category cp ON cp.id = c.parentid
						LEFT OUTER JOIN groups g ON g.id = releases.groupid
						LEFT OUTER JOIN (SELECT id, releasetitle, rageid FROM tvrage GROUP BY rageid) tvr ON tvr.rageid = releases.rageid
						LEFT OUTER JOIN episodeinfo epinfo ON epinfo.id = releases.episodeinfoid
						INNER JOIN
						(   SELECT id FROM
								( SELECT id, rageid, categoryid, season, episode FROM releases WHERE %s ORDER BY season DESC, episode DESC, postdate ASC ) releases
							GROUP BY rageid, season, episode, categoryid
						) z ON z.id = releases.id
						WHERE %s %s %s
						AND releases.passwordstatus <= (SELECT VALUE FROM settings WHERE setting='showpasswordedrelease')
						ORDER BY postdate DESC %s", $usql, $usql, $exccatlist, $airdate, $limit
		);

		return $this->pdo->query($sql, true, NN_CACHE_EXPIRY_MEDIUM);
	}

	/**
	 * Get releases in users 'my movies' rss feed
	 */
	public function getMyMoviesRss($num, $uid = 0, $excludedcats = array())
	{


		$exccatlist = "";
		if (count($excludedcats) > 0)
			$exccatlist = " and releases.categoryid not in (" . implode(",", $excludedcats) . ")";

		$usermovies = $this->pdo->query(sprintf("SELECT imdbid, categoryid FROM usermovies WHERE userid = %d", $uid), true);
		$usql = '(1=2 ';
		foreach ($usermovies as $umov) {
			$usql .= sprintf('or (releases.imdbid = %d', $umov['imdbid']);
			if ($umov['categoryid'] != '') {
				$catsArr = explode('|', $umov['categoryid']);
				if (count($catsArr) > 1)
					$usql .= sprintf(' and releases.categoryid in (%s)', implode(',', $catsArr));
				else
					$usql .= sprintf(' and releases.categoryid = %d', $catsArr[0]);
			}
			$usql .= ') ';
		}
		$usql .= ') ';

		$limit = " LIMIT 0," . ($num > 100 ? 100 : $num);

		$sql = sprintf(" SELECT releases.*, mi.title AS releasetitle, g.name AS group_name, concat(cp.title, '-', c.title) AS category_name, concat(cp.id, ',', c.id) AS category_ids, coalesce(cp.id,0) AS parentCategoryID
						FROM releases
						LEFT OUTER JOIN category c ON c.id = releases.categoryid
						LEFT OUTER JOIN category cp ON cp.id = c.parentid
						LEFT OUTER JOIN groups g ON g.id = releases.groupid
						LEFT OUTER JOIN movieinfo mi ON mi.imdbid = releases.imdbid
						WHERE %s %s
						AND releases.passwordstatus <= (SELECT VALUE FROM settings WHERE setting='showpasswordedrelease')
						ORDER BY postdate DESC %s", $usql, $exccatlist, $limit
		);

		return $this->pdo->query($sql, true, NN_CACHE_EXPIRY_MEDIUM);
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
			$exccatlist = " and releases.categoryid not in (" . implode(",", $excludedcats) . ")";

		$usql = '(1=2 ';
		foreach ($usershows as $ushow) {
			$usql .= sprintf('or (releases.rageid = %d', $ushow['rageid']);
			if ($ushow['categoryid'] != '') {
				$catsArr = explode('|', $ushow['categoryid']);
				if (count($catsArr) > 1)
					$usql .= sprintf(' and releases.categoryid in (%s)', implode(',', $catsArr));
				else
					$usql .= sprintf(' and releases.categoryid = %d', $catsArr[0]);
			}
			$usql .= ') ';
		}
		$usql .= ') ';

		$maxagesql = "";
		if ($maxage > 0)
			$maxagesql = sprintf(" and releases.postdate > now() - interval %d day ", $maxage);

		$order = $this->getBrowseOrder($orderby);
		$sql = sprintf(" SELECT releases.*, concat(cp.title, '-', c.title) AS category_name, concat(cp.id, ',', c.id) AS category_ids, groups.name AS group_name, pre.ctime, pre.nuketype, rn.id AS nfoid, re.releaseid AS reID FROM releases LEFT OUTER JOIN releasevideo re ON re.releaseid = releases.id LEFT OUTER JOIN groups ON groups.id = releases.groupid LEFT OUTER JOIN releasenfo rn ON rn.releaseid = releases.id AND rn.nfo IS NOT NULL LEFT OUTER JOIN category c ON c.id = releases.categoryid LEFT OUTER JOIN predb pre ON pre.id = releases.preid LEFT OUTER JOIN category cp ON cp.id = c.parentid WHERE %s %s AND releases.passwordstatus <= (SELECT VALUE FROM settings WHERE setting='showpasswordedrelease') %s ORDER BY %s %s" . $limit, $usql, $exccatlist, $maxagesql, $order[0], $order[1]);

		return $this->pdo->query($sql, true, NN_CACHE_EXPIRY_MEDIUM);
	}

	/**
	 * Get count of releases in users 'my tvshows' for pager
	 *
	 * @param       $usershows
	 * @param int   $maxage
	 * @param array $excludedcats
	 *
	 * @return
	 */
	public function getShowsCount($usershows, $maxage = -1, $excludedcats = array())
	{


		$exccatlist = "";
		if (count($excludedcats) > 0)
			$exccatlist = " and releases.categoryid not in (" . implode(",", $excludedcats) . ")";

		$usql = '(1=2 ';
		foreach ($usershows as $ushow) {
			$usql .= sprintf('or (releases.rageid = %d', $ushow['rageid']);
			if ($ushow['categoryid'] != '') {
				$catsArr = explode('|', $ushow['categoryid']);
				if (count($catsArr) > 1)
					$usql .= sprintf(' and releases.categoryid in (%s)', implode(',', $catsArr));
				else
					$usql .= sprintf(' and releases.categoryid = %d', $catsArr[0]);
			}
			$usql .= ') ';
		}
		$usql .= ') ';

		$maxagesql = "";
		if ($maxage > 0)
			$maxagesql = sprintf(" and releases.postdate > now() - interval %d day ", $maxage);

		$res = $this->pdo->queryOneRow(sprintf(" SELECT count(releases.id) AS num FROM releases WHERE %s %s AND releases.passwordstatus <= (SELECT VALUE FROM settings WHERE setting='showpasswordedrelease') %s", $usql, $exccatlist, $maxagesql), true);

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


		$this->pdo->queryExec(sprintf("UPDATE releases SET name=%s, searchname=%s, fromname=%s, categoryid=%d, totalpart=%d, grabs=%d, size=%s, postdate=%s, adddate=%s, rageid=%d, seriesfull=%s, season=%s, episode=%s, imdbid=%d, anidbid=%d, tvdbid=%d,consoleinfoid=%d WHERE id = %d",
				$this->pdo->escapeString($name), $this->pdo->escapeString($searchname), $this->pdo->escapeString($fromname), $category, $parts, $grabs, $this->pdo->escapeString($size), $this->pdo->escapeString($posteddate), $this->pdo->escapeString($addeddate), $rageid, $this->pdo->escapeString($seriesfull), $this->pdo->escapeString($season), $this->pdo->escapeString($episode), $imdbid, $anidbid, $tvdbid, $consoleinfoid, $id
			)
		);
		$this->sphinxSearch->updateReleaseSearchName($id, $searchname);
	}

	/**
	 * Update multiple releases.
	 */
	public function updatemulti($guids, $category, $grabs, $rageid, $season, $imdbid)
	{
		if (!is_array($guids) || sizeof($guids) < 1)
			return false;

		$update = array(
			'categoryid' => (($category == '-1') ? '' : $category),
			'grabs'      => $grabs,
			'rageid'     => $rageid,
			'season'     => $season,
			'imdbid'     => $imdbid
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
	 * @param        $rageId
	 * @param string $series
	 * @param string $episode
	 * @param int    $offset
	 * @param int    $limit
	 * @param string $name
	 * @param array  $cat
	 * @param int    $maxAge
	 *
	 * @return array
	 */
	public function searchbyRageId($rageId, $series = '', $episode = '', $offset = 0, $limit = 100, $name = '', $cat = [-1], $maxAge = -1)
	{
		$whereSql = sprintf(
			"%s
			WHERE r.categoryid BETWEEN 5000 AND 5999
			AND r.nzbstatus = %d
			AND r.passwordstatus %s %s %s %s %s %s %s",
			($name !== '' ? $this->releaseSearch->getFullTextJoinString() : ''),
			Enzebe::NZB_ADDED,
			$this->showPasswords,
			($rageId != -1 ? sprintf(' AND rageid = %d ', $rageId) : ''),
			($series != '' ? sprintf(' AND UPPER(r.season) = UPPER(%s)', $this->pdo->escapeString(((is_numeric($series) && strlen($series) != 4) ? sprintf('S%02d', $series) : $series))) : ''),
			($episode != '' ? sprintf(' AND r.episode %s', $this->pdo->likeString((is_numeric($episode) ? sprintf('E%02d', $episode) : $episode))) : ''),
			($name !== '' ? $this->releaseSearch->getSearchSQL(['searchname' => $name]) : ''),
			$this->categorySQL($cat),
			($maxAge > 0 ? sprintf(' AND r.postdate > NOW() - INTERVAL %d DAY ', $maxAge) : '')
		);

		$baseSql = sprintf(
			"SELECT r.*,
				concat(cp.title, ' > ', c.title) AS category_name,
				CONCAT(cp.id, ',', c.id) AS category_ids,
				groups.name AS group_name,
				rn.id AS nfoid,
				re.releaseid AS reid
			FROM releases r
			INNER JOIN category c ON c.id = r.categoryid
			INNER JOIN groups ON groups.id = r.groupid
			LEFT OUTER JOIN releasevideo re ON re.releaseid = r.id
			LEFT OUTER JOIN releasenfo rn ON rn.releaseid = r.id AND rn.nfo IS NOT NULL
			INNER JOIN category cp ON cp.id = c.parentid
			%s",
			$whereSql
		);

		$sql = sprintf(
			"%s
			ORDER BY postdate DESC
			LIMIT %d OFFSET %d",
			$baseSql,
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
	 * @param int    $aniDbID
	 * @param string $episodeNumber
	 * @param int    $offset
	 * @param int    $limit
	 * @param string $name
	 * @param array  $cat
	 * @param int    $maxAge
	 *
	 * @return array
	 */
	public function searchbyAnidbId($aniDbID, $episodeNumber = '', $offset = 0, $limit = 100, $name = '', $cat = [-1], $maxAge = -1)
	{
		$whereSql = sprintf(
			"%s
			WHERE r.passwordstatus %s AND r.nzbstatus = %d %s %s %s %s %s",
			($name !== '' ? $this->releaseSearch->getFullTextJoinString() : ''),
			$this->showPasswords,
			Enzebe::NZB_ADDED,
			($aniDbID > -1 ? sprintf(' AND anidbid = %d ', $aniDbID) : ''),
			(is_numeric($episodeNumber) ? sprintf(" AND r.episode '%s' ", $this->pdo->likeString($episodeNumber)) : ''),
			($name !== '' ? $this->releaseSearch->getSearchSQL(['searchname' => $name]) : ''),
			$this->categorySQL($cat),
			($maxAge > 0 ? sprintf(' AND r.postdate > NOW() - INTERVAL %d DAY ', $maxAge) : '')
		);

		$baseSql = sprintf(
			"SELECT r.*,
				CONCAT(cp.title, ' > ', c.title) AS category_name,
				CONCAT(cp.id, ',', c.id) AS category_ids,
				groups.name AS group_name,
				rn.id AS nfoid
			FROM releases r
			INNER JOIN category c ON c.id = r.categoryid
			INNER JOIN groups ON groups.id = r.groupid
			LEFT OUTER JOIN releasenfo rn ON rn.releaseid = r.id AND rn.nfo IS NOT NULL
			INNER JOIN category cp ON cp.id = c.parentid
			%s",
			$whereSql
		);

		$sql = sprintf(
			"%s
			ORDER BY postdate DESC
			LIMIT %d OFFSET %d",
			$baseSql,
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
	 * @param int    $imDbId
	 * @param int    $offset
	 * @param int    $limit
	 * @param string $name
	 * @param array  $cat
	 * @param int    $maxAge
	 *
	 * @return array
	 */
	public function searchbyImdbId($imDbId, $offset = 0, $limit = 100, $name = '', $cat = [-1], $maxAge = -1)
	{
		$whereSql = sprintf(
			"%s
			WHERE r.categoryid BETWEEN 2000 AND 2999
			AND r.nzbstatus = %d
			AND r.passwordstatus %s
			%s %s %s %s",
			($name !== '' ? $this->releaseSearch->getFullTextJoinString() : ''),
			Enzebe::NZB_ADDED,
			$this->showPasswords,
			($name !== '' ? $this->releaseSearch->getSearchSQL(['searchname' => $name]) : ''),
			(($imDbId != '-1' && is_numeric($imDbId)) ? sprintf(' AND imdbid = %d ', str_pad($imDbId, 7, '0', STR_PAD_LEFT)) : ''),
			$this->categorySQL($cat),
			($maxAge > 0 ? sprintf(' AND r.postdate > NOW() - INTERVAL %d DAY ', $maxAge) : '')
		);

		$baseSql = sprintf(
			"SELECT r.*,
				concat(cp.title, ' > ', c.title) AS category_name,
				CONCAT(cp.id, ',', c.id) AS category_ids,
				g.name AS group_name,
				rn.id AS nfoid
			FROM releases r
			INNER JOIN groups g ON g.id = r.groupid
			INNER JOIN category c ON c.id = r.categoryid
			LEFT OUTER JOIN releasenfo rn ON rn.releaseid = r.id AND rn.nfo IS NOT NULL
			INNER JOIN category cp ON cp.id = c.parentid
			%s",
			$whereSql
		);

		$sql = sprintf(
			"%s
			ORDER BY postdate DESC
			LIMIT %d OFFSET %d",
			$baseSql,
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
	 * Search for releases by album/artist/musicinfo. Used by API.
	 */
	public function searchAudio($artist, $album, $label, $track, $year, $genre = array(-1), $offset = 0, $limit = 100, $cat = array(-1), $maxage = -1)
	{
		$s = new Settings();
		if ($s->getSetting('sphinxenabled')) {
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
							$chlist .= ", " . $child["id"];

						if ($chlist != "-99")
							$catsrch .= " releases.categoryid in (" . $chlist . ") or ";
					} else {
						$catsrch .= sprintf(" releases.categoryid = %d or ", $category);
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

		$sql = sprintf("SELECT releases.*, musicinfo.cover AS mi_cover, musicinfo.review AS mi_review, musicinfo.tracks AS mi_tracks, musicinfo.publisher AS mi_publisher, musicinfo.title AS mi_title, musicinfo.artist AS mi_artist, genres.title AS music_genrename, concat(cp.title, ' > ', c.title) AS category_name, concat(cp.id, ',', c.id) AS category_ids, groups.name AS group_name, rn.id AS nfoid FROM releases %s LEFT OUTER JOIN musicinfo ON musicinfo.id = releases.musicinfoid LEFT JOIN genres ON genres.id = musicinfo.genreID LEFT OUTER JOIN groups ON groups.id = releases.groupid LEFT OUTER JOIN category c ON c.id = releases.categoryid LEFT OUTER JOIN releasenfo rn ON rn.releaseid = releases.id AND rn.nfo IS NOT NULL LEFT OUTER JOIN category cp ON cp.id = c.parentid WHERE releases.passwordstatus <= (SELECT VALUE FROM settings WHERE setting='showpasswordedrelease') %s %s %s %s ORDER BY postdate DESC LIMIT %d, %d ", $usecatindex, $searchsql, $catsrch, $maxage, $genresql, $offset, $limit);
		$orderpos = strpos($sql, "order by");
		$wherepos = strpos($sql, "where");
		$sqlcount = "SELECT count(releases.id) AS num FROM releases INNER JOIN musicinfo ON musicinfo.id = releases.musicinfoid " . substr($sql, $wherepos, $orderpos - $wherepos);

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
		$s = new Settings();
		if ($s->getSetting('sphinxenabled')) {
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

		$sql = sprintf("SELECT releases.*, bookinfo.cover AS bi_cover, bookinfo.review AS bi_review, bookinfo.publisher AS bi_publisher, bookinfo.pages AS bi_pages, bookinfo.publishdate AS bi_publishdate, bookinfo.title AS bi_title, bookinfo.author AS bi_author, genres.title AS book_genrename, concat(cp.title, ' > ', c.title) AS category_name, concat(cp.id, ',', c.id) AS category_ids, groups.name AS group_name, rn.id AS nfoid FROM releases LEFT OUTER JOIN bookinfo ON bookinfo.id = releases.bookinfoid LEFT JOIN genres ON genres.id = bookinfo.genreID LEFT OUTER JOIN groups ON groups.id = releases.groupid LEFT OUTER JOIN category c ON c.id = releases.categoryid LEFT OUTER JOIN releasenfo rn ON rn.releaseid = releases.id AND rn.nfo IS NOT NULL LEFT OUTER JOIN category cp ON cp.id = c.parentid WHERE releases.passwordstatus <= (SELECT value FROM settings WHERE setting='showpasswordedrelease') %s %s ORDER BY postdate DESC LIMIT %d, %d ", $searchsql, $maxage, $offset, $limit);
		$orderpos = strpos($sql, "order by");
		$wherepos = strpos($sql, "where");
		$sqlcount = "SELECT count(releases.id) AS num FROM releases INNER JOIN bookinfo ON bookinfo.id = releases.bookinfoid " . substr($sql, $wherepos, $orderpos - $wherepos);

		$countres = $this->pdo->queryOneRow($sqlcount, true);
		$res = $this->pdo->query($sql, true);
		if (count($res) > 0)
			$res[0]["_totalrows"] = $countres["num"];

		return $res;
	}

	/**
	 * @param       $currentID
	 * @param       $name
	 * @param int   $limit
	 * @param array $excludedCats
	 *
	 * @return array
	 */
	public function searchSimilar($currentID, $name, $limit = 6, $excludedCats = [])
	{
		// Get the category for the parent of this release.
		$currRow = $this->getById($currentID);
		$catRow = (new \Category(['Settings' => $this->pdo]))->getById($currRow['categoryid']);
		$parentCat = $catRow['parentid'];

		$results = $this->search(
			$this->getSimilarName($name), -1, -1, -1, [$parentCat], -1, -1, 0, 0, -1, -1, 0, $limit, '', -1, $excludedCats
		);
		if (!$results) {
			return $results;
		}

		$ret = [];
		foreach ($results as $res) {
			if ($res['id'] != $currentID && $res['categoryParentID'] == $parentCat) {
				$ret[] = $res;
			}
		}
		return $ret;
	}

	/**
	 * @param string $name
	 *
	 * @return string
	 */
	public function getSimilarName($name)
	{
		return implode(' ', array_slice(str_word_count(str_replace(['.', '_'], ' ', $name), 2), 0, 2));
	}

	/**
	 * Function for searching on the site (by subject, searchname or advanced).
	 *
	 * @param string $searchName
	 * @param string $usenetName
	 * @param string $posterName
	 * @param string $groupName
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
	 * @param integer[] $excludedCats
	 * @param string $type
	 * @param array  $cat
	 *
	 * @return array
	 */
	public function search(
		$searchName,
		$usenetName,
		$posterName,
		$groupName,
		$sizeFrom,
		$sizeTo,
		$hasNfo,
		$hasComments,
		$daysNew,
		$daysOld,
		$offset = 0,
		$limit = 1000,
		$orderBy = '',
		$maxAge = -1,
		$excludedCats = [],
		$type = 'basic',
		$cat = [-1]
	) {
		$sizeRange = [
			1 => 1,
			2 => 2.5,
			3 => 5,
			4 => 10,
			5 => 20,
			6 => 30,
			7 => 40,
			8 => 80,
			9 => 160,
			10 => 320,
			11 => 640,
		];

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
			WHERE r.passwordstatus %s AND r.nzbstatus = %d %s %s %s %s %s %s %s %s %s %s %s",
			$this->releaseSearch->getFullTextJoinString(),
			$this->showPasswords,
			Enzebe::NZB_ADDED,
			($maxAge > 0 ? sprintf(' AND r.postdate > (NOW() - INTERVAL %d DAY) ', $maxAge) : ''),
			($groupName != -1 ? sprintf(' AND r.groupid = %d ', $this->groups->getIDByName($groupName)) : ''),
			(array_key_exists($sizeFrom, $sizeRange) ? ' AND r.size > ' . (string)(104857600 * (int)$sizeRange[$sizeFrom]) . ' ' : ''),
			(array_key_exists($sizeTo, $sizeRange) ? ' AND r.size < ' . (string)(104857600 * (int)$sizeRange[$sizeTo]) . ' ' : ''),
			($hasNfo != 0 ? ' AND r.nfostatus = 1 ' : ''),
			($hasComments != 0 ? ' AND r.comments > 0 ' : ''),
			($type !== 'advanced' ? $this->categorySQL($cat) : ($cat[0] != '-1' ? sprintf(' AND (r.categoryid = %d) ', $cat[0]) : '')),
			($daysNew != -1 ? sprintf(' AND r.postdate < (NOW() - INTERVAL %d DAY) ', $daysNew) : ''),
			($daysOld != -1 ? sprintf(' AND r.postdate > (NOW() - INTERVAL %d DAY) ', $daysOld) : ''),
			(count($excludedCats) > 0 ? ' AND r.categoryid NOT IN (' . implode(',', $excludedCats) . ')' : ''),
			(count($searchOptions) > 0 ? $this->releaseSearch->getSearchSQL($searchOptions) : '')
		);

		$baseSql = sprintf(
			"SELECT r.*,
				CONCAT(cp.title, ' > ', c.title) AS category_name,
				CONCAT(cp.id, ',', c.id) AS category_ids,
				groups.name AS group_name,
				rn.id AS nfoid,
				re.releaseid AS reid,
				cp.id AS categoryparentid
			FROM releases r
			LEFT OUTER JOIN releasevideo re ON re.releaseid = r.id
			LEFT OUTER JOIN releasenfo rn ON rn.releaseid = r.id
			INNER JOIN groups ON groups.id = r.groupid
			INNER JOIN category c ON c.id = r.categoryid
			INNER JOIN category cp ON cp.id = c.parentid
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
				preg_replace('/SELECT.+?FROM\s+releases/is', 'SELECT r.id FROM releases', $query),
				NN_MAX_PAGER_RESULTS
			)
		);
		if (isset($count['count']) && is_numeric($count['count'])) {
			return $count['count'];
		}

		return 0;
	}

	/**
	 * Creates part of a query for searches requiring the categoryid's.
	 *
	 * @param array $categories
	 *
	 * @return string
	 */
	public function categorySQL($categories)
	{
		$sql = '';
		if (count($categories) > 0 && $categories[0] != -1) {
			$Category = new \Category(['Settings' => $this->pdo]);
			$sql = ' AND (';
			foreach ($categories as $category) {
				if ($category != -1) {
					if ($Category->isParent($category)) {
						$children = $Category->getChildren($category);
						$childList = '-99';
						foreach ($children as $child) {
							$childList .= ', ' . $child['id'];
						}

						if ($childList != '-99') {
							$sql .= ' r.categoryid IN (' . $childList . ') OR ';
						}
					} else {
						$sql .= sprintf(' r.categoryid = %d OR ', $category);
					}
				}
			}
			$sql .= '1=2 )';
		}

		return $sql;
	}

	/**
	 * @param int $id
	 *
	 * @return array|bool
	 */
	public function getById($id)
	{
		return $this->pdo->queryOneRow(
			sprintf(
				'SELECT r.*, g.name AS group_name
				FROM releases r
				INNER JOIN groups g ON g.id = r.groupid
				WHERE r.id = %d',
				$id
			)
		);
	}

	/**
	 * Writes a zip file of an array of release guids directly to the stream
	 */
	public function getZipped($guids)
	{
		$s = new Settings();
		$this->nzb = new NZB;
		$zipfile = new zipfile();

		foreach ($guids as $guid) {
			$nzbpath = $this->nzb->getNZBPath($guid, $s->getSetting('nzbpath'));

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
		$sql = sprintf("SELECT releases.*, musicinfo.cover AS mi_cover, musicinfo.review AS mi_review, musicinfo.tracks AS mi_tracks, musicinfo.publisher AS mi_publisher, musicinfo.title AS mi_title, musicinfo.artist AS mi_artist, music_genre.title AS music_genrename,    bookinfo.cover AS bi_cover, bookinfo.review AS bi_review, bookinfo.publisher AS bi_publisher, bookinfo.publishdate AS bi_publishdate, bookinfo.title AS bi_title, bookinfo.author AS bi_author, bookinfo.pages AS bi_pages,  bookinfo.isbn AS bi_isbn, concat(cp.title, ' > ', c.title) AS category_name, concat(cp.id, ',', c.id) AS category_ids, groups.name AS group_name, movieinfo.title AS movietitle, movieinfo.year AS movieyear, (SELECT releasetitle FROM tvrage WHERE rageid = releases.rageid AND rageid > 0 LIMIT 1) AS tvreleasetitle FROM releases LEFT OUTER JOIN groups ON groups.id = releases.groupid LEFT OUTER JOIN category c ON c.id = releases.categoryid LEFT OUTER JOIN category cp ON cp.id = c.parentid LEFT OUTER JOIN musicinfo ON musicinfo.id = releases.musicinfoid LEFT OUTER JOIN bookinfo ON bookinfo.id = releases.bookinfoid LEFT OUTER JOIN movieinfo ON movieinfo.imdbid = releases.imdbid LEFT JOIN genres music_genre ON music_genre.id = musicinfo.genreID WHERE %s", $gsql);

		return (is_array($guid)) ? $this->pdo->query($sql) : $this->pdo->queryOneRow($sql);
	}

	/**
	 * Removes an associated tvrage id from all releases using it.
	 */
	public function removeRageIdFromReleases($rageid)
	{

		$res = $this->pdo->queryOneRow(sprintf("SELECT count(id) AS num FROM releases WHERE rageid = %d", $rageid));
		$ret = $res["num"];
		$this->pdo->queryExec(sprintf("UPDATE releases SET rageid = -1, seriesfull = NULL, season = NULL, episode = NULL WHERE rageid = %d", $rageid));

		return $ret;
	}

	/**
	 * Removes an associated tvdb id from all releases using it.
	 */
	public function removeThetvdbIdFromReleases($tvdbID)
	{

		$res = $this->pdo->queryOneRow(sprintf("SELECT count(id) AS num FROM releases WHERE tvdbid = %d", $tvdbID));
		$ret = $res["num"];
		$res = $this->pdo->queryExec(sprintf("UPDATE releases SET tvdbid = -1 WHERE tvdbid = %d", $tvdbID));

		return $ret;
	}

	public function removeAnidbIdFromReleases($anidbID)
	{

		$res = $this->pdo->queryOneRow(sprintf("SELECT count(id) AS num FROM releases WHERE anidbid = %d", $anidbID));
		$ret = $res["num"];
		$this->pdo->queryExec(sprintf("UPDATE releases SET anidbid = -1, episode = NULL, tvtitle = NULL, tvairdate = NULL WHERE anidbid = %d", $anidbID));

		return $ret;
	}

	public function getReleaseNfo($id, $incnfo = true)
	{

		$selnfo = ($incnfo) ? ', uncompress(nfo) as nfo' : '';

		return $this->pdo->queryOneRow(sprintf("SELECT id, releaseid" . $selnfo . " FROM releasenfo where releaseid = %d AND nfo IS NOT NULL", $id));
	}

	public function updateGrab($guid)
	{

		$this->pdo->queryExec(sprintf("UPDATE releases SET grabs = grabs + 1 WHERE guid = %s", $this->pdo->escapeString($guid)));
	}


	/**
	 * @param int    $categorize
	 * @param int    $postProcess
	 * @param string $groupName (optional)
	 * @param \NNTP   $nntp
	 * @param bool   $echooutput
	 *
	 * @return int
	 */
	public function processReleases($categorize, $postProcess, $groupName, &$nntp, $echooutput)
	{
		$this->echoCLI = ($echooutput && NN_ECHOCLI);
		$page = new Page();
		$groupID = '';
		$s = new Sites();
		echo $s->getLicense();

		if (!empty($groupName)) {
			$groupInfo = $this->groups->getByName($groupName);
			$groupID = $groupInfo['id'];
		}

		$processReleases = microtime(true);
		if ($this->echoCLI) {
			$this->pdo->log->doEcho($this->pdo->log->header("Starting release update process (" . date('Y-m-d H:i:s') . ")"), true);
		}

		if (!file_exists($page->settings->getSetting('nzbpath'))) {
			$this->pdo->log->doEcho($this->pdo->log->primary('Bad or missing nzb directory - ' . $page->settings->getSetting('nzbpath')), true);

			return -1;
		}

		$this->checkRegexesUptoDate($page->settings->getSetting('latestregexurl'), $page->settings->getSetting('latestregexrevision'), $page->settings->getSetting('newznabID'));

		$this->applyRegex($groupID);
		$this->processIncompleteBinaries($groupID);

		$DIR = NN_MISC;
		$PYTHON = shell_exec('which python3 2>/dev/null');
		$PYTHON = (empty($PYTHON) ? 'python -OOu' : 'python3 -OOu');
		$processRequestIDs = (int)$page->settings->getSetting('lookup_reqids');
		$consoleTools = new ConsoleTools(['ColorCLI' => $this->pdo->log]);
		$totalReleasesAdded = 0;
		do {
			$releasesCount = $this->createReleases($groupID);
			$totalReleasesAdded += $releasesCount['added'];

			if ($processRequestIDs === 0) {
				$this->processRequestIDs($groupID, 5000, true);
			} else if ($processRequestIDs === 1) {
				$this->processRequestIDs($groupID, 5000, true);
				$this->processRequestIDs($groupID, 1000, false);
			} else if ($processRequestIDs === 2) {
				$requestIDTime = time();
				if ($this->echoCLI) {
					$this->pdo->log->doEcho($this->pdo->log->header("Process Releases -> Request id Threaded lookup."));
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
		} while (($releasesCount['added'] + $releasesCount['dupes']) >= $this->releaseCreationLimit);

		$this->deletedReleasesByGroup($groupID);
		$this->deleteReleases();

		//
		// User/Request housekeeping, should ideally move this to its own section, but it needs to be done automatically.
		//
		$users = new Users;
		$users->pruneRequestHistory($page->settings->getSetting('userdownloadpurgedays'));

		//Print amount of added releases and time it took.
		if ($this->echoCLI && $this->tablePerGroup === false) {
			$countID = $this->pdo->queryOneRow('SELECT COUNT(id) AS count FROM binaries ' . (!empty($groupID) ? ' WHERE groupid = ' . $groupID : ''));
			$this->pdo->log->doEcho(
				$this->pdo->log->primary(
					'Completed adding ' .
					number_format($totalReleasesAdded) .
					' releases in ' .
					$this->consoleTools->convertTime(number_format(microtime(true) - $processReleases, 2)) .
					'. ' .
					number_format(($countID === false ? 0 : $countID['count'])) .
					' binaries waiting to be converted (still incomplete or in queue for creation)'
				), true
			);
		}

		return $totalReleasesAdded;

	}
	/**
	 * Delete unwanted binaries based on size/file count using admin settings.
	 *
	 * @param int|string $groupID (optional)
	 *
	 * @void
	 * @access public
	 */
	public function deleteBinaries($groupID)
	{
		$group = $this->groups->getCBPTableNames($this->tablePerGroup, $groupID);
		$page = new Page();
		$currTime_ori = $this->pdo->queryOneRow("SELECT NOW() as now");
		//
		// aggregate the releasefiles upto the releases.
		//
		$this->pdo->log->doEcho($this->pdo->log->primary('Aggregating Files'));
		$this->pdo->queryExec("UPDATE releases INNER JOIN (SELECT releaseid, COUNT(id) AS num FROM releasefiles GROUP BY releaseid) b ON b.releaseid = releases.id AND releases.rarinnerfilecount = 0 SET rarinnerfilecount = b.num");

		// Remove the binaries and parts used to form releases, or that are duplicates.
		//
		if ($page->settings->getSetting('partsdeletechunks') > 0) {
			$this->pdo->log->doEcho($this->pdo->log->primary('Chunk deleting unused binaries and parts'));
			$query = sprintf("SELECT p.id AS partsID,b.id AS binariesID FROM %s p
						LEFT JOIN %s b ON b.id = p.binaryid
						WHERE b.dateadded < %s - INTERVAL %d HOUR LIMIT 0,%d",
				$group['pname'],
				$group['bname'],
				$this->pdo->escapeString($currTime_ori["now"]),
				ceil($page->settings->getSetting('rawretentiondays') * 24),
				$page->settings->getSetting('partsdeletechunks')
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
					$fr = $this->pdo->queryExec("DELETE FROM %s WHERE id IN {$pID}", $group['pname']);
					if ($fr > 0) {
						$cc += $fr;
						$cc += $this->pdo->queryExec("DELETE FROM %s WHERE id IN {$bID}", $group['bname']);
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
			$this->pdo->queryDelete(sprintf("DELETE %s, %s FROM %s JOIN %s  ON %s.id = %s.binaryid
			WHERE %s.dateadded < %s - INTERVAL %d HOUR",
					$group['pname'],
					$group['bname'],
					$group['pname'],
					$group['bname'],
					$group['bname'],
					$group['pname'],
					$group['bname'],
					$this->pdo->escapeString($currTime_ori["now"]),
					ceil($page->settings->getSetting('rawretentiondays') * 24)
				)
			);
		}
	}

	/**
	 * @param int $groupID
	 * @void
	 * @access public
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
		$result = $this->pdo->queryDirect(sprintf("SELECT relname, date, SUM(reltotalpart) AS reltotalpart, groupid, reqid, fromname, SUM(num) AS num, coalesce(g.minfilestoformrelease, s.minfilestoformrelease) AS minfilestoformrelease FROM ( SELECT relname, reltotalpart, groupid, reqid, fromname, max(date) AS date, COUNT(id) AS num FROM %s WHERE procstat = %s GROUP BY relname, reltotalpart, groupid, reqid, fromname ORDER BY NULL ) x LEFT OUTER JOIN groups g ON g.id = x.groupid INNER JOIN ( SELECT value AS minfilestoformrelease FROM settings WHERE setting = 'minfilestoformrelease' ) s GROUP BY relname, groupid, reqid, fromname, minfilestoformrelease ORDER BY NULL", $group['bname'], Releases::PROCSTAT_TITLEMATCHED));

		while ($row = $this->pdo->getAssocArray($result)) {
			$retcount++;

			//
			// Less than the site permitted number of files in a release. Dont discard it, as it may
			// be part of a set being uploaded.
			//
			if ($row["num"] < $row["minfilestoformrelease"]) {
				//echo "Number of files in release ".$row["relname"]." less than site/group setting (".$row['num']."/".$row["minfilestoformrelease"].")\n";
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
					$binlist = $this->pdo->query(sprintf('SELECT %s.id, totalparts, date, COUNT(DISTINCT %s.messageid) AS num FROM %s,
					%s WHERE %s.id=%s.binaryid AND %s.relname = %s
					AND %s.procstat = %d AND %s.groupid = %d AND %s.fromname = %s
					GROUP BY %s.id ORDER BY NULL',
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
					$row['groupid'],
					$group['bname'],
					$this->pdo->escapeString($row["fromname"]),
					$group['bname']
						)
					);

					foreach ($binlist as $rowbin) {
						if ($rowbin['num'] < $rowbin['totalparts']) {
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
					if ($row['reqid'] != '' && $page->settings->getSetting('reqidurl') != "") {
						//
						// Try and get the name using the group
						//
						$binGroup = $this->groups->getByNameById($groupID);
						$newtitle = $this->getReleaseNameForReqId($page->settings->getSetting('reqidurl'), $page->settings->getSetting('newznabID'), $binGroup, $row["reqid"]);

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
							$this->pdo->queryExec(sprintf("UPDATE %s SET relname = %s, procstat = %d WHERE %s relname = %s AND procstat = %d AND fromname = %s",
									$group['bname'], $this->pdo->escapeString($newtitle), Releases::PROCSTAT_READYTORELEASE, (!empty($groupID) ? ' groupid = ' . $groupID . ' AND ' : ' '), $this->pdo->escapeString($row["relname"]), Releases::PROCSTAT_TITLEMATCHED, $this->pdo->escapeString($row["fromname"])
								)
							);
						} else {
							//
							// Item not found, if the binary was added to the index yages ago, then give up.
							//
							$maxaddeddate = $this->pdo->queryOneRow(sprintf("SELECT NOW() AS now, MAX(dateadded) AS dateadded FROM %s WHERE relname = %s AND procstat = %d AND groupid = %d AND fromname=%s",
									$group['bname'], $this->pdo->escapeString($row["relname"]), Releases::PROCSTAT_TITLEMATCHED, $groupID, $this->pdo->escapeString($row["fromname"])
								)
							);

							//
							// If added to the index over 48 hours ago, give up trying to determine the title
							//
							if (strtotime($maxaddeddate['now']) - strtotime($maxaddeddate['dateadded']) > (60 * 60 * 48)) {
								$this->pdo->queryExec(sprintf("UPDATE %s SET procstat=%d WHERE relname = %s AND procstat = %d AND groupid = %d AND fromname=%s",
										$group['bname'], Releases::PROCSTAT_NOREQIDNAMELOOKUPFOUND, $this->pdo->escapeString($row["relname"]), Releases::PROCSTAT_TITLEMATCHED, $groupID, $this->pdo->escapeString($row["fromname"])
									)
								);
							}
						}
					} else {
						$this->pdo->queryExec(sprintf("UPDATE %s SET procstat = %d WHERE relname = %s AND procstat = %d AND %s fromname=%s",
								$group['bname'], Releases::PROCSTAT_READYTORELEASE, $this->pdo->escapeString($row["relname"]), Releases::PROCSTAT_TITLEMATCHED,(!empty($groupID) ? ' groupid = ' . $groupID . ' AND ' : ' '), $this->pdo->escapeString($row["fromname"])
							)
						);
					}
				}
			}
			if ($retcount % 100 == 0)
				echo ".";
		}
	}

	/**
	 * Create releases from complete binaries.
	 *
	 * @param int|string $groupID (optional)
	 *
	 * @return int
	 * @access public
	 */
	public function createReleases($groupID)
	{
		$startTime = time();
		$group = $this->groups->getCBPTableNames($this->tablePerGroup, $groupID);
		$page = new Page();
		$this->pdo->log->doEcho($this->pdo->log->primary('Creating releases from complete binaries'));

		$this->pdo->ping(true);
		//
		// Get out all distinct relname, group from binaries
		//
		$categorize = new \Categorize(['Settings' => $this->pdo]);
		$returnCount = $duplicate = 0;
		$result = $this->pdo->queryDirect(sprintf("SELECT %s.*, g.name AS group_name, count(%s.id) AS parts FROM %s INNER JOIN groups g ON g.id = %s.groupid WHERE %s procstat = %d AND relname IS NOT NULL GROUP BY relname, g.name, groupid, fromname ORDER BY COUNT(%s.id) DESC LIMIT %d", $group['bname'], $group['bname'], $group['bname'], $group['bname'], (!empty($groupID) ? ' groupid = ' . $groupID . ' AND ' : ' '), Releases::PROCSTAT_READYTORELEASE, $group['bname'], $this->releaseCreationLimit));
		while ($row = $this->pdo->getAssocArray($result)) {
			$relguid = $this->createGUID();
			// Clean release name
			$releaseCleaning = new ReleaseCleaning();
			$cleanRelName = $this->cleanReleaseName($row['relname']);
			$cleanedName = $releaseCleaning->releaseCleaner(
				$row['relname'], $row['fromname'], $row['group_name']
			);

			if (is_array($cleanedName)) {
				$properName = $cleanedName['properlynamed'];
				$prehashID = (isset($cleanerName['predb']) ? $cleanerName['predb'] : false);
				$isReqID = (isset($cleanerName['requestid']) ? $cleanerName['requestid'] : false);
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
					$prehashID = $preMatch['prehashid'];
					$properName = true;
				}
			}
			$relid = $this->insertRelease(
				[
					'name'           => $this->pdo->escapeString($cleanRelName),
					'searchname'     => $this->pdo->escapeString(utf8_encode($cleanedName)),
					'totalpart'      => $row["parts"],
					'groupid'        => $row["groupid"],
					'guid'           => $this->pdo->escapeString($relguid),
					'categoryid'     => $categorize->determineCategory($groupID, $cleanedName),
					'regexid'        => $row["regexid"],
					'postdate'       => $this->pdo->escapeString($row['date']),
					'fromname'       => $this->pdo->escapeString($row['fromname']),
					'reqid'          => $row["reqid"],
					'passwordstatus' => ($page->settings->getSetting('checkpasswordedrar') > 0 ? -1 : 0),
					'nzbstatus'      => \Enzebe::NZB_NONE,
					'isrenamed'      => ($properName === true ? 1 : 0),
					'reqidstatus'    => ($isReqID === true ? 1 : 0),
					'prehashid'      => ($prehashID === false ? 0 : $prehashID)
				]
			);
			//
			// Tag every binary for this release with its parent release id
			//
			$this->pdo->queryExec(sprintf("UPDATE %s SET procstat = %d, releaseid = %d WHERE relname = %s AND procstat = %d AND %s fromname=%s",
					$group['bname'], Releases::PROCSTAT_RELEASED, $relid, $this->pdo->escapeString($row["relname"]), Releases::PROCSTAT_READYTORELEASE, (!empty($groupID) ? ' groupid = ' . $groupID . ' AND ' : ' '), $this->pdo->escapeString($row["fromname"])
				)
			);
		$cat = new \Categorize(['Settings' => $this->pdo]);

			//
			// Write the nzb to disk
			//
			$catId = $cat->determineCategory($groupID, $cleanRelName);
			$nzbfile = $this->nzb->getNZBPath($relguid, $page->settings->getSetting('nzbpath'), true);
			$this->nzb->writeNZBforreleaseID($relid, $cleanRelName, $catId, $nzbfile, $groupID);

			//
			// Remove used binaries
			//
			$this->pdo->queryDelete(sprintf("DELETE %s, %s FROM %s JOIN %s ON %s.id = %s.binaryid WHERE releaseid = %d ",
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
			$nzbInfo = new NZBInfo;
			if (!$nzbInfo->loadFromFile($nzbfile)) {
				$this->pdo->log->doEcho($this->pdo->log->primary('Failed to write nzb file (bad perms?) ' . $nzbfile . ''));
				$this->delete($relid);
			} else {
				// Check if gid already exists
				$dupes = $this->pdo->queryOneRow(sprintf("SELECT EXISTS(SELECT 1 FROM releases WHERE gid = %s) AS total", $this->pdo->escapeString($nzbInfo->gid)));
				if ($dupes['total'] > 0) {
					$this->pdo->log->doEcho($this->pdo->log->primary('Duplicate - ' . $cleanRelName . ''));
					$this->delete($relid);
					$duplicate++;
				} else {
					$this->pdo->queryExec(sprintf("UPDATE releases SET totalpart = %d, size = %s, COMPLETION = %d, GID=%s , nzb_guid = %s WHERE id = %d",
							$nzbInfo->filecount,
							$nzbInfo->filesize,
							$nzbInfo->completion,
							$this->pdo->escapeString($nzbInfo->gid),
							$this->pdo->escapeString($nzbInfo->gid),
							$relid
						)
					);
					$this->pdo->log->doEcho($this->pdo->log->primary('Added release ' . $cleanRelName . ''));
					$returnCount++;

					if ($this->echoCLI) {
						$this->pdo->log->doEcho($this->pdo->log->primary('Added ' . $returnCount . 'releases.'));
					}

				}
             }
		}
		if ($this->echoCLI) {
			$this->pdo->log->doEcho(
				$this->pdo->log->primary(
					PHP_EOL .
					number_format($returnCount) .
					' Releases added and ' .
					number_format($duplicate) .
					' duplicate releases deleted in ' .
					$this->consoleTools->convertTime(time() - $startTime)
				), true
			);
		}

		return ['added' => $returnCount, 'dupes' => $duplicate];
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

						$site = new Sites();

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
	 * @param int|string $groupID (optional)
	 */
	public function applyRegex($groupID)
	{
		//
		// Get all regexes for all groups which are to be applied to new binaries
		// in order of how they should be applied
		//
		$group = $this->groups->getCBPTableNames($this->tablePerGroup, $groupID);

		$activeGroups = $this->groups->getActive();
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
			$ressql = sprintf('SELECT id, name, date, totalparts, procstat, fromname FROM %s b
 								WHERE groupid = %d AND procstat IN (%d, %d) AND regexid IS NULL ORDER BY b.date ASC',
								$group['bname'],
								$groupArr['id'],
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
					$this->pdo->queryExec(sprintf("UPDATE %s SET relname = replace(%s, '_', ' '), relpart = %d, reltotalpart = %d, procstat=%d, categoryid=%s, regexid=%d, reqid=%s WHERE id = %d",
							$group['bname'], $this->pdo->escapeString($regexMatches['name']), $relparts[0], $relparts[1], Releases::PROCSTAT_TITLEMATCHED, $regexMatches['regcatid'], $regexMatches['regexid'], $this->pdo->escapeString($regexMatches['reqid']), $rowbin["id"]
						)
					);
				} else {
					if ($rowbin['procstat'] == Releases::PROCSTAT_NEW)
						$newUnmatchedBinaries[] = $rowbin['id'];
				}

			}

			//mark as not matched
			if (!empty($newUnmatchedBinaries))
				$this->pdo->queryExec(sprintf("UPDATE %s SET procstat=%d WHERE id IN (%s)", $group['bname'], Releases::PROCSTAT_TITLENOTMATCHED, implode(',', $newUnmatchedBinaries)));

		}
	}

	/**
	 * @param $url
	 * @param $nnid
	 * @param $groupname
	 * @param $reqid
	 *
	 * @return string
	 */
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

	/**
	 * @param $relname
	 *
	 * @return mixed
	 */
	public function cleanReleaseName($relname)
	{
		$cleanArr = array('#', '@', '$', '%', '^', '§', '¨', '©', 'Ö');

		$relname = str_replace($cleanArr, '', $relname);
		$relname = str_replace('_', ' ', $relname);

		return $relname;
	}

	/**
	 * @param array $parameters
	 *
	 * @return bool|int
	 */
	public function insertRelease(array $parameters = [])
	{

		if ($parameters['regexid'] == "")
			$parameters['regexid'] = " null ";

		if ($parameters['reqid'] != "")
			$parameters['reqid'] = $this->pdo->escapeString($parameters['reqid']);
		else
			$parameters['reqid'] = " null ";

		$parameters['id'] = $this->pdo->queryInsert(sprintf("INSERT INTO releases (name, searchname, totalpart, groupid, adddate, guid, categoryid, regexid, rageid, postdate, fromname, size, reqid, passwordstatus, completion, haspreview, nfostatus, nzbstatus,
					isrenamed, iscategorized, reqidstatus, prehashid)
                    VALUES (%s, %s, %d, %d, now(), %s, %d, %s, -1, %s, %s, 0, %s, %d, 100,-1, -1, %d, %d, 1, %d, %d)",
				$parameters['name'],
				$parameters['searchname'],
				$parameters['totalpart'],
				$parameters['groupid'],
				$parameters['guid'],
				$parameters['categoryid'],
				$parameters['regexid'],
				$parameters['postdate'],
				$parameters['fromname'],
				$parameters['reqid'],
				$parameters['passwordstatus'],
				$parameters['nzbstatus'],
				$parameters['isrenamed'],
				$parameters['reqidstatus'],
				$parameters['prehashid']
			)
		);

		$this->sphinxSearch->insertRelease($parameters);

		return $parameters['id'];
	}


	/**
	 * @param      $id
	 * @param bool $isGuid
	 */
	public function delete($id, $isGuid = false)
	{

		$users = new Users();
		$s = new Settings();
		$nfo = new Nfo();
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
				$nzbpath = $s->getSetting('nzbpath') . substr($identifier, 0, 1) . "/" . $identifier . ".nzb.gz";
			elseif ($rel)
				$nzbpath = $s->getSetting('nzbpath') . substr($rel["guid"], 0, 1) . "/" . $rel["guid"] . ".nzb.gz";

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
				$nfo->deleteReleaseNfo($rel['id']);
				$rc->deleteCommentsForRelease($rel['id']);
				$users->delCartForRelease($rel['id']);
				$users->delDownloadRequestsForRelease($rel['id']);
				$rf->delete($rel['id']);
				$re->delete($rel['id']);
				$re->deleteFull($rel['id']);
				$ri->delete($rel['guid']);
				$this->pdo->queryExec(sprintf("DELETE FROM releases WHERE id = %d", $rel['id']));
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
		$this->pdo->queryDelete(
			sprintf('
				DELETE r, rn, rc, uc, rf, ra, rs, rv, re
				FROM releases r
				LEFT OUTER JOIN releasenfo rn ON rn.releaseid = r.id
				LEFT OUTER JOIN releasecomment rc ON rc.releaseid = r.id
				LEFT OUTER JOIN usercart uc ON uc.releaseid = r.id
				LEFT OUTER JOIN releasefiles rf ON rf.releaseid = r.id
				LEFT OUTER JOIN releaseaudio ra ON ra.releaseid = r.id
				LEFT OUTER JOIN releasesubs rs ON rs.releaseid = r.id
				LEFT OUTER JOIN releasevideo rv ON rv.releaseid = r.id
				LEFT OUTER JOIN releaseextrafull re ON re.releaseid = r.id
				WHERE r.guid = %s',
				$this->pdo->escapeString($identifiers['g'])
			)
		);
	}

	/**
	 * @return array
	 */
	public function getTopDownloads()
	{


		return $this->pdo->query("SELECT id, searchname, guid, adddate, grabs FROM releases
							WHERE grabs > 0
							ORDER BY grabs DESC
							LIMIT 10"
		);
	}

	/**
	 * @return array
	 */
	public function getTopComments()
	{


		return $this->pdo->query("SELECT id, guid, searchname, adddate, comments FROM releases
							WHERE comments > 0
							ORDER BY comments DESC
							LIMIT 10"
		);
	}

	/**
	 * @return array
	 */
	public function getRecentlyAdded()
	{


		return $this->pdo->query("SELECT concat(cp.title, ' > ', category.title) AS title, COUNT(*) AS count
                            FROM category
                            LEFT OUTER JOIN category cp ON cp.id = category.parentid
                            INNER JOIN releases ON releases.categoryid = category.id
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
		return $this->pdo->queryDirect(
			"SELECT r.imdbid, r.guid, r.name, r.searchname, r.size, r.completion,
				postdate, categoryid, comments, grabs,
				m.cover
			FROM releases r
			INNER JOIN movieinfo m USING (imdbid)
			WHERE r.categoryid BETWEEN 2000 AND 2999
			AND m.imdbid > 0
			AND m.cover = 1
			AND r.id in (select max(id) from releases where imdbid > 0 group by imdbid)
			ORDER BY r.postdate DESC
			LIMIT 24"
		);
	}

	/**
	 * Get all newest console games with covers for poster wall.
	 *
	 * @return array
	 */
	public function getNewestConsole()
	{
		return $this->pdo->queryDirect(
			"SELECT r.consoleinfoid, r.guid, r.name, r.searchname, r.size, r.completion,
				r.postdate, r.categoryid, r.comments, r.grabs,
				con.cover
			FROM releases r
			INNER JOIN consoleinfo con ON r.consoleinfoid = con.id
			WHERE r.categoryid BETWEEN 1000 AND 1999
			AND con.id > 0
			AND con.cover > 0
			AND r.id in (select max(id) from releases where consoleinfoid > 0 group by consoleinfoid)
			ORDER BY r.postdate DESC
			LIMIT 35"
		);
	}

	/**
	 * Get all newest PC games with covers for poster wall.
	 *
	 * @return array
	 */
	public function getNewestGames()
	{
		return $this->pdo->queryDirect(
			"SELECT r.gamesinfo_id, r.guid, r.name, r.searchname, r.size, r.completion,
				r.postdate, r.categoryid, r.comments, r.grabs,
				gi.cover
			FROM releases r
			INNER JOIN gamesinfo gi ON r.gamesinfo_id = gi.id
			WHERE r.categoryid = 4050
			AND gi.id > 0
			AND gi.cover > 0
			AND r.id in (select max(id) from releases where gamesinfo_id > 0 group by gamesinfo_id)
			ORDER BY r.postdate DESC
			LIMIT 35"
		);
	}

	/**
	 * Get all newest music with covers for poster wall.
	 *
	 * @return array
	 */
	public function getNewestMP3s()
	{
		return $this->pdo->queryDirect(
			"SELECT r.musicinfoid, r.guid, r.name, r.searchname, r.size, r.completion,
				r.postdate, r.categoryid, r.comments, r.grabs,
				m.cover
			FROM releases r
			INNER JOIN musicinfo m ON r.musicinfoid = m.id
			WHERE r.categoryid BETWEEN 3000 AND 3999
			AND r.categoryid != 3030
			AND m.id > 0
			AND m.cover > 0
			AND r.id in (select max(id) from releases where musicinfoid > 0 group by musicinfoid)
			ORDER BY r.postdate DESC
			LIMIT 24"
		);
	}

	/**
	 * Get all newest books with covers for poster wall.
	 *
	 * @return array
	 */
	public function getNewestBooks()
	{
		return $this->pdo->queryDirect(
			"SELECT r.bookinfoid, r.guid, r.name, r.searchname, r.size, r.completion,
				r.postdate, r.categoryid, r.comments, r.grabs,
				b.url,	b.cover, b.title as booktitle, b.author
			FROM releases r
			INNER JOIN bookinfo b ON r.bookinfoid = b.id
			WHERE r.categoryid BETWEEN 7000 AND 7999
			OR r.categoryid = 3030
			AND b.id > 0
			AND b.cover > 0
			AND r.id in (select max(id) from releases where bookinfoid > 0 group by bookinfoid)
			ORDER BY r.postdate DESC
			LIMIT 24"
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
				r.postdate, r.categoryid, r.comments, r.grabs,
				xxx.cover, xxx.title
			FROM releases r
			INNER JOIN xxxinfo xxx ON r.xxxinfo_id = xxx.id
			WHERE r.categoryid BETWEEN 6000 AND 6999
			AND xxx.id > 0
			AND xxx.cover = 1
			AND r.id in (select max(id) from releases where xxxinfo_id > 0 group by xxxinfo_id)
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
			"SELECT r.rageid, r.guid, r.name, r.searchname, r.size, r.completion,
				r.postdate, r.categoryid, r.comments, r.grabs,
				tv.id as tvid, tv.imgdata, tv.releasetitle as tvtitle
			FROM releases r
			INNER JOIN tvrage tv USING (rageid)
			WHERE r.categoryid BETWEEN 5000 AND 5999
			AND tv.rageid > 0
			AND length(tv.imgdata) > 0
			GROUP BY tv.rageid
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
		$s = new Settings();
		$consoleTools = new ConsoleTools(['ColorCLI' => $this->pdo->log]);
		if ($local === false && $s->getSetting('lookup_reqids') == 0) {
			return;
		}

		$startTime = time();
		if ($echoCLI) {
			$this->pdo->log->doEcho(
				$this->pdo->log->header(
					sprintf(
						"Process Releases -> Request id %s lookup -- limit %s",
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
			$groupIDs = [['id' => $groupID]];
		}

		$maxSizeSetting = $this->pdo->getSetting('maxsizetoformrelease');
		$minSizeSetting = $this->pdo->getSetting('minsizetoformrelease');
		$minFilesSetting = $this->pdo->getSetting('minfilestoformrelease');

		foreach ($groupIDs as $groupID) {
			$releases = $this->pdo->queryDirect(
				sprintf("
					SELECT r.guid, r.id
					FROM releases r
					INNER JOIN groups g ON g.id = r.groupid
					WHERE r.groupid = %d
					AND greatest(IFNULL(g.minsizetoformrelease, 0), %d) > 0
					AND r.size < greatest(IFNULL(g.minsizetoformrelease, 0), %d)",
					$groupID['id'],
					$minSizeSetting,
					$minSizeSetting
				)
			);
			if ($releases instanceof \Traversable) {
				foreach ($releases as $release) {
					$this->deleteSingle(['g' => $release['guid'], 'i' => $release['id']], $this->nzb, $this->releaseImage);
					$minSizeDeleted++;
				}
			}

			if ($maxSizeSetting > 0) {
				$releases = $this->pdo->queryDirect(
					sprintf('
						SELECT id, guid
						FROM releases
						WHERE groupid = %d
						AND size > %d',
						$groupID['id'],
						$maxSizeSetting
					)
				);
				if ($releases instanceof \Traversable) {
					foreach ($releases as $release) {
						$this->deleteSingle(['g' => $release['guid'], 'i' => $release['id']], $this->nzb, $this->releaseImage);
						$maxSizeDeleted++;
					}
				}
			}

			$releases = $this->pdo->queryDirect(
				sprintf("
					SELECT r.id, r.guid
					FROM releases r
					INNER JOIN groups g ON g.id = r.groupid
					WHERE r.groupid = %d
					AND greatest(IFNULL(g.minfilestoformrelease, 0), %d) > 0
					AND r.totalpart < greatest(IFNULL(g.minfilestoformrelease, 0), %d)",
					$groupID['id'],
					$minFilesSetting,
					$minFilesSetting
				)
			);
			if ($releases instanceof \Traversable) {
				foreach ($releases as $release) {
					$this->deleteSingle(['g' => $release['guid'], 'i' => $release['id']], $this->nzb, $this->releaseImage);
					$minFilesDeleted++;
				}
			}
		}

		if ($this->echoCLI) {
			$this->pdo->log->doEcho(
				$this->pdo->log->primary(
					'Deleted ' . ($minSizeDeleted + $maxSizeDeleted + $minFilesDeleted) . ' releases: ' . PHP_EOL .
					$minSizeDeleted . ' smaller than, ' .
					$maxSizeDeleted . ' bigger than, ' .
					$minFilesDeleted . ' with less files than site/group settings in: ' .
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
		if ($this->pdo->getSetting('releaseretentiondays') != 0) {
			$releases = $this->pdo->queryDirect(
				sprintf(
					'SELECT id, guid FROM releases WHERE postdate < (NOW() - INTERVAL %d DAY)',
					$this->pdo->getSetting('releaseretentiondays')
				)
			);
			if ($releases instanceof \Traversable) {
				foreach ($releases as $release) {
					$this->deleteSingle(['g' => $release['guid'], 'i' => $release['id']], $this->nzb, $this->releaseImage);
					$retentionDeleted++;
				}
			}
		}

		// Passworded releases.
		if ($this->pdo->getSetting('deletepasswordedrelease') == 1) {
			$releases = $this->pdo->queryDirect(
				sprintf(
					'SELECT id, guid FROM releases WHERE passwordstatus = %d',
					\Releases::PASSWD_RAR
				)
			);
			if ($releases instanceof \Traversable) {
				foreach ($releases as $release) {
					$this->deleteSingle(['g' => $release['guid'], 'i' => $release['id']], $this->nzb, $this->releaseImage);
					$passwordDeleted++;
				}
			}
		}

		// Possibly passworded releases.
		if ($this->pdo->getSetting('deletepossiblerelease') == 1) {
			$releases = $this->pdo->queryDirect(
				sprintf(
					'SELECT id, guid FROM releases WHERE passwordstatus = %d',
					\Releases::PASSWD_POTENTIAL
				)
			);
			if ($releases instanceof \Traversable) {
				foreach ($releases as $release) {
					$this->deleteSingle(['g' => $release['guid'], 'i' => $release['id']], $this->nzb, $this->releaseImage);
					$passwordDeleted++;
				}
			}
		}

		if ($this->crossPostTime != 0) {
			// Crossposted releases.
			do {
				$releases = $this->pdo->queryDirect(
					sprintf(
						'SELECT id, guid FROM releases WHERE adddate > (NOW() - INTERVAL %d HOUR) GROUP BY name HAVING COUNT(name) > 1',
						$this->crossPostTime
					)
				);
				$total = 0;
				if ($releases && $releases->rowCount()) {
					$total = $releases->rowCount();
					foreach ($releases as $release) {
						$this->deleteSingle(['g' => $release['guid'], 'i' => $release['id']], $this->nzb, $this->releaseImage);
						$duplicateDeleted++;
					}
				}
			} while ($total > 0);
		}

		if ($this->completion > 0) {
			$releases = $this->pdo->queryDirect(
				sprintf('SELECT id, guid FROM releases WHERE completion < %d AND completion > 0', $this->completion)
			);
			if ($releases instanceof \Traversable) {
				foreach ($releases as $release) {
					$this->deleteSingle(['g' => $release['guid'], 'i' => $release['id']], $this->nzb, $this->releaseImage);
					$completionDeleted++;
				}
			}
		}

		// Disabled categories.
		$disabledCategories = $category->getDisabledIDs();
		if (count($disabledCategories) > 0) {
			foreach ($disabledCategories as $disabledCategory) {
				$releases = $this->pdo->queryDirect(
					sprintf('SELECT id, guid FROM releases WHERE categoryid = %d', $disabledCategory['id'])
				);
				if ($releases instanceof \Traversable) {
					foreach ($releases as $release) {
						$disabledCategoryDeleted++;
						$this->deleteSingle(['g' => $release['guid'], 'i' => $release['id']], $this->nzb, $this->releaseImage);
					}
				}
			}
		}

		// Delete smaller than category minimum sizes.
		$categories = $this->pdo->queryDirect('
			SELECT c.id AS id,
			CASE WHEN c.minsizetoformrelease = 0 THEN cp.minsizetoformrelease ELSE c.minsizetoformrelease END AS minsize
			FROM category c
			INNER JOIN category cp ON cp.id = c.parentid
			WHERE c.parentid IS NOT NULL'
		);

		if ($categories instanceof \Traversable) {
			foreach ($categories as $category) {
				if ($category['minsize'] > 0) {
					$releases = $this->pdo->queryDirect(
						sprintf('
							SELECT r.id, r.guid
							FROM releases r
							WHERE r.categoryid = %d
							AND r.size < %d',
							$category['id'],
							$category['minsize']
						)
					);
					if ($releases instanceof \Traversable) {
						foreach ($releases as $release) {
							$this->deleteSingle(['g' => $release['guid'], 'i' => $release['id']], $this->nzb, $this->releaseImage);
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
						SELECT id, guid
						FROM releases
						INNER JOIN (SELECT id AS mid FROM musicinfo WHERE musicinfo.genreID = %d) mi
						ON musicinfoid = mid',
						$genre['id']
					)
				);
				if ($releases instanceof \Traversable) {
					foreach ($releases as $release) {
						$disabledGenreDeleted++;
						$this->deleteSingle(['g' => $release['guid'], 'i' => $release['id']], $this->nzb, $this->releaseImage);
					}
				}
			}
		}

		// Misc other.
		if ($this->pdo->getSetting('miscotherretentionhours') > 0) {
			$releases = $this->pdo->queryDirect(
				sprintf('
					SELECT id, guid
					FROM releases
					WHERE categoryid = %d
					AND adddate <= NOW() - INTERVAL %d HOUR',
					\Category::CAT_MISC_OTHER,
					$this->pdo->getSetting('miscotherretentionhours')
				)
			);
			if ($releases instanceof \Traversable) {
				foreach ($releases as $release) {
					$this->deleteSingle(['g' => $release['guid'], 'i' => $release['id']], $this->nzb, $this->releaseImage);
					$miscRetentionDeleted++;
				}
			}
		}

		// Misc hashed.
		if ($this->pdo->getSetting('mischashedretentionhours') > 0) {
			$releases = $this->pdo->queryDirect(
				sprintf('
					SELECT id, guid
					FROM releases
					WHERE categoryid = %d
					AND adddate <= NOW() - INTERVAL %d HOUR',
					\Category::CAT_MISC_HASHED,
					$this->pdo->getSetting('mischashedretentionhours')
				)
			);
			if ($releases instanceof \Traversable) {
				foreach ($releases as $release) {
					$this->deleteSingle(['g' => $release['guid'], 'i' => $release['id']], $this->nzb, $this->releaseImage);
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
		$releases = $this->pdo->queryDirect(sprintf('SELECT id, %s, groupid FROM releases %s', $type, $where));
		if ($releases && $releases->rowCount()) {
			$total = $releases->rowCount();
			foreach ($releases as $release) {
				$catId = $cat->determineCategory($release['groupid'], $release[$type]);
				$this->pdo->queryExec(
					sprintf('UPDATE releases SET categoryid = %d, iscategorized = 1 WHERE id = %d', $catId, $release['id'])
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
			(!empty($groupID) ? 'WHERE iscategorized = 0 AND groupid = ' . $groupID : 'WHERE iscategorized = 0')
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
			sprintf('UPDATE releases SET categoryid = %d, iscategorized = 0 %s', \Category::CAT_MISC_OTHER, $where)
		);
	}

	/**
	 * Create a GUID for a release.
	 * @return string
	 */
	public function createGUID()
	{
		return bin2hex(openssl_random_pseudo_bytes(20));
	}

	/**
	 * Return site setting for hiding/showing passworded releases.
	 *
	 * @param Settings $pdo
	 *
	 * @return string
	 */
	public static function showPasswords(Settings $pdo)
	{
		$setting = $pdo->query(
			"SELECT value FROM settings WHERE setting = 'showpasswordedrelease'",
			true, NN_CACHE_EXPIRY_LONG
		);
		switch ((isset($setting[0]['value']) && is_numeric($setting[0]['value']) ? $setting[0]['value'] : 10)) {
			case 0: // Show releases that may have passwords (does not hide unprocessed releases).
				return ('<= ' . Releases::PASSWD_POTENTIAL);
			case 1: // Show releases that definitely have no passwords (hides unprocessed releases).
				return ('= ' . Releases::PASSWD_NONE);
			case 2: // Show releases that definitely have no passwords (does not hide unprocessed releases).
				return ('<= ' . Releases::PASSWD_NONE);
			case 10: // Shows everything.
			default:
				return ('<= ' . Releases::PASSWD_RAR);
		}
	}

	/**
	 * Retrieve alternate release with same or similar searchname
	 *
	 * @param string $guid
	 * @param string $searchname
	 * @param string $userid
	 * @return string
	 */
	public function getAlternate($guid, $searchname, $userid)
	{
		//status values
		// 0/false 	= successfully downloaded
		// 1/true 	= failed download
		$this->pdo->queryInsert(sprintf("INSERT IGNORE INTO dnzb_failures (userid, guid) VALUES (%d, %s)",
				$userid,
				$this->pdo->escapeString($guid)
				)
		);

		$alternate = $this->pdo->queryOneRow(sprintf('SELECT * FROM releases r
			WHERE r.searchname %s
			AND r.guid NOT IN (SELECT guid FROM failed_downloads WHERE userid = %d)',
			$this->pdo->likeString($searchname),
			$userid
			)
		);
		return $alternate;
	}
}