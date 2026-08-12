<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class ExpireWebLogins
{
    public function __construct(private readonly WebLoginSessionPolicy $webLoginSessionPolicy) {}

    public function expireAll(Request $request): int
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new RuntimeException('An authenticated user is required to expire web logins.');
        }

        return DB::transaction(function () use ($actor, $request): int {
            $this->expireUserState($actor);
            $count = 1;

            User::withTrashed()
                ->whereKeyNot($actor->getKey())
                ->chunkById(500, function ($users) use (&$count): void {
                    foreach ($users as $user) {
                        $this->expireUserState($user);
                        $count++;
                    }
                });

            TrustedDevice::query()->delete();
            $request->session()->put(WebLoginSessionPolicy::SESSION_TOKEN_KEY, $actor->session_token);
            $request->session()->forget(WebLoginSessionPolicy::REMEMBERED_LOGIN_KEY);

            return $count;
        });
    }

    public function expireForUser(Request $request, User $target): void
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new RuntimeException('An authenticated user is required to expire web logins.');
        }

        DB::transaction(function () use ($actor, $request, $target): void {
            $subject = $actor->is($target) ? $actor : $target;
            $this->expireUserState($subject);
            TrustedDevice::query()->where('user_id', $subject->getKey())->delete();

            if ($actor->is($subject)) {
                $request->session()->put(WebLoginSessionPolicy::SESSION_TOKEN_KEY, $actor->session_token);
                $request->session()->forget(WebLoginSessionPolicy::REMEMBERED_LOGIN_KEY);
            }
        });
    }

    private function expireUserState(User $user): void
    {
        $user->forceFill([
            'remember_token' => Str::random(60),
            'session_token' => $this->webLoginSessionPolicy->newAdminExpiryToken(),
        ])->save();
    }
}
