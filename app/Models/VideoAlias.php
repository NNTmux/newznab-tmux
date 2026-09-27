<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\VideoAlias.
 *
 * @property int $videos_id FK to videos.id of the parent title.
 * @property string $title AKA of the video.
 * @property-read Video $video
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
    use HasCompositePrimaryKey;

    protected $table = 'videos_aliases';

    /**
     * @var string
     */
    protected $primaryKey = 'videos_id';

    /**
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * @return non-empty-list<string>
     */
    public function compositeKeyColumns(): array
    {
        return ['videos_id', 'title'];
    }

    /**
     * @return BelongsTo<Video, $this>
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'videos_id');
    }
}
