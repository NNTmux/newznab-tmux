<?php

namespace App\Models;

use Watson\Rememberable\Rememberable;
use Illuminate\Database\Eloquent\Model;

class RootCategory extends Model
{
    use Rememberable;

    protected $guarded = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function categories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Category::class, 'root_categories_id');
    }
}
