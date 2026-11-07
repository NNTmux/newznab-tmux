<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\LogoutDisabledUsers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LogoutDisabledUsersTest extends TestCase
{
    #[Test]
    public function a_disabled_user_with_a_live_session_is_logged_out_and_sent_to_login(): void
    {
        $request = $this->requestFor($this->userWithRole(UserRole::DISABLED));

        $response = (new LogoutDisabledUsers)->handle($request, fn () => response('page'));

        $this->assertTrue($response->isRedirect(route('login')));
        $this->assertGuest();
    }

    #[Test]
    public function an_active_user_passes_through(): void
    {
        $request = $this->requestFor($this->userWithRole(UserRole::USER));

        $response = (new LogoutDisabledUsers)->handle($request, fn () => response('page'));

        $this->assertSame('page', $response->getContent());
        $this->assertAuthenticated();
    }

    private function userWithRole(UserRole $role): User
    {
        $user = new User;
        $user->id = 7;
        $user->roles_id = $role->value;

        return $user;
    }

    private function requestFor(User $user): Request
    {
        $this->actingAs($user);
        $request = Request::create('/browse');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => Auth::user());

        return $request;
    }
}
