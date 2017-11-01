<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function music()
    {
        return $this->hasMany(MusicInfo::class, 'genres_id');
    }
}
