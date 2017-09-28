<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovieInfo extends Model
{
    /**
     * @var string
     */
    protected $table = 'movieinfo';

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $guarded = ['id'];
}
