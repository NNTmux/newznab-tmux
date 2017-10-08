<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed $release
 * @property mixed $hash
 */
class Predb extends Model
{
    /**
     * @var string
     */
    protected $table = 'predb';

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
    protected $guarded = [];

    public function hash()
    {
        return $this->hasMany('App\Models\PredbHash', 'predb_id');
    }

    public function release()
    {
        return $this->hasOne('App\Models\Release', 'predb_id');
    }
}
