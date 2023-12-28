<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\ReleaseExtraFull.
 *
 * @property int $releases_id FK to releases.id
 * @property string|null $mediainfo
 * @property-read Release $release
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseExtraFull whereMediainfo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseExtraFull whereReleasesId($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseExtraFull newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseExtraFull newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseExtraFull query()
 */
class ReleaseExtraFull extends Model
{
    /**
     * @var string
     */
    protected $table = 'releaseextrafull';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @var string
     */
    protected $primaryKey = 'releases_id';

    public function release(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Release::class, 'releases_id');
    }
}
