<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReleaseUnique extends Model
{
    /**
     * @var string
     */
    protected $table = 'release_unique';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var array
     */
    protected $fillable = [
        'releases_id',
        'uniqueid',
    ];
}
