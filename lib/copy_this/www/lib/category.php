<?php
require_once(WWW_DIR . "/lib/framework/db.php");
require_once(WWW_DIR . "lib/Categorize.php");

/**
 * This class manages the site wide categories.
 */
class Category
{
	const CAT_GAME_NDS = 1010;
	const CAT_GAME_PSP = 1020;
	const CAT_GAME_WII = 1030;
	const CAT_GAME_XBOX = 1040;
	const CAT_GAME_XBOX360 = 1050;
	const CAT_GAME_WIIWARE = 1060;
	const CAT_GAME_XBOX360DLC = 1070;
	const CAT_GAME_PS3 = 1080;
	const CAT_GAME_OTHER = 1090;
	const CAT_GAME_3DS = 1110;
	const CAT_GAME_PSVITA = 1120;
	const CAT_GAME_WIIU = 1130;
	const CAT_GAME_XBOXONE = 1140;
	const CAT_GAME_PS4 = 1180;
	const CAT_MOVIE_FOREIGN = 2010;
	const CAT_MOVIE_OTHER = 2020;
	const CAT_MOVIE_SD = 2030;
	const CAT_MOVIE_HD = 2040;
	const CAT_MOVIE_3D = 2050;
	const CAT_MOVIE_BLURAY = 2060;
	const CAT_MOVIE_DVD = 2070;
	const CAT_MOVIE_WEBDL = 2080;
	const CAT_MUSIC_MP3 = 3010;
	const CAT_MUSIC_VIDEO = 3020;
	const CAT_MUSIC_AUDIOBOOK = 3030;
	const CAT_MUSIC_LOSSLESS = 3040;
	const CAT_MUSIC_OTHER = 3050;
	const CAT_MUSIC_FOREIGN = 3060;
	const CAT_PC_0DAY = 4010;
	const CAT_PC_ISO = 4020;
	const CAT_PC_MAC = 4030;
	const CAT_PC_MOBILEOTHER = 4040;
	const CAT_PC_GAMES = 4050;
	const CAT_PC_MOBILEIOS = 4060;
	const CAT_PC_MOBILEANDROID = 4070;
	const CAT_TV_WEBDL = 5010;
	const CAT_TV_FOREIGN = 5020;
	const CAT_TV_SD = 5030;
	const CAT_TV_HD = 5040;
	const CAT_TV_OTHER = 5050;
	const CAT_TV_SPORT = 5060;
	const CAT_TV_ANIME = 5070;
	const CAT_TV_DOCU = 5080;
	const CAT_XXX_DVD = 6010;
	const CAT_XXX_WMV = 6020;
	const CAT_XXX_XVID = 6030;
	const CAT_XXX_X264 = 6040;
	const CAT_XXX_PACK = 6050;
	const CAT_XXX_IMAGESET = 6060;
	const CAT_XXX_OTHER = 6070;
	const CAT_BOOK_MAGS = 7010;
	const CAT_BOOK_EBOOK = 7020;
	const CAT_BOOK_COMICS = 7030;
	const CAT_BOOK_TECHNICAL = 7040;
	const CAT_BOOK_OTHER = 7050;
	const CAT_BOOK_FOREIGN = 7060;
	const CAT_MISC_OTHER = 8010;
	const CAT_MISC_HASHED = 8020;
	const CAT_PARENT_GAME = 1000;
	const CAT_PARENT_MOVIE = 2000;
	const CAT_PARENT_MUSIC = 3000;
	const CAT_PARENT_PC = 4000;
	const CAT_PARENT_TV = 5000;
	const CAT_PARENT_XXX = 6000;
	const CAT_PARENT_BOOK = 7000;
	const CAT_PARENT_MISC = 8000;
	const CAT_NOT_DETERMINED = 7900;
	const STATUS_INACTIVE = 0;
	const STATUS_ACTIVE = 1;
	const STATUS_DISABLED = 2;

	private $tmpCat = 0;

	/**
	 * Get a list of categories.
	 */
	public function get($activeonly = false, $excludedcats = array())
	{
		$db = new DB();

		$exccatlist = "";
		if (count($excludedcats) > 0)
			$exccatlist = " and c.ID not in (" . implode(",", $excludedcats) . ")";

		$act = "";
		if ($activeonly)
			$act = sprintf(" where c.status = %d ", Category::STATUS_ACTIVE);

		if ($exccatlist != "")
			$act .= $exccatlist;

		return $db->query("select c.ID, concat(cp.title, ' > ',c.title) as title, cp.ID as parentID, c.status from category c inner join category cp on cp.ID = c.parentID " . $act . " ORDER BY c.ID", true);
	}

	/**
	 * Parse category search constraints
	 *
	 * @param array $cat
	 *
	 * @return string $catsrch
	 */
	public function getCategorySearch($cat = array())
	{
		$catsrch = ' (';

		foreach ($cat as $category) {

			$chlist = '-99';

			if ($category != -1 && $this->isParent($category)) {
				$children = $this->getChildren($category);

				foreach ($children as $child) {
					$chlist .= ', ' . $child['ID'];
				}
			}

			if ($chlist != '-99') {
				$catsrch .= ' r.categoryID IN (' . $chlist . ') OR ';
			} else {
				$catsrch .= sprintf(' r.categoryID = %d OR ', $category);
			}
			$catsrch .= '1=2 )';
		}

		return $catsrch;
	}

	/**
	 * Determine if a category is a parent.
	 */
	public function isParent($cid)
	{
		$db = new DB();
		$ret = $db->queryOneRow(sprintf("select count(*) as count from category where ID = %d and parentID is null", $cid), true);
		if ($ret['count'])
			return true;
		else
			return false;
	}

