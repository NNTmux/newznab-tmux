<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleExcludedCategory extends Model
{
    protected $dateFormat = false;

    protected $guarded = [];

    public function role()
    {
        return $this->belongsTo(UserRole::class, 'user_roles_id');
    }

    public function category()
    {
        return $this->hasMany(Category::class, 'categories_id');
    }
}
