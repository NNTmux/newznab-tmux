<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Api\ApiController;
use App\Http\Middleware\Google2FAMiddleware;
use App\Models\RegistrationStatusHistory;
use App\Models\Settings;
use App\Models\User;
use App\Services\RegistrationFailureLogService;
use App\View\Composers\GlobalDataComposer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminRegistrationControllerTest extends TestCase
{
    private string $logNamespace = '';

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
        $this->seedSettings();
        $this->resetGlobalComposerState();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withoutMiddleware(Google2FAMiddleware::class);

        $this->logNamespace = 'registration-admin-tests/'.Str::uuid()->toString();
        File::ensureDirectoryExists(storage_path('logs/'.$this->logNamespace));
        $this->app->bind(RegistrationFailureLogService::class, function () {
            return new RegistrationFailureLogService(storage_path('logs/'.$this->logNamespace));
        });
    }

    protected function tearDown(): void
    {
        if ($this->logNamespace !== '') {
            File::deleteDirectory(storage_path('logs/'.$this->logNamespace));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_admin_can_view_registration_admin_page_with_recent_activity(): void
    {
        $admin = $this->createUserWithRole('Admin');
        /** @var Authenticatable $authenticatedAdmin */
        $authenticatedAdmin = $admin;

        DB::table('registration_periods')->insert([
            'name' => 'Weekend Open',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'is_enabled' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_activities')->insert([
            'user_id' => 999,
            'username' => 'freshmember',
            'activity_type' => 'registered',
            'description' => 'New user registered: freshmember',
            'metadata' => json_encode([
                'email' => 'freshmember@example.com',
                'ip_address' => '127.0.0.1',
            ]),
            'created_at' => now(),
        ]);

        DB::table('registration_status_history')->insert([
            'action' => RegistrationStatusHistory::ACTION_MANUAL_STATUS_CHANGED,
            'old_status' => Settings::REGISTER_STATUS_OPEN,
            'new_status' => Settings::REGISTER_STATUS_INVITE,
            'changed_by' => $admin->id,
            'description' => 'Manual registration status changed from Open to Invite.',
            'metadata' => json_encode(['note' => 'Temporary slowdown']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createRegistrationLogFile([
            '[2026-03-10 12:00:00] testing.WARNING: Registration attempt failed: invalid_or_expired_invitation {"reason":"invalid_or_expired_invitation","email":"invalid@example.com","username":"badinvite","ip":"10.1.1.2","registration_status":1,"manual_registration_status":1} []',
        ]);

        $response = $this->actingAs($authenticatedAdmin)->get(route('admin.registrations.index'));

        $response->assertOk();
        $response->assertSee('Registration Admin');
        $response->assertSee('Weekend Open');
        $response->assertSee('freshmember@example.com');
        $response->assertSee('invalid_or_expired_invitation');
        $response->assertSee('Temporary slowdown');
    }

    public function test_admin_can_update_manual_registration_status_and_record_history(): void
    {
        $admin = $this->createUserWithRole('Admin');
        /** @var Authenticatable $authenticatedAdmin */
        $authenticatedAdmin = $admin;

        $this->actingAs($authenticatedAdmin)
            ->post(route('admin.registrations.update-status'), [
                'registerstatus' => Settings::REGISTER_STATUS_CLOSED,
                'note' => 'Closing signups for maintenance.',
            ])
            ->assertRedirect(route('admin.registrations.index'))
            ->assertSessionHas('success', 'Manual registration status updated successfully.');

        $this->assertSame(
            Settings::REGISTER_STATUS_CLOSED,
            (int) DB::table('settings')->where('name', 'registerstatus')->value('value')
        );

        $history = DB::table('registration_status_history')
            ->where('action', RegistrationStatusHistory::ACTION_MANUAL_STATUS_CHANGED)
            ->latest('id')
            ->first();

        $this->assertNotNull($history);
        $this->assertSame(Settings::REGISTER_STATUS_OPEN, $history->old_status);
        $this->assertSame(Settings::REGISTER_STATUS_CLOSED, $history->new_status);
        $this->assertSame($admin->id, $history->changed_by);
        $this->assertStringContainsString('maintenance', (string) $history->metadata);
    }

    public function test_admin_can_manage_scheduled_periods_and_api_capabilities_follow_effective_status(): void
    {
        DB::table('settings')
            ->where('name', 'registerstatus')
            ->update(['value' => (string) Settings::REGISTER_STATUS_CLOSED]);

        $admin = $this->createUserWithRole('Admin');
        /** @var Authenticatable $authenticatedAdmin */
        $authenticatedAdmin = $admin;

        $this->actingAs($authenticatedAdmin)
            ->post(route('admin.registrations.periods.store'), [
                'name' => 'Launch Window',
                'starts_at' => now()->subHour()->format('Y-m-d\TH:i'),
                'ends_at' => now()->addHour()->format('Y-m-d\TH:i'),
                'is_enabled' => '1',
                'notes' => 'Temporarily open registrations after deploy.',
            ])
            ->assertRedirect(route('admin.registrations.index'));

        $periodId = (int) DB::table('registration_periods')->value('id');
        $this->assertGreaterThan(0, $periodId);

        $this->getJson('/api/v2/capabilities')
            ->assertOk()
            ->assertJsonPath('registration.available', 'yes')
            ->assertJsonPath('registration.open', 'yes');

        $apiController = app(ApiController::class);
        $reflection = new ReflectionClass($apiController);
        $typeProperty = $reflection->getProperty('type');
        $typeProperty->setAccessible(true);
        $typeProperty->setValue($apiController, 'caps');

        $menu = $apiController->getForMenu();
        $this->assertSame('yes', $menu['registration']['available']);
        $this->assertSame('yes', $menu['registration']['open']);

        $this->actingAs($authenticatedAdmin)
            ->put(route('admin.registrations.periods.update', $periodId), [
                'name' => 'Launch Window Updated',
                'starts_at' => now()->subMinutes(30)->format('Y-m-d\TH:i'),
                'ends_at' => now()->addHours(2)->format('Y-m-d\TH:i'),
                'is_enabled' => '1',
                'notes' => 'Extended after launch demand.',
            ])
            ->assertRedirect(route('admin.registrations.index'));

        $this->assertSame(
            'Launch Window Updated',
            DB::table('registration_periods')->where('id', $periodId)->value('name')
        );

        $this->actingAs($authenticatedAdmin)
            ->post(route('admin.registrations.periods.toggle', $periodId))
            ->assertRedirect(route('admin.registrations.index'));

        $this->assertSame(0, (int) DB::table('registration_periods')->where('id', $periodId)->value('is_enabled'));

        $this->getJson('/api/v2/capabilities')
            ->assertOk()
            ->assertJsonPath('registration.available', 'no')
            ->assertJsonPath('registration.open', 'no');

        $this->actingAs($authenticatedAdmin)
            ->delete(route('admin.registrations.periods.destroy', $periodId))
            ->assertRedirect(route('admin.registrations.index'));

        $this->assertNull(DB::table('registration_periods')->where('id', $periodId)->first());

        $actions = DB::table('registration_status_history')
            ->pluck('action')
            ->all();

        $this->assertContains(RegistrationStatusHistory::ACTION_PERIOD_CREATED, $actions);
        $this->assertContains(RegistrationStatusHistory::ACTION_PERIOD_UPDATED, $actions);
        $this->assertContains(RegistrationStatusHistory::ACTION_PERIOD_TOGGLED, $actions);
        $this->assertContains(RegistrationStatusHistory::ACTION_PERIOD_DELETED, $actions);
    }

    public function test_expired_registration_periods_are_disabled_and_shown_as_done(): void
    {
        $admin = $this->createUserWithRole('Admin');
        /** @var Authenticatable $authenticatedAdmin */
        $authenticatedAdmin = $admin;

        DB::table('registration_periods')->insert([
            'name' => 'Current Window',
            'starts_at' => now()->subMinutes(30),
            'ends_at' => now()->addHour(),
            'is_enabled' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('registration_periods')->insert([
            'name' => 'Upcoming Disabled Window',
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(4),
            'is_enabled' => false,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('registration_periods')->insert([
            'name' => 'Completed Window',
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subMinutes(10),
            'is_enabled' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('nntmux:disable-expired-registration-periods')
            ->expectsOutputToContain('Successfully completed 1 expired registration period(s).')
            ->assertExitCode(0);

        $completedPeriod = DB::table('registration_periods')->where('name', 'Completed Window')->first();
        $this->assertNotNull($completedPeriod);
        $this->assertSame(0, (int) $completedPeriod->is_enabled);

        $currentPeriod = DB::table('registration_periods')->where('name', 'Current Window')->first();
        $this->assertNotNull($currentPeriod);
        $this->assertSame(1, (int) $currentPeriod->is_enabled);

        $history = DB::table('registration_status_history')
            ->where('action', RegistrationStatusHistory::ACTION_PERIOD_COMPLETED)
            ->latest('id')
            ->first();

        $this->assertNotNull($history);
        $this->assertStringContainsString('completed after reaching its end time', $history->description);

        $response = $this->actingAs($authenticatedAdmin)->get(route('admin.registrations.index'));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Current & Upcoming Open Periods',
            'Current Window',
            'Upcoming Disabled Window',
            'Past Open Periods',
            'Completed Window',
        ]);
        $response->assertSee('Active Right Now');
        $response->assertSee('Done');
        $response->assertSee('x-data="confirmForm"', false);
        $response->assertSee('data-title="Disable Scheduled Period"', false);
        $response->assertSee('data-title="Enable Scheduled Period"', false);
        $response->assertSee('data-title="Delete Scheduled Period"', false);
        $response->assertSee('data-title="Delete Past Period"', false);
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

        Schema::create('registration_periods', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('is_enabled')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('registration_status_history', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('action', 100);
            $table->unsignedTinyInteger('old_status')->nullable();
            $table->unsignedTinyInteger('new_status')->nullable();
            $table->unsignedInteger('registration_period_id')->nullable();
            $table->unsignedInteger('changed_by')->nullable();
            $table->string('description');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    private function seedSettings(): void
    {
        DB::table('settings')->insert([
            ['name' => 'registerstatus', 'value' => (string) Settings::REGISTER_STATUS_OPEN],
            ['name' => 'title', 'value' => 'NNTmux Test'],
            ['name' => 'home_link', 'value' => '/'],
        ]);

        DB::table('root_categories')->insert([
            'id' => 1,
            'title' => 'Movies',
            'status' => 1,
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

        $user = User::query()->create([
            'username' => strtolower($roleName).'_'.Str::random(8),
            'email' => Str::random(12).'@example.test',
            'password' => bcrypt('password'),
            'roles_id' => $role->id,
            'rate_limit' => 60,
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
        $reflection = new ReflectionClass(GlobalDataComposer::class);
        $property = $reflection->getProperty('resolvedData');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    /**
     * @param  list<string>  $lines
     */
    private function createRegistrationLogFile(array $lines): void
    {
        $absolutePath = storage_path('logs/'.$this->logNamespace.'/registration.log');
        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, implode(PHP_EOL, $lines).PHP_EOL);
        clearstatcache(true, $absolutePath);
    }
}
