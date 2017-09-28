<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsoleInfo extends Model
{
    /**
     * @var string
     */
    protected $table = 'consoleinfo';

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
        'salesrank',
        'platform',
        'publisher',
        'genres_id',
        'esrb',
        'releasedate',
        'review',
        'cover',
        'created_at',
        'updated_at',
    ];
}
