<?php
require_once(dirname(__FILE__) . "/../bin/config.php");
require_once(WWW_DIR . "/lib/framework/db.php");
require_once(WWW_DIR . "/lib/users.php");
require_once(WWW_DIR . "/lib/releaseregex.php");
require_once(WWW_DIR . "/lib/category.php");
require_once(WWW_DIR . "/lib/nzb.php");
require_once(WWW_DIR . "/lib/nzbinfo.php");
require_once(WWW_DIR . "/lib/zipfile.php");
require_once(WWW_DIR . "/lib/site.php");
require_once(WWW_DIR . "/lib/util.php");
require_once(WWW_DIR . "/lib/releasefiles.php");
require_once(WWW_DIR . "/lib/releaseextra.php");
require_once(WWW_DIR . "/lib/releaseimage.php");
require_once(WWW_DIR . "/lib/releasecomments.php");
require_once(WWW_DIR . "../misc/update_scripts/nix_scripts/tmux/lib/Enzebe.php");
require_once(WWW_DIR . "../misc/update_scripts/nix_scripts/tmux/lib/Info.php");


/**
 * Class Rlses
 */
class Rlses
{
	// RAR/ZIP Passworded indicator.
	const PASSWD_NONE = 0; // No password.
	const PASSWD_POTENTIAL = 1; // Might have a password.
	const BAD_FILE = 2; // Possibly broken RAR/ZIP.
	const PASSWD_RAR = 10; // Definitely passworded.

	// Request ID.
	const REQID_NONE = -3; // The Request ID was not found.
	const REQID_ZERO = -2; // The Request ID was 0.
	const REQID_BAD = -1; // Request ID is in bad format?
	const REQID_UPROC = 0; // Release has not been processed.
	const REQID_FOUND = 1; // Request ID found and release was updated.

	/**
	 * @param bool $echooutput
	 */
	public function __construct($echooutput = false)
	{
		$this->echooutput = ($echooutput && nZEDb_ECHOCLI);
		$this->db = new DB();
		$this->s = new Sites();
		$this->site = $this->s->get();
		$this->groups = new Groups($this->db);
		$this->releaseCleaning = new ReleaseCleaning();
		$this->consoleTools = new ConsoleTools();
		$this->stage5limit = (isset($this->site->maxnzbsprocessed)) ? (int)$this->site->maxnzbsprocessed : 1000;
		$this->completion = (isset($this->site->releasecompletion)) ? (int)$this->site->releasecompletion : 0;
		$this->crosspostt = (isset($this->site->crossposttime)) ? (int)$this->site->crossposttime : 2;
		$this->updategrabs = ($this->site->grabstatus == '0') ? false : true;
		$this->requestids = $this->tmux->lookup_reqids;
		$this->delaytimet = (isset($this->site->delaytime)) ? (int)$this->site->delaytime : 2;
		$this->c = new ColorCLI();
	}

	/**
	 * @return array
	 */
	public function get()
	{
		return $this->db->query(
			'
						SELECT releases.*, g.name AS group_name, c.title AS category_name
						FROM releases
						INNER JOIN category c ON c.ID = releases.categoryID
						INNER JOIN groups g ON g.ID = releases.groupID
						WHERE nzbstatus = 1'
		);
	}

	/**
	 * @param $start
	 * @param $num
	 *
	 * @return array
	 */
	public function getRange($start, $num)
	{
		return $this->db->query(
			sprintf(
				"
								SELECT releases.*, CONCAT(cp.title, ' > ', c.title) AS category_name
								FROM releases
								INNER JOIN category c ON c.ID = releases.categoryID
								INNER JOIN category cp ON cp.ID = c.parentID
								WHERE nzbstatus = 1
								ORDER BY postdate DESC %s",
				($start === false ? '' : 'LIMIT ' . $num . ' OFFSET ' . $start)
			)
		);
	}

	/**
	 * Used for paginator.
	 *
	 * @param        $cat
	 * @param        $maxage
	 * @param array  $excludedcats
	 * @param string $grp
	 *
	 * @return mixed
	 */
	public function getBrowseCount($cat, $maxage = -1, $excludedcats = array(), $grp = '')
	{
		$catsrch = $this->categorySQL($cat);

		$exccatlist = $grpjoin = $grpsql = '';

		$maxagesql = (
		$maxage > 0
			? " AND postdate > NOW() - INTERVAL " .
			(DB_TYPE === 'mysql' ? $maxage . ' DAY ' : "'" . $maxage . " DAYS' ")
			: ''
		);

		if ($grp != '') {
			$grpjoin = 'INNER JOIN groups ON groups.ID = releases.groupID';
			$grpsql = sprintf(' AND groups.name = %s ', $this->db->escapeString($grp));
		}

		if (count($excludedcats) > 0) {
			$exccatlist = ' AND categoryID NOT IN (' . implode(',', $excludedcats) . ')';
		}

		$res = $this->db->queryOneRow(
			sprintf(
				'
								SELECT COUNT(releases.ID) AS num
								FROM releases %s
								WHERE nzbstatus = 1
								AND releases.passwordstatus <= %d %s %s %s %s',
				$grpjoin, $this->showPasswords(), $catsrch, $maxagesql, $exccatlist, $grpsql
			)
		);

		return ($res === false ? 0 : $res['num']);
	}

	/**
	 * Used for browse results.
	 *
	 * @param        $cat
	 * @param        $start
	 * @param        $num
	 * @param        $orderby
	 * @param        $maxage
	 * @param array  $excludedcats
	 * @param string $grp
	 *
	 * @return array
	 */
	public function getBrowseRange($cat, $start, $num, $orderby, $maxage = -1, $excludedcats = array(), $grp = '')
	{
		$limit = ($start === false ? '' : ' LIMIT ' . $num . ' OFFSET ' . $start);

		$catsrch = $this->categorySQL($cat);

		$grpsql = $exccatlist = '';
		$maxagesql = (
		$maxage > 0
			? " AND postdate > NOW() - INTERVAL " .
			(DB_TYPE === 'mysql' ? $maxage . ' DAY ' : "'" . $maxage . " DAYS' ")
			: ''
		);

		if ($grp != '') {
			$grpsql = sprintf(' AND groups.name = %s ', $this->db->escapeString($grp));
		}

		if (count($excludedcats) > 0) {
			$exccatlist = ' AND releases.categoryID NOT IN (' . implode(',', $excludedcats) . ')';
		}

		$order = $this->getBrowseOrder($orderby);

		return $this->db->query(
			sprintf(
				"
								SELECT releases.*,
									CONCAT(cp.title, ' > ', c.title) AS category_name,
									CONCAT(cp.ID, ',', c.ID) AS category_ids,
									groups.name AS group_name,
									rn.ID AS nfoid,
									re.releaseID AS reid
								FROM releases
								INNER JOIN groups ON groups.ID = releases.groupID
								LEFT OUTER JOIN releasevideo re ON re.releaseID = releases.ID
								LEFT OUTER JOIN releasenfo rn ON rn.releaseID = releases.ID
									AND rn.nfo IS NOT NULL
								INNER JOIN category c ON c.ID = releases.categoryID
								INNER JOIN category cp ON cp.ID = c.parentID
								WHERE nzbstatus = 1
								AND releases.passwordstatus <= %d %s %s %s %s
								ORDER BY %s %s %s",
				$this->showPasswords(), $catsrch, $maxagesql, $exccatlist, $grpsql, $order[0], $order[1], $limit
			), true
		);
	}

	/**
	 * Return site setting for hiding/showing passworded releases.
	 *
	 * @return int
	 */
	public function showPasswords()
	{
		$res = $this->db->queryOneRow("SELECT value FROM site WHERE setting = 'showpasswordedrelease'");

		return ($res === false ? 0 : $res['value']);
	}

	/**
	 * Use to order releases on site.
	 *
	 * @param string $orderby
	 *
	 * @return array
	 */
	public function getBrowseOrder($orderby)
	{
		$order = ($orderby == '') ? 'posted_desc' : $orderby;
		$orderArr = explode('_', $order);
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
		return array(
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
		);
	}

	/**
	 * Get list of releases avaible for export.
	 *
	 * @param string $postfrom (optional) Date in this format : 01/01/2014
	 * @param string $postto   (optional) Date in this format : 01/01/2014
	 * @param string $group    (optional) Group ID.
	 *
	 * @return array
	 */
	public function getForExport($postfrom = '', $postto = '', $group = '')
	{
		if ($postfrom != '') {
			$dateparts = explode('/', $postfrom);
			if (count($dateparts) == 3) {
				$postfrom = sprintf(
					' AND postdate > %s ',
					$this->db->escapeString($dateparts[2] . '-' . $dateparts[1] . '-' . $dateparts[0] . ' 00:00:00')
				);
			} else {
				$postfrom = '';
			}
		}

		if ($postto != '') {
			$dateparts = explode('/', $postto);
			if (count($dateparts) == 3) {
				$postto = sprintf(
					' AND postdate < %s ',
					$this->db->escapeString($dateparts[2] . '-' . $dateparts[1] . '-' . $dateparts[0] . ' 23:59:59')
				);
			} else {
				$postto = '';
			}
		}

		if ($group != '' && $group != '-1') {
			$group = sprintf(' AND groupID = %d ', $group);
		} else {
			$group = '';
		}

		return $this->db->query(
			sprintf(
				"
								SELECT searchname, guid, groups.name AS gname, CONCAT(cp.title,'_',category.title) AS catName
								FROM releases
								INNER JOIN category ON releases.categoryID = category.ID
								INNER JOIN groups ON releases.groupID = groups.ID
								INNER JOIN category cp ON cp.ID = category.parentID
								WHERE nzbstatus = 1 %s %s %s",
				$postfrom, $postto, $group
			)
		);
	}

	/**
	 * Get date in this format : 01/01/2014 of the oldest release.
	 *
	 * @return mixed
	 */
	public function getEarliestUsenetPostDate()
	{
		$row = $this->db->queryOneRow(
			sprintf(
				"
								SELECT %s AS postdate FROM releases",
				(DB_TYPE === 'mysql'
					? "DATE_FORMAT(min(postdate), '%d/%m/%Y')"
					: "to_char(min(postdate), 'dd/mm/yyyy')"
				)
			)
		);

		return ($row === false ? '01/01/2014' : $row['postdate']);
	}

	/**
	 * Get date in this format : 01/01/2014 of the newest release.
	 *
	 * @return mixed
	 */
	public function getLatestUsenetPostDate()
	{
		$row = $this->db->queryOneRow(
			sprintf(
				"
								SELECT %s AS postdate FROM releases",
				(DB_TYPE === 'mysql'
					? "DATE_FORMAT(max(postdate), '%d/%m/%Y')"
					: "to_char(max(postdate), 'dd/mm/yyyy')"
				)
			)
		);

		return ($row === false ? '01/01/2014' : $row['postdate']);
	}

	/**
	 * Gets all groups for drop down selection on NZB-Export web page.
	 *
	 * @param bool $blnIncludeAll
	 *
	 * @return array
	 */
	public function getReleasedGroupsForSelect($blnIncludeAll = true)
	{
		$groups = $this->db->query('SELECT DISTINCT groups.ID, groups.name FROM releases INNER JOIN groups on groups.ID = releases.groupID');
		$temp_array = array();

		if ($blnIncludeAll) {
			$temp_array[-1] = '--All Groups--';
		}

		foreach ($groups as $group) {
			$temp_array[$group['ID']] = $group['name'];
		}

		return $temp_array;
	}

	/**
	 * Get releases for RSS.
	 *
	 * @param     $cat
	 * @param     $num
	 * @param int $uid
	 * @param int $rageID
	 * @param int $anidbID
	 * @param int $airdate
	 *
	 * @return array
	 */
	public function getRss($cat, $num, $uid = 0, $rageID, $anidbID, $airdate = -1)
	{
		if (DB_TYPE === 'mysql') {
			$limit = ' LIMIT 0,' . ($num > 100 ? 100 : $num);
		} else {
			$limit = ' LIMIT ' . ($num > 100 ? 100 : $num) . ' OFFSET 0';
		}

		$catsrch = $cartsrch = '';
		if (count($cat) > 0) {
			if ($cat[0] == -2) {
				$cartsrch = sprintf(' INNER JOIN usercart ON usercart.userID = %d AND usercart.releaseID = releases.ID ', $uid);
			} else if ($cat[0] != -1) {
				$catsrch = ' AND (';
				foreach ($cat as $category) {
					if ($category != -1) {
						$categ = new Category();
						if ($categ->isParent($category)) {
							$children = $categ->getChildren($category);
							$chlist = '-99';
							foreach ($children as $child) {
								$chlist .= ', ' . $child['ID'];
							}

							if ($chlist != '-99') {
								$catsrch .= ' releases.categoryID IN (' . $chlist . ') OR ';
							}
						} else {
							$catsrch .= sprintf(' releases.categoryID = %d OR ', $category);
						}
					}
				}
				$catsrch .= '1=2 )';
			}
		}

		$rage = ($rageID > -1) ? sprintf(' AND releases.rageID = %d ', $rageID) : '';
		$anidb = ($anidbID > -1) ? sprintf(' AND releases.anidbID = %d ', $anidbID) : '';
		if (DB_TYPE === 'mysql') {
			$airdate = ($airdate >
				-1) ? sprintf(' AND releases.tvairdate >= DATE_SUB(CURDATE(), INTERVAL %d DAY) ', $airdate) : '';
		} else {
			$airdate = ($airdate >
				-1) ? sprintf(" AND releases.tvairdate >= (CURDATE() - INTERVAL '%d DAYS') ", $airdate) : '';
		}

		return $this->db->query(
			sprintf(
				"
								SELECT releases.*, m.cover, m.imdbID, m.rating, m.plot,
										m.year, m.genre, m.director, m.actors, g.name AS group_name,
										CONCAT(cp.title, ' > ', c.title) AS category_name,
										CONCAT(cp.ID, ',', c.ID) AS category_ids,
										COALESCE(cp.ID,0) AS parentcategoryID,
										mu.title AS mu_title, mu.url AS mu_url, mu.artist AS mu_artist,
										mu.publisher AS mu_publisher, mu.releasedate AS mu_releasedate,
										mu.review AS mu_review, mu.tracks AS mu_tracks, mu.cover AS mu_cover,
										mug.title AS mu_genre, co.title AS co_title, co.url AS co_url,
										co.publisher AS co_publisher, co.releasedate AS co_releasedate,
										co.review AS co_review, co.cover AS co_cover, cog.title AS co_genre
								FROM releases
								INNER JOIN category c ON c.ID = releases.categoryID
								INNER JOIN category cp ON cp.ID = c.parentID
								INNER JOIN groups g ON g.ID = releases.groupID
								LEFT OUTER JOIN movieinfo m ON m.imdbID = releases.imdbID AND m.title != ''
								LEFT OUTER JOIN musicinfo mu ON mu.ID = releases.musicinfoID
								LEFT OUTER JOIN genres mug ON mug.ID = mu.genreID
								LEFT OUTER JOIN consoleinfo co ON co.ID = releases.consolenfoID
								LEFT OUTER JOIN genres cog ON cog.ID = co.genreID %s
								WHERE releases.passwordstatus <= %d %s %s %s %s ORDER BY postdate DESC %s",
				$cartsrch, $this->showPasswords(), $catsrch, $rage, $anidb, $airdate, $limit
			)
		);
	}

