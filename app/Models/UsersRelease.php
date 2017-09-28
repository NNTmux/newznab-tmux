<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsersRelease extends Model
{
    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo('\App\Models\User', 'users_id');
    }

    public function release()
    {
        return $this->belongsTo('App\Models\Release', 'releases_id');
    }
}
