<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\AnidbTitle.
 *
 * @property int $anidbid ID of title from AniDB
 * @property string $type type of title.
 * @property string $lang
 * @property string $title
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\AnidbInfo[] $info
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbTitle whereAnidbid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbTitle whereLang($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbTitle whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbTitle whereType($value)
 *
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
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\AnidbInfo, $this>
     */
    public function info(): HasMany
    {
        return $this->hasMany(AnidbInfo::class, 'anidbid');
    }
}
