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

class AdminMonitoringControllerTest extends TestCase
{
    private string $keyDirectory = '';

    private string $publicKey = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->keyDirectory = storage_path('framework/testing/monitoring/'.Str::uuid()->toString());
        File::ensureDirectoryExists($this->keyDirectory);

        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $private);
        File::put($this->keyDirectory.'/grafana-jwt.key', $private);
        $this->publicKey = openssl_pkey_get_details($key)['key'];

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'mail.from.address' => '',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'monitoring.enabled' => true,
            'monitoring.grafana.url' => '/grafana',
            'monitoring.grafana.jwt.private_key_path' => $this->keyDirectory.'/grafana-jwt.key',
            'monitoring.grafana.jwt.ttl' => 900,
        ]);

        DB::purge();
        DB::reconnect();
        Cache::flush();
        Queue::fake();

        $this->createSchema();
        $this->resetGlobalComposerState();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withoutMiddleware(Google2FAMiddleware::class);
    }

    protected function tearDown(): void
    {
        if ($this->keyDirectory !== '') {
            File::deleteDirectory($this->keyDirectory);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_admin_sees_a_tab_and_iframe_for_every_dashboard_without_a_token_in_the_html(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.monitoring'));

        $response->assertOk();
        $response->assertSee('x-data="adminMonitoring"', false);
        $response->assertSee('data-token-url="'.route('admin.monitoring.token').'"', false);
        foreach (config('monitoring.grafana.dashboards') as $dashboard) {
            $response->assertSee('/grafana/d/'.$dashboard['uid'].'/', false);
        }
        $response->assertDontSee('auth_token', false);
        $response->assertSee('href="'.route('admin.monitoring').'"', false);
    }

    public function test_page_explains_setup_when_monitoring_is_disabled(): void
    {
        config(['monitoring.enabled' => false]);

        $response = $this->actingAs($this->admin())->get(route('admin.monitoring'));

        $response->assertOk();
        $response->assertSee('Monitoring is not set up');
        $response->assertSee('php artisan monitoring:install');
        $response->assertDontSee('x-data="adminMonitoring"', false);
    }

    public function test_page_points_at_the_key_when_enabled_without_a_readable_key(): void
    {
        config(['monitoring.grafana.jwt.private_key_path' => $this->keyDirectory.'/missing.key']);

        $this->actingAs($this->admin())
            ->get(route('admin.monitoring'))
            ->assertOk()
            ->assertSee('GRAFANA_JWT_PRIVATE_KEY_PATH');
    }

    public function test_token_endpoint_returns_a_signed_viewer_token(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->getJson(route('admin.monitoring.token'));

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        [$header, $payload, $signature] = explode('.', (string) $response->json('token'));
        $this->assertSame(1, openssl_verify("{$header}.{$payload}", $this->base64UrlDecode($signature), $this->publicKey, OPENSSL_ALGO_SHA256));
        $claims = json_decode($this->base64UrlDecode($payload), true);
        $this->assertSame($admin->username, $claims['sub']);
        $this->assertSame('Viewer', $claims['role']);
        $this->assertSame($claims['exp'], $response->json('expires_at'));
        $this->assertSame(900, $response->json('ttl'));
    }

    public function test_token_endpoint_is_not_found_when_monitoring_is_disabled(): void
    {
        config(['monitoring.enabled' => false]);

        $this->actingAs($this->admin())
            ->getJson(route('admin.monitoring.token'))
            ->assertNotFound()
            ->assertJsonMissingPath('token');
    }

    public function test_non_admins_are_forbidden(): void
    {
        /** @var Authenticatable $user */
        $user = $this->createUserWithRole('User');

        $this->actingAs($user)->get(route('admin.monitoring'))->assertForbidden();
        $this->actingAs($user)->getJson(route('admin.monitoring.token'))->assertForbidden();
    }

    public function test_guests_cannot_get_a_token(): void
    {
        $response = $this->getJson(route('admin.monitoring.token'));

        $this->assertContains($response->status(), [401, 403]);
        $response->assertJsonMissingPath('token');
    }

    private function admin(): User
    {
        return $this->createUserWithRole('Admin');
    }

    private function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }

    private function createSchema(): void
    {

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
}
