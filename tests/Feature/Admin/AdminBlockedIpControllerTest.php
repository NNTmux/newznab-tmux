<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\IpBlockSource;
use App\Http\Middleware\Google2FAMiddleware;
use App\Models\BlockedIp;
use App\Models\User;
use App\Services\Security\IpBlockService;
use App\View\Composers\GlobalDataComposer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminBlockedIpControllerTest extends TestCase
{
    private const string IP = '203.0.113.50';

    private const string ADMIN_IP = '203.0.113.9';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'mail.from.address' => '',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'nntmux.ip_blocking.enabled' => true,
            'nntmux.ip_blocking.allowlist' => [],
            'trustedproxy.cloudflare.enabled' => false,
            'trustedproxy.proxies' => null,
        ]);

        DB::purge();
        DB::reconnect();
        Cache::flush();
        Queue::fake();
        Http::fake();

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
    public function admins_see_each_block_with_its_status_source_and_reason(): void
    {
        $blocks = app(IpBlockService::class);
        $blocks->blockAutomatically(self::IP, 'Tried 3 accounts that do not exist within 24 hours');
        $blocks->blockManually('203.0.113.60', null, 'Scraper');
        $blocks->blockManually('203.0.113.70', now()->addHour(), 'Short block');
        $this->travel(2)->hours();

        $this->actingAs($this->userWithRole('Admin'))->get(route('admin.blocked-ips.index'))
            ->assertOk()
            ->assertViewHas('activeCount', 2)
            ->assertSee(self::IP)
            ->assertSee('Tried 3 accounts that do not exist within 24 hours')
            ->assertSee('Automatic')
            ->assertSee('Scraper')
            ->assertSee('Until lifted')
            ->assertSee('Ended')
            ->assertSee('href="'.route('admin.login-attempts.index', ['ip' => self::IP]).'"', false);
    }

    #[Test]
    public function an_empty_list_explains_itself(): void
    {
        $this->actingAs($this->userWithRole('Admin'))->get(route('admin.blocked-ips.index'))
            ->assertOk()
            ->assertSee('No addresses have been blocked');
    }

    #[Test]
    public function only_admins_can_see_block_or_unblock_addresses(): void
    {
        $block = app(IpBlockService::class)->blockManually(self::IP);
        $this->assertNotNull($block);

        $this->get(route('admin.blocked-ips.index'))->assertRedirect();

        $user = $this->userWithRole('User');
        $this->actingAs($user)->get(route('admin.blocked-ips.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.blocked-ips.store'), ['ip' => '203.0.113.60'])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.blocked-ips.destroy', $block))->assertForbidden();

        $this->assertSame([self::IP], BlockedIp::query()->active()->pluck('ip_address')->all());
    }

    #[Test]
    public function an_admin_can_block_an_address_for_a_number_of_hours(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)->from(route('admin.blocked-ips.index'))
            ->post(route('admin.blocked-ips.store'), ['ip' => self::IP, 'hours' => '168', 'reason' => 'Scraper'])
            ->assertRedirect(route('admin.blocked-ips.index'))
            ->assertSessionHas('success', self::IP.' is blocked for 168 hours.');

        $block = BlockedIp::query()->sole();
        $this->assertSame(self::IP, $block->ip_address);
        $this->assertSame(IpBlockSource::Manual, $block->source);
        $this->assertSame('Scraper', $block->reason);
        $this->assertSame($admin->id, $block->created_by);
        $this->assertSame('2026-11-28 12:00:00', $block->expires_at?->toDateTimeString());
        $this->assertTrue(app(IpBlockService::class)->isBlocked(self::IP));
    }

    #[Test]
    public function a_block_without_a_duration_lasts_until_it_is_lifted(): void
    {
        $this->actingAs($this->userWithRole('Admin'))
            ->post(route('admin.blocked-ips.store'), ['ip' => self::IP, 'hours' => ''])
            ->assertSessionHas('success', self::IP.' is blocked until the block is lifted.');

        $this->assertNull(BlockedIp::query()->sole()->expires_at);
    }

    #[Test]
    public function blocking_from_another_admin_page_returns_to_that_page(): void
    {
        $from = route('admin.login-attempts.index', ['status' => 'failed']);

        $this->actingAs($this->userWithRole('Admin'))->from($from)
            ->post(route('admin.blocked-ips.store'), ['ip' => self::IP, 'hours' => '24'])
            ->assertRedirect($from);
    }

    #[Test]
    public function lifting_a_block_lets_the_address_in_again_and_keeps_its_history(): void
    {
        $block = app(IpBlockService::class)->blockManually(self::IP);
        $this->assertNotNull($block);

        $this->actingAs($this->userWithRole('Admin'))
            ->delete(route('admin.blocked-ips.destroy', $block))
            ->assertRedirect(route('admin.blocked-ips.index'))
            ->assertSessionHas('success', 'The block on '.self::IP.' was lifted.');

        $this->assertFalse(app(IpBlockService::class)->isBlocked(self::IP));
        $this->assertSame(1, BlockedIp::query()->count());
    }

    /**
     * @param  array<string, string>  $input
     */
    #[Test]
    #[DataProvider('rejectedBlocks')]
    public function a_block_that_must_not_be_made_is_rejected(array $input, string $invalidField): void
    {
        $this->actingAs($this->userWithRole('Admin'))
            ->withServerVariables(['REMOTE_ADDR' => self::ADMIN_IP])
            ->post(route('admin.blocked-ips.store'), $input)
            ->assertRedirect()
            ->assertSessionHasErrors($invalidField);

        $this->assertSame(0, BlockedIp::query()->count());
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string}>
     */
    public static function rejectedBlocks(): array
    {
        return [
            'no address' => [[], 'ip'],
            'not an ip address' => [['ip' => 'example.test'], 'ip'],
            'the address the admin is connected from' => [['ip' => self::ADMIN_IP], 'ip'],
            'a private address' => [['ip' => '192.168.1.10'], 'ip'],
            'zero hours' => [['ip' => self::IP, 'hours' => '0'], 'hours'],
            'a reason that is too long' => [['ip' => self::IP, 'reason' => str_repeat('a', 256)], 'reason'],
        ];
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