	/**
	 * Get a list of categories and their parents.
	 */
	public function getFlat($activeonly = false)
	{
		$db = new DB();
		$act = "";
		if ($activeonly)
			$act = sprintf(" where c.status = %d ", Category::STATUS_ACTIVE);

		return $db->query("select c.*, (SELECT title FROM category WHERE ID=c.parentID) AS parentName from category c " . $act . " ORDER BY c.ID");
	}

	/**
	 * Get a list of all child categories for a parent.
	 */
	public function getChildren($cid)
	{
		$db = new DB();

		return $db->query(sprintf("select c.* from category c where parentID = %d", $cid), true);
	}

	/**
	 * Get names of enabled parent categories.
	 *
	 * @return array
	 */
	public function getEnabledParentNames()
	{
		$db = new DB();

		return $db->query("SELECT title FROM category WHERE parentID IS NULL AND status = 1");
	}

	/**
	 * Get a category row by its ID.
	 */
	public function getById($id)
	{
		$db = new DB();

		return $db->queryOneRow(sprintf("SELECT c.disablepreview, c.ID, c.description, c.minsizetoformrelease, c.maxsizetoformrelease, CONCAT(COALESCE(cp.title,'') , CASE WHEN cp.title IS NULL THEN '' ELSE ' > ' END , c.title) as title, c.status, c.parentID from category c left outer join category cp on cp.ID = c.parentID where c.ID = %d", $id));
	}

	/*
	* Return min/max size range (in array(min, max)) otherwise, none is returned
	* if no size restrictions are set
	*/
	public function getSizeRangeById($id)
	{
		$db = new DB();
		$res = $db->queryOneRow(sprintf("SELECT c.minsizetoformrelease, c.maxsizetoformrelease, cp.minsizetoformrelease as p_minsizetoformrelease, cp.maxsizetoformrelease as p_maxsizetoformrelease" .
				" from category c left outer join category cp on cp.ID = c.parentID where c.ID = %d", $id
			)
		);
		if (!$res)
			return null;

		$min = intval($res['minsizetoformrelease']);
		$max = intval($res['maxsizetoformrelease']);
		if ($min == 0 && $max == 0) {
			# Size restriction disabled; now check parent
			$min = intval($res['p_minsizetoformrelease']);
			$max = intval($res['p_maxsizetoformrelease']);
			if ($min == 0 && $max == 0) {
				# no size restriction
				return null;
			} else if ($max > 0) {
				$min = 0;
				$max = intval($res['p_maxsizetoformrelease']);
			} else {
				$min = intval($res['p_minsizetoformrelease']);
				$max = PHP_INT_MAX;
			}
		} else if ($max > 0) {
			$min = 0;
			$max = intval($res['maxsizetoformrelease']);
		} else {
			$min = intval($res['minsizetoformrelease']);
			$max = PHP_INT_MAX;
		}

		# If code reaches here, then content is enabled
		return array('min' => $min, 'max' => $max);
	}

	/**
	 * Get a list of categories by an array of IDs.
	 */
	public function getByIds($ids)
	{
		$db = new DB();

		return $db->query(sprintf("SELECT concat(cp.title, ' > ',c.title) as title from category c inner join category cp on cp.ID = c.parentID where c.ID in (%s)", implode(',', $ids)));
	}

	public function getNameByID($ID)
	{
		$db = new DB();
		$parent = $db->queryOneRow(sprintf("SELECT title FROM category WHERE ID = %d", substr($ID, 0, 1) . "000"));
		$cat = $db->queryOneRow(sprintf("SELECT title FROM category WHERE ID = %d", $ID));

		return $parent["title"] . " " . $cat["title"];
	}

	/**
	 * Update a category.
	 */
	public function update($id, $status, $desc, $disablepreview, $minsize, $maxsize)
	{
		$db = new DB();

		return $db->queryExec(sprintf("update category set disablepreview = %d, status = %d, minsizetoformrelease = %d, maxsizetoformrelease = %d, description = %s where ID = %d", $disablepreview, $status, $minsize, $maxsize, $db->escapeString($desc), $id));
	}

	/**
	 * Get the categories in a format for use by the headermenu.tpl.
	 */
	public function getForMenu($excludedcats = array())
	{
		$db = new DB();
		$ret = array();

		$exccatlist = "";
		if (count($excludedcats) > 0)
			$exccatlist = " and ID not in (" . implode(",", $excludedcats) . ")";

		$arr = $db->query(sprintf("select * from category where status = %d %s", Category::STATUS_ACTIVE, $exccatlist), true);
		foreach ($arr as $a)
			if ($a["parentID"] == "")
				$ret[] = $a;

		foreach ($ret as $key => $parent) {
			$subcatlist = array();
			$subcatnames = array();
			foreach ($arr as $a) {
				if ($a["parentID"] == $parent["ID"]) {
					$subcatlist[] = $a;
					$subcatnames[] = $a["title"];
				}
			}

			if (count($subcatlist) > 0) {
				array_multisort($subcatnames, SORT_ASC, $subcatlist);
				$ret[$key]["subcatlist"] = $subcatlist;
			} else {
				unset($ret[$key]);
			}
		}

		return $ret;
	}

	/**
	 * Return a list of categories for use in a dropdown.
	 */
	public function getForSelect($blnIncludeNoneSelected = true)
	{
		$categories = $this->get();
		$temp_array = array();

		if ($blnIncludeNoneSelected) {
			$temp_array[-1] = "--Please Select--";
		}

		foreach ($categories as $category)
			$temp_array[$category["ID"]] = $category["title"];

		return $temp_array;
	}

}