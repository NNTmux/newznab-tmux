<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookInfo extends Model
{
    const CREATED_AT = 'createddate';
    const UPDATED_AT = 'updateddate';

    /**
     * @var string
     */
    protected $table = 'bookinfo';

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $fillable = [
        'title',
        'author',
        'asin',
        'isbn',
        'ean',
        'url',
        'salesrank',
        'publisher',
        'publishdate',
        'pages',
        'overview',
        'genre',
        'cover',
        'createddate',
        'updateddate',
    ];
}
