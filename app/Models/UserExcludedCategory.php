<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\UserExcludedCategory.
 *
 * @property int $id
 * @property int $users_id
 * @property int $categories_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Category $category
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'categories_id');
    }
}
