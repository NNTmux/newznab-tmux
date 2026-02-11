<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\UserExcludedCategory.
 *
 * @property int $id
 * @property int $users_id
 * @property int $categories_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Category $category
 *
 * @method static \Illuminate\Database\Eloquent\Builder|UserExcludedCategory whereUsersId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserExcludedCategory whereCategoriesId($value)
 *
 * @mixin \Eloquent
 */
class UserExcludedCategory extends Model
{
    protected $table = 'user_excluded_categories';

    protected $fillable = [
        'users_id',
        'categories_id',
    ];

    protected function casts(): array
    {
        return [
            'users_id' => 'integer',
            'categories_id' => 'integer',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'categories_id');
    }
}
