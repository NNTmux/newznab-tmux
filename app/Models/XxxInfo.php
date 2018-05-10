<?php

namespace App\Models;

use App\Support\Database\CacheQueryBuilder;
use Illuminate\Database\Eloquent\Model;

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
