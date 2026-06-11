<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class UserApiCacheObserver
{
    public function updated(User $user): void
    {
        if (! $user->wasChanged(['api_token', 'roles_id', 'rate_limit', 'verified', 'email_verified_at', 'deleted_at'])) {
            return;
        }

        $this->flushTokenCaches($user, $user->getOriginal('api_token'));
        $this->flushTokenCaches($user, $user->api_token);
    }

    public function deleted(User $user): void
    {
        $this->flushTokenCaches($user, $user->api_token);
    }

    public function restored(User $user): void
    {
        $this->flushTokenCaches($user, $user->api_token);
    }

    public function forceDeleted(User $user): void
    {
        $this->flushTokenCaches($user, $user->api_token);
    }

    private function flushTokenCaches(User $user, mixed $apiToken): void
    {
        if (! is_string($apiToken) || $apiToken === '') {
            return;
        }

        $tokenHash = md5($apiToken);

        Cache::forget('api_user:'.$tokenHash);
        Cache::forget('api_rate_limit_user:'.$tokenHash);
        Cache::forget('api_user_stats:'.$user->id);
    }
}

