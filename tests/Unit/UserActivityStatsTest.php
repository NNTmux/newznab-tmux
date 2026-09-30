<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\UserActivityStat;
use App\Services\UserStatsService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class UserActivityStatsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
        ]);
        DB::purge();
        DB::reconnect();
        Cache::flush();
        Carbon::setTestNow('2026-07-20 14:37:00');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->dateTime('deleted_at')->nullable();
        });
        Schema::create('user_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('users_id');
            $table->text('request');
            $table->dateTime('timestamp');
        });
        Schema::create('user_downloads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('users_id');
            $table->dateTime('timestamp');
        });
        Schema::create('user_activity_stats', function (Blueprint $table): void {
            $table->id();
            $table->date('stat_date')->unique();
            $table->integer('downloads_count')->default(0);
            $table->integer('api_hits_count')->default(0);
            $table->timestamps();
        });
        Schema::create('user_activity_stats_hourly', function (Blueprint $table): void {
            $table->id();
            $table->dateTime('stat_hour')->unique();
            $table->integer('downloads_count')->default(0);
            $table->integer('api_hits_count')->default(0);
            $table->timestamps();
        });

        DB::table('users')->insert(['id' => 1]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_summary_and_charts_combine_hourly_api_and_rss_hits_with_the_live_hour(): void
    {
        $this->insertHourly('2026-07-14 09:00:00', 3, 7);
        $this->insertHourly('2026-07-20 13:00:00', 2, 4);

        DB::table('user_activity_stats')->insert([
            'stat_date' => '2026-07-19',
            'downloads_count' => 999,
            'api_hits_count' => 999,
        ]);
        DB::table('user_requests')->insert([
            ['users_id' => 1, 'request' => '/api/v2/search?id=one', 'timestamp' => '2026-07-20 14:37:00'],
            ['users_id' => 1, 'request' => '/rss/full-feed?api_token=test', 'timestamp' => '2026-07-20 14:37:30'],
        ]);
        DB::table('user_downloads')->insert([
            'users_id' => 1,
            'timestamp' => '2026-07-20 14:36:00',
        ]);

        $service = new UserStatsService;
        $summary = $service->getSummaryStats();

        $this->assertSame(6, $summary['api_hits_today']);
        $this->assertSame(13, $summary['api_hits_week']);
        $this->assertSame(3, $summary['downloads_today']);
        $this->assertSame(6, $summary['downloads_week']);

        $hourly = $service->getApiHitsPerHour(2);
        $this->assertSame([4, 2], array_column($hourly, 'count'));

        $daily = $service->getApiHitsPerDay(7);
        $this->assertSame(7, $daily[0]['count']);
        $this->assertSame(6, $daily[6]['count']);

        $perMinute = $service->getApiHitsPerMinute(60);
        $this->assertSame(2, $perMinute[59]['count']);
    }

    public function test_daily_rollup_remains_stable_after_raw_rows_are_pruned(): void
    {
        $this->insertHourly('2026-07-19 01:00:00', 2, 3);
        $this->insertHourly('2026-07-19 23:00:00', 4, 5);
        DB::table('user_requests')->insert([
            'users_id' => 1,
            'request' => '/api/v1/api?t=search',
            'timestamp' => '2026-07-19 23:30:00',
        ]);

        UserActivityStat::collectDailyStats('2026-07-19');
        DB::table('user_requests')->delete();
        UserActivityStat::collectDailyStats('2026-07-19');

        $daily = DB::table('user_activity_stats')->where('stat_date', '2026-07-19')->first();
        $this->assertSame(6, $daily->downloads_count);
        $this->assertSame(8, $daily->api_hits_count);
    }

    public function test_forced_daily_backfill_rebuilds_retained_history_from_hourly_totals(): void
    {
        $this->insertHourly('2026-07-19 10:00:00', 11, 17);
        DB::table('user_activity_stats')->insert([
            'stat_date' => '2026-07-19',
            'downloads_count' => 1,
            'api_hits_count' => 1,
        ]);

        $this->artisan('nntmux:backfill-user-activity-stats', [
            '--type' => 'daily',
            '--days' => 2,
            '--force' => true,
        ])->assertSuccessful();

        $daily = DB::table('user_activity_stats')->where('stat_date', '2026-07-19')->first();
        $this->assertSame(11, $daily->downloads_count);
        $this->assertSame(17, $daily->api_hits_count);
    }

    private function insertHourly(string $hour, int $downloads, int $apiHits): void
    {
        DB::table('user_activity_stats_hourly')->insert([
            'stat_hour' => $hour,
            'downloads_count' => $downloads,
            'api_hits_count' => $apiHits,
        ]);
    }
}
