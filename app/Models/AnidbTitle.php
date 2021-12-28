<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\AnidbTitle.
 *
 * @property int $anidbid ID of title from AniDB
 * @property string $type type of title.
 * @property string $lang
 * @property string $title
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\AnidbEpisode[] $episode
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\AnidbInfo[] $info
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbTitle whereAnidbid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbTitle whereLang($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbTitle whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbTitle whereType($value)
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbTitle newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbTitle newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbTitle query()
 */
class AnidbTitle extends Model
{
    /**
     * @var string
     */
    protected $primaryKey = 'anidbid';

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array
     */
    protected $guarded = [];

    public function episode()
    {
        return $this->hasMany(AnidbEpisode::class, 'anidbid');
    }

    public function info()
    {
        return $this->hasMany(AnidbInfo::class, 'anidbid');
    }
}
