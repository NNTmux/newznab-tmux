<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Events\UserLoggedIn;
use App\Models\PasswordSecurity;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\PasswordBreachService;
use App\View\Composers\GlobalDataComposer;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PragmaRX\Google2FALaravel\Facade as Google2FA;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RememberMeAuthenticationTest extends TestCase
{
    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-remember-me-auth-test.sqlite';

        $this->originalEnvironment = [
            'APP_ENV' => getenv('APP_ENV'),
            'DB_CONNECTION' => getenv('DB_CONNECTION'),
            'DB_DATABASE' => getenv('DB_DATABASE'),
        ];

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        $pdo = new PDO('sqlite:'.$this->databasePath);
        $pdo->exec('CREATE TABLE settings (name VARCHAR PRIMARY KEY, value TEXT NULL)');
        $pdo->exec("INSERT INTO settings (name, value) VALUES
            ('categorizeforeign', '0'),
            ('catwebdl', '0'),
            ('innerfileblacklist', ''),
            ('title', 'NNTmux Test'),
            ('home_link', '/')");

        $this->setEnvironmentValue('APP_ENV', 'testing');
        $this->setEnvironmentValue('DB_CONNECTION', 'sqlite');
        $this->setEnvironmentValue('DB_DATABASE', $this->databasePath);

        $app = require __DIR__.'/../../../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'session.driver' => 'array',
            'google2fa.session_var' => 'google2fa',
        ]);

        DB::purge();
        DB::reconnect();

        $this->createSchema();
        Schema::create('content', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('status');
            $table->integer('contenttype');
            $table->integer('ordinal')->nullable();
        });
        $this->seedSettings();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->app->instance(PasswordBreachService::class, new class extends PasswordBreachService
        {
            public function isPasswordBreached(string $password): bool
            {
                return false;
            }
        });

        Route::middleware(['web', 'auth'])->get('__remember_me_probe', fn () => response('ok', 200));
        Route::middleware(['web', 'auth', '2fa'])->get('__otp_probe', fn () => response('otp passed'));
    }

    protected function tearDown(): void
    {
        if ($this->databasePath !== '' && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();

        foreach ($this->originalEnvironment as $key => $value) {
            $this->setEnvironmentValue($key, $value === false ? null : $value);
        }
    }

    public function test_password_login_with_remember_me_queues_recaller_cookie(): void
    {
        Event::fake([UserLoggedIn::class]);
        $user = $this->createUser('remember-password@example.test');

        $response = $this->post(route('login'), [
            'username' => $user->email,
            'password' => 'password',
            'rememberme' => 'on',
        ]);

        $response->assertRedirect('/');
        $response->assertCookie($this->recallerCookieName());
        $this->assertAuthenticatedAs($user);
    }

    public function test_turnstile_failure_is_visible_after_redirect_and_is_logged(): void
    {
        $this->enableTurnstile();
        Event::fake([UserLoggedIn::class]);
        Log::spy();
        $user = $this->createUser('captcha-failure@example.test');
        Http::fake(['challenges.cloudflare.com/*' => Http::response([
            'success' => false,
            'error-codes' => ['timeout-or-duplicate'],
        ])]);

        $message = 'Cloudflare human verification could not be confirmed. Please reload the page, complete the verification, and try again.';
        $response = $this->withHeader('CF-Ray', 'login-ray')->post(route('login'), [
            'username' => $user->email,
            'password' => 'password',
            'cf-turnstile-response' => 'expired-token',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['cf-turnstile-response' => $message]);
        $this->assertGuest();
        Event::assertNotDispatched(UserLoggedIn::class);
        Log::shouldHaveReceived('warning')->once()->with('Turnstile verification failed.', \Mockery::on(
            fn (array $context): bool => $context['route'] === 'login.post'
                && $context['cf_ray'] === 'login-ray'
                && $context['error_codes'] === ['timeout-or-duplicate']
        ));

        (new \ReflectionProperty(GlobalDataComposer::class, 'resolvedData'))->setValue(null, null);
        $this->get(route('login'))->assertOk()
            ->assertSee($message)
            ->assertSee('data-prefers-password="1"', false);
    }

    public function test_missing_or_malformed_turnstile_token_shows_a_clear_message(): void
    {
        $this->enableTurnstile();
        Http::fake();

        foreach ([null, ['invalid-token']] as $token) {
            $response = $this->post(route('login'), [
                'username' => 'captcha@example.test',
                'password' => 'password',
                'cf-turnstile-response' => $token,
            ]);

            $response->assertRedirect(route('login'));
            $response->assertSessionHasErrors([
                'cf-turnstile-response' => 'Please complete the Cloudflare human verification before signing in.',
            ]);
            $this->assertGuest();
        }

        Http::assertNothingSent();
    }

    public function test_valid_turnstile_token_allows_password_login(): void
    {
        $this->enableTurnstile();
        Event::fake([UserLoggedIn::class]);
        $user = $this->createUser('captcha-success@example.test');
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->post(route('login'), [
            'username' => $user->email,
            'password' => 'password',
            'cf-turnstile-response' => 'valid-token',
        ])->assertRedirect('/')->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
        $this->get('/__remember_me_probe')->assertOk();
    }

    private function enableTurnstile(): void
    {
        config([
            'captcha.provider' => 'turnstile',
            'captcha.turnstile.enabled' => true,
            'captcha.turnstile.sitekey' => 'test-sitekey',
            'captcha.turnstile.secret' => 'test-secret',
            'captcha.recaptcha.enabled' => false,
        ]);
        Http::preventStrayRequests();
    }

    public function test_password_login_with_boolean_remember_me_value_queues_recaller_cookie(): void
    {
        Event::fake([UserLoggedIn::class]);
        $user = $this->createUser('remember-password-boolean@example.test');

        $response = $this->post(route('login'), [
            'username' => $user->email,
            'password' => 'password',
            'rememberme' => '1',
        ]);

        $response->assertRedirect('/');
        $response->assertCookie($this->recallerCookieName());
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_login_with_remember_me_restores_authentication_after_session_expires(): void
    {
        Event::fake([UserLoggedIn::class]);
        $user = $this->createUser('remember-restore@example.test');

        $response = $this->post(route('login'), [
            'username' => $user->email,
            'password' => 'password',
            'rememberme' => 'on',
        ]);

        $recallerCookie = $response->getCookie($this->recallerCookieName(), decrypt: false);
        $this->assertNotNull($recallerCookie);
        $decryptedRecallerCookie = $response->getCookie($this->recallerCookieName());
        $this->assertNotNull($decryptedRecallerCookie);

        [, $rememberToken] = explode('|', (string) $decryptedRecallerCookie->getValue(), 3);
        $this->assertSame($user->fresh()?->remember_token, $rememberToken);

        $this->flushSession();
        Auth::forgetGuards();

        $this
            ->withUnencryptedCookie($this->recallerCookieName(), $recallerCookie->getValue())
            ->get('/__remember_me_probe')
            ->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_password_login_without_remember_me_does_not_queue_recaller_cookie(): void
    {
        Event::fake([UserLoggedIn::class]);
        $user = $this->createUser('session-password@example.test');

        $response = $this->post(route('login'), [
            'username' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $response->assertCookieMissing($this->recallerCookieName());
        $this->assertAuthenticatedAs($user);
    }

    public function test_username_password_login_survives_the_next_request(): void
    {
        $user = $this->createUser('next-request@example.test');
        $previousPasswordHash = Auth::hashPasswordForCookie($user->getAuthPassword());

        $response = $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $cookie = $response->getCookie(config('session.cookie'), decrypt: false);
        $this->assertNotNull($cookie);
        Auth::forgetGuards();

        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
            ->get('/__remember_me_probe')->assertOk();
        $this->assertAuthenticatedAs($user);

        $this->withSession(['password_hash_web' => $previousPasswordHash]);
        session()->save();
        Auth::forgetGuards();
        $this->get('/__remember_me_probe')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_password_login_replaces_a_previous_password_hash_in_the_session(): void
    {
        $user = $this->createUser('previous-session@example.test');
        $this->withSession(['password_hash_web' => bcrypt('previous-password')]);

        $response = $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'password',
            'rememberme' => 'on',
        ]);

        $response->assertRedirect('/');
        $cookie = $response->getCookie(config('session.cookie'), decrypt: false);
        $this->assertNotNull($cookie);
        Auth::forgetGuards();

        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
            ->get('/__remember_me_probe')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_login_records_the_client_ip_when_ip_storage_is_enabled(): void
    {
        Event::fake([UserLoggedIn::class]);
        config()->set('nntmux_settings.store_user_ip', true);
        $user = $this->createUser('ip-address@example.test');

        $this
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->post(route('login'), [
                'username' => $user->email,
                'password' => 'password',
            ])
            ->assertRedirect('/');

        Event::assertDispatched(
            UserLoggedIn::class,
            fn (UserLoggedIn $event): bool => $event->user->is($user) && $event->ip === '203.0.113.42'
        );
    }

    public function test_two_factor_login_preserves_remember_me_until_otp_success(): void
    {
        Event::fake([UserLoggedIn::class]);
        $user = $this->createUser('remember-2fa@example.test');
        $secret = Google2FA::generateSecretKey();
        PasswordSecurity::query()->create([
            'user_id' => $user->id,
            'google2fa_enable' => 1,
            'google2fa_secret' => $secret,
        ]);

        $loginResponse = $this->post(route('login'), [
            'username' => $user->email,
            'password' => 'password',
            'rememberme' => 'on',
        ]);

        $loginResponse->assertRedirect(route('2fa.verify'));
        $loginResponse->assertCookieMissing($this->recallerCookieName());
        $this->assertGuest();
        $this->assertTrue((bool) session('2fa:remember'));
        $this->assertSame($user->id, session('2fa:user:id'));
        $this->assertFalse((bool) session(config('google2fa.session_var').'.auth_passed'));

        $verifyResponse = $this->post(route('2fa.post'), [
            'one_time_password' => Google2FA::getCurrentOtp($secret),
        ]);

        $verifyResponse->assertRedirect('/');
        $verifyResponse->assertCookie($this->recallerCookieName());
        $this->assertAuthenticatedAs($user);
        $this->assertTrue((bool) session(config('google2fa.session_var').'.auth_passed'));
        $this->assertNull(session('2fa:remember'));
        $this->assertNull(session('2fa:user:id'));
        $this->assertNull(session('2fa:password_breached'));
    }

    public function test_two_factor_login_without_remember_me_does_not_queue_recaller_cookie_after_otp_success(): void
    {
        config(['google2fa.enabled' => true, 'google2fa.lifetime' => 5]);
        Event::fake([UserLoggedIn::class]);
        $user = $this->createUser('session-2fa@example.test');
        $secret = Google2FA::generateSecretKey();
        PasswordSecurity::query()->create([
            'user_id' => $user->id,
            'google2fa_enable' => 1,
            'google2fa_secret' => $secret,
        ]);

        $this->post(route('login'), [
            'username' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('2fa.verify'));

        $this->get(route('2fa.verify'))->assertOk();
        $this->withSession(['url.intended' => '/__otp_probe']);
        $verifyResponse = $this->post(route('2fa.post'), [
            'one_time_password' => Google2FA::getCurrentOtp($secret),
            'trust_device' => false,
        ]);

        $verifyResponse->assertRedirect('/__otp_probe');
        $verifyResponse->assertCookieMissing($this->recallerCookieName());
        $verifyResponse->assertCookieMissing('2fa_trusted_device');
        $verifyResponse->assertSessionMissing('2fa:user:id');
        $verifyResponse->assertSessionHas('google2fa.auth_passed', true);
        $verifyResponse->assertSessionHas('google2fa.auth_time');
        $cookie = $verifyResponse->getCookie(config('session.cookie'), decrypt: false);
        $this->assertNotNull($cookie);
        Auth::forgetGuards();
        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
            ->get('/__otp_probe')->assertOk()->assertSeeText('otp passed');
        $this->get('/__otp_probe')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_otp_keeps_the_pending_login_and_does_not_authenticate(): void
    {
        config(['google2fa.enabled' => true]);
        Event::fake([UserLoggedIn::class]);
        $user = $this->createUser('invalid-otp@example.test');
        $secret = Google2FA::generateSecretKey();
        PasswordSecurity::query()->create([
            'user_id' => $user->id,
            'google2fa_enable' => true,
            'google2fa_secret' => $secret,
        ]);
        $this->post(route('login'), ['username' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('2fa.verify'));
        $this->post(route('2fa.post'), ['one_time_password' => '123456789'])
            ->assertRedirect(route('2fa.verify'))
            ->assertSessionHas('2fa:user:id', $user->id)
            ->assertSessionMissing('google2fa.auth_passed');
        $this->assertGuest();
        $this->get('/__otp_probe')->assertRedirect(route('login'));
    }

    public function test_remembered_session_requires_mfa_and_recent_auth_before_managing_passkeys(): void
    {
        $user = $this->createUser('remembered-mfa@example.test');
        $secret = Google2FA::generateSecretKey();
        PasswordSecurity::query()->create([
            'user_id' => $user->id, 'google2fa_enable' => true, 'google2fa_secret' => $secret,
        ]);
        config(['google2fa.enabled' => true]);
        $this->post(route('login'), [
            'username' => $user->email, 'password' => 'password', 'rememberme' => 'on',
        ])->assertRedirect(route('2fa.verify'));
        $login = $this->post(route('2fa.post'), ['one_time_password' => Google2FA::getCurrentOtp($secret)]);
        $recaller = $login->getCookie($this->recallerCookieName(), decrypt: false);
        $this->assertNotNull($recaller);
        $this->flushSession();
        Auth::forgetGuards();
        $this->withCredentials()->withUnencryptedCookie($this->recallerCookieName(), $recaller->getValue());

        $this->postJson(route('passkeys.register_options'), ['name' => 'Attacker key'])
            ->assertForbidden()->assertSessionMissing('passkey-registration-options');
        $this->postJson(route('passkeys.store'), ['name' => 'Attacker key', 'passkey' => '{}'])->assertForbidden();
        $this->deleteJson(route('passkeys.destroy', ['passkey' => 1]))->assertForbidden();
        $this->assertAuthenticatedAs($user);
        $this->get(route('2fa.verify'))->assertOk();
        $this->post(route('2faVerify'), ['one_time_password' => Google2FA::getCurrentOtp($secret)])
            ->assertRedirect(route('profileedit').'#security')
            ->assertSessionHas('google2fa.auth_passed', true)
            ->assertSessionMissing('2fa:user:id');
        $this->postJson(route('passkeys.register_options'), ['name' => 'Key'])->assertStatus(423);
        $this->get(route('password.confirm'))->assertOk()->assertSee('Confirm Password');
    }

    #[DataProvider('laterChallengeStates')]
    public function test_authenticated_user_can_complete_a_later_otp_challenge(string $state): void
    {
        Event::fake([OtherDeviceLogout::class, UserLoggedIn::class]);
        config(['google2fa.enabled' => true, 'google2fa.lifetime' => 5, 'google2fa.keep_alive' => false]);
        $user = $this->createUser('later-otp@example.test');
        $secret = Google2FA::generateSecretKey();
        PasswordSecurity::query()->create([
            'user_id' => $user->id, 'google2fa_enable' => true, 'google2fa_secret' => $secret,
        ]);
        $user->forceFill(['session_token' => 'existing-session', 'remember_token' => 'existing-remember'])->save();
        $this->actingAs($user)->withSession(['session_token_web' => 'existing-session']);

        if ($state === 'expired') {
            $this->withSession([
                'google2fa.auth_passed' => true,
                'google2fa.auth_time' => now()->subMinutes(6)->toIso8601String(),
            ]);
        } elseif ($state === 'revoked') {
            $device = TrustedDevice::issueForUser($user);
            $this->withCookie('2fa_trusted_device', json_encode([
                'user_id' => $user->id, 'token' => $device['plain'],
                'expires_at' => $device['device']->expires_at->getTimestamp(),
            ], JSON_THROW_ON_ERROR));
            $this->get('/__otp_probe')->assertOk()->assertSeeText('otp passed');
            $device['device']->delete();
        }

        $this->get('/__otp_probe')->assertOk()->assertSee('One Time Password');
        $this->get(route('2fa.verify'))->assertOk();
        $this->post(route('2fa.post'), ['one_time_password' => '123456789'])
            ->assertRedirect(route('2fa.verify'))->assertSessionMissing('2fa:user:id');
        $this->get('/__otp_probe')->assertSee('One Time Password');
        $sessionId = session()->getId();
        $this->post(route('2fa.post'), ['one_time_password' => Google2FA::getCurrentOtp($secret)])
            ->assertRedirect(url('/__otp_probe'))
            ->assertCookieMissing($this->recallerCookieName())
            ->assertSessionHas('google2fa.auth_passed', true)
            ->assertSessionHas('google2fa.auth_time')
            ->assertSessionHas('session_token_web', 'existing-session');
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertSame('existing-session', $user->fresh()->session_token);
        $this->assertSame('existing-remember', $user->fresh()->remember_token);
        Event::assertNotDispatched(OtherDeviceLogout::class);
        Event::assertNotDispatched(UserLoggedIn::class);
        $this->get('/__otp_probe')->assertOk()->assertSeeText('otp passed');
    }

    /** @return array<string, array{string}> */
    public static function laterChallengeStates(): array
    {
        return ['missing' => ['missing'], 'expired' => ['expired'], 'revoked' => ['revoked']];
    }

    public function test_reverification_uses_the_authenticated_account_over_a_stale_pending_login(): void
    {
        $user = $this->createUser('current-otp@example.test');
        $other = $this->createUser('stale-pending@example.test');
        $secret = Google2FA::generateSecretKey();
        $otherSecret = Google2FA::generateSecretKey();
        foreach ([[$user, $secret], [$other, $otherSecret]] as [$account, $key]) {
            PasswordSecurity::query()->create([
                'user_id' => $account->id, 'google2fa_enable' => true, 'google2fa_secret' => $key,
            ]);
        }
        $this->actingAs($user)->withSession(['2fa:user:id' => $other->id]);
        $this->get(route('2fa.verify'))->assertViewHas('user', fn (User $shown): bool => $shown->is($user));
        $this->post(route('2fa.post'), ['one_time_password' => Google2FA::getCurrentOtp($secret)])
            ->assertRedirect('/')->assertSessionMissing('2fa:user:id');
        $this->assertAuthenticatedAs($user);
    }

    public function test_guest_without_pending_password_login_cannot_verify_otp(): void
    {
        $this->get(route('2fa.verify'))->assertRedirect(route('login'));
        $this->post(route('2fa.post'), ['one_time_password' => '123456'])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_two_factor_login_with_valid_trusted_device_logs_in_without_otp(): void
    {
        Event::fake([UserLoggedIn::class]);
        $user = $this->createUser('trusted-2fa@example.test');
        PasswordSecurity::query()->create([
            'user_id' => $user->id,
            'google2fa_enable' => 1,
            'google2fa_secret' => Google2FA::generateSecretKey(),
        ]);
        $trustedDevice = TrustedDevice::issueForUser($user, '127.0.0.1', 'Feature Test');
        $cookieValue = json_encode([
            'user_id' => $user->id,
            'token' => $trustedDevice['plain'],
            'expires_at' => $trustedDevice['device']->expires_at->getTimestamp(),
        ], JSON_THROW_ON_ERROR);

        $response = $this
            ->withCookie('2fa_trusted_device', $cookieValue)
            ->post(route('login'), [
                'username' => $user->email,
                'password' => 'password',
            ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertTrue((bool) session(config('google2fa.session_var').'.auth_passed'));
        $this->assertNull(session('2fa:user:id'));

        $cookie = $response->getCookie(config('session.cookie'), decrypt: false);
        $this->assertNotNull($cookie);
        Auth::forgetGuards();

        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
            ->get('/__remember_me_probe')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_two_factor_login_with_forged_trusted_device_cookie_still_requires_otp(): void
    {
        Event::fake([UserLoggedIn::class]);
        $user = $this->createUser('forged-2fa@example.test');
        PasswordSecurity::query()->create([
            'user_id' => $user->id,
            'google2fa_enable' => 1,
            'google2fa_secret' => Google2FA::generateSecretKey(),
        ]);
        $cookieValue = json_encode([
            'user_id' => $user->id,
            'token' => 'forged-client-token',
            'expires_at' => time() + 3600,
        ], JSON_THROW_ON_ERROR);

        $response = $this
            ->withCookie('2fa_trusted_device', $cookieValue)
            ->post(route('login'), [
                'username' => $user->email,
                'password' => 'password',
            ]);

        $response->assertRedirect(route('2fa.verify'));
        $this->assertGuest();
        $this->assertSame($user->id, session('2fa:user:id'));
        $this->assertFalse((bool) session(config('google2fa.session_var').'.auth_passed'));
    }

    private function recallerCookieName(): string
    {
        return Auth::guard()->getRecallerName();
    }

    protected function createSchema(): void
    {
        foreach ([
            'user_activities',
            'trusted_devices',
            'role_has_permissions',
            'model_has_permissions',
            'model_has_roles',
            'password_securities',
            'users',
            'permissions',
            'roles',
            'settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('settings', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->text('value')->nullable();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name');
            $table->integer('rate_limit')->default(60);
            $table->boolean('isdefault')->default(false);
            $table->unsignedInteger('defaultinvites')->default(0);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('roles_id')->default(1);
            $table->integer('rate_limit')->default(60);
            $table->string('api_token')->nullable();
            $table->boolean('verified')->default(true);
            $table->boolean('can_post')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('lastlogin')->nullable();
            $table->string('host')->nullable();
            $table->rememberToken();
            $table->string('session_token', 60)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_securities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->boolean('google2fa_enable')->default(false);
            $table->string('google2fa_secret')->nullable();
            $table->timestamps();
        });

        Schema::create('trusted_devices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('last_used_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedInteger('role_id');
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedInteger('permission_id');
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedInteger('permission_id');
            $table->unsignedInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('user_activities', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('username');
            $table->string('activity_type', 50);
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    protected function seedSettings(): void
    {
        DB::table('settings')->insert([
            ['name' => 'title', 'value' => 'NNTmux Test'],
            ['name' => 'home_link', 'value' => '/'],
            ['name' => 'categorizeforeign', 'value' => '0'],
            ['name' => 'catwebdl', 'value' => '0'],
            ['name' => 'innerfileblacklist', 'value' => ''],
        ]);
    }

    protected function createUser(string $email): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'User', 'guard_name' => 'web'],
            ['rate_limit' => 60, 'isdefault' => true, 'defaultinvites' => 1]
        );

        $user = User::query()->create([
            'username' => 'user_'.md5($email),
            'email' => $email,
            'password' => bcrypt('password'),
            'roles_id' => $role->id,
            'rate_limit' => 60,
            'api_token' => md5($email),
            'verified' => true,
            'email_verified_at' => now(),
            'lastlogin' => now(),
        ]);

        $user->assignRole($role);

        return $user->fresh();
    }

    private function setEnvironmentValue(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
