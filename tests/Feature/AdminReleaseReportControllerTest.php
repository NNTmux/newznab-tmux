<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminReleaseReportControllerTest extends TestCase
{
    /**
     * Test that the controller has the revert method.
     */
    public function test_controller_has_revert_method(): void
    {
        $controllerPath = app_path('Http/Controllers/Admin/AdminReleaseReportController.php');

        $this->assertFileExists($controllerPath);

        $content = file_get_contents($controllerPath);

        // Check that the controller contains the revert method
        $this->assertStringContainsString('public function revert', $content);
        $this->assertStringContainsString("'resolved', 'dismissed'", $content);
        $this->assertStringContainsString('Report reverted to reviewed status', $content);
    }

    /**
     * Test that bulk action supports revert option.
     */
    public function test_bulk_action_supports_revert(): void
    {
        $controllerPath = app_path('Http/Controllers/Admin/AdminReleaseReportController.php');

        $this->assertFileExists($controllerPath);

        $content = file_get_contents($controllerPath);

        // Check that bulk action validation includes revert
        $this->assertStringContainsString("'action' => 'required|in:dismiss,resolve,reviewed,delete,revert'", $content);
        $this->assertStringContainsString("'revert' => 'reverted to reviewed'", $content);
    }

    /**
     * Test that the revert route is defined.
     */
    public function test_revert_route_is_defined(): void
    {
        $routesPath = base_path('routes/web.php');

        $this->assertFileExists($routesPath);

        $content = file_get_contents($routesPath);

        // Check that the revert route exists
        $this->assertStringContainsString('release-reports/{id}/revert', $content);
        $this->assertStringContainsString("'revert'])->name('admin.release-reports.revert')", $content);
    }

    /**
     * Test that the admin view includes revert button for resolved/dismissed reports.
     */
    public function test_admin_view_has_revert_button(): void
    {
        $viewPath = resource_path('views/admin/release-reports/index.blade.php');

        $this->assertFileExists($viewPath);

        $content = file_get_contents($viewPath);

        // Check that the view contains the revert button with proper data attributes
        $this->assertStringContainsString('revert-report-btn', $content);
        $this->assertStringContainsString('data-action-url', $content);
        $this->assertStringContainsString('admin.release-reports.revert', $content);
        $this->assertStringContainsString('fa-undo', $content);
        $this->assertStringContainsString('Revert', $content);
        $this->assertStringContainsString("in_array(\$report->status, ['resolved', 'dismissed'])", $content);
    }

    /**
     * Test that the admin view has revert confirmation modal.
     */
    public function test_admin_view_has_revert_confirmation_modal(): void
    {
        $viewPath = resource_path('views/admin/release-reports/index.blade.php');

        $this->assertFileExists($viewPath);

        $content = file_get_contents($viewPath);

        // Check that the view contains the revert confirmation modal
        $this->assertStringContainsString('revertConfirmModal', $content);
        $this->assertStringContainsString('revertConfirmForm', $content);
        $this->assertStringContainsString('Confirm Revert', $content);
        $this->assertStringContainsString('revert-modal-close', $content);
    }

    /**
     * Test that bulk action dropdown includes revert option.
     */
    public function test_bulk_action_dropdown_has_revert_option(): void
    {
        $viewPath = resource_path('views/admin/release-reports/index.blade.php');

        $this->assertFileExists($viewPath);

        $content = file_get_contents($viewPath);

        // Check that the bulk action dropdown contains revert option
        $this->assertStringContainsString('<option value="revert">Revert to Reviewed</option>', $content);
    }

    /**
     * Test that ReleaseBrowseService includes resolved status in report query.
     */
    public function test_browse_service_includes_resolved_status(): void
    {
        $servicePath = app_path('Services/Releases/ReleaseBrowseService.php');

        $this->assertFileExists($servicePath);

        $content = file_get_contents($servicePath);

        // Check that the query includes resolved status
        $this->assertStringContainsString("WHERE status IN ('pending', 'reviewed', 'resolved')", $content);
    }

    /**
     * Test that ReleaseSearchService includes resolved status in report query.
     */
    public function test_search_service_includes_resolved_status(): void
    {
        $servicePath = app_path('Services/Releases/ReleaseSearchService.php');

        $this->assertFileExists($servicePath);

        $content = file_get_contents($servicePath);

        // Check that the query includes resolved status
        $this->assertStringContainsString("WHERE status IN ('pending', 'reviewed', 'resolved')", $content);
    }

    /**
     * Test that ReleaseBrowseService includes resolved status in report query.
     */
    public function test_release_browse_service_includes_resolved_status(): void
    {
        $servicePath = app_path('Services/Releases/ReleaseBrowseService.php');

        $this->assertFileExists($servicePath);

        $content = file_get_contents($servicePath);

        // Check that the query includes resolved status
        $this->assertStringContainsString("WHERE status IN ('pending', 'reviewed', 'resolved')", $content);
    }

    /**
     * Test that DetailsController includes resolved status in report query.
     */
    public function test_details_controller_includes_resolved_status(): void
    {
        $controllerPath = app_path('Http/Controllers/DetailsController.php');

        $this->assertFileExists($controllerPath);

        $content = file_get_contents($controllerPath);

        // Check that the query includes resolved status
        $this->assertStringContainsString("whereIn('status', ['pending', 'reviewed', 'resolved'])", $content);
    }
}
