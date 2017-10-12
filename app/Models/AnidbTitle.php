<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnidbTitle extends Model
{
    /**
     * @var string
     */
    protected $primaryKey = 'anidbid';

    /**
     * @var bool
     */
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
     * @var array
     */
    protected $guarded = [];

    public function episode()
    {
        return $this->hasMany('App\Models\AnidbEpisode', 'anidbid');
    }

    public function info()
    {
        return $this->hasMany('App\Models\AnidbInfo', 'anidbid');
    }
}
