<?php

namespace Tests\Unit;

use App\Models\UserActivity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AdminActivityToneClassesTest extends TestCase
{
    #[Test]
    public function activity_badges_use_literal_semantic_classes_that_tailwind_can_scan(): void
    {
        $expected = [
            'registered' => 'bg-success-100',
            'deleted' => 'bg-danger-100',
            'role_updated' => 'bg-primary-100',
            'something_else' => 'bg-gray-100',
        ];

        foreach ($expected as $type => $background) {
            $activity = new UserActivity;
            $activity->activity_type = $type;

            $this->assertStringStartsWith($background.' ', $activity->iconBadgeClasses(), $type);
        }

        $stylesheet = (string) file_get_contents(__DIR__.'/../../resources/css/app.css');
        $this->assertStringContainsString('@source "../../app/Models/UserActivity.php";', $stylesheet);
    }

    #[Test]
    public function dashboard_widgets_never_assemble_class_names_at_runtime(): void
    {
        $script = (string) file_get_contents(__DIR__.'/../../resources/js/alpine/components/admin/dashboard.js');
        $service = (string) file_get_contents(__DIR__.'/../../app/Services/AdminDashboardSnapshotService.php');

        $this->assertDoesNotMatchRegularExpression('/(?:bg|text|from|to)-\$\{/', $script);
        $this->assertStringContainsString('SUMMARY_TONES[c.tone]', $script);
        $this->assertStringNotContainsString("'bg-'.", $service);
        $this->assertStringContainsString("'icon_classes' => \$activity->iconBadgeClasses()", $service);
    }

    #[Test]
    public function dashboard_cards_and_charts_follow_the_theme(): void
    {
        $script = (string) file_get_contents(__DIR__.'/../../resources/js/alpine/components/admin/dashboard.js');
        $view = (string) file_get_contents(__DIR__.'/../../resources/views/admin/dashboard.blade.php');
        $monitoring = (string) file_get_contents(__DIR__.'/../../resources/views/admin/monitoring.blade.php');

        $this->assertStringContainsString("from '../../lib/chart-theme.js'", $script);
        $this->assertStringNotContainsString('rgba(', $script, 'Chart colors come from the theme tokens');
        $this->assertSame(6, substr_count($view, '<x-admin.stat-card'));
        $this->assertStringContainsString('role="tablist"', $monitoring);
        $this->assertStringContainsString('role="tabpanel"', $monitoring);
        $this->assertStringNotContainsString(':aria-pressed', $monitoring);
    }
}
