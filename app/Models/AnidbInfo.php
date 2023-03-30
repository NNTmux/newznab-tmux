<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\AnidbInfo.
 *
 * @property-read \App\Models\AnidbEpisode $episode
 * @property-read \App\Models\AnidbTitle $title
 *
 * @mixin \Eloquent
 *
 * @property int $anidbid ID of title from AniDB
 * @property string|null $type
 * @property string|null $startdate
 * @property string|null $enddate
 * @property string $updated
 * @property string|null $related
 * @property string|null $similar
 * @property string|null $creators
 * @property string|null $description
 * @property string|null $rating
 * @property string|null $picture
 * @property string|null $categories
 * @property string|null $characters
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbInfo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbInfo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbInfo query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbInfo whereAnidbid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbInfo whereCategories($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbInfo whereCharacters($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbInfo whereCreators($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbInfo whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbInfo whereEnddate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbInfo wherePicture($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbInfo whereRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbInfo whereRelated($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbInfo whereSimilar($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbInfo whereStartdate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbInfo whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AnidbInfo whereUpdated($value)
 */
class AnidbInfo extends Model
{
    /**
     * @var string
     */
    protected $primaryKey = 'anidbid';

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @var string
     */
    protected $table = 'anidb_info';

    public function title(): BelongsTo
    {
        return $this->belongsTo(AnidbTitle::class, 'anidbid');
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(AnidbEpisode::class, 'anidbid');
    }
}
