<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class AdminDashboardGrafanaEmbedTest extends TestCase
{
    public function test_panels_partial_renders_token_free_iframes_and_a_monitoring_link(): void
    {
        $html = view('admin.partials.grafana-panels', [
            'grafanaPanels' => [
                ['title' => 'CPU usage', 'url' => '/grafana/d-solo/nntmux-host/nntmux-host?orgId=1&panelId=1'],
                ['title' => 'Processing backlog', 'url' => '/grafana/d-solo/nntmux-processing/nntmux-processing?orgId=1&panelId=2'],
            ],
        ])->render();

        $this->assertStringContainsString('x-data="grafanaPanels"', $html);
        $this->assertStringContainsString('data-token-url="'.route('admin.monitoring.token').'"', $html);
        $this->assertSame(2, substr_count($html, '<iframe'));
        $this->assertStringContainsString('data-src-base="/grafana/d-solo/nntmux-host/nntmux-host?orgId=1&amp;panelId=1"', $html);
        $this->assertStringContainsString('href="'.route('admin.monitoring').'"', $html);
        $this->assertStringNotContainsString('auth_token', $html);
    }

    public function test_dashboard_keeps_the_chart_js_system_resources_as_the_fallback(): void
    {
        $content = (string) file_get_contents(resource_path('views/admin/dashboard.blade.php'));

        $embed = strpos($content, "@include('admin.partials.grafana-panels')");
        $else = strpos($content, '@else', (int) $embed);
        $fallbackChart = strpos($content, 'id="cpuHistory24hChart"');

        $this->assertNotFalse($embed);
        $this->assertStringContainsString('@if(!empty($grafanaPanels))', $content);
        $this->assertNotFalse($else);
        $this->assertGreaterThan($else, $fallbackChart, 'The Chart.js CPU history must stay in the @else branch.');
    }

    public function test_grafana_components_are_registered_with_the_lazy_loader(): void
    {
        $loader = (string) file_get_contents(resource_path('js/alpine/lazy-loader.js'));

        $this->assertStringContainsString("'adminMonitoring': () => import('./components/admin/monitoring.js')", $loader);
        $this->assertStringContainsString("'grafanaPanels':   () => import('./components/admin/monitoring.js')", $loader);
    }
}
