<?php

use newznab\db\Settings;

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
	const CAT_XXX_CLIPHD = 6041;
	const CAT_XXX_CLIPSD = 6042;
	const CAT_XXX_PACK = 6050;
	const CAT_XXX_IMAGESET = 6060;
	const CAT_XXX_OTHER = 6070;
	const CAT_XXX_SD = 6080;
	const CAT_XXX_WEBDL = 6090;
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
	 * @var newznab\db\Settings
	 */
	public $pdo;

	/**
	 * Construct.
	 *
	 * @param array $options Class instances.
	 */
	public function __construct(array $options = array())
	{
		$defaults = [
			'Settings' => null,
		];
		$options += $defaults;

		$this->pdo = ($options['Settings'] instanceof Settings ? $options['Settings'] : new Settings());
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
					$chlist .= ', ' . $child['id'];
				}
			}

			if ($chlist != '-99') {
				$catsrch .= ' r.categoryid IN (' . $chlist . ') OR ';
			} else {
				$catsrch .= sprintf(' r.categoryid = %d OR ', $category);
			}
			$catsrch .= '1=2 )';
		}

		return $catsrch;
	}

	/**
	 * Check if category is parent.
	 *
	 * @param $cid
	 *
	 * @return bool
	 */
	public function isParent($cid)
	{
		$ret = $this->pdo->query(
			sprintf("SELECT id FROM category WHERE id = %d AND parentid IS NULL", $cid),
			true, NN_CACHE_EXPIRY_LONG
		);
		return (isset($ret[0]['id']));
	}

	/**
	 * @param bool $activeonly
	 *
	 * @return array
	 */
	public function getFlat($activeonly = false)
	{
		$act = "";
		if ($activeonly) {
			$act = sprintf(" WHERE c.status = %d ", Category::STATUS_ACTIVE);
		}
		return $this->pdo->query("SELECT c.*, (SELECT title FROM category WHERE id=c.parentid) AS parentName FROM category c " . $act . " ORDER BY c.id");
	}

	/**
	 * Get children of a parent category.
	 *
	 * @param $cid
	 *
	 * @return array
	 */
	public function getChildren($cid)
	{
		return $this->pdo->query(
			sprintf("SELECT c.* FROM category c WHERE parentid = %d", $cid),
			true, NN_CACHE_EXPIRY_LONG
		);
	}

	/**
	 * Get names of enabled parent categories.
	 * @return array
	 */
	public function getEnabledParentNames()
	{
		return $this->pdo->query(
			"SELECT title FROM category WHERE parentid IS NULL AND status = 1",
			true, NN_CACHE_EXPIRY_LONG
		);
	}

	/**
	 * Returns category ID's for site disabled categories.
	 *
	 * @return array
	 */
	public function getDisabledIDs()
	{
		return $this->pdo->query(
			"SELECT id FROM category WHERE status = 2 OR parentid IN (SELECT id FROM category WHERE status = 2 AND parentid IS NULL)",
			true, NN_CACHE_EXPIRY_LONG
		);
	}

	/**
	 * Get a category row by its id.
	 */
	public function getById($id)
	{
		$db = new newznab\db\Settings();

		return $db->queryOneRow(sprintf("SELECT c.disablepreview, c.id, c.description, c.minsizetoformrelease, c.maxsizetoformrelease, CONCAT(COALESCE(cp.title,'') , CASE WHEN cp.title IS NULL THEN '' ELSE ' > ' END , c.title) as title, c.status, c.parentid from category c left outer join category cp on cp.id = c.parentid where c.id = %d", $id));
	}

	public function getSizeRangeById($id)
	{
		$db = new newznab\db\Settings();
		$res = $db->queryOneRow(sprintf("SELECT c.minsizetoformrelease, c.maxsizetoformrelease, cp.minsizetoformrelease as p_minsizetoformrelease, cp.maxsizetoformrelease as p_maxsizetoformrelease" .
				" from category c left outer join category cp on cp.id = c.parentid where c.id = %d", $id
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

	/*
	* Return min/max size range (in array(min, max)) otherwise, none is returned
	* if no size restrictions are set
	*/

	/**
	 * Get multiple categories.
	 *
	 * @param array $ids
	 *
	 * @return array|bool
	 */
	public function getByIds($ids)
	{
		if (count($ids) > 0) {
			return $this->pdo->query(
				sprintf(
					"SELECT CONCAT(cp.title, ' > ',c.title) AS title
					FROM category c
					INNER JOIN category cp ON cp.id = c.parentid
					WHERE c.id IN (%s)", implode(',', $ids)
				), true, NN_CACHE_EXPIRY_LONG
			);
		} else {
			return false;
		}
	}

	public function getNameByID($ID)
	{
		$db = new newznab\db\Settings();
		$parent = $db->queryOneRow(sprintf("SELECT title FROM category WHERE id = %d", substr($ID, 0, 1) . "000"));
		$cat = $db->queryOneRow(sprintf("SELECT title FROM category WHERE id = %d", $ID));

		return $parent["title"] . " " . $cat["title"];
	}

	/**
	 * Update a category.
	 */
	public function update($id, $status, $desc, $disablepreview, $minsize, $maxsize)
	{
		$db = new newznab\db\Settings();

		return $db->queryExec(sprintf("update category set disablepreview = %d, status = %d, minsizetoformrelease = %d, maxsizetoformrelease = %d, description = %s where id = %d", $disablepreview, $status, $minsize, $maxsize, $db->escapeString($desc), $id));
	}

	/**
	 * @param array $excludedcats
	 *
	 * @return array
	 */
	public function getForMenu($excludedcats = [])
	{
		$ret = [];

		$exccatlist = '';
		if (count($excludedcats) > 0) {
			$exccatlist = ' AND id NOT IN (' . implode(',', $excludedcats) . ')';
		}

		$arr = $this->pdo->query(
			sprintf('SELECT * FROM category WHERE status = %d %s', Category::STATUS_ACTIVE, $exccatlist),
			true, NN_CACHE_EXPIRY_LONG
		);
		foreach ($arr as $a) {
			if ($a['parentid'] == '') {
				$ret[] = $a;
			}
		}

		foreach ($ret as $key => $parent) {
			$subcatlist = [];
			$subcatnames = [];
			foreach ($arr as $a) {
				if ($a['parentid'] == $parent['id']) {
					$subcatlist[] = $a;
					$subcatnames[] = $a['title'];
				}
			}

			if (count($subcatlist) > 0) {
				array_multisort($subcatnames, SORT_ASC, $subcatlist);
				$ret[$key]['subcatlist'] = $subcatlist;
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
			$temp_array[$category["id"]] = $category["title"];

		return $temp_array;
	}

	/**
	 * Get a list of categories.
	 */
	public function get($activeonly = false, $excludedcats = array())
	{
		$db = new newznab\db\Settings();

		$exccatlist = "";
		if (count($excludedcats) > 0)
			$exccatlist = " and c.id not in (" . implode(",", $excludedcats) . ")";

		$act = "";
		if ($activeonly)
			$act = sprintf(" where c.status = %d ", Category::STATUS_ACTIVE);

		if ($exccatlist != "")
			$act .= $exccatlist;

		return $db->query("select c.id, concat(cp.title, ' > ',c.title) as title, cp.id as parentid, c.status from category c inner join category cp on cp.id = c.parentid " . $act . " ORDER BY c.id", true);
	}

}