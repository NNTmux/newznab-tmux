<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\ExpireWebLogins;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;

final class AdminLoginSessionController extends Controller
{
    public function __construct(private readonly ExpireWebLogins $expireWebLogins) {}

    public function expireAll(Request $request): RedirectResponse
    {
        $expiredUsers = $this->expireWebLogins->expireAll($request);
        $this->expireAuthenticationCookies();

        Log::channel('admin')->warning('Admin expired every web login', [
            'admin_user_id' => $request->user()?->getAuthIdentifier(),
            'expired_users' => $expiredUsers,
        ]);

        return redirect()
            ->route('admin.site-edit')
            ->with('success', 'All web logins have been expired except your current session.');
    }

    public function expireUser(Request $request, User $user): RedirectResponse
    {
        $this->expireWebLogins->expireForUser($request, $user);

        if ($request->user()?->getAuthIdentifier() === $user->getAuthIdentifier()) {
            $this->expireAuthenticationCookies();
        }

        Log::channel('admin')->warning('Admin expired a user\'s web logins', [
            'admin_user_id' => $request->user()?->getAuthIdentifier(),
            'target_user_id' => $user->getAuthIdentifier(),
            'target_username' => $user->username,
        ]);

        return redirect()
            ->route('admin.user-edit', ['id' => $user->getAuthIdentifier()])
            ->with('success', "All web logins for {$user->username} have been expired.");
    }

    private function expireAuthenticationCookies(): void
    {
        Cookie::expire('2fa_trusted_device');

        $guard = Auth::guard();

        if ($guard instanceof SessionGuard) {
            Cookie::expire($guard->getRecallerName());
        }
    }
}
