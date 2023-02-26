<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Category whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Category whereDisablepreview($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Category whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Category whereMaxsizetoformrelease($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Category whereMinsizetoformrelease($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Category whereParentid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Category whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Category whereTitle($value)
 * @mixin \Eloquent
 *
 * @property int|null $root_categories_id
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Category newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Category newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Category query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Category whereRootCategoriesId($value)
 */
class Category extends Model
{
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
    public const MUSIC_PODCAST = 3050;
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
     * Tag constants.
     * Do NOT use the values, as they may change, always use the constant - that's what it's for.
     */
    public const TAG_OTHER_MISC = 'Other_Misc';
    public const TAG_OTHER_HASHED = 'Other_Hashed';
    public const TAG_GAME_NDS = 'Game_NDS';
    public const TAG_GAME_PSP = 'Game_PSP';
    public const TAG_GAME_WII = 'Game_Wii';
    public const TAG_GAME_XBOX = 'Game_Xbox';
    public const TAG_GAME_XBOX360 = 'Game_Xbox360';
    public const TAG_GAME_WIIWARE = 'Game_WiiWare';
    public const TAG_GAME_XBOX360DLC = 'Game_Xbox360_DLC';
    public const TAG_GAME_PS3 = 'Game_PS3';
    public const TAG_GAME_OTHER = 'Game_Other';
    public const TAG_GAME_3DS = 'Game_3DS';
    public const TAG_GAME_PSVITA = 'Game_PS_Vita';
    public const TAG_GAME_WIIU = 'Game_WiiU';
    public const TAG_GAME_XBOXONE = 'Game_Xbox_One';
    public const TAG_GAME_PS4 = 'Game_PS4';
    public const TAG_MOVIE_FOREIGN = 'Movie_Foreign';
    public const TAG_MOVIE_OTHER = 'Movie_Other';
    public const TAG_MOVIE_SD = 'Movie_SD';
    public const TAG_MOVIE_HD = 'Movie_HD';
    public const TAG_MOVIE_UHD = 'Movie_UHD';
    public const TAG_MOVIE_3D = 'Movie_3D';
    public const TAG_MOVIE_BLURAY = 'Movie_BluRay';
    public const TAG_MOVIE_DVD = 'Movie_DVD';
    public const TAG_MOVIE_WEBDL = 'Movie_Web_DL';
    public const TAG_MOVIE_X265 = 'Movie_x265';
    public const TAG_MUSIC_MP3 = 'Music_MP3';
    public const TAG_MUSIC_VIDEO = 'Music_Video';
    public const TAG_MUSIC_AUDIOBOOK = 'Music_Audio_Book';
    public const TAG_MUSIC_LOSSLESS = 'Music_LossLess';
    public const TAG_MUSIC_PODCAST = 'Music_Podcast';
    public const TAG_MUSIC_OTHER = 'Music_Other';
    public const TAG_MUSIC_FOREIGN = 'Music_Foreign';
    public const TAG_PC_0DAY = 'Pc_0Day';
    public const TAG_PC_ISO = 'Pc_Iso';
    public const TAG_PC_MAC = 'Pc_Mac';
    public const TAG_PC_PHONE_OTHER = 'Pc_Phone_Other';
    public const TAG_PC_GAMES = 'Pc_Games';
    public const TAG_PC_PHONE_IOS = 'Pc_Phone_Ios';
    public const TAG_PC_PHONE_ANDROID = 'Pc_Phone_Android';
    public const TAG_TV_WEBDL = 'Tv_Web_DL';
    public const TAG_TV_FOREIGN = 'Tv_Foreign';
    public const TAG_TV_SD = 'Tv_SD';
    public const TAG_TV_HD = 'Tv_HD';
    public const TAG_TV_UHD = 'Tv_UHD';
    public const TAG_TV_OTHER = 'Tv_Other';
    public const TAG_TV_SPORT = 'Tv_Sport';
    public const TAG_TV_ANIME = 'Tv_Anime';
    public const TAG_TV_DOCU = 'Tv_Documentary';
    public const TAG_TV_X265 = 'Tv_x265';
    public const TAG_XXX_DVD = 'Xxx_DVD';
    public const TAG_XXX_WMV = 'Xxx_Wmv';
    public const TAG_XXX_XVID = 'Xxx_XvID';
    public const TAG_XXX_X264 = 'Xxx_X264';
    public const TAG_XXX_CLIPHD = 'Xxx_Clip_HD';
    public const TAG_XXX_CLIPSD = 'Xxx_Clip_SD';
    public const TAG_XXX_UHD = 'Xxx_UHD';
    public const TAG_XXX_PACK = 'Xxx_Pack';
    public const TAG_XXX_IMAGESET = 'Xxx_ImageSet';
    public const TAG_XXX_OTHER = 'Xxx_Other';
    public const TAG_XXX_SD = 'Xxx_SD';
    public const TAG_XXX_WEBDL = 'Xxx_Web_DL';
    public const TAG_BOOKS_MAGAZINES = 'Magazines';
    public const TAG_BOOKS_EBOOK = 'Ebook';
    public const TAG_BOOKS_COMICS = 'Comics';
    public const TAG_BOOKS_TECHNICAL = 'Technical';
    public const TAG_BOOKS_FOREIGN = 'Books_Foreign';
    public const TAG_BOOKS_UNKNOWN = 'Books_Unknown';

    /**
     * @var string
     */
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
        return $this->belongsTo(RootCategory::class, 'root_categories_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public static function getRecentlyAdded()
    {
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_long'));
        $result = Cache::get(md5('RecentlyAdded'));
        if ($result !== null) {
            return $result;
        }

        $result = self::query()
            ->with('parent')
            ->where('r.adddate', '>', now()->subWeek())
            ->select(['root_categories_id', DB::raw('COUNT(r.id) as count'), 'title'])
            ->join('releases as r', 'r.categories_id', '=', 'categories.id')
            ->groupBy('title')
            ->orderBy('count', 'desc')
            ->get();

        Cache::put(md5('RecentlyAdded'), $result, $expiresAt);

        return $result;
    }

    /**
     * @param  array  $cat
     * @return string
     */
    public static function getCategorySearch(array $cat = [])
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
            if (is_numeric($category) && $category !== -1 && self::isParent($category)) {
                foreach (RootCategory::find($category)->categories as $child) {
                    $categories[] = $child['id'];
                }
            } elseif (is_numeric($category) && $category > 0) {
                $categories[] = $category;
            }
        }
        $catCount = \count($categories);
        switch ($catCount) {
            //No category constraint
            case 0:
                    $catsrch = 'AND 1=1';
                break;
            // One category constraint
            case 1:
                    $catsrch = $categories[0] !== -1 ? '  AND r.categories_id = '.$categories[0] : '';
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
     * @return bool
     */
    public static function isParent($cid): bool
    {
        $ret = RootCategory::query()->where(['id' => $cid])->first();

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
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_long'));
        $result = Cache::get(md5($categoryId));
        if ($result !== null) {
            return $result;
        }

        $result = RootCategory::find($categoryId)->categories;
        Cache::put(md5($categoryId), $result, $expiresAt);

        return $result;
    }

    /**
     * Get names of enabled parent categories.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getEnabledParentNames()
    {
        return RootCategory::query()->where('status', '=', 1)->get(['title']);
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
            ->get(['id']);
    }

    /**
     * Get multiple categories.
     *
     * @param $ids
     * @return bool|\Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getByIds($ids)
    {
        if (\count($ids) > 0) {
            $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_long'));
            $result = Cache::get(md5(implode(',', $ids)));
            if ($result !== null) {
                return $result;
            }
            $result = self::query()->whereIn('id', $ids)->get();

            Cache::put(md5(md5(implode(',', $ids))), $result, $expiresAt);

            return $result;
        }

        return false;
    }

    /**
     * Return the parent and category name from the supplied categoryID.
     *
     *
     * @param $categoryId
     * @return string
     */
    public static function getNameByID($categoryId): string
    {
        $cat = self::query()->where('id', $categoryId)->first();

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
     * @param $desc
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
     * @param  array  $excludedCats
     * @return array
     */
    public static function getForMenu(array $excludedCats = []): array
    {
        $categoriesResult = [];
        $categoriesArray = RootCategory::query()->with(['categories' =>function ($query) use ($excludedCats) {
            if (! empty($excludedCats)) {
                $query->whereNotIn('id', $excludedCats);
            }
            $query->select(['id', 'title', 'root_categories_id', 'description']);
        }])->select(['id', 'title'])->get()->toArray();

        foreach ($categoriesArray as $category) {
            if (! empty($category['categories'])) {
                $categoriesResult[] = $category;
            }
        }

        return $categoriesResult;
    }

    /**
     * @return mixed
     */
    public static function getForApi()
    {
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_long'));
        $result = Cache::get(md5('ForApi'));
        if ($result !== null) {
            return $result;
        }

        $result = RootCategory::query()->select(['id', 'title'])->where('status', '=', self::STATUS_ACTIVE)->get();

        Cache::put(md5('ForApi'), $result, $expiresAt);

        return $result;
    }

    /**
     * Return a list of categories for use in a dropdown.
     *
     * @param bool $blnIncludeNoneSelected
     * @return array
     */
    public static function getForSelect(bool $blnIncludeNoneSelected = true): array
    {
        $categories = self::getCategories();
        $temp_array = [];

        if ($blnIncludeNoneSelected) {
            $temp_array[-1] = '--Please Select--';
        }
        foreach ($categories as $category) {
            $temp_array[$category->id] = $category->parent->title.' > '.$category->title;
        }

        return $temp_array;
    }

    /**
     * @param bool $activeOnly
     * @param array $excludedCats
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public static function getCategories(bool $activeOnly = false, array $excludedCats = []): \Illuminate\Database\Eloquent\Collection|array
    {
        $sql = self::query()
            ->with('parent')
            ->select(['id', 'status', 'title', 'root_categories_id'])
            ->orderBy('id');

        if ($activeOnly) {
            $sql->where('status', '=', self::STATUS_ACTIVE);
        }

        if (! empty($excludedCats)) {
            $sql->whereNotIn('id', $excludedCats);
        }

        return $sql->get();
    }
}
