<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\ParHash.
 *
 * @property int $releases_id FK to releases.id
 * @property string $hash hash_16k block of par2
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ParHash whereHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ParHash whereReleasesId($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ParHash newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ParHash newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ParHash query()
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

    protected $dateFormat = false;

    /**
     * @var array<string>
     */
    protected $guarded = [];
}
