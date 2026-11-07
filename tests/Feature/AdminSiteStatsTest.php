<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\Google2FAMiddleware;
use App\Models\DownloadStat;
use App\Models\ReleaseStat;
use App\Models\User;
use App\Services\Admin\SiteStatsReport;
use App\View\Composers\GlobalDataComposer;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminSiteStatsTest extends TestCase
{
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
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    #[Test]
    public function the_page_explains_when_nothing_has_been_collected(): void
    {
        DB::table('role_stats')->delete();

        $this->actingAs($this->admin())->get(route('admin.site-stats'))
            ->assertOk()
            ->assertSee('No statistics available')
            ->assertSee('nntmux:collect-stats');
    }

    #[Test]
    public function the_page_summarises_every_collected_statistic(): void
    {
        $this->seedStats();

        $this->actingAs($this->admin())->get(route('admin.site-stats'))
            ->assertOk()
            ->assertSee('New users this month')
            ->assertSee('Most active: Movies &gt; HD', false)
            ->assertSee('Releases added, last 7 days')
            ->assertSee('Signups, last 12 months')
            ->assertSee('Top grabbers')
            ->assertSee('Most downloaded releases')
            ->assertSee('href="'.url('/details/'.str_repeat('d', 32)).'"', false)
            ->assertSee('style="width: 75%"', false)
            ->assertSee('Updated');
    }

    #[Test]
    public function the_report_computes_totals_shares_and_a_twelve_month_series(): void
    {
        $this->seedStats();

        $report = app(SiteStatsReport::class)->build(CarbonImmutable::parse('2026-11-07'));

        $this->assertSame(12, $report['kpis']['total_users']);
        $this->assertSame(4, $report['kpis']['signups_this_month']);
        $this->assertSame(2, $report['kpis']['signups_last_month']);
        $this->assertSame(40, $report['kpis']['releases_last_week']);
        $this->assertSame(['Movies', 'TV'], array_column($report['parents'], 'label'));
        $this->assertSame([87.5, 12.5], array_column($report['parents'], 'share'));
        $this->assertSame(75.0, $report['categories'][0]['share']);
        $this->assertCount(12, $report['signups']);
        $this->assertSame('Dec', $report['signups'][0]['label']);
        $this->assertSame(['Nov', 4, 100.0], [$report['signups'][11]['label'], $report['signups'][11]['count'], $report['signups'][11]['height']]);
        $this->assertSame(0, $report['signups'][5]['count'], 'Months without signups appear as zero');
        $this->assertSame(36, $report['signups_total']);
        $this->assertSame([100.0, 50.0], array_column($report['grabbers'], 'bar'));
        $this->assertFalse($report['is_empty']);
    }

    #[Test]
    public function collectors_drop_stale_categories_and_duplicate_downloads(): void
    {
        DB::table('root_categories')->insert(['id' => 2000, 'title' => 'Movies']);
        DB::table('categories')->insert(['id' => 2040, 'title' => 'HD', 'root_categories_id' => 2000]);
        DB::table('releases')->insert([
            ['id' => 1, 'categories_id' => 2040, 'searchname' => 'Popular.Release', 'guid' => str_repeat('a', 32), 'adddate' => now()->subDay(), 'grabs' => 9],
            ['id' => 2, 'categories_id' => 2040, 'searchname' => 'Other.Release', 'guid' => str_repeat('b', 32), 'adddate' => now()->subDays(2), 'grabs' => 3],
        ]);
        DB::table('release_stats')->insert(['category' => 'TV > SD', 'count' => 99]);
        DB::table('download_stats')->insert([
            ['searchname' => 'Popular.Release', 'guid' => str_repeat('a', 32), 'adddate' => now()->subDay(), 'grabs' => 5],
            ['searchname' => 'Popular.Release', 'guid' => str_repeat('a', 32), 'adddate' => now()->subDay(), 'grabs' => 7],
        ]);

        ReleaseStat::insertRecentlyAdded();
        DownloadStat::insertTopDownloads();

        $this->assertSame([['category' => 'Movies > HD', 'count' => 2]], ReleaseStat::getRecentlyAdded());
        $this->assertSame([[str_repeat('a', 32), 9], [str_repeat('b', 32), 3]], array_map(
            static fn (array $row): array => [$row['guid'], (int) $row['grabs']],
            DownloadStat::getTopDownloads(),
        ));
    }

    private function seedStats(): void
    {
        $now = now();
        DB::table('role_stats')->insert([
            ['role' => 'Admin', 'users' => 2, 'updated_at' => $now],
            ['role' => 'User', 'users' => 10, 'updated_at' => $now],
        ]);
        DB::table('release_stats')->insert([
            ['category' => 'Movies > HD', 'count' => 30, 'updated_at' => $now],
            ['category' => 'TV > HD', 'count' => 5, 'updated_at' => $now],
            ['category' => 'Movies > UHD', 'count' => 5, 'updated_at' => $now],
        ]);
        DB::table('signup_stats')->insert([
            ['month' => 'November 2026', 'sort_date' => '2026-11-01', 'signups' => 4, 'updated_at' => $now],
            ['month' => 'October 2026', 'sort_date' => '2026-10-01', 'signups' => 2, 'updated_at' => $now],
            ['month' => 'January 2026', 'sort_date' => '2026-01-01', 'signups' => 3, 'updated_at' => $now],
            ['month' => 'March 2024', 'sort_date' => '2024-03-01', 'signups' => 27, 'updated_at' => $now],
        ]);
        DB::table('grab_stats')->insert([
            ['username' => 'alice', 'grabs' => 40, 'updated_at' => $now],
            ['username' => 'bob', 'grabs' => 20, 'updated_at' => $now],
        ]);
        DB::table('download_stats')->insert([
            ['searchname' => 'Most.Wanted.Release', 'guid' => str_repeat('d', 32), 'adddate' => $now->copy()->subDays(3), 'grabs' => 12, 'updated_at' => $now],
        ]);
    }

    private function admin(): Authenticatable
    {
        $role = Role::query()->firstOrCreate(['name' => 'Admin', 'guard_name' => 'web'], ['rate_limit' => 60]);

        /** @var User $user */
        $user = User::query()->create([
            'username' => 'admin_'.Str::random(6),
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
        Schema::create('releases', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('categories_id');
            $table->string('searchname');
            $table->string('guid');
            $table->timestamp('adddate')->nullable();
            $table->integer('grabs')->default(0);
        });
        Schema::create('grab_stats', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username');
            $table->integer('grabs');
            $table->timestamps();
        });
        Schema::create('release_stats', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('category');
            $table->integer('count');
            $table->timestamps();
        });
        Schema::create('signup_stats', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('month');
            $table->date('sort_date')->nullable();
            $table->integer('signups');
            $table->timestamps();
        });
        Schema::create('role_stats', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('role');
            $table->integer('users');
            $table->timestamps();
        });
        Schema::create('download_stats', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('searchname');
            $table->string('guid');
            $table->timestamp('adddate')->nullable();
            $table->integer('grabs');
            $table->timestamps();
        });
    }
}
