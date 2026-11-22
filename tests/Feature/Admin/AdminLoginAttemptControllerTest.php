<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Middleware\Google2FAMiddleware;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\Security\IpBlockService;
use App\View\Composers\GlobalDataComposer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminLoginAttemptControllerTest extends TestCase
{
    private const int ALICE_ID = 501;

    private const int BOB_ID = 502;

    private const int ERASED_ID = 999;

    private const string BOB_MEDIUM = 'agent-bob-medium';

    private const string STRANGER_FAILED = 'agent-stranger-failed';

    private const string ALICE_FAILED = 'agent-alice-failed';

    private const string BOB_HIGH = 'agent-bob-high';

    private const string ALICE_OK = 'agent-alice-ok';

    private const array ALL_ATTEMPTS = [self::BOB_MEDIUM, self::STRANGER_FAILED, self::ALICE_FAILED, self::BOB_HIGH, self::ALICE_OK];

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
        $property = (new \ReflectionClass(GlobalDataComposer::class))->getProperty('resolvedData');
        $property->setValue(null, null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withoutMiddleware(Google2FAMiddleware::class);
        $this->travelTo('2026-11-21 12:00:00');
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    #[Test]
    public function admins_see_attempts_newest_first_with_account_location_risk_and_the_last_day_summary(): void
    {
        $this->seedAttempts();

        $this->actingAs($this->userWithRole('Admin'))->get(route('admin.login-attempts.index'))
            ->assertOk()
            ->assertSeeInOrder([self::ALICE_OK, self::BOB_HIGH, self::ALICE_FAILED, self::STRANGER_FAILED, self::BOB_MEDIUM])
            ->assertSee('alice@example.test')
            ->assertSee('href="'.route('admin.user-edit', ['id' => self::ALICE_ID]).'"', false)
            ->assertSee('NL, Amsterdam')
            ->assertSee('Login from a new country')
            ->assertViewHas('summary', ['total' => 3, 'failed' => 1, 'suspicious' => 1]);
    }

    #[Test]
    public function only_admins_can_open_the_page(): void
    {
        $this->get(route('admin.login-attempts.index'))->assertRedirect();

        $this->actingAs($this->userWithRole('User'))->get(route('admin.login-attempts.index'))->assertForbidden();
    }

    /**
     * @param  array<string, string>  $query
     * @param  list<string>  $expectedAttempts
     */
    #[Test]
    #[DataProvider('filters')]
    public function the_list_is_narrowed_by_each_filter(array $query, array $expectedAttempts): void
    {
        $this->seedAttempts();

        $response = $this->actingAs($this->userWithRole('Admin'))->get(route('admin.login-attempts.index', $query))->assertOk();

        foreach (self::ALL_ATTEMPTS as $attempt) {
            \in_array($attempt, $expectedAttempts, true) ? $response->assertSee($attempt) : $response->assertDontSee($attempt);
        }
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: list<string>}>
     */
    public static function filters(): array
    {
        return [
            'failed' => [['status' => 'failed'], [self::STRANGER_FAILED, self::ALICE_FAILED]],
            'successful' => [['status' => 'successful'], [self::BOB_MEDIUM, self::BOB_HIGH, self::ALICE_OK]],
            'username contains' => [['user' => 'ali'], [self::ALICE_FAILED, self::ALICE_OK]],
            'email contains' => [['user' => 'corp.test'], [self::BOB_MEDIUM, self::BOB_HIGH]],
            'submitted login of an unknown account' => [['user' => 'intruder'], [self::STRANGER_FAILED]],
            'account id' => [['user_id' => (string) self::BOB_ID], [self::BOB_MEDIUM, self::BOB_HIGH]],
            'a full ip address matches exactly' => [['ip' => '10.0.0.1'], [self::ALICE_OK]],
            'a partial ip address matches as a prefix' => [['ip' => '198.51.100.'], [self::STRANGER_FAILED, self::BOB_HIGH]],
            'one risk level' => [['risk' => 'high'], [self::BOB_HIGH]],
            'suspicious covers medium and high' => [['risk' => 'suspicious'], [self::BOB_MEDIUM, self::BOB_HIGH]],
            'date range includes the whole last day' => [['date_from' => '2026-11-19', 'date_to' => '2026-11-20'], [self::STRANGER_FAILED, self::ALICE_FAILED]],
            'filters combine' => [['status' => 'successful', 'user_id' => (string) self::ALICE_ID], [self::ALICE_OK]],
        ];
    }

    #[Test]
    public function an_attempt_without_a_live_account_shows_the_submitted_login_a_deleted_marker_or_the_account_id(): void
    {
        $this->seedAccount(self::ALICE_ID, 'alice', 'alice@example.test');
        $this->recordAttempt(['successful' => false, 'identifier' => 'intruder@evil.test']);
        $this->recordAttempt(['authenticatable_type' => User::class, 'authenticatable_id' => self::ALICE_ID]);
        $this->recordAttempt(['authenticatable_type' => User::class, 'authenticatable_id' => self::ERASED_ID]);
        DB::table('users')->where('id', self::ALICE_ID)->update(['deleted_at' => now()]);

        $this->actingAs($this->userWithRole('Admin'))->get(route('admin.login-attempts.index'))
            ->assertOk()
            ->assertSee('intruder@evil.test')
            ->assertSee('No matching account')
            ->assertSee('alice')
            ->assertSee('Deleted account')
            ->assertDontSee('href="'.route('admin.user-edit', ['id' => self::ALICE_ID]).'"', false)
            ->assertSee('User #'.self::ERASED_ID)
            ->assertSee('Account no longer exists');
    }

    #[Test]
    public function a_failed_sign_in_to_a_deleted_account_is_marked_as_such_instead_of_as_an_unknown_account(): void
    {
        $this->seedAccount(self::ALICE_ID, 'alice', 'alice@example.test');
        DB::table('users')->where('id', self::ALICE_ID)->update(['deleted_at' => now()]);
        $this->recordAttempt(['successful' => false, 'identifier' => 'Alice']);
        $this->recordAttempt(['successful' => false, 'identifier' => 'alice@example.test']);

        $this->actingAs($this->userWithRole('Admin'))->get(route('admin.login-attempts.index'))
            ->assertOk()
            ->assertViewHas('deletedAccountLogins', ['alice@example.test', 'alice'])
            ->assertSee('Deleted account')
            ->assertDontSee('No matching account');
    }

    #[Test]
    public function a_blocked_address_is_marked_and_other_addresses_offer_a_block_button(): void
    {
        $this->recordAttempt(['successful' => false, 'identifier' => 'ghost', 'ip_address' => '203.0.113.50']);
        $this->recordAttempt(['successful' => false, 'identifier' => 'typo', 'ip_address' => '203.0.113.60']);
        app(IpBlockService::class)->blockManually('203.0.113.50');

        $this->actingAs($this->userWithRole('Admin'))->get(route('admin.login-attempts.index'))
            ->assertOk()
            ->assertViewHas('blockedIps', ['203.0.113.50'])
            ->assertSee('IP blocked')
            ->assertSee('aria-label="Block 203.0.113.60"', false)
            ->assertDontSee('aria-label="Block 203.0.113.50"', false);
    }

    #[Test]
    public function pagination_keeps_the_filters(): void
    {
        config(['nntmux.items_per_page' => 2]);
        $this->recordAttempt(['successful' => false, 'identifier' => 'first']);
        $this->recordAttempt(['successful' => false, 'identifier' => 'second']);
        $this->recordAttempt(['successful' => false, 'identifier' => 'third']);

        $this->actingAs($this->userWithRole('Admin'))->get(route('admin.login-attempts.index', ['status' => 'failed']))
            ->assertOk()
            ->assertSee('status=failed&amp;page=2', false)
            ->assertDontSee('first');
    }

    #[Test]
    public function an_empty_result_explains_itself(): void
    {
        $this->actingAs($this->userWithRole('Admin'))->get(route('admin.login-attempts.index'))
            ->assertOk()
            ->assertSee('No login attempts found')
            ->assertViewHas('summary', ['total' => 0, 'failed' => 0, 'suspicious' => 0]);
    }

    /**
     * @param  array<string, string>  $query
     */
    #[Test]
    #[DataProvider('invalidFilters')]
    public function an_invalid_filter_value_is_rejected(array $query, string $invalidField): void
    {
        $this->actingAs($this->userWithRole('Admin'))
            ->get(route('admin.login-attempts.index', $query))
            ->assertRedirect()
            ->assertSessionHasErrors($invalidField);
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string}>
     */
    public static function invalidFilters(): array
    {
        return [
            'unknown result' => [['status' => 'bogus'], 'status'],
            'unknown risk' => [['risk' => 'bogus'], 'risk'],
            'wildcard in the ip address' => [['ip' => '%'], 'ip'],
            'end date before the start date' => [['date_from' => '2026-11-20', 'date_to' => '2026-11-19'], 'date_to'],
        ];
    }

    /**
     * Five attempts in chronological order: Bob (medium risk), a stranger's failure, Alice's failure
     * late on the 20th, then Bob (high risk) and Alice within the last 24 hours.
     */
    private function seedAttempts(): void
    {
        $this->seedAccount(self::ALICE_ID, 'alice', 'alice@example.test');
        $this->seedAccount(self::BOB_ID, 'bob', 'bob@corp.test');
        $alice = ['authenticatable_type' => User::class, 'authenticatable_id' => self::ALICE_ID];
        $bob = ['authenticatable_type' => User::class, 'authenticatable_id' => self::BOB_ID];

        $this->recordAttempt($bob + [
            'user_agent' => self::BOB_MEDIUM, 'ip_address' => '192.0.2.5', 'created_at' => '2026-11-05 10:00:00',
            'risk_level' => 'medium', 'risk_score' => 45,
        ]);
        $this->recordAttempt([
            'user_agent' => self::STRANGER_FAILED, 'ip_address' => '198.51.100.99', 'created_at' => '2026-11-19 10:00:00',
            'successful' => false, 'identifier' => 'intruder@evil.test',
        ]);
        $this->recordAttempt($alice + [
            'user_agent' => self::ALICE_FAILED, 'ip_address' => '10.0.0.10', 'created_at' => '2026-11-20 23:30:00',
            'successful' => false, 'identifier' => 'alice',
        ]);
        $this->recordAttempt($bob + [
            'user_agent' => self::BOB_HIGH, 'ip_address' => '198.51.100.7', 'created_at' => '2026-11-21 08:00:00',
            'risk_level' => 'high', 'risk_score' => 80, 'country_code' => 'NL', 'city' => 'Amsterdam',
            'reasons' => [['check' => 'new_country', 'score' => 30, 'reason' => 'Login from a new country']],
        ]);
        $this->recordAttempt($alice + [
            'user_agent' => self::ALICE_OK, 'ip_address' => '10.0.0.1', 'created_at' => '2026-11-21 09:00:00',
            'risk_level' => 'low', 'risk_score' => 5,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recordAttempt(array $attributes): LoginAttempt
    {
        return LoginAttempt::query()->create($attributes + [
            'guard' => 'web',
            'successful' => true,
            'blocked' => false,
            'ip_address' => '203.0.113.10',
        ]);
    }

    private function seedAccount(int $id, string $username, string $email): void
    {
        DB::table('users')->insert(['id' => $id, 'username' => $username, 'email' => $email, 'password' => 'secret']);
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName, 'guard_name' => 'web'], ['rate_limit' => 60]);

        /** @var User $user */
        $user = User::query()->create([
            'username' => strtolower($roleName).'_'.Str::random(6),
            'email' => Str::random(10).'@example.test',
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

    private function createSchema(): void
    {
        (require database_path('migrations/2026_11_19_171205_create_login_attempts_table.php'))->up();
        (require database_path('migrations/2026_11_21_110719_add_created_at_index_to_login_attempts_table.php'))->up();
        (require database_path('migrations/2026_11_21_165723_create_blocked_ips_table.php'))->up();

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
        Schema::create('user_activities', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('username');
            $table->string('activity_type', 50);
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
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
    }
}
