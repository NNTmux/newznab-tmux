<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminDashboardReportedReleasesTest extends TestCase
{
    /**
     * Test that the dashboard blade template contains reported releases widget markup.
     */
    public function test_dashboard_blade_contains_reported_releases_widget(): void
    {
        $bladePath = resource_path('views/admin/dashboard.blade.php');

        $this->assertFileExists($bladePath);

        $content = file_get_contents($bladePath);

        // Check that the blade file contains the reported releases widget
        $this->assertStringContainsString('Reported Releases', $content);
        $this->assertStringContainsString("stats['reported']", $content);
        $this->assertStringContainsString('/admin/release-reports', $content);
        $this->assertStringContainsString('View reports', $content);
    }

    /**
     * Test that the AdminPageController contains the reported count in stats.
     */
    public function test_controller_stats_method_has_reported_key(): void
    {
        $controllerPath = app_path('Http/Controllers/Admin/AdminPageController.php');

        $this->assertFileExists($controllerPath);

        $content = file_get_contents($controllerPath);

        // Check that the controller contains the reported releases count
        $this->assertStringContainsString("'reported'", $content);
        $this->assertStringContainsString('admin_stats_reported_count', $content);
        $this->assertStringContainsString('ReleaseReport::where', $content);
    }
}
