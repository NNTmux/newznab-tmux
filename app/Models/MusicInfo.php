<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\MusicInfo.
 *
 * @property int $id
 * @property string $title
 * @property string|null $asin
 * @property string|null $url
 * @property int|null $salesrank
 * @property string|null $artist
 * @property string|null $publisher
 * @property string|null $releasedate
 * @property string|null $review
 * @property string $year
 * @property int|null $genres_id
 * @property string|null $tracks
 * @property bool $cover
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $_totalcount Computed total count from aggregate queries
 * @property-read Genre|null $genre
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MusicInfo whereArtist($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MusicInfo whereAsin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MusicInfo whereCover($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MusicInfo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MusicInfo whereGenresId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MusicInfo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MusicInfo wherePublisher($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MusicInfo whereReleasedate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MusicInfo whereReview($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MusicInfo whereSalesrank($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MusicInfo whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MusicInfo whereTracks($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MusicInfo whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MusicInfo whereUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MusicInfo whereYear($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MusicInfo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MusicInfo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MusicInfo query()
 */
class MusicInfo extends Model
{
    /**
     * @var string
     */
    protected $table = 'musicinfo';

    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $timestamps = true;

    /**
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * @return BelongsTo<Genre, $this>
     */
    public function genre(): BelongsTo
    {
        return $this->belongsTo(Genre::class, 'genres_id');
    }
}
