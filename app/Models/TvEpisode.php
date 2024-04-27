<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\TvEpisode.
 *
 * @property int $id
 * @property int $videos_id FK to videos.id of the parent series.
 * @property int $series Number of series/season.
 * @property int $episode Number of episode within series
 * @property string $se_complete String version of Series/Episode as taken from release subject (i.e. S02E21+22).
 * @property string $title Title of the episode.
 * @property string $firstaired Date of original airing/release.
 * @property string $summary Description/summary of the episode.
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Release[] $release
 * @property-read Video $video
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\TvEpisode whereEpisode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\TvEpisode whereFirstaired($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\TvEpisode whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\TvEpisode whereSeComplete($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\TvEpisode whereSeries($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\TvEpisode whereSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\TvEpisode whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\TvEpisode whereVideosId($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\TvEpisode newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\TvEpisode newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\TvEpisode query()
 */
class TvEpisode extends Model
{
    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @var bool
     */
    public $timestamps = false;

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'videos_id');
    }

    public function release(): HasMany
    {
        return $this->hasMany(Release::class, 'tv_episodes_id');
    }
}
