<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReleaseNfo extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    protected $dateFormat = false;

    protected $primaryKey = 'releases_id';

    /**
     * @var array
     */
    protected $guarded = [];

    public function release()
    {
        return $this->belongsTo('App\Models\Release', 'releases_id');
    }
}
