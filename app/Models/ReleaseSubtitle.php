<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\ReleaseSubtitle.
 *
 * @property int $id
 * @property int $releases_id FK to releases.id
 * @property int $subsid
 * @property string $subslanguage
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseSubtitle whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseSubtitle whereReleasesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseSubtitle whereSubsid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseSubtitle whereSubslanguage($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseSubtitle newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseSubtitle newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseSubtitle query()
 */
class ReleaseSubtitle extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    protected $dateFormat = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'releases_id',
        'subsid',
        'subslanguage',
    ];
}
