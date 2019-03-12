<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RootCategory extends Model
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function categories()
    {
        return $this->hasMany(Category::class, 'root_categories_id');
    }
}
