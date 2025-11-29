<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\RolePromotionStat
 *
 * @property int $id
 * @property int $user_id
 * @property int $role_promotion_id
 * @property int $role_id
 * @property int $days_added
 * @property \Carbon\Carbon|null $previous_expiry_date
 * @property \Carbon\Carbon|null $new_expiry_date
 * @property \Carbon\Carbon $applied_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @property-read \App\Models\RolePromotion $promotion
 * @property-read \Spatie\Permission\Models\Role $role
 */
class RolePromotionStat extends Model
{
    protected $fillable = [
        'user_id',
        'role_promotion_id',
        'role_id',
        'days_added',
        'previous_expiry_date',
        'new_expiry_date',
        'applied_at',
    ];

    protected $casts = [
        'days_added' => 'integer',
        'previous_expiry_date' => 'datetime',
        'new_expiry_date' => 'datetime',
        'applied_at' => 'datetime',
    ];

    /**
     * Get the user that received the promotion
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the promotion that was applied
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(RolePromotion::class, 'role_promotion_id');
    }

    /**
     * Get the role that was upgraded
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class);
    }

    /**
     * Scope to get stats for a specific promotion
     */
    public function scopeForPromotion($query, int $promotionId)
    {
        return $query->where('role_promotion_id', $promotionId);
    }

    /**
     * Scope to get stats for a specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get stats for a specific role
     */
    public function scopeForRole($query, int $roleId)
    {
        return $query->where('role_id', $roleId);
    }

    /**
     * Scope to get stats within a date range
     */
    public function scopeAppliedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('applied_at', [$startDate, $endDate]);
    }

    /**
     * Get statistics summary for a promotion
     */
    public static function getPromotionSummary(int $promotionId): array
    {
        $stats = static::forPromotion($promotionId)->get();

        return [
            'total_upgrades' => $stats->count(),
            'total_days_added' => $stats->sum('days_added'),
            'unique_users' => $stats->unique('user_id')->count(),
            'roles_affected' => $stats->unique('role_id')->count(),
            'latest_application' => $stats->max('applied_at'),
            'earliest_application' => $stats->min('applied_at'),
        ];
    }

    /**
     * Get statistics summary for a user
     */
    public static function getUserSummary(int $userId): array
    {
        $stats = static::forUser($userId)->get();

        return [
            'total_promotions_received' => $stats->count(),
            'total_days_added' => $stats->sum('days_added'),
            'promotions' => $stats->groupBy('role_promotion_id')->map(function ($group) {
                return [
                    'promotion_id' => $group->first()->role_promotion_id,
                    'promotion_name' => $group->first()->promotion?->name,
                    'times_applied' => $group->count(),
                    'total_days' => $group->sum('days_added'),
                ];
            })->values()->all(),
        ];
    }

    /**
     * Record a promotion application
     */
    public static function recordPromotion(
        int $userId,
        int $promotionId,
        int $roleId,
        int $daysAdded,
        ?\Carbon\Carbon $previousExpiryDate = null,
        ?\Carbon\Carbon $newExpiryDate = null
    ): self {
        return static::create([
            'user_id' => $userId,
            'role_promotion_id' => $promotionId,
            'role_id' => $roleId,
            'days_added' => $daysAdded,
            'previous_expiry_date' => $previousExpiryDate,
            'new_expiry_date' => $newExpiryDate,
            'applied_at' => now(),
        ]);
    }
}

