<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\ParHash.
 *
 * @property int $releases_id FK to releases.id
 * @property string $hash hash_16k block of par2
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ParHash whereHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ParHash whereReleasesId($value)
 * @mixin \Eloquent
 */
class ParHash extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $guarded = [];
}
