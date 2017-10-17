<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
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

    public function releases()
    {
        return $this->hasMany('App\Models\Release', 'categories_id');
    }

    public function parent()
    {
        return $this->belongsTo(static::class, 'parentid');
    }

    public function children()
    {
        return $this->hasMany(static::class, 'parentid');
    }

    public function userExcludedCategory()
    {
        return $this->belongsTo('App\Models\UserExcludedCategory', 'categories_id');
    }

    public function roleExcludedCategory()
    {
        return $this->belongsTo('App\Models\RoleExcludedCategory', 'categories_id');
    }
}
