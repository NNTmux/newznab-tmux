<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

/**
 * App\Models\RolePromotion
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property array $applicable_roles
 * @property int $additional_days
 * @property \Carbon\Carbon|null $start_date
 * @property \Carbon\Carbon|null $end_date
 * @property bool $is_active
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\RolePromotionStat[] $statistics
 */
class RolePromotion extends Model
{
    protected $fillable = [
        'name',
        'description',
        'applicable_roles',
        'additional_days',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'additional_days' => 'integer',
        'applicable_roles' => 'array',
    ];

    /**
     * Get the statistics for this promotion
     */
    public function statistics(): HasMany
    {
        return $this->hasMany(RolePromotionStat::class);
    }

    /**
     * Get the roles this promotion applies to
     */
    public function getApplicableRolesModels()
    {
        if (empty($this->applicable_roles)) {
            return static::getCustomRoles();
        }

        return Role::whereIn('id', $this->applicable_roles)->get();
    }

    /**
     * Scope to get only valid (non-expired) promotions
     */
    public function scopeValid($query)
    {
        $now = Carbon::now();

        return $query->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', $now);
            });
    }

    /**
     * Get custom roles (excluding system roles)
     */
    public static function getCustomRoles()
    {
        $systemRoles = ['Admin', 'User', 'Moderator', 'Disabled', 'Friend'];

        return Role::whereNotIn('name', $systemRoles)->get();
    }

    /**
     * Check if the promotion is currently active
     */
    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = Carbon::now();

        if ($this->start_date && $now->lt($this->start_date)) {
            return false;
        }

        if ($this->end_date && $now->gt($this->end_date)) {
            return false;
        }

        return true;
    }

    /**
     * Get active promotions for a specific role
     */
    public static function getActivePromotions(?int $roleId = null)
    {
        $query = static::where('is_active', true);

        $now = Carbon::now();
        $query->where(function ($q) use ($now) {
            $q->whereNull('start_date')
                ->orWhere('start_date', '<=', $now);
        });

        $query->where(function ($q) use ($now) {
            $q->whereNull('end_date')
                ->orWhere('end_date', '>=', $now);
        });

        if ($roleId !== null) {
            $query->where(function ($q) use ($roleId) {
                // If applicable_roles is empty, applies to all custom roles
                $q->whereJsonLength('applicable_roles', 0)
                    ->orWhereJsonContains('applicable_roles', $roleId);
            });
        }

        return $query->get();
    }

    /**
     * Calculate total additional days from active promotions
     */
    public static function calculateAdditionalDays(int $roleId): int
    {
        $promotions = static::getActivePromotions($roleId);

        return $promotions->sum('additional_days');
    }

    /**
     * Get summary statistics for this promotion
     */
    public function getSummaryStatistics(): array
    {
        return RolePromotionStat::getPromotionSummary($this->id);
    }

    /**
     * Get the count of users who have received this promotion
     */
    public function getTotalUpgrades(): int
    {
        return $this->statistics()->count();
    }

    /**
     * Get the count of unique users who have received this promotion
     */
    public function getUniqueUsersCount(): int
    {
        return $this->statistics()->distinct('user_id')->count('user_id');
    }

    /**
     * Get the total days added across all applications of this promotion
     */
    public function getTotalDaysAdded(): int
    {
        return $this->statistics()->sum('days_added');
    }

    /**
     * Track a promotion application
     */
    public function trackApplication(
        int $userId,
        int $roleId,
        ?\Illuminate\Support\Carbon $previousExpiryDate = null,
        ?\Illuminate\Support\Carbon $newExpiryDate = null
    ): RolePromotionStat {
        return RolePromotionStat::recordPromotion(
            $userId,
            $this->id,
            $roleId,
            $this->additional_days,
            $previousExpiryDate,
            $newExpiryDate
        );
    }

    /**
     * Get statistics grouped by role
     */
    public function getStatisticsByRole(): array
    {
        return $this->statistics()
            ->with('role')
            ->get()
            ->groupBy('role_id')
            ->map(function ($stats, $roleId) {
                /** @var \App\Models\RolePromotionStat|null $firstStat */
                $firstStat = $stats->first();

                return [
                    'role_id' => $roleId,
                    'role_name' => $firstStat?->role?->name,
                    'total_upgrades' => $stats->count(),
                    'total_days_added' => $stats->sum('days_added'),
                    'unique_users' => $stats->unique('user_id')->count(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Get statistics for a specific time period
     */
    public function getStatisticsForPeriod(Carbon $startDate, Carbon $endDate): array
    {
        /** @phpstan-ignore method.notFound */
        $stats = $this->statistics()
            ->appliedBetween($startDate, $endDate)
            ->get();

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'total_upgrades' => $stats->count(),
            'total_days_added' => $stats->sum('days_added'),
            'unique_users' => $stats->unique('user_id')->count(),
            'roles_affected' => $stats->unique('role_id')->count(),
        ];
    }
}
