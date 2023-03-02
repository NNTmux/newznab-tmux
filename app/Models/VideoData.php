<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\VideoData.
 *
 * @property int $releases_id FK to releases.id
 * @property string|null $containerformat
 * @property string|null $overallbitrate
 * @property string|null $videoduration
 * @property string|null $videoformat
 * @property string|null $videocodec
 * @property int|null $videowidth
 * @property int|null $videoheight
 * @property string|null $videoaspect
 * @property float|null $videoframerate
 * @property string|null $videolibrary
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\VideoData whereContainerformat($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\VideoData whereOverallbitrate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\VideoData whereReleasesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\VideoData whereVideoaspect($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\VideoData whereVideocodec($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\VideoData whereVideoduration($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\VideoData whereVideoformat($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\VideoData whereVideoframerate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\VideoData whereVideoheight($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\VideoData whereVideolibrary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\VideoData whereVideowidth($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\VideoData newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\VideoData newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\VideoData query()
 */
class VideoData extends Model
{
    /**
     * @var string
     */
    protected $table = 'video_data';

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
     * @var string
     */
    protected $primaryKey = 'releases_id';

    /**
     * @var array
     */
    protected $guarded = [];
}
