<?php

namespace App\Models;

use Yadakhov\InsertOnDuplicateKey;
use Illuminate\Database\Eloquent\Model;

class VideoAlias extends Model
{
    use InsertOnDuplicateKey;

    protected $table = 'videos_aliases';
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function video()
    {
        return $this->belongsTo(Video::class, 'videos_id');
    }
}
