<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\Google2FAMiddleware;
use App\Models\Content;
use App\Models\User;
use App\View\Composers\GlobalDataComposer;
use Illuminate\Contracts\Auth\Authenticatable;
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

class AdminContentControllerTest extends TestCase
{
    private string $databasePath;

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-admin-content-test.sqlite';

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

        putenv('APP_ENV=testing');
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE='.$this->databasePath);

        $_ENV['APP_ENV'] = 'testing';
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $this->databasePath;
        $_SERVER['APP_ENV'] = 'testing';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = $this->databasePath;

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
    }

    public function test_admin_can_create_content_without_a_title(): void
    {
        $admin = $this->createUserWithRole('Admin');
        /** @var Authenticatable $authenticatedAdmin */
        $authenticatedAdmin = $admin;

        $response = $this->actingAs($authenticatedAdmin)->post(route('admin.content-add'), [
            'action' => 'submit',
            'title' => '',
            'url' => 'about',
            'body' => '<p>Welcome to the new content page.</p>',
            'metadescription' => 'About page',
            'metakeywords' => 'about,nntmux',
            'contenttype' => Content::TYPE_USEFUL,
            'status' => Content::STATUS_ENABLED,
            'ordinal' => 0,
            'role' => Content::ROLE_EVERYONE,
        ]);

        $content = Content::query()->firstOrFail();

        $response->assertRedirect(route('admin.content-add', ['id' => $content->id]));
        $response->assertSessionHas('success', 'Content created successfully');
        $this->assertSame('', $content->title);
        $this->assertSame('/about/', $content->url);
    }

    public function test_create_form_marks_title_as_optional(): void
    {
        $admin = $this->createUserWithRole('Admin');
        /** @var Authenticatable $authenticatedAdmin */
        $authenticatedAdmin = $admin;

        $response = $this->actingAs($authenticatedAdmin)->get(route('admin.content-add', ['action' => 'add']));

        $response->assertOk();
        $response->assertSee('Leave blank to create content without a page title.');
        $response->assertDontSee('name="title" required', false);
    }

    public function test_updating_existing_content_without_a_title_is_rejected(): void
    {
        $admin = $this->createUserWithRole('Admin');
        /** @var Authenticatable $authenticatedAdmin */
        $authenticatedAdmin = $admin;

        $content = Content::query()->create([
            'title' => 'Existing Content Title',
            'url' => '/existing/',
            'body' => '<p>Existing body</p>',
            'metadescription' => 'Existing description',
            'metakeywords' => 'existing',
            'contenttype' => Content::TYPE_USEFUL,
            'status' => Content::STATUS_ENABLED,
            'ordinal' => 1,
            'role' => Content::ROLE_EVERYONE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->from(route('admin.content-add', ['id' => $content->id]))
            ->actingAs($authenticatedAdmin)
            ->post(route('admin.content-add'), [
                'action' => 'submit',
                'id' => $content->id,
                'title' => '',
                'url' => 'existing',
                'body' => '<p>Updated body</p>',
                'metadescription' => 'Updated description',
                'metakeywords' => 'updated',
                'contenttype' => Content::TYPE_USEFUL,
                'status' => Content::STATUS_ENABLED,
                'ordinal' => 1,
                'role' => Content::ROLE_EVERYONE,
            ]);

        $response->assertRedirect(route('admin.content-add', ['id' => $content->id]));
        $response->assertSessionHasErrors('title');
        $this->assertSame('Existing Content Title', $content->fresh()->title);
    }

    public function test_admin_content_list_uses_untitled_fallback_for_blank_titles(): void
    {
        $admin = $this->createUserWithRole('Admin');
        /** @var Authenticatable $authenticatedAdmin */
        $authenticatedAdmin = $admin;

        Content::query()->create([
            'title' => '',
            'url' => '/untitled/',
            'body' => '<p>Untitled body</p>',
            'metadescription' => 'Untitled description',
            'metakeywords' => 'untitled',
            'contenttype' => Content::TYPE_USEFUL,
            'status' => Content::STATUS_ENABLED,
            'ordinal' => 2,
            'role' => Content::ROLE_EVERYONE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($authenticatedAdmin)->get(route('admin.content-list'));

        $response->assertOk();
        $response->assertSee('Untitled');
        $response->assertSee('x-data="contentToggle"', false);
        $response->assertSee('x-on:click.prevent="deleteContent(', false);
    }

    public function test_admin_can_delete_content_via_ajax(): void
    {
        $admin = $this->createUserWithRole('Admin');
        /** @var Authenticatable $authenticatedAdmin */
        $authenticatedAdmin = $admin;

        $content = Content::query()->create([
            'title' => 'Delete Me',
            'url' => '/delete-me/',
            'body' => '<p>Delete me</p>',
            'metadescription' => 'Delete me description',
            'metakeywords' => 'delete',
            'contenttype' => Content::TYPE_USEFUL,
            'status' => Content::STATUS_ENABLED,
            'ordinal' => 3,
            'role' => Content::ROLE_EVERYONE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($authenticatedAdmin)
            ->postJson(route('admin.content-delete'), ['id' => $content->id]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Content deleted successfully',
        ]);
        $this->assertDatabaseMissing('content', ['id' => $content->id]);
    }

    public function test_admin_content_list_orders_by_lowest_ordinal_first_within_each_group(): void
    {
        $admin = $this->createUserWithRole('Admin');
        /** @var Authenticatable $authenticatedAdmin */
        $authenticatedAdmin = $admin;

        $this->createContent([
            'title' => 'Lower Ordinal',
            'ordinal' => 2,
        ]);

        $this->createContent([
            'title' => 'Higher Ordinal',
            'ordinal' => 9,
        ]);

        $this->createContent([
            'title' => 'Homepage First',
            'contenttype' => Content::TYPE_INDEX,
            'ordinal' => 1,
        ]);

        $this->createContent([
            'title' => 'Homepage Second',
            'contenttype' => Content::TYPE_INDEX,
            'ordinal' => 3,
        ]);

        $response = $this->actingAs($authenticatedAdmin)->get(route('admin.content-list'));

        $response->assertOk();
        $response->assertSeeInOrder(['Lower Ordinal', 'Higher Ordinal']);
        $response->assertSeeInOrder(['Homepage First', 'Homepage Second']);
    }

    public function test_admin_can_reorder_content_within_a_group_only(): void
    {
        $admin = $this->createUserWithRole('Admin');
        /** @var Authenticatable $authenticatedAdmin */
        $authenticatedAdmin = $admin;

        $first = $this->createContent([
            'title' => 'First',
            'ordinal' => 30,
        ]);

        $second = $this->createContent([
            'title' => 'Second',
            'ordinal' => 20,
        ]);

        $third = $this->createContent([
            'title' => 'Third',
            'ordinal' => 10,
        ]);

        $homepage = $this->createContent([
            'title' => 'Homepage Item',
            'contenttype' => Content::TYPE_INDEX,
            'ordinal' => 7,
        ]);

        $response = $this->actingAs($authenticatedAdmin)
            ->postJson(route('admin.content-reorder'), [
                'contenttype' => Content::TYPE_USEFUL,
                'ordered_ids' => [$third->id, $first->id, $second->id],
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Content order updated successfully',
        ]);

        $this->assertSame(
            [$third->id, $first->id, $second->id],
            Content::query()->where('contenttype', Content::TYPE_USEFUL)->ordered()->pluck('id')->all()
        );
        $this->assertSame(1, $third->fresh()->ordinal);
        $this->assertSame(2, $first->fresh()->ordinal);
        $this->assertSame(3, $second->fresh()->ordinal);
        $this->assertSame(7, $homepage->fresh()->ordinal);
    }

    public function test_new_content_defaults_to_bottom_ordinal_within_its_group(): void
    {
        $admin = $this->createUserWithRole('Admin');
        /** @var Authenticatable $authenticatedAdmin */
        $authenticatedAdmin = $admin;

        $this->createContent([
            'title' => 'Top Content',
            'ordinal' => 8,
        ]);

        $this->createContent([
            'title' => 'Bottom Content',
            'ordinal' => 4,
        ]);

        $this->createContent([
            'title' => 'Homepage Content',
            'contenttype' => Content::TYPE_INDEX,
            'ordinal' => 99,
        ]);

        $response = $this->actingAs($authenticatedAdmin)->post(route('admin.content-add'), [
            'action' => 'submit',
            'title' => 'Appended Content',
            'url' => 'appended',
            'body' => '<p>Appended body</p>',
            'metadescription' => 'Appended description',
            'metakeywords' => 'appended',
            'contenttype' => Content::TYPE_USEFUL,
            'status' => Content::STATUS_ENABLED,
            'role' => Content::ROLE_EVERYONE,
        ]);

        $content = Content::query()->where('title', 'Appended Content')->firstOrFail();

        $response->assertRedirect(route('admin.content-add', ['id' => $content->id]));
        $this->assertSame(9, $content->ordinal);
    }

    public function test_deleting_content_does_not_reassign_remaining_ordinals(): void
    {
        $admin = $this->createUserWithRole('Admin');
        /** @var Authenticatable $authenticatedAdmin */
        $authenticatedAdmin = $admin;

        $remainingContent = $this->createContent([
            'title' => 'Keep Me',
            'ordinal' => 12,
        ]);

        $deletedContent = $this->createContent([
            'title' => 'Remove Me',
            'ordinal' => 5,
        ]);

        $response = $this->actingAs($authenticatedAdmin)
            ->postJson(route('admin.content-delete'), ['id' => $deletedContent->id]);

        $response->assertOk();
        $this->assertSame(12, $remainingContent->fresh()->ordinal);
        $this->assertDatabaseMissing('content', ['id' => $deletedContent->id]);
    }

    private function createContent(array $overrides = []): Content
    {
        $defaults = [
            'title' => 'Test Content',
            'url' => '/test-content/',
            'body' => '<p>Test content body</p>',
            'metadescription' => 'Test description',
            'metakeywords' => 'test',
            'contenttype' => Content::TYPE_USEFUL,
            'status' => Content::STATUS_ENABLED,
            'ordinal' => 1,
            'role' => Content::ROLE_EVERYONE,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        return Content::query()->create(array_merge($defaults, $overrides));
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
            $table->integer('contenttype')->default(Content::TYPE_USEFUL);
            $table->integer('status')->default(Content::STATUS_ENABLED);
            $table->integer('ordinal')->nullable();
            $table->integer('role')->default(Content::ROLE_EVERYONE);
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

    private function resetGlobalComposerState(): void
    {
        $reflection = new ReflectionClass(GlobalDataComposer::class);
        $property = $reflection->getProperty('resolvedData');
        $property->setValue(null, null);
    }
}
