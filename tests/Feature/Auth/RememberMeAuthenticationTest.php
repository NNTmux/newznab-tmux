<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Events\UserLoggedIn;
use App\Models\PasswordSecurity;
use App\Models\User;
use App\Services\PasswordBreachService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use PragmaRX\Google2FALaravel\Facade as Google2FA;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RememberMeAuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'session.driver' => 'array',
            'google2fa.session_var' => 'google2fa',
        ]);

        DB::purge();
        DB::reconnect();

        $this->createSchema();
        $this->seedSettings();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->app->instance(PasswordBreachService::class, new class extends PasswordBreachService
        {
            public function isPasswordBreached(string $password): bool
            {
                return false;
            }
        });
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

        $verifyResponse = $this->post(route('2fa.post'), [
            'one_time_password' => Google2FA::getCurrentOtp($secret),
        ]);

        $verifyResponse->assertRedirect('/');
        $verifyResponse->assertCookie($this->recallerCookieName());
        $this->assertAuthenticatedAs($user);
        $this->assertTrue((bool) session(config('google2fa.session_var')));
        $this->assertNull(session('2fa:remember'));
    }

    public function test_two_factor_login_without_remember_me_does_not_queue_recaller_cookie_after_otp_success(): void
    {
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

        $verifyResponse = $this->post(route('2fa.post'), [
            'one_time_password' => Google2FA::getCurrentOtp($secret),
        ]);

        $verifyResponse->assertRedirect('/');
        $verifyResponse->assertCookieMissing($this->recallerCookieName());
        $this->assertAuthenticatedAs($user);
    }

    private function recallerCookieName(): string
    {
        return Auth::guard()->getRecallerName();
    }

    protected function createSchema(): void
    {
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
}

