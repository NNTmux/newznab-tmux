<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MusicInfo extends Model
{
    /**
     * @var string
     */
    protected $table = 'musicinfo';
    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $timestamps = true;

    /**
     * @var array
     */
    protected $guarded = [];

    public function genre()
    {
        return $this->belongsTo(Genre::class, 'genres_id');
    }
}
