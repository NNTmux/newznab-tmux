<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\Google2FAMiddleware;
use App\Models\User;
use App\View\Composers\GlobalDataComposer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminLogViewerControllerTest extends TestCase
{
    private string $logNamespace;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'mail.from.address' => '',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);

        DB::purge();
        DB::reconnect();
        Cache::flush();
        Queue::fake();

        $this->createSchema();
        $this->resetGlobalComposerState();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withoutMiddleware(Google2FAMiddleware::class);

        $this->logNamespace = 'admin-log-viewer-tests/'.Str::uuid()->toString();
        File::ensureDirectoryExists(storage_path('logs/'.$this->logNamespace));
    }

    protected function tearDown(): void
    {
        if ($this->logNamespace !== '') {
            File::deleteDirectory(storage_path('logs/'.$this->logNamespace));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_admin_can_open_log_viewer_and_see_available_logs(): void
    {
        $selectedLog = $this->createLogFile('application.log', [
            '[2026-03-10 09:00:00] local.INFO: first entry',
            '[2026-03-10 09:01:00] local.WARNING: second entry',
            '[2026-03-10 09:02:00] local.ERROR: third entry',
        ]);
        $otherLog = $this->createLogFile('secondary.log', [
            '[2026-03-10 10:00:00] local.INFO: secondary log entry',
        ]);

        $admin = $this->createUserWithRole('Admin');
        /** @var Authenticatable $authenticatedAdmin */
        $authenticatedAdmin = $admin;

        $response = $this->actingAs($authenticatedAdmin)->get(route('admin.logs.index', [
            'file' => $selectedLog,
            'lines' => 100,
        ]));

        $response->assertOk();
        $response->assertSee('Log Viewer');
        $response->assertSee($selectedLog);
        $response->assertSee($otherLog);
        $response->assertSee('Showing the latest 3 of 3 lines.');
        $response->assertSee('[2026-03-10 09:02:00] local.ERROR: third entry');
    }

    public function test_non_admin_user_is_forbidden_from_log_viewer(): void
    {
        $user = $this->createUserWithRole('User');
        /** @var Authenticatable $authenticatedUser */
        $authenticatedUser = $user;

        $this->actingAs($authenticatedUser)
            ->get(route('admin.logs.index'))
            ->assertForbidden();
    }

    public function test_admin_can_search_within_selected_log_file_only(): void
    {
        $selectedLog = $this->createLogFile('search-target.log', [
            'boot sequence started',
            'match alpha',
            'continuing process',
            'match beta',
        ]);
        $this->createLogFile('other.log', [
            'match from other file',
        ]);

        $admin = $this->createUserWithRole('Admin');
        /** @var Authenticatable $authenticatedAdmin */
        $authenticatedAdmin = $admin;

        $response = $this->actingAs($authenticatedAdmin)->get(route('admin.logs.index', [
            'file' => $selectedLog,
            'search' => 'match',
            'lines' => 100,
        ]));

        $response->assertOk();
        $response->assertSee('Found 2 matching lines');
        $response->assertSee('Line 2');
        $response->assertSee('Line 4');
        $response->assertSee('match alpha');
        $response->assertSee('match beta');
        $response->assertDontSee('match from other file');
    }

    public function test_unknown_log_file_redirects_with_error(): void
    {
        $this->createLogFile('known.log', [
            'known entry',
        ]);

        $admin = $this->createUserWithRole('Admin');
        /** @var Authenticatable $authenticatedAdmin */
        $authenticatedAdmin = $admin;

        $this->actingAs($authenticatedAdmin)
            ->get(route('admin.logs.index', [
                'file' => $this->logNamespace.'/missing.log',
            ]))
            ->assertRedirect(route('admin.logs.index'))
            ->assertSessionHas('error', 'Selected log file is not available.');
    }

    public function test_path_traversal_is_rejected(): void
    {
        $this->createLogFile('known.log', [
            'known entry',
        ]);

        $admin = $this->createUserWithRole('Admin');
        /** @var Authenticatable $authenticatedAdmin */
        $authenticatedAdmin = $admin;

        $this->actingAs($authenticatedAdmin)
            ->get(route('admin.logs.index', [
                'file' => '../bootstrap/app.php',
            ]))
            ->assertRedirect(route('admin.logs.index'))
            ->assertSessionHas('error', 'Selected log file is not available.');
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
            $table->string('api_token')->nullable();
            $table->boolean('verified')->default(true);
            $table->boolean('can_post')->default(true);
            $table->integer('rate_limit')->default(60);
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

        Schema::create('categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->unsignedInteger('root_categories_id')->nullable();
            $table->integer('status')->default(1);
        });

        Schema::create('root_categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->integer('status')->default(1);
        });

        Schema::create('user_excluded_categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->unsignedInteger('categories_id');
        });

        Schema::create('content', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->string('url')->nullable();
            $table->text('body')->nullable();
            $table->text('metadescription')->nullable();
            $table->text('metakeywords')->nullable();
            $table->integer('contenttype')->default(1);
            $table->integer('status')->default(1);
            $table->integer('ordinal')->nullable();
            $table->integer('role')->default(0);
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

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(
            [
                'name' => $roleName,
                'guard_name' => 'web',
            ],
            [
                'rate_limit' => 60,
            ]
        );

        $user = User::query()->create([
            'username' => strtolower($roleName).'_'.Str::random(8),
            'email' => Str::random(12).'@example.test',
            'password' => bcrypt('password'),
            'roles_id' => $role->id,
            'api_token' => Str::random(32),
            'verified' => true,
            'email_verified_at' => now(),
            'lastlogin' => now(),
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function resetGlobalComposerState(): void
    {
        $reflection = new \ReflectionClass(GlobalDataComposer::class);
        $property = $reflection->getProperty('resolvedData');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    /**
     * @param  list<string>  $lines
     */
    private function createLogFile(string $filename, array $lines): string
    {
        $relativePath = $this->logNamespace.'/'.$filename;
        $absolutePath = storage_path('logs/'.$relativePath);

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, implode(PHP_EOL, $lines).PHP_EOL);
        clearstatcache(true, $absolutePath);

        return $relativePath;
    }
}
