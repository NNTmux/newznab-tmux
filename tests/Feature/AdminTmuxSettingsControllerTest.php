<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\Google2FAMiddleware;
use App\Models\User;
use App\View\Composers\GlobalDataComposer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminTmuxSettingsControllerTest extends TestCase
{
    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication(): Application
    {
        $this->databasePath = $this->makeTempPath('nntmux-admin-tmux-settings-test', '.sqlite');

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
        $this->seedCategories();
        $this->resetGlobalComposerState();
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

    public function test_admin_can_save_all_thread_settings(): void
    {
        $payload = [
            'binarythreads' => '2',
            'backfillthreads' => '3',
            'releasethreads' => '4',
            'postthreads' => '5',
            'nfothreads' => '6',
            'postthreadsnon' => '7',
            'postthreadsamazon' => '8',
            'fixnamethreads' => '9',
        ];

        $response = $this->actingAs($this->admin())->post(route('admin.tmux-update'), $payload);

        $response->assertRedirect(route('admin.tmux-edit'));
        $response->assertSessionHas('success', 'Tmux settings updated successfully');

        foreach ($payload as $field => $value) {
            $this->assertSame($value, DB::table('settings')->where('name', $field)->value('value'));
        }
    }

    #[DataProvider('invalidThreadValuesProvider')]
    public function test_invalid_thread_setting_is_rejected_without_persisting_any_thread_settings(string $field, string $value): void
    {
        $before = $this->threadSettings();
        $payload = array_fill_keys(array_keys($before), '2');
        $payload[$field] = $value;

        $response = $this->from(route('admin.tmux-edit'))
            ->actingAs($this->admin())
            ->post(route('admin.tmux-update'), $payload);

        $response->assertRedirect(route('admin.tmux-edit'));
        $response->assertSessionHasErrors($field);
        $this->assertSame($before, $this->threadSettings());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidThreadValuesProvider(): iterable
    {
        yield 'zero' => ['binarythreads', '0'];
        yield 'negative' => ['backfillthreads', '-5'];
        yield 'non-integer' => ['releasethreads', 'abc'];
        yield 'additional threads above 99' => ['postthreads', '100'];
        yield 'NFO threads above 16' => ['nfothreads', '17'];
        yield 'video threads above 99' => ['postthreadsnon', '100'];
        yield 'Amazon threads above 99' => ['postthreadsamazon', '100'];
        yield 'fix-names threads above 16' => ['fixnamethreads', '17'];
    }

    public function test_tmux_page_renders_thread_fields_and_site_page_does_not(): void
    {
        $admin = $this->admin();
        $tmuxResponse = $this->actingAs($admin)->get(route('admin.tmux-edit'));
        $siteResponse = $this->actingAs($admin)->get(route('admin.site-edit'));

        $tmuxResponse->assertOk();
        $siteResponse->assertOk();

        foreach (self::threadMaximums() as $field => $maximum) {
            $tmuxResponse->assertSee('name="'.$field.'"', false);
            $siteResponse->assertDontSee('name="'.$field.'"', false);
            $this->assertMatchesRegularExpression(
                '/<input[^>]*type="number"[^>]*id="'.preg_quote($field, '/').'"[^>]*name="'.preg_quote($field, '/').'"[^>]*min="1"[^>]*max="'.$maximum.'"[^>]*required[^>]*>/',
                (string) $tmuxResponse->getContent(),
            );
        }

        $tmuxResponse->assertSee('name="postthreadsamazon"', false);
        $tmuxResponse->assertSee('books, music, console and PC games', false);
        $siteResponse->assertDontSee('Advanced - Threaded Settings');
    }

    public function test_validation_failure_shows_summary_inline_error_and_submitted_value(): void
    {
        $payload = array_fill_keys(array_keys($this->threadSettings()), '2');
        $payload['binarythreads'] = 'abc';

        $response = $this->from(route('admin.tmux-edit'))
            ->actingAs($this->admin())
            ->followingRedirects()
            ->post(route('admin.tmux-update'), $payload);

        $response->assertOk();
        $response->assertSee('Unable to save tmux settings');
        $response->assertSee('The Update Binaries Threads field must be an integer.');
        $response->assertSee('value="abc"', false);
    }

    /**
     * @return array<string, string>
     */
    private function threadSettings(): array
    {
        return DB::table('settings')
            ->whereIn('name', array_keys(self::threadDefaults()))
            ->orderBy('name')
            ->pluck('value', 'name')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function threadDefaults(): array
    {
        return [
            'binarythreads' => '1',
            'backfillthreads' => '1',
            'releasethreads' => '1',
            'postthreads' => '1',
            'nfothreads' => '1',
            'postthreadsnon' => '1',
            'postthreadsamazon' => '1',
            'fixnamethreads' => '1',
        ];
    }

    /**
     * @return array<string, int>
     */
    private static function threadMaximums(): array
    {
        return [
            'binarythreads' => 99,
            'backfillthreads' => 99,
            'releasethreads' => 99,
            'postthreads' => 99,
            'nfothreads' => 16,
            'postthreadsnon' => 99,
            'postthreadsamazon' => 99,
            'fixnamethreads' => 16,
        ];
    }

    private function admin(): Authenticatable
    {
        /** @var Authenticatable $admin */
        $admin = $this->createUserWithRole('Admin');

        return $admin;
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
            $table->unsignedInteger('grabs')->default(0);
            $table->unsignedInteger('invites')->default(0);
            $table->text('notes')->default('');
            $table->boolean('movieview')->default(true);
            $table->boolean('xxxview')->default(false);
            $table->boolean('musicview')->default(true);
            $table->boolean('consoleview')->default(true);
            $table->boolean('bookview')->default(true);
            $table->boolean('gameview')->default(true);
            $table->boolean('verified')->default(true);
            $table->boolean('can_post')->default(true);
            $table->string('theme_preference', 10)->default('light');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('lastlogin')->nullable();
            $table->rememberToken();
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

    private function seedSettings(): void
    {
        $settings = [
            'title' => 'NNTmux Test',
            'home_link' => '/',
            'categorizeforeign' => '0',
            'catwebdl' => '0',
            ...self::threadDefaults(),
        ];

        DB::table('settings')->upsert(
            array_map(
                static fn (string $value, string $name): array => ['name' => $name, 'value' => $value],
                array_values($settings),
                array_keys($settings),
            ),
            ['name'],
            ['value'],
        );
    }

    private function seedCategories(): void
    {
        DB::table('root_categories')->insert([
            'id' => 1,
            'title' => 'General',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('categories')->insert([
            'id' => 1,
            'title' => 'General',
            'root_categories_id' => 1,
            'description' => 'General category',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName, 'guard_name' => 'web'],
            ['rate_limit' => 60, 'isdefault' => false, 'defaultinvites' => 1],
        );

        /** @var User $user */
        $user = User::withoutEvents(fn () => User::factory()->create([
            'username' => strtolower($roleName).'_'.Str::random(8),
            'email' => Str::random(12).'@example.test',
            'password' => bcrypt('password'),
            'roles_id' => $role->id,
            'rate_limit' => 60,
            'api_token' => Str::random(32),
            'verified' => true,
            'email_verified_at' => now(),
            'lastlogin' => now(),
        ]));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->assignRole($role);

        return $user->fresh();
    }

    private function resetGlobalComposerState(): void
    {
        $reflection = new ReflectionClass(GlobalDataComposer::class);
        $property = $reflection->getProperty('resolvedData');
        $property->setValue(null, null);
    }
}
