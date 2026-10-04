<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class AdminDashboardAutoRefreshTest extends TestCase
{
    public function test_dashboard_blade_has_auto_refresh_container(): void
    {
        $bladePath = resource_path('views/admin/dashboard.blade.php');

        $this->assertFileExists($bladePath);

        $content = file_get_contents($bladePath);

        $this->assertStringContainsString('x-data="adminDashboard"', $content);
        $this->assertStringContainsString('data-data-url="{{ route(\'admin.api.dashboard-data\') }}"', $content);
        $this->assertStringContainsString('data-refresh-interval="{{ 15 * 60 * 1000 }}"', $content);
        $this->assertStringContainsString('data-dashboard-content', $content);
        // The previous full-page-reload approach has been removed in favour of
        // updating headline tiles + widgets directly from the JSON payload.
        $this->assertStringNotContainsString('data-refresh-url=', $content);
    }

    public function test_dashboard_blade_labels_match_fifteen_minute_refresh(): void
    {
        $bladePath = resource_path('views/admin/dashboard.blade.php');

        $this->assertFileExists($bladePath);

        $content = file_get_contents($bladePath);

        $this->assertStringContainsString('Auto-refreshes every 15 minutes', $content);
        $this->assertStringContainsString('Last dashboard refresh:', $content);
        $this->assertStringContainsString('$dashboardLastRefreshedAt', $content);
        $this->assertStringContainsString('data-stat="last-refresh"', $content);
        $this->assertStringNotContainsString('Auto-refreshes every minute', $content);
        $this->assertStringNotContainsString('$dashboardLastRefreshedAt = now()', $content);
        $this->assertStringNotContainsString('Auto-refreshes every 20 minutes', $content);
        $this->assertStringNotContainsString('Auto-updates every minute', $content);
    }

    public function test_dashboard_component_refreshes_headline_stats_without_page_reload(): void
    {
        $scriptPath = resource_path('js/alpine/components/admin/dashboard.js');

        $this->assertFileExists($scriptPath);

        $content = file_get_contents($scriptPath);

        $this->assertStringContainsString('|| (15 * 60 * 1000)', $content);
        $this->assertStringContainsString('this._loadDashboardData();', $content);
        // The fetch hits the cached JSON endpoint.
        $this->assertStringContainsString('this.$el.dataset.dataUrl', $content);
        // Each tick re-renders headline tiles + registration status + the
        // visible "Last refresh" label, not just the deferred widgets.
        $this->assertStringContainsString('_renderHeadlineStats(payload.stats)', $content);
        $this->assertStringContainsString('_renderRegistrationStatus(payload.registrationStatus)', $content);
        $this->assertStringContainsString('_renderLastRefresh(payload.generated_at_time)', $content);
        $this->assertStringContainsString("_setStatText('last-refresh', timeText ?? '')", $content);
    }

    public function test_dashboard_warmer_runs_every_fifteen_minutes_without_overlapping(): void
    {
        $events = app(Schedule::class)->events();
        $warmer = collect($events)->first(
            static fn ($event): bool => str_contains($event->command ?? '', 'admin:warm-dashboard')
        );

        $this->assertNotNull($warmer);
        $this->assertSame('*/15 * * * *', $warmer->expression);
        $this->assertTrue($warmer->withoutOverlapping);
    }

    public function test_dashboard_blade_exposes_stat_hooks_for_each_headline_tile(): void
    {
        $bladePath = resource_path('views/admin/dashboard.blade.php');
        $content = (string) file_get_contents($bladePath);

        foreach ([
            'releases',
            'releases-today',
            'users',
            'users-today',
            'active-groups',
            'groups',
            'failed',
            'reported',
            'soft-deleted-users',
            'permanently-deleted-users',
            'deleted-users-total',
            'last-refresh',
        ] as $hook) {
            $this->assertStringContainsString(
                'data-stat="'.$hook.'"',
                $content,
                "Missing data-stat=\"$hook\" hook required by the auto-refresh JS."
            );
        }

        foreach ([
            'effective-badge',
            'manual-badge',
            'scheduled-override',
            'message',
        ] as $hook) {
            $this->assertStringContainsString(
                'data-registration="'.$hook.'"',
                $content,
                "Missing data-registration=\"$hook\" hook required by the auto-refresh JS."
            );
        }
    }
}
