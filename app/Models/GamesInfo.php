<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GamesInfo extends Model
{
    /**
     * @var string
     */
    protected $table = 'gamesinfo';

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $fillable = [
        'id',
        'title',
        'asin',
        'url',
        'publisher',
        'genres_id',
        'esrb',
        'releasedate',
        'review',
        'cover',
        'backdrop',
        'trailer',
        'classused',
        'created_at',
        'updated_at',
    ];
}
