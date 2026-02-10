<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * App\Models\Invitation
 *
 * @property int $id
 * @property string $token
 * @property string $email
 * @property int $invited_by
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $used_at
 * @property int|null $used_by
 * @property bool $is_active
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $invitedBy
 * @property-read \App\Models\User|null $usedBy
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Invitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Invitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Invitation query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Invitation active()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Invitation valid()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Invitation expired()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Invitation unused()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Invitation used()
 */
class Invitation extends Model
{
    public const DEFAULT_INVITES = 1;

    public const DEFAULT_INVITE_EXPIRY_DAYS = 7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'token',
        'email',
        'invited_by',
        'expires_at',
        'used_at',
        'used_by',
        'is_active',
        'metadata',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * @var array
     */
    protected $dates = [
        'expires_at',
        'used_at',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the user who created this invitation
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Get the user who used this invitation
     */
    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    /**
     * Scope to get only active invitations
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get valid (active and not expired) invitations
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->active() // @phpstan-ignore method.notFound
            ->where('expires_at', '>', now())
            ->whereNull('used_at');
    }

    /**
     * Scope to get expired invitations
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope to get unused invitations
     */
    public function scopeUnused(Builder $query): Builder
    {
        return $query->whereNull('used_at');
    }

    /**
     * Scope to get used invitations
     */
    public function scopeUsed(Builder $query): Builder
    {
        return $query->whereNotNull('used_at');
    }

    /**
     * Check if the invitation is valid
     */
    public function isValid(): bool
    {
        return $this->is_active &&
               $this->expires_at->isFuture() &&
               is_null($this->used_at);
    }

    /**
     * Check if the invitation is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the invitation has been used
     */
    public function isUsed(): bool
    {
        return ! is_null($this->used_at);
    }

    /**
     * Mark the invitation as used
     */
    public function markAsUsed(int $userId): bool
    {
        $this->used_at = now();
        $this->used_by = $userId;
        $this->is_active = false;

        return $this->save();
    }

    /**
     * Mark the invitation as expired
     */
    public function markAsExpired(): bool
    {
        $this->is_active = false;

        return $this->save();
    }

    /**
     * Create a new invitation
     */
    public static function createInvitation(
        string $email,
        int $invitedBy,
        int $expiryDays = self::DEFAULT_INVITE_EXPIRY_DAYS,
        array $metadata = []
    ): self {
        return self::create([
            'token' => Str::random(64),
            'email' => strtolower(trim($email)),
            'invited_by' => $invitedBy,
            'expires_at' => now()->addDays($expiryDays),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Find invitation by token
     */
    public static function findByToken(string $token): ?self
    {
        return self::where('token', $token)->first();
    }

    /**
     * Find valid invitation by token
     */
    public static function findValidByToken(string $token): ?self
    {
        return self::valid()->where('token', $token)->first();
    }

    /**
     * Find invitation by email
     */
    public static function findByEmail(string $email): ?self
    {
        return self::where('email', strtolower(trim($email)))->first();
    }

    /**
     * Get invitation by token (legacy compatibility)
     */
    public static function getInvite(string $token): ?self
    {
        return self::findValidByToken($token);
    }

    /**
     * Delete invitation by token (legacy compatibility)
     */
    public static function deleteInvite(string $token): bool
    {
        $invitation = self::findByToken($token);
        if ($invitation) {
            return $invitation->delete();
        }

        return false;
    }

    /**
     * Add invite (legacy compatibility)
     */
    public static function addInvite(int $userId, string $token): self
    {
        // For legacy compatibility, we create an invitation with a specific token
        // In practice, this should use createInvitation method instead
        return self::create([
            'token' => $token,
            'email' => '', // Legacy method doesn't specify email
            'invited_by' => $userId,
            'expires_at' => now()->addDays(self::DEFAULT_INVITE_EXPIRY_DAYS),
        ]);
    }

    /**
     * Clean up expired invitations
     */
    public static function cleanupExpired(): int
    {
        $expiredCount = self::expired()->active()->count();
        self::expired()->active()->update(['is_active' => false]);

        return $expiredCount;
    }

    /**
     * Get invitation statistics
     */
    public static function getStats(): array
    {
        return [
            'total' => self::count(),
            'active' => self::active()->count(),
            'valid' => self::valid()->count(),
            'expired' => self::expired()->count(),
            'used' => self::used()->count(),
            'unused' => self::unused()->count(),
        ];
    }
}
