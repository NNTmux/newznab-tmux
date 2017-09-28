<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XxxInfo extends Model
{
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
    protected $guarded = ['id'];
}
