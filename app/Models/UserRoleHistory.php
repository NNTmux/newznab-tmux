<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\UserRoleHistory
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $old_role_id
 * @property int $new_role_id
 * @property string|null $old_expiry_date
 * @property string|null $new_expiry_date
 * @property string $effective_date
 * @property bool $is_stacked
 * @property string|null $change_reason
 * @property int|null $changed_by
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read User $user
 * @property-read User|null $changedByUser
 */
class UserRoleHistory extends Model
{
    protected $table = 'user_role_history';

    protected $fillable = [
        'user_id',
        'old_role_id',
        'new_role_id',
        'old_expiry_date',
        'new_expiry_date',
        'effective_date',
        'is_stacked',
        'change_reason',
        'changed_by',
    ];

    protected $casts = [
        'old_expiry_date' => 'datetime',
        'new_expiry_date' => 'datetime',
        'effective_date' => 'datetime',
        'is_stacked' => 'boolean',
    ];

    /**
     * Get the user this history belongs to
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin who made this change
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this>
     */
    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Get the old role
     */
    public function oldRole(): BelongsTo // @phpstan-ignore missingType.generics
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class, 'old_role_id');
    }

    /**
     * Get the new role
     */
    public function newRole(): BelongsTo // @phpstan-ignore missingType.generics
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class, 'new_role_id');
    }

    /**
     * Record a role change
     */
    public static function recordRoleChange(
        int $userId,
        ?int $oldRoleId,
        int $newRoleId,
        ?CarbonInterface $oldExpiryDate,
        ?CarbonInterface $newExpiryDate,
        CarbonInterface $effectiveDate,
        bool $isStacked = false,
        ?string $changeReason = null,
        ?int $changedBy = null
    ): self {
        return static::create([
            'user_id' => $userId,
            'old_role_id' => $oldRoleId,
            'new_role_id' => $newRoleId,
            'old_expiry_date' => $oldExpiryDate,
            'new_expiry_date' => $newExpiryDate,
            'effective_date' => $effectiveDate,
            'is_stacked' => $isStacked,
            'change_reason' => $changeReason,
            'changed_by' => $changedBy,
        ]);
    }

    /**
     * Get role history for a user
     */
    public static function getUserHistory(int $userId): mixed
    {
        return static::where('user_id', $userId)
            ->orderBy('effective_date', 'desc')
            ->get();
    }

    /**
     * Get stacked role changes for a user
     */
    public static function getUserStackedChanges(int $userId): mixed
    {
        return static::where('user_id', $userId)
            ->where('is_stacked', true)
            ->orderBy('effective_date', 'desc')
            ->get();
    }
}
