<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookInfo extends Model
{
    /**
     * @var string
     */
    protected $table = 'bookinfo';

    /**
     * @var bool
     */
    public $timestamps = false;

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
