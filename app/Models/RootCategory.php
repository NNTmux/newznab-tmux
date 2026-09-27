<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * App\Models\RootCategory.
 *
 * @property int $id
 * @property string $title
 * @property int $status
 * @property int $disablepreview
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection|Category[] $categories
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
 *
 * @mixin \Eloquent
 */
class RootCategory extends Model
{
    protected $guarded = [];

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'root_categories_id');
    }
}
