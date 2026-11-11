<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class ApiUserResolver
{
    public function v1(string $token): ?User
    {
        // v1 only accepts verified users, so it must never share a cache entry with v2.
        return Cache::remember(self::v1CacheKey($token), 300, static function () use ($token): ?User {
            $user = User::findVerifiedByApiToken($token);
            // `roles` is cached too so hasRole() checks need no query per request.
            $user?->load(['role', 'roles']);

            return $user;
        });
    }

    public function v2(string $token): ?User
    {
        return Cache::remember(self::v2CacheKey($token), 300, static fn (): ?User => User::query()
            ->whereApiToken($token)
            ->with(['role', 'roles'])
            ->first());
    }

    /**
     * Every cache key that may hold the user for this API token.
     *
     * @return list<string>
     */
    public static function cacheKeys(string $token): array
    {
        return [self::v1CacheKey($token), self::v2CacheKey($token)];
    }

    private static function v1CacheKey(string $token): string
    {
        return 'api_user:v1:'.md5($token);
    }

    private static function v2CacheKey(string $token): string
    {
        return 'api_user:v2:'.md5($token);
    }
}
