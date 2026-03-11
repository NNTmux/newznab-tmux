<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminDashboardRegistrationStatusWidgetTest extends TestCase
{
    public function test_dashboard_blade_contains_registration_status_widget(): void
    {
        $bladePath = resource_path('views/admin/dashboard.blade.php');

        $this->assertFileExists($bladePath);

        $content = file_get_contents($bladePath);

        $this->assertStringContainsString('Site Registration Status', $content);
        $this->assertStringContainsString("route('admin.registrations.index')", $content);
        $this->assertStringContainsString("registrationStatus['effective_status_label']", $content);
        $this->assertStringContainsString("registrationStatus['manual_status_label']", $content);
        $this->assertStringContainsString('$nextRegistrationPeriod', $content);
        $this->assertStringContainsString('Manage Registrations', $content);
    }

    public function test_dashboard_controller_provides_registration_widget_data(): void
    {
        $controllerPath = app_path('Http/Controllers/Admin/AdminPageController.php');

        $this->assertFileExists($controllerPath);

        $content = file_get_contents($controllerPath);

        $this->assertStringContainsString('RegistrationStatusService', $content);
        $this->assertStringContainsString("'registrationStatus' => \$registrationStatus", $content);
        $this->assertStringContainsString("'nextRegistrationPeriod' => \$nextRegistrationPeriod", $content);
        $this->assertStringContainsString('getNextUpcomingPeriod', $content);
    }
}
