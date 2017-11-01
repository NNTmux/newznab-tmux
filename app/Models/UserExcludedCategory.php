<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserExcludedCategory extends Model
{
    protected $dateFormat = false;

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'categories_id');
    }
}
