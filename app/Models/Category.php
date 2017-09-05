<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
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
        'parentid',
        'status',
        'description',
        'disablepreview',
        'minsizetoformrelease',
        'maxsizetoformrelease',
    ];
}
