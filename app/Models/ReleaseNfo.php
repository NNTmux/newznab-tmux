<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\ReleaseNfo.
 *
 * @property int $releases_id FK to releases.id
 * @property mixed|null $nfo
 * @property-read Release $release
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseNfo whereNfo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseNfo whereReleasesId($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseNfo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseNfo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseNfo query()
 */
class ReleaseNfo extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    protected $dateFormat = false;

    protected $primaryKey = 'releases_id';

    /**
     * @var array
     */
    protected $guarded = [];

    public function release()
    {
        return $this->belongsTo(Release::class, 'releases_id');
    }

    /**
     * @return Model|null|static
     */
    public static function getReleaseNfo($id, bool $getNfoString = true)
    {
        $nfo = self::query()->where('releases_id', $id)->whereNotNull('nfo')->select(['releases_id']);
        if ($getNfoString === true) {
            $nfo->selectRaw('UNCOMPRESS(nfo) AS nfo');
        }

        return $nfo->first();
    }
}
