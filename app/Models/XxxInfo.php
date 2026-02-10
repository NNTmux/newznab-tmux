<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\XxxInfo.
 *
 * @property int $id
 * @property string $title
 * @property string $tagline
 * @property mixed|null $plot
 * @property string $genre
 * @property string|null $director
 * @property string $actors
 * @property string|null $extras
 * @property string|null $productinfo
 * @property string|null $trailers
 * @property string $directurl
 * @property string $classused
 * @property bool $cover
 * @property bool $backdrop
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereActors($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereBackdrop($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereClassused($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereCover($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereDirector($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereDirecturl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereExtras($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereGenre($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo wherePlot($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereProductinfo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereTagline($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereTrailers($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo query()
 */
class XxxInfo extends Model
{
    /**
     * @var string
     */
    protected $table = 'xxxinfo';

    protected $dateFormat = false;

    /**
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * Get releases associated with this XXX info.
     */
    public function releases(): HasMany
    {
        return $this->hasMany(Release::class, 'xxxinfo_id');
    }

    /**
     * Get info for a xxx id with decompressed plot.
     */
    public static function getXXXInfo(int $xxxid): ?self
    {
        return self::query()
            ->where('id', $xxxid)
            ->selectRaw('*, UNCOMPRESS(plot) as plot')
            ->first();
    }

    /**
     * Update XXX Information.
     */
    public static function updateXxxInfo(
        string $id,
        string $title = '',
        string $tagLine = '',
        string $plot = '',
        string $genre = '',
        string $director = '',
        string $actors = '',
        string $extras = '',
        string $productInfo = '',
        string $trailers = '',
        string $directUrl = '',
        string $classUsed = '',
        string $cover = '',
        string $backdrop = ''
    ): void {
        if (! empty($id)) {
            self::query()->where('id', $id)->update([
                'title' => $title,
                'tagline' => $tagLine,
                'plot' => "\x1f\x8b\x08\x00".gzcompress($plot),
                'genre' => substr($genre, 0, 64),
                'director' => $director,
                'actors' => $actors,
                'extras' => $extras,
                'productinfo' => $productInfo,
                'trailers' => $trailers,
                'directurl' => $directUrl,
                'classused' => $classUsed,
                'cover' => empty($cover) ? 0 : $cover,
                'backdrop' => empty($backdrop) ? 0 : $backdrop,
            ]);
        }
    }

    /**
     * Get all genres for search-filter.tpl.
     */
    public static function getAllGenres(bool $activeOnly = false): array
    {
        $ret = [];
        if ($activeOnly) {
            $res = Genre::query()
                ->where(['disabled' => 0, 'type' => Category::XXX_ROOT])
                ->orderBy('title')
                ->get(['title'])
                ->toArray();
        } else {
            $res = Genre::query()
                ->where(['type' => Category::XXX_ROOT])
                ->orderBy('title')
                ->get(['title'])
                ->toArray();
        }

        return array_column($res, 'title');
    }

    /**
     * Get a specific genre.
     */
    public static function getGenres(bool $activeOnly = false, ?int $gid = null): mixed
    {
        if ($activeOnly) {
            return Genre::query()
                ->where(['disabled' => 0, 'type' => Category::XXX_ROOT])
                ->when($gid !== null, fn ($query) => $query->where('id', $gid))
                ->orderBy('title')
                ->first(['title']);
        }

        return Genre::query()
            ->where(['type' => Category::XXX_ROOT])
            ->when($gid !== null, fn ($query) => $query->where('id', $gid))
            ->orderBy('title')
            ->first(['title']);
    }

    /**
     * Get Genre id's of the title.
     *
     * @param  array|string  $arr  - Array or String
     * @return string - If array .. 1,2,3,4 if string .. 1
     */
    public static function getGenreID(array|string $arr): string
    {
        $ret = null;

        if (! \is_array($arr)) {
            $res = Genre::query()->where('title', $arr)->first(['id']);
            if ($res !== null) {
                return (string) $res['id'];
            }

            return '';
        }

        foreach ($arr as $key => $value) {
            $res = Genre::query()->where('title', $value)->first(['id']);
            if ($res !== null) {
                $ret .= ','.$res['id'];
            } else {
                $ret .= ','.self::insertGenre($value);
            }
        }

        $ret = ltrim($ret, ',');

        return $ret;
    }

    /**
     * Inserts Genre and returns last affected row (Genre ID).
     */
    private static function insertGenre(?string $genre): int|string
    {
        $res = '';
        if ($genre !== null) {
            $res = Genre::query()->insertGetId([
                'title' => $genre,
                'type' => Category::XXX_ROOT,
                'disabled' => 0,
            ]);
        }

        return $res;
    }

    /**
     * Checks xxxinfo to make sure releases exist.
     */
    public static function checkXXXInfoExists(string $releaseName): ?self
    {
        return self::query()
            ->where('title', 'like', '%'.$releaseName.'%')
            ->first(['id', 'title']);
    }
}
