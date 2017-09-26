<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsersRelease extends Model
{
    const CREATED_AT = 'createddate';
    const UPDATED_AT = 'updateddate';

    protected $dateFormat = false;

    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo('\App\Models\User', 'users_id');
    }

    public function release()
    {
        return $this->belongsTo('App\Models\Release', 'id');
    }
}
