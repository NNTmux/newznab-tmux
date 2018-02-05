<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TvInfo extends Model
{
    /**
     * @var string
     */
    protected $table = 'tv_info';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var string
     */
    protected $primaryKey = 'videos_id';
}
