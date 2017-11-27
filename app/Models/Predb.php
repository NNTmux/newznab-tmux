<?php

namespace App\Models;

use Watson\Rememberable\Rememberable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed $release
 * @property mixed $hash
 */
class Predb extends Model
{
    use Rememberable;

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
        return $this->hasMany(PredbHash::class, 'predb_id');
    }

    public function release()
    {
        return $this->hasMany(Release::class, 'predb_id');
    }
}
