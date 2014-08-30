<?php
require_once(WWW_DIR . "/lib/framework/db.php");
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
require_once(WWW_DIR . "../misc/update_scripts/nix_scripts/tmux/lib/ReleaseCleaner.php");
require_once(WWW_DIR . "../misc/update_scripts/nix_scripts/tmux/lib/Enzebe.php");

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
	 * Get a list of releases by an array of names
	 */
	public function getByNames($names)
	{
		$db = new DB();

		$nsql = "1=2";
		if (count($names) > 0) {
			$n = array();
			foreach ($names as $nm)
				$n[] = " searchname = " . $db->escapeString($nm);

			$nsql = "( " . implode(' or ', $n) . " )";
		}

		$sql = sprintf(" SELECT releases.*, CONCAT(cp.title, ' > ', c.title) AS category_name,
							m.ID AS movie_id, m.title, m.rating, m.cover, m.plot, m.year, m.genre, m.director, m.actors, m.tagline,
							mu.ID AS music_id, mu.title as mu_title, mu.cover as mu_cover, mu.year as mu_year, mu.artist as mu_artist, mu.tracks as mu_tracks, mu.review as mu_review,
							ep.ID AS ep_id, ep.showtitle as ep_showtitle, ep.airdate as ep_airdate, ep.fullep as ep_fullep, ep.overview as ep_overview,
							tvrage.imgdata as rage_imgdata, tvrage.ID as rg_ID
							FROM releases
							LEFT OUTER JOIN category c ON c.ID = releases.categoryID
							LEFT OUTER JOIN category cp ON cp.ID = c.parentID
							LEFT OUTER JOIN movieinfo m ON m.imdbID = releases.imdbID
							LEFT OUTER JOIN musicinfo mu ON mu.ID = releases.musicinfoID
							LEFT OUTER JOIN episodeinfo ep ON ep.ID = releases.episodeinfoID
							LEFT OUTER JOIN tvrage ON tvrage.rageID = releases.rageID
						where %s", $nsql
		);

		return $db->queryDirect($sql);
	}

	/**
	 * Get a count of releases for pager. used in admin manage list
	 */
	public function getCount()
	{
		$db = new DB();
		$res = $db->queryOneRow("select count(ID) as num from releases");

		return $res["num"];
	}

	/**
	 * Get a range of releases. used in admin manage list
	 */
	public function getRange($start, $num)
	{
		$db = new DB();

		if ($start === false)
			$limit = "";
		else
			$limit = " LIMIT " . $start . "," . $num;

		return $db->query(" SELECT releases.*, concat(cp.title, ' > ', c.title) as category_name from releases left outer join category c on c.ID = releases.categoryID left outer join category cp on cp.ID = c.parentID order by postdate desc" . $limit);
	}

	/**
	 * Get a count of previews for pager. used in admin manage list
	 */
	public function getPreviewCount($previewtype, $cat)
	{
		$db = new DB();

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

		$sql = sprintf("select count(ID) as num from releases where haspreview = %d %s ", $previewtype, $catsrch);
		$res = $db->queryOneRow($sql);

		return $res["num"];
	}

	/**
	 * Get a range of releases. used in admin manage list
	 */
	public function getPreviewRange($previewtype, $cat, $start, $num)
	{
		$db = new DB();

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

		$sql = sprintf(" SELECT releases.*, concat(cp.title, ' > ', c.title) as category_name from releases left outer join category c on c.ID = releases.categoryID left outer join category cp on cp.ID = c.parentID where haspreview = %d %s order by postdate desc %s", $previewtype, $catsrch, $limit);

		return $db->query($sql);
	}


	/**
	 * Get a count of releases for main browse pager.
	 */
	public function getBrowseCount($cat, $maxage = -1, $excludedcats = array(), $grp = array())
	{
		$db = new DB();

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
			$maxage = sprintf(" and postdate > now() - interval %d day ", $maxage);
		else
			$maxage = "";

		$grpsql = "";
		if (count($grp) > 0) {
			$grpsql = " and (";
			foreach ($grp as $grpname) {
				$grpsql .= sprintf(" groups.name = %s or ", $db->escapeString(str_replace("a.b.", "alt.binaries.", $grpname)));
			}
			$grpsql .= "1=2 )";
		}

		$exccatlist = "";
		if (count($excludedcats) > 0)
			$exccatlist = " and categoryID not in (" . implode(",", $excludedcats) . ")";

		$sql = sprintf("select count(releases.ID) as num from releases left outer join groups on groups.ID = releases.groupID where releases.passwordstatus <= (select value from site where setting='showpasswordedrelease') %s %s %s %s", $catsrch, $maxage, $exccatlist, $grpsql);
		$res = $db->queryOneRow($sql, true);

		return $res["num"];
	}

	/**
	 * Get a releases for main browse pages.
	 */
	public function getBrowseRange($cat, $start, $num, $orderby, $maxage = -1, $excludedcats = array(), $grp = array())
	{
		$db = new DB();

		if ($start === false)
			$limit = "";
		else
			$limit = " LIMIT " . $start . "," . $num;

		$usecatindex = "";
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
			$usecatindex = " use index (ix_releases_categoryID) ";
		}

		$maxagesql = "";
		if ($maxage > 0)
			$maxagesql = sprintf(" and postdate > now() - interval %d day ", $maxage);

		$grpsql = "";
		if (count($grp) > 0) {
			$grpsql = "select ID from groups where (";
			foreach ($grp as $grpname)
				$grpsql .= sprintf(" groups.name = %s or ", $db->escapeString(str_replace("a.b.", "alt.binaries.", $grpname)));

			$grpsql .= "1=2 )";

			$grpres = $db->query($grpsql);
			if (count($grpsql) > 0) {
				$grpsql = " and ( ";
				foreach ($grpres as $grpresrow)
					$grpsql .= sprintf(" groups.ID = %d or ", $grpresrow["ID"]);

				$grpsql = substr($grpsql, 0, strlen($grpsql) - 3) . " ) ";
			} else
				$grpsql = "";
		}

		$exccatlist = "";
		if (count($excludedcats) > 0)
			$exccatlist = " and releases.categoryID not in (" . implode(",", $excludedcats) . ")";

		$order = $this->getBrowseOrder($orderby);
		$sql = sprintf(" SELECT releases.*, concat(cp.title, ' > ', c.title) as category_name, concat(cp.ID, ',', c.ID) as category_ids, groups.name as group_name, rn.ID as nfoID, re.releaseID as reID, pre.ctime, pre.nuketype, coalesce(movieinfo.ID,0) as movieinfoID from releases %s left outer join groups on groups.ID = releases.groupID left outer join movieinfo on movieinfo.imdbID = releases.imdbID left outer join releaseaudio re on re.releaseID = releases.ID and re.audioID = 1 left outer join releasenfo rn on rn.releaseID = releases.ID and rn.nfo is not null left outer join category c on c.ID = releases.categoryID left outer join category cp on cp.ID = c.parentID left outer join predb pre on pre.ID = releases.preID where releases.passwordstatus <= (select value from site where setting='showpasswordedrelease') %s %s %s %s order by %s %s" . $limit, $usecatindex, $catsrch, $maxagesql, $exccatlist, $grpsql, $order[0], $order[1]);

		return $db->query($sql, true);
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
	 * Get a list of available columns for sorting browse list
	 */
	public function getBrowseOrdering()
	{
		return array('name_asc', 'name_desc', 'cat_asc', 'cat_desc', 'posted_asc', 'posted_desc', 'size_asc', 'size_desc', 'files_asc', 'files_desc', 'stats_asc', 'stats_desc');
	}

	/**
	 * Get a range of releases. Used in nzb export
	 */
	public function getForExport($postfrom, $postto, $group, $cat)
	{
		$db = new DB();
		if ($postfrom != "") {
			$dateparts = explode("/", $postfrom);
			if (count($dateparts) == 3)
				$postfrom = sprintf(" and postdate > %s ", $db->escapeString($dateparts[2] . "-" . $dateparts[1] . "-" . $dateparts[0] . " 00:00:00"));
			else
				$postfrom = "";
		}

		if ($postto != "") {
			$dateparts = explode("/", $postto);
			if (count($dateparts) == 3)
				$postto = sprintf(" and postdate < %s ", $db->escapeString($dateparts[2] . "-" . $dateparts[1] . "-" . $dateparts[0] . " 23:59:59"));
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

		return $db->queryDirect(sprintf("SELECT searchname, guid, CONCAT(cp.title,'_',category.title) as catName FROM releases INNER JOIN category ON releases.categoryID = category.ID LEFT OUTER JOIN category cp ON cp.ID = category.parentID where 1 = 1 %s %s %s %s", $postfrom, $postto, $group, $cat));
	}

	/**
	 * Get the earliest release
	 */
	public function getEarliestUsenetPostDate()
	{
		$db = new DB();
		$row = $db->queryOneRow("SELECT DATE_FORMAT(min(postdate), '%d/%m/%Y') as postdate from releases");

		return $row["postdate"];
	}

	/**
	 * Get the most recent release
	 */
	public function getLatestUsenetPostDate()
	{
		$db = new DB();
		$row = $db->queryOneRow("SELECT DATE_FORMAT(max(postdate), '%d/%m/%Y') as postdate from releases");

		return $row["postdate"];
	}

	/**
	 * Get all groups for which there is a release for a html select
	 */
	public function getReleasedGroupsForSelect($blnIncludeAll = true)
	{
		$db = new DB();
		$groups = $db->query("select distinct groups.ID, groups.name from releases inner join groups on groups.ID = releases.groupID");
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
		$db = new DB();

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

		$sql = sprintf(" SELECT releases.*, rn.ID as nfoID, m.title as imdbtitle, m.cover, m.imdbID, m.rating, m.plot, m.year, m.genre, m.director, m.actors, g.name as group_name, concat(cp.title, ' > ', c.title) as category_name, concat(cp.ID, ',', c.ID) as category_ids, coalesce(cp.ID,0) as parentCategoryID, mu.title as mu_title, mu.url as mu_url, mu.artist as mu_artist, mu.publisher as mu_publisher, mu.releasedate as mu_releasedate, mu.review as mu_review, mu.tracks as mu_tracks, mu.cover as mu_cover, mug.title as mu_genre, co.title as co_title, co.url as co_url, co.publisher as co_publisher, co.releasedate as co_releasedate, co.review as co_review, co.cover as co_cover, cog.title as co_genre,   bo.title as bo_title, bo.url as bo_url, bo.publisher as bo_publisher, bo.author as bo_author, bo.publishdate as bo_publishdate, bo.review as bo_review, bo.cover as bo_cover  from releases left outer join category c on c.ID = releases.categoryID left outer join category cp on cp.ID = c.parentID left outer join groups g on g.ID = releases.groupID left outer join releasenfo rn on rn.releaseID = releases.ID and rn.nfo is not null left outer join movieinfo m on m.imdbID = releases.imdbID and m.title != '' left outer join musicinfo mu on mu.ID = releases.musicinfoID left outer join genres mug on mug.ID = mu.genreID left outer join bookinfo bo on bo.ID = releases.bookinfoID left outer join consoleinfo co on co.ID = releases.consoleinfoID left outer join genres cog on cog.ID = co.genreID %s where releases.passwordstatus <= (select value from site where setting='showpasswordedrelease') %s %s %s %s order by postdate desc %s", $cartsrch, $catsrch, $rage, $anidb, $airdate, $limit);

		return $db->query($sql, true);
	}

	/**
	 * Get releases in users 'my tv show' rss feed
	 */
	public function getShowsRss($num, $uid = 0, $excludedcats = array(), $airdate = -1)
	{
		$db = new DB();

		$exccatlist = "";
		if (count($excludedcats) > 0)
			$exccatlist = " and releases.categoryID not in (" . implode(",", $excludedcats) . ")";

		$usershows = $db->query(sprintf("select rageID, categoryID from userseries where userID = %d", $uid), true);
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

		$sql = sprintf(" SELECT releases.*, tvr.rageID, tvr.releasetitle, epinfo.overview, epinfo.director, epinfo.gueststars, epinfo.writer, epinfo.rating, epinfo.fullep, epinfo.showtitle, epinfo.tvdbID as ep_tvdbID, g.name as group_name, concat(cp.title, '-', c.title) as category_name, concat(cp.ID, ',', c.ID) as category_ids, coalesce(cp.ID,0) as parentCategoryID
						FROM releases FORCE INDEX (ix_releases_rageID)
						left outer join category c on c.ID = releases.categoryID
						left outer join category cp on cp.ID = c.parentID
						left outer join groups g on g.ID = releases.groupID
						left outer join (SELECT ID, releasetitle, rageid FROM tvrage GROUP BY rageid) tvr on tvr.rageID = releases.rageID
						left outer join episodeinfo epinfo on epinfo.ID = releases.episodeinfoID
						inner join
						(   select ID from
								( select id, rageID, categoryID, season, episode from releases where %s order by season desc, episode desc, postdate asc ) releases
							group by rageID, season, episode, categoryID
						) z on z.id = releases.ID
						where %s %s %s
						and releases.passwordstatus <= (select value from site where setting='showpasswordedrelease')
						order by postdate desc %s", $usql, $usql, $exccatlist, $airdate, $limit
		);

		return $db->query($sql);
	}

	/**
	 * Get releases in users 'my movies' rss feed
	 */
	public function getMyMoviesRss($num, $uid = 0, $excludedcats = array())
	{
		$db = new DB();

		$exccatlist = "";
		if (count($excludedcats) > 0)
			$exccatlist = " and releases.categoryID not in (" . implode(",", $excludedcats) . ")";

		$usermovies = $db->query(sprintf("select imdbID, categoryID from usermovies where userID = %d", $uid), true);
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

		$sql = sprintf(" SELECT releases.*, mi.title as releasetitle, g.name as group_name, concat(cp.title, '-', c.title) as category_name, concat(cp.ID, ',', c.ID) as category_ids, coalesce(cp.ID,0) as parentCategoryID
						FROM releases
						left outer join category c on c.ID = releases.categoryID
						left outer join category cp on cp.ID = c.parentID
						left outer join groups g on g.ID = releases.groupID
						left outer join movieinfo mi on mi.imdbID = releases.imdbID
						where %s %s
						and releases.passwordstatus <= (select value from site where setting='showpasswordedrelease')
						order by postdate desc %s", $usql, $exccatlist, $limit
		);

		return $db->query($sql);
	}

	/**
	 * Get range of releases in users 'my tvshows'
	 */
	public function getShowsRange($usershows, $start, $num, $orderby, $maxage = -1, $excludedcats = array())
	{
		$db = new DB();

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
		$sql = sprintf(" SELECT releases.*, concat(cp.title, '-', c.title) as category_name, concat(cp.ID, ',', c.ID) as category_ids, groups.name as group_name, pre.ctime, pre.nuketype, rn.ID as nfoID, re.releaseID as reID from releases left outer join releasevideo re on re.releaseID = releases.ID left outer join groups on groups.ID = releases.groupID left outer join releasenfo rn on rn.releaseID = releases.ID and rn.nfo is not null left outer join category c on c.ID = releases.categoryID left outer join predb pre on pre.ID = releases.preID left outer join category cp on cp.ID = c.parentID where %s %s and releases.passwordstatus <= (select value from site where setting='showpasswordedrelease') %s order by %s %s" . $limit, $usql, $exccatlist, $maxagesql, $order[0], $order[1]);

		return $db->query($sql, true);
	}

	/**
	 * Get count of releases in users 'my tvshows' for pager
	 */
	public function getShowsCount($usershows, $maxage = -1, $excludedcats = array())
	{
		$db = new DB();

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

		$res = $db->queryOneRow(sprintf(" SELECT count(releases.ID) as num from releases where %s %s and releases.passwordstatus <= (select value from site where setting='showpasswordedrelease') %s", $usql, $exccatlist, $maxagesql), true);

		return $res["num"];
	}

	/**
	 * Delete one or more releases.
	 */
	public function delete($id, $isGuid = false)
	{
		$db = new DB();
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
				$db->exec(sprintf("DELETE from releases where id = %d", $rel['ID']));
			}
		}
	}

	/**
	 * Deletes a single release by GUID, and all the corresponding files.
	 *
	 * @param              $guid
	 * @param              $id
	 * @param NZB          $nzb
	 * @param ReleaseImage $releaseImage
	 */
	public function deleteSingle($guid, $id, $nzb, $releaseImage)
	{
		$db = new DB();
		$releaseImage = new ReleaseImage();
		// Delete NZB from disk.
		$nzbPath = $nzb->getNZBPath($guid);
		if ($nzbPath) {
			@unlink($nzbPath);
		}

		// Delete images.
		$releaseImage->delete($guid);

		// Delete from DB.
		$db->queryExec(
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
				$db->escapeString($guid)
			)
		);
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
	 * Update a release.
	 */
	public function update($id, $name, $searchname, $fromname, $category, $parts, $grabs, $size, $posteddate, $addeddate, $rageid, $seriesfull, $season, $episode, $imdbid, $anidbid, $tvdbid, $consoleinfoid)
	{
		$db = new DB();

		$db->exec(sprintf("update releases set name=%s, searchname=%s, fromname=%s, categoryID=%d, totalpart=%d, grabs=%d, size=%s, postdate=%s, adddate=%s, rageID=%d, seriesfull=%s, season=%s, episode=%s, imdbID=%d, anidbID=%d, tvdbID=%d,consoleinfoID=%d where id = %d",
				$db->escapeString($name), $db->escapeString($searchname), $db->escapeString($fromname), $category, $parts, $grabs, $db->escapeString($size), $db->escapeString($posteddate), $db->escapeString($addeddate), $rageid, $db->escapeString($seriesfull), $db->escapeString($season), $db->escapeString($episode), $imdbid, $anidbid, $tvdbid, $consoleinfoid, $id
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

		$db = new DB();
		$updateSql = array();
		foreach ($update as $updk => $updv) {
			if ($updv != '')
				$updateSql[] = sprintf($updk . '=%s', $db->escapeString($updv));
		}

		if (sizeof($updateSql) < 1) {
			//echo 'no field set to be changed';
			return -1;
		}

		$updateGuids = array();
		foreach ($guids as $guid) {
			$updateGuids[] = $db->escapeString($guid);
		}

		$sql = sprintf('update releases set ' . implode(', ', $updateSql) . ' where guid in (%s)', implode(', ', $updateGuids));

		return $db->exec($sql);
	}

	/**
	 * Update whether a release has a preview.
	 */
	public function updateHasPreview($guid, $haspreview)
	{
		$db = new DB();
		$db->exec(sprintf("update releases set haspreview = %d where guid = %s", $haspreview, $db->escapeString($guid)));
	}

	/**
	 * Not yet implemented.
	 */
	public function searchadv($searchname, $filename, $poster, $group, $cat = array(-1), $sizefrom, $sizeto, $offset = 0, $limit = 1000, $orderby = '', $maxage = -1, $excludedcats = array())
	{
		return array();
	}

	/**
	 * Search for releases.
	 */
	public function search($search, $cat = array(-1), $offset = 0, $limit = 1000, $orderby = '', $maxage = -1, $excludedcats = array(), $grp = array(), $minsize = -1, $maxsize = -1)
	{
		$s = new Sites();
		$site = $s->get();

		if ($site->sphinxenabled) {
			$sphinx = new Sphinx();
			$order = $this->getBrowseOrder($orderby);
			$results = $sphinx->search($search, $cat, $offset, $limit, $order, $maxage, $excludedcats, $grp, array(), true, $minsize, $maxsize);
			if (is_array($results))
				return $results;
		}

		//
		// Search using MySQL
		//
		$db = new DB();

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

		$grpsql = "";
		if (count($grp) > 0) {
			$grpsql = " and (";
			foreach ($grp as $grpname) {
				$grpsql .= sprintf(" groups.name = %s or ", $db->escapeString(str_replace("a.b.", "alt.binaries.", $grpname)));
			}
			$grpsql .= "1=2 )";
		}

		//
		// if the query starts with a ^ it indicates the search is looking for items which start with the term
		// still do the fulltext match, but mandate that all items returned must start with the provided word
		//
		$words = explode(" ", $search);
		$searchsql = "";
		$intwordcount = 0;
		if (count($words) > 0) {
			foreach ($words as $word) {
				if ($word != "") {
					//
					// see if the first word had a caret, which indicates search must start with term
					//
					if ($intwordcount == 0 && (strpos($word, "^") === 0))
						$searchsql .= sprintf(" and releases.searchname like %s", $db->escapeString(substr($word, 1) . "%"));
					elseif (substr($word, 0, 2) == '--')
						$searchsql .= sprintf(" and releases.searchname not like %s", $db->escapeString("%" . substr($word, 2) . "%"));
					else
						$searchsql .= sprintf(" and releases.searchname like %s", $db->escapeString("%" . $word . "%"));

					$intwordcount++;
				}
			}
		}

		if ($maxage > 0)
			$maxage = sprintf(" and postdate > now() - interval %d day ", $maxage);
		else
			$maxage = "";

		if ($minsize != -1)
			$minsize = sprintf(" and size > %d ", $minsize);
		else
			$minsize = "";

		if ($maxsize != -1)
			$maxsize = sprintf(" and size < %d ", $maxsize);
		else
			$maxsize = "";

		$exccatlist = "";
		if (count($excludedcats) > 0)
			$exccatlist = " and releases.categoryID not in (" . implode(",", $excludedcats) . ")";

		if ($orderby == "") {
			$order[0] = " postdate ";
			$order[1] = " desc ";
		} else
			$order = $this->getBrowseOrder($orderby);

		$sql = sprintf("select releases.*, concat(cp.title, ' > ', c.title) as category_name, concat(cp.ID, ',', c.ID) as category_ids, groups.name as group_name, rn.ID as nfoID, re.releaseID as reID, cp.ID as categoryParentID, pre.ctime, pre.nuketype, coalesce(movieinfo.ID,0) as movieinfoID from releases %s left outer join movieinfo on movieinfo.imdbID = releases.imdbID left outer join releasevideo re on re.releaseID = releases.ID left outer join releasenfo rn on rn.releaseID = releases.ID left outer join groups on groups.ID = releases.groupID left outer join category c on c.ID = releases.categoryID left outer join category cp on cp.ID = c.parentID left outer join predb pre on pre.ID = releases.preID where releases.passwordstatus <= (select value from site where setting='showpasswordedrelease') %s %s %s %s %s %s %s order by %s %s limit %d, %d ", $usecatindex, $searchsql, $catsrch, $maxage, $exccatlist, $grpsql, $minsize, $maxsize, $order[0], $order[1], $offset, $limit);
		$orderpos = strpos($sql, "order by");
		$wherepos = strpos($sql, "where");
		$sqlcount = "select count(releases.ID) as num from releases " . substr($sql, $wherepos, $orderpos - $wherepos);

		$countres = $db->queryOneRow($sqlcount, true);
		$res = $db->query($sql, true);
		if (count($res) > 0)
			$res[0]["_totalrows"] = $countres["num"];

		return $res;
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

		$db = new DB();

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

			$series = sprintf(" and releases.season = %s", $db->escapeString($series));
		}
		if ($episode != "") {
			if (is_numeric($episode))
				$episode = sprintf('E%02d', $episode);

			$episode = sprintf(" and releases.episode like %s", $db->escapeString('%' . $episode . '%'));
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
						$searchsql .= sprintf(" and releases.searchname like %s", $db->escapeString(substr($word, 1) . "%"));
					elseif (substr($word, 0, 2) == '--')
						$searchsql .= sprintf(" and releases.searchname not like %s", $db->escapeString("%" . substr($word, 2) . "%"));
					else
						$searchsql .= sprintf(" and releases.searchname like %s", $db->escapeString("%" . $word . "%"));

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

		$sql = sprintf("select releases.*, concat(cp.title, ' > ', c.title) as category_name, concat(cp.ID, ',', c.ID) as category_ids, groups.name as group_name, rn.ID as nfoID, re.releaseID as reID from releases %s left outer join category c on c.ID = releases.categoryID left outer join groups on groups.ID = releases.groupID left outer join releasevideo re on re.releaseID = releases.ID left outer join releasenfo rn on rn.releaseID = releases.ID and rn.nfo is not null left outer join category cp on cp.ID = c.parentID where releases.passwordstatus <= (select value from site where setting='showpasswordedrelease') %s %s %s %s %s %s order by postdate desc limit %d, %d ", $usecatindex, $rageId, $series, $episode, $searchsql, $catsrch, $maxage, $offset, $limit);
		$orderpos = strpos($sql, "order by");
		$wherepos = strpos($sql, "where");
		$sqlcount = "select count(releases.ID) as num from releases " . substr($sql, $wherepos, $orderpos - $wherepos);

		$countres = $db->queryOneRow($sqlcount, true);
		$res = $db->query($sql, true);
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

		$db = new DB();

		$anidbID = ($anidbID > -1) ? sprintf(" AND anidbID = %d ", $anidbID) : '';

		$epno = is_numeric($epno) ? sprintf(" AND releases.episode LIKE '%s' ", $db->escapeString('%' . $epno . '%')) : '';

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
						$searchsql .= sprintf(" AND releases.searchname LIKE '%s' ", $db->escapeString(substr($word, 1) . "%"));
					elseif (substr($word, 0, 2) == '--')
						$searchsql .= sprintf(" AND releases.searchname NOT LIKE '%s' ", $db->escapeString("%" . substr($word, 2) . "%"));
					else
						$searchsql .= sprintf(" AND releases.searchname LIKE '%s' ", $db->escapeString("%" . $word . "%"));

					$intwordcount++;
				}
			}
		}

		$maxage = ($maxage > 0) ? sprintf(" and postdate > now() - interval %d day ", $maxage) : '';

		$sql = sprintf("SELECT releases.*, concat(cp.title, ' > ', c.title)
			AS category_name, concat(cp.ID, ',', c.ID) AS category_ids, groups.name AS group_name, rn.ID AS nfoID
			FROM releases LEFT OUTER JOIN category c ON c.ID = releases.categoryID LEFT OUTER JOIN groups ON groups.ID = releases.groupID
			LEFT OUTER JOIN releasenfo rn ON rn.releaseID = releases.ID and rn.nfo IS NOT NULL LEFT OUTER JOIN category cp ON cp.ID = c.parentID
			WHERE releases.passwordstatus <= (select value from site where setting='showpasswordedrelease') %s %s %s %s ORDER BY postdate desc LIMIT %d, %d ",
			$anidbID, $epno, $searchsql, $maxage, $offset, $limit
		);
		$orderpos = strpos($sql, "ORDER BY");
		$wherepos = strpos($sql, "WHERE");
		$sqlcount = "SELECT count(releases.ID) AS num FROM releases " . substr($sql, $wherepos, $orderpos - $wherepos);

		$countres = $db->queryOneRow($sqlcount, true);
		$res = $db->query($sql, true);
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

		$db = new DB();
		$searchsql = "";

		if ($artist != "")
			$searchsql .= sprintf(" and musicinfo.artist like %s ", $db->escapeString("%" . $artist . "%"));
		if ($album != "")
			$searchsql .= sprintf(" and musicinfo.title like %s ", $db->escapeString("%" . $album . "%"));
		if ($label != "")
			$searchsql .= sprintf(" and musicinfo.publisher like %s ", $db->escapeString("%" . $label . "%"));
		if ($track != "")
			$searchsql .= sprintf(" and musicinfo.tracks like %s ", $db->escapeString("%" . $track . "%"));
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

		$sql = sprintf("select releases.*, musicinfo.cover as mi_cover, musicinfo.review as mi_review, musicinfo.tracks as mi_tracks, musicinfo.publisher as mi_publisher, musicinfo.title as mi_title, musicinfo.artist as mi_artist, genres.title as music_genrename, concat(cp.title, ' > ', c.title) as category_name, concat(cp.ID, ',', c.ID) as category_ids, groups.name as group_name, rn.ID as nfoID from releases %s left outer join musicinfo on musicinfo.ID = releases.musicinfoID left join genres on genres.ID = musicinfo.genreID left outer join groups on groups.ID = releases.groupID left outer join category c on c.ID = releases.categoryID left outer join releasenfo rn on rn.releaseID = releases.ID and rn.nfo is not null left outer join category cp on cp.ID = c.parentID where releases.passwordstatus <= (select value from site where setting='showpasswordedrelease') %s %s %s %s order by postdate desc limit %d, %d ", $usecatindex, $searchsql, $catsrch, $maxage, $genresql, $offset, $limit);
		$orderpos = strpos($sql, "order by");
		$wherepos = strpos($sql, "where");
		$sqlcount = "select count(releases.ID) as num from releases inner join musicinfo on musicinfo.ID = releases.musicinfoID " . substr($sql, $wherepos, $orderpos - $wherepos);

		$countres = $db->queryOneRow($sqlcount, true);
		$res = $db->query($sql, true);
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

		$db = new DB();
		$searchsql = "";

		if ($author != "")
			$searchsql .= sprintf(" and bookinfo.author like %s ", $db->escapeString("%" . $author . "%"));
		if ($title != "")
			$searchsql .= sprintf(" and bookinfo.title like %s ", $db->escapeString("%" . $title . "%"));

		if ($maxage > 0)
			$maxage = sprintf(" and postdate > now() - interval %d day ", $maxage);
		else
			$maxage = "";

		$sql = sprintf("select releases.*, bookinfo.cover as bi_cover, bookinfo.review as bi_review, bookinfo.publisher as bi_publisher, bookinfo.pages as bi_pages, bookinfo.publishdate as bi_publishdate, bookinfo.title as bi_title, bookinfo.author as bi_author, genres.title as book_genrename, concat(cp.title, ' > ', c.title) as category_name, concat(cp.ID, ',', c.ID) as category_ids, groups.name as group_name, rn.ID as nfoID from releases left outer join bookinfo on bookinfo.ID = releases.bookinfoID left join genres on genres.ID = bookinfo.genreID left outer join groups on groups.ID = releases.groupID left outer join category c on c.ID = releases.categoryID left outer join releasenfo rn on rn.releaseID = releases.ID and rn.nfo is not null left outer join category cp on cp.ID = c.parentID where releases.passwordstatus <= (select value from site where setting='showpasswordedrelease') %s %s order by postdate desc limit %d, %d ", $searchsql, $maxage, $offset, $limit);
		$orderpos = strpos($sql, "order by");
		$wherepos = strpos($sql, "where");
		$sqlcount = "select count(releases.ID) as num from releases inner join bookinfo on bookinfo.ID = releases.bookinfoID " . substr($sql, $wherepos, $orderpos - $wherepos);

		$countres = $db->queryOneRow($sqlcount, true);
		$res = $db->query($sql, true);
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

		$db = new DB();

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
						$searchsql .= sprintf(" and releases.searchname like %s", $db->escapeString(substr($word, 1) . "%"));
					elseif (substr($word, 0, 2) == '--')
						$searchsql .= sprintf(" and releases.searchname not like %s", $db->escapeString("%" . substr($word, 2) . "%"));
					else
						$searchsql .= sprintf(" and releases.searchname like %s", $db->escapeString("%" . $word . "%"));

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
			$genre = sprintf(" and movieinfo.genre like %s", $db->escapeString("%" . $genre . "%"));
		}

		$sql = sprintf("select releases.*, movieinfo.title as moi_title, movieinfo.tagline as moi_tagline, movieinfo.rating as moi_rating, movieinfo.plot as moi_plot, movieinfo.year as moi_year, movieinfo.genre as moi_genre, movieinfo.director as moi_director, movieinfo.actors as moi_actors, movieinfo.cover as moi_cover, movieinfo.backdrop as moi_backdrop, concat(cp.title, ' > ', c.title) as category_name, concat(cp.ID, ',', c.ID) as category_ids, groups.name as group_name, rn.ID as nfoID from releases left outer join groups on groups.ID = releases.groupID left outer join category c on c.ID = releases.categoryID left outer join releasenfo rn on rn.releaseID = releases.ID and rn.nfo is not null left outer join category cp on cp.ID = c.parentID left outer join movieinfo on releases.imdbID = movieinfo.imdbID where releases.passwordstatus <= (select value from site where setting='showpasswordedrelease') %s %s %s %s %s order by postdate desc limit %d, %d ", $searchsql, $imdbId, $catsrch, $maxage, $genre, $offset, $limit);
		$orderpos = strpos($sql, "order by");
		$wherepos = strpos($sql, "where");
		$sqlcount = "select count(releases.ID) as num from releases left outer join movieinfo on releases.imdbID = movieinfo.imdbID " . substr($sql, $wherepos, $orderpos - $wherepos);

		$countres = $db->queryOneRow($sqlcount, true);
		$res = $db->query($sql, true);
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
	 * Retrieve one or more releases by guid.
	 */
	public function getByGuid($guid)
	{
		$db = new DB();
		if (is_array($guid)) {
			$tmpguids = array();
			foreach ($guid as $g)
				$tmpguids[] = $db->escapeString($g);
			$gsql = sprintf('guid in (%s)', implode(',', $tmpguids));
		} else {
			$gsql = sprintf('guid = %s', $db->escapeString($guid));
		}
		$sql = sprintf("select releases.*, musicinfo.cover as mi_cover, musicinfo.review as mi_review, musicinfo.tracks as mi_tracks, musicinfo.publisher as mi_publisher, musicinfo.title as mi_title, musicinfo.artist as mi_artist, music_genre.title as music_genrename,    bookinfo.cover as bi_cover, bookinfo.review as bi_review, bookinfo.publisher as bi_publisher, bookinfo.publishdate as bi_publishdate, bookinfo.title as bi_title, bookinfo.author as bi_author, bookinfo.pages as bi_pages,  bookinfo.isbn as bi_isbn, concat(cp.title, ' > ', c.title) as category_name, concat(cp.ID, ',', c.ID) as category_ids, groups.name as group_name, movieinfo.title AS movietitle, movieinfo.year AS movieyear, (SELECT releasetitle FROM tvrage WHERE rageid = releases.rageid AND rageid > 0 LIMIT 1) AS tvreleasetitle from releases left outer join groups on groups.ID = releases.groupID left outer join category c on c.ID = releases.categoryID left outer join category cp on cp.ID = c.parentID left outer join musicinfo on musicinfo.ID = releases.musicinfoID left outer join bookinfo on bookinfo.ID = releases.bookinfoID left outer join movieinfo on movieinfo.imdbID = releases.imdbID left join genres music_genre on music_genre.ID = musicinfo.genreID where %s", $gsql);

		return (is_array($guid)) ? $db->query($sql) : $db->queryOneRow($sql);
	}

	/**
	 * Writes a zip file of an array of release guids directly to the stream
	 */
	public function getZipped($guids)
	{
		$s = new Sites();
		$nzb = new NZB;
		$site = $s->get();
		$zipfile = new zipfile();

		foreach ($guids as $guid) {
			$nzbpath = $nzb->getNZBPath($guid, $site->nzbpath);

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
	 * Removes an associated tvrage id from all releases using it.
	 */
	public function removeRageIdFromReleases($rageid)
	{
		$db = new DB();
		$res = $db->queryOneRow(sprintf("select count(ID) as num from releases where rageID = %d", $rageid));
		$ret = $res["num"];
		$db->exec(sprintf("update releases set rageID = -1, seriesfull = null, season = null, episode = null where rageID = %d", $rageid));

		return $ret;
	}

	/**
	 * Removes an associated tvdb id from all releases using it.
	 */
	public function removeThetvdbIdFromReleases($tvdbID)
	{
		$db = new DB();
		$res = $db->queryOneRow(sprintf("SELECT count(ID) AS num FROM releases WHERE tvdbID = %d", $tvdbID));
		$ret = $res["num"];
		$res = $db->exec(sprintf("update releases SET tvdbID = -1 where tvdbID = %d", $tvdbID));

		return $ret;
	}

	public function removeAnidbIdFromReleases($anidbID)
	{
		$db = new DB();
		$res = $db->queryOneRow(sprintf("SELECT count(ID) AS num FROM releases WHERE anidbID = %d", $anidbID));
		$ret = $res["num"];
		$db->exec(sprintf("update releases SET anidbID = -1, episode = null, tvtitle = null, tvairdate = null where anidbID = %d", $anidbID));

		return $ret;
	}

	public function getById($id)
	{
		$db = new DB();

		return $db->queryOneRow(sprintf("select releases.*, groups.name as group_name from releases left outer join groups on groups.ID = releases.groupID where releases.ID = %d ", $id));
	}

	public function getReleaseNfo($id, $incnfo = true)
	{
		$db = new DB();
		$selnfo = ($incnfo) ? ', uncompress(nfo) as nfo' : '';

		return $db->queryOneRow(sprintf("SELECT ID, releaseID" . $selnfo . " FROM releasenfo where releaseID = %d AND nfo IS NOT NULL", $id));
	}

	public function updateGrab($guid)
	{
		$db = new DB();
		$db->exec(sprintf("update releases set grabs = grabs + 1 where guid = %s", $db->escapeString($guid)));
	}

	function processReleases()
	{
		require_once(WWW_DIR . "/lib/binaries.php");

		$db = new DB;
		$currTime_ori = $db->queryOneRow("SELECT NOW() as now");
		$cat = new Categorize();
		$nzb = new Nzb();
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


		echo "\nStage 3 : Creating releases from complete binaries\n";
		//
		// Get out all distinct relname, group from binaries of STAGE2
		//
		$result = $db->queryDirect(sprintf("SELECT relname, groupID, g.name as group_name, fromname, max(categoryID) as categoryID, max(regexID) as regexID, max(reqID) as reqID, MAX(date) as date, count(binaries.ID) as parts from binaries inner join groups g on g.ID = binaries.groupID where procstat = %d and relname is not null group by relname, g.name, groupID, fromname ORDER BY COUNT(binaries.ID) desc", Releases::PROCSTAT_READYTORELEASE));
		while ($row = $db->getAssocArray($result)) {
			$relguid = md5(uniqid());

			//
			// Get categoryID if one has been allocated to this
			//
			if ($row["categoryID"] != "")
				$catId = $row["categoryID"];
			else
				$catId = $cat->determineCategory($row["group_name"], $row["relname"]);

			// Clean release name
			$releaseCleaning = new ReleaseCleaning();
			$cleanRelName = $this->cleanReleaseName($row['relname']);
			$cleanedName = $releaseCleaning->releaseCleaner(
				$row['subject'], $row['fromname'], $row['filesize'], $row['group_name']
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
			$relid = $this->insertRelease($cleanRelName, $db->escapeString(utf8_encode($cleanedName)), $row["parts"], $row["groupID"], $relguid, $catId, $row["regexID"], $row["date"], $row["fromname"], $row["reqID"], $page->site, Enzebe::NZB_NONE, $properName === true ? 1 : 0, $isReqID, $prehashID);
			var_dump($relid);
			//
			// Tag every binary for this release with its parent release id
			//
			$db->exec(sprintf("update binaries set procstat = %d, releaseID = %d where relname = %s and procstat = %d and groupID = %d and fromname=%s",
					Releases::PROCSTAT_RELEASED, $relid, $db->escapeString($row["relname"]), Releases::PROCSTAT_READYTORELEASE, $row["groupID"], $db->escapeString($row["fromname"])
				)
			);

			//
			// Write the nzb to disk
			//
			$nzbfile = $nzb->getNZBPath($relguid, $page->site->nzbpath, true);
			$nzb->writeNZBforreleaseID($relid, $cleanRelName, $catId, $nzbfile);

			//
			// Remove used binaries
			//
			$db->exec(sprintf("DELETE parts, binaries FROM parts JOIN binaries ON binaries.ID = parts.binaryID WHERE releaseID = %d ", $relid));

			//
			// If nzb successfully written, then load it and get size completion from it
			//
			$nzbInfo = new nzbInfo;
			if (!$nzbInfo->loadFromFile($nzbfile)) {
				echo "Stage 3 : Failed to write nzb file (bad perms?) " . $nzbfile . "\n";
				//copy($nzbfile, "./ERRORNZB_".$relguid);
				$this->delete($relid);
			} else {
				// Check if gid already exists
				$dupes = $db->queryOneRow(sprintf("SELECT EXISTS(SELECT 1 FROM releases WHERE gid = %s) as total", $db->escapeString($nzbInfo->gid)));
				if ($dupes['total'] > 0) {
					echo "Stage 3 : Duplicate - " . $cleanRelName . "\n";
					$this->delete($relid);
				} else {
					$db->exec(sprintf("update releases set totalpart = %d, size = %s, completion = %d, GID=%s where ID = %d", $nzbInfo->filecount, $nzbInfo->filesize, $nzbInfo->completion, $db->escapeString($nzbInfo->gid), $relid));
					echo "Stage 3 : Added release " . $cleanRelName . "\n";

					//Increment new release count
					$retcount++;
				}
			}
		}

		//
		// Delete any releases under the minimum completion percent.
		//
		if ($page->site->completionpercent != 0) {
			echo "Stage 4 : Deleting releases less than " . $page->site->completionpercent . " complete\n";
			$result = $db->query(sprintf("select ID from releases where completion > 0 and completion < %d", $page->site->completionpercent));
			foreach ($result as $row)
				$this->delete($row["ID"]);
		}

		//
		// Delete releases whos minsize is less than the site or group minimum
		//
		$result = $db->query("select releases.ID from releases left outer join (SELECT g.ID, coalesce(g.minsizetoformrelease, s.minsizetoformrelease) as minsizetoformrelease FROM groups g inner join ( select value as minsizetoformrelease from site where setting = 'minsizetoformrelease' ) s ) x on x.ID = releases.groupID where minsizetoformrelease != 0 and releases.size < minsizetoformrelease");
		if (count($result) > 0) {
			echo "Stage 4 : Deleting " . count($result) . " release(s) where size is smaller than minsize for site/group\n";
			foreach ($result as $row)
				$this->delete($row["ID"]);
		}

		$result = $db->query("select releases.ID, name, categoryID, size FROM releases JOIN (
						select
						catc.ID,
						case when catc.minsizetoformrelease = 0 then catp.minsizetoformrelease else catc.minsizetoformrelease end as minsizetoformrelease,
						case when catc.maxsizetoformrelease = 0 then catp.maxsizetoformrelease else catc.maxsizetoformrelease end as maxsizetoformrelease
						from category catp join category catc on catc.parentID = catp.ID
						where (catc.minsizetoformrelease != 0 or catc.maxsizetoformrelease != 0) or (catp.minsizetoformrelease != 0 or catp.maxsizetoformrelease != 0)
						) x on x.ID = releases.categoryID
						where
						(size < minsizetoformrelease and minsizetoformrelease != 0) or
						(size > maxsizetoformrelease and maxsizetoformrelease != 0)"
		);

		if (count($result) > 0) {
			echo "Stage 4 : Deleting release(s) not matching category min/max size ...\n";
			foreach ($result as $r) {
				$this->delete($r['ID']);
			}
		}

		echo "Stage 5 : Post processing is done in tmux\n";
		//$postprocess = new PostProcess(true);
		//$postprocess->processAll();

		//
		// aggregate the releasefiles upto the releases.
		//
		echo "Stage 6 : Aggregating Files\n";
		$db->exec("update releases INNER JOIN (SELECT releaseID, COUNT(ID) AS num FROM releasefiles GROUP BY releaseID) b ON b.releaseID = releases.ID and releases.rarinnerfilecount = 0 SET rarinnerfilecount = b.num");

		// Remove the binaries and parts used to form releases, or that are duplicates.
		//
		if ($page->site->partsdeletechunks > 0) {
			echo "Stage 7 : Chunk deleting unused binaries and parts";
			$query = sprintf("SELECT parts.ID as partsID,binaries.ID as binariesID FROM parts
						LEFT JOIN binaries ON binaries.ID = parts.binaryID
						WHERE binaries.dateadded < %s - INTERVAL %d HOUR LIMIT 0,%d",
				$db->escapeString($currTime_ori["now"]), ceil($page->site->rawretentiondays * 24),
				$page->site->partsdeletechunks
			);

			$cc = 0;
			$done = false;
			while (!$done) {
				$dd = $cc;
				$result = $db->query($query);
				if (count($result) > 0) {
					$pID = array();
					$bID = array();
					foreach ($result as $row) {
						$pID[] = $row['partsID'];
						$bID[] = $row['binariesID'];
					}
					$pID = '(' . implode(',', $pID) . ')';
					$bID = '(' . implode(',', $bID) . ')';
					$fr = $db->exec("DELETE FROM parts WHERE ID IN {$pID}");
					if ($fr > 0) {
						$cc += $fr;
						$cc += $db->exec("DELETE FROM binaries WHERE ID IN {$bID}");
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
			echo "\nStage 7 : Complete - " . $cc . " rows affected\n";
		} else {
			echo "Stage 7 : Deleting unused binaries and parts\n";
			$db->exec(sprintf("DELETE parts, binaries FROM parts JOIN binaries ON binaries.ID = parts.binaryID
			WHERE binaries.dateadded < %s - INTERVAL %d HOUR", $db->escapeString($currTime_ori["now"]), ceil($page->site->rawretentiondays * 24)
				)
			);
		}

		//
		// User/Request housekeeping, should ideally move this to its own section, but it needs to be done automatically.
		//
		$users = new Users;
		$users->pruneRequestHistory($page->site->userdownloadpurgedays);

		echo "Done    : Added " . $retcount . " releases\n\n";

		return $retcount;
	}

	public function insertRelease($cleanRelName, $cleanedName, $parts, $group, $guid, $catId, $regexID, $date, $fromname, $reqID, $site, $nzbstatus, $isrenamed, $isReqID, $prehashID)
	{
		$db = new DB();

		if ($regexID == "")
			$regexID = " null ";

		if ($reqID != "")
			$reqID = $db->escapeString($reqID);
		else
			$reqID = " null ";

		$sql = sprintf("insert into releases (name, searchname, totalpart, groupID, adddate, guid, categoryID, regexID, rageID, postdate, fromname, size, reqID, passwordstatus, completion, haspreview, nfostatus, nzbstatus,
					isrenamed, iscategorized, reqidstatus, prehashID)
                    values (%s, %s, %d, %d, now(), %s, %d, %s, -1, %s, %s, 0, %s, %d, 100, %d, %d, %d, %d, 1, %d, %d)",
			$db->escapeString($cleanRelName), $cleanedName, $parts, $group, $db->escapeString($guid), $catId, $regexID,
			$db->escapeString($date), $db->escapeString($fromname), $reqID, ($site->checkpasswordedrar > 0 ? -1 : 0), -1, -1,
			$db->escapeString($nzbstatus), $db->escapeString($isrenamed), $db->escapeString($isReqID), $db->escapeString($prehashID)
		);
		var_dump($sql);

		$relid = $db->queryInsert($sql);
		var_dump($relid);

		return $relid;
	}

	public function cleanReleaseName($relname)
	{
		$cleanArr = array('#', '@', '$', '%', '^', '§', '¨', '©', 'Ö');

		$relname = str_replace($cleanArr, '', $relname);
		$relname = str_replace('_', ' ', $relname);

		return $relname;
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

		$util = new Utility();
		$xml = $util->getUrl($url);

		if ($xml === false || preg_match('/no feed/i', $xml))
			return "no feed";
		else {
			if ($xml != "") {
				$xmlObj = @simplexml_load_string($xml);
				$arrXml = $util->objectsIntoArray($xmlObj);

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

	public function checkRegexesUptoDate($url, $rev, $nnid)
	{
		if ($url != "") {
			if ($nnid != "")
				$nnid = "?newznabID=" . $nnid . "&rev=" . $rev;

			$util = new Utility();
			$regfile = $util->getUrl($url . $nnid, "get", "", "gzip");
			if ($regfile !== false && $regfile != "") {
				/*$Rev: 728 $*/
				if (preg_match('/\/\*\$Rev: (\d{3,4})/i', $regfile, $matches)) {
					$serverrev = intval($matches[1]);
					if ($serverrev > $rev) {
						$db = new DB();
						$site = new Sites;

						$queries = explode(";", $regfile);
						$queries = array_map("trim", $queries);
						foreach ($queries as $q) {
							if ($q) {
								$db->exec($q);
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

	public function getTopDownloads()
	{
		$db = new DB();

		return $db->query("SELECT ID, searchname, guid, adddate, grabs FROM releases
							where grabs > 0
							ORDER BY grabs DESC
							LIMIT 10"
		);
	}

	public function getTopComments()
	{
		$db = new DB();

		return $db->query("SELECT ID, guid, searchname, adddate, comments FROM releases
							where comments > 0
							ORDER BY comments DESC
							LIMIT 10"
		);
	}

	public function getRecentlyAdded()
	{
		$db = new DB();

		return $db->query("SELECT concat(cp.title, ' > ', category.title) as title, COUNT(*) AS count
                            FROM category
                            left outer join category cp on cp.ID = category.parentID
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
		$db = new DB();

		return $db->query(
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
		$db = new DB();

		return $db->query(
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
		$db = new DB();

		return $db->query(
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
		$db = new DB();

		return $db->query(
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
		$db = new DB();

		return $db->query(
			"SELECT DISTINCT (a.bookinfoID),
				guid, name, b.title, searchname, size, completion,
				postdate, categoryID, comments, grabs, url, c.cover, c.title as booktitle, c.author
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
}
