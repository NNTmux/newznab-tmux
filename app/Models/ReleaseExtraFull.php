<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReleaseExtraFull extends Model
{
    /**
     * @var string
     */
    protected $table = 'releaseextrafull';

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
    protected $guarded = [];

    /**
     * @var string
     */
    protected $primaryKey = 'releases_id';

    public function release()
    {
        return $this->belongsTo('App\Models\Release', 'releases_id');
    }
}
