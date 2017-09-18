<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XxxInfo extends Model
{
    const CREATED_AT = 'createddate';
    const UPDATED_AT = 'updateddate';

    /**
     * @var string
     */
    protected $table = 'xxxinfo';

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
        'tagline',
        'plot',
        'genre',
        'director',
        'actors',
        'extras',
        'productinfo',
        'trailers',
        'directurl',
        'classused',
        'cover',
        'backdrop',
        'createddate',
        'updateddate',
    ];
}
