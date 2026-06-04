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

class AdminRegexesControllerTest extends TestCase
{
    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-admin-regexes-test.sqlite';

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

    public function test_admin_regex_edit_pages_accept_numeric_string_query_ids(): void
    {
        $admin = $this->createUserWithRole('Admin');

        $releaseNamingRegexId = DB::table('release_naming_regexes')->insertGetId([
            'group_regex' => 'alt\\.binaries\\.tv',
            'regex' => '/(?P<name>Example\.Show)/i',
            'description' => 'Release naming regex description',
            'ordinal' => 10,
            'status' => 1,
        ]);

        $categoryRegexId = DB::table('category_regexes')->insertGetId([
            'group_regex' => 'alt\\.binaries\\.movies',
            'regex' => '/movie/i',
            'description' => 'Category regex description',
            'ordinal' => 20,
            'categories_id' => 1,
            'status' => 1,
        ]);

        $collectionRegexId = DB::table('collection_regexes')->insertGetId([
            'group_regex' => 'alt\\.binaries\\.multimedia',
            'regex' => '/collection/i',
            'description' => 'Collection regex description',
            'ordinal' => 30,
            'status' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.release_naming_regexes-edit', ['id' => (string) $releaseNamingRegexId]))
            ->assertOk()
            ->assertSee('Release Naming Regex Edit')
            ->assertSee('Release naming regex description')
            ->assertSee('value="'.$releaseNamingRegexId.'"', false);

        $this->actingAs($admin)
            ->get(route('admin.category_regexes-edit', ['id' => (string) $categoryRegexId]))
            ->assertOk()
            ->assertSee('Category Regex Edit')
            ->assertSee('Category regex description')
            ->assertSee('value="'.$categoryRegexId.'"', false);

        $this->actingAs($admin)
            ->get(route('admin.collection_regexes-edit', ['id' => (string) $collectionRegexId]))
            ->assertOk()
            ->assertSee('Collections Regex Edit')
            ->assertSee('Collection regex description')
            ->assertSee('value="'.$collectionRegexId.'"', false);
    }

    public function test_admin_regex_edit_pages_return_404_for_invalid_query_ids(): void
    {
        $admin = $this->createUserWithRole('Admin');

        $this->actingAs($admin)
            ->get(route('admin.release_naming_regexes-edit', ['id' => 'invalid']))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('admin.category_regexes-edit', ['id' => 'invalid']))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('admin.collection_regexes-edit', ['id' => 'invalid']))
            ->assertNotFound();
    }

    public function test_admin_regex_list_pages_decode_entity_encoded_regexes_without_double_escaping(): void
    {
        $admin = $this->createUserWithRole('Admin');
        $rawRegex = '/^(?P<name>.+?) - "(?P<title>.+?)"$/i';
        $entityEncodedRegex = '/^(?P&lt;name&gt;.+?) - &quot;(?P&lt;title&gt;.+?)&quot;$/i';
        $scriptTag = '<'.'script>alert("x")</'.'script>';
        $htmlLookingRegex = '/^(?P&lt;name&gt;.+?)'.$scriptTag.'$/i';

        foreach (['release_naming_regexes', 'collection_regexes', 'category_regexes'] as $table) {
            $this->insertRegexFixture($table, $rawRegex, 'Raw regex fixture');
            $this->insertRegexFixture($table, $entityEncodedRegex, 'Entity encoded regex fixture');
            $this->insertRegexFixture($table, $htmlLookingRegex, 'HTML-looking regex fixture');
        }

        foreach ([
            route('admin.release_naming_regexes-list'),
            route('admin.collection_regexes-list'),
            route('admin.category_regexes-list'),
        ] as $url) {
            $response = $this->actingAs($admin)->get($url);

            $response->assertOk()
                ->assertSee(e($rawRegex), false)
                ->assertSee(e(html_entity_decode($entityEncodedRegex, ENT_QUOTES | ENT_HTML5, 'UTF-8')), false)
                ->assertSee(e(html_entity_decode($htmlLookingRegex, ENT_QUOTES | ENT_HTML5, 'UTF-8')), false)
                ->assertDontSee('&amp;quot;', false)
                ->assertDontSee('&amp;lt;', false)
                ->assertDontSee('&amp;gt;', false)
                ->assertDontSee($scriptTag, false);
        }
    }

    public function test_admin_regex_edit_pages_decode_entity_encoded_regexes_without_double_escaping(): void
    {
        $admin = $this->createUserWithRole('Admin');
        $entityEncodedRegex = '/^(?P&lt;name&gt;.+?) - &quot;(?P&lt;title&gt;.+?)&quot;$/i';
        $expectedRenderedRegex = e(html_entity_decode($entityEncodedRegex, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        $releaseNamingRegexId = $this->insertRegexFixture('release_naming_regexes', $entityEncodedRegex, 'Release naming encoded regex');
        $collectionRegexId = $this->insertRegexFixture('collection_regexes', $entityEncodedRegex, 'Collection encoded regex');
        $categoryRegexId = $this->insertRegexFixture('category_regexes', $entityEncodedRegex, 'Category encoded regex');

        foreach ([
            route('admin.release_naming_regexes-edit', ['id' => (string) $releaseNamingRegexId]),
            route('admin.collection_regexes-edit', ['id' => (string) $collectionRegexId]),
            route('admin.category_regexes-edit', ['id' => (string) $categoryRegexId]),
        ] as $url) {
            $this->actingAs($admin)
                ->get($url)
                ->assertOk()
                ->assertSee($expectedRenderedRegex, false)
                ->assertDontSee('&amp;quot;', false)
                ->assertDontSee('&amp;lt;', false)
                ->assertDontSee('&amp;gt;', false);
        }
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

        Schema::create('release_naming_regexes', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('group_regex');
            $table->text('regex');
            $table->text('description')->nullable();
            $table->integer('ordinal')->default(0);
            $table->integer('status')->default(1);
        });

        Schema::create('category_regexes', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('group_regex');
            $table->text('regex');
            $table->text('description')->nullable();
            $table->integer('ordinal')->default(0);
            $table->unsignedInteger('categories_id');
            $table->integer('status')->default(1);
        });

        Schema::create('collection_regexes', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('group_regex');
            $table->text('regex');
            $table->text('description')->nullable();
            $table->integer('ordinal')->default(0);
            $table->integer('status')->default(1);
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

    private function insertRegexFixture(string $table, string $regex, string $description): int
    {
        $data = [
            'group_regex' => 'alt\\.binaries\\.example',
            'regex' => $regex,
            'description' => $description,
            'ordinal' => 10,
            'status' => 1,
        ];

        if ($table === 'category_regexes') {
            $data['categories_id'] = 1;
        }

        return (int) DB::table($table)->insertGetId($data);
    }

    private function resetGlobalComposerState(): void
    {
        $reflection = new ReflectionClass(GlobalDataComposer::class);
        $property = $reflection->getProperty('resolvedData');
        $property->setValue(null, null);
    }
}
