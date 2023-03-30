<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\VideoAlias.
 *
 * @property int $videos_id FK to videos.id of the parent title.
 * @property string $title AKA of the video.
 * @property-read \App\Models\Video $video
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\VideoAlias whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\VideoAlias whereVideosId($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\VideoAlias newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\VideoAlias newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\VideoAlias query()
 */
class VideoAlias extends Model
{
    protected $table = 'videos_aliases';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'videos_id');
    }
}
