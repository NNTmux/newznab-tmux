<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Events\UserLoggedIn;
use App\Http\Middleware\Google2FAMiddleware;
use App\Models\PasswordSecurity;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Models\Passkey;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PasskeyAuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'session.driver' => 'array',
        ]);

        DB::purge();
        DB::reconnect();

        $this->createSchema();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withoutMiddleware(Google2FAMiddleware::class);
    }

    public function test_passkey_authentication_logs_user_in_and_sets_2fa_session_flag(): void
    {
        $this->withMiddleware(Google2FAMiddleware::class);
        config(['google2fa.enabled' => true, 'google2fa.lifetime' => 5]);
        Route::middleware(['web', 'auth', '2fa'])->get('__passkey_otp_probe', fn () => response('passkey passed'));
        Event::fake([UserLoggedIn::class]);
        $user = $this->createUser('passkey-user@example.test');
        PasswordSecurity::query()->create([
            'user_id' => $user->id,
            'google2fa_enable' => true,
            'google2fa_secret' => 'JBSWY3DPEHPK3PXP',
        ]);

        $passkey = new Passkey;
        $passkey->setRawAttributes([
            'id' => 1,
            'authenticatable_id' => $user->id,
            'name' => 'Laptop',
            'credential_id' => 'credential-1',
            'data' => '{}',
        ], true);
        $passkey->setRelation('authenticatable', $user);

        FakeFindPasskeyAction::$passkey = $passkey;
        config()->set('passkeys.actions.find_passkey', FakeFindPasskeyAction::class);

        $response = $this
            ->withSession(['passkey-authentication-options' => '{}', 'passkeys.redirect' => '/__passkey_otp_probe'])
            ->post(route('passkeys.login'), [
                'start_authentication_response' => json_encode(['id' => 'credential-1'], JSON_THROW_ON_ERROR),
                'remember' => false,
            ]);

        $response->assertRedirect('/__passkey_otp_probe');
        $response->assertCookieMissing(Auth::guard()->getRecallerName());
        $response->assertCookieMissing('2fa_trusted_device');
        $this->assertAuthenticatedAs($user);
        $this->assertTrue((bool) session(config('google2fa.session_var').'.auth_passed'));
        $response->assertSessionHas('google2fa.auth_time');
        $cookie = $response->getCookie(config('session.cookie'), decrypt: false);
        $this->assertNotNull($cookie);
        Auth::forgetGuards();
        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
            ->get('/__passkey_otp_probe')->assertOk()->assertSeeText('passkey passed');
        Event::assertDispatched(UserLoggedIn::class);
    }

    public function test_passkey_authentication_with_remember_sets_remember_token(): void
    {
        Event::fake([UserLoggedIn::class]);
        $user = $this->createUser('passkey-remember@example.test');

        $passkey = new Passkey;
        $passkey->setRawAttributes([
            'id' => 3,
            'authenticatable_id' => $user->id,
            'name' => 'Desktop',
            'credential_id' => 'credential-3',
            'data' => '{}',
        ], true);
        $passkey->setRelation('authenticatable', $user);

        FakeFindPasskeyAction::$passkey = $passkey;
        config()->set('passkeys.actions.find_passkey', FakeFindPasskeyAction::class);

        $response = $this
            ->withSession(['passkey-authentication-options' => '{}'])
            ->post(route('passkeys.login'), [
                'start_authentication_response' => json_encode(['id' => 'credential-3'], JSON_THROW_ON_ERROR),
                'remember' => true,
            ]);

        $response->assertRedirect('/');
        $response->assertCookie(Auth::guard()->getRecallerName());
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()?->remember_token);
    }

    public function test_passkey_authentication_records_the_client_ip_when_ip_storage_is_enabled(): void
    {
        Event::fake([UserLoggedIn::class]);
        config()->set('nntmux_settings.store_user_ip', true);
        $user = $this->createUser('passkey-ip-address@example.test');

        $passkey = new Passkey;
        $passkey->setRawAttributes([
            'id' => 4,
            'authenticatable_id' => $user->id,
            'name' => 'Security key',
            'credential_id' => 'credential-4',
            'data' => '{}',
        ], true);
        $passkey->setRelation('authenticatable', $user);

        FakeFindPasskeyAction::$passkey = $passkey;
        config()->set('passkeys.actions.find_passkey', FakeFindPasskeyAction::class);

        $this
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.43'])
            ->withSession(['passkey-authentication-options' => '{}'])
            ->post(route('passkeys.login'), [
                'start_authentication_response' => json_encode(['id' => 'credential-4'], JSON_THROW_ON_ERROR),
                'remember' => false,
            ])
            ->assertRedirect('/');

        Event::assertDispatched(
            UserLoggedIn::class,
            fn (UserLoggedIn $event): bool => $event->user->is($user) && $event->ip === '203.0.113.43'
        );
    }

    public function test_unverified_users_cannot_authenticate_with_passkeys(): void
    {
        $user = $this->createUser('unverified@example.test', false);
        $passkey = new Passkey;
        $passkey->setRawAttributes([
            'id' => 2,
            'authenticatable_id' => $user->id,
            'name' => 'Phone',
            'credential_id' => 'credential-2',
            'data' => '{}',
        ], true);
        $passkey->setRelation('authenticatable', $user);

        FakeFindPasskeyAction::$passkey = $passkey;
        config()->set('passkeys.actions.find_passkey', FakeFindPasskeyAction::class);

        $response = $this
            ->from(route('login'))
            ->withSession(['passkey-authentication-options' => '{}'])
            ->post(route('passkeys.login'), [
                'start_authentication_response' => json_encode(['id' => 'credential-2'], JSON_THROW_ON_ERROR),
            ]);

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame('ineligible_account', session('authenticatePasskey::reason'));
    }

    public function test_disabled_users_cannot_authenticate_with_passkeys(): void
    {
        Event::fake([UserLoggedIn::class]);
        $user = $this->createUser('disabled@example.test');
        $user->forceFill(['roles_id' => UserRole::DISABLED->value])->save();

        $passkey = new Passkey;
        $passkey->setRawAttributes([
            'id' => 5,
            'authenticatable_id' => $user->id,
            'name' => 'Suspended key',
            'credential_id' => 'credential-5',
            'data' => '{}',
        ], true);
        $passkey->setRelation('authenticatable', $user->fresh());

        FakeFindPasskeyAction::$passkey = $passkey;
        config()->set('passkeys.actions.find_passkey', FakeFindPasskeyAction::class);

        $response = $this
            ->from(route('login'))
            ->withSession(['passkey-authentication-options' => '{}'])
            ->post(route('passkeys.login'), [
                'start_authentication_response' => json_encode(['id' => 'credential-5'], JSON_THROW_ON_ERROR),
            ]);

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame('ineligible_account', session('authenticatePasskey::reason'));
        Event::assertNotDispatched(UserLoggedIn::class);
        $this->assertNull($user->fresh()?->session_token);
    }

    public function test_passkey_authentication_without_session_options_redirects_with_message(): void
    {
        $response = $this
            ->from(route('login'))
            ->post(route('passkeys.login'), [
                'start_authentication_response' => json_encode(['id' => 'credential-x'], JSON_THROW_ON_ERROR),
            ]);

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame('missing_auth_options', session('authenticatePasskey::reason'));
        $this->assertStringContainsString('expired', (string) session('authenticatePasskey::message'));
    }

    protected function createSchema(): void
    {
        Schema::create('password_securities', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->boolean('google2fa_enable')->default(false);
            $table->string('google2fa_secret')->nullable();
            $table->timestamps();
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
            $table->rememberToken();
            $table->string('session_token', 60)->nullable();
            $table->timestamps();
            $table->softDeletes();
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

        Schema::create('passkeys', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('authenticatable_id');
            $table->text('name');
            $table->text('credential_id');
            $table->json('data');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    protected function createUser(string $email, bool $verified = true): User
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
            'verified' => $verified,
            'email_verified_at' => $verified ? now() : null,
            'lastlogin' => now(),
        ]);

        $user->assignRole($role);

        return $user->fresh();
    }
}

class FakeFindPasskeyAction extends FindPasskeyToAuthenticateAction
{
    public static ?Passkey $passkey = null;

    public function execute(string $publicKeyCredentialJson, string $passkeyOptionsJson): ?Passkey
    {
        return self::$passkey;
    }
}
