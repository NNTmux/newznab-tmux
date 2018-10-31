<?php

namespace App\Models;

use Watson\Rememberable\Rememberable;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Category.
 *
 * @property int $id
 * @property string $title
 * @property int|null $parentid
 * @property int $status
 * @property string|null $description
 * @property bool $disablepreview
 * @property int $minsizetoformrelease
 * @property int $maxsizetoformrelease
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Category[] $children
 * @property-read \App\Models\Category|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Release[] $releases
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Category whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Category whereDisablepreview($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Category whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Category whereMaxsizetoformrelease($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Category whereMinsizetoformrelease($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Category whereParentid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Category whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Category whereTitle($value)
 * @mixin \Eloquent
 */
class Category extends Model
{
    use Rememberable;

    /**
     * Category constants.
     * Do NOT use the values, as they may change, always use the constant - that's what it's for.
     */
    public const OTHER_MISC = 10;
    public const OTHER_HASHED = 20;
    public const GAME_NDS = 1010;
    public const GAME_PSP = 1020;
    public const GAME_WII = 1030;
    public const GAME_XBOX = 1040;
    public const GAME_XBOX360 = 1050;
    public const GAME_WIIWARE = 1060;
    public const GAME_XBOX360DLC = 1070;
    public const GAME_PS3 = 1080;
    public const GAME_OTHER = 1999;
    public const GAME_3DS = 1110;
    public const GAME_PSVITA = 1120;
    public const GAME_WIIU = 1130;
    public const GAME_XBOXONE = 1140;
    public const GAME_PS4 = 1180;
    public const MOVIE_FOREIGN = 2010;
    public const MOVIE_OTHER = 2999;
    public const MOVIE_SD = 2030;
    public const MOVIE_HD = 2040;
    public const MOVIE_UHD = 2045;
    public const MOVIE_3D = 2050;
    public const MOVIE_BLURAY = 2060;
    public const MOVIE_DVD = 2070;
    public const MOVIE_WEBDL = 2080;
    public const MOVIE_X265 = 2090;
    public const MUSIC_MP3 = 3010;
    public const MUSIC_VIDEO = 3020;
    public const MUSIC_AUDIOBOOK = 3030;
    public const MUSIC_LOSSLESS = 3040;
    public const MUSIC_OTHER = 3999;
    public const MUSIC_FOREIGN = 3060;
    public const PC_0DAY = 4010;
    public const PC_ISO = 4020;
    public const PC_MAC = 4030;
    public const PC_PHONE_OTHER = 4040;
    public const PC_GAMES = 4050;
    public const PC_PHONE_IOS = 4060;
    public const PC_PHONE_ANDROID = 4070;
    public const TV_WEBDL = 5010;
    public const TV_FOREIGN = 5020;
    public const TV_SD = 5030;
    public const TV_HD = 5040;
    public const TV_UHD = 5045;
    public const TV_OTHER = 5999;
    public const TV_SPORT = 5060;
    public const TV_ANIME = 5070;
    public const TV_DOCU = 5080;
    public const TV_X265 = 5090;
    public const XXX_DVD = 6010;
    public const XXX_WMV = 6020;
    public const XXX_XVID = 6030;
    public const XXX_X264 = 6040;
    public const XXX_CLIPHD = 6041;
    public const XXX_CLIPSD = 6042;
    public const XXX_UHD = 6045;
    public const XXX_PACK = 6050;
    public const XXX_IMAGESET = 6060;
    public const XXX_OTHER = 6999;
    public const XXX_SD = 6080;
    public const XXX_WEBDL = 6090;
    public const BOOKS_MAGAZINES = 7010;
    public const BOOKS_EBOOK = 7020;
    public const BOOKS_COMICS = 7030;
    public const BOOKS_TECHNICAL = 7040;
    public const BOOKS_FOREIGN = 7060;
    public const BOOKS_UNKNOWN = 7999;
    public const OTHER_ROOT = 1;
    public const GAME_ROOT = 1000;
    public const MOVIE_ROOT = 2000;
    public const MUSIC_ROOT = 3000;
    public const PC_ROOT = 4000;
    public const TV_ROOT = 5000;
    public const XXX_ROOT = 6000;
    public const BOOKS_ROOT = 7000;
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

    public const MOVIES_GROUP =
        [
            self::MOVIE_FOREIGN,
            self::MOVIE_ROOT,
            self::MOVIE_OTHER,
            self::MOVIE_SD,
            self::MOVIE_HD,
            self::MOVIE_UHD,
            self::MOVIE_3D,
            self::MOVIE_BLURAY,
            self::MOVIE_DVD,
            self::MOVIE_WEBDL,
            self::MOVIE_X265,
        ];

    public const TV_GROUP =
        [
            self::TV_FOREIGN,
            self::TV_ROOT,
            self::TV_OTHER,
            self::TV_SD,
            self::TV_HD,
            self::TV_UHD,
            self::TV_ANIME,
            self::TV_DOCU,
            self::TV_SPORT,
            self::TV_WEBDL,
            self::TV_X265,
        ];

    /**
     * Temporary category while we sort through the name.
     * @var int
     */
    protected $tmpCat = self::OTHER_MISC;

    protected $table = 'categories';
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function releases()
    {
        return $this->hasMany(Release::class, 'categories_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(static::class, 'parentid');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(static::class, 'parentid');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public static function getRecentlyAdded()
    {
        return self::query()
            ->remember(config('nntmux.cache_expiry_long'))
            ->where('r.adddate', '>', now()->subWeek())
            ->selectRaw('CONCAT(cp.title, " > ", categories.title) as title')
            ->selectRaw('COUNT(r.id) as count')
            ->join('categories as cp', 'cp.id', '=', 'categories.parentid')
            ->join('releases as r', 'r.categories_id', '=', 'categories.id')
            ->groupBy('title')
            ->orderBy('count', 'desc')
            ->get();
    }

    /**
     * @param array $cat
     * @param null  $query
     * @param bool  $builder
     *
     * @return string|\Illuminate\Database\Query\Builder
     */
    public static function getCategorySearch(array $cat = [], $query = null, $builder = false)
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
            if ($category !== -1 && self::isParent($category)) {
                foreach (self::getChildren($category) as $child) {
                    $categories[] = $child['id'];
                }
            } elseif ($category > 0) {
                $categories[] = $category;
            }
        }
        $catCount = \count($categories);
        $catsrch = '';
        switch ($catCount) {
            //No category constraint
            case 0:
                if ($builder === false) {
                    $catsrch = 'AND 1=1';
                }
                break;
            // One category constraint
            case 1:
                if ($builder === false) {
                    $catsrch = $categories[0] !== -1 ? '  AND r.categories_id = '.$categories[0] : '';
                } else {
                    return $query->where('r.categories_id', '=', $categories[0]);
                }
                break;
            // Multiple category constraints
            default:
                if ($builder === false) {
                    $catsrch = ' AND r.categories_id IN ('.implode(', ', $categories).') ';
                } else {
                    return $query->whereIn('r.categories_id', $categories);
                }
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
    public static function isParent($cid): bool
    {
        $ret = self::query()->where(['id' => $cid, 'parentid' => null])->first();

        return $ret !== null;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getFlat()
    {
        return self::query()->get();
    }

    /**
     * Get children of a parent category.
     *
     *
     * @param $categoryId
     * @return mixed
     */
    public static function getChildren($categoryId)
    {
        return self::remember(config('nntmux.cache_expiry_long'))->find($categoryId)->children;
    }

    /**
     * Get names of enabled parent categories.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getEnabledParentNames()
    {
        return self::query()
            ->where(['parentid' => null, 'status' => 1])
            ->get(['title']);
    }

    /**
     * Returns category ID's for site disabled categories.
     *
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getDisabledIDs()
    {
        return self::query()
            ->where('status', '=', 2)
            ->orWhere(['status' => 2, 'parentid' => null])
            ->get(['id']);
    }

    /**
     * Get multiple categories.
     *
     * @param $ids
     *
     * @return bool|\Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getByIds($ids)
    {
        if (\count($ids) > 0) {
            return self::query()->remember(config('nntmux.cache_expiry_long'))->whereIn('id', $ids)->get();
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
    public static function getNameByID($ID): string
    {
        $cat = self::query()->where('id', $ID)->first();

        return $cat !== null ? $cat->parent->title.' -> '.$cat->title : '';
    }

    /**
     * @param $title
     * @param $parent
     * @return bool|mixed
     */
    public static function getIdByName($title, $parent)
    {
        $cat = self::query()->where('title', $title)->with('parent.'.$parent)->first(['id']);

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
    public static function updateCategory($id, $status, $desc, $disablepreview, $minsize, $maxsize): int
    {
        return self::query()->where('id', $id)->update(
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
     * @return array
     */
    public static function getForMenu(array $excludedCats = []): array
    {
        $ret = [];

        $sql = self::query()->remember(config('nntmux.cache_expiry_long'))->where('status', '=', self::STATUS_ACTIVE)->select(['id', 'title', 'parentid', 'description']);

        if (! empty($excludedCats)) {
            $sql->whereNotIn('id', $excludedCats);
        }

        $arr = $sql->get()->toArray();

        foreach ($arr as $key => $val) {
            if ($val['id'] === self::OTHER_ROOT) {
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
     * @return mixed
     */
    public static function getForApi()
    {
        return self::query()->remember(config('nntmux.cache_expiry_long'))->select(['id', 'title'])->where('status', '=', self::STATUS_ACTIVE)->whereNull('parentid')->get();
    }

    /**
     * Return a list of categories for use in a dropdown.
     *
     * @param bool $blnIncludeNoneSelected
     *
     * @return array
     */
    public static function getForSelect($blnIncludeNoneSelected = true): array
    {
        $categories = self::getCategories();
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
     * @return array
     */
    public static function getCategories($activeOnly = false, array $excludedCats = [])
    {
        $sql = self::query()
            ->select(['categories.id', 'cp.id as parentid', 'categories.status'])
            ->selectRaw("CONCAT(cp.title, ' > ',categories.title) AS title")
            ->leftJoin('categories as cp', 'cp.id', '=', 'categories.parentid')
            ->orderBy('categories.id');

        if ($activeOnly) {
            $sql->where('categories.status', '=', self::STATUS_ACTIVE);
        }

        if (\count($excludedCats) > 0) {
            $sql->whereNotIn('categories.id', $excludedCats);
        }

        return $sql->get()->toArray();
    }
}
