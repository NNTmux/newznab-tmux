<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Events\UserLoggedIn;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPasskeys\Models\Passkey;

final class PasskeyLogoutOtherDevicesTest extends PasskeyAuthenticationTest
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth'])->get('__session_token_probe', fn () => response('ok', 200));
    }

    public function test_passkey_login_keeps_concurrent_sessions_when_single_active_session_is_off(): void
    {
        Event::fake([UserLoggedIn::class, OtherDeviceLogout::class]);
        $user = $this->createUser('passkey-logout-devices@example.test');
        $user->forceFill(['session_token' => 'pre_rotation_token'])->save();

        $passkey = new Passkey;
        $passkey->setRawAttributes([
            'id' => 10,
            'authenticatable_id' => $user->id,
            'name' => 'Laptop',
            'credential_id' => 'credential-10',
            'data' => '{}',
        ], true);
        $passkey->setRelation('authenticatable', $user);

        FakeFindPasskeyAction::$passkey = $passkey;
        config()->set('passkeys.actions.find_passkey', FakeFindPasskeyAction::class);

        $this
            ->withSession(['passkey-authentication-options' => '{}'])
            ->post(route('passkeys.login'), [
                'start_authentication_response' => json_encode(['id' => 'credential-10'], JSON_THROW_ON_ERROR),
                'remember' => false,
            ])
            ->assertRedirect('/');

        $user->refresh();
        $this->assertSame('pre_rotation_token', $user->session_token);
        $this->assertSame($user->session_token, session('session_token_web'));
        Event::assertNotDispatched(OtherDeviceLogout::class);
    }

    public function test_passkey_login_rotates_session_token_when_single_active_session_is_on(): void
    {
        DB::table('settings')->insert([
            'name' => 'single_active_session',
            'value' => '1',
        ]);

        Event::fake([UserLoggedIn::class, OtherDeviceLogout::class]);
        $user = $this->createUser('passkey-single-session@example.test');
        $user->forceFill(['session_token' => 'pre_rotation_token'])->save();

        $passkey = new Passkey;
        $passkey->setRawAttributes([
            'id' => 11,
            'authenticatable_id' => $user->id,
            'name' => 'Laptop',
            'credential_id' => 'credential-11',
            'data' => '{}',
        ], true);
        $passkey->setRelation('authenticatable', $user);

        FakeFindPasskeyAction::$passkey = $passkey;
        config()->set('passkeys.actions.find_passkey', FakeFindPasskeyAction::class);

        $this
            ->withSession(['passkey-authentication-options' => '{}'])
            ->post(route('passkeys.login'), [
                'start_authentication_response' => json_encode(['id' => 'credential-11'], JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect('/');

        $user->refresh();
        $this->assertNotSame('pre_rotation_token', $user->session_token);
        $this->assertSame($user->session_token, session('session_token_web'));

        Event::assertDispatched(OtherDeviceLogout::class, function (OtherDeviceLogout $event) use ($user): bool {
            return (int) $event->user->getAuthIdentifier() === (int) $user->getAuthIdentifier();
        });
    }

    public function test_stale_session_token_is_ignored_when_single_active_session_is_off(): void
    {
        $user = $this->createUser('passkey-stale-session@example.test');
        $user->forceFill(['session_token' => 'current_user_token'])->save();

        /** @var Authenticatable $auth */
        $auth = $user;

        $this->actingAs($auth)
            ->withSession(['session_token_web' => 'stale_wrong_token'])
            ->get('/__session_token_probe')
            ->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_stale_session_token_logs_out_with_an_accurate_message_when_single_active_session_is_on(): void
    {
        DB::table('settings')->insert([
            'name' => 'single_active_session',
            'value' => '1',
        ]);

        $user = $this->createUser('passkey-stale-enforced@example.test');
        $user->forceFill(['session_token' => 'current_user_token'])->save();

        /** @var Authenticatable $auth */
        $auth = $user;

        $this->actingAs($auth)
            ->withSession(['session_token_web' => 'stale_wrong_token'])
            ->get('/__session_token_probe')
            ->assertRedirect(route('login'))
            ->assertSessionHas('info', 'You were signed out because this account signed in on another device.');

        $this->assertGuest();
    }

    public function test_single_session_token_message_wins_over_password_hash_invalidation(): void
    {
        DB::table('settings')->insert([
            'name' => 'single_active_session',
            'value' => '1',
        ]);
        $user = $this->createUser('password-session-message@example.test');
        $oldPasswordHash = $user->password;
        $user->forceFill([
            'password' => bcrypt('replacement-password'),
            'session_token' => 's.current_session_token',
        ])->save();

        /** @var Authenticatable $auth */
        $auth = $user;

        $this->actingAs($auth)
            ->withSession([
                'password_hash_web' => $oldPasswordHash,
                'session_token_web' => 's.stale_session_token',
            ])
            ->get('/__session_token_probe')
            ->assertRedirect(route('login'))
            ->assertSessionHas('info', 'You were signed out because this account signed in on another device.');
    }

    public function test_session_token_web_is_adopted_when_missing(): void
    {
        $user = $this->createUser('passkey-adopt-session@example.test');
        $user->forceFill(['session_token' => 'adopt_me_token'])->save();

        /** @var Authenticatable $auth */
        $auth = $user;

        $this->actingAs($auth)
            ->get('/__session_token_probe')
            ->assertOk();

        $this->assertSame('adopt_me_token', session('session_token_web'));
        $this->assertAuthenticatedAs($user);
    }
}
