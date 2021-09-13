<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\RootCategory.
 *
 * @property int $id
 * @property string $title
 * @property int $status
 * @property int $disablepreview
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Category[] $categories
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RootCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RootCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RootCategory query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RootCategory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RootCategory whereDisablepreview($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RootCategory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RootCategory whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RootCategory whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RootCategory whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class RootCategory extends Model
{
    protected $guarded = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function categories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Category::class, 'root_categories_id');
    }
}
