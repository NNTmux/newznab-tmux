<?php

declare(strict_types=1);

namespace Tests\Unit\Monitoring;

use App\Services\Monitoring\GrafanaEmbedService;
use App\Services\Monitoring\GrafanaJwtIssuer;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GrafanaEmbedServiceTest extends TestCase
{
    public function test_builds_kiosk_dashboard_and_solo_panel_urls_without_tokens(): void
    {
        config(['monitoring.grafana.url' => '/grafana']);
        $service = $this->service(keyUsable: true);

        $this->assertSame(
            '/grafana/d/nntmux-host/nntmux-host?orgId=1&from=now-6h&to=now&refresh=1m&kiosk',
            $service->dashboardUrl('host'),
        );
        $this->assertSame(
            '/grafana/d-solo/nntmux-processing/nntmux-processing?orgId=1&from=now-6h&to=now&refresh=1m&panelId=2',
            $service->panelUrl('processing', 2),
        );
        $this->assertStringNotContainsString('auth_token', $service->dashboardUrl('services', kiosk: false));
    }

    public function test_dashboard_panels_follow_the_configured_list_when_enabled(): void
    {
        config(['monitoring.enabled' => true]);

        $panels = $this->service(keyUsable: true)->dashboardPanels();

        $this->assertCount(count(config('monitoring.grafana.dashboard_panels')), $panels);
        $this->assertSame('CPU usage', $panels[0]['title']);
        $this->assertStringContainsString('/d-solo/nntmux-host/', $panels[0]['url']);
    }

    public function test_is_disabled_without_the_flag_or_a_usable_key(): void
    {
        config(['monitoring.enabled' => false]);
        $this->assertFalse($this->service(keyUsable: true)->isEnabled());
        $this->assertSame([], $this->service(keyUsable: true)->dashboardPanels());

        config(['monitoring.enabled' => true]);
        $this->assertFalse($this->service(keyUsable: false)->isEnabled());
    }

    /**
     * @return array<string, array{?string, ?string, ?string}>
     */
    public static function frameOriginProvider(): array
    {
        return [
            'relative path' => ['/grafana', 'https://nntmux.example', null],
            'same origin absolute' => ['https://nntmux.example/grafana', 'https://nntmux.example', null],
            'other host' => ['https://grafana.example/grafana', 'https://nntmux.example', 'https://grafana.example'],
            'other port' => ['http://nntmux.example:3000', 'http://nntmux.example', 'http://nntmux.example:3000'],
            'unset' => [null, 'https://nntmux.example', null],
        ];
    }

    #[DataProvider('frameOriginProvider')]
    public function test_frame_origin_is_only_set_for_cross_origin_grafana(?string $grafanaUrl, ?string $appUrl, ?string $expected): void
    {
        $this->assertSame($expected, GrafanaEmbedService::frameOrigin($grafanaUrl, $appUrl));
    }

    private function service(bool $keyUsable): GrafanaEmbedService
    {
        $issuer = Mockery::mock(GrafanaJwtIssuer::class);
        $issuer->allows('hasUsableKey')->andReturn($keyUsable);

        return new GrafanaEmbedService($issuer);
    }
}
