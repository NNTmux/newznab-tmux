<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\Google2FAMiddleware;
use App\Models\User;
use App\View\Composers\GlobalDataComposer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use ReflectionClass;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminListActionsTest extends TestCase
{
    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-admin-list-actions-test.sqlite';

        $this->originalEnvironment = [
            'APP_ENV' => getenv('APP_ENV'),
            'DB_CONNECTION' => getenv('DB_CONNECTION'),
            'DB_DATABASE' => getenv('DB_DATABASE'),
        ];

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        $pdo = new PDO('sqlite:'.$this->databasePath);

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

    public function test_role_delete_form_posts_and_removes_the_role(): void
    {
        $admin = $this->createUserWithRole('Admin');
        $roleId = (int) Role::query()->create(['name' => 'Disposable', 'guard_name' => 'web'])->id;

        $this->actingAs($admin)
            ->get(route('admin.role-list'))
            ->assertOk()
            ->assertSee('action="'.route('admin.role-delete').'"', false)
            ->assertSee('name="id" value="'.$roleId.'"', false);

        $this->actingAs($admin)
            ->from(route('admin.role-list'))
            ->post(route('admin.role-delete'), ['id' => $roleId])
            ->assertRedirect(route('admin.role-list'));

        $this->assertDatabaseMissing('roles', ['id' => $roleId]);
    }

    public function test_role_delete_is_not_reachable_with_a_get_request(): void
    {
        $admin = $this->createUserWithRole('Admin');
        $roleId = (int) Role::query()->create(['name' => 'Disposable', 'guard_name' => 'web'])->id;

        $this->actingAs($admin)
            ->get(url('admin/role-delete?id='.$roleId))
            ->assertStatus(405);

        $this->assertDatabaseHas('roles', ['id' => $roleId]);
    }

    public function test_blacklist_delete_route_removes_the_entry(): void
    {
        $admin = $this->createUserWithRole('Admin');
        $blacklistId = (int) DB::table('binaryblacklist')->insertGetId([
            'groupname' => 'alt.binaries.test',
            'regex' => '/spam/i',
            'description' => 'Spam filter',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.binaryblacklist-list'))
            ->post(route('admin.binaryblacklist-delete', $blacklistId))
            ->assertRedirect(route('admin.binaryblacklist-list'));

        $this->assertDatabaseMissing('binaryblacklist', ['id' => $blacklistId]);
    }

    public function test_category_list_honours_the_sort_links(): void
    {
        $admin = $this->createUserWithRole('Admin');
        DB::table('categories')->insert([
            ['id' => 2, 'title' => 'Zebra', 'root_categories_id' => 1, 'status' => 1],
            ['id' => 3, 'title' => 'Alpha', 'root_categories_id' => 1, 'status' => 1],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.category-list', ['sort' => 'title', 'order' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder(['Zebra', 'General', 'Alpha']);

        $this->resetGlobalComposerState();

        $this->actingAs($admin)
            ->get(route('admin.category-list', ['sort' => 'title', 'order' => 'asc']))
            ->assertOk()
            ->assertSeeInOrder(['Alpha', 'General', 'Zebra']);
    }

    public function test_blacklist_search_filters_by_group_regex_or_description(): void
    {
        $admin = $this->createUserWithRole('Admin');
        DB::table('binaryblacklist')->insert([
            ['groupname' => 'alt.binaries.teevee', 'regex' => '/sample/i', 'description' => 'Sample files'],
            ['groupname' => 'alt.binaries.movies', 'regex' => '/password/i', 'description' => 'Passworded posts'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.binaryblacklist-list', ['search' => 'PASSWORD']))
            ->assertOk()
            ->assertSee('alt.binaries.movies')
            ->assertDontSee('alt.binaries.teevee')
            ->assertSee('value="PASSWORD"', false);
    }

    public function test_book_list_search_filters_by_title_or_author(): void
    {
        $admin = $this->createUserWithRole('Admin');
        DB::table('bookinfo')->insert([
            ['title' => 'Clean Code', 'author' => 'Robert C. Martin', 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Refactoring', 'author' => 'Martin Fowler', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.book-list', ['search' => 'fowler']))
            ->assertOk()
            ->assertSee('Refactoring')
            ->assertDontSee('Clean Code');
    }

    public function test_console_list_search_filters_by_title_or_platform(): void
    {
        $admin = $this->createUserWithRole('Admin');
        DB::table('consoleinfo')->insert([
            ['title' => 'Astro Bot', 'platform' => 'PS5', 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Metroid Prime', 'platform' => 'Switch', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.console-list', ['search' => 'switch']))
            ->assertOk()
            ->assertSee('Metroid Prime')
            ->assertDontSee('Astro Bot');
    }

    public function test_music_list_search_filters_by_title_or_artist(): void
    {
        $admin = $this->createUserWithRole('Admin');
        $this->insertMusic('Blue Train', 'John Coltrane');
        $this->insertMusic('Kind of Blue', 'Miles Davis');
        $this->insertMusic('Head Hunters', 'Herbie Hancock');

        $this->actingAs($admin)
            ->get(route('admin.music-list', ['musicsearch' => 'coltrane']))
            ->assertOk()
            ->assertSee('Blue Train')
            ->assertDontSee('Kind of Blue')
            ->assertDontSee('Head Hunters');
    }

    public function test_music_list_links_to_further_pages_and_keeps_the_search_term(): void
    {
        config(['nntmux.items_per_page' => 1]);
        $admin = $this->createUserWithRole('Admin');
        $this->insertMusic('Blue Train', 'John Coltrane');
        $this->insertMusic('Kind of Blue', 'Miles Davis');

        $this->actingAs($admin)
            ->get(route('admin.music-list', ['musicsearch' => 'blue']))
            ->assertOk()
            ->assertSee('musicsearch=blue&amp;page=2', false);
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

        Schema::create('content', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->string('url', 2000)->nullable();
            $table->text('body')->nullable();
            $table->string('metadescription', 1000)->default('');
            $table->string('metakeywords', 1000)->default('');
            $table->integer('contenttype')->default(2);
            $table->integer('status')->default(1);
            $table->integer('ordinal')->nullable();
            $table->integer('role')->default(0);
            $table->timestamps();
        });

        Schema::create('binaryblacklist', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('groupname')->nullable();
            $table->string('regex');
            $table->unsignedInteger('msgcol')->default(1);
            $table->unsignedInteger('optype')->default(1);
            $table->unsignedInteger('status')->default(1);
            $table->string('description', 1000)->nullable();
            $table->date('last_activity')->nullable();
        });

        Schema::create('usenet_groups', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('bookinfo', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('asin')->nullable();
            $table->string('isbn')->nullable();
            $table->string('url')->nullable();
            $table->string('publisher')->nullable();
            $table->dateTime('publishdate')->nullable();
            $table->boolean('cover')->default(false);
            $table->timestamps();
        });

        Schema::create('consoleinfo', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->string('asin')->nullable();
            $table->string('url')->nullable();
            $table->string('platform')->nullable();
            $table->string('publisher')->nullable();
            $table->unsignedInteger('genres_id')->nullable();
            $table->string('esrb')->nullable();
            $table->dateTime('releasedate')->nullable();
            $table->text('review')->nullable();
            $table->boolean('cover')->default(false);
            $table->timestamps();
        });

        Schema::create('musicinfo', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->string('artist')->nullable();
            $table->string('publisher')->nullable();
            $table->string('year', 4)->default('');
            $table->unsignedInteger('genres_id')->nullable();
            $table->text('tracks')->nullable();
            $table->boolean('cover')->default(false);
            $table->timestamps();
        });
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
            [
                'name' => $roleName,
                'guard_name' => 'web',
            ],
            [
                'rate_limit' => 60,
                'isdefault' => $roleName === 'User',
                'defaultinvites' => 1,
            ]
        );

        /** @var User $user */
        $user = User::withoutEvents(fn () => User::query()->create([
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

    private function insertMusic(string $title, string $artist): void
    {
        DB::table('musicinfo')->insert([
            'title' => $title,
            'artist' => $artist,
            'year' => '1959',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function resetGlobalComposerState(): void
    {
        $reflection = new ReflectionClass(GlobalDataComposer::class);
        $property = $reflection->getProperty('resolvedData');
        $property->setValue(null, null);
    }
}
