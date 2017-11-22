<?php

namespace nntmux;

use nntmux\db\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Models\Category as CategoryModel;
use Illuminate\Support\Facades\Cache as CacheFacade;

/**
 * This class manages the site wide categories.
 */
class Category
{
    /**
     * Category constants.
     * Do NOT use the values, as they may change, always use the constant - that's what it's for.
     */
    public const OTHER_MISC = '0010';
    public const OTHER_HASHED = '0020';
    public const GAME_NDS = '1010';
    public const GAME_PSP = '1020';
    public const GAME_WII = '1030';
    public const GAME_XBOX = '1040';
    public const GAME_XBOX360 = '1050';
    public const GAME_WIIWARE = '1060';
    public const GAME_XBOX360DLC = '1070';
    public const GAME_PS3 = '1080';
    public const GAME_OTHER = '1999';
    public const GAME_3DS = '1110';
    public const GAME_PSVITA = '1120';
    public const GAME_WIIU = '1130';
    public const GAME_XBOXONE = '1140';
    public const GAME_PS4 = '1180';
    public const MOVIE_FOREIGN = '2010';
    public const MOVIE_OTHER = '2999';
    public const MOVIE_SD = '2030';
    public const MOVIE_HD = '2040';
    public const MOVIE_UHD = '2045';
    public const MOVIE_3D = '2050';
    public const MOVIE_BLURAY = '2060';
    public const MOVIE_DVD = '2070';
    public const MOVIE_WEBDL = '2080';
    public const MUSIC_MP3 = '3010';
    public const MUSIC_VIDEO = '3020';
    public const MUSIC_AUDIOBOOK = '3030';
    public const MUSIC_LOSSLESS = '3040';
    public const MUSIC_OTHER = '3999';
    public const MUSIC_FOREIGN = '3060';
    public const PC_0DAY = '4010';
    public const PC_ISO = '4020';
    public const PC_MAC = '4030';
    public const PC_PHONE_OTHER = '4040';
    public const PC_GAMES = '4050';
    public const PC_PHONE_IOS = '4060';
    public const PC_PHONE_ANDROID = '4070';
    public const TV_WEBDL = '5010';
    public const TV_FOREIGN = '5020';
    public const TV_SD = '5030';
    public const TV_HD = '5040';
    public const TV_UHD = '5045';
    public const TV_OTHER = '5999';
    public const TV_SPORT = '5060';
    public const TV_ANIME = '5070';
    public const TV_DOCU = '5080';
    public const XXX_DVD = '6010';
    public const XXX_WMV = '6020';
    public const XXX_XVID = '6030';
    public const XXX_X264 = '6040';
    public const XXX_CLIPHD = '6041';
    public const XXX_CLIPSD = '6042';
    public const XXX_UHD = '6045';
    public const XXX_PACK = '6050';
    public const XXX_IMAGESET = '6060';
    public const XXX_OTHER = '6999';
    public const XXX_SD = '6080';
    public const XXX_WEBDL = '6090';
    public const BOOKS_MAGAZINES = '7010';
    public const BOOKS_EBOOK = '7020';
    public const BOOKS_COMICS = '7030';
    public const BOOKS_TECHNICAL = '7040';
    public const BOOKS_FOREIGN = '7060';
    public const BOOKS_UNKNOWN = '7999';
    public const OTHER_ROOT = '0000';
    public const GAME_ROOT = '1000';
    public const MOVIE_ROOT = '2000';
    public const MUSIC_ROOT = '3000';
    public const PC_ROOT = '4000';
    public const TV_ROOT = '5000';
    public const XXX_ROOT = '6000';
    public const BOOKS_ROOT = '7000';
    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_DISABLED = 2;

    public const OTHERS_GROUP =
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
     * @var \nntmux\db\DB
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
            foreach (\array_slice($tmpcats, 1) as $tmpcat) {
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

        $catCount = \count($categories);

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
            self::OTHERS_GROUP
        );
    }

    /**
     * @param $category
     *
     * @return mixed
     */
    public static function getCategoryValue($category)
    {
        return \constant('self::'.$category);
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
        return CategoryModel::query()
            ->where(['parentid' => null, 'status' => 1])
            ->get(['title']);
    }

    /**
     * Returns category ID's for site disabled categories.
     *
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getDisabledIDs()
    {
        return CategoryModel::query()
            ->where('status', '=', 2)
            ->orWhere(['status' => 2, 'parentid' => null])
            ->get(['id']);
    }

    /**
     * Get a category row by its id.
     *
     *
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getById($id)
    {
        return CategoryModel::query()
            ->with('parent')
            ->where('id', $id)
            ->first();
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
        if (\count($ids) > 0) {
            $catIds = CacheFacade::get('categoryids');
            if ($catIds !== null) {
                return $catIds;
            }
            $catIds = CategoryModel::query()->whereIn('id', $ids)->get();
            $expiresAt = Carbon::now()->addSeconds(NN_CACHE_EXPIRY_LONG);
            CacheFacade::put('categoryids', $catIds, $expiresAt);

            return $catIds;
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
    public function getNameByID($ID): string
    {
        $cat = CategoryModel::query()->where('id', $ID)->first();

        return $cat !== null ? $cat->parent->title.' -> '.$cat->title : '';
    }

    /**
     * @param $title
     * @param $parent
     * @return bool|mixed
     */
    public function getIdByName($title, $parent)
    {
        $cat = CategoryModel::query()->where('title', $title)->with('parent.'.$parent)->first(['id']);

        return $cat !== null ? $cat->id : false;
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
     * @param array $excludedCats
     *
     * @param array $roleExcludedCats
     *
     * @return array
     */
    public function getForMenu(array $excludedCats = [], array $roleExcludedCats = []): array
    {
        $ret = [];

        $excCatList = '';
        if (\count($excludedCats) > 0 && \count($roleExcludedCats) === 0) {
            $excCatList = ' AND id NOT IN ('.implode(',', $excludedCats).')';
        } elseif (\count($excludedCats) > 0 && \count($roleExcludedCats) > 0) {
            $excCatList = ' AND id NOT IN ('.implode(',', $excludedCats).','.implode(',', $roleExcludedCats).')';
        } elseif (\count($excludedCats) === 0 && \count($roleExcludedCats) > 0) {
            $excCatList = ' AND id NOT IN ('.implode(',', $roleExcludedCats).')';
        }

        $sql = sprintf('SELECT * FROM categories WHERE status = %d %s', self::STATUS_ACTIVE, $excCatList);
        $arrsql = Cache::get(md5($sql));
        if ($arrsql !== null) {
            $arr = $arrsql;
        } else {
            $arr = $this->pdo->query($sql);
            $expiresAt = Carbon::now()->addSeconds(NN_CACHE_EXPIRY_LONG);
            Cache::put(md5($sql), $arr, $expiresAt);
        }

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

            if (\count($subcatlist) > 0) {
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
     * @param bool $activeOnly
     * @param array $excludedCats
     * @return string|static
     */
    public function getCategories($activeOnly = false, array $excludedCats = [])
    {
        return $this->pdo->query(
            "SELECT c.id, CONCAT(cp.title, ' > ',c.title) AS title, cp.id AS parentid, c.status
			FROM categories c
			INNER JOIN categories cp ON cp.id = c.parentid ".
            (
                $activeOnly ?
                sprintf(
                    ' WHERE c.status = %d %s ',
                    self::STATUS_ACTIVE,
                    (\count($excludedCats) > 0 ? ' AND c.id NOT IN ('.implode(',', $excludedCats).')' : '')
                ) : ''
            ).
            ' ORDER BY c.id'
        );
    }
}
