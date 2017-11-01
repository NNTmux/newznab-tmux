<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function predb()
    {
        return $this->belongsTo(Predb::class, 'predb_id');
    }
}
