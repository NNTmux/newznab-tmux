<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\ReleaseUnique.
 *
 * @property int $releases_id FK to releases.id.
 * @property mixed $uniqueid Unique_ID from mediainfo.
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseUnique whereReleasesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseUnique whereUniqueid($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseUnique newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseUnique newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseUnique query()
 */
class ReleaseUnique extends Model
{
    /**
     * @var string
     */
    protected $table = 'release_unique';

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
    protected $fillable = [
        'releases_id',
        'uniqueid',
    ];
}
