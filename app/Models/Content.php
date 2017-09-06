<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Content extends Model
{
    /**
     * @var string
     */
    protected $table = 'content';

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
        'id',
        'title',
        'url',
        'body',
        'metadescription',
        'metakeywords',
        'contenttype',
        'showinmenu',
        'status',
        'ordinal',
        'role',
    ];
}
