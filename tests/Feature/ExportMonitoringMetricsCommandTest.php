<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ServiceStatusEnum;
use App\Services\Monitoring\Collectors\MetricsCollector;
use App\Services\Monitoring\TmuxMetricsSnapshot;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Monitoring\FailingMetricsCollector;
use Tests\TestCase;

class ExportMonitoringMetricsCommandTest extends TestCase
{
    private const string PUSH_URL = 'http://pushgateway.test:9091/metrics/job/nntmux';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'queue.failed.driver' => 'database-uuids',
            'queue.failed.database' => 'sqlite',
            'monitoring.enabled' => true,
            'monitoring.pushgateway.url' => 'http://pushgateway.test:9091',
        ]);

        DB::purge();
        DB::reconnect();
        Cache::flush();
        $this->createSchema();
    }

    public function test_pushes_tmux_snapshot_service_and_failed_job_metrics(): void
    {
        Http::fake([self::PUSH_URL => Http::response('', 200)]);
        app(TmuxMetricsSnapshot::class)->publish([
            'settings' => ['is_running' => 1],
            'counts' => ['now' => ['processnfo' => 42, 'collections_table' => 7, 'releases' => 1000, 'total_work' => 42]],
            'conncounts' => ['primary' => ['active' => 3, 'total' => 10]],
        ]);
        DB::table('service_statuses')->insert([
            'name' => 'Database', 'slug' => 'database', 'check_type' => 'probe', 'status' => ServiceStatusEnum::MajorOutage->value,
            'uptime_percentage' => 99.5, 'response_time_ms' => 250, 'is_enabled' => true, 'sort_order' => 1,
        ]);
        DB::table('failed_jobs')->insert(['uuid' => 'a', 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()]);

        $this->artisan('monitoring:export-metrics')->assertSuccessful();

        Http::assertSent(function (Request $request): bool {
            $body = $request->body();

            return $request->method() === 'PUT'
                && $request->url() === self::PUSH_URL
                && str_starts_with($request->header('Content-Type')[0] ?? '', 'text/plain; version=0.0.4')
                && str_contains($body, "nntmux_up 1\n")
                && str_contains($body, 'nntmux_processing_backlog{type="nfo"} 42')
                && str_contains($body, 'nntmux_table_rows{table="collections"} 7')
                && str_contains($body, 'nntmux_nntp_connections{server="primary",state="active"} 3')
                && str_contains($body, 'nntmux_service_up{service="database"} 0')
                && str_contains($body, 'nntmux_service_response_time_seconds{service="database"} 0.25')
                && str_contains($body, 'nntmux_failed_jobs 1')
                && str_contains($body, 'nntmux_metrics_collector_errors{collector="ServiceStatusCollector"} 0');
        });
    }

    public function test_a_failing_collector_is_reported_and_the_rest_are_still_pushed(): void
    {
        Http::fake([self::PUSH_URL => Http::response('', 200)]);
        $this->app->tag([FailingMetricsCollector::class], MetricsCollector::TAG);

        $this->artisan('monitoring:export-metrics')->assertSuccessful();

        Http::assertSent(static fn (Request $request): bool => str_contains($request->body(), 'nntmux_metrics_collector_errors{collector="FailingMetricsCollector"} 1')
            && str_contains($request->body(), "nntmux_up 1\n")
            && ! str_contains($request->body(), 'nntmux_partial'));
    }

    public function test_fails_cleanly_when_the_pushgateway_is_unreachable(): void
    {
        Http::fake([self::PUSH_URL => static fn () => throw new ConnectionException('Connection refused')]);

        $this->artisan('monitoring:export-metrics')->assertFailed();
    }

    public function test_fails_when_the_pushgateway_rejects_the_metrics(): void
    {
        Http::fake([self::PUSH_URL => Http::response('text format parsing error', 400)]);

        $this->artisan('monitoring:export-metrics')->assertFailed();
    }

    public function test_does_nothing_when_monitoring_is_disabled(): void
    {
        config(['monitoring.enabled' => false]);
        Http::fake();

        $this->artisan('monitoring:export-metrics')->assertSuccessful();

        Http::assertNothingSent();
    }

    private function createSchema(): void
    {
        Schema::create('service_statuses', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('endpoint_url')->nullable();
            $table->string('check_type');
            $table->string('probe_identifier')->nullable();
            $table->string('status');
            $table->timestamp('last_checked_at')->nullable();
            $table->decimal('uptime_percentage', 5, 2)->default(100);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('failed_jobs', static function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }
}
