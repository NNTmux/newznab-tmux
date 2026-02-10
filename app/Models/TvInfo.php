<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\TvInfo.
 *
 * @property int $videos_id FK to video.id
 * @property string $summary Description/summary of the show.
 * @property string $publisher The channel/network of production/release (ABC, BBC, Showtime, etc.).
 * @property string $localzone The linux tz style identifier
 * @property bool $image Does the video have a cover image?
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\TvInfo whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\TvInfo whereLocalzone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\TvInfo wherePublisher($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\TvInfo whereSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\TvInfo whereVideosId($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\TvInfo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\TvInfo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\TvInfo query()
 */
class TvInfo extends Model
{
    /**
     * @var string
     */
    protected $table = 'tv_info';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * @var bool
     */
    public $timestamps = false;

    protected $dateFormat = false;

    /**
     * @var string
     */
    protected $primaryKey = 'videos_id';
}
