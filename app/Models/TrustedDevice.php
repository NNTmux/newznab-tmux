<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

        DB::insert(
            'insert into trusted_devices (user_id, token_hash, expires_at, ip_address, user_agent, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?)',
            [
                $user->id,
                self::hashToken($plainToken),
                $now->copy()->addDays(30)->toDateTimeString(),
                $ipAddress,
                $userAgent !== null ? Str::limit($userAgent, 500, '') : null,
                $now->toDateTimeString(),
                $now->toDateTimeString(),
            ]
        );

        $deviceId = (int) DB::getPdo()->lastInsertId();

        /** @var self $device */
        $device = self::query()->findOrFail($deviceId);

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
