<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserExcludedCategory extends Model
{
    protected $dateFormat = false;

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'users_id');
    }

    public function category()
    {
        return $this->hasMany('App\Models\Category', 'categories_id');
    }
}
