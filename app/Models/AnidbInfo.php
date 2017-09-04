<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnidbInfo extends Model
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
    protected $fillable = [
        'anidbid',
        'type',
        'startdate',
        'enddate',
        'updated',
        'related',
        'similar',
        'creators',
        'description',
        'rating',
        'picture',
        'categories',
        'characters',
    ];
}
