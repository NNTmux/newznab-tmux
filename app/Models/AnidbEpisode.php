<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\AnidbEpisode.
 *
 * @property int $anidbid ID of title from AniDB
 * @property int $episodeid anidb id for this episode
 * @property int $episode_no Numeric version of episode (leave 0 for combined episodes).
 * @property string $episode_title Title of the episode (en, x-jat)
 * @property string $airdate
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\AnidbInfo[] $info
 * @property-read AnidbTitle $title
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbEpisode whereAirdate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbEpisode whereAnidbid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbEpisode whereEpisodeNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbEpisode whereEpisodeTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbEpisode whereEpisodeid($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbEpisode newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbEpisode newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbEpisode query()
 */
class AnidbEpisode extends Model
{
    protected $dateFormat = false;

    /**
     * @var string
     */
    protected $primaryKey = 'anidbid';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\AnidbTitle, $this>
     */
    public function title(): BelongsTo
    {
        return $this->belongsTo(AnidbTitle::class, 'anidbid');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\AnidbInfo, $this>
     */
    public function info(): HasMany
    {
        return $this->hasMany(AnidbInfo::class, 'anidbid');
    }
}
