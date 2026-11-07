<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\ProfileController;
use App\Http\Middleware\Google2FAMiddleware;
use App\Models\User;
use App\Notifications\VerifyEmailBranded;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PDO;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ProfileControllerTest extends TestCase
{
    private string $databasePath = '';

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-profile-controller-test.sqlite';

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
            ('title', 'NNTmux Test'),
            ('home_link', '/')");

        $this->setEnvironmentValue('APP_ENV', 'testing');
        $this->setEnvironmentValue('DB_CONNECTION', 'sqlite');
        $this->setEnvironmentValue('DB_DATABASE', $this->databasePath);

        $app = require __DIR__.'/../../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'mail.from.address' => 'noreply@example.test',
            'mail.from.name' => 'NNTmux Tests',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);

        DB::purge();
        DB::reconnect();
        Cache::flush();

        $this->createSchema();
        $this->seedSettings();
        $this->seedPermissions();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withoutMiddleware(Google2FAMiddleware::class);
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

    public function test_non_admin_email_change_resets_verification_and_logs_user_out(): void
    {
        Notification::fake();

        $user = $this->createUserWithRole('User');
        /** @var Authenticatable $authenticatedUser */
        $authenticatedUser = $user;

        $response = $this->actingAs($authenticatedUser)->post(route('profileedit'), [
            'action' => 'submit',
            'email' => 'changed@example.com',
            'current_password' => 'password',
            'theme_preference' => 'light',
            'color_scheme' => 'blue',
            'timezone' => 'UTC',
            'movieview' => '1',
            'musicview' => '1',
            'gameview' => '1',
            'consoleview' => '1',
            'bookview' => '1',
            'excluded_categories' => [],
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('info', 'You will be able to login after you verify your new email address');
        $this->assertGuest();

        $updatedUser = $user->fresh();

        $this->assertNotNull($updatedUser);
        $this->assertSame('changed@example.com', $updatedUser->email);
        $this->assertFalse($updatedUser->verified);
        $this->assertNull($updatedUser->email_verified_at);

        Notification::assertSentTo($updatedUser, VerifyEmailBranded::class);
    }

    public function test_email_change_without_current_password_is_rejected(): void
    {
        Notification::fake();
        $user = $this->createUserWithRole('User');
        $originalEmail = $user->email;
        /** @var Authenticatable $authenticatedUser */
        $authenticatedUser = $user;

        $this->actingAs($authenticatedUser)->post(route('profileedit'), [
            'action' => 'submit',
            'email' => 'attacker@example.com',
        ]);

        $this->assertAuthenticated();
        $this->assertSame($originalEmail, $user->fresh()?->email);
        Notification::assertNothingSent();
    }

    public function test_moderator_viewing_admin_profile_by_name_gets_public_view_without_admin_privileges(): void
    {
        $moderator = $this->createUserWithRole('Moderator');
        $admin = $this->createUserWithRole('Admin');

        $data = $this->profileViewData($moderator, ['name' => $admin->username]);

        $this->assertSame($admin->id, $data['user']->id);
        $this->assertTrue($data['publicview']);
        $this->assertFalse($data['isadmin']);
    }

    public function test_user_viewing_another_profile_by_name_gets_public_view_when_profiles_are_public(): void
    {
        config(['nntmux_settings.private_profiles' => false]);
        $viewer = $this->createUserWithRole('User');
        $other = $this->createUserWithRole('User');

        $data = $this->profileViewData($viewer, ['name' => $other->username]);

        $this->assertSame($other->id, $data['user']->id);
        $this->assertTrue($data['publicview']);
        $this->assertFalse($data['isadmin']);
    }

    public function test_own_profile_is_not_a_public_view(): void
    {
        $user = $this->createUserWithRole('User');

        $data = $this->profileViewData($user, ['name' => $user->username]);

        $this->assertFalse($data['publicview']);
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    private function profileViewData(User $viewer, array $query): array
    {
        $this->createProfileActivitySchema();
        /** @var Authenticatable $authenticatedViewer */
        $authenticatedViewer = $viewer;
        $this->actingAs($authenticatedViewer);

        $controller = app(ProfileController::class);
        $controller->userdata = $viewer;

        $view = $controller->show(Request::create('/profile', 'GET', $query));

        $this->assertInstanceOf(View::class, $view);

        return $view->getData();
    }

    private function createProfileActivitySchema(): void
    {
        Schema::create('releases', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('guid')->nullable();
            $table->string('searchname')->nullable();
        });
        Schema::create('user_downloads', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->unsignedInteger('releases_id')->nullable();
            $table->timestamp('timestamp')->nullable();
        });
        Schema::create('user_requests', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->string('request')->nullable();
            $table->timestamp('timestamp')->nullable();
        });
        Schema::create('release_comments', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('releases_id');
            $table->unsignedInteger('users_id');
            $table->text('text')->nullable();
            $table->timestamps();
        });
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

    private function createSchema(): void
    {
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table): void {
                $table->string('name')->primary();
                $table->text('value')->nullable();
            });
        }

        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name');
            $table->unsignedInteger('apirequests')->default(10);
            $table->integer('rate_limit')->default(60);
            $table->unsignedInteger('downloadrequests')->default(5);
            $table->unsignedInteger('defaultinvites')->default(0);
            $table->boolean('isdefault')->default(false);
            $table->integer('donation')->default(0);
            $table->integer('addyears')->default(0);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
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

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('roles_id')->default(1);
            $table->string('host')->nullable();
            $table->integer('grabs')->default(0);
            $table->string('api_token')->nullable();
            $table->integer('invites')->default(0);
            $table->unsignedInteger('invitedby')->nullable();
            $table->boolean('movieview')->default(true);
            $table->boolean('xxxview')->default(false);
            $table->boolean('musicview')->default(true);
            $table->boolean('consoleview')->default(true);
            $table->boolean('bookview')->default(true);
            $table->boolean('gameview')->default(true);
            $table->string('notes')->nullable();
            $table->string('style')->nullable();
            $table->string('theme_preference', 10)->default('light');
            $table->string('color_scheme', 20)->default('blue');
            $table->string('timezone')->nullable();
            $table->string('allowed_ip_1')->nullable();
            $table->string('allowed_ip_2')->nullable();
            $table->text('cloudflare_rule_ids')->nullable();
            $table->integer('rate_limit')->default(60);
            $table->boolean('verified')->default(true);
            $table->string('verification_token')->nullable();
            $table->boolean('can_post')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_securities', function (Blueprint $table): void {
            $table->increments('id');
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

        Schema::create('root_categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->unsignedInteger('root_categories_id')->nullable();
            $table->text('description')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('user_excluded_categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->unsignedInteger('categories_id');
            $table->timestamps();
        });
    }

    private function seedSettings(): void
    {
        DB::table('settings')->upsert([
            ['name' => 'title', 'value' => 'NNTmux Test'],
            ['name' => 'home_link', 'value' => '/'],
            ['name' => 'categorizeforeign', 'value' => '0'],
            ['name' => 'catwebdl', 'value' => '0'],
        ], ['name'], ['value']);
    }

    private function seedPermissions(): void
    {
        foreach ([
            'view console',
            'view movies',
            'view audio',
            'view pc',
            'view tv',
            'view adult',
            'view books',
            'view other',
            'preview',
            'hideads',
            'edit release',
        ] as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(
            [
                'name' => $roleName,
                'guard_name' => 'web',
            ],
            [
                'apirequests' => 10,
                'downloadrequests' => 5,
                'rate_limit' => 60,
                'isdefault' => $roleName === 'User',
                'defaultinvites' => 1,
                'addyears' => 0,
            ]
        );

        /** @var User $user */
        $user = User::withoutEvents(fn () => User::query()->create([
            'username' => strtolower($roleName).'_'.Str::random(8),
            'email' => Str::random(12).'@example.com',
            'password' => bcrypt('password'),
            'roles_id' => $role->id,
            'rate_limit' => 60,
            'api_token' => Str::random(32),
            'verified' => true,
            'email_verified_at' => now(),
        ]));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->assignRole($role);

        return $user->fresh();
    }
}
