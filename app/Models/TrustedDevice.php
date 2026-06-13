<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $last_used_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
class TrustedDevice extends Model
{
    protected $fillable = [
        'user_id',
        'token_hash',
        'expires_at',
        'last_used_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array{plain: string, device: self}
     */
    public static function issueForUser(User $user, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        $plainToken = Str::random(64);
        $now = now();

        /** @var self $device */
        $device = self::create([
            'user_id' => $user->id,
            'token_hash' => self::hashToken($plainToken),
            'expires_at' => $now->copy()->addDays(30)->toDateTimeString(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent !== null ? Str::limit($userAgent, 500, '') : null,
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]);

        return ['plain' => $plainToken, 'device' => $device];
    }

    public static function findValidForUser(int $userId, string $plainToken): ?self
    {
        if ($plainToken === '') {
            return null;
        }

        /** @var self|null $device */
        $device = self::query()
            ->where('user_id', $userId)
            ->where('token_hash', self::hashToken($plainToken))
            ->where('expires_at', '>', now())
            ->first();

        if ($device !== null) {
            $device->forceFill(['last_used_at' => now()])->save();
        }

        return $device;
    }

    public static function hashToken(string $plainToken): string
    {
        return hash_hmac('sha256', $plainToken, (string) config('app.key'));
    }
}
