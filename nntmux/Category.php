<?php

namespace nntmux;

use nntmux\db\DB;
use App\Models\Category as CategoryModel;

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
            self::OTHER_MISC,
        ];

    /**
     * Temporary category while we sort through the name.
     * @var int
     */
    protected $tmpCat = self::OTHER_MISC;

    /**
     * @var DB
     */
    public $pdo;

    /**
     * Construct.
     *
     * @param array $options Class instances.
     * @throws \Exception
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
     * Parse category search constraints.
     *
     * @param array $cat
     *
     * @return string $catsrch
     */
    public function getCategorySearch(array $cat = []): string
    {
        $categories = [];

        // If multiple categories were sent in a single array position, slice and add them
        if (strpos($cat[0], ',') !== false) {
            $tmpcats = explode(',', $cat[0]);
            // Reset the category to the first comma separated value in the string
            $cat[0] = $tmpcats[0];
            // Add the remaining categories in the string to the original array
            foreach (array_slice($tmpcats, 1) as $tmpcat) {
                $cat[] = $tmpcat;
            }
        }

        foreach ($cat as $category) {
            if ($category !== -1 && $this->isParent($category)) {
                foreach ($this->getChildren($category) as $child) {
                    $categories[] = $child['id'];
                }
            } elseif ($category > 0) {
                $categories[] = $category;
            }
        }

        $catCount = count($categories);

        switch ($catCount) {
            //No category constraint
            case 0:
                $catsrch = ' AND 1=1 ';
                break;
            // One category constraint
            case 1:
                $catsrch = $categories[0] !== -1 ? ' AND r.categories_id = '.$categories[0] : '';
                break;
            // Multiple category constraints
            default:
                $catsrch = ' AND r.categories_id IN ('.implode(', ', $categories).') ';
                break;
        }

        return $catsrch;
    }

    /**
     * Returns a concatenated list of other categories.
     *
     * @return string
     */
    public static function getCategoryOthersGroup(): string
    {
        return implode(
            ',',
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
            ]
        );
    }

    /**
     * @param $category
     *
     * @return mixed
     */
    public static function getCategoryValue($category)
    {
        return constant('self::'.$category);
    }

    /**
     * Check if category is parent.
     *
     * @param $cid
     *
     * @return bool
     */
    public function isParent($cid): bool
    {
        $ret = CategoryModel::query()->where(['id' => $cid, 'parentid' => null])->first();

        return $ret !== null;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getFlat()
    {
        return CategoryModel::query()->get();
    }

    /**
     * Get children of a parent category.
     *
     *
     * @param $cid
     * @return mixed
     */
    public function getChildren($cid)
    {
        return CategoryModel::find($cid)->children;
    }

    /**
     * Get names of enabled parent categories.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getEnabledParentNames()
    {
        return CategoryModel::query()->where(['parentid' => null, 'status' => 1])->get(['title']);
    }

    /**
     * Returns category ID's for site disabled categories.
     *
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getDisabledIDs()
    {
        return CategoryModel::query()->where('status', '=', 2)->orWhere(['status' => 2, 'parentid' => null])->get(['id']);
    }

    /**
     * Get a category row by its id.
     *
     *
     * @param $id
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getById($id)
    {
        return CategoryModel::query()->where('id', $id)->get();
    }

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
            return CategoryModel::query()->whereIn('id', $ids)->get();
        }

        return false;
    }

    /**
     * Return the parent and category name from the supplied categoryID.
     *
     *
     * @param $ID
     * @return string
     */
    public function getNameByID($ID)
    {
        $cat = CategoryModel::query()->where('id', $ID)->first();

        return $cat !== null ? $cat->parent->title.' -> '.$cat->title : '';
    }

    /**
     * Update a category.
     *
     *
     * @param $id
     * @param $status
     * @param $desc->update
     * @param $disablepreview
     * @param $minsize
     * @param $maxsize
     * @return int
     */
    public function update($id, $status, $desc, $disablepreview, $minsize, $maxsize): int
    {
        return CategoryModel::query()->where('id', $id)->update(
            [
                'disablepreview' => $disablepreview,
                'status' => $status,
                'minsizetoformrelease' => $minsize,
                'maxsizetoformrelease' => $maxsize,
                'description' => $desc,
            ]
        );
    }

    /**
     * @param array $excludedcats
     *
     * @param array $roleexcludedcats
     *
     * @return array
     */
    public function getForMenu(array $excludedcats = [], array $roleexcludedcats = []): array
    {
        $ret = [];

        $exccatlist = '';
        if (count($excludedcats) > 0 && count($roleexcludedcats) == 0) {
            $exccatlist = ' AND id NOT IN ('.implode(',', $excludedcats).')';
        } elseif (count($excludedcats) > 0 && count($roleexcludedcats) > 0) {
            $exccatlist = ' AND id NOT IN ('.implode(',', $excludedcats).','.implode(',', $roleexcludedcats).')';
        } elseif (count($excludedcats) === 0 && count($roleexcludedcats) > 0) {
            $exccatlist = ' AND id NOT IN ('.implode(',', $roleexcludedcats).')';
        }

        $arr = $this->pdo->query(
            sprintf('SELECT * FROM categories WHERE status = %d %s', self::STATUS_ACTIVE, $exccatlist),
            true,
            NN_CACHE_EXPIRY_LONG
        );

        foreach ($arr as $key => $val) {
            if ($val['id'] === '0') {
                $item = $arr[$key];
                unset($arr[$key]);
                $arr[] = $item;
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
                if ($a['parentid'] === $parent['id']) {
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
    public function getForSelect($blnIncludeNoneSelected = true): array
    {
        $categories = $this->getCategories();
        $temp_array = [];

        if ($blnIncludeNoneSelected) {
            $temp_array[-1] = '--Please Select--';
        }

        foreach ($categories as $category) {
            $temp_array[$category['id']] = $category['title'];
        }

        return $temp_array;
    }

    /**
     * @param bool $activeonly
     * @param array $excludedcats
     * @return string|static
     */
    public function getCategories($activeonly = false, array $excludedcats = [])
    {
        return $this->pdo->query(
            "SELECT c.id, CONCAT(cp.title, ' > ',c.title) AS title, cp.id AS parentid, c.status
			FROM categories c
			INNER JOIN categories cp ON cp.id = c.parentid ".
            (
                $activeonly ?
                sprintf(
                    ' WHERE c.status = %d %s ',
                    self::STATUS_ACTIVE,
                    (count($excludedcats) > 0 ? ' AND c.id NOT IN ('.implode(',', $excludedcats).')' : '')
                ) : ''
            ).
            ' ORDER BY c.id'
        );
    }
}
