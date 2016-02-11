<?php
namespace newznab;

use newznab\db\Settings;
use newznab\utility\Utility;
use newznab\processing\PostProcess;

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
	 * @note Initial binary state after being added from usenet
	 * @var
	 */
	const PROCSTAT_NEW = 0;

	/**
	 * @access public
	 * @note After a binary has matched a releaseregex
	 * @var
	 */
	const PROCSTAT_TITLEMATCHED = 5;

	/**
	 * @access public
	 * @note After a binary has been confirmed as having the right number of parts
	 * @var
	 */
	const PROCSTAT_READYTORELEASE = 1;

	/**
	 * @access public
	 * @note Binary has not matched a releaseregex
	 * @var
	 */
	const PROCSTAT_TITLENOTMATCHED = 3;

	/**
	 * @note Binary that has finished and successfully made it into a release
	 * @access public
	 * @var
	 */
	const PROCSTAT_RELEASED = 4;

	/**
	 * @note After a series of attempts to lookup the allfilled style reqid to get a name, its given up
	 * @access public
	 * @var
	 */
	const PROCSTAT_NOREQIDNAMELOOKUPFOUND = 7;

	/**
	 * @var SphinxSearch
	 */
	public $sphinxSearch;

	/**
	 * @var \newznab\db\Settings
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
	 * @var ConsoleTools
	 */
	public $consoleTools;

	/**
	 * @var NZB
	 */
	public $nzb;

	/**
	 * @var ReleaseCleaning
	 */
	public $releaseCleaning;

	/**
	 * @var ReleaseImage
	 */
	public $releaseImage;

	/**
	 * @var string
	 */
	public $showPasswords;

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
		$this->consoleTools = ($options['ConsoleTools'] instanceof ConsoleTools ? $options['ConsoleTools'] : new ConsoleTools(['ColorCLI' => $this->pdo->log]));
		$this->groups = ($options['Groups'] instanceof Groups ? $options['Groups'] : new Groups(['Settings' => $this->pdo]));
		$this->nzb = ($options['NZB'] instanceof NZB ? $options['NZB'] : new NZB());
		$this->releaseCleaning = ($options['ReleaseCleaning'] instanceof ReleaseCleaning ? $options['ReleaseCleaning'] : new ReleaseCleaning($this->pdo));
		$this->releaseImage = ($options['ReleaseImage'] instanceof ReleaseImage ? $options['ReleaseImage'] : new ReleaseImage($this->pdo));
		$this->updategrabs = ($this->pdo->getSetting('grabstatus') == '0' ? false : true);
		$this->passwordStatus = ($this->pdo->getSetting('checkpasswordedrar') == 1 ? -1 : 0);
		$this->sphinxSearch = new SphinxSearch();
		$this->releaseSearch = new ReleaseSearch($this->pdo, $this->sphinxSearch);
		$this->releaseRegex = new ReleaseRegex();

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
	 * Get a count of releases for pager. used in admin manage list
	 */
	public function getCount()
	{

		$res = $this->pdo->queryOneRow("SELECT count(id) AS num FROM releases");

		return $res["num"];
	}

	/**
	 * Get a range of releases. used in admin manage list
	 *
	 * @param $start
	 * @param $num
	 *
	 * @return array
	 */
	public function getRange($start, $num)
	{
		if ($start === false)
			$limit = "";
		else
			$limit = " LIMIT " . $start . "," . $num;

		return $this->pdo->query("SELECT r.*, concat(cp.title, ' > ', c.title) AS category_name
 									  FROM releases r
 									  LEFT OUTER JOIN category c ON c.id = r.categoryid
 									  LEFT OUTER JOIN category cp ON cp.id = c.parentid
 									  ORDER BY postdate DESC" . $limit
		);
	}

	/**
	 * Get a count of previews for pager. used in admin manage list
	 *
	 * @param $previewtype
	 * @param $cat
	 *
	 * @return
	 */
	public function getPreviewCount($previewtype, $cat)
	{
		$catsrch = "";
		if (count($cat) > 0 && $cat[0] != -1) {
			$catsrch = " AND (";
			foreach ($cat as $category) {
				if ($category != -1) {
					$categ = new Categorize();
					if ($categ->isParent($category)) {
						$children = $categ->getChildren($category);
						$chlist = "-99";
						foreach ($children as $child)
							$chlist .= ", " . $child["id"];

						if ($chlist != "-99")
							$catsrch .= " r.categoryid in (" . $chlist . ") or ";
					} else {
						$catsrch .= sprintf(" r.categoryid = %d or ", $category);
					}
				}
			}
			$catsrch .= "1=2 )";
		}
		$res = $this->pdo->queryOneRow(sprintf("SELECT count(id) AS num
												FROM releases r
												WHERE haspreview = %d %s",
												$previewtype,
												$catsrch
												)
											);
		return $res["num"];
	}

	/**
	 * Get a range of releases. used in admin manage list
	 *
	 * @param $previewtype
	 * @param $cat
	 * @param $start
	 * @param $num
	 *
	 * @return array
	 */
	public function getPreviewRange($previewtype, $cat, $start, $num)
	{
		$catsrch = "";
		if (count($cat) > 0 && $cat[0] != -1) {
			$catsrch = " AND (";
			foreach ($cat as $category) {
				if ($category != -1) {
					$categ = new Categorize();
					if ($categ->isParent($category)) {
						$children = $categ->getChildren($category);
						$chlist = "-99";
						foreach ($children as $child)
							$chlist .= ", " . $child["id"];

						if ($chlist != "-99")
							$catsrch .= " r.categoryid in (" . $chlist . ") or ";
					} else {
						$catsrch .= sprintf(" r.categoryid = %d or ", $category);
					}
				}
			}
			$catsrch .= "1=2 )";
		}

		if ($start === false)
			$limit = "";
		else
			$limit = " LIMIT " . $start . "," . $num;

		return $this->pdo->query(sprintf("SELECT releases.*,
						concat(cp.title, ' > ', c.title) AS category_name
						FROM releases r
						LEFT OUTER JOIN category c ON c.id = r.categoryid
						LEFT OUTER JOIN category cp ON cp.id = c.parentid
						WHERE haspreview = %d %s
						ORDER BY postdate DESC %s",
						$previewtype,
						$catsrch,
						$limit
			)
		);
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
				($groupName != '' ? 'LEFT JOIN groups g ON g.id = r.groupid' : ''),
				NZB::NZB_ADDED,
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

		$qry = sprintf(
			"SELECT r.*,
					CONCAT(cp.title, ' > ', c.title) AS category_name,
					CONCAT(cp.id, ',', c.id) AS category_ids,
					(SELECT df.failed) AS failed,
					rn.id AS nfoid,
					re.releaseid AS reid,
					v.tvdb, v.trakt, v.tvrage, v.tvmaze, v.imdb, v.tmdb,
					tve.title, tve.firstaired
				FROM
				(
					SELECT r.*, g.name AS group_name
					FROM releases r
					LEFT JOIN groups g ON g.id = r.groupid
					WHERE r.nzbstatus = %d
					AND r.passwordstatus %s
					%s %s %s %s
					ORDER BY %s %s %s
				) r
				INNER JOIN category c ON c.id = r.categoryid
				INNER JOIN category cp ON cp.id = c.parentid
				LEFT OUTER JOIN videos v ON r.videos_id = v.id
				LEFT OUTER JOIN tv_episodes tve ON r.tv_episodes_id = tve.id
				LEFT OUTER JOIN releasevideo re ON re.releaseid = r.id
				LEFT OUTER JOIN releasenfo rn ON rn.releaseid = r.id
				LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
				GROUP BY r.id
				ORDER BY %7\$s %8\$s",
			NZB::NZB_ADDED,
			$this->showPasswords,
			$this->categorySQL($cat),
			($maxAge > 0 ? (" AND postdate > NOW() - INTERVAL " . $maxAge . ' DAY ') : ''),
			(count($excludedCats) ? (' AND r.categoryid NOT IN (' . implode(',', $excludedCats) . ')') : ''),
			($groupName != '' ? sprintf(' AND g.name = %s ', $this->pdo->escapeString($groupName)) : ''),
			$orderBy[0],
			$orderBy[1],
			($start === false ? '' : ' LIMIT ' . $num . ' OFFSET ' . $start)
		);
		$sql = $this->pdo->query($qry, true, NN_CACHE_EXPIRY_MEDIUM);
		return $sql;
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
			case 0: // Hide releases with a password or a potential password (Hide unprocessed releases).
				return ('= ' . Releases::PASSWD_NONE);
			case 1: // Show releases with no password or a potential password (Show unprocessed releases).
				return ('<= ' . Releases::PASSWD_POTENTIAL);
			case 2: // Hide releases with a password or a potential password (Show unprocessed releases).
				return ('<= ' . Releases::PASSWD_NONE);
			case 10: // Shows everything.
			default:
				return ('<= ' . Releases::PASSWD_RAR);
		}
	}

	/**
	 * Get a column names browse list to be ordered by
	 *
	 * @param $orderby
	 *
	 * @return array
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

		return [$orderfield, $ordersort];
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
				"SELECT searchname, guid, groups.name AS gname,
					CONCAT(cp.title,'_',c.title) AS catName
					FROM releases r
					INNER JOIN category c ON r.categoryid = c.id
					INNER JOIN groups ON r.groupid = groups.id
					INNER JOIN category cp ON cp.id = c.parentid
					WHERE r.nzbstatus = %d
					%s %s %s",
					NZB::NZB_ADDED,
					$this->exportDateString($postFrom),
					$this->exportDateString($postTo, false),
					(($groupID != '' && $groupID != '-1') ? sprintf(' AND groupid = %d ', $groupID) : '')
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
	 *
	 * @param bool $blnIncludeAll
	 *
	 * @return array
	 */
	public function getReleasedGroupsForSelect($blnIncludeAll = true)
	{

		$groups = $this->pdo->query("SELECT DISTINCT g.id, g.name
										FROM releases r
										INNER JOIN groups g ON g.id = r.groupid"
		);
		$temp_array = [];

		if ($blnIncludeAll)
			$temp_array[-1] = "--All Groups--";

		foreach ($groups as $group)
			$temp_array[$group["id"]] = $group["name"];

		return $temp_array;
	}

	/**
	 * Cache of concatenated category ID's used in queries.
	 * @var null|array
	 */
	private $concatenatedCategoryIDsCache = null;

	/**
	 * Gets / sets a string of concatenated category ID's used in queries.
	 *
	 * @return array|null
	 */
	public function getConcatenatedCategoryIDs()
	{
		if (is_null($this->concatenatedCategoryIDsCache)) {
			$result = $this->pdo->query(
				"SELECT CONCAT(cp.id, ',', c.id) AS category_ids
					FROM category c
					INNER JOIN category cp ON cp.id = c.parentid",
				true, NN_CACHE_EXPIRY_LONG
			);
			if (isset($result[0]['category_ids'])) {
				$this->concatenatedCategoryIDsCache = $result[0]['category_ids'];
			}
		}
		return $this->concatenatedCategoryIDsCache;
	}

	/**
	 * Get TV for my shows page.
	 *
	 * @param          $userShows
	 * @param int|bool $offset
	 * @param int      $limit
	 * @param string   $orderBy
	 * @param int      $maxAge
	 * @param array    $excludedCats
	 *
	 * @return array
	 */
	public function getShowsRange($userShows, $offset, $limit, $orderBy, $maxAge = -1, $excludedCats = [])
	{
		$orderBy = $this->getBrowseOrder($orderBy);
		return $this->pdo->query(
			sprintf(
				"SELECT r.*,
					CONCAT(cp.title, '-', c.title) AS category_name,
					%s AS category_ids,
					groups.name AS group_name,
					rn.id AS nfoid, re.releaseid AS reid,
					tve.firstaired,
					(SELECT df.failed) AS failed
				FROM releases r
				LEFT OUTER JOIN releasevideo re ON re.releaseid = r.id
				INNER JOIN groups ON groups.id = r.groupid
				LEFT OUTER JOIN releasenfo rn ON rn.releaseid = r.id
				LEFT OUTER JOIN tv_episodes tve ON tve.videos_id = r.videos_id
				INNER JOIN category c ON c.id = r.categoryid
				INNER JOIN category cp ON cp.id = c.parentid
				LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
				WHERE %s %s
				AND r.nzbstatus = %d
				AND r.categoryid BETWEEN " . Category::TV_ROOT . " AND " . Category::TV_OTHER . "
				AND r.passwordstatus %s
				%s
				GROUP BY r.id
				ORDER BY %s %s %s",
				$this->getConcatenatedCategoryIDs(),
				$this->uSQL($userShows, 'videos_id'),
				(count($excludedCats) ? ' AND r.categoryid NOT IN (' . implode(',', $excludedCats) . ')' : ''),
				NZB::NZB_ADDED,
				$this->showPasswords,
				($maxAge > 0 ? sprintf(' AND r.postdate > NOW() - INTERVAL %d DAY ', $maxAge) : ''),
				$orderBy[0],
				$orderBy[1],
				($offset === false ? '' : (' LIMIT ' . $limit . ' OFFSET ' . $offset))
			), true, NN_CACHE_EXPIRY_MEDIUM
		);
	}

	/**
	 * Get count for my shows page pagination.
	 *
	 * @param       $userShows
	 * @param int   $maxAge
	 * @param array $excludedCats
	 *
	 * @return int
	 */
	public function getShowsCount($userShows, $maxAge = -1, $excludedCats = [])
	{
		return $this->getPagerCount(
			sprintf(
				'SELECT r.id
				FROM releases r
				WHERE %s %s
				AND r.nzbstatus = %d
				AND r.categoryid BETWEEN " . Category::TV_ROOT . " AND " . Category::TV_OTHER . "
				AND r.passwordstatus %s
				%s',
				$this->uSQL($userShows, 'videos_id'),
				(count($excludedCats) ? ' AND r.categoryid NOT IN (' . implode(',', $excludedCats) . ')' : ''),
				NZB::NZB_ADDED,
				$this->showPasswords,
				($maxAge > 0 ? sprintf(' AND r.postdate > NOW() - INTERVAL %d DAY ', $maxAge) : '')
			)
		);
	}

	/**
	 * Delete a preview associated with a release and update the release to indicate it doesnt have one.
	 *
	 * @param $guid
	 */
	public function deletePreview($guid)
	{
		$this->updateHasPreview($guid, 0);
		$ri = new ReleaseImage();
		$ri->delete($guid);
	}

	/**
	 * Update whether a release has a preview.
	 *
	 * @param $guid
	 * @param $haspreview
	 */
	public function updateHasPreview($guid, $haspreview)
	{
		$this->pdo->queryExec(
			sprintf(
				"UPDATE releases
					SET haspreview = %d
					WHERE guid = %s",
				$haspreview,
				$this->pdo->escapeString($guid)));
	}

	/**
	 * Used for release edit page on site.
	 *
	 * @param int    $ID
	 * @param string $name
	 * @param string $searchName
	 * @param string $fromName
	 * @param int    $categoryID
	 * @param int    $parts
	 * @param int    $grabs
	 * @param int    $size
	 * @param string $postedDate
	 * @param string $addedDate
	 * @param        $videoId
	 * @param        $episodeId
	 * @param int    $imDbID
	 * @param int    $aniDbID
	 *
	 */
	public function update($ID, $name, $searchName, $fromName, $categoryID, $parts, $grabs, $size,
						   $postedDate, $addedDate, $videoId, $episodeId, $imDbID, $aniDbID)
	{
		$this->pdo->queryExec(
			sprintf(
				'UPDATE releases
				SET name = %s, searchname = %s, fromname = %s, categoryid = %d,
					totalpart = %d, grabs = %d, size = %s, postdate = %s, adddate = %s, videos_id = %d,
					tv_episodes_id = %s, imdbid = %d, anidbid = %d
				WHERE id = %d',
				$this->pdo->escapeString($name),
				$this->pdo->escapeString($searchName),
				$this->pdo->escapeString($fromName),
				$categoryID,
				$parts,
				$grabs,
				$this->pdo->escapeString($size),
				$this->pdo->escapeString($postedDate),
				$this->pdo->escapeString($addedDate),
				$videoId,
				$episodeId,
				$imDbID,
				$aniDbID,
				$ID
			)
		);
		$this->sphinxSearch->updateRelease($ID, $this->pdo);
	}

	/**
	 * @param $guids
	 * @param $category
	 * @param $grabs
	 * @param $videoId
	 * @param $episodeId
	 * @param $anidbId
	 * @param $imdbId
	 *
	 * @return array|bool|int
	 */
	public function updateMulti($guids, $category, $grabs, $videoId, $episodeId, $anidbId, $imdbId)
	{
		if (!is_array($guids) || count($guids) < 1) {
			return false;
		}

		$update = [
			'categoryid'     => (($category == '-1') ? 'categoryid' : $category),
			'grabs'          => $grabs,
			'videos_id'      => $videoId,
			'tv_episodes_id' => $episodeId,
			'anidbid'        => $anidbId,
			'imdbid'         => $imdbId
		];

		$updateSql = [];
		foreach ($update as $key => $value) {
			if ($value != '') {
				$updateSql[] = sprintf($key . '=%s', $this->pdo->escapeString($value));
			}
		}

		if (count($updateSql) < 1) {
			return -1;
		}

		$updateGuids = [];
		foreach ($guids as $guid) {
			$updateGuids[] = $this->pdo->escapeString($guid);
		}

		return $this->pdo->queryExec(
			sprintf(
				'UPDATE releases SET %s WHERE guid IN (%s)',
				implode(', ', $updateSql),
				implode(', ', $updateGuids)
			)
		);
	}

	/**
	 * Creates part of a query for some functions.
	 *
	 * @param array  $userQuery
	 * @param string $type
	 *
	 * @return string
	 */
	public function uSQL($userQuery, $type)
	{
		$sql = '(1=2 ';
		foreach ($userQuery as $query) {
			$sql .= sprintf('OR (r.%s = %d', $type, $query[$type]);
			if ($query['categoryid'] != '') {
				$catsArr = explode('|', $query['categoryid']);
				if (count($catsArr) > 1) {
					$sql .= sprintf(' AND r.categoryid IN (%s)', implode(',', $catsArr));
				} else {
					$sql .= sprintf(' AND r.categoryid = %d', $catsArr[0]);
				}
			}
			$sql .= ') ';
		}
		$sql .= ') ';

		return $sql;
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
			WHERE r.categoryid BETWEEN " . Category::TV_ROOT . " AND " . Category::TV_OTHER . "
			AND r.nzbstatus = %d
			AND r.passwordstatus %s %s %s %s %s %s %s",
			($name !== '' ? $this->releaseSearch->getFullTextJoinString() : ''),
			NZB::NZB_ADDED,
			$this->showPasswords,
			($rageId != -1 ? sprintf(' AND tvinfoid = %d ', $rageId) : ''),
			($series != '' ? sprintf(' AND UPPER(r.season) = UPPER(%s)', $this->pdo->escapeString(((is_numeric($series) && strlen($series) != 4) ? sprintf('S%02d', $series) : $series))) : ''),
			($episode != '' ? sprintf(' AND r.episode %s', $this->pdo->likeString((is_numeric($episode) ? sprintf('E%02d', $episode) : $episode))) : ''),
			($name !== '' ? $this->releaseSearch->getSearchSQL(['searchname' => $name]) : ''),
			$this->categorySQL($cat),
			($maxAge > 0 ? sprintf(' AND r.postdate > NOW() - INTERVAL %d DAY ', $maxAge) : '')
		);

		$baseSql = sprintf(
			"SELECT r.*,
				concat(cp.title, ' > ', c.title) AS category_name,
				%s AS category_ids,
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
			$this->getConcatenatedCategoryIDs(),
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

		return $releases;
	}

	/**
	 * Search for releases by album/artist/musicinfo. Used by API.
	 *
	 * @param       $artist
	 * @param       $album
	 * @param       $label
	 * @param       $track
	 * @param       $year
	 * @param array $genre
	 * @param int   $offset
	 * @param int   $limit
	 * @param array $cat
	 * @param int   $maxage
	 *
	 * @return array
	 */
	public function searchAudio($artist, $album, $label, $track, $year, $genre = [-1], $offset = 0, $limit = 100, $cat = [-1], $maxage = -1)
	{
		$s = new Settings();
		if ($s->getSetting('sphinxenabled')) {
			$sphinx = new Sphinx();
			$results = $sphinx->searchAudio($artist, $album, $label, $track, $year, $genre, $offset, $limit, $cat, $maxage, [], true);
			if (is_array($results))
				return $results;
		}


		$searchsql = "";

		if ($artist != "")
			$searchsql .= sprintf(" AND m.artist like %s ", $this->pdo->escapeString("%" . $artist . "%"));
		if ($album != "")
			$searchsql .= sprintf(" AND m.title like %s ", $this->pdo->escapeString("%" . $album . "%"));
		if ($label != "")
			$searchsql .= sprintf(" AND mu.publisher like %s ", $this->pdo->escapeString("%" . $label . "%"));
		if ($track != "")
			$searchsql .= sprintf(" AND m.tracks like %s ", $this->pdo->escapeString("%" . $track . "%"));
		if ($year != "")
			$searchsql .= sprintf(" AND m.year = %d ", $year);


		$catsrch = "";
		$usecatindex = "";
		if (count($cat) > 0 && $cat[0] != -1) {
			$catsrch = " AND (";
			foreach ($cat as $category) {
				if ($category != -1) {
					$categ = new Categorize();
					if ($categ->isParent($category)) {
						$children = $categ->getChildren($category);
						$chlist = "-99";
						foreach ($children as $child)
							$chlist .= ", " . $child["id"];

						if ($chlist != "-99")
							$catsrch .= " r.categoryid in (" . $chlist . ") or ";
					} else {
						$catsrch .= sprintf("r.categoryid = %d or ", $category);
					}
				}
			}
			$catsrch .= "1=2 )";
			$usecatindex = " use index (ix_releases_categoryID) ";
		}

		if ($maxage > 0)
			$maxage = sprintf(" AND postdate > now() - interval %d day ", $maxage);
		else
			$maxage = "";

		$genresql = "";
		if (count($genre) > 0 && $genre[0] != -1) {
			$genresql = " AND (";
			foreach ($genre as $g) {
				$genresql .= sprintf(" m.genreid = %d or ", $g);
			}
			$genresql .= "1=2 )";
		}

		$res = $this->pdo->query(sprintf(
			"SELECT r.*,
			m.cover AS mi_cover,
			m.review AS mi_review,
			m.tracks AS mi_tracks,
			m.publisher AS mi_publisher,
			m.title AS mi_title,
			m.artist AS mi_artist,
			g.title AS music_genrename,
			CONCAT(cp.title, ' > ', c.title) AS category_name,
			%s AS category_ids,
			g.name AS group_name,
			rn.id AS nfoid FROM releases r %s
			LEFT OUTER JOIN musicinfo m ON m.id = r.musicinfoid
			LEFT JOIN genres g ON g.id = m.genreid
			LEFT OUTER JOIN groups g ON g.id = r.groupid
			LEFT OUTER JOIN category c ON c.id = r.categoryid
			LEFT OUTER JOIN releasenfo rn ON rn.releaseid = r.id
			AND rn.nfo IS NOT NULL
			LEFT OUTER JOIN category cp ON cp.id = c.parentid
			WHERE r.passwordstatus <= (SELECT VALUE FROM settings WHERE setting='showpasswordedrelease')
			%s %s %s %s
			ORDER BY postdate DESC LIMIT %d, %d ",
			$this->getConcatenatedCategoryIDs(),
			$usecatindex,
			$searchsql,
			$catsrch,
			$maxage,
			$genresql,
			$offset,
			$limit
			)
			, true
		);
		return $res;
	}

	/**
	 * Search for releases by author/bookinfo. Used by API.
	 *
	 * @param     $author
	 * @param     $title
	 * @param int $offset
	 * @param int $limit
	 * @param int $maxage
	 *
	 * @return array
	 */
	public function searchBook($author, $title, $offset = 0, $limit = 100, $maxage = -1)
	{
		$s = new Settings();
		if ($s->getSetting('sphinxenabled')) {
			$sphinx = new Sphinx();
			$results = $sphinx->searchBook($author, $title, $offset, $limit, $maxage, [], true);
			if (is_array($results))
				return $results;
		}


		$searchsql = "";

		if ($author != "")
			$searchsql .= sprintf(" AND b.author like %s ", $this->pdo->escapeString("%" . $author . "%"));
		if ($title != "")
			$searchsql .= sprintf(" AND b.title like %s ", $this->pdo->escapeString("%" . $title . "%"));

		if ($maxage > 0)
			$maxage = sprintf(" AND postdate > now() - interval %d day ", $maxage);
		else
			$maxage = "";

		$res = $this->pdo->query(sprintf(
			"SELECT r.*, b.cover AS bi_cover,
				b.review AS bi_review,
				b.publisher AS bi_publisher,
				b.pages AS bi_pages,
				b.publishdate AS bi_publishdate,
				b.title AS bi_title,
				b.author AS bi_author,
				gn.title AS book_genrename,
				CONCAT(cp.title, ' > ', c.title) AS category_name,
				%s AS category_ids,
				g.name AS group_name,
				rn.id AS nfoid
				FROM releases r
				LEFT OUTER JOIN bookinfo b ON b.id = r.bookinfoid
				LEFT JOIN genres gn ON g.id = b.genreid
				LEFT OUTER JOIN groups g ON g.id = r.groupid
				LEFT OUTER JOIN category c ON c.id = r.categoryid
				LEFT OUTER JOIN releasenfo rn ON rn.releaseid = r.id
				AND rn.nfo IS NOT NULL
				LEFT OUTER JOIN category cp ON cp.id = c.parentid
				WHERE releases.passwordstatus <= (SELECT value FROM settings WHERE setting='showpasswordedrelease')
				%s %s
				ORDER BY postdate DESC LIMIT %d, %d ",
			$this->getConcatenatedCategoryIDs(),
			$searchsql,
			$maxage,
			$offset,
			$limit
			), true
		);

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
		$catRow = (new Category(['Settings' => $this->pdo]))->getById($currRow['categoryid']);
		$parentCat = $catRow['parentid'];

		$results = $this->search(
			$this->getSimilarName($name), -1, -1, -1, -1, -1, -1, 0, 0, -1, -1, 0, $limit, '', -1, $excludedCats, null, [$parentCat]);
		if (!$results) {
			return $results;
		}

		$ret = [];
		foreach ($results as $res) {
			if ($res['id'] != $currentID && $res['categoryparentid'] == $parentCat) {
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
	 * @param string $fileName
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
		$fileName,
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
		if ($fileName != -1) {
			$searchOptions['filename'] = $fileName;
		}

		$whereSql = sprintf(
			"%s
			WHERE r.passwordstatus %s AND r.nzbstatus = %d %s %s %s %s %s %s %s %s %s %s %s",
			$this->releaseSearch->getFullTextJoinString(),
			$this->showPasswords,
			NZB::NZB_ADDED,
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
				%s AS category_ids,
				(SELECT df.failed) AS failed,
				groups.name AS group_name,
				rn.id AS nfoid,
				re.releaseid AS reid,
				cp.id AS categoryparentid,
				v.tvdb, v.trakt, v.tvrage, v.tvmaze, v.imdb, v.tmdb,
				tve.firstaired
			FROM releases r
			LEFT OUTER JOIN releasevideo re ON re.releaseid = r.id
			LEFT OUTER JOIN videos v ON r.videos_id = v.id
			LEFT OUTER JOIN tv_episodes tve ON r.tv_episodes_id = tve.id
			LEFT OUTER JOIN releasenfo rn ON rn.releaseid = r.id
			INNER JOIN groups ON groups.id = r.groupid
			INNER JOIN category c ON c.id = r.categoryid
			INNER JOIN category cp ON cp.id = c.parentid
			LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
			%s",
			$this->getConcatenatedCategoryIDs(),
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
		$releases = $this->pdo->query($sql, true, NN_CACHE_EXPIRY_MEDIUM);
		if ($releases && count($releases)) {
			$releases[0]['_totalrows'] = $this->getPagerCount($baseSql);
		}
		return $releases;
	}

	/**
	 * @param array  $siteIdArr
	 * @param string $series
	 * @param string $episode
	 * @param string $airdate
	 * @param int    $offset
	 * @param int    $limit
	 * @param string $name
	 * @param array  $cat
	 * @param int    $maxAge
	 *
	 * @return array
	 */
	public function searchShows($siteIdArr = array(), $series = '', $episode = '', $airdate = '', $offset = 0,
								$limit = 100, $name = '', $cat = [-1], $maxAge = -1)
	{
		$siteSQL = array();

		if (is_array($siteIdArr)) {
			foreach ($siteIdArr as $column => $Id) {
				if ($Id > 0) {
					$siteSQL[] = sprintf('v.%s = %d', $column, $Id);
				}
			}
		}

		$siteCount = count($siteSQL);

		$whereSql = sprintf(
			"%s
			WHERE r.categoryid BETWEEN " . Category::TV_ROOT . " AND " . Category::TV_OTHER . "
			AND r.nzbstatus = %d
			AND r.passwordstatus %s
			AND (%s)
			%s %s %s %s %s %s",
			($name !== '' ? $this->releaseSearch->getFullTextJoinString() : ''),
			NZB::NZB_ADDED,
			$this->showPasswords,
			($siteCount > 0 ? implode(' OR ', $siteSQL) : '1=1'),
			($series != '' ? sprintf('AND tve.series = %d', (int)preg_replace('/^s0*/i', '', $series)): ''),
			($episode != '' ? sprintf('AND tve.episode = %d', (int)preg_replace('/^e0*/i', '', $episode)): ''),
			($airdate != '' ? sprintf('AND DATE(tve.firstaired) = %s', $this->pdo->escapeString($airdate)) : ''),
			($name !== '' ? $this->releaseSearch->getSearchSQL(['searchname' => $name]) : ''),
			$this->categorySQL($cat),
			($maxAge > 0 ? sprintf('AND r.postdate > NOW() - INTERVAL %d DAY', $maxAge) : '')
		);

		$baseSql = sprintf(
			"SELECT r.*,
				v.title, v.countries_id, v.started, v.tvdb, v.trakt,
					v.imdb, v.tmdb, v.tvmaze, v.tvrage, v.source,
				tvi.summary, tvi.publisher, tvi.image,
				tve.series, tve.episode, tve.se_complete, tve.title, tve.firstaired, tve.summary,
				CONCAT(cp.title, ' > ', c.title) AS category_name,
				%s AS category_ids,
				groups.name AS group_name,
				rn.id AS nfoid,
				re.releaseid AS reid
			FROM releases r
			INNER JOIN videos v ON r.videos_id = v.id AND v.type = 0
			INNER JOIN tv_info tvi ON v.id = tvi.videos_id
			INNER JOIN tv_episodes tve ON r.tv_episodes_id = tve.id
			INNER JOIN category c ON c.id = r.categoryid
			INNER JOIN groups ON groups.id = r.groupid
			LEFT OUTER JOIN releasevideo re ON re.releaseid = r.id
			LEFT OUTER JOIN releasenfo rn ON rn.releaseid = r.id
			INNER JOIN category cp ON cp.id = c.parentid
			%s",
			$this->getConcatenatedCategoryIDs(),
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

		$releases = $this->pdo->query($sql, true, NN_CACHE_EXPIRY_MEDIUM);

		return $releases;
	}

	/**
	 * @param        $aniDbID
	 * @param int    $offset
	 * @param int    $limit
	 * @param string $name
	 * @param array  $cat
	 * @param int    $maxAge
	 *
	 * @return array
	 */
	public function searchbyAnidbId($aniDbID, $offset = 0, $limit = 100, $name = '', $cat = [-1], $maxAge = -1)
	{
		$whereSql = sprintf(
			"%s
			WHERE r.passwordstatus %s
			AND r.nzbstatus = %d
			%s %s %s %s",
			($name !== '' ? $this->releaseSearch->getFullTextJoinString() : ''),
			$this->showPasswords,
			NZB::NZB_ADDED,
			($aniDbID > -1 ? sprintf(' AND anidbid = %d ', $aniDbID) : ''),
			($name !== '' ? $this->releaseSearch->getSearchSQL(['searchname' => $name]) : ''),
			$this->categorySQL($cat),
			($maxAge > 0 ? sprintf(' AND r.postdate > NOW() - INTERVAL %d DAY ', $maxAge) : '')
		);

		$baseSql = sprintf(
			"SELECT r.*,
				CONCAT(cp.title, ' > ', c.title) AS category_name,
				%s AS category_ids,
				groups.name AS group_name,
				rn.id AS nfoid,
				re.releaseid AS reid
			FROM releases r
			INNER JOIN category c ON c.id = r.categoryid
			INNER JOIN groups ON groups.id = r.groupid
			LEFT OUTER JOIN releasenfo rn ON rn.releaseid = r.id
			LEFT OUTER JOIN releaseextrafull re ON re.releaseid = r.id
			INNER JOIN category cp ON cp.id = c.parentid
			%s",
			$this->getConcatenatedCategoryIDs(),
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
		$releases = $this->pdo->query($sql, true, NN_CACHE_EXPIRY_MEDIUM);

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
			WHERE r.categoryid BETWEEN " . Category::MOVIE_ROOT . " AND " . Category::MOVIE_OTHER . "
			AND r.nzbstatus = %d
			AND r.passwordstatus %s
			%s %s %s %s",
			($name !== '' ? $this->releaseSearch->getFullTextJoinString() : ''),
			NZB::NZB_ADDED,
			$this->showPasswords,
			($name !== '' ? $this->releaseSearch->getSearchSQL(['searchname' => $name]) : ''),
			(($imDbId != '-1' && is_numeric($imDbId)) ? sprintf(' AND imdbid = %d ', str_pad($imDbId, 7, '0', STR_PAD_LEFT)) : ''),
			$this->categorySQL($cat),
			($maxAge > 0 ? sprintf(' AND r.postdate > NOW() - INTERVAL %d DAY ', $maxAge) : '')
		);

		$baseSql = sprintf(
			"SELECT r.*,
				CONCAT(cp.title, ' > ', c.title) AS category_name,
				%s AS category_ids,
				g.name AS group_name,
				rn.id AS nfoid
			FROM releases r
			INNER JOIN groups g ON g.id = r.groupid
			INNER JOIN category c ON c.id = r.categoryid
			LEFT OUTER JOIN releasenfo rn ON rn.releaseid = r.id
			INNER JOIN category cp ON cp.id = c.parentid
			%s",
			$this->getConcatenatedCategoryIDs(),
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
		$releases = $this->pdo->query($sql, true, NN_CACHE_EXPIRY_MEDIUM);

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
		$count = $this->pdo->query(
				sprintf(
						'SELECT COUNT(z.id) AS count FROM (%s LIMIT %s) z',
						preg_replace('/SELECT.+?FROM\s+releases/is', 'SELECT r.id FROM releases', $query),
						NN_MAX_PAGER_RESULTS
				)
		);
		return (isset($count[0]['count']) ? $count[0]['count'] : 0);
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
		if (is_array($categories) && $categories[0] != -1) {
			$Category = new Category(['Settings' => $this->pdo]);
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
	 *
	 * @param $guids
	 *
	 * @return string
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
	 * @param string $guid
	 *
	 * @return array|bool
	 */
	public function getByGuid($guid)
	{
		if (is_array($guid)) {
			$tempGuids = [];
			foreach ($guid as $identifier) {
				$tempGuids[] = $this->pdo->escapeString($identifier);
			}
			$gSql = sprintf('r.guid IN (%s)', implode(',', $tempGuids));
		} else {
			$gSql = sprintf('r.guid = %s', $this->pdo->escapeString($guid));
		}
		$sql = sprintf(
			"SELECT r.*,
				CONCAT(cp.title, ' > ', c.title) AS category_name,
				%s AS category_ids,
				g.name AS group_name,
				v.title AS showtitle, v.tvdb, v.trakt, v.tvrage, v.tvmaze, v.source,
				tvi.summary, tvi.image,
				tve.title, tve.firstaired, tve.se_complete
				FROM releases r
			INNER JOIN groups g ON g.id = r.groupid
			INNER JOIN category c ON c.id = r.categoryid
			INNER JOIN category cp ON cp.id = c.parentid
			LEFT OUTER JOIN videos v ON r.videos_id = v.id
			LEFT OUTER JOIN tv_info tvi ON r.videos_id = tvi.videos_id
			LEFT OUTER JOIN tv_episodes tve ON r.tv_episodes_id = tve.id
			WHERE %s",
			$this->getConcatenatedCategoryIDs(),
			$gSql
		);

		return (is_array($guid)) ? $this->pdo->query($sql) : $this->pdo->queryOneRow($sql);
	}

	/**
	 * @param        $videoId
	 * @param string $series
	 * @param string $episode
	 *
	 * @return array|bool
	 */
	public function getbyVideoId($videoId, $series = '', $episode = '')
	{
		$tvWhere = '';
		if ($series != '') {
			$tvWhere = 'INNER JOIN tv_episodes tve ON r.tv_episodes_id = tve.id';
			$series = sprintf(' AND tve.series = %s', $this->pdo->escapeString($series));
		}

		if ($episode != '') {
			$episode = sprintf(' AND tve.episode = %s', $this->pdo->escapeString($episode));
		}

		return $this->pdo->queryOneRow(
			sprintf(
				"SELECT r.*,
					CONCAT(cp.title, ' > ', c.title) AS category_name,
					g.name AS group_name
				FROM releases r
				INNER JOIN groups g ON g.id = r.groupid
				INNER JOIN category c ON c.id = r.categoryid
				INNER JOIN category cp ON cp.id = c.parentid
				INNER JOIN videos v ON r.videos_id = v.id
				%s
				WHERE r.categoryid BETWEEN " . Category::TV_ROOT . " AND " . Category::TV_OTHER . "
				AND r.passwordstatus %s
				AND v.id = %d %s %s",
				$tvWhere,
				$this->showPasswords,
				$videoId,
				$series,
				$episode
			)
		);
	}

	/**
	 * Resets the videos_id and tv_episodes_id column on all releases to zero for a given Video ID
	 *
	 * @param $videoId
	 *
	 * @return bool|\PDOStatement
	 */
	public function removeVideoIdFromReleases($videoId)
	{
		return $this->pdo->queryExec(
			sprintf('
					UPDATE releases
					SET videos_id = 0, tv_episodes_id = 0
					WHERE videos_id = %d',
				$videoId
			)
		);
	}

	/**
	 * @param $anidbID
	 *
	 * @return bool|\PDOStatement
	 */
	public function removeAnidbIdFromReleases($anidbID)
	{
		return	$this->pdo->queryExec(
			sprintf('
						UPDATE releases
						SET anidbid = -1
						WHERE anidbid = %d',
				$anidbID
			)
		);
	}

	public function getReleaseNfo($id, $incnfo = true)
	{

		$selnfo = ($incnfo) ? ', uncompress(nfo) as nfo' : '';

		return $this->pdo->queryOneRow(sprintf("SELECT id, releaseid" . $selnfo . " FROM releasenfo WHERE releaseid = %d AND nfo IS NOT NULL", $id));
	}

	public function updateGrab($guid)
	{

		$this->pdo->queryExec(sprintf("UPDATE releases SET grabs = grabs + 1 WHERE guid = %s", $this->pdo->escapeString($guid)));
	}


	/**
	 * @param int    $categorize
	 * @param int    $postProcess
	 * @param string $groupName (optional)
	 * @param NNTP   $nntp
	 * @param bool   $echooutput
	 *
	 * @return int
	 */
	public function processReleases($categorize, $postProcess, $groupName, &$nntp, $echooutput)
	{
		$this->echoCLI = ($echooutput && NN_ECHOCLI);
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

		if (!file_exists($this->pdo->getSetting('nzbpath'))) {
			$this->pdo->log->doEcho($this->pdo->log->primary('Bad or missing nzb directory - ' . $this->pdo->getSetting('nzbpath')), true);

			return -1;
		}

		$this->checkRegexesUptoDate($this->pdo->getSetting('latestregexurl'), $this->pdo->getSetting('latestregexrevision'), $this->pdo->getSetting('newznabID'));

		$this->applyRegex($groupID);
		$this->processIncompleteBinaries($groupID);

		$DIR = NN_MISC;
		$PYTHON = shell_exec('which python3 2>/dev/null');
		$PYTHON = (empty($PYTHON) ? 'python -OOu' : 'python3 -OOu');
		$processRequestIDs = (int)$this->pdo->getSetting('lookup_reqids');
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
				passthru("$PYTHON ${DIR}update/python/requestid_threaded.py");
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
		$users->pruneRequestHistory($this->pdo->getSetting('userdownloadpurgedays'));

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
		$currTime_ori = $this->pdo->queryOneRow("SELECT NOW() as now");
		//
		// aggregate the releasefiles upto the releases.
		//
		$this->pdo->log->doEcho($this->pdo->log->primary('Aggregating Files'));
		$this->pdo->queryExec("UPDATE releases INNER JOIN (SELECT releaseid, COUNT(releaseid) AS num FROM release_files GROUP BY releaseid) b ON b.releaseid = releases.id AND releases.rarinnerfilecount = 0 SET rarinnerfilecount = b.num");

		// Remove the binaries and parts used to form releases, or that are duplicates.
		//
		if ($this->pdo->getSetting('partsdeletechunks') > 0) {
			$this->pdo->log->doEcho($this->pdo->log->primary('Chunk deleting unused binaries and parts'));

			$cc = 0;
			$done = false;
			while (!$done) {
				$dd = $cc;
				$result = $this->pdo->query(sprintf('SELECT p.id AS partsID,b.id AS binariesID FROM %s p
						LEFT JOIN %s b ON b.id = p.binaryid
						WHERE b.dateadded < %s - INTERVAL %d HOUR LIMIT 0,%d',
					$group['pname'],
					$group['bname'],
					$this->pdo->escapeString($currTime_ori["now"]),
					ceil($this->pdo->getSetting('rawretentiondays') * 24),
					$this->pdo->getSetting('partsdeletechunks')
				));
				if (count($result) > 0) {
					$pID = [];
					$bID = [];
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
			$this->pdo->queryExec(sprintf("DELETE %s, %s FROM %s JOIN %s ON %s.id = %s.binaryid
			WHERE %s.dateadded < %s - INTERVAL %d HOUR",
					$group['pname'],
					$group['bname'],
					$group['pname'],
					$group['bname'],
					$group['bname'],
					$group['pname'],
					$group['bname'],
					$this->pdo->escapeString($currTime_ori["now"]),
					ceil($this->pdo->getSetting('rawretentiondays') * 24)
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
		$currTime_ori = $this->pdo->queryOneRow("SELECT NOW() AS now");
		$group = $this->groups->getCBPTableNames($this->tablePerGroup, $groupID);
		$this->pdo->log->doEcho($this->pdo->log->primary('Marking binaries where all parts are available'));
		$result = $this->pdo->queryDirect(sprintf(
			"SELECT relname, date, SUM(reltotalpart) AS reltotalpart,
				groupid, reqid, fromname, SUM(num) AS num,
				COALESCE (g.minfilestoformrelease, s.minfilestoformrelease) AS minfilestoformrelease
				FROM
				(SELECT relname, reltotalpart, groupid, reqid, fromname, max(date) AS date,
				COUNT(id) AS num FROM %s
				WHERE procstat = %s
				GROUP BY relname, reltotalpart, groupid, reqid, fromname
				ORDER BY NULL) x
				LEFT OUTER JOIN groups g ON g.id = x.groupid
				INNER JOIN (SELECT value AS minfilestoformrelease FROM settings WHERE setting = 'minfilestoformrelease' ) s
				GROUP BY relname, groupid, reqid, fromname, minfilestoformrelease
				ORDER BY NULL",
			$group['bname'],
			Releases::PROCSTAT_TITLEMATCHED));

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
					if ($row['reqid'] != '' && $this->pdo->getSetting('reqidurl') != "") {
						//
						// Try and get the name using the group
						//
						$binGroup = $this->groups->getByNameById($groupID);
						$newtitle = $this->getReleaseNameForReqId($this->pdo->getSetting('reqidurl'), $this->pdo->getSetting('newznabID'), $binGroup, $row["reqid"]);

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
		$this->pdo->log->doEcho($this->pdo->log->primary('Creating releases from complete binaries'));

		$this->pdo->ping(true);
		//
		// Get out all distinct relname, group from binaries
		//
		$categorize = new Categorize(['Settings' => $this->pdo]);
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
				$preID = (isset($cleanerName['predb']) ? $cleanerName['predb'] : false);
				$isReqID = (isset($cleanerName['requestid']) ? $cleanerName['requestid'] : false);
				$cleanedName = $cleanedName['cleansubject'];
			} else {
				$properName = true;
				$isReqID = $preID = false;
			}

			if ($preID === false && $cleanedName !== '') {
				// try to match the cleaned searchname to predb title or filename here
				$preHash = new PreDb();
				$preMatch = $preHash->matchPre($cleanedName);
				if ($preMatch !== false) {
					$cleanedName = $preMatch['title'];
					$preID = $preMatch['preid'];
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
					'passwordstatus' => ($this->pdo->getSetting('checkpasswordedrar') > 0 ? -1 : 0),
					'nzbstatus'      => NZB::NZB_NONE,
					'isrenamed'      => ($properName === true ? 1 : 0),
					'reqidstatus'    => ($isReqID === true ? 1 : 0),
					'preid'      => ($preID === false ? 0 : $preID)
				]
			);
			//
			// Tag every binary for this release with its parent release id
			//
			$this->pdo->queryExec(sprintf("UPDATE %s SET procstat = %d, releaseid = %d WHERE relname = %s AND procstat = %d AND %s fromname=%s",
					$group['bname'], Releases::PROCSTAT_RELEASED, $relid, $this->pdo->escapeString($row["relname"]), Releases::PROCSTAT_READYTORELEASE, (!empty($groupID) ? ' groupid = ' . $groupID . ' AND ' : ' '), $this->pdo->escapeString($row["fromname"])
				)
			);
		$cat = new Categorize(['Settings' => $this->pdo]);

			//
			// Write the nzb to disk
			//
			$catId = $cat->determineCategory($groupID, $cleanRelName);
			$nzbfile = $this->nzb->getNZBPath($relguid, $this->pdo->getSetting('nzbpath'), true);
			$this->nzb->writeNZBforreleaseID($relid, $cleanRelName, $catId, $nzbfile, $groupID);

			//
			// Remove used binaries
			//
			$this->pdo->queryExec(sprintf("DELETE %s, %s FROM %s JOIN %s ON %s.id = %s.binaryid WHERE releaseid = %d ",
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
				$this->deleteSingle(['g' => $relguid, 'i' => $relid], $this->nzb, $this->releaseImage);
			} else {
				// Check if gid already exists
				$dupes = $this->pdo->queryOneRow(sprintf("SELECT EXISTS(SELECT 1 FROM releases WHERE gid = %s) AS total", $this->pdo->escapeString($nzbInfo->gid)));
				if ($dupes['total'] > 0) {
					$this->pdo->log->doEcho($this->pdo->log->primary('Duplicate - ' . $cleanRelName . ''));
					$this->deleteSingle(['g' => $relguid, 'i' => $relid], $this->nzb, $this->releaseImage);
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

					/*if ($this->echoCLI) {
						$this->pdo->log->doEcho($this->pdo->log->primary('Added ' . $returnCount . 'releases.'));
					}*/

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
			(new PostProcess(['Echo' => $this->echoCLI, 'Settings' => $this->pdo, 'Groups' => $this->groups]))->processAll($nntp);
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
			$newUnmatchedBinaries = [];
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
				$regexMatches = [];
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


		$xml = Utility::getUrl(['url' => $url, 'verifycert'=> false]);

		if ($xml === false || preg_match('/no feed/i', $xml))
			return "no feed";
		else {
			if ($xml != "") {
				$xmlObj = @simplexml_load_string($xml);
				$arrXml = Utility::objectsIntoArray($xmlObj);

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
		$cleanArr = ['#', '@', '$', '%', '^', '§', '¨', '©', 'Ö'];

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

		if (isset($parameters['regexid']) && $parameters['regexid'] == "")
			$parameters['regexid'] = " null ";

		if (isset($parameters['reqid']) && $parameters['reqid'] != "")
			$parameters['reqid'] = $this->pdo->escapeString($parameters['reqid']);
		else
			$parameters['reqid'] = " null ";

		$parameters['id'] = $this->pdo->queryInsert(sprintf("INSERT INTO releases (name, searchname, totalpart, groupid, adddate, guid, categoryid, regexid, postdate, fromname, size, reqid, passwordstatus, completion, haspreview, nfostatus, nzbstatus,
					isrenamed, iscategorized, reqidstatus, preid)
                    VALUES (%s, %s, %d, %d, now(), %s, %d, %s, %s, %s, 0, %s, %d, 100,-1, -1, %d, %d, 1, %d, %d)",
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
				$parameters['preid']
			)
		);

		$this->sphinxSearch->insertRelease($parameters);

		return $parameters['id'];
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
				DELETE r, rn, rc, uc, rf, ra, rs, rv, re, df
				FROM releases r
				LEFT OUTER JOIN releasenfo rn ON rn.releaseid = r.id
				LEFT OUTER JOIN release_comments rc ON rc.releaseid = r.id
				LEFT OUTER JOIN usercart uc ON uc.releaseid = r.id
				LEFT OUTER JOIN release_files rf ON rf.releaseid = r.id
				LEFT OUTER JOIN releaseaudio ra ON ra.releaseid = r.id
				LEFT OUTER JOIN releasesubs rs ON rs.releaseid = r.id
				LEFT OUTER JOIN releasevideo rv ON rv.releaseid = r.id
				LEFT OUTER JOIN releaseextrafull re ON re.releaseid = r.id
				LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
				WHERE r.guid = %s',
				$this->pdo->escapeString($identifiers['g'])
			)
		);
	}

	/**
	 * Delete multiple releases, or a single by ID.
	 *
	 * @param array|int|string $list   Array of GUID or ID of releases to delete.
	 * @param bool             $isGUID Are the identifiers GUID or ID?
	 */
	public function deleteMultiple($list, $isGUID = false)
	{
		if (!is_array($list)) {
			$list = [$list];
		}

		$nzb = new NZB($this->pdo);
		$releaseImage = new ReleaseImage($this->pdo);

		foreach ($list as $identifier) {
			if ($isGUID) {
				$this->deleteSingle(['g' => $identifier, 'i' => false], $nzb, $releaseImage);
			} else {
				$release = $this->pdo->queryOneRow(sprintf('SELECT guid FROM releases WHERE id = %d', $identifier));
				if ($release === false) {
					continue;
				}
				$this->deleteSingle(['g' => $release['guid'], 'i' => false], $nzb, $releaseImage);
			}
		}
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
		return $this->pdo->query(
				"SELECT CONCAT(cp.title, ' > ', category.title) AS title, COUNT(r.id) AS count
			FROM category
			INNER JOIN category cp ON cp.id = category.parentid
			INNER JOIN releases r ON r.categoryid = category.id
			WHERE r.adddate > NOW() - INTERVAL 1 WEEK
			GROUP BY CONCAT(cp.title, ' > ', category.title)
			ORDER BY count DESC", true, NN_CACHE_EXPIRY_MEDIUM
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
			WHERE r.categoryid BETWEEN " . Category::MOVIE_ROOT . " AND " . Category::MOVIE_OTHER . "
			AND m.imdbid > 0
			AND m.cover = 1
			AND r.id in (SELECT max(id) FROM releases WHERE imdbid > 0 GROUP BY imdbid)
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
			WHERE r.categoryid BETWEEN " . Category::GAME_ROOT . " AND " . Category::GAME_OTHER . "
			AND con.id > 0
			AND con.cover > 0
			AND r.id in (SELECT max(id) FROM releases WHERE consoleinfoid > 0 GROUP BY consoleinfoid)
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
			AND r.id in (SELECT max(id) FROM releases WHERE gamesinfo_id > 0 GROUP BY gamesinfo_id)
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
			WHERE r.categoryid BETWEEN " . Category::MUSIC_ROOT . " AND " . Category::MUSIC_OTHER . "
			AND r.categoryid != " . Category::MUSIC_AUDIOBOOK ."
			AND m.id > 0
			AND m.cover > 0
			AND r.id in (SELECT max(id) FROM releases WHERE musicinfoid > 0 GROUP BY musicinfoid)
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
			WHERE r.categoryid BETWEEN " . Category::BOOKS_ROOT . " AND " . Category::BOOKS_UNKNOWN . "
			OR r.categoryid = " . Category::MUSIC_AUDIOBOOK . "
			AND b.id > 0
			AND b.cover > 0
			AND r.id in (SELECT max(id) FROM releases WHERE bookinfoid > 0 GROUP BY bookinfoid)
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
			WHERE r.categoryid BETWEEN " . Category::XXX_ROOT . " AND " . Category::XXX_OTHER . "
			AND xxx.id > 0
			AND xxx.cover = 1
			AND r.id in (SELECT max(id) FROM releases WHERE xxxinfo_id > 0 GROUP BY xxxinfo_id)
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
		return $this->pdo->query(
			"SELECT r.videos_id, r.guid, r.name, r.searchname, r.size, r.completion,
				r.postdate, r.categoryid, r.comments, r.grabs,
				v.id AS tvid, v.title AS tvtitle, v.tvdb, v.trakt, v.tvrage, v.tvmaze, v.imdb, v.tmdb,
				tvi.image
			FROM releases r
			INNER JOIN videos v ON r.videos_id = v.id
			INNER JOIN tv_info tvi ON r.videos_id = tvi.videos_id
			WHERE r.categoryid BETWEEN " . Category::TV_ROOT . " AND " . Category::TV_OTHER . "
			AND v.id > 0
			AND v.type = 0
			AND tvi.image = 1
			AND r.id in (SELECT max(id) FROM releases WHERE videos_id > 0 GROUP BY videos_id)
			ORDER BY r.postdate DESC
			LIMIT 24", true, NN_CACHE_EXPIRY_LONG
		);
	}

	/**
	 * Get all newest anime with covers for poster wall.
	 *
	 * @return array
	 */
	public function getNewestAnime()
	{
		return $this->pdo->query(
			"SELECT r.anidbid, r.guid, r.name, r.searchname, r.size, r.completion,
				r.postdate, r.categoryid, r.comments, r.grabs, at.title
			FROM releases r
			INNER JOIN anidb_titles at USING (anidbid)
			INNER JOIN anidb_info ai USING (anidbid)
			WHERE r.categoryid = 5070
			AND at.anidbid > 0
			AND at.lang = 'en'
			AND ai.picture != ''
			AND r.id IN (SELECT MAX(id) FROM releases WHERE anidbid > 0 GROUP BY anidbid)
			GROUP BY r.id
			ORDER BY r.postdate DESC
			LIMIT 24", true, NN_CACHE_EXPIRY_LONG
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
			new RequestIDLocal(
				['Echo'   => $echoCLI, 'ConsoleTools' => $consoleTools,
				 'Groups' => $groups, 'Settings' => $this->pdo
				]
			)
			)->lookupRequestIDs(['GroupID' => $groupID, 'limit' => $limit, 'time' => 168]);
		} else {
			$foundRequestIDs = (
			new RequestIDWeb(
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
		$category = new Category(['Settings' => $this->pdo]);
		$genres = new Genres(['Settings' => $this->pdo]);
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
					Releases::PASSWD_RAR
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
					Releases::PASSWD_POTENTIAL
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
						INNER JOIN (SELECT id AS mid FROM musicinfo WHERE musicinfo.genreid = %d) mi
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
					Category::OTHER_MISC,
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
					Category::OTHER_HASHED,
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
		$cat = new Categorize(['Settings' => $this->pdo]);
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
				(!empty($groupID)
						? 'WHERE categoryid = ' . Category::OTHER_MISC . ' AND iscategorized = 0 AND groupid = ' . $groupID
						: 'WHERE categoryid = ' . Category::OTHER_MISC . ' AND iscategorized = 0')
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
			sprintf('UPDATE releases SET categoryid = %d, iscategorized = 0 %s', Category::OTHER_MISC, $where)
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
}
