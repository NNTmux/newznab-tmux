<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Admin\AdminPageController;
use App\Models\Release;
use App\Models\User;
use App\Services\AdminDashboardSnapshotService;
use App\Services\Monitoring\GrafanaEmbedService;
use App\Services\RegistrationStatusService;
use App\Services\SiteStatusService;
use App\Services\SystemMetricsService;
use App\Services\UserStatsService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class AdminDashboardUserCountTest extends TestCase
{
    private AdminDashboardSnapshotService $snapshot;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
        ]);
        DB::purge();
        Cache::flush();
        $this->freezeTime();
        $this->createSchema();

        $userStats = $this->createMock(UserStatsService::class);
        $systemMetrics = $this->createMock(SystemMetricsService::class);
        $systemMetrics->method('getDiskSpace')->willReturn('100 GB');
        $systemMetrics->method('getCpuInfo')->willReturn(['cores' => 4, 'threads' => 8, 'model' => 'Test CPU']);
        $systemMetrics->method('getRamUsage')->willReturn(['used' => 1, 'total' => 8, 'percentage' => 12.5]);
        $registration = $this->createMock(RegistrationStatusService::class);
        $registration->method('resolve')->willReturn([
            'manual_status' => RegistrationStatus::Open->value,
            'manual_status_label' => 'Open',
            'effective_status' => RegistrationStatus::Open->value,
            'effective_status_label' => 'Open',
            'active_period' => null,
            'scheduled_override_active' => false,
            'message' => 'Registration is open.',
        ]);
        $siteStatus = $this->createMock(SiteStatusService::class);
        $siteStatus->method('getEnabledServices')->willReturn(new Collection);
        $siteStatus->method('getActiveIncidents')->willReturn(new Collection);

        $this->app->instance(UserStatsService::class, $userStats);
        $this->app->instance(SystemMetricsService::class, $systemMetrics);
        $this->app->instance(RegistrationStatusService::class, $registration);
        $this->app->instance(SiteStatusService::class, $siteStatus);
        $this->snapshot = new AdminDashboardSnapshotService($userStats, $systemMetrics, $registration, $siteStatus);
        $this->app->instance(AdminDashboardSnapshotService::class, $this->snapshot);
    }

    public function test_dashboard_counts_exact_rows_and_excludes_soft_deleted_users(): void
    {
        $this->insertRelease(10);
        $this->insertRelease(20);
        $this->insertRelease(50, now()->subDay()->toDateTimeString());
        $this->insertUser(10);
        $this->insertUser(20, now()->subDay()->toDateTimeString());
        $this->insertUser(30, deletedAt: now()->toDateTimeString());
        DB::table('usenet_groups')->insert([
            ['id' => 10, 'active' => 1],
            ['id' => 20, 'active' => 1],
            ['id' => 50, 'active' => 0],
        ]);
        DB::table('dnzb_failures')->insert([
            ['release_id' => 10, 'users_id' => 10, 'failed' => 1],
            ['release_id' => 10, 'users_id' => 20, 'failed' => 2],
            ['release_id' => 20, 'users_id' => 10, 'failed' => 1],
        ]);
        DB::table('release_reports')->insert([
            ['status' => 'pending'],
            ['status' => 'pending'],
            ['status' => 'resolved'],
        ]);
        DB::table('user_activities')->insert([
            ['activity_type' => 'deleted', 'is_permanent' => true],
            ['activity_type' => 'deleted', 'is_permanent' => false],
            ['activity_type' => 'registered', 'is_permanent' => true],
        ]);

        $this->assertSame([
            'releases' => 3,
            'releases_today' => 2,
            'users' => 2,
            'users_today' => 1,
            'groups' => 3,
            'active_groups' => 2,
            'failed' => 3,
            'reported' => 2,
            'soft_deleted_users' => 1,
            'permanently_deleted_users' => 1,
            'disk_free' => '100 GB',
        ], $this->snapshot->get()['stats']);
    }

    public function test_dashboard_returns_zero_counts_for_empty_tables(): void
    {
        $stats = $this->snapshot->get()['stats'];
        unset($stats['disk_free']);

        $this->assertSame(array_fill_keys(array_keys($stats), 0), $stats);
    }

    public function test_dashboard_reuses_its_snapshot_for_fifteen_minutes_without_queries(): void
    {
        $first = $this->snapshot->get();
        $this->insertRelease(10);
        $this->travel(899)->seconds();
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->assertSame($first, $this->snapshot->get());
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_dashboard_defers_rebuilding_during_the_sixty_second_stale_grace(): void
    {
        $first = $this->snapshot->get();
        $this->insertRelease(10);
        $this->travel(900)->seconds();

        $this->assertSame($first, $this->snapshot->get());

        $this->app->make(DeferredCallbackCollection::class)->invoke();

        $refreshed = $this->snapshot->get();
        $this->assertSame(1, $refreshed['stats']['releases']);
        $this->assertSame(now()->toIso8601String(), $refreshed['generated_at']);
    }

    public function test_dashboard_rebuilds_immediately_after_the_stale_grace_expires(): void
    {
        $this->snapshot->get();
        $this->insertRelease(10);
        $this->travel(961)->seconds();

        $refreshed = $this->snapshot->get();

        $this->assertSame(1, $refreshed['stats']['releases']);
        $this->assertSame(now()->toIso8601String(), $refreshed['generated_at']);
    }

    public function test_dashboard_warmer_rebuilds_and_reuses_the_new_snapshot(): void
    {
        $this->snapshot->get();
        $this->insertRelease(10);
        $this->travel(15)->minutes();

        $this->artisan('admin:warm-dashboard')->assertSuccessful();

        $warmed = $this->snapshot->get();
        $this->assertSame(1, $warmed['stats']['releases']);
        $this->assertSame(now()->toIso8601String(), $warmed['generated_at']);

        $this->insertRelease(20);
        $this->travel(899)->seconds();
        $this->assertSame($warmed, $this->snapshot->get());
    }

    public function test_dashboard_ignores_old_estimates_and_rebuilds_after_invalidation(): void
    {
        Cache::put('admin:dashboard:snapshot', ['stats' => ['releases' => 999]], 600);
        $this->assertSame(0, $this->snapshot->get()['stats']['releases']);

        $this->insertRelease(10);
        Cache::forget(AdminDashboardSnapshotService::CACHE_KEY);

        $this->assertSame(1, $this->snapshot->get()['stats']['releases']);
    }

    public function test_initial_page_and_json_display_the_cached_snapshot_time(): void
    {
        $snapshot = $this->snapshot->get();
        $snapshotTime = now()->format('H:i:s');
        $this->travel(5)->minutes();

        /** @var AdminPageController&Mockery\MockInterface $controller */
        $controller = Mockery::mock(AdminPageController::class.'[setAdminPrefs]', [
            app(UserStatsService::class),
            app(SystemMetricsService::class),
            app(RegistrationStatusService::class),
            app(SiteStatusService::class),
            $this->snapshot,
        ]);
        $controller->shouldReceive('setAdminPrefs')->once();

        $view = $controller->index(app(GrafanaEmbedService::class));
        $data = $controller->getDashboardData()->getData(true);

        $this->assertSame($snapshotTime, $view->getData()['dashboardLastRefreshedAt']);
        $this->assertSame($snapshotTime, $data['generated_at_time']);
        $this->assertSame($snapshot['stats'], $view->getData()['stats']);
        $this->assertSame($snapshot['stats'], $data['stats']);
    }

    private function insertRelease(int $id, ?string $adddate = null): void
    {
        DB::table('releases')->insert(Arr::only(Release::factory()->raw([
            'id' => $id,
            'adddate' => $adddate ?? now()->toDateTimeString(),
        ]), ['id', 'adddate']));
    }

    private function insertUser(int $id, ?string $createdAt = null, ?string $deletedAt = null): void
    {
        DB::table('users')->insert(Arr::only(User::factory()->raw([
            'id' => $id,
            'created_at' => $createdAt ?? now()->toDateTimeString(),
            'deleted_at' => $deletedAt,
        ]), ['id', 'created_at', 'deleted_at']));
    }

    private function createSchema(): void
    {
        Schema::create('settings', static function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->text('value')->nullable();
        });
        Schema::create('releases', static function (Blueprint $table): void {
            $table->id();
            $table->dateTime('adddate');
        });
        Schema::create('users', static function (Blueprint $table): void {
            $table->id();
            $table->dateTime('created_at');
            $table->softDeletes();
        });
        Schema::create('usenet_groups', static function (Blueprint $table): void {
            $table->id();
            $table->boolean('active');
        });
        Schema::create('dnzb_failures', static function (Blueprint $table): void {
            $table->unsignedBigInteger('release_id');
            $table->unsignedBigInteger('users_id');
            $table->integer('failed');
            $table->primary(['release_id', 'users_id']);
        });
        Schema::create('release_reports', static function (Blueprint $table): void {
            $table->id();
            $table->string('status');
        });
        Schema::create('user_activities', static function (Blueprint $table): void {
            $table->id();
            $table->string('activity_type');
            $table->boolean('is_permanent');
            $table->string('description')->nullable();
            $table->string('username')->nullable();
            $table->text('metadata')->nullable();
            $table->dateTime('created_at')->nullable();
        });
        Schema::create('payments', static function (Blueprint $table): void {
            $table->id();
            $table->dateTime('created_at')->nullable();
            foreach (['username', 'email', 'item_description', 'invoice_amount', 'payment_method', 'payment_status', 'invoice_status', 'order_id'] as $column) {
                $table->string($column)->nullable();
            }
        });
    }
}
