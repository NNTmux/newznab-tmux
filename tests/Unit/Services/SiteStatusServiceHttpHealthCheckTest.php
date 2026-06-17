<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\IncidentImpactEnum;
use App\Models\ServiceStatus;
use App\Services\SiteStatusService;
use App\Services\StatusProbes\ServiceProbeRegistry;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SiteStatusServiceHttpHealthCheckTest extends TestCase
{
    public function test_authenticated_rss_feed_url_400_is_reported_as_unexpected_response(): void
    {
        config(['app.url' => 'https://example.test']);

        Http::fake([
            'https://example.test/rss/full-feed' => Http::response(['error' => 'Missing parameter (api_token)'], 400),
        ]);

        $result = $this->statusService()->checkServiceHealth(new ServiceStatus([
            'name' => 'RSS',
            'slug' => 'rss',
            'endpoint_url' => '/rss/full-feed',
            'check_type' => 'http',
        ]));

        $this->assertFalse($result['ok']);
        $this->assertSame(400, $result['status_code']);
        $this->assertSame(IncidentImpactEnum::Major, $result['impact']);
        $this->assertSame('Unexpected response (HTTP 400)', $result['reason']);
    }

    public function test_public_rss_health_endpoint_is_reported_as_healthy(): void
    {
        config(['app.url' => 'https://example.test']);

        Http::fake([
            'https://example.test/rss/health' => Http::response(['status' => 'ok', 'service' => 'rss'], 200),
        ]);

        $result = $this->statusService()->checkServiceHealth(new ServiceStatus([
            'name' => 'RSS',
            'slug' => 'rss',
            'endpoint_url' => '/rss/health',
            'check_type' => 'http',
        ]));

        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['status_code']);
        $this->assertNull($result['impact']);
        $this->assertSame('OK', $result['reason']);
    }

    private function statusService(): SiteStatusService
    {
        return new SiteStatusService($this->createMock(ServiceProbeRegistry::class));
    }
}
