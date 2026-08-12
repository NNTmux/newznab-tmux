<?php

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use PDO;
use Tests\TestCase;

class AdminReleaseReportControllerTest extends TestCase
{
    private string $databasePath = '';

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = $this->makeTempPath('nntmux-admin-release-report-test', '.sqlite');

        $this->originalEnvironment = [
            'APP_ENV' => getenv('APP_ENV'),
            'DB_CONNECTION' => getenv('DB_CONNECTION'),
            'DB_DATABASE' => getenv('DB_DATABASE'),
        ];

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        $pdo = new PDO('sqlite:'.$this->databasePath);
        $pdo->exec('CREATE TABLE settings (name VARCHAR PRIMARY KEY, value TEXT NULL)');
        $pdo->exec("INSERT INTO settings (name, value) VALUES
            ('categorizeforeign', '0'),
            ('catwebdl', '0'),
            ('innerfileblacklist', ''),
            ('title', 'NNTmux Test'),
            ('home_link', '/')");

        $this->setEnvironmentValue('APP_ENV', 'testing');
        $this->setEnvironmentValue('DB_CONNECTION', 'sqlite');
        $this->setEnvironmentValue('DB_DATABASE', $this->databasePath);

        $app = require __DIR__.'/../../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function tearDown(): void
    {
        if ($this->databasePath !== '' && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        foreach ($this->originalEnvironment as $key => $value) {
            $this->setEnvironmentValue($key, $value === false ? null : $value);
        }

        parent::tearDown();
    }

    private function setEnvironmentValue(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

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
        $requestPath = app_path('Http/Requests/Admin/AdminReleaseReportBulkActionRequest.php');

        $this->assertFileExists($requestPath);

        $content = file_get_contents($requestPath);

        // Check that bulk action validation includes revert
        $this->assertStringContainsString("'in:dismiss,resolve,reviewed,delete,revert'", $content);
        $this->assertStringContainsString('public function reportIds', $content);
        $this->assertStringContainsString('public function actionName', $content);

        $controllerPath = app_path('Http/Controllers/Admin/AdminReleaseReportController.php');
        $controllerContent = file_get_contents($controllerPath);
        $this->assertStringContainsString("'revert' => 'reverted to reviewed'", $controllerContent);
        $this->assertStringContainsString('bulkUpdateReportStatuses', $controllerContent);
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
     * Test that the admin view mounts the Alpine component that drives the modals.
     */
    public function test_admin_view_mounts_release_reports_component(): void
    {
        $viewPath = resource_path('views/admin/release-reports/index.blade.php');

        $this->assertFileExists($viewPath);

        $content = file_get_contents($viewPath);

        $this->assertStringContainsString('x-data="adminReleaseReports"', $content);
        $this->assertStringContainsString('@click="showDescription(', $content);
        $this->assertStringContainsString('@click="showRevert(', $content);
    }

    /**
     * Test that legacy admin feature JS no longer handles release report modals.
     */
    public function test_legacy_admin_features_js_no_longer_handles_release_reports(): void
    {
        $scriptPath = resource_path('js/alpine/components/admin/features.js');

        $this->assertFileExists($scriptPath);

        $content = file_get_contents($scriptPath);

        $this->assertStringNotContainsString('reportDescriptionModal', $content);
        $this->assertStringNotContainsString('revertConfirmModal', $content);
        $this->assertStringNotContainsString('.report-checkbox', $content);
        $this->assertStringNotContainsString('.report-description-btn', $content);
        $this->assertStringNotContainsString('.revert-report-btn', $content);
    }

    public function test_verify_user_component_is_split_from_legacy_admin_features_bundle(): void
    {
        $loaderPath = resource_path('js/alpine/lazy-loader.js');
        $featuresPath = resource_path('js/alpine/components/admin/features.js');
        $verifyPath = resource_path('js/alpine/components/admin/verify-user.js');

        $this->assertFileExists($loaderPath);
        $this->assertFileExists($featuresPath);
        $this->assertFileExists($verifyPath);

        $loaderContent = file_get_contents($loaderPath);
        $featuresContent = file_get_contents($featuresPath);
        $verifyContent = file_get_contents($verifyPath);

        $this->assertStringContainsString("'verifyUser':      () => import('./components/admin/verify-user.js')", $loaderContent);
        $this->assertStringNotContainsString("Alpine.data('verifyUser'", $featuresContent);
        $this->assertStringContainsString("Alpine.data('verifyUser'", $verifyContent);
    }

    public function test_release_report_list_uses_column_scoped_eager_loading(): void
    {
        $modelPath = app_path('Models/ReleaseReport.php');

        $this->assertFileExists($modelPath);

        $content = file_get_contents($modelPath);

        $this->assertStringContainsString("'release:id,guid,searchname,size'", $content);
        $this->assertStringContainsString("'user:id,username'", $content);
        $this->assertStringContainsString("->orderByDesc('created_at')", $content);
    }

    /**
     * Test that release report bulk-selection JS queries from the component root.
     */
    public function test_release_report_component_uses_root_scoped_selection_queries(): void
    {
        $scriptPath = resource_path('js/alpine/components/release-report.js');

        $this->assertFileExists($scriptPath);

        $content = file_get_contents($scriptPath);

        $this->assertStringContainsString('rootEl: null', $content);
        $this->assertStringContainsString('this.rootEl = this.$root;', $content);
        $this->assertStringContainsString('return root ? [...root.querySelectorAll(\'.report-checkbox\')] : [];', $content);
        $this->assertStringNotContainsString('this.$el.querySelectorAll(\'.report-checkbox\')', $content);
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
     * Test that bulk selection controls are rendered above the table and
     * row checkboxes are associated to the bulk form.
     */
    public function test_bulk_selection_controls_use_a_dedicated_bulk_form(): void
    {
        $viewPath = resource_path('views/admin/release-reports/index.blade.php');

        $this->assertFileExists($viewPath);

        $content = file_get_contents($viewPath);

        $this->assertStringContainsString('id="bulk-action-form"', $content);
        $this->assertStringContainsString('Select All', $content);
        $this->assertStringContainsString('Clear Selection', $content);
        $this->assertStringContainsString('form="bulk-action-form"', $content);
        $this->assertStringContainsString('x-text="selectedCount"', $content);
    }

    /**
     * Test that ReleaseBrowseService includes resolved status in report query.
     */
    public function test_browse_service_includes_resolved_status(): void
    {
        $servicePath = app_path('Services/Releases/ReleaseBrowseService.php');

        $this->assertFileExists($servicePath);

        $content = file_get_contents($servicePath);

        // Check that the active report count includes resolved status.
        $this->assertStringContainsString("status IN ('pending', 'reviewed', 'resolved')", $content);
    }

    /**
     * Test that ReleaseSearchService includes resolved status in report query.
     */
    public function test_search_service_includes_resolved_status(): void
    {
        $servicePath = app_path('Services/Releases/ReleaseSearchService.php');

        $this->assertFileExists($servicePath);

        $content = file_get_contents($servicePath);

        // Check that the active report count includes resolved status.
        $this->assertStringContainsString("status IN ('pending', 'reviewed', 'resolved')", $content);
    }

    /**
     * Test that ReleaseBrowseService includes resolved status in report query.
     */
    public function test_release_browse_service_includes_resolved_status(): void
    {
        $servicePath = app_path('Services/Releases/ReleaseBrowseService.php');

        $this->assertFileExists($servicePath);

        $content = file_get_contents($servicePath);

        // Check that the active report count includes resolved status.
        $this->assertStringContainsString("status IN ('pending', 'reviewed', 'resolved')", $content);
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

    /**
     * Test that release reports support staff response fields.
     */
    public function test_release_report_model_supports_response_fields(): void
    {
        $modelPath = app_path('Models/ReleaseReport.php');

        $this->assertFileExists($modelPath);

        $content = file_get_contents($modelPath);

        $this->assertStringContainsString("'response'", $content);
        $this->assertStringContainsString("'responded_by'", $content);
        $this->assertStringContainsString("'responded_at'", $content);
        $this->assertStringContainsString("'response_is_public'", $content);
        $this->assertStringContainsString('public function responder', $content);
    }

    /**
     * Test that the response route and controller method are defined.
     */
    public function test_response_route_and_controller_method_are_defined(): void
    {
        $routesPath = base_path('routes/web.php');
        $controllerPath = app_path('Http/Controllers/Admin/AdminReleaseReportController.php');

        $this->assertFileExists($routesPath);
        $this->assertFileExists($controllerPath);

        $routes = file_get_contents($routesPath);
        $controller = file_get_contents($controllerPath);

        $this->assertStringContainsString('release-reports/{id}/response', $routes);
        $this->assertStringContainsString('admin.release-reports.update-response', $routes);
        $this->assertStringContainsString('public function updateResponse', $controller);
        $this->assertStringContainsString("'response' => 'nullable|string|max:2000'", $controller);
        $this->assertStringContainsString('ReleaseBrowseService::bumpCacheVersion();', $controller);
    }

    /**
     * Test that the admin release reports view includes response UI.
     */
    public function test_admin_view_has_response_ui(): void
    {
        $viewPath = resource_path('views/admin/release-reports/index.blade.php');
        $scriptPath = resource_path('js/alpine/components/release-report.js');

        $this->assertFileExists($viewPath);
        $this->assertFileExists($scriptPath);

        $view = file_get_contents($viewPath);
        $script = file_get_contents($scriptPath);

        $this->assertStringContainsString('reportResponseModal', $view);
        $this->assertStringContainsString('reportResponseForm', $view);
        $this->assertStringContainsString('response-report-btn', $view);
        $this->assertStringContainsString('admin.release-reports.update-response', $view);
        $this->assertStringContainsString('showResponse(', $script);
        $this->assertStringContainsString('submitResponse()', $script);
    }

    /**
     * Test that details and browse views expose public report responses.
     */
    public function test_details_and_browse_views_show_report_responses(): void
    {
        $detailsControllerPath = app_path('Http/Controllers/DetailsController.php');
        $detailsViewPath = resource_path('views/details/index.blade.php');
        $releaseResultsComponentPath = resource_path('views/components/release-results.blade.php');
        $browseServicePath = app_path('Services/Releases/ReleaseBrowseService.php');

        $this->assertFileExists($detailsControllerPath);
        $this->assertFileExists($detailsViewPath);
        $this->assertFileExists($releaseResultsComponentPath);
        $this->assertFileExists($browseServicePath);

        $detailsController = file_get_contents($detailsControllerPath);
        // The details view is split into partials under details/partials/; assert against the combined source.
        $detailsView = file_get_contents($detailsViewPath);
        foreach (glob(resource_path('views/details/partials/*.blade.php')) ?: [] as $detailsPartial) {
            $detailsView .= file_get_contents($detailsPartial);
        }
        $releaseResultsComponent = file_get_contents($releaseResultsComponentPath);
        $browseService = file_get_contents($browseServicePath);

        $this->assertStringContainsString('publicReportResponses', $detailsController);
        $this->assertStringContainsString('originalReportData', $detailsController);
        $this->assertStringContainsString('totalReportCount', $detailsController);
        $this->assertStringContainsString("where('response_is_public', true)", $detailsController);
        $this->assertStringContainsString('Staff response', $detailsView);
        $this->assertStringContainsString('Original report', $detailsView);
        $this->assertStringContainsString('total_report_count', $browseService);
        $this->assertStringContainsString('latest_report_description', $browseService);
        $this->assertStringContainsString('all_report_reasons', $browseService);
        $this->assertStringContainsString('report_response_count', $browseService);
        $this->assertStringContainsString('response_is_public = 1', $browseService);
        $this->assertStringContainsString('Original report:', $releaseResultsComponent);
        $this->assertStringContainsString('Staff response available on release details', $releaseResultsComponent);
    }
}
