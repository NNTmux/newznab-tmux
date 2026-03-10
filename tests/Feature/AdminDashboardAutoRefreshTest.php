<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminDashboardAutoRefreshTest extends TestCase
{
    public function test_dashboard_blade_has_auto_refresh_container(): void
    {
        $bladePath = resource_path('views/admin/dashboard.blade.php');

        $this->assertFileExists($bladePath);

        $content = file_get_contents($bladePath);

        $this->assertStringContainsString('x-data="adminDashboard"', $content);
        $this->assertStringContainsString("data-refresh-url=\"{{ route('admin.index') }}\"", $content);
        $this->assertStringContainsString('data-refresh-interval="{{ 15 * 60 * 1000 }}"', $content);
        $this->assertStringContainsString('data-dashboard-content', $content);
    }

    public function test_dashboard_blade_labels_match_fifteen_minute_refresh(): void
    {
        $bladePath = resource_path('views/admin/dashboard.blade.php');

        $this->assertFileExists($bladePath);

        $content = file_get_contents($bladePath);

        $this->assertStringContainsString('Auto-refreshes every 15 minutes', $content);
        $this->assertStringContainsString('Last dashboard refresh:', $content);
        $this->assertStringContainsString('$dashboardLastRefreshedAt', $content);
        $this->assertStringNotContainsString('@php($dashboardLastRefreshedAt', $content);
        $this->assertStringNotContainsString('Auto-refreshes every 20 minutes', $content);
        $this->assertStringNotContainsString('Auto-updates every minute', $content);
    }

    public function test_dashboard_component_refreshes_widgets_without_page_reload(): void
    {
        $scriptPath = resource_path('js/alpine/components/admin/dashboard.js');

        $this->assertFileExists($scriptPath);

        $content = file_get_contents($scriptPath);

        $this->assertStringContainsString('_refreshDashboard(url)', $content);
        $this->assertStringContainsString('DOMParser().parseFromString', $content);
        $this->assertStringContainsString('#adminDashboard [data-dashboard-content]', $content);
        $this->assertStringContainsString('currentContent.innerHTML = nextContent.innerHTML', $content);
        $this->assertStringContainsString('15 * 60 * 1000', $content);
    }
}
