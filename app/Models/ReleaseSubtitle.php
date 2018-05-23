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
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseSubtitle whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseSubtitle whereReleasesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseSubtitle whereSubsid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseSubtitle whereSubslanguage($value)
 * @mixin \Eloquent
 */
class ReleaseSubtitle extends Model
{
    /**
     * @var string
     */
    protected $table = 'release_subtitles';

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
    protected $fillable = [
		'id',
		'releases_id',
		'subsid',
		'subslanguage',
	];
}
