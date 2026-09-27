<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\RoleExpirationEmail.
 *
 * @property int $id
 * @property int $users_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $day
 * @property int $week
 * @property int $month
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RoleExpirationEmail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RoleExpirationEmail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RoleExpirationEmail query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RoleExpirationEmail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RoleExpirationEmail whereDay($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RoleExpirationEmail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RoleExpirationEmail whereMonth($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RoleExpirationEmail whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RoleExpirationEmail whereUsersId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RoleExpirationEmail whereWeek($value)
 *
 * @mixin \Eloquent
 */
class RoleExpirationEmail extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }
}
