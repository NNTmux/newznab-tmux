<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

/**
 * App\Models\GamesInfo.
 *
 * @property int $id
 * @property string $title
 * @property string|null $asin
 * @property string|null $url
 * @property string|null $publisher
 * @property int|null $genres_id
 * @property string|null $esrb
 * @property string|null $releasedate
 * @property string|null $review
 * @property bool $cover
 * @property bool $backdrop
 * @property string $trailer
 * @property string $classused
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\GamesInfo whereAsin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\GamesInfo whereBackdrop($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\GamesInfo whereClassused($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\GamesInfo whereCover($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\GamesInfo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\GamesInfo whereEsrb($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\GamesInfo whereGenresId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\GamesInfo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\GamesInfo wherePublisher($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\GamesInfo whereReleasedate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\GamesInfo whereReview($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\GamesInfo whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\GamesInfo whereTrailer($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\GamesInfo whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\GamesInfo whereUrl($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\GamesInfo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\GamesInfo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\GamesInfo query()
 */
class GamesInfo extends Model
{
    use Searchable;

    /**
     * @var string
     */
    protected $table = 'gamesinfo';

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @return string
     */
    public function searchableAs()
    {
        return 'ix_title_ft';
    }

    /**
     * @return array
     */
    public function toSearchableArray()
    {
        return [
            'title' => $this->title,
        ];
    }
}
