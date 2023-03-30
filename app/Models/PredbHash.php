<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\PredbHash.
 *
 * @property int $predb_id id, of the predb entry, this hash belongs to
 * @property mixed $hash
 * @property-read \App\Models\Predb $predb
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PredbHash whereHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PredbHash wherePredbId($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PredbHash newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PredbHash newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PredbHash query()
 */
class PredbHash extends Model
{
    /**
     * @var bool
     */
    public $incrementing = false;

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

    /**
     * @var string
     */
    protected $primaryKey = 'hash';

    public function predb(): BelongsTo
    {
        return $this->belongsTo(Predb::class, 'predb_id');
    }
}
