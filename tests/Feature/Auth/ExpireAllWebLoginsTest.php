<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Middleware\Google2FAMiddleware;
use App\Models\User;
use App\Services\Auth\WebLoginSessionPolicy;
use Database\Seeders\SettingsTableSeeder;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ExpireAllWebLoginsTest extends TestCase
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
        $this->seedSettings();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withoutMiddleware(Google2FAMiddleware::class);

        Route::middleware(['web', 'auth'])->get('__expired_login_probe', fn () => response('ok'));
    }

    public function test_admin_can_expire_every_login_while_sparing_only_the_current_session(): void
    {
        $admin = $this->createUser('Admin', 'admin@example.test', 'admin-old-token', 'admin-old-remember');
        $target = $this->createUser('User', 'target@example.test', 'target-old-token', 'target-old-remember');
        $other = $this->createUser('User', 'other@example.test', 'other-old-token', 'other-old-remember');
        $this->createTrustedDevice($admin);
        $this->createTrustedDevice($target);
        $this->createTrustedDevice($other);

        $targetOldRecaller = $this->recallerValue($target);
        $targetApiToken = $target->api_token;

        $response = $this->actingAs($admin)
            ->withSession([
                WebLoginSessionPolicy::SESSION_TOKEN_KEY => 'admin-old-token',
                WebLoginSessionPolicy::REMEMBERED_LOGIN_KEY => true,
            ])
            ->post(route('admin.login-sessions.expire-all'));

        $response->assertRedirect(route('admin.site-edit'));
        $response->assertSessionHas('success', 'All web logins have been expired except your current session.');
        $response->assertCookieExpired('2fa_trusted_device');
        $response->assertCookieExpired($this->recallerCookieName());

        $admin->refresh();
        $target->refresh();
        $other->refresh();

        $this->assertStringStartsWith('a.', (string) $admin->session_token);
        $this->assertStringStartsWith('a.', (string) $target->session_token);
        $this->assertStringStartsWith('a.', (string) $other->session_token);
        $this->assertNotSame('admin-old-remember', $admin->remember_token);
        $this->assertNotSame('target-old-remember', $target->remember_token);
        $this->assertNotSame('other-old-remember', $other->remember_token);
        $this->assertSame($admin->session_token, session(WebLoginSessionPolicy::SESSION_TOKEN_KEY));
        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseCount('trusted_devices', 0);
        $this->assertSame($targetApiToken, $target->api_token);

        $this->flushSession();
        Auth::forgetGuards();

        $this->withUnencryptedCookie($this->recallerCookieName(), $targetOldRecaller)
            ->get('/__expired_login_probe')
            ->assertRedirect(route('login'));

        Auth::forgetGuards();
        /** @var Authenticatable $targetAuth */
        $targetAuth = $target;

        $this->actingAs($targetAuth)
            ->withSession([WebLoginSessionPolicy::SESSION_TOKEN_KEY => 'target-old-token'])
            ->get('/__expired_login_probe')
            ->assertRedirect(route('login'))
            ->assertSessionHas('info', 'You were signed out because an administrator has signed everyone out.');
    }

    public function test_admin_can_expire_one_users_logins_without_touching_other_accounts(): void
    {
        $admin = $this->createUser('Admin', 'admin@example.test', 'admin-token', 'admin-remember');
        $target = $this->createUser('User', 'target@example.test', 'target-token', 'target-remember');
        $other = $this->createUser('User', 'other@example.test', 'other-token', 'other-remember');
        $targetTrustedDevice = $this->createTrustedDevice($target);
        $otherTrustedDevice = $this->createTrustedDevice($other);

        $this->actingAs($admin)
            ->withSession([WebLoginSessionPolicy::SESSION_TOKEN_KEY => 'admin-token'])
            ->post(route('admin.login-sessions.expire-user', $target))
            ->assertRedirect(route('admin.user-edit', ['id' => $target->id]))
            ->assertSessionHas('success', "All web logins for {$target->username} have been expired.");

        $admin->refresh();
        $target->refresh();
        $other->refresh();

        $this->assertSame('admin-token', $admin->session_token);
        $this->assertSame('admin-remember', $admin->remember_token);
        $this->assertStringStartsWith('a.', (string) $target->session_token);
        $this->assertNotSame('target-remember', $target->remember_token);
        $this->assertSame('other-token', $other->session_token);
        $this->assertSame('other-remember', $other->remember_token);
        $this->assertDatabaseMissing('trusted_devices', ['id' => $targetTrustedDevice]);
        $this->assertDatabaseHas('trusted_devices', ['id' => $otherTrustedDevice]);
        $this->assertSame('admin-token', session(WebLoginSessionPolicy::SESSION_TOKEN_KEY));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_can_enable_single_active_session_from_site_settings(): void
    {
        $admin = $this->createUser('Admin', 'admin@example.test', 'admin-token', 'admin-remember');
        $this->withoutMiddleware();

        $this->actingAs($admin)
            ->withSession([WebLoginSessionPolicy::SESSION_TOKEN_KEY => 'admin-token'])
            ->post(route('admin.site-edit'), [
                'action' => 'submit',
                'single_active_session' => '1',
            ])
            ->assertRedirect('admin/site-edit');

        $this->assertDatabaseHas('settings', [
            'name' => 'single_active_session',
            'value' => '1',
        ]);
    }

    public function test_single_active_session_defaults_to_off_in_the_application_seeder(): void
    {
        (new SettingsTableSeeder)->run();

        $this->assertDatabaseHas('settings', [
            'name' => 'single_active_session',
            'value' => '0',
        ]);
    }

    public function test_upgrade_migration_adds_the_single_active_session_setting_without_overwriting_it(): void
    {
        DB::table('settings')->where('name', 'single_active_session')->delete();

        $migration = require database_path('migrations/2026_08_12_135248_add_single_active_session_setting.php');
        $migration->up();

        $this->assertDatabaseHas('settings', [
            'name' => 'single_active_session',
            'value' => '0',
        ]);

        DB::table('settings')->where('name', 'single_active_session')->update(['value' => '1']);
        $migration->up();

        $this->assertDatabaseHas('settings', [
            'name' => 'single_active_session',
            'value' => '1',
        ]);

        $migration->down();
        $this->assertDatabaseMissing('settings', ['name' => 'single_active_session']);
    }

    private function createSchema(): void
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

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('roles_id');
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

        Schema::create('trusted_devices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
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
    }

    private function seedSettings(): void
    {
        DB::table('settings')->insert([
            ['name' => 'title', 'value' => 'NNTmux Test'],
            ['name' => 'home_link', 'value' => '/'],
            ['name' => 'categorizeforeign', 'value' => '0'],
            ['name' => 'catwebdl', 'value' => '0'],
            ['name' => 'single_active_session', 'value' => '0'],
        ]);
    }

    private function createUser(
        string $roleName,
        string $email,
        string $sessionToken,
        string $rememberToken,
    ): User {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName, 'guard_name' => 'web'],
            ['rate_limit' => 60, 'isdefault' => $roleName === 'User', 'defaultinvites' => 1],
        );

        $user = User::query()->create([
            'username' => str_replace(['@', '.'], '_', $email),
            'email' => $email,
            'password' => bcrypt('password'),
            'roles_id' => $role->id,
            'rate_limit' => 60,
            'api_token' => md5($email),
            'verified' => true,
            'email_verified_at' => now(),
            'lastlogin' => now(),
            'remember_token' => $rememberToken,
            'session_token' => $sessionToken,
        ]);

        $user->assignRole($role);

        return $user->fresh();
    }

    private function createTrustedDevice(User $user): int
    {
        return (int) DB::table('trusted_devices')->insertGetId([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', Str::random()),
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function recallerValue(User $user): string
    {
        $guard = Auth::guard();
        $this->assertInstanceOf(SessionGuard::class, $guard);

        return $user->getAuthIdentifier().'|'.$user->getRememberToken().'|'.$guard->hashPasswordForCookie($user->getAuthPassword());
    }

    private function recallerCookieName(): string
    {
        return Auth::guard()->getRecallerName();
    }
}
