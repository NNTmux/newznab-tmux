<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Middleware\Google2FAMiddleware;
use App\Models\Payment;
use App\Models\User;
use App\View\Composers\GlobalDataComposer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminPaymentControllerTest extends TestCase
{
    private const string ALICE_FIRST = 'ORD-ALICE-FIRST';

    private const string ALICE_LATEST = 'ORD-ALICE-LATEST';

    private const string BOB_UNSETTLED = 'ORD-BOB-UNSETTLED';

    private const string CAROL_LEGACY = 'ORD-CAROL-LEGACY';

    private const array ALL_ORDERS = [self::ALICE_FIRST, self::ALICE_LATEST, self::BOB_UNSETTLED, self::CAROL_LEGACY];

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
        $this->travelTo('2026-11-16 12:00:00');
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    #[Test]
    public function the_page_shows_figures_breakdowns_and_a_chart_for_the_payments(): void
    {
        $this->seedPayments();

        $this->actingAs($this->userWithRole('Admin'))->get(route('admin.payment-list'))
            ->assertOk()
            ->assertSee('By plan')
            ->assertSee('By coin')
            ->assertSee('44 not counted as revenue')
            ->assertSee('x-data="adminPayments"', false)
            ->assertSee('&quot;unit&quot;:&quot;month&quot;', false)
            ->assertSee('Recorded before invoice settlement was tracked')
            ->assertSee('Paid, but the role upgrade has not been applied')
            ->assertSee('href="'.route('admin.payment-list.export').'"', false);
    }

    /**
     * @param  array<string, string>  $query
     * @param  list<string>  $expectedOrders
     */
    #[Test]
    #[DataProvider('filters')]
    public function the_list_is_narrowed_by_each_filter(array $query, array $expectedOrders): void
    {
        $this->seedPayments();

        $response = $this->actingAs($this->userWithRole('Admin'))->get(route('admin.payment-list', $query))->assertOk();

        foreach (self::ALL_ORDERS as $order) {
            \in_array($order, $expectedOrders, true) ? $response->assertSee($order) : $response->assertDontSee($order);
        }
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: list<string>}>
     */
    public static function filters(): array
    {
        return [
            'invoice id' => [['q' => 'INV-ALICE-LATEST'], [self::ALICE_LATEST]],
            'username contains' => [['username' => 'ali'], [self::ALICE_FIRST, self::ALICE_LATEST]],
            'exact payer' => [['user' => 'bob'], [self::BOB_UNSETTLED]],
            'email contains' => [['email' => 'carol@'], [self::CAROL_LEGACY]],
            'plan' => [['plan' => 'Supporter 2 years'], [self::CAROL_LEGACY]],
            'coin across method names' => [['coin' => 'BTC'], [self::ALICE_FIRST, self::ALICE_LATEST]],
            'settled' => [['invoice_status' => 'settled'], [self::ALICE_FIRST, self::ALICE_LATEST]],
            'unsettled excludes legacy' => [['invoice_status' => 'unsettled'], [self::BOB_UNSETTLED]],
            'legacy' => [['invoice_status' => 'legacy'], [self::CAROL_LEGACY]],
            'minimum amount' => [['amount_min' => '19'], [self::BOB_UNSETTLED, self::CAROL_LEGACY]],
            'maximum amount' => [['amount_max' => '10'], [self::ALICE_FIRST]],
            'returning payments' => [['payer' => 'returning'], [self::ALICE_LATEST]],
            'first payments' => [['payer' => 'first_time'], [self::ALICE_FIRST, self::BOB_UNSETTLED, self::CAROL_LEGACY]],
            'payers with an account' => [['account' => 'linked'], [self::ALICE_FIRST, self::ALICE_LATEST]],
            'payers without an account' => [['account' => 'missing'], [self::BOB_UNSETTLED, self::CAROL_LEGACY]],
            'custom range' => [['period' => 'custom', 'start_date' => '2026-11-11', 'end_date' => '2026-11-30'], [self::BOB_UNSETTLED]],
            'this year' => [['period' => 'this_year'], [self::ALICE_LATEST, self::BOB_UNSETTLED]],
            'dates without a period are a custom range' => [['start_date' => '2025-01-01', 'end_date' => '2025-12-31'], [self::ALICE_FIRST]],
            'a preset ignores leftover dates' => [['period' => '30days', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31'], [self::ALICE_LATEST, self::BOB_UNSETTLED]],
        ];
    }

    #[Test]
    public function amounts_sort_by_value_not_as_text(): void
    {
        Payment::factory()->plan('Supporter 1 year', '9')->create(['order_id' => 'ORD-NINE']);
        Payment::factory()->plan('Supporter 1 year', '100')->create(['order_id' => 'ORD-HUNDRED']);
        Payment::factory()->plan('Supporter 1 year', '10')->create(['order_id' => 'ORD-TEN']);

        $this->actingAs($this->userWithRole('Admin'))
            ->get(route('admin.payment-list', ['sort' => 'invoice_amount', 'order' => 'asc']))
            ->assertOk()
            ->assertSeeInOrder(['ORD-NINE', 'ORD-TEN', 'ORD-HUNDRED']);
    }

    #[Test]
    public function drilling_into_a_payer_shows_their_history_and_links_to_the_user(): void
    {
        $this->seedPayments();
        $aliceId = (int) DB::table('users')->where('username', 'alice')->value('id');

        $this->actingAs($this->userWithRole('Admin'))->get(route('admin.payment-list', ['user' => 'alice']))
            ->assertOk()
            ->assertSee('Total paid')
            ->assertSee('Supporter ++')
            ->assertSee('href="'.route('admin.user-edit', ['id' => $aliceId]).'"', false)
            ->assertSee('2 payments by this payer')
            ->assertSee('Open user')
            ->assertDontSee('No matching user');
    }

    #[Test]
    public function a_payer_without_an_account_is_marked_as_such(): void
    {
        $this->seedPayments();

        $this->actingAs($this->userWithRole('Admin'))->get(route('admin.payment-list', ['user' => 'bob']))
            ->assertOk()
            ->assertSee('No matching user')
            ->assertDontSee('Open user');
    }

    #[Test]
    public function an_empty_result_explains_itself_and_renders_no_chart(): void
    {
        $this->actingAs($this->userWithRole('Admin'))->get(route('admin.payment-list'))
            ->assertOk()
            ->assertSee('No payments found')
            ->assertDontSee('x-data="adminPayments"', false);
    }

    #[Test]
    public function an_unknown_filter_value_is_rejected(): void
    {
        $this->actingAs($this->userWithRole('Admin'))
            ->get(route('admin.payment-list', ['invoice_status' => 'bogus']))
            ->assertRedirect()
            ->assertSessionHasErrors('invoice_status');
    }

    #[Test]
    public function only_admins_can_open_the_list_or_export_it(): void
    {
        $this->get(route('admin.payment-list'))->assertRedirect();

        $user = $this->userWithRole('User');

        $this->actingAs($user)->get(route('admin.payment-list'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.payment-list.export'))->assertForbidden();
    }

    #[Test]
    public function the_export_downloads_the_filtered_payments_as_csv(): void
    {
        $this->seedPayments();
        Payment::factory()->create(['username' => '=HYPERLINK("https://evil.test")', 'order_id' => 'ORD-FORMULA', 'payment_method' => 'BTC-CHAIN']);
        $adminLog = Mockery::spy(LoggerInterface::class);
        Log::partialMock()->shouldReceive('channel')->with('admin')->andReturn($adminLog);

        $response = $this->actingAs($this->userWithRole('Admin'))
            ->get(route('admin.payment-list.export', ['coin' => 'BTC', 'sort' => 'id', 'order' => 'asc']));

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=payments-', (string) $response->headers->get('Content-Disposition'));

        $rows = array_map(static fn (string $line): array => str_getcsv($line, ',', '"', ''), explode("\n", trim($response->streamedContent())));

        $this->assertSame('ID', $rows[0][0]);
        $this->assertSame([self::ALICE_FIRST, self::ALICE_LATEST, 'ORD-FORMULA'], array_column(\array_slice($rows, 1), 11));
        $this->assertSame(['alice', '10', 'BTC', 'BTC', 'settled'], [$rows[1][2], $rows[1][5], $rows[1][6], $rows[1][7], $rows[1][10]]);
        $this->assertSame('\'=HYPERLINK("https://evil.test")', $rows[3][2], 'A cell that would run as a formula is neutralised');
        $adminLog->shouldHaveReceived('info')->once()->withArgs(
            static fn (string $message, array $context): bool => $context['rows'] === 3 && $context['filters'] === ['coin'],
        );
    }

    #[Test]
    public function the_chart_component_is_registered_with_the_lazy_loader(): void
    {
        $this->assertStringContainsString(
            "'adminPayments':   () => import('./components/admin/payments.js')",
            (string) file_get_contents(resource_path('js/alpine/lazy-loader.js')),
        );
    }

    /**
     * Alice (has an account) paid twice, Bob's invoice is unsettled, Carol's predates settlement tracking.
     */
    private function seedPayments(): void
    {
        $supporter = Role::query()->firstOrCreate(['name' => 'Supporter ++', 'guard_name' => 'web'], ['rate_limit' => 60]);
        DB::table('users')->insert([
            'username' => 'alice',
            'email' => 'alice@example.test',
            'password' => 'secret',
            'roles_id' => $supporter->id,
        ]);

        Payment::factory()->method('BTC', '0.0002')->plan('Supporter 1 year', '10')->paidAt('2025-03-01 10:00:00')
            ->create(['username' => 'alice', 'email' => 'alice@example.test', 'order_id' => self::ALICE_FIRST, 'invoice_id' => 'INV-ALICE-FIRST']);
        Payment::factory()->method('BTC-CHAIN', '0.0001')->plan('Supporter 1 year', '15')->paidAt('2026-11-10 10:00:00')
            ->create(['username' => 'alice', 'email' => 'alice@example.test', 'order_id' => self::ALICE_LATEST, 'invoice_id' => 'INV-ALICE-LATEST']);
        Payment::factory()->unsettled()->method('LTC-CHAIN', '0.6')->plan('Supporter ++ 2 years', '44')->paidAt('2026-11-12 10:00:00')
            ->create(['username' => 'bob', 'email' => 'bob@example.test', 'order_id' => self::BOB_UNSETTLED, 'invoice_id' => 'INV-BOB']);
        Payment::factory()->legacy()->method('XMR', '0.1')->plan('Supporter 2 years', '19')
            ->create(['username' => 'carol', 'email' => 'carol@example.test', 'order_id' => self::CAROL_LEGACY, 'invoice_id' => 'INV-CAROL']);
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
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->string('email');
            $table->string('username');
            $table->string('item_description');
            $table->string('order_id');
            $table->string('payment_id');
            $table->string('payment_status');
            $table->string('invoice_status')->nullable()->default('Pending');
            $table->string('invoice_amount');
            $table->string('payment_method');
            $table->string('payment_value');
            $table->string('webhook_id');
            $table->string('invoice_id');
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
