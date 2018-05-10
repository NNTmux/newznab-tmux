<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\Database\CacheQueryBuilder;

class XxxInfo extends Model
{
    use CacheQueryBuilder;
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
    protected $guarded = [];
}
