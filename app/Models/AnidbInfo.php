<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\AnidbInfo.
 *
 * @property-read AnidbTitle $title
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
 * @property int|null $anilist_id ID from AniList
 * @property int|null $mal_id ID from MyAnimeList
 * @property string|null $country ISO 3166-1 alpha-2 country code
 * @property string|null $media_type ANIME or MANGA
 * @property int|null $episodes Number of episodes
 * @property int|null $duration Duration in minutes
 * @property string|null $status Media status (FINISHED, RELEASING, etc.)
 * @property string|null $source Original source (MANGA, ORIGINAL, etc.)
 * @property string|null $hashtag AniList hashtag
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
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * @var string
     */
    protected $table = 'anidb_info';

    /**
     * Get the title associated with this info.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\AnidbTitle, $this>
     */
    public function title(): BelongsTo
    {
        return $this->belongsTo(AnidbTitle::class, 'anidbid');
    }

    /**
     * Get the releases associated with this anime.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Release, $this>
     */
    public function releases(): HasMany
    {
        return $this->hasMany(Release::class, 'anidbid', 'anidbid');
    }

    /**
     * Get the picture path.
     */
    public function getPicturePath(): string
    {
        return storage_path('covers/anime/'.$this->anidbid.'.jpg');
    }

    /**
     * Check if picture image exists locally.
     */
    public function hasPictureImage(): bool
    {
        return file_exists($this->getPicturePath());
    }

    /**
     * Get the picture URL.
     */
    public function getPictureUrl(): ?string
    {
        if (empty($this->picture)) {
            return null;
        }

        // If picture is already a URL, return it
        if (filter_var($this->picture, FILTER_VALIDATE_URL)) {
            return $this->picture;
        }

        // Otherwise construct the local path
        if ($this->hasPictureImage()) {
            return url('/covers/anime/'.$this->anidbid.'.jpg');
        }

        return null;
    }

    /**
     * Get AniList URL if anilist_id exists.
     */
    public function getAnilistUrl(): ?string
    {
        if (empty($this->anilist_id)) {
            return null;
        }

        return 'https://anilist.co/anime/'.$this->anilist_id;
    }

    /**
     * Get MyAnimeList URL if mal_id exists.
     */
    public function getMalUrl(): ?string
    {
        if (empty($this->mal_id)) {
            return null;
        }

        return 'https://myanimelist.net/anime/'.$this->mal_id;
    }
}
