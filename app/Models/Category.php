<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property int|null $count Computed count from aggregate queries
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Category[] $children
 * @property-read Category|null $parent
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
 *
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
    public const int OTHER_MISC = 10;

    public const int OTHER_HASHED = 20;

    public const int GAME_NDS = 1010;

    public const int GAME_PSP = 1020;

    public const int GAME_WII = 1030;

    public const int GAME_XBOX = 1040;

    public const int GAME_XBOX360 = 1050;

    public const int GAME_WIIWARE = 1060;

    public const int GAME_XBOX360DLC = 1070;

    public const int GAME_PS3 = 1080;

    public const int GAME_OTHER = 1999;

    public const int GAME_3DS = 1110;

    public const int GAME_PSVITA = 1120;

    public const int GAME_WIIU = 1130;

    public const int GAME_XBOXONE = 1140;

    public const int GAME_PS4 = 1180;

    public const int MOVIE_FOREIGN = 2010;

    public const int MOVIE_OTHER = 2999;

    public const int MOVIE_SD = 2030;

    public const int MOVIE_HD = 2040;

    public const int MOVIE_UHD = 2045;

    public const int MOVIE_3D = 2050;

    public const int MOVIE_BLURAY = 2060;

    public const int MOVIE_DVD = 2070;

    public const int MOVIE_WEBDL = 2080;

    public const int MOVIE_X265 = 2090;

    public const int MUSIC_MP3 = 3010;

    public const int MUSIC_VIDEO = 3020;

    public const int MUSIC_AUDIOBOOK = 3030;

    public const int MUSIC_LOSSLESS = 3040;

    public const int MUSIC_PODCAST = 3050;

    public const int MUSIC_OTHER = 3999;

    public const int MUSIC_FOREIGN = 3060;

    public const int PC_0DAY = 4010;

    public const int PC_ISO = 4020;

    public const int PC_MAC = 4030;

    public const int PC_PHONE_OTHER = 4040;

    public const int PC_GAMES = 4050;

    public const int PC_PHONE_IOS = 4060;

    public const int PC_PHONE_ANDROID = 4070;

    public const int TV_WEBDL = 5010;

    public const int TV_FOREIGN = 5020;

    public const int TV_SD = 5030;

    public const int TV_HD = 5040;

    public const int TV_UHD = 5045;

    public const int TV_OTHER = 5999;

    public const int TV_SPORT = 5060;

    public const int TV_ANIME = 5070;

    public const int TV_DOCU = 5080;

    public const int TV_X265 = 5090;

    public const int XXX_DVD = 6010;

    public const int XXX_WMV = 6020;

    public const int XXX_XVID = 6030;

    public const int XXX_X264 = 6040;

    public const int XXX_CLIPHD = 6041;

    public const int XXX_CLIPSD = 6042;

    public const int XXX_UHD = 6045;

    public const int XXX_VR = 6046;

    public const int XXX_ONLYFANS = 6047;

    public const int XXX_PACK = 6050;

    public const int XXX_IMAGESET = 6060;

    public const int XXX_OTHER = 6999;

    public const int XXX_SD = 6080;

    public const int XXX_WEBDL = 6090;

    public const int BOOKS_MAGAZINES = 7010;

    public const int BOOKS_EBOOK = 7020;

    public const int BOOKS_COMICS = 7030;

    public const int BOOKS_TECHNICAL = 7040;

    public const int BOOKS_FOREIGN = 7060;

    public const int BOOKS_UNKNOWN = 7999;

    public const int OTHER_ROOT = 1;

    public const int GAME_ROOT = 1000;

    public const int MOVIE_ROOT = 2000;

    public const int MUSIC_ROOT = 3000;

    public const int PC_ROOT = 4000;

    public const int TV_ROOT = 5000;

    public const int XXX_ROOT = 6000;

    public const int BOOKS_ROOT = 7000;

    public const int STATUS_INACTIVE = 0;

    public const int STATUS_ACTIVE = 1;

    public const int STATUS_DISABLED = 2;

    public const array OTHERS_GROUP =
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

    public const array MOVIES_GROUP =
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

    public const array TV_GROUP =
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
     * @var bool
     */
    public $timestamps = false;

    protected $dateFormat = false;

    /**
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Release, $this>
     */
    public function releases(): HasMany
    {
        return $this->hasMany(Release::class, 'categories_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\RootCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(RootCategory::class, 'root_categories_id');
    }

    public static function getRecentlyAdded(): mixed
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
            ->orderByDesc('count')
            ->get();

        Cache::put(md5('RecentlyAdded'), $result, $expiresAt);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $cat
     * @return array<string, mixed>
     */
    public static function getCategorySearch(array $cat = [], ?string $searchType = null, mixed $builder = false): string|array|null
    {
        // Generate a cache key based on the input parameters
        $cacheKey = 'cat_search_'.md5(serialize($cat).$searchType.($builder ? '1' : '0'));
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $categories = [];

        // if searchType is tv return TV categories (only if no specific categories provided)
        if ($searchType === 'tv' && (empty($cat) || $cat === [-1] || $cat === ['-1'])) {
            $cat = self::TV_GROUP;
        }

        // is searchType is movies return MOVIES categories (only if no specific categories provided)
        if ($searchType === 'movies' && (empty($cat) || $cat === [-1] || $cat === ['-1'])) {
            $cat = self::MOVIES_GROUP;
        }

        // If multiple categories were sent in a single array position, slice and add them
        if (isset($cat[0]) && is_string($cat[0]) && str_contains($cat[0], ',')) {
            $tmpcats = explode(',', $cat[0]);
            // Reset the category to the first comma separated value in the string
            $cat[0] = $tmpcats[0];
            // Add the remaining categories in the string to the original array
            foreach (array_slice($tmpcats, 1) as $tmpcat) {
                $cat[] = $tmpcat;
            }
        }

        foreach ($cat as $category) {
            $categoryInt = (int) $category;
            if (is_numeric($category) && $categoryInt !== -1 && self::isParent($categoryInt)) {
                // Cache child category IDs for parent categories
                $childCacheKey = 'cat_children_'.$categoryInt;
                $children = Cache::get($childCacheKey);
                if ($children === null) {
                    $children = RootCategory::find($categoryInt)->categories->pluck('id')->toArray();
                    Cache::put($childCacheKey, $children, now()->addHours(24));
                }
                $categories = array_merge($categories, $children);
            } elseif (is_numeric($category) && $categoryInt > 0) {
                $categories[] = $categoryInt;
            }
        }

        $catCount = count($categories);

        if ($builder) {
            $result = match ($catCount) {
                0 => null,
                1 => $categories[0] !== -1 ? $categories : null,
                default => $categories,
            };
            Cache::put($cacheKey, $result, now()->addHours(24));

            return $result;
        }

        $result = match ($catCount) {
            0 => 'AND 1=1',
            1 => $categories[0] !== -1 ? ' AND r.categories_id = '.$categories[0] : '',
            default => ' AND r.categories_id IN ('.implode(', ', $categories).') ',
        };

        Cache::put($cacheKey, $result, now()->addHours(24));

        return $result;
    }

    /**
     * Returns a concatenated list of other categories.
     */
    public static function getCategoryOthersGroup(): string
    {
        return implode(
            ',',
            self::OTHERS_GROUP
        );
    }

    /**
     * @return mixed
     */
    public static function getCategoryValue(mixed $category)
    {
        return \constant('self::'.$category);
    }

    /**
     * Check if category is parent.
     */
    public static function isParent(mixed $cid): bool
    {
        // Cache the parent category check to avoid repeated DB queries
        $cacheKey = 'cat_is_parent_'.$cid;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $ret = RootCategory::query()->where(['id' => $cid])->first();
        $isParent = $ret !== null;

        // Cache for a long time since parent categories rarely change
        Cache::put($cacheKey, $isParent, now()->addHours(24));

        return $isParent;
    }

    public static function getFlat(): mixed
    {
        return self::query()->get();
    }

    /**
     * Get children of a parent category.
     *
     *
     * @return mixed
     */
    public static function getChildren(mixed $categoryId)
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
     */
    public static function getEnabledParentNames(): mixed
    {
        return RootCategory::query()->where('status', '=', 1)->get(['title']);
    }

    /**
     * Returns category ID's for site disabled categories.
     */
    public static function getDisabledIDs(): mixed
    {
        return self::query()
            ->where('status', '=', 2)
            ->get(['id']);
    }

    /**
     * Get multiple categories.
     */
    public static function getByIds(mixed $ids): mixed
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
     */
    public static function getNameByID(mixed $categoryId): string
    {
        $cat = self::query()->where('id', $categoryId)->first();

        return $cat !== null ? $cat->parent->title.' -> '.$cat->title : '';
    }

    /**
     * @return bool|mixed
     */
    public static function getIdByName(mixed $title, mixed $parent)
    {
        $cat = self::query()->where('title', $title)->with('parent.'.$parent)->first(['id']);

        return $cat !== null ? $cat->id : false;
    }

    /**
     * Update a category.
     */
    public static function updateCategory(mixed $id, mixed $status, mixed $desc, mixed $disablepreview, mixed $minsize, mixed $maxsize): int
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
     * @param  array<string, mixed>  $excludedCats
     * @return array<string, mixed>
     */
    public static function getForMenu(array $excludedCats = []): array
    {
        $categoriesResult = [];
        $categoriesArray = RootCategory::query()->with(['categories' => function ($query) use ($excludedCats) {
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
     * @return list<mixed>
     */
    public static function getForApi(): mixed
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
     * @return array<int, string>
     */
    public static function getForSelect(bool $blnIncludeNoneSelected = true): array
    {
        $categories = self::getCategories();
        $temp_array = [];

        if ($blnIncludeNoneSelected) {
            $temp_array[-1] = '--Please Select--';
        }
        foreach ($categories as $category) {
            /** @var Category $category */
            $temp_array[$category->id] = $category->parent->title.' > '.$category->title;
        }

        return $temp_array;
    }

    /**
     * @param  array<string, mixed>  $excludedCats
     * @return array<int, string>
     */
    public static function getCategories(bool $activeOnly = false, array $excludedCats = []): \Illuminate\Database\Eloquent\Collection // @phpstan-ignore missingType.generics
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
