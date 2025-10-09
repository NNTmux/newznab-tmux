<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * App\Models\MovieInfo.
 *
 * @property int $id
 * @property int $imdbid
 * @property int $tmdbid
 * @property string $title
 * @property string $tagline
 * @property string $rating
 * @property string $rtrating RottenTomatoes rating score
 * @property string $plot
 * @property string $year
 * @property string $genre
 * @property string $type
 * @property string $director
 * @property string $actors
 * @property string $language
 * @property bool $cover
 * @property bool $backdrop
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property string $trailer
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MovieInfo whereActors($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MovieInfo whereBackdrop($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MovieInfo whereCover($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MovieInfo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MovieInfo whereDirector($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MovieInfo whereGenre($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MovieInfo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MovieInfo whereImdbid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MovieInfo whereLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MovieInfo wherePlot($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MovieInfo whereRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MovieInfo whereRtrating($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MovieInfo whereTagline($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MovieInfo whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MovieInfo whereTmdbid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MovieInfo whereTrailer($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MovieInfo whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MovieInfo whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MovieInfo whereYear($value)
 *
 * @mixin \Eloquent
 *
 * @property int $traktid
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MovieInfo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MovieInfo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MovieInfo query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MovieInfo whereTraktid($value)
 */
class MovieInfo extends Model
{
    /**
     * @var string
     */
    protected $table = 'movieinfo';

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $guarded = ['id'];

    public static function getAll(string $search = ''): mixed
    {
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        $movie = Cache::get(md5($search));
        if ($movie !== null) {
            return $movie;
        }
        $sql = self::query()->select('*');
        if (! empty($search)) {
            // Search by both title and IMDB ID
            $sql->where(function ($query) use ($search) {
                $query->whereLike('title', '%'.$search.'%')
                    ->orWhere('imdbid', $search);
            });
        }

        $movie = $sql->paginate(config('nntmux.items_per_page'));
        Cache::put(md5($search), $movie, $expiresAt);

        return $movie;
    }
}
