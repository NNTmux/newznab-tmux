<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnidbEpisodes extends Model
{
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
    protected $fillable = [
        'anidbid',
        'episodeid',
        'episode_no',
        'episode_title',
        'airdate'
    ];

}