	/**
	 * Get TV shows for RSS.
	 *
	 * @param       $num
	 * @param int   $uid
	 * @param array $excludedcats
	 * @param       $airdate
	 *
	 * @return array
	 */
	public function getShowsRss($num, $uid = 0, $excludedcats = array(), $airdate = -1)
	{
		$exccatlist = '';
		if (count($excludedcats) > 0) {
			$exccatlist = ' AND releases.categoryID NOT IN (' . implode(',', $excludedcats) . ')';
		}

		$usql = $this->uSQL($this->db->query(sprintf('SELECT rageID, categoryID FROM userseries WHERE userID = %d', $uid), true), 'rageID');
		if (DB_TYPE === 'mysql') {
			$airdate = ($airdate >
				-1) ? sprintf(' AND releases.tvairdate >= DATE_SUB(CURDATE(), INTERVAL %d DAY) ', $airdate) : '';
		} else {
			$airdate = ($airdate >
				-1) ? sprintf(" AND releases.tvairdate >= (CURDATE() - INTERVAL '%d DAYS') ", $airdate) : '';
		}
		$limit = ' LIMIT ' . ($num > 100 ? 100 : $num) . ' OFFSET 0';

		return $this->db->query(
			sprintf(
				"
								SELECT releases.*, tvr.rageID, tvr.releasetitle, g.name AS group_name,
									CONCAT(cp.title, '-', c.title) AS category_name,
									CONCAT(cp.ID, ',', c.ID) AS category_ids,
									COALESCE(cp.ID,0) AS parentcategoryID
								FROM releases
								INNER JOIN category c ON c.ID = releases.categoryID
								INNER JOIN category cp ON cp.ID = c.parentID
								INNER JOIN groups g ON g.ID = releases.groupID
								LEFT OUTER JOIN tvrage tvr ON tvr.rageID = releases.rageID
								WHERE %s %s %s
								AND releases.passwordstatus <= %d
								ORDER BY postdate DESC %s",
				$usql, $exccatlist, $airdate, $this->showPasswords(), $limit
			)
		);
	}

	/**
	 * Get movies for RSS.
	 *
	 * @param       $num
	 * @param int   $uid
	 * @param array $excludedcats
	 *
	 * @return array
	 */
	public function getMyMoviesRss($num, $uid = 0, $excludedcats = array())
	{
		$exccatlist = '';
		if (count($excludedcats) > 0) {
			$exccatlist = ' AND releases.categoryID NOT IN (' . implode(',', $excludedcats) . ')';
		}

		$usql = $this->uSQL($this->db->query(sprintf('SELECT imdbID, categoryID FROM usermovies WHERE userID = %d', $uid), true), 'imdbID');
		$limit = ' LIMIT ' . ($num > 100 ? 100 : $num) . ' OFFSET 0';

		return $this->db->query(
			sprintf(
				"
								SELECT releases.*, mi.title AS releasetitle, g.name AS group_name,
									CONCAT(cp.title, '-', c.title) AS category_name,
									CONCAT(cp.ID, ',', c.ID) AS category_ids,
									COALESCE(cp.ID,0) AS parentcategoryID
								FROM releases
								INNER JOIN category c ON c.ID = releases.categoryID
								INNER JOIN category cp ON cp.ID = c.parentID
								INNER JOIN groups g ON g.ID = releases.groupID
								LEFT OUTER JOIN movieinfo mi ON mi.imdbID = releases.imdbID
								WHERE %s %s
								AND releases.passwordstatus <= %d
								ORDER BY postdate DESC %s",
				$usql, $exccatlist, $this->showPasswords(), $limit
			)
		);
	}

	/**
	 * Get TV for my shows page.
	 *
	 * @param       $usershows
	 * @param       $start
	 * @param       $num
	 * @param       $orderby
	 * @param       $maxage
	 * @param array $excludedcats
	 *
	 * @return array
	 */
	public function getShowsRange($usershows, $start, $num, $orderby, $maxage = -1, $excludedcats = array())
	{
		if ($start === false) {
			$limit = '';
		} else {
			$limit = ' LIMIT ' . $num . ' OFFSET ' . $start;
		}

		$exccatlist = $maxagesql = '';
		if (count($excludedcats) > 0) {
			$exccatlist = ' AND releases.categoryID NOT IN (' . implode(',', $excludedcats) . ')';
		}

		$usql = $this->uSQL($usershows, 'rageID');

		if ($maxage > 0) {
			if (DB_TYPE === 'mysql') {
				$maxagesql = sprintf(' AND releases.postdate > NOW() - INTERVAL %d DAY ', $maxage);
			} else {
				$maxagesql = sprintf(" AND releases.postdate > NOW() - INTERVAL '%d DAYS' ", $maxage);
			}
		}

		$order = $this->getBrowseOrder($orderby);

		return $this->db->query(
			sprintf(
				"
								SELECT releases.*, CONCAT(cp.title, '-', c.title) AS category_name,
									CONCAT(cp.ID, ',', c.ID) AS category_ids, groups.name AS group_name,
									rn.ID AS nfoid, re.releaseID AS reid
								FROM releases
								LEFT OUTER JOIN releasevideo re ON re.releaseID = releases.ID
								INNER JOIN groups ON groups.ID = releases.groupID
								LEFT OUTER JOIN releasenfo rn ON rn.releaseID = releases.ID AND rn.nfo IS NOT NULL
								INNER JOIN category c ON c.ID = releases.categoryID
								INNER JOIN category cp ON cp.ID = c.parentID
								WHERE %s %s
								AND releases.passwordstatus <= %d %s
								ORDER BY %s %s %s",
				$usql, $exccatlist, $this->showPasswords(), $maxagesql, $order[0], $order[1], $limit
			)
		);
	}

	/**
	 * Get count for my shows page pagination.
	 *
	 * @param       $usershows
	 * @param       $maxage
	 * @param array $excludedcats
	 *
	 * @return int
	 */
	public function getShowsCount($usershows, $maxage = -1, $excludedcats = array())
	{
		$exccatlist = $maxagesql = '';
		if (count($excludedcats) > 0) {
			$exccatlist = ' AND releases.categoryID NOT IN (' . implode(',', $excludedcats) . ')';
		}

		$usql = $this->uSQL($usershows, 'rageID');

		if ($maxage > 0) {
			if (DB_TYPE === 'mysql') {
				$maxagesql = sprintf(' AND releases.postdate > NOW() - INTERVAL %d DAY ', $maxage);
			} else {
				$maxagesql = sprintf(" AND releases.postdate > NOW() - INTERVAL '%d DAYS' ", $maxage);
			}
		}

		$res = $this->db->queryOneRow(
			sprintf(
				'
								SELECT COUNT(releases.ID) AS num
								FROM releases
								WHERE %s %s
								AND releases.passwordstatus <= %d %s',
				$usql, $exccatlist, $this->showPasswords(), $maxagesql
			), true
		);

		return ($res === false ? 0 : $res['num']);
	}

	/**
	 * Get count for admin release list page.
	 *
	 * @return int
	 */
	public function getCount()
	{
		$res = $this->db->queryOneRow('SELECT COUNT(ID) AS num FROM releases');

		return ($res === false ? 0 : $res['num']);
	}

	/**
	 * Delete a release or multiple releases.
	 *
	 * @param int|string $id
	 * @param bool       $isGuid
	 */
	public function delete($id, $isGuid = false)
	{
		if (!is_array($id)) {
			$id = array($id);
		}

		foreach ($id as $identifier) {
			if ($isGuid) {
				$rel = $this->getByGuid($identifier);
			} else {
				$rel = $this->getById($identifier);
			}
			$this->fastDelete($rel['ID'], $rel['guid']);
		}
	}

	/**
	 * Deletes a single release, and all the corresponding files.
	 *
	 * @param int    $id   release id
	 * @param string $guid release guid
	 */
	public function fastDelete($id, $guid)
	{
		$nzb = new Enzebe();
		// Delete NZB from disk.
		$nzbpath = $nzb->getNZBPath($guid);
		if (is_file($nzbpath)) {
			@unlink($nzbpath);
		}

		// Delete images.
		$ri = new ReleaseImage();
		$ri->delete($guid);

		// Delete from DB.
		if (DB_TYPE === 'mysql') {
			$this->db->exec(
				sprintf(
					'DELETE
						releases, releasenfo, releasecomment, usercart, releasefiles,
						releaseaudio, releasesubs, releasevideo, releaseextrafull
					FROM releases
					LEFT OUTER JOIN releasenfo ON releasenfo.releaseID = releases.ID
					LEFT OUTER JOIN releasecomment ON releasecomment.releaseID = releases.ID
					LEFT OUTER JOIN usercart ON usercart.releaseID = releases.ID
					LEFT OUTER JOIN releasefiles ON releasefiles.releaseID = releases.ID
					LEFT OUTER JOIN releaseaudio ON releaseaudio.releaseID = releases.ID
					LEFT OUTER JOIN releasesubs ON releasesubs.releaseID = releases.ID
					LEFT OUTER JOIN releasevideo ON releasevideo.releaseID = releases.ID
					LEFT OUTER JOIN releaseextrafull ON releaseextrafull.releaseID = releases.ID
					WHERE releases.ID = %d',
					$id
				)
			);
		} else {
			$this->db->exec('DELETE FROM releasenfo WHERE releaseID = ' . $id);
			$this->db->exec('DELETE FROM releasecomment WHERE releaseID = ' . $id);
			$this->db->exec('DELETE FROM usercart WHERE releaseID = ' . $id);
			$this->db->exec('DELETE FROM releasefiles WHERE releaseID = ' . $id);
			$this->db->exec('DELETE FROM releaseaudio WHERE releaseID = ' . $id);
			$this->db->exec('DELETE FROM releasesubs WHERE releaseID = ' . $id);
			$this->db->exec('DELETE FROM releasevideo WHERE releaseID = ' . $id);
			$this->db->exec('DELETE FROM releaseextrafull WHERE releaseID = ' . $id);
			$this->db->exec('DELETE FROM releases WHERE ID = ' . $id);
		}
	}

	/**
	 * Used for release edit page on site.
	 *
	 * @param $id
	 * @param $name
	 * @param $searchname
	 * @param $fromname
	 * @param $category
	 * @param $parts
	 * @param $grabs
	 * @param $size
	 * @param $posteddate
	 * @param $addeddate
	 * @param $rageID
	 * @param $seriesfull
	 * @param $season
	 * @param $episode
	 * @param $imdbID
	 * @param $anidbID
	 */
	public function update(
		$id, $name, $searchname, $fromname, $category, $parts, $grabs, $size,
		$posteddate, $addeddate, $rageID, $seriesfull, $season, $episode, $imdbID, $anidbID
	)
	{
		$this->db->exec(
			sprintf(
				'UPDATE releases
				SET name = %s, searchname = %s, fromname = %s, categoryID = %d,
				totalpart = %d, grabs = %d, size = %s, postdate = %s, adddate = %s, rageID = %d,
				seriesfull = %s, season = %s, episode = %s, imdbID = %d, anidbID = %d
				WHERE ID = %d',
				$this->db->escapeString($name), $this->db->escapeString($searchname), $this->db->escapeString($fromname),
				$category, $parts, $grabs, $this->db->escapeString($size), $this->db->escapeString($posteddate),
				$this->db->escapeString($addeddate), $rageID, $this->db->escapeString($seriesfull),
				$this->db->escapeString($season), $this->db->escapeString($episode), $imdbID, $anidbID, $id
			)
		);
	}

	/**
	 * Used for updating releases on site.
	 *
	 * @param $guids
	 * @param $category
	 * @param $grabs
	 * @param $rageID
	 * @param $season
	 * @param $imdbID
	 *
	 * @return array|bool|int
	 */
	public function updatemulti($guids, $category, $grabs, $rageID, $season, $imdbID)
	{
		if (!is_array($guids) || sizeof($guids) < 1) {
			return false;
		}

		$update = array(
			'categoryID' => (($category == '-1') ? '' : $category),
			'grabs'      => $grabs,
			'rageID'     => $rageID,
			'season'     => $season,
			'imdbID'     => $imdbID
		);

		$updateSql = array();
		foreach ($update as $updk => $updv) {
			if ($updv != '') {
				$updateSql[] = sprintf($updk . '=%s', $this->db->escapeString($updv));
			}
		}

		if (count($updateSql) < 1) {
			return -1;
		}

		$updateGuids = array();
		foreach ($guids as $guid) {
			$updateGuids[] = $this->db->escapeString($guid);
		}

		return $this->db->query(
			sprintf(
				'
								UPDATE releases SET %s WHERE guid IN (%s)',
				implode(', ', $updateSql),
				implode(', ', $updateGuids)
			)
		);
	}

	/**
	 * Creates part of a query for some functions.
	 *
	 * @param $userquery
	 * @param $type
	 *
	 * @return string
	 */
	public function uSQL($userquery, $type)
	{
		$usql = '(1=2 ';
		foreach ($userquery as $u) {
			$usql .= sprintf('OR (releases.%s = %d', $type, $u[$type]);
			if ($u['categoryID'] != '') {
				$catsArr = explode('|', $u['categoryID']);
				if (count($catsArr) > 1) {
					$usql .= sprintf(' AND releases.categoryID IN (%s)', implode(',', $catsArr));
				} else {
					$usql .= sprintf(' AND releases.categoryID = %d', $catsArr[0]);
				}
			}
			$usql .= ') ';
		}
		$usql .= ') ';

		return $usql;
	}

