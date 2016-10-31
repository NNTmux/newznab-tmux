<?php
namespace nntmux;

use nntmux\db\DB;

/**
 * This class manages the site wide categories.
 */
class Category
{

	/**
	 * Category constants.
	 * Do NOT use the values, as they may change, always use the constant - that's what it's for.
	 */
	const OTHER_MISC = '0010';
	const OTHER_HASHED = '0020';
	const GAME_NDS = '1010';
	const GAME_PSP = '1020';
	const GAME_WII = '1030';
	const GAME_XBOX = '1040';
	const GAME_XBOX360 = '1050';
	const GAME_WIIWARE = '1060';
	const GAME_XBOX360DLC = '1070';
	const GAME_PS3 = '1080';
	const GAME_OTHER = '1999';
	const GAME_3DS = '1110';
	const GAME_PSVITA = '1120';
	const GAME_WIIU = '1130';
	const GAME_XBOXONE = '1140';
	const GAME_PS4 = '1180';
	const MOVIE_FOREIGN = '2010';
	const MOVIE_OTHER = '2999';
	const MOVIE_SD = '2030';
	const MOVIE_HD = '2040';
	const MOVIE_UHD = '2045';
	const MOVIE_3D = '2050';
	const MOVIE_BLURAY = '2060';
	const MOVIE_DVD = '2070';
	const MOVIE_WEBDL = '2080';
	const MUSIC_MP3 = '3010';
	const MUSIC_VIDEO = '3020';
	const MUSIC_AUDIOBOOK = '3030';
	const MUSIC_LOSSLESS = '3040';
	const MUSIC_OTHER = '3999';
	const MUSIC_FOREIGN = '3060';
	const PC_0DAY = '4010';
	const PC_ISO = '4020';
	const PC_MAC = '4030';
	const PC_PHONE_OTHER = '4040';
	const PC_GAMES = '4050';
	const PC_PHONE_IOS = '4060';
	const PC_PHONE_ANDROID = '4070';
	const TV_WEBDL = '5010';
	const TV_FOREIGN = '5020';
	const TV_SD = '5030';
	const TV_HD = '5040';
	const TV_UHD = '5045';
	const TV_OTHER = '5999';
	const TV_SPORT = '5060';
	const TV_ANIME = '5070';
	const TV_DOCU = '5080';
	const XXX_DVD = '6010';
	const XXX_WMV = '6020';
	const XXX_XVID = '6030';
	const XXX_X264 = '6040';
	const XXX_CLIPHD = '6041';
	const XXX_CLIPSD = '6042';
	const XXX_UHD = '6045';
	const XXX_PACK = '6050';
	const XXX_IMAGESET = '6060';
	const XXX_OTHER = '6999';
	const XXX_SD = '6080';
	const XXX_WEBDL = '6090';
	const BOOKS_MAGAZINES = '7010';
	const BOOKS_EBOOK = '7020';
	const BOOKS_COMICS = '7030';
	const BOOKS_TECHNICAL = '7040';
	const BOOKS_FOREIGN = '7060';
	const BOOKS_UNKNOWN = '7999';
	const OTHER_ROOT = '0000';
	const GAME_ROOT = '1000';
	const MOVIE_ROOT = '2000';
	const MUSIC_ROOT = '3000';
	const PC_ROOT = '4000';
	const TV_ROOT = '5000';
	const XXX_ROOT = '6000';
	const BOOKS_ROOT = '7000';
	const STATUS_INACTIVE = 0;
	const STATUS_ACTIVE = 1;
	const STATUS_DISABLED = 2;

	const OTHERS_GROUP =
		[
			self::BOOKS_UNKNOWN,
			self::GAME_OTHER,
			self::MOVIE_OTHER,
			self::MUSIC_OTHER,
			self::PC_PHONE_OTHER,
			self::TV_OTHER,
			self::OTHER_HASHED,
			self::XXX_OTHER,
			self::OTHER_MISC
		]
	;

	private $tmpCat = 0;

	/**
	 * @var \nntmux\db\Settings
	 */
	public $pdo;

