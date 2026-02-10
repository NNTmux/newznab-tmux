<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property-read Genre|null $genre
 * @property-read \Illuminate\Database\Eloquent\Collection|Release[] $releases
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

    protected $dateFormat = false;

    /**
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'cover' => 'boolean',
        'backdrop' => 'boolean',
        'genres_id' => 'integer',
        'releasedate' => 'date',
    ];

    /**
     * Get the genre for this game.
     */
    public function genre(): BelongsTo
    {
        return $this->belongsTo(Genre::class, 'genres_id');
    }

    /**
     * Get the releases for this game.
     */
    public function releases(): HasMany
    {
        return $this->hasMany(Release::class, 'gamesinfo_id');
    }

    public function searchableAs(): string
    {
        return 'ix_title_ft';
    }

    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
        ];
    }

    /**
     * Get the cover image path.
     */
    public function getCoverPath(): ?string
    {
        if (! $this->cover) {
            return null;
        }

        $path = config('nntmux_settings.covers_path').'/games/'.$this->id.'.jpg';

        return file_exists($path) ? $path : null;
    }

    /**
     * Get the backdrop image path.
     */
    public function getBackdropPath(): ?string
    {
        if (! $this->backdrop) {
            return null;
        }

        $path = config('nntmux_settings.covers_path').'/games/'.$this->id.'-backdrop.jpg';

        return file_exists($path) ? $path : null;
    }
}