	/**
	 * Creates part of a query for searches based on the type of search.
	 *
	 * @param $search
	 * @param $type
	 *
	 * @return string
	 */
	public function searchSQL($search, $type)
	{
		// If the query starts with a ^ or ! it indicates the search is looking for items which start with the term
		// still do the fulltext match, but mandate that all items returned must start with the provided word.
		$words = explode(' ', $search);

		//only used to get a count of words
		$searchwords = $searchsql = '';
		$intwordcount = 0;

		if (count($words) > 0) {
			if ($type === 'name' || $type === 'searchname') {
				//at least 1 term needs to be mandatory
				if (!preg_match('/[+|!|^]/', $search)) {
					$search = '+' . $search;
					$words = explode(' ', $search);
				}
				foreach ($words as $word) {
					$word = trim(rtrim(trim($word), '-'));
					$word = str_replace('!', '+', $word);
					$word = str_replace('^', '+', $word);
					$word = str_replace("'", "\\'", $word);

					if ($word !== '' && $word !== '-' && strlen($word) >= 2) {
						$searchwords .= sprintf('%s ', $word);
					}
				}
				$searchwords = trim($searchwords);

				$searchsql .= sprintf(" AND MATCH(rs.name, rs.searchname) AGAINST('%s' IN BOOLEAN MODE)",
					$searchwords
				);
			}
			if ($searchwords === '') {
				$words = explode(' ', $search);
				$like = 'ILIKE';
				if (DB_TYPE === 'mysql') {
					$like = 'LIKE';
				}
				foreach ($words as $word) {
					if ($word != '') {
						$word = trim(rtrim(trim($word), '-'));
						if ($intwordcount == 0 && (strpos($word, '^') === 0)) {
							$searchsql .= sprintf(
								' AND releases.%s %s %s', $type, $like, $this->db->escapeString(
									substr($word, 1) . '%'
								)
							);
						} else if (substr($word, 0, 2) == '--') {
							$searchsql .= sprintf(
								' AND releases.%s NOT %s %s', $type, $like, $this->db->escapeString(
									'%' . substr($word, 2) . '%'
								)
							);
						} else {
							$searchsql .= sprintf(
								' AND releases.%s %s %s', $type, $like, $this->db->escapeString(
									'%' . $word . '%'
								)
							);
						}

						$intwordcount++;
					}
				}
			}

			return $searchsql;
		}
	}

	// Creates part of a query for searches requiring the categoryID's.
	public function categorySQL($cat)
	{
		$catsrch = '';
		if (count($cat) > 0 && $cat[0] != -1) {
			$categ = new Category();
			$catsrch = ' AND (';
			foreach ($cat as $category) {
				if ($category != -1) {
					if ($categ->isParent($category)) {
						$children = $categ->getChildren($category);
						$chlist = '-99';
						foreach ($children as $child) {
							$chlist .= ', ' . $child['ID'];
						}

						if ($chlist != '-99') {
							$catsrch .= ' releases.categoryID IN (' . $chlist . ') OR ';
						}
					} else {
						$catsrch .= sprintf(' releases.categoryID = %d OR ', $category);
					}
				}
			}
			$catsrch .= '1=2 )';
		}

		return $catsrch;
	}

	// Function for searching on the site (by subject, searchname or advanced).
	public function search($searchname, $usenetname, $postername, $groupname, $cat = array(-1), $sizefrom, $sizeto, $hasnfo, $hascomments, $daysnew, $daysold, $offset = 0, $limit = 1000, $orderby = '', $maxage = -1, $excludedcats = array(), $type = 'basic')
	{
		if ($type !== 'advanced') {
			$catsrch = $this->categorySQL($cat);
		} else {
			$catsrch = '';
			if ($cat != '-1') {
				$catsrch = sprintf(' AND (releases.categoryID = %d) ', $cat);
			}
		}

		$daysnewsql = $daysoldsql = $maxagesql = $groupIDsql = $parentcatsql = '';

		$searchnamesql = ($searchname != '-1' ? $this->searchSQL($searchname, 'searchname') : '');
		$usenetnamesql = ($usenetname != '-1' ? $this->searchSQL($usenetname, 'name') : '');
		$posternamesql = ($postername != '-1' ? $this->searchSQL($postername, 'fromname') : '');
		$hasnfosql = ($hasnfo != '0' ? ' AND releases.nfostatus = 1 ' : '');
		$hascommentssql = ($hascomments != '0' ? ' AND releases.comments > 0 ' : '');
		$exccatlist = (count($excludedcats) > 0 ?
			' AND releases.categoryID NOT IN (' . implode(',', $excludedcats) . ')' : '');

		if ($daysnew != '-1') {
			if (DB_TYPE === 'mysql') {
				$daysnewsql = sprintf(' AND releases.postdate < (NOW() - INTERVAL %d DAY) ', $daysnew);
			} else {
				$daysnewsql = sprintf(" AND releases.postdate < NOW() - INTERVAL '%d DAYS' ", $daysnew);
			}
		}

		if ($daysold != '-1') {
			if (DB_TYPE === 'mysql') {
				$daysoldsql = sprintf(' AND releases.postdate > (NOW() - INTERVAL %d DAY) ', $daysold);
			} else {
				$daysoldsql = sprintf(" AND releases.postdate > NOW() - INTERVAL '%d DAYS' ", $daysold);
			}
		}

		if ($maxage > 0) {
			if (DB_TYPE === 'mysql') {
				$maxagesql = sprintf(' AND releases.postdate > (NOW() - INTERVAL %d DAY) ', $maxage);
			} else {
				$maxagesql = sprintf(" AND releases.postdate > NOW() - INTERVAL '%d DAYS' ", $maxage);
			}
		}

		if ($groupname != '-1') {
			$groupID = $this->functions->getIDByName($groupname);
			$groupIDsql = sprintf(' AND releases.groupID = %d ', $groupID);
		}

		$sizefromsql = '';
		switch ($sizefrom) {
			case '1':
			case '2':
			case '3':
			case '4':
			case '5':
			case '6':
			case '7':
			case '8':
			case '9':
			case '10':
			case '11':
				$sizefromsql = ' AND releases.size > ' . (string)(104857600 * (int)$sizefrom) . ' ';
				break;
			default:
				break;
		}

		$sizetosql = '';
		switch ($sizeto) {
			case '1':
			case '2':
			case '3':
			case '4':
			case '5':
			case '6':
			case '7':
			case '8':
			case '9':
			case '10':
			case '11':
				$sizetosql = ' AND releases.size < ' . (string)(104857600 * (int)$sizeto) . ' ';
				break;
			default:
				break;
		}

		if ($orderby == '') {
			$order[0] = 'postdate ';
			$order[1] = 'desc ';
		} else {
			$order = $this->getBrowseOrder($orderby);
		}

		$sql = sprintf(
			"SELECT * FROM (SELECT releases.*, CONCAT(cp.title, ' > ', c.title) AS category_name,
			CONCAT(cp.ID, ',', c.ID) AS category_ids,
			groups.name AS group_name, rn.ID AS nfoid,
			re.releaseID AS reid, cp.ID AS categoryparentID
			FROM releases
			INNER JOIN releasesearch rs on rs.releaseID = releases.ID
			LEFT OUTER JOIN releasevideo re ON re.releaseID = releases.ID
			LEFT OUTER JOIN releasenfo rn ON rn.releaseID = releases.ID
			INNER JOIN groups ON groups.ID = releases.groupID
			INNER JOIN category c ON c.ID = releases.categoryID
			INNER JOIN category cp ON cp.ID = c.parentID
			WHERE releases.passwordstatus <= %d %s %s %s %s %s %s %s %s %s %s %s %s %s) r
			ORDER BY r.%s %s LIMIT %d OFFSET %d",
			$this->showPasswords(), $searchnamesql, $usenetnamesql, $maxagesql, $posternamesql, $groupIDsql, $sizefromsql,
			$sizetosql, $hasnfosql, $hascommentssql, $catsrch, $daysnewsql, $daysoldsql, $exccatlist, $order[0],
			$order[1], $limit, $offset
		);
		$wherepos = strpos($sql, 'WHERE');
		$countres = $this->db->queryOneRow(
			'SELECT COUNT(releases.ID) AS num FROM releases INNER JOIN releasesearch rs on rs.releaseID = releases.ID ' .
			substr($sql, $wherepos, strrpos($sql, ')') - $wherepos)
		);
		$res = $this->db->query($sql);
		if (count($res) > 0) {
			$res[0]['_totalrows'] = $countres['num'];
		}