	/**
	 * Construct.
	 *
	 * @param array $options Class instances.
	 */
	public function __construct(array $options = [])
	{
		$defaults = [
			'Settings' => null,
		];
		$options += $defaults;

		$this->pdo = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());
	}

	/**
	 * Parse category search constraints
	 *
	 * @param array $cat
	 *
	 * @return string $catsrch
	 */
	public function getCategorySearch(array $cat = [])
	{
		$catsrch = ' (';

		foreach ($cat as $category) {

			$chlist = '';

			if ($category != -1 && $this->isParent($category)) {
				$children = $this->getChildren($category);

				foreach ($children as $child) {
					$chlist .= "{$child['id']}, ";
				}
				$chlist = rtrim($chlist, ", ");
			}

			if ($chlist != '') {
				$catsrch .= " r.categories_id IN ({$chlist}) OR ";
			} else {
				$catsrch .= sprintf(' r.categories_id = %d OR ', $category);
			}
			$catsrch .= '1=2 )';
		}
		return $catsrch;
	}

	/**
	 * Returns a concatenated list of other categories
	 *
	 * @return string
	 */
	public static function getCategoryOthersGroup()
	{
		return implode(",",
			[
				self::BOOKS_UNKNOWN,
				self::GAME_OTHER,
				self::MOVIE_OTHER,
				self::MUSIC_OTHER,
				self::PC_PHONE_OTHER,
				self::TV_OTHER,
				self::OTHER_HASHED,
				self::XXX_OTHER,
				self::OTHER_MISC,
				self::OTHER_HASHED
			]
		);
	}

	public static function getCategoryValue($category)
	{
		return constant('self::' . $category);
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
			sprintf("SELECT id FROM categories WHERE id = %d AND parentid IS NULL", $cid),
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
		return $this->pdo->query("SELECT c.*, (SELECT title FROM categories WHERE id=c.parentid) AS parentName FROM categories c " . $act . " ORDER BY c.id");
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
			sprintf("SELECT c.* FROM categories c WHERE parentid = %d", $cid),
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
			"SELECT title FROM categories WHERE parentid IS NULL AND status = 1",
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
			"SELECT id FROM categories WHERE status = 2 OR parentid IN (SELECT id FROM categories WHERE status = 2 AND parentid IS NULL)",
			true, NN_CACHE_EXPIRY_LONG
		);
	}

	/**
	 * Get a category row by its id.
	 *
	 * @param $id
	 *
	 * @return array|bool
	 */
	public function getById($id)
	{

		return $this->pdo->queryOneRow(sprintf("SELECT c.disablepreview, c.id, c.description, c.minsizetoformrelease, c.maxsizetoformrelease, CONCAT(COALESCE(cp.title,'') , CASE WHEN cp.title IS NULL THEN '' ELSE ' > ' END , c.title) as title, c.status, c.parentid from categories c left outer join categories cp on cp.id = c.parentid where c.id = %d", $id));
	}

	public function getSizeRangeById($id)
	{
		$res = $this->pdo->queryOneRow(sprintf("SELECT c.minsizetoformrelease, c.maxsizetoformrelease, cp.minsizetoformrelease as p_minsizetoformrelease, cp.maxsizetoformrelease as p_maxsizetoformrelease" .
				" from categories c left outer join categories cp on cp.id = c.parentid where c.id = %d", $id
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
					FROM categories c
					INNER JOIN categories cp ON cp.id = c.parentid
					WHERE c.id IN (%s)", implode(',', $ids)
				), true, NN_CACHE_EXPIRY_LONG
			);
		} else {
			return false;
		}
	}

	/**
	 * Return the parent and category name from the supplied categoryID.
	 * @param $ID
	 *
	 * @return string
	 */
	public function getNameByID($ID)
	{
		$cat = $this->pdo->queryOneRow(
			sprintf("
				SELECT c.title AS ctitle, cp.title AS ptitle
				FROM categories c
				INNER JOIN categories cp ON c.parentid = cp.id
				WHERE c.id = %d",
				$ID
			)
		);
		return $cat["ptitle"] . " -> " . $cat["ctitle"];
	}

	/**
	 * Update a category.
	 *
	 * @param $id
	 * @param $status
	 * @param $desc
	 * @param $disablepreview
	 * @param $minsize
	 * @param $maxsize
	 *
	 * @return bool|\PDOStatement
	 */
	public function update($id, $status, $desc, $disablepreview, $minsize, $maxsize)
	{
		return $this->pdo->queryExec(sprintf("UPDATE categories SET disablepreview = %d, status = %d, minsizetoformrelease = %d, maxsizetoformrelease = %d, description = %s WHERE id = %d", $disablepreview, $status, $minsize, $maxsize, $this->pdo->escapeString($desc), $id));
	}

	/**
	 * @param array $excludedcats
	 *
	 * @return array
	 */
	public function getForMenu($excludedcats = [], $roleexcludedcats = [])
	{
		$ret = [];

		$exccatlist = '';
		if (count($excludedcats) > 0 && count($roleexcludedcats) == 0) {
			$exccatlist = ' AND id NOT IN (' . implode(',', $excludedcats) . ')';
		} elseif (count($excludedcats) > 0 && count($roleexcludedcats) > 0) {
			$exccatlist = ' AND id NOT IN (' . implode(',', $excludedcats) . ',' . implode(',', $roleexcludedcats) . ')';
		} elseif (count($excludedcats) == 0 && count($roleexcludedcats) > 0) {
			$exccatlist = ' AND id NOT IN (' . implode(',', $roleexcludedcats) . ')';
		}

		$arr = $this->pdo->query(
			sprintf('SELECT * FROM categories WHERE status = %d %s', Category::STATUS_ACTIVE, $exccatlist),
			true, NN_CACHE_EXPIRY_LONG
		);

		foreach($arr as $key => $val) {
			if($val['id'] == '0') {
				$item = $arr[$key];
				unset($arr[$key]);
				array_push($arr, $item);
				break;
			}
		}

		foreach ($arr as $a) {
			if (empty($a['parentid'])) {
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
	 *
	 * @param bool $blnIncludeNoneSelected
	 *
	 * @return array
	 */
	public function getForSelect($blnIncludeNoneSelected = true)
	{
		$categories = $this->getCategories();
		$temp_array = [];

		if ($blnIncludeNoneSelected) {
			$temp_array[-1] = "--Please Select--";
		}

		foreach ($categories as $category)
			$temp_array[$category["id"]] = $category["title"];

		return $temp_array;
	}

	/**
	 * Get array of categories in DB.
	 *
	 * @param bool  $activeonly
	 * @param array $excludedcats
	 *
	 * @return array
	 */
	public function getCategories($activeonly = false, $excludedcats = [])
	{
		return $this->pdo->query(
			"SELECT c.id, CONCAT(cp.title, ' > ',c.title) AS title, cp.id AS parentid, c.status
			FROM categories c
			INNER JOIN categories cp ON cp.id = c.parentid " .
			($activeonly ?
				sprintf(
					" WHERE c.status = %d %s ",
					Category::STATUS_ACTIVE,
					(count($excludedcats) > 0 ? " AND c.id NOT IN (" . implode(",", $excludedcats) . ")" : '')
				) : ''
			) .
			" ORDER BY c.id"
		);
	}
}
