<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\Genre.
 *
 * @property int $id
 * @property string $title
 * @property int|null $type
 * @property bool $disabled
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\MusicInfo[] $music
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Genre whereDisabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Genre whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Genre whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Genre whereType($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Genre newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Genre newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Genre query()
 */
class Genre extends Model
{
    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array
     */
    protected $guarded = [];

    public function music(): HasMany
    {
        return $this->hasMany(MusicInfo::class, 'genres_id');
    }
}
