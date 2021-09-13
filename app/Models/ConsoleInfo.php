<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

/**
 * App\Models\ConsoleInfo.
 *
 * @property int $id
 * @property string $title
 * @property string|null $asin
 * @property string|null $url
 * @property int|null $salesrank
 * @property string|null $platform
 * @property string|null $publisher
 * @property int|null $genres_id
 * @property string|null $esrb
 * @property string|null $releasedate
 * @property string|null $review
 * @property bool $cover
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ConsoleInfo whereAsin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ConsoleInfo whereCover($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ConsoleInfo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ConsoleInfo whereEsrb($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ConsoleInfo whereGenresId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ConsoleInfo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ConsoleInfo wherePlatform($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ConsoleInfo wherePublisher($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ConsoleInfo whereReleasedate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ConsoleInfo whereReview($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ConsoleInfo whereSalesrank($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ConsoleInfo whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ConsoleInfo whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ConsoleInfo whereUrl($value)
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ConsoleInfo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ConsoleInfo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ConsoleInfo query()
 */
class ConsoleInfo extends Model
{
    use Searchable;
    /**
     * @var string
     */
    protected $table = 'consoleinfo';

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
    public function searchableAs(): string
    {
        return 'ix_consoleinfo_title_platform_ft';
    }

    /**
     * @return array
     */
    public function toSearchableArray(): array
    {
        return [
            'title'=> $this->title,
            'platform' => $this->platform,
        ];
    }
}