		return $res;
	}

	public function searchbyrageID($rageID, $series = '', $episode = '', $offset = 0, $limit = 100, $name = '', $cat = array(-1), $maxage = -1)
	{
		$rageIDsql = $maxagesql = '';

		if ($rageID != '-1') {
			$rageIDsql = sprintf(' AND rageID = %d ', $rageID);
		}

		if ($series != '') {
			// Exclude four digit series, which will be the year 2010 etc.
			if (is_numeric($series) && strlen($series) != 4) {
				$series = sprintf('S%02d', $series);
			}

			$series = sprintf(' AND UPPER(releases.season) = UPPER(%s)', $this->db->escapeString($series));
		}

		if ($episode != '') {
			if (is_numeric($episode)) {
				$episode = sprintf('E%02d', $episode);
			}

			$like = 'ILIKE';
			if (DB_TYPE === 'mysql') {
				$like = 'LIKE';
			}
			$episode = sprintf(' AND releases.episode %s %s', $like, $this->db->escapeString('%' . $episode . '%'));
		}

		$searchsql = '';
		if ($name !== '') {
			$searchsql = $this->searchSQL($name, 'searchname');
		}
		$catsrch = $this->categorySQL($cat);

		if ($maxage > 0) {
			if (DB_TYPE === 'mysql') {
				$maxagesql = sprintf(' AND releases.postdate > NOW() - INTERVAL %d DAY ', $maxage);
			} else {
				$maxagesql = sprintf(" AND releases.postdate > NOW() - INTERVAL '%d DAYS' ", $maxage);
			}
		}
		$sql = sprintf("SELECT releases.*, concat(cp.title, ' > ', c.title) AS category_name, CONCAT(cp.ID, ',', c.ID) AS category_ids, groups.name AS group_name, rn.ID AS nfoid, re.releaseID AS reid FROM releases INNER JOIN category c ON c.ID = releases.categoryID INNER JOIN groups ON groups.ID = releases.groupID INNER JOIN releasesearch rs on rs.releaseID = releases.ID LEFT OUTER JOIN releasevideo re ON re.releaseID = releases.ID LEFT OUTER JOIN releasenfo rn ON rn.releaseID = releases.ID AND rn.nfo IS NOT NULL INNER JOIN category cp ON cp.ID = c.parentID WHERE releases.passwordstatus <= %d %s %s %s %s %s %s ORDER BY postdate DESC LIMIT %d OFFSET %d", $this->showPasswords(), $rageIDsql, $series, $episode, $searchsql, $catsrch, $maxagesql, $limit, $offset);
		$orderpos = strpos($sql, 'ORDER BY');
		$wherepos = strpos($sql, 'WHERE');
		$sqlcount = 'SELECT COUNT(releases.ID) AS num FROM releases INNER JOIN releasesearch rs on rs.releaseID = releases.ID  ' . substr($sql, $wherepos, $orderpos - $wherepos);

		$countres = $this->db->queryOneRow($sqlcount);
		$res = $this->db->query($sql);
		if (count($res) > 0) {
			$res[0]['_totalrows'] = $countres['num'];
		}

		return $res;
	}

	public function searchbyanidbID($anidbID, $epno = '', $offset = 0, $limit = 100, $name = '', $cat = array(-1), $maxage = -1)
	{
		$anidbID = ($anidbID > -1) ? sprintf(' AND anidbID = %d ', $anidbID) : '';

		$like = 'ILIKE';
		if (DB_TYPE === 'mysql') {
			$like = 'LIKE';
		}

		is_numeric($epno) ? $epno = sprintf(
			" AND releases.episode %s '%s' ", $like, $this->db->escapeString(
				'%' . $epno . '%'
			)
		) : '';

		$searchsql = '';
		if ($name !== '') {
			$searchsql = $this->searchSQL($name, 'searchname');
		}
		$catsrch = $this->categorySQL($cat);

		$maxagesql = '';
		if ($maxage > 0) {
			if (DB_TYPE === 'mysql') {
				$maxagesql = sprintf(' AND releases.postdate > NOW() - INTERVAL %d DAY ', $maxage);
			} else {
				$maxagesql = sprintf(" AND releases.postdate > NOW() - INTERVAL '%d DAYS' ", $maxage);
			}
		}

		$sql = sprintf("SELECT releases.*, CONCAT(cp.title, ' > ', c.title) AS category_name, CONCAT(cp.ID, ',', c.ID) AS category_ids, groups.name AS group_name, rn.ID AS nfoid FROM releases INNER JOIN releasesearch rs on rs.releaseID = releases.ID INNER JOIN category c ON c.ID = releases.categoryID INNER JOIN groups ON groups.ID = releases.groupID LEFT OUTER JOIN releasenfo rn ON rn.releaseID = releases.ID and rn.nfo IS NOT NULL INNER JOIN category cp ON cp.ID = c.parentID WHERE releases.passwordstatus <= %d %s %s %s %s %s ORDER BY postdate DESC LIMIT %d OFFSET %d", $this->showPasswords(), $anidbID, $epno, $searchsql, $catsrch, $maxage, $limit, $offset);
		$orderpos = strpos($sql, 'ORDER BY');
		$wherepos = strpos($sql, 'WHERE');
		$sqlcount = 'SELECT COUNT(releases.ID) AS num FROM releases INNER JOIN releasesearch rs on rs.releaseID = releases.ID ' . substr($sql, $wherepos, $orderpos - $wherepos);

		$countres = $this->db->queryOneRow($sqlcount);
		$res = $this->db->query($sql);
		if (count($res) > 0) {
			$res[0]['_totalrows'] = $countres['num'];
		}

		return $res;
	}

	public function searchbyimdbID($imdbID, $offset = 0, $limit = 100, $name = '', $cat = array(-1), $maxage = -1)
	{
		if ($imdbID != '-1' && is_numeric($imdbID)) {
			// Pad ID with zeros just in case.
			$imdbID = str_pad($imdbID, 7, '0', STR_PAD_LEFT);
			$imdbID = sprintf(' AND imdbID = %d ', $imdbID);
		} else {
			$imdbID = '';
		}

		$searchsql = '';
		if ($name !== '') {
			$searchsql = $this->searchSQL($name, 'searchname');
		}
		$catsrch = $this->categorySQL($cat);

		if ($maxage > 0) {
			if (DB_TYPE === 'mysql') {
				$maxage = sprintf(' AND releases.postdate > NOW() - INTERVAL %d DAY ', $maxage);
			} else {
				$maxage = sprintf(" AND releases.postdate > NOW() - INTERVAL '%d DAYS ", $maxage);
			}
		} else {
			$maxage = '';
		}

		$sql = sprintf("SELECT releases.*, concat(cp.title, ' > ', c.title) AS category_name, CONCAT(cp.ID, ',', c.ID) AS category_ids, groups.name AS group_name, rn.ID AS nfoid FROM releases INNER JOIN groups ON groups.ID = releases.groupID INNER JOIN category c ON c.ID = releases.categoryID INNER JOIN releasesearch rs on rs.releaseID = releases.ID LEFT OUTER JOIN releasenfo rn ON rn.releaseID = releases.ID AND rn.nfo IS NOT NULL INNER JOIN category cp ON cp.ID = c.parentID WHERE releases.passwordstatus <= %d %s %s %s %s ORDER BY postdate DESC LIMIT %d OFFSET %d", $this->showPasswords(), $searchsql, $imdbID, $catsrch, $maxage, $limit, $offset);
		$orderpos = strpos($sql, 'ORDER BY');
		$wherepos = strpos($sql, 'WHERE');
		$sqlcount = 'SELECT COUNT(releases.ID) AS num FROM releases INNER JOIN releasesearch rs on rs.releaseID = releases.ID ' . substr($sql, $wherepos, $orderpos - $wherepos);

		$countres = $this->db->queryOneRow($sqlcount);
		$res = $this->db->query($sql);
		if (count($res) > 0) {
			$res[0]['_totalrows'] = $countres['num'];
		}

		return $res;
	}

	public function searchSimilar($currentid, $name, $limit = 6, $excludedcats = array())
	{
		// Get the category for the parent of this release.
		$currRow = $this->getById($currentid);
		$cat = new Category();
		$catrow = $cat->getById($currRow['categoryID']);
		$parentCat = $catrow['parentID'];

		$name = $this->getSimilarName($name);
		$results = $this->search(
			$name, -1, -1, -1, array($parentCat), -1, -1, 0, 0, -1, -1, 0, $limit, '', -1,
			$excludedcats
		);
		if (!$results) {
			return $results;
		}

		$ret = array();
		foreach ($results as $res) {
			if ($res['ID'] != $currentid && $res['categoryparentID'] == $parentCat) {
				$ret[] = $res;
			}
		}

		return $ret;
	}

	public function getSimilarName($name)
	{
		$words = str_word_count(str_replace(array('.', '_'), ' ', $name), 2);
		$firstwords = array_slice($words, 0, 3);

		return implode(' ', $firstwords);
	}

	public function getByGuid($guid)
	{
		if (is_array($guid)) {
			$tmpguids = array();
			foreach ($guid as $g) {
				$tmpguids[] = $this->db->escapeString($g);
			}
			$gsql = sprintf('guid IN (%s)', implode(',', $tmpguids));
		} else {
			$gsql = sprintf('guid = %s', $this->db->escapeString($guid));
		}
		$sql = sprintf("SELECT releases.*, CONCAT(cp.title, ' > ', c.title) AS category_name, CONCAT(cp.ID, ',', c.ID) AS category_ids, groups.name AS group_name FROM releases INNER JOIN groups ON groups.ID = releases.groupID INNER JOIN category c ON c.ID = releases.categoryID INNER JOIN category cp ON cp.ID = c.parentID WHERE %s ", $gsql);

		return (is_array($guid)) ? $this->db->query($sql) : $this->db->queryOneRow($sql);
	}

	// Writes a zip file of an array of release guids directly to the stream.
	public function getZipped($guids)
	{
		$nzb = new Enzebe();
		$zipfile = new ZipFile();

		foreach ($guids as $guid) {
			$nzbpath = $nzb->getNZBPath($guid);

			if (is_file($nzbpath)) {
				ob_start();
				@readgzfile($nzbpath);
				$nzbfile = ob_get_contents();
				ob_end_clean();

				$filename = $guid;
				$r = $this->getByGuid($guid);
				if ($r) {
					$filename = $r['searchname'];
				}

				$zipfile->addFile($nzbfile, $filename . '.nzb');
			}
		}

		return $zipfile->file();
	}

	public function getbyrageID($rageID, $series = '', $episode = '')
	{
		if ($series != '') {
			// Exclude four digit series, which will be the year 2010 etc.
			if (is_numeric($series) && strlen($series) != 4) {
				$series = sprintf('S%02d', $series);
			}

			$series = sprintf(' AND UPPER(releases.season) = UPPER(%s)', $this->db->escapeString($series));
		}

		if ($episode != '') {
			if (is_numeric($episode)) {
				$episode = sprintf('E%02d', $episode);
			}

			$episode = sprintf(' AND UPPER(releases.episode) = UPPER(%s)', $this->db->escapeString($episode));
		}

		return $this->db->queryOneRow(sprintf("SELECT releases.*, CONCAT(cp.title, ' > ', c.title) AS category_name, groups.name AS group_name FROM releases INNER JOIN groups ON groups.ID = releases.groupID INNER JOIN category c ON c.ID = releases.categoryID INNER JOIN category cp ON cp.ID = c.parentID WHERE releases.passwordstatus <= %d AND rageID = %d %s %s", $this->showPasswords(), $rageID, $series, $episode));
	}

	public function removerageIDFromReleases($rageID)
	{
		$res = $this->db->queryOneRow(sprintf('SELECT COUNT(ID) AS num FROM releases WHERE rageID = %d', $rageID));
		$this->db->exec(sprintf('UPDATE releases SET rageID = -1, seriesfull = NULL, season = NULL, episode = NULL WHERE rageID = %d', $rageID));

		return $res['num'];
	}

	public function removeanidbIDFromReleases($anidbID)
	{
		$res = $this->db->queryOneRow(sprintf('SELECT COUNT(ID) AS num FROM releases WHERE anidbID = %d', $anidbID));
		$this->db->exec(sprintf('UPDATE releases SET anidbID = -1, episode = NULL, tvtitle = NULL, tvairdate = NULL WHERE anidbID = %d', $anidbID));

		return $res['num'];
	}

	public function getById($id)
	{
		return $this->db->queryOneRow(sprintf('SELECT releases.*, groups.name AS group_name FROM releases INNER JOIN groups ON groups.ID = releases.groupID WHERE releases.ID = %d ', $id));
	}

	public function getReleaseNfo($id, $incnfo = true)
	{
		if (DB_TYPE === 'mysql') {
			$uc = 'UNCOMPRESS(nfo)';
		} else {
			$uc = 'nfo';
		}
		$selnfo = ($incnfo) ? ", {$uc} AS nfo" : '';

		return $this->db->queryOneRow(
			sprintf(
				'SELECT ID, releaseID' . $selnfo . ' FROM releasenfo WHERE releaseID = %d AND nfo IS NOT NULL', $id
			)
		);
	}

	public function updateGrab($guid)
	{
		if ($this->updategrabs) {
			$this->db->exec(sprintf('UPDATE releases SET grabs = grabs + 1 WHERE guid = %s', $this->db->escapeString($guid)));
		}
	}

	// Sends releases back to other->misc.
	public function resetCategorize($where = '')
	{
		$this->db->exec('UPDATE releases SET categoryID = 7010, iscategorized = 0 ' . $where);
	}

	// Categorizes releases.
	// $type = name or searchname
	// Returns the quantity of categorized releases.
	public function categorizeRelease($type, $where = '', $echooutput = false)
	{
		$cat = new Category();
		$relcount = 0;
		$resrel = $this->db->queryDirect('SELECT ID, ' . $type . ', groupID FROM releases ' . $where);
		$total = 0;
		if ($resrel !== false) {
			$total = $resrel->rowCount();
		}
		if ($total > 0) {
			foreach ($resrel as $rowrel) {
				$catId = $cat->determineCategory($rowrel[$type], $rowrel['groupID']);
				$this->db->exec(sprintf('UPDATE releases SET categoryID = %d, iscategorized = 1 WHERE ID = %d', $catId, $rowrel['ID']));
				$relcount++;
				if ($this->echooutput) {
					$this->consoleTools->overWritePrimary(
						'Categorizing: ' . $this->consoleTools->percentString($relcount, $total)
					);
				}
			}
		}
		if ($this->echooutput !== false && $relcount > 0) {
			echo "\n";
		}

		return $relcount;
	}

	public function processReleasesStage1($groupID)
	{
		require_once(WWW_DIR . "/lib/binaries.php");

		$db = new DB;
		$currTime_ori = $db->queryOneRow("SELECT NOW() as now");
		$cat = new Category();
		$nzb = new Enzebe();
		$s = new Sites();
		$releaseRegex = new ReleaseRegex();
		$page = new Page();
		$groups = new Groups;
		$retcount = 0;

		echo $s->getLicense();

		echo "\n\nStarting release update process (" . date("Y-m-d H:i:s") . ")\n";

		if (!file_exists($page->site->nzbpath)) {
			echo "Bad or missing nzb directory - " . $page->site->nzbpath;

			return -1;
		}

		$this->checkRegexesUptoDate($page->site->latestregexurl, $page->site->latestregexrevision, $page->site->newznabID);

		//
		// Get all regexes for all groups which are to be applied to new binaries
		// in order of how they should be applied
		//
		$releaseRegex->get();
		echo "Stage 1 : Applying regex to binaries\n";
		$activeGroups = $groups->getActive(false);
		foreach ($activeGroups as $groupArr) {
			//check if regexes have already been applied during update binaries
			if ($groupArr['regexmatchonly'] == 1)
				continue;

			$groupRegexes = $releaseRegex->getForGroup($groupArr['name']);

			echo "Stage 1 : Applying " . sizeof($groupRegexes) . " regexes to group " . $groupArr['name'] . "\n";

			// Get out all binaries of STAGE0 for current group
			$newUnmatchedBinaries = array();
			$ressql = sprintf("SELECT binaries.ID, binaries.name, binaries.date, binaries.totalParts, binaries.procstat, binaries.fromname from binaries where groupID = %d and procstat IN (%d,%d) and regexID IS NULL order by binaries.date asc", $groupArr['ID'], Releases::PROCSTAT_NEW, Releases::PROCSTAT_TITLENOTMATCHED);
			$resbin = $db->queryDirect($ressql);

			$matchedbins = 0;
			while ($rowbin = $db->getAssocArray($resbin)) {
				$regexMatches = array();
				foreach ($groupRegexes as $groupRegex) {
					$regexCheck = $releaseRegex->performMatch($groupRegex, $rowbin['name']);
					if ($regexCheck !== false) {
						$regexMatches = $regexCheck;
						break;
					}
				}

				if (!empty($regexMatches)) {
					$matchedbins++;
					$relparts = explode("/", $regexMatches['parts']);
					$db->exec(sprintf("update binaries set relname = replace(%s, '_', ' '), relpart = %d, reltotalpart = %d, procstat=%d, categoryID=%s, regexID=%d, reqID=%s where ID = %d",
							$db->escapeString($regexMatches['name']), $relparts[0], $relparts[1], Releases::PROCSTAT_TITLEMATCHED, $regexMatches['regcatid'], $regexMatches['regexID'], $db->escapeString($regexMatches['reqID']), $rowbin["ID"]
						)
					);
				} else {
					if ($rowbin['procstat'] == Releases::PROCSTAT_NEW)
						$newUnmatchedBinaries[] = $rowbin['ID'];
				}

			}

			//mark as not matched
			if (!empty($newUnmatchedBinaries))
				$db->exec(sprintf("update binaries set procstat=%d where ID IN (%s)", Releases::PROCSTAT_TITLENOTMATCHED, implode(',', $newUnmatchedBinaries)));

		}
	}

	public function processReleasesStage2($groupID)
	{
		//
		// Move all binaries from releases which have the correct number of files on to the next stage.
		//
		echo "Stage 2 : Marking binaries where all parts are available";
		$result = $db->queryDirect(sprintf("SELECT relname, date, SUM(reltotalpart) AS reltotalpart, groupID, reqID, fromname, SUM(num) AS num, coalesce(g.minfilestoformrelease, s.minfilestoformrelease) as minfilestoformrelease FROM   ( SELECT relname, reltotalpart, groupID, reqID, fromname, max(date) as date, COUNT(ID) AS num FROM binaries     WHERE procstat = %s     GROUP BY relname, reltotalpart, groupID, reqID, fromname ORDER BY NULL ) x left outer join groups g on g.ID = x.groupID inner join ( select value as minfilestoformrelease from site where setting = 'minfilestoformrelease' ) s GROUP BY relname, groupID, reqID, fromname, minfilestoformrelease ORDER BY NULL", Releases::PROCSTAT_TITLEMATCHED));

		while ($row = $db->getAssocArray($result)) {
			$retcount++;

			//
			// Less than the site permitted number of files in a release. Dont discard it, as it may
			// be part of a set being uploaded.
			//
			if ($row["num"] < $row["minfilestoformrelease"]) {
				//echo "Number of files in release ".$row["relname"]." less than site/group setting (".$row['num']."/".$row["minfilestoformrelease"].")\n";
				$db->exec(sprintf("update binaries set procattempts = procattempts + 1 where relname = %s and procstat = %d and groupID = %d and fromname = %s", $db->escapeString($row["relname"]), Releases::PROCSTAT_TITLEMATCHED, $row["groupID"], $db->escapeString($row["fromname"])));
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
					$binlist = $db->query(sprintf("SELECT binaries.ID, totalParts, date, COUNT(DISTINCT parts.messageID) AS num FROM binaries, parts WHERE binaries.ID=parts.binaryID AND binaries.relname = %s AND binaries.procstat = %d AND binaries.groupID = %d AND binaries.fromname = %s GROUP BY binaries.ID ORDER BY NULL", $db->escapeString($row["relname"]), Releases::PROCSTAT_TITLEMATCHED, $row["groupID"], $db->escapeString($row["fromname"])));

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
						$binGroup = $db->queryOneRow(sprintf("SELECT name FROM groups WHERE ID = %d", $row["groupID"]));
						$newtitle = $this->getReleaseNameForReqId($page->site->reqidurl, $page->site->newznabID, $binGroup["name"], $row["reqID"]);

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
							$db->exec(sprintf("update binaries set relname = %s, procstat=%d where relname = %s and procstat = %d and groupID = %d and fromname=%s",
									$db->escapeString($newtitle), Releases::PROCSTAT_READYTORELEASE, $db->escapeString($row["relname"]), Releases::PROCSTAT_TITLEMATCHED, $row["groupID"], $db->escapeString($row["fromname"])
								)
							);
						} else {
							//
							// Item not found, if the binary was added to the index yages ago, then give up.
							//
							$maxaddeddate = $db->queryOneRow(sprintf("SELECT NOW() as now, MAX(dateadded) as dateadded FROM binaries WHERE relname = %s and procstat = %d and groupID = %d and fromname=%s",
									$db->escapeString($row["relname"]), Releases::PROCSTAT_TITLEMATCHED, $row["groupID"], $db->escapeString($row["fromname"])
								)
							);

							//
							// If added to the index over 48 hours ago, give up trying to determine the title
							//
							if (strtotime($maxaddeddate['now']) - strtotime($maxaddeddate['dateadded']) > (60 * 60 * 48)) {
								$db->exec(sprintf("update binaries set procstat=%d where relname = %s and procstat = %d and groupID = %d and fromname=%s",
										Releases::PROCSTAT_NOREQIDNAMELOOKUPFOUND, $db->escapeString($row["relname"]), Releases::PROCSTAT_TITLEMATCHED, $row["groupID"], $db->escapeString($row["fromname"])
									)
								);
							}
						}
					} else {
						$db->exec(sprintf("update binaries set procstat=%d where relname = %s and procstat = %d and groupID = %d and fromname=%s",
								Releases::PROCSTAT_READYTORELEASE, $db->escapeString($row["relname"]), Releases::PROCSTAT_TITLEMATCHED, $row["groupID"], $db->escapeString($row["fromname"])
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
				//$db->exec(sprintf("update binaries set procattempts = procattempts + 1 where relname = %s and procstat = %d and groupID = %d and fromname=%s", $db->escapeString($row["relname"]), Releases::PROCSTAT_TITLEMATCHED, $row["groupID"], $db->escapeString($row["fromname"]) ));
			}
			if ($retcount % 100 == 0)
				echo ".";
		}

		$retcount = 0;
	}

	public function processReleasesStage3($groupID)
	{
		$minsizecounts = $maxsizecounts = $minfilecounts = 0;

		// Set table names
		if ($this->tablepergroup === 1) {
			if ($groupID == '') {
				exit($this->c->error("\nYou are using 'tablepergroup', you must use releases_threaded.py"));
			}
			$group['cname'] = 'collections_' . $groupID;
			$group['bname'] = 'binaries_' . $groupID;
			$group['pname'] = 'parts_' . $groupID;
		} else {
			$group['cname'] = 'collections';
			$group['bname'] = 'binaries';
			$group['pname'] = 'parts';
		}

		if ($this->echooutput) {
			$this->c->doEcho($this->c->header("Stage 3 -> Creating releases from complete binaries."));
		}
		$stage3 = TIME();

		if ($groupID == '') {
			$groupIDs = $this->groups->getActiveIDs();
			foreach ($groupIDs as $groupID) {
				$res = $this->db->query(
					'SELECT ID FROM ' . $group['cname'] . ' WHERE filecheck = 3 AND filesize > 0 AND groupID = ' .
					$groupID['ID']
				);
				if (count($res) > 0) {
					$minsizecount = 0;
					if (DB_TYPE === 'mysql') {
						$mscq = $this->db->exec(
							"UPDATE " . $group['cname'] .
							" c LEFT JOIN (SELECT g.ID, COALESCE(g.minsizetoformrelease, s.minsizetoformrelease) AS minsizetoformrelease FROM groups g INNER JOIN ( SELECT value AS minsizetoformrelease FROM settings WHERE setting = 'minsizetoformrelease' ) s ) g ON g.ID = c.groupID SET c.filecheck = 5 WHERE g.minsizetoformrelease != 0 AND c.filecheck = 3 AND c.filesize < g.minsizetoformrelease AND c.filesize > 0 AND groupID = " .
							$groupID['ID']
						);
						if ($mscq !== false) {
							$minsizecount = $mscq->rowCount();
						}
					} else {
						$s = $this->db->queryOneRow(
							"SELECT GREATEST(s.value::integer, g.minsizetoformrelease::integer) as size FROM settings s, groups g WHERE s.setting = 'minsizetoformrelease' AND g.ID = " .
							$groupID['ID']
						);
						if ($s['size'] > 0) {
							$mscq = $this->db->exec(
								sprintf(
									'UPDATE ' . $group['cname'] .
									' SET filecheck = 5 WHERE filecheck = 3 AND filesize < %d AND filesize > 0 AND groupID = ' .
									$groupID['ID'], $s['size']
								)
							);
							if ($mscq !== false) {
								$minsizecount = $mscq->rowCount();
							}
						}
					}
					if ($minsizecount < 1) {
						$minsizecount = 0;
					}
					$minsizecounts = $minsizecount + $minsizecounts;

					$maxfilesizeres = $this->db->queryOneRow("SELECT value FROM settings WHERE setting = 'maxsizetoformrelease'");
					if ($maxfilesizeres['value'] != 0) {
						$mascq = $this->db->exec(
							sprintf(
								'UPDATE ' . $group['cname'] .
								' SET filecheck = 5 WHERE filecheck = 3 AND groupID = %d AND filesize > %d ', $groupID['ID'], $maxfilesizeres['value']
							)
						);
						$maxsizecount = 0;
						if ($mascq !== false) {
							$maxsizecount = $mascq->rowCount();
						}
						if ($maxsizecount < 1) {
							$maxsizecount = 0;
						}
						$maxsizecounts = $maxsizecount + $maxsizecounts;
					}

					$minfilecount = 0;
					if (DB_TYPE === 'mysql') {
						$mifcq = $this->db->exec(
							"UPDATE " . $group['cname'] .
							" c LEFT JOIN (SELECT g.ID, COALESCE(g.minfilestoformrelease, s.minfilestoformrelease) AS minfilestoformrelease FROM groups g INNER JOIN ( SELECT value AS minfilestoformrelease FROM settings WHERE setting = 'minfilestoformrelease' ) s ) g ON g.ID = c.groupID SET c.filecheck = 5 WHERE g.minfilestoformrelease != 0 AND c.filecheck = 3 AND c.totalfiles < g.minfilestoformrelease AND groupID = " .
							$groupID['ID']
						);
						if ($mifcq !== false) {
							$minfilecount = $mifcq->rowCount();
						}
					} else {
						$f = $this->db->queryOneRow(
							"SELECT GREATEST(s.value::integer, g.minfilestoformrelease::integer) as files FROM settings s, groups g WHERE s.setting = 'minfilestoformrelease' AND g.ID = " .
							$groupID['ID']
						);
						if ($f['files'] > 0) {
							$mifcq = $this->db->exec(
								sprintf(
									'UPDATE ' . $group['cname'] .
									' SET filecheck = 5 WHERE filecheck = 3 AND filesize < %d AND filesize > 0 AND groupID = ' .
									$groupID['ID'], $s['size']
								)
							);
							if ($mifcq !== false) {
								$minfilecount = $mifcq->rowCount();
							}
						}
					}
					if ($minfilecount < 1) {
						$minfilecount = 0;
					}
					$minfilecounts = $minfilecount + $minfilecounts;
				}
			}
		} else {
			$res = $this->db->queryDirect(
				'SELECT ID FROM ' . $group['cname'] . ' WHERE filecheck = 3 AND filesize > 0 AND groupID = ' . $groupID
			);
			if ($res !== false && $res->rowCount() > 0) {
				$minsizecount = 0;
				if (DB_TYPE === 'mysql') {
					$mscq = $this->db->exec(
						"UPDATE " . $group['cname'] .
						" c LEFT JOIN (SELECT g.ID, coalesce(g.minsizetoformrelease, s.minsizetoformrelease) AS minsizetoformrelease FROM groups g INNER JOIN ( SELECT value AS minsizetoformrelease FROM settings WHERE setting = 'minsizetoformrelease' ) s ) g ON g.ID = c.groupID SET c.filecheck = 5 WHERE g.minsizetoformrelease != 0 AND c.filecheck = 3 AND c.filesize < g.minsizetoformrelease AND c.filesize > 0 AND groupID = " .
						$groupID
					);
					if ($mscq !== false) {
						$minsizecount = $mscq->rowCount();
					}
				} else {
					$s = $this->db->queryOneRow(
						"SELECT GREATEST(s.value::integer, g.minsizetoformrelease::integer) as size FROM settings s, groups g WHERE s.setting = 'minsizetoformrelease' AND g.ID = " .
						$groupID
					);
					if ($s['size'] > 0) {
						$mscq = $this->db->exec(
							sprintf(
								'UPDATE ' . $group['cname'] .
								' SET filecheck = 5 WHERE filecheck = 3 AND filesize < %d AND filesize > 0 AND groupID = ' .
								$groupID, $s['size']
							)
						);
						if ($mscq !== false) {
							$minsizecount = $mscq->rowCount();
						}
					}
				}
				if ($minsizecount < 0) {
					$minsizecount = 0;
				}
				$minsizecounts = $minsizecount + $minsizecounts;

				$maxfilesizeres = $this->db->queryOneRow("SELECT value FROM settings WHERE setting = 'maxsizetoformrelease'");
				if ($maxfilesizeres['value'] != 0) {
					$mascq = $this->db->exec(
						sprintf(
							'UPDATE ' . $group['cname'] .
							' SET filecheck = 5 WHERE filecheck = 3 AND filesize > %d ', $maxfilesizeres['value']
						)
					);
					if ($mascq !== false) {
						$maxsizecount = $mascq->rowCount();
					}
					if ($maxsizecount < 0) {
						$maxsizecount = 0;
					}
					$maxsizecounts = $maxsizecount + $maxsizecounts;
				}

				$minfilecount = 0;
				if (DB_TYPE === 'mysql') {
					$mifcq = $this->db->exec(
						"UPDATE " . $group['cname'] .
						" c LEFT JOIN (SELECT g.ID, coalesce(g.minfilestoformrelease, s.minfilestoformrelease) AS minfilestoformrelease FROM groups g INNER JOIN ( SELECT value AS minfilestoformrelease FROM settings WHERE setting = 'minfilestoformrelease' ) s ) g ON g.ID = c.groupID SET c.filecheck = 5 WHERE g.minfilestoformrelease != 0 AND c.filecheck = 3 AND c.totalfiles < g.minfilestoformrelease AND groupID = " .
						$groupID
					);
					if ($mifcq !== false) {
						$minfilecount = $mifcq->rowCount();
					}
				} else {
					$f = $this->db->queryOneRow(
						"SELECT GREATEST(s.value::integer, g.minfilestoformrelease::integer) as files FROM settings s, groups g WHERE s.setting = 'minfilestoformrelease' AND g.ID = " .
						$groupID
					);
					if ($f['files'] > 0) {
						$mifcq = $this->db->exec(
							sprintf(
								'UPDATE ' . $group['cname'] .
								' SET filecheck = 5 WHERE filecheck = 3 AND filesize < %d AND filesize > 0 AND groupID = ' .
								$groupID, $s['size']
							)
						);
						if ($mifcq !== false) {
							$minfilecount = $mifcq->rowCount();
						}
					}
				}
				if ($minfilecount < 0) {
					$minfilecount = 0;
				}
				$minfilecounts = $minfilecount + $minfilecounts;
			}
		}

		$delcount = $minsizecounts + $maxsizecounts + $minfilecounts;
		if ($this->echooutput && $delcount > 0) {
			$this->c->doEcho(
				$this->c->primary(
					'Deleted ' .
					number_format($delcount) .
					" collections smaller/larger than group/site settings."
				)
			);
		}
		if ($this->echooutput) {
			$this->c->doEcho($this->c->primary($this->consoleTools->convertTime(TIME() - $stage3)), true);
		}
	}

	public function processReleasesStage4($groupID)
	{
		$categorize = new Category();
		$retcount = $duplicate = 0;
		$where = (!empty($groupID)) ? ' groupID = ' . $groupID . ' AND ' : ' ';

		// Set table names
		if ($this->tablepergroup === 1) {
			if ($groupID == '') {
				exit($this->c->error("\nYou are using 'tablepergroup', you must use releases_threaded.py"));
			}
			$group['cname'] = 'collections_' . $groupID;
			$group['bname'] = 'binaries_' . $groupID;
			$group['pname'] = 'parts_' . $groupID;
		} else {
			$group['cname'] = 'collections';
			$group['bname'] = 'binaries';
			$group['pname'] = 'parts';
		}

		if ($this->echooutput) {
			$this->c->doEcho($this->c->header("Stage 4 -> Create releases."));
		}
		$stage4 = TIME();
		$rescol = $this->db->queryDirect(
			'SELECT ' . $group['cname'] . '.*, groups.name AS gname FROM ' . $group['cname'] .
			' INNER JOIN groups ON ' . $group['cname'] . '.groupID = groups.ID WHERE' . $where .
			'filecheck = 3 AND filesize > 0 LIMIT ' . $this->stage5limit
		);
		if ($rescol !== false && $this->echooutput) {
			echo $this->c->primary($rescol->rowCount() . " Collections ready to be converted to releases.");
		}

		if ($rescol !== false && $rescol->rowCount() > 0) {
			$predb = new PreDb($this->echooutput);
			foreach ($rescol as $rowcol) {
				$propername = true;
				$relid = false;
				$cleanRelName = str_replace(array('#', '@', '$', '%', '^', '§', '¨', '©', 'Ö'), '', $rowcol['subject']);
				$cleanerName = $this->releaseCleaning->releaseCleaner($rowcol['subject'], $rowcol['fromname'], $rowcol['filesize'], $rowcol['gname']);
				/* $ncarr = $this->collectionsCleaning->collectionsCleaner($subject, $rowcol['gname']);
				  $cleanerName = $ncarr['subject'];
				  $category = $ncarr['cat'];
				  $relstat = $ncar['rstatus']; */
				$fromname = trim($rowcol['fromname'], "'");
				if (!is_array($cleanerName)) {
					$cleanName = $cleanerName;
				} else {
					$cleanName = $cleanerName['cleansubject'];
					$propername = $cleanerName['properlynamed'];
				}
				$relguid = sha1(uniqid('', true) . mt_rand());

				$category = $categorize->determineCategory($cleanName, $rowcol['groupID']);
				$cleanRelName = utf8_encode($cleanRelName);
				$cleanName = utf8_encode($cleanName);
				$fromname = utf8_encode($fromname);

				// Look for duplicates, duplicates match on releases.name, releases.fromname and releases.size
				// A 1% variance in size is considered the same size when the subject and poster are the same
				$minsize = $rowcol['filesize'] * .99;
				$maxsize = $rowcol['filesize'] * 1.01;

				$dupecheck = $this->db->queryOneRow(sprintf('SELECT ID, guid FROM releases WHERE name = %s AND fromname = %s AND size BETWEEN %s AND %s', $this->db->escapeString($cleanRelName), $this->db->escapeString($fromname), $this->db->escapeString($minsize), $this->db->escapeString($maxsize)));
				if (!$dupecheck) {
					if ($propername == true) {
						$relid = $this->db->queryInsert(
							sprintf(
								'INSERT INTO releases
									(name, searchname, totalpart, groupID, adddate, guid, rageID, postdate, fromname,
									size, passwordstatus, haspreview, categoryID, nfostatus, isrenamed, iscategorized)
								VALUES
									(%s, %s, %d, %d, NOW(), %s, -1, %s, %s, %s, %d, -1, %d, -1, 1, 1)',
								$this->db->escapeString($cleanRelName),
								$this->db->escapeString($cleanName),
								$rowcol['totalfiles'],
								$rowcol['groupID'],
								$this->db->escapeString($relguid),
								$this->db->escapeString($rowcol['date']),
								$this->db->escapeString($fromname),
								$this->db->escapeString($rowcol['filesize']),
								($this->site->checkpasswordedrar == '1' ? -1 : 0),
								$category
							)
						);
					} else {
						$relid = $this->db->queryInsert(
							sprintf(
								'INSERT INTO releases
									(name, searchname, totalpart, groupID, adddate, guid, rageID, postdate, fromname,
									size, passwordstatus, haspreview, categoryID, nfostatus, iscategorized)
								VALUES (%s, %s, %d, %d, NOW(), %s, -1, %s, %s, %s, %d, -1, %d, -1, 1)',
								$this->db->escapeString($cleanRelName),
								$this->db->escapeString($cleanName),
								$rowcol['totalfiles'],
								$rowcol['groupID'],
								$this->db->escapeString($relguid),
								$this->db->escapeString($rowcol['date']),
								$this->db->escapeString($fromname),
								$this->db->escapeString($rowcol['filesize']),
								($this->site->checkpasswordedrar == '1' ? -1 : 0),
								$category
							)
						);
					}
				}

				if ($relid) {
					// try to match to predb here
					$predb->matchPre($cleanRelName, $relid);

					// Update collections table to say we inserted the release.
					$this->db->exec(
						sprintf(
							'UPDATE ' . $group['cname'] .
							' SET filecheck = 4, releaseID = %d WHERE ID = %d', $relid, $rowcol['ID']
						)
					);
					$retcount++;
					if ($this->echooutput) {
						echo $this->c->primary('Added release ' . $cleanName);
					}
				} else if (isset($relid) && $relid == false) {
					$this->db->exec(
						sprintf(
							'UPDATE ' . $group['cname'] .
							' SET filecheck = 5 WHERE collectionhash = %s', $this->db->escapeString($rowcol['collectionhash'])
						)
					);
					$duplicate++;
				}
			}
		}

		if ($this->echooutput) {
			$this->c->doEcho(
				$this->c->primary(
					number_format($retcount) .
					' Releases added and ' .
					number_format($duplicate) .
					' marked for deletion in ' .
					$this->consoleTools->convertTime(TIME() - $stage4)
				), true
			);
		}

		return $retcount;
	}

	/*
	 * 	Adding this in to delete releases before NZB's are created.
	 */

	public function processReleasesStage4dot5($groupID)
	{
		$minsizecount = $maxsizecount = $minfilecount = $catminsizecount = 0;

		if ($this->echooutput) {
			echo $this->c->header("Stage 4.5 -> Delete releases smaller/larger than minimum size/file count from group/site setting.");
		}

		$stage4dot5 = TIME();
		// Delete smaller than min sizes
		$catresrel = $this->db->queryDirect('SELECT c.ID AS id, CASE WHEN c.minsize = 0 THEN cp.minsize ELSE c.minsize END AS minsize FROM category c INNER JOIN category cp ON cp.ID = c.parentID WHERE c.parentID IS NOT NULL');
		foreach ($catresrel as $catrowrel) {
			if ($catrowrel['minsize'] > 0) {
				//printf("SELECT r.id, r.guid FROM releases r WHERE r.categoryID = %d AND r.size < %d\n", $catrowrel['ID'], $catrowrel['minsize']);
				$resrel = $this->db->queryDirect(sprintf('SELECT r.id, r.guid FROM releases r WHERE r.categoryID = %d AND r.size < %d', $catrowrel['ID'], $catrowrel['minsize']));
				foreach ($resrel as $rowrel) {
					$this->fastDelete($rowrel['ID'], $rowrel['guid']);
					$catminsizecount++;
				}
			}
		}

		// Delete larger than max sizes
		if ($groupID == '') {
			$groupIDs = $this->groups->getActiveIDs();

			foreach ($groupIDs as $groupID) {
				if (DB_TYPE === 'mysql') {
					$resrel = $this->db->queryDirect(sprintf("SELECT r.id, r.guid FROM releases r LEFT JOIN (SELECT g.ID, coalesce(g.minsizetoformrelease, s.minsizetoformrelease) AS minsizetoformrelease FROM groups g INNER JOIN ( SELECT value as minsizetoformrelease FROM settings WHERE setting = 'minsizetoformrelease' ) s WHERE g.ID = %s ) g ON g.ID = r.groupID WHERE g.minsizetoformrelease != 0 AND r.size < minsizetoformrelease AND r.groupID = %s", $groupID['ID'], $groupID['ID']));
				} else {
					$resrel = array();
					$s = $this->db->queryOneRow(
						"SELECT GREATEST(s.value::integer, g.minsizetoformrelease::integer) as size FROM settings s, groups g WHERE s.setting = 'minsizetoformrelease' AND g.ID = " .
						$groupID['ID']
					);
					if ($s['size'] > 0) {
						$resrel = $this->db->queryDirect(sprintf('SELECT ID, guid FROM releases WHERE size < %d AND groupID = %d', $s['size'], $groupID['ID']));
					}
				}
				if ($resrel !== false && $resrel->rowCount() > 0) {
					foreach ($resrel as $rowrel) {
						$this->fastDelete($rowrel['ID'], $rowrel['guid']);
						$minsizecount++;
					}
				}

				$maxfilesizeres = $this->db->queryOneRow("SELECT value FROM settings WHERE setting = 'maxsizetoformrelease'");
				if ($maxfilesizeres['value'] != 0) {
					$resrel = $this->db->queryDirect(sprintf('SELECT ID, guid FROM releases WHERE groupID = %d AND size > %d', $groupID['ID'], $maxfilesizeres['value']));
					if ($resrel !== false && $resrel->rowCount() > 0) {
						foreach ($resrel as $rowrel) {
							$this->fastDelete($rowrel['ID'], $rowrel['guid']);
							$maxsizecount++;
						}
					}
				}

				if (DB_TYPE === 'mysql') {
					$resrel = $this->db->queryDirect(sprintf("SELECT r.id, r.guid FROM releases r LEFT JOIN (SELECT g.ID, coalesce(g.minfilestoformrelease, s.minfilestoformrelease) as minfilestoformrelease FROM groups g INNER JOIN ( SELECT value as minfilestoformrelease FROM settings WHERE setting = 'minfilestoformrelease' ) s WHERE g.ID = %d ) g ON g.ID = r.groupID WHERE g.minfilestoformrelease != 0 AND r.totalpart < minfilestoformrelease AND r.groupID = %d", $groupID['ID'], $groupID['ID']));
				} else {
					$resrel = array();
					$f = $this->db->queryOneRow(
						"SELECT GREATEST(s.value::integer, g.minfilestoformrelease::integer) as files FROM settings s, groups g WHERE s.setting = 'minfilestoformrelease' AND g.ID = " .
						$groupID['ID']
					);
					if ($f['files'] > 0) {
						$resrel = $this->db->queryDirect(sprintf('SELECT ID, guid FROM releases WHERE totalpart < %d AND groupID = %d', $f['files'], $groupID['ID']));
					}
				}
				if ($resrel !== false && $resrel->rowCount() > 0) {
					foreach ($resrel as $rowrel) {
						$this->fastDelete($rowrel['ID'], $rowrel['guid']);
						$minfilecount++;
					}
				}
			}
		} else {
			if (DB_TYPE === 'mysql') {
				$resrel = $this->db->queryDirect(sprintf("SELECT r.id, r.guid FROM releases r LEFT JOIN (SELECT g.ID, coalesce(g.minsizetoformrelease, s.minsizetoformrelease) AS minsizetoformrelease FROM groups g INNER JOIN ( SELECT value AS minsizetoformrelease FROM settings WHERE setting = 'minsizetoformrelease' ) s WHERE g.ID = %d ) g ON g.ID = r.groupID WHERE g.minsizetoformrelease != 0 AND r.size < minsizetoformrelease AND r.groupID = %d", $groupID, $groupID));
			} else {
				$resrel = array();
				$s = $this->db->queryOneRow(
					"SELECT GREATEST(s.value::integer, g.minsizetoformrelease::integer) as size FROM settings s, groups g WHERE s.setting = 'minsizetoformrelease' AND g.ID = " .
					$groupID
				);
				if ($s['size'] > 0) {
					$resrel = $this->db->queryDirect(sprintf('SELECT ID, guid FROM releases WHERE size < %d AND groupID = %d', $s['size'], $groupID));
				}
			}
			if ($resrel !== false && $resrel->rowCount() > 0) {
				foreach ($resrel as $rowrel) {
					$this->fastDelete($rowrel['ID'], $rowrel['guid']);
					$minsizecount++;
				}
			}

			$maxfilesizeres = $this->db->queryOneRow("SELECT value FROM settings WHERE setting = 'maxsizetoformrelease'");
			if ($maxfilesizeres['value'] != 0) {
				$resrel = $this->db->queryDirect(sprintf('SELECT ID, guid FROM releases WHERE groupID = %d AND size > %s', $groupID, $this->db->escapeString($maxfilesizeres['value'])));
				if ($resrel !== false && $resrel->rowCount() > 0) {
					foreach ($resrel as $rowrel) {
						$this->fastDelete($rowrel['ID'], $rowrel['guid']);
						$maxsizecount++;
					}
				}
			}

			if (DB_TYPE === 'mysql') {
				$resrel = $this->db->queryDirect(sprintf("SELECT r.id, r.guid FROM releases r LEFT JOIN (SELECT g.ID, coalesce(g.minfilestoformrelease, s.minfilestoformrelease) AS minfilestoformrelease FROM groups g INNER JOIN ( SELECT value AS minfilestoformrelease FROM settings WHERE setting = 'minfilestoformrelease' ) s WHERE g.ID = %d ) g ON g.ID = r.groupID WHERE g.minfilestoformrelease != 0 AND r.totalpart < minfilestoformrelease AND r.groupID = %d", $groupID, $groupID));
			} else {
				$resrel = array();
				$f = $this->db->queryOneRow(
					"SELECT GREATEST(s.value::integer, g.minfilestoformrelease::integer) as files FROM settings s, groups g WHERE s.setting = 'minfilestoformrelease' AND g.ID = " .
					$groupID
				);
				if ($f['files'] > 0) {
					$resrel = $this->db->queryDirect(sprintf('SELECT ID, guid FROM releases WHERE totalpart < %d AND groupID = %d', $f['files'], $groupID));
				}
			}
			if ($resrel !== false && $resrel->rowCount() > 0) {
				foreach ($resrel as $rowrel) {
					$this->fastDelete($rowrel['ID'], $rowrel['guid']);
					$minfilecount++;
				}
			}
		}

		$delcount = $minsizecount + $maxsizecount + $minfilecount + $catminsizecount;
		if ($this->echooutput && $delcount > 0) {
			$this->c->doEcho(
				$this->c->primary(
					'Deleted ' .
					number_format($delcount) .
					" releases smaller/larger than group/site settings."
				)
			);
		}
		if ($this->echooutput) {
			$this->c->doEcho($this->c->primary($this->consoleTools->convertTime(TIME() - $stage4dot5)), true);
		}
	}

	public function processReleasesStage5($groupID)
	{
		$nzbcount = $reccount = 0;
		$where = (!empty($groupID)) ? ' r.groupID = ' . $groupID . ' AND ' : ' ';

		// Set table names
		if ($this->tablepergroup === 1) {
			if ($groupID == '') {
				exit($this->c->error("\nYou are using 'tablepergroup', you must use releases_threaded.py"));
			}
			$group['cname'] = 'collections_' . $groupID;
			$group['bname'] = 'binaries_' . $groupID;
			$group['pname'] = 'parts_' . $groupID;
		} else {
			$group['cname'] = 'collections';
			$group['bname'] = 'binaries';
			$group['pname'] = 'parts';
		}

		// Create NZB.
		if ($this->echooutput) {
			$this->c->doEcho($this->c->header("Stage 5 -> Create the NZB, mark collections as ready for deletion."));
		}

		$stage5 = TIME();
		$resrel = $this->db->queryDirect(
			"SELECT CONCAT(COALESCE(cp.title,'') , CASE WHEN cp.title IS NULL THEN '' ELSE ' > ' END , c.title) AS title, r.name, r.id, r.guid FROM releases r INNER JOIN category c ON r.categoryID = c.ID INNER JOIN category cp ON cp.ID = c.parentID WHERE" .
			$where . "nzbstatus = 0"
		);
		$total = 0;
		if ($resrel !== false) {
			$total = $resrel->rowCount();
		}
		if ($total > 0) {
			$nzb = new Enzebe();
			// Init vars for writing the NZB's.
			$nzb->initiateForWrite($this->db, htmlspecialchars(date('F j, Y, g:i a O'), ENT_QUOTES, 'utf-8'), $groupID);
			foreach ($resrel as $rowrel) {
				$nzb_create = $nzb->writeNZBforreleaseID($rowrel['ID'], $rowrel['guid'], $rowrel['name'], $rowrel['title']);
				if ($nzb_create !== false) {
					$this->db->exec(
						sprintf(
							'UPDATE %s SET filecheck = 5 WHERE releaseID = %s', $group['cname'], $rowrel['ID']
						)
					);
					$nzbcount++;
					if ($this->echooutput) {
						echo $this->consoleTools->overWritePrimary(
							'Creating NZBs: ' . $this->consoleTools->percentString($nzbcount, $total)
						);
					}
				}
			}
			// Reset vars for next use.
			$nzb->cleanForWrite();
		}

		$timing = $this->c->primary($this->consoleTools->convertTime(TIME() - $stage5));
		if ($this->echooutput) {
			$this->c->doEcho(
				$this->c->primary(
					number_format($nzbcount) .
					' NZBs created in ' .
					$timing
				)
			);
		}

		return $nzbcount;
	}

	/**
	 * Process RequestID's.
	 *
	 * @param int $groupID
	 */
	public function processReleasesStage5b($groupID)
	{
		if ($this->site->lookup_reqids == 1 || $this->site->lookup_reqids == 2) {
			$category = new Category();
			$iFoundCnt = 0;
			$stage8 = TIME();

			if ($this->echooutput) {
				$this->c->doEcho($this->c->header("Stage 5b -> Request ID lookup. "));
			}

			// Look for records that potentially have requestID titles and have not been renamed by any other means
			$resRel = $this->db->queryDirect(
				sprintf("
					SELECT r.id, r.name, r.searchname, g.name AS groupname
					FROM releases r
					LEFT JOIN groups g ON r.groupID = g.ID
					WHERE r.groupID = %d
					AND  nzbstatus = 1
					AND isrenamed = 0
					AND (isrequestid = 1 AND reqidstatus in (%d, %d) OR (reqidstatus = %d AND adddate > NOW() - INTERVAL %d HOUR))
					LIMIT 100",
					$groupID,
					self::REQID_UPROC,
					self::REQID_BAD,
					self::REQID_NONE,
					(isset($this->site->request_hours) ? (int)$this->site->request_hours : 1)
				)
			);

			if ($resRel !== false && $resRel->rowCount() > 0) {
				$newTitle = false;
				$web = (!empty($this->site->request_url) &&
					(nzedb\utility\getUrl($this->site->request_url) === false ? false : true));

				foreach ($resRel as $rowRel) {
					$newTitle = $local = false;

					// Try to get request id.
					if (preg_match('/\[\s*(\d+)\s*\]/', $rowRel['name'], $requestID)) {
						$requestID = (int)$requestID[1];
					} else {
						$requestID = 0;
					}

					if ($requestID === 0) {
						$this->db->exec(
							sprintf('
								UPDATE releases
								SET reqidstatus = %d
								WHERE ID = %d',
								self::REQID_ZERO,
								$rowRel['ID']
							)
						);
					} else {

						// Do a local lookup first.
						$run = $this->db->queryOneRow(
							sprintf("
								SELECT title
								FROM predb
								WHERE requestid = %d
								AND groupID = %d",
								$requestID, $groupID
							)
						);
						if ($run !== false) {
							$newTitle = $run['title'];
							$local = true;
							$iFoundCnt++;

							// Do a web lookup.
						} else if ($web !== false) {
							$xml = @simplexml_load_file(
								str_ireplace(
									'[REQUEST_ID]',
									$requestID,
									str_ireplace(
										'[GROUP_NM]',
										urlencode($rowRel['groupname']),
										$this->site->request_url
									)
								)
							);
							if ($xml !== false &&
								isset($xml->request[0]['name']) && !empty($xml->request[0]['name']) &&
								strtolower($xml->request[0]['name']) !== strtolower($rowRel['searchname'])
							) {
								$newTitle = $xml->request[0]['name'];
								$iFoundCnt++;
							}
						}
					}

					if ($newTitle !== false) {

						$determinedCat = $category->determineCategory($newTitle, $groupID);
						$this->db->exec(
							sprintf('
								UPDATE releases
								SET reqidstatus = %d, isrenamed = 1, proc_files = 1, searchname = %s, categoryID = %d
								WHERE ID = %d',
								self::REQID_FOUND,
								$this->db->escapeString($newTitle),
								$determinedCat,
								$rowRel['ID']
							)
						);

						if ($this->echooutput) {
							echo $this->c->primary(
								"\n\nNew name:  $newTitle" .
								"\nOld name:  " . $rowRel['searchname'] .
								"\nNew cat:   " . $category->getNameByID($determinedCat) .
								"\nGroup:     " . $rowRel['groupname'] .
								"\nMethod:    " . ($local === true ? 'requestID local' : 'requestID web') .
								"\nreleaseID: " . $rowRel['ID']
							);
						}
					} else {
						$this->db->exec(
							sprintf(
								'UPDATE releases SET reqidstatus = %d WHERE ID = %d',
								self::REQID_NONE,
								$rowRel['ID']
							)
						);
					}
				}
				if ($this->echooutput && $newTitle !== false) {
					echo "\n";
				}
			}

			if ($this->echooutput) {
				$this->c->doEcho(
					$this->c->primary(
						number_format($iFoundCnt) .
						' Releases updated in ' .
						$this->consoleTools->convertTime(TIME() - $stage8)
					), true
				);
			}
		}
	}

	public function processReleasesStage6($categorize, $postproc, $groupID, $nntp)
	{
		$where = (!empty($groupID)) ? 'WHERE iscategorized = 0 AND groupID = ' . $groupID : 'WHERE iscategorized = 0';

		// Categorize releases.
		if ($this->echooutput) {
			echo $this->c->header("Stage 6 -> Categorize and post process releases.");
		}
		$stage6 = TIME();
		if ($categorize == 1) {
			$this->categorizeRelease('name', $where);
		}

		if ($postproc == 1) {
			$postprocess = new PostProcess($this->echooutput);
			$postprocess->processAll($nntp);
		} else {
			if ($this->echooutput) {
				$this->c->doEcho(
					$this->c->info(
						"\nPost-processing is not running inside the releases.php file.\n" .
						"If you are using tmux or screen they might have their own files running Post-processing."
					)
				);
			}
		}
		if ($this->echooutput) {
			$this->c->doEcho($this->c->primary($this->consoleTools->convertTime(TIME() - $stage6)), true);
		}
	}

	public function processReleasesStage7a($groupID)
	{
		$reccount = $delq = 0;
		$where = ' ';
		$where1 = '';

		// Set table names
		if ($this->tablepergroup === 1) {
			if ($groupID == '') {
				exit($this->c->error("\nYou are using 'tablepergroup', you must use releases_threaded.py"));
			}
			$group['cname'] = 'collections_' . $groupID;
			$group['bname'] = 'binaries_' . $groupID;
			$group['pname'] = 'parts_' . $groupID;
		} else {
			$group['cname'] = 'collections';
			$group['bname'] = 'binaries';
			$group['pname'] = 'parts';
			$where = (!empty($groupID)) ? ' ' . $group['cname'] . '.groupID = ' . $groupID . ' AND ' : ' ';
			$where1 = (!empty($groupID)) ? ' AND ' . $group['cname'] . '.groupID = ' . $groupID : '';
		}

		// Delete old releases and finished collections.
		if ($this->echooutput) {
			echo $this->c->header("Stage 7a -> Delete finished collections.");
		}
		$stage7 = TIME();

		// Completed releases and old collections that were missed somehow.
		if (DB_TYPE === 'mysql') {
			$delq = $this->db->exec(
				sprintf(
					'DELETE ' . $group['cname'] . ', ' . $group['bname'] . ', ' . $group['pname'] . ' FROM ' .
					$group['cname'] . ', ' . $group['bname'] . ', ' . $group['pname'] . ' WHERE' . $where .
					$group['cname'] . '.filecheck = 5 AND ' . $group['cname'] . '.id = ' . $group['bname'] .
					'.collectionid AND ' . $group['bname'] . '.id = ' . $group['pname'] . '.binaryid'
				)
			);
			if ($delq !== false) {
				$reccount += $delq->rowCount();
			}
		} else {
			$idr = $this->db->queryDirect('SELECT ID FROM ' . $group['cname'] . ' WHERE filecheck = 5 ' . $where);
			if ($idr !== false && $idr->rowCount() > 0) {
				foreach ($idr as $id) {
					$delqa = $this->db->exec(
						sprintf(
							'DELETE FROM ' . $group['pname'] . ' WHERE EXISTS (SELECT ID FROM ' . $group['bname'] .
							' WHERE ' . $group['bname'] . '.id = ' . $group['pname'] . '.binaryid AND ' .
							$group['bname'] . '.collectionid = %d)', $id['ID']
						)
					);
					if ($delqa !== false) {
						$reccount += $delqa->rowCount();
					}
					$delqb = $this->db->exec(
						sprintf(
							'DELETE FROM ' . $group['bname'] . ' WHERE collectionid = %d', $id['ID']
						)
					);
					if ($delqb !== false) {
						$reccount += $delqb->rowCount();
					}
				}
				$delqc = $this->db->exec('DELETE FROM ' . $group['cname'] . ' WHERE filecheck = 5 ' . $where);
				if ($delqc !== false) {
					$reccount += $delqc->rowCount();
				}
			}
		}

		// Old collections that were missed somehow.
		if (DB_TYPE === 'mysql') {
			$delq = $this->db->exec(
				sprintf(
					'DELETE ' . $group['cname'] . ', ' . $group['bname'] . ', ' . $group['pname'] . ' FROM ' .
					$group['cname'] . ', ' . $group['bname'] . ', ' . $group['pname'] . ' WHERE ' . $group['cname'] .
					'.dateadded < (NOW() - INTERVAL %d HOUR) AND ' . $group['cname'] . '.id = ' . $group['bname'] .
					'.collectionid AND ' . $group['bname'] . '.id = ' . $group['pname'] . '.binaryid' .
					$where1, $this->site->partretentionhours
				)
			);
			if ($delq !== false) {
				$reccount += $delq->rowCount();
			}
		} else {
			$idr = $this->db->queryDirect(
				sprintf(
					"SELECT ID FROM " . $group['cname'] . " WHERE dateadded < (NOW() - INTERVAL '%d HOURS')" .
					$where1, $this->site->partretentionhours
				)
			);

			if ($idr !== false && $idr->rowCount() > 0) {
				foreach ($idr as $id) {
					$delqa = $this->db->exec(
						sprintf(
							'DELETE FROM ' . $group['pname'] . ' WHERE EXISTS (SELECT ID FROM ' . $group['bname'] .
							' WHERE ' . $group['bname'] . '.id = ' . $group['pname'] . '.binaryid AND ' .
							$group['bname'] . '.collectionid = %d)', $id['ID']
						)
					);
					if ($delqa !== false) {
						$reccount += $delqa->rowCount();
					}
					$delqb = $this->db->exec(
						sprintf(
							'DELETE FROM ' . $group['bname'] . ' WHERE collectionid = %d', $id['ID']
						)
					);
					if ($delqb !== false) {
						$reccount += $delqb->rowCount();
					}
				}
			}
			$delqc = $this->db->exec(
				sprintf(
					"DELETE FROM " . $group['cname'] . " WHERE dateadded < (NOW() - INTERVAL '%d HOURS')" .
					$where1, $this->site->partretentionhours
				)
			);
			if ($delqc !== false) {
				$reccount += $delqc->rowCount();
			}
		}

		// Binaries/parts that somehow have no collection.
		if (DB_TYPE === 'mysql') {
			$delqd = $this->db->exec(
				'DELETE ' . $group['bname'] . ', ' . $group['pname'] . ' FROM ' . $group['bname'] . ', ' .
				$group['pname'] . ' WHERE ' . $group['bname'] . '.collectionid = 0 AND ' . $group['bname'] . '.id = ' .
				$group['pname'] . '.binaryid'
			);
			if ($delqd !== false) {
				$reccount += $delqd->rowCount();
			}
		} else {
			$delqe = $this->db->exec(
				'DELETE FROM ' . $group['pname'] . ' WHERE EXISTS (SELECT ID FROM ' . $group['bname'] . ' WHERE ' .
				$group['bname'] . '.id = ' . $group['pname'] . '.binaryid AND ' . $group['bname'] . '.collectionid = 0)'
			);
			if ($delqe !== false) {
				$reccount += $delqe->rowCount();
			}
			$delqf = $this->db->exec('DELETE FROM ' . $group['bname'] . ' WHERE collectionid = 0');
			if ($delqf !== false) {
				$reccount += $delqf->rowCount();
			}
		}

		// Parts that somehow have no binaries.
		if (mt_rand(1, 100) % 3 == 0) {
			$delqg = $this->db->exec(
				'DELETE FROM ' . $group['pname'] . ' WHERE binaryid NOT IN (SELECT b.id FROM ' . $group['bname'] . ' b)'
			);
			if ($delqg !== false) {
				$reccount += $delqg->rowCount();
			}
		}

		// Binaries that somehow have no collection.
		$delqh = $this->db->exec(
			'DELETE FROM ' . $group['bname'] . ' WHERE collectionid NOT IN (SELECT c.ID FROM ' . $group['cname'] . ' c)'
		);
		if ($delqh !== false) {
			$reccount += $delqh->rowCount();
		}

		// Collections that somehow have no binaries.
		$delqi = $this->db->exec(
			'DELETE FROM ' . $group['cname'] . ' WHERE ' . $group['cname'] . '.id NOT IN (SELECT ' . $group['bname'] .
			'.collectionid FROM ' . $group['bname'] . ') ' . $where1
		);
		if ($delqi !== false) {
			$reccount += $delqi->rowCount();
		}

		if ($this->echooutput) {
			$this->c->doEcho(
				$this->c->primary(
					'Removed ' .
					number_format($reccount) .
					' parts/binaries/collection rows in ' .
					$this->consoleTools->convertTime(TIME() - $stage7)
				)
			);
		}
	}

	// Queries that are not per group
	public function processReleasesStage7b()
	{
		$category = new Category();
		$genres = new Genres();
		$remcount = $reccount = $passcount = $dupecount = $relsizecount = $completioncount = $disabledcount = $disabledgenrecount = $miscothercount = $total = 0;

		// Delete old releases and finished collections.
		if ($this->echooutput) {
			$this->c->doEcho($this->c->header("Stage 7b -> Delete old releases and passworded releases."));
		}
		$stage7 = TIME();

		// Releases past retention.
		if ($this->site->releaseretentiondays != 0) {
			if (DB_TYPE === 'mysql') {
				$result = $this->db->queryDirect(sprintf('SELECT ID, guid FROM releases WHERE postdate < (NOW() - INTERVAL %d DAY)', $this->site->releaseretentiondays));
			} else {
				$result = $this->db->queryDirect(sprintf("SELECT ID, guid FROM releases WHERE postdate < (NOW() - INTERVAL '%d DAYS')", $this->site->releaseretentiondays));
			}
			if ($result !== false && $result->rowCount() > 0) {
				foreach ($result as $rowrel) {
					$this->fastDelete($rowrel['ID'], $rowrel['guid']);
					$remcount++;
				}
			}
		}

		// Passworded releases.
		if ($this->site->deletepasswordedrelease == 1) {
			$result = $this->db->queryDirect(
				'SELECT ID, guid FROM releases WHERE passwordstatus = ' . Releases::PASSWD_RAR
			);
			if ($result !== false && $result->rowCount() > 0) {
				foreach ($result as $rowrel) {
					$this->fastDelete($rowrel['ID'], $rowrel['guid']);
					$passcount++;
				}
			}
		}

		// Possibly passworded releases.
		if ($this->site->deletepossiblerelease == 1) {
			$result = $this->db->queryDirect(
				'SELECT ID, guid FROM releases WHERE passwordstatus = ' . Releases::PASSWD_POTENTIAL
			);
			if ($result !== false && $result->rowCount() > 0) {
				foreach ($result as $rowrel) {
					$this->fastDelete($rowrel['ID'], $rowrel['guid']);
					$passcount++;
				}
			}
		}

		// Crossposted releases.
		do {
			if ($this->crosspostt != 0) {
				if (DB_TYPE === 'mysql') {
					$resrel = $this->db->queryDirect(sprintf('SELECT ID, guid FROM releases WHERE adddate > (NOW() - INTERVAL %d HOUR) GROUP BY name HAVING COUNT(name) > 1', $this->crosspostt));
				} else {
					$resrel = $this->db->queryDirect(sprintf("SELECT ID, guid FROM releases WHERE adddate > (NOW() - INTERVAL '%d HOURS') GROUP BY name, id HAVING COUNT(name) > 1", $this->crosspostt));
				}
				$total = 0;
				if ($resrel !== false) {
					$total = $resrel->rowCount();
				}
				if ($total > 0) {
					foreach ($resrel as $rowrel) {
						$this->fastDelete($rowrel['ID'], $rowrel['guid']);
						$dupecount++;
					}
				}
			}
		} while ($total > 0);

		// Releases below completion %.
		if ($this->completion > 100) {
			$this->completion = 100;
			echo $this->c->error("\nYou have an invalid setting for completion.");
		}
		if ($this->completion > 0) {
			$resrel = $this->db->queryDirect(sprintf('SELECT ID, guid FROM releases WHERE completion < %d AND completion > 0', $this->completion));
			if ($resrel !== false && $resrel->rowCount() > 0) {
				foreach ($resrel as $rowrel) {
					$this->fastDelete($rowrel['ID'], $rowrel['guid']);
					$completioncount++;
				}
			}
		}

		// Disabled categories.
		$catlist = $category->getDisabledIDs();
		if (count($catlist) > 0) {
			foreach ($catlist as $cat) {
				$res = $this->db->queryDirect(sprintf('SELECT ID, guid FROM releases WHERE categoryID = %d', $cat['ID']));
				if ($res !== false && $res->rowCount() > 0) {
					foreach ($res as $rel) {
						$disabledcount++;
						$this->fastDelete($rel['ID'], $rel['guid']);
					}
				}
			}
		}

		// Disabled music genres.
		$genrelist = $genres->getDisabledIDs();
		if (count($genrelist) > 0) {
			foreach ($genrelist as $genre) {
				$rels = $this->db->queryDirect(sprintf('SELECT ID, guid FROM releases INNER JOIN (SELECT ID AS mid FROM musicinfo WHERE musicinfo.genreID = %d) mi ON releases.musicinfoID = mid', $genre['ID']));
				if ($rels !== false && $rels->rowCount() > 0) {
					foreach ($rels as $rel) {
						$disabledgenrecount++;
						$this->fastDelete($rel['ID'], $rel['guid']);
					}
				}
			}
		}

		// Misc other.
		if ($this->site->miscotherretentionhours > 0) {
			if (DB_TYPE === 'mysql') {
				$resrel = $this->db->queryDirect(sprintf('SELECT ID, guid FROM releases WHERE categoryID = %d AND adddate <= NOW() - INTERVAL %d HOUR', CATEGORY::CAT_MISC, $this->site->miscotherretentionhours));
			} else {
				$resrel = $this->db->queryDirect(sprintf("SELECT ID, guid FROM releases WHERE categoryID = %d AND adddate <= NOW() - INTERVAL '%d HOURS'", CATEGORY::CAT_MISC, $this->site->miscotherretentionhours));
			}
			if ($resrel !== false && $resrel->rowCount() > 0) {
				foreach ($resrel as $rowrel) {
					$this->fastDelete($rowrel['ID'], $rowrel['guid']);
					$miscothercount++;
				}
			}
		}

		if (DB_TYPE === 'mysql') {
			$this->db->exec(sprintf('DELETE FROM nzbs WHERE dateadded < (NOW() - INTERVAL %d HOUR)', $this->site->partretentionhours));
		} else {
			$this->db->exec(sprintf("DELETE FROM nzbs WHERE dateadded < (NOW() - INTERVAL '%d HOURS')", $this->site->partretentionhours));
		}

		if ($this->echooutput && $this->completion > 0) {
			$this->c->doEcho(
				$this->c->primary(
					'Removed releases: ' .
					number_format($remcount) .
					' past retention, ' .
					number_format($passcount) .
					' passworded, ' .
					number_format($dupecount) .
					' crossposted, ' .
					number_format($disabledcount) .
					' from disabled categories, ' .
					number_format($disabledgenrecount) .
					' from disabled music genres, ' .
					number_format($miscothercount) .
					' from misc->other, ' .
					number_format($completioncount) .
					' under ' .
					$this->completion .
					'% completion.'
				)
			);
		} else if ($this->echooutput && $this->completion == 0) {
			$this->c->doEcho(
				$this->c->primary(
					'Removed releases: ' .
					number_format($remcount) .
					' past retention, ' .
					number_format($passcount) .
					' passworded, ' .
					number_format($dupecount) .
					' crossposted, ' .
					number_format($disabledcount) .
					' from disabled categories, ' .
					number_format($disabledgenrecount) .
					' from disabled music genres, ' .
					number_format($miscothercount) .
					' from misc->other'
				)
			);
		}

		if ($this->echooutput) {
			if ($reccount > 0) {
				$this->c->doEcho(
					$this->c->primary(
						"Removed " . number_format($reccount) . ' parts/binaries/collection rows.'
					)
				);
			}
			$this->c->doEcho($this->c->primary($this->consoleTools->convertTime(TIME() - $stage7)), true);
		}
	}

	public function processReleasesStage4567_loop($categorize, $postproc, $groupID, $nntp)
	{
		$DIR = nZEDb_MISC;
		$PYTHON = shell_exec('which python3 2>/dev/null');
		$PYTHON = (empty($PYTHON) ? 'python -OOu' : 'python3 -OOu');

		$tot_retcount = $tot_nzbcount = $loops = 0;
		do {
			$retcount = $this->processReleasesStage4($groupID);
			$tot_retcount = $tot_retcount + $retcount;

			$nzbcount = $this->processReleasesStage5($groupID);
			if ($this->requestids == '1') {
				$this->processReleasesStage5b($groupID);
			} else if ($this->requestids == '2') {
				$stage8 = TIME();
				if ($this->echooutput) {
					$this->c->doEcho($this->c->header("Stage 5b -> Request ID Threaded lookup."));
				}
				passthru("$PYTHON ${DIR}update/python/requestid_threaded.py");
				if ($this->echooutput) {
					$this->c->doEcho(
						$this->c->primary(
							"\nReleases updated in " .
							$this->consoleTools->convertTime(TIME() - $stage8)
						)
					);
				}
			}

			$tot_nzbcount = $tot_nzbcount + $nzbcount;
			$this->processReleasesStage6($categorize, $postproc, $groupID, $nntp);
			$this->processReleasesStage7a($groupID);
			$loops++;
			// This loops as long as there were releases created or 3 loops, otherwise, you could loop indefinately
		} while (($nzbcount > 0 || $retcount > 0) && $loops < 3);

		return $tot_retcount;
	}

	public function processReleases($categorize, $postproc, $groupName, $nntp, $echooutput)
	{
		$this->echooutput = $echooutput;
		if ($this->hashcheck == 0) {
			exit($this->c->error("You must run update_binaries.php to update your collectionhash.\n"));
		}
		$groupID = '';

		if (!empty($groupName)) {
			$groupInfo = $this->groups->getByName($groupName);
			$groupID = $groupInfo['ID'];
		}

		$this->processReleases = microtime(true);
		if ($this->echooutput) {
			$this->c->doEcho($this->c->header("Starting release update process (" . date('Y-m-d H:i:s') . ")"), true);
		}

		if (!file_exists($this->site->nzbpath)) {
			if ($this->echooutput) {
				$this->c->doEcho($this->c->error('Bad or missing nzb directory - ' . $this->site->nzbpath), true);
			}

			return;
		}

		$this->processReleasesStage1($groupID);
		$this->processReleasesStage2($groupID);
		$this->processReleasesStage3($groupID);
		$releasesAdded = $this->processReleasesStage4567_loop($categorize, $postproc, $groupID, $nntp);
		$this->processReleasesStage4dot5($groupID);
		$this->processReleasesStage7b();
		$where = (!empty($groupID)) ? ' WHERE groupID = ' . $groupID : '';

		//Print amount of added releases and time it took.
		if ($this->echooutput && $this->tablepergroup == 0) {
			$countID = $this->db->queryOneRow('SELECT COUNT(ID) FROM collections ' . $where);
			$this->c->doEcho(
				$this->c->primary(
					'Completed adding ' .
					number_format($releasesAdded) .
					' releases in ' .
					$this->consoleTools->convertTime(number_format(microtime(true) - $this->processReleases, 2)) .
					'. ' .
					number_format(array_shift($countID)) .
					' collections waiting to be created (still incomplete or in queue for creation)'
				), true
			);
		}

		return $releasesAdded;
	}

	// This resets collections, useful when the namecleaning class's collectioncleaner function changes.
	public function resetCollections()
	{
		$res = $this->db->queryDirect('SELECT b.id as bid, b.name as bname, c.* FROM binaries b LEFT JOIN collections c ON b.collectionid = c.ID');
		if ($res !== false && $res->rowCount() > 0) {
			$timestart = TIME();
			if ($this->echooutput) {
				echo "Going to remake all the collections. This can be a long process, be patient. DO NOT STOP THIS SCRIPT!\n";
			}
			// Reset the collectionhash.
			$this->db->exec('UPDATE collections SET collectionhash = 0');
			$delcount = 0;
			$cIDS = array();
			foreach ($res as $row) {

				$groupName = $this->groups->getByNameByID($row['groupID']);
				$newSHA1 = sha1(
					$this->collectionsCleaning->collectionsCleaner(
						$row['bname'],
						$groupName .
						$row['fromname'] .
						$row['groupID'] .
						$row['totalfiles']
					)
				);
				$cres = $this->db->queryOneRow(sprintf('SELECT ID FROM collections WHERE collectionhash = %s', $this->db->escapeString($newSHA1)));
				if (!$cres) {
					$cIDS[] = $row['ID'];
					$csql = sprintf('INSERT INTO collections (subject, fromname, date, xref, groupID, totalfiles, collectionhash, filecheck, dateadded) VALUES (%s, %s, %s, %s, %d, %s, %s, 0, NOW())', $this->db->escapeString($row['bname']), $this->db->escapeString($row['fromname']), $this->db->escapeString($row['date']), $this->db->escapeString($row['xref']), $row['groupID'], $this->db->escapeString($row['totalfiles']), $this->db->escapeString($newSHA1));
					$collectionID = $this->db->queryInsert($csql);
					if ($this->echooutput) {
						$this->consoleTools->overWrite(
							'Recreated: ' . count($cIDS) . ' collections. Time:' .
							$this->consoleTools->convertTimer(TIME() - $timestart)
						);
					}
				} else {
					$collectionID = $cres['ID'];
				}
				//Update the binaries with the new info.
				$this->db->exec(sprintf('UPDATE binaries SET collectionid = %d WHERE ID = %d', $collectionID, $row['bid']));
			}
			//Remove the old collections.
			$delstart = TIME();
			if ($this->echooutput) {
				echo "\n";
			}
			$totalcIDS = count($cIDS);
			foreach ($cIDS as $cID) {
				$this->db->exec(sprintf('DELETE FROM collections WHERE ID = %d', $cID));
				$delcount++;
				if ($this->echooutput) {
					$this->consoleTools->overWrite(
						'Deleting old collections:' . $this->consoleTools->percentString($delcount, $totalcIDS) .
						' Time:' . $this->consoleTools->convertTimer(TIME() - $delstart)
					);
				}
			}
			// Delete previous failed attempts.
			$this->db->exec('DELETE FROM collections WHERE collectionhash = "0"');

			if ($this->hashcheck == 0) {
				$this->db->exec("UPDATE settings SET value = 1 WHERE setting = 'hashcheck'");
			}
			if ($this->echooutput) {
				echo "\nRemade " . count($cIDS) . ' collections in ' .
					$this->consoleTools->convertTime(TIME() - $timestart) . "\n";
			}
		} else {
			$this->db->exec("UPDATE settings SET value = 1 WHERE setting = 'hashcheck'");
		}
	}

	public function getTopDownloads()
	{
		return $this->db->query('SELECT ID, searchname, guid, adddate, SUM(grabs) AS grabs FROM releases WHERE grabs > 0 GROUP BY id, searchname, adddate HAVING SUM(grabs) > 0 ORDER BY grabs DESC LIMIT 10');
	}

	public function getTopComments()
	{
		return $this->db->query('SELECT ID, guid, searchname, adddate, SUM(comments) AS comments FROM releases WHERE comments > 0 GROUP BY id, searchname, adddate HAVING SUM(comments) > 0 ORDER BY comments DESC LIMIT 10');
	}

	public function getRecentlyAdded()
	{
		if (DB_TYPE === 'mysql') {
			return $this->db->query("SELECT CONCAT(cp.title, ' > ', category.title) AS title, COUNT(*) AS count FROM category INNER JOIN category cp on cp.ID = category.parentID INNER JOIN releases ON releases.categoryID = category.id WHERE releases.adddate > NOW() - INTERVAL 1 WEEK GROUP BY concat(cp.title, ' > ', category.title) ORDER BY COUNT(*) DESC");
		} else {
			return $this->db->query("SELECT CONCAT(cp.title, ' > ', category.title) AS title, COUNT(*) AS count FROM category INNER JOIN category cp on cp.ID = category.parentID INNER JOIN releases ON releases.categoryID = category.id WHERE releases.adddate > NOW() - INTERVAL '1 WEEK' GROUP BY concat(cp.title, ' > ', category.title) ORDER BY COUNT(*) DESC");
		}
	}

	/**
	 * Get all newest movies with coves for poster wall.
	 *
	 * @return array
	 */
	public function getNewestMovies()
	{
		return $this->db->query(
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
	 * Get all newest games with covers for poster wall.
	 *
	 * @return array
	 */
	public function getNewestConsole()
	{
		return $this->db->query(
			"SELECT DISTINCT (a.consolenfoID),
				guid, name, b.title, searchname, size, completion,
				postdate, categoryID, comments, grabs, c.cover
			FROM releases a, category b, consoleinfo c
			WHERE c.cover > 0
			AND a.categoryID BETWEEN 1000 AND 1999
			AND b.title = 'Console'
			AND a.consolenfoID = c.ID
			AND a.consolenfoID != -2
			AND a.consolenfoID != 0
			GROUP BY a.consolenfoID
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
		return $this->db->query(
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
		return $this->db->query(
			"SELECT DISTINCT (a.bookinfoid),
				guid, name, b.title, searchname, size, completion,
				postdate, categoryID, comments, grabs, url, c.cover, c.title as booktitle, c.author
			FROM releases a, category b, bookinfo c
			WHERE c.cover > 0
			AND (a.categoryID BETWEEN 8000 AND 8999 OR a.categoryID = 3030)
			AND (b.title = 'Books' OR b.title = 'Audiobook')
			AND a.bookinfoid = c.ID
			AND a.bookinfoid != -2
			GROUP BY a.bookinfoid
			ORDER BY a.postdate
			DESC LIMIT 24"
		);
	}

}
