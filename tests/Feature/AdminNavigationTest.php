<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ConfigurationDomain;
use App\Support\Navigation\AdminNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    #[Test]
    public function the_trail_names_the_section_and_page_including_edit_pages_under_a_list(): void
    {
        $this->assertSame(['Dashboard'], AdminNavigation::trailFor(Request::create('/admin/index')));
        $this->assertSame(['Users', 'User List'], AdminNavigation::trailFor(Request::create('/admin/user-list')));
        $this->assertSame(['Users', 'User List'], AdminNavigation::trailFor(Request::create('/admin/user-edit?id=7')));
        $this->assertSame(['Users', 'Role History'], AdminNavigation::trailFor(Request::create('/admin/user-role-history')));
        $this->assertSame(['System', 'Tmux'], AdminNavigation::trailFor(Request::create('/admin/settings/tmux')));
        $this->assertSame(['Filtering', 'Category Regexes'], AdminNavigation::trailFor(Request::create('/admin/category_regexes-edit?id=3')));
        $this->assertSame(['Releases', 'Categories'], AdminNavigation::trailFor(Request::create('/admin/category-edit?id=3')));
        $this->assertSame([], AdminNavigation::trailFor(Request::create('/admin/unknown-page')));
    }

    #[Test]
    public function pagination_and_filter_parameters_do_not_move_the_highlight_to_another_page(): void
    {
        $this->assertSame(['PreDB'], AdminNavigation::trailFor(Request::create('/admin/predb?page=2')));
        $this->assertSame(['Releases', 'Release Reports'], AdminNavigation::trailFor(Request::create('/admin/release-reports?status=pending')));
        $this->assertSame(['Groups', 'Active Groups'], AdminNavigation::trailFor(Request::create('/admin/group-list-active?page=2')));
    }

    #[Test]
    public function pages_nested_below_a_menu_page_highlight_that_page(): void
    {
        $this->assertSame(['Users', 'Role History'], AdminNavigation::trailFor(Request::create('/admin/user-role-history/7')));
        $this->assertSame(['Users', 'Promotions'], AdminNavigation::trailFor(Request::create('/admin/promotions/5/edit')));
        $this->assertSame(['Invitations'], AdminNavigation::trailFor(Request::create('/admin/invitations/12')));
    }

    #[Test]
    public function every_settings_domain_is_reachable_from_the_menu(): void
    {
        foreach (ConfigurationDomain::cases() as $domain) {
            $trail = AdminNavigation::trailFor(Request::create('/admin/settings/'.$domain->value));

            $this->assertSame('System', $trail[0] ?? null, $domain->value);
            $this->assertCount(2, $trail, $domain->value);
        }
    }

    #[Test]
    public function only_the_group_holding_the_current_page_is_open(): void
    {
        $menu = AdminNavigation::forRequest(Request::create('/admin/release-reports'));
        $open = array_values(array_filter($menu, static fn (array $group): bool => $group['open']));

        $this->assertCount(1, $open);
        $this->assertSame('Releases', $open[0]['label']);
        $this->assertSame(['Release Reports'], array_column(array_filter($open[0]['children'], static fn (array $child): bool => $child['active']), 'label'));
    }

    #[Test]
    public function every_menu_link_has_an_icon_and_points_at_a_registered_route(): void
    {
        foreach (AdminNavigation::definition() as $group) {
            $this->assertStringStartsWith('fa-', $group['icon'], $group['label']);

            foreach (isset($group['url']) ? [$group] : $group['children'] as $item) {
                $this->assertStringStartsWith('fa-', $item['icon'], $item['label']);

                if (str_contains($item['url'], (string) config('horizon.path', 'horizon'))) {
                    continue;
                }

                try {
                    Route::getRoutes()->match(Request::create($item['url']));
                } catch (NotFoundHttpException) {
                    $this->fail("{$item['label']} links to an unknown route: {$item['url']}");
                }
            }
        }
    }

    #[Test]
    public function the_admin_shell_uses_monochrome_icons_flat_headers_and_a_remembered_rail(): void
    {
        $menu = (string) file_get_contents(resource_path('views/partials/admin-menu.blade.php'));
        $layout = (string) file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertDoesNotMatchRegularExpression('/text-[a-z]+-400/', $menu);
        $this->assertStringContainsString('<x-nav-link', $menu);
        $this->assertStringContainsString("@include('partials.theme-init', ['adminRail' => true])", $layout);
        $this->assertStringContainsString('x-data="adminSidebarRail"', $layout);
        $this->assertStringContainsString('aria-label="Breadcrumb"', $layout);
        $this->assertStringContainsString("localStorage.getItem('admin_sidebar')", (string) file_get_contents(resource_path('views/partials/theme-init.blade.php')));

        foreach (glob(resource_path('views/admin/{*,*/*}.blade.php'), GLOB_BRACE) ?: [] as $view) {
            $this->assertDoesNotMatchRegularExpression('/<x-admin\.card[^>]*>\s*(?:<!--[^>]*-->\s*)?<x-admin\.page-header/', (string) file_get_contents($view), basename($view));
        }
    }
}
