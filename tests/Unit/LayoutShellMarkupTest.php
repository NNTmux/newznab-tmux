<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class LayoutShellMarkupTest extends TestCase
{
    public function test_layouts_allow_unrestricted_zoom_and_keep_safe_area_support(): void
    {
        foreach (['main', 'admin', 'guest'] as $layout) {
            $markup = $this->view("layouts/{$layout}.blade.php");
            preg_match('/<meta name="viewport" content="([^"]+)"/', $markup, $matches);

            $this->assertArrayHasKey(1, $matches);
            $this->assertStringContainsString('width=device-width', $matches[1]);
            $this->assertStringContainsString('viewport-fit=cover', $matches[1]);
            $this->assertStringNotContainsString('maximum-scale', $matches[1]);
            $this->assertStringNotContainsString('user-scalable=no', $matches[1]);
        }
    }

    public function test_frontend_motion_preferences_and_theme_accessibility_state(): void
    {
        $process = new Process(['node', __DIR__.'/../Fixtures/frontend-standards.mjs']);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());
    }

    public function test_shared_modals_use_native_dialogs_with_accessible_names_and_dismissal(): void
    {
        foreach (['confirmation', 'nfo', 'image', 'preview', 'filelist', 'mediainfo', 'report'] as $modal) {
            $markup = $this->view("partials/{$modal}-modal.blade.php");
            $this->assertMatchesRegularExpression('/<dialog[^>]+x-modal="open"/s', $markup);
            $this->assertMatchesRegularExpression('/aria-labelledby="([^"]+)"/', $markup);
            preg_match('/aria-labelledby="([^"]+)"/', $markup, $label);
            $this->assertStringContainsString('id="'.$label[1].'"', $markup);
            $this->assertStringContainsString('@cancel.prevent=', $markup);
            $this->assertStringContainsString('</dialog>', $markup);
        }
    }

    public function test_auth_password_controls_load_their_component_and_expose_autofill_and_errors(): void
    {
        foreach (['login' => 'current-password', 'register' => 'new-password'] as $page => $autocomplete) {
            $markup = $this->view("auth/{$page}.blade.php");
            $this->assertStringContainsString('x-data="passwordToggle"', $markup);
            $this->assertStringContainsString('x-ref="field"', $markup);
            $this->assertStringContainsString('autocomplete="'.$autocomplete.'"', $markup);
            $this->assertStringContainsString('autocomplete="username"', $markup);
            $this->assertStringContainsString('aria-controls="password"', $markup);
            $this->assertStringContainsString('aria-describedby="password-error"', $markup);
            $this->assertStringContainsString('id="password-error"', $markup);
        }

        $this->assertStringContainsString('<main id="main-content"', $this->view('layouts/guest.blade.php'));
    }

    public function test_user_and_admin_layouts_expose_accessible_shell_landmarks(): void
    {
        foreach (['main', 'admin'] as $layout) {
            $markup = $this->view("layouts/{$layout}.blade.php");

            $this->assertStringContainsString('class="skip-link"', $markup);
            $this->assertStringContainsString('class="layout-shell', $markup);
            $this->assertStringContainsString('class="layout-sidebar', $markup);
            $this->assertStringContainsString('class="layout-topbar', $markup);
            $this->assertStringContainsString('id="main-content"', $markup);
            $this->assertStringContainsString('id="mobile-sidebar-backdrop"', $markup);
            $this->assertStringContainsString('aria-controls="sidebar"', $markup);
            $this->assertStringContainsString('aria-expanded="false"', $markup);
        }

        $this->assertStringNotContainsString('assets/images/logo.svg', $this->view('layouts/main.blade.php'));
    }

    public function test_mobile_sidebar_script_supports_dismissal_and_focus_state(): void
    {
        $script = $this->resource('js/alpine/components/mobile-nav.js');

        $this->assertStringContainsString("event.key === 'Escape'", $script);
        $this->assertStringContainsString("mobileSidebarBackdrop?.addEventListener('click'", $script);
        $this->assertStringContainsString("mobileSidebarToggle.setAttribute('aria-expanded'", $script);
        $this->assertStringContainsString('mobileSidebarClose?.focus()', $script);
    }

    public function test_shared_shell_styles_include_focus_and_reduced_motion_support(): void
    {
        $stylesheet = $this->resource('css/app.css');

        $this->assertStringContainsString(':focus-visible', $stylesheet);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $stylesheet);
        $this->assertStringContainsString('.layout-flash--success', $stylesheet);
        $this->assertStringContainsString('.admin-page-header__icon', $stylesheet);
    }

    public function test_shared_icon_only_controls_have_accessible_names(): void
    {
        $this->assertStringContainsString('aria-label="Search releases"', $this->view('partials/header-menu.blade.php'));
        $this->assertStringContainsString('aria-label="Close confirmation"', $this->view('partials/confirmation-modal.blade.php'));
        $this->assertStringContainsString('aria-label="Dismiss notification"', $this->view('partials/toast-notifications.blade.php'));
    }

    public function test_site_header_links_use_the_configurable_home_setting(): void
    {
        $homeLink = "url(\$site->homeLink ?? '/')";

        $this->assertStringContainsString($homeLink, $this->view('layouts/main.blade.php'));
        $this->assertStringContainsString($homeLink, $this->view('layouts/admin.blade.php'));
        $this->assertStringNotContainsString('href="{{ url(\'/\') }}"', $this->view('layouts/admin.blade.php'));
    }

    public function test_shared_branding_uses_the_configurable_logo_without_workspace_copy(): void
    {
        foreach (['main', 'admin'] as $layout) {
            $this->assertStringContainsString('<x-site-logo :url="$siteLogoUrl ?? null"', $this->view("layouts/{$layout}.blade.php"));
        }

        $this->assertStringNotContainsString('Usenet workspace', $this->view('layouts/main.blade.php'));

        $settingsForm = $this->view('admin/settings/show.blade.php');
        $this->assertStringContainsString('enctype="multipart/form-data"', $settingsForm);
        $this->assertStringContainsString("\$field->control === 'upload'", $settingsForm);
        $this->assertStringContainsString('name="remove_site_logo"', $settingsForm);
    }

    public function test_desktop_header_search_uses_separated_fully_rounded_controls(): void
    {
        $markup = $this->view('partials/header-menu.blade.php');

        $this->assertStringContainsString('layout-primary-nav__search relative hidden items-center gap-2 2xl:flex', $markup);
        $this->assertStringContainsString('id="header-search-category" name="t" class="rounded-lg', $markup);
        $this->assertStringContainsString('class="w-40 rounded-lg border border-gray-600', $markup);
        $this->assertStringContainsString('class="rounded-lg bg-primary-600', $markup);
    }

    public function test_profile_and_edit_profile_share_the_full_content_width(): void
    {
        $profile = $this->view('profile/index.blade.php');
        $editProfile = $this->view('profile/edit.blade.php');

        $this->assertStringContainsString('<div class="w-full">', $editProfile);
        $this->assertStringNotContainsString('max-w-4xl', $editProfile);
        $this->assertStringNotContainsString('max-w-4xl', $profile);
    }

    public function test_first_party_layouts_apply_the_shared_view_treatment(): void
    {
        $this->assertStringContainsString('class="app-view-stack layout-page-container', $this->view('layouts/main.blade.php'));
        $this->assertStringContainsString('class="app-view-stack app-view-stack--admin', $this->view('layouts/admin.blade.php'));
        $this->assertStringContainsString('class="guest-view-stack', $this->view('layouts/guest.blade.php'));
    }

    public function test_shared_page_components_use_the_modern_workspace_primitives(): void
    {
        $this->assertStringContainsString('workspace-hero', $this->view('components/page-header.blade.php'));
        $this->assertStringContainsString('workspace-hero__glow', $this->view('components/page-header.blade.php'));
        $this->assertStringContainsString('aria-label="Breadcrumb"', $this->view('components/page-header.blade.php'));
        $this->assertStringContainsString('workspace-page-header', $this->view('components/admin/page-header.blade.php'));
        $this->assertStringContainsString('rounded-2xl', $this->view('components/panel.blade.php'));
        $this->assertStringContainsString('empty-state__icon', $this->view('components/empty-state.blade.php'));
        $this->assertStringContainsString('min-h-11', $this->view('components/input.blade.php'));
        $this->assertStringContainsString('rounded-xl', $this->view('components/select.blade.php'));
        $this->assertStringContainsString('catalog-results-toolbar', $this->view('components/cover-results-toolbar.blade.php'));
        $this->assertStringContainsString('catalog-release-row', $this->view('components/cover-release-list.blade.php'));
        $this->assertStringContainsString('aria-label="Result view options"', $this->view('components/view-toggle.blade.php'));
        $this->assertStringContainsString('aria-haspopup="menu"', $this->view('components/sort-dropdown.blade.php'));
        $this->assertStringContainsString('aria-label="Search releases"', $this->view('components/search-autocomplete.blade.php'));
        $this->assertStringContainsString('aria-label="Pagination"', $this->view('components/admin/pagination.blade.php'));
    }

    public function test_shared_user_hero_uses_compact_vertical_spacing(): void
    {
        $hero = $this->view('components/page-header.blade.php');

        $this->assertStringContainsString('px-5 py-4', $hero);
        $this->assertStringContainsString('sm:px-7 sm:py-5', $hero);
        $this->assertStringContainsString('flex flex-col gap-4', $hero);
        $this->assertStringContainsString('aria-label="Breadcrumb" class="mb-2', $hero);
        $this->assertStringContainsString('class="mt-2 max-w-2xl text-sm leading-5', $hero);
        $this->assertStringContainsString('app-page-header__meta flex min-w-0 flex-wrap', $hero);
        $this->assertStringContainsString('@if(isset($stats) || isset($actions))', $hero);
        $this->assertStringNotContainsString('mt-3 flex flex-wrap gap-2 text-sm', $hero);
        $this->assertStringNotContainsString('sm:py-8', $hero);
    }

    public function test_shared_view_styles_cover_application_admin_and_guest_surfaces(): void
    {
        $stylesheet = $this->resource('css/app.css');

        $this->assertStringContainsString('.app-view-stack > .surface-panel', $stylesheet);
        $this->assertStringContainsString('.workspace-page-header::after', $stylesheet);
        $this->assertStringContainsString('.workspace-hero__glow', $stylesheet);
        $this->assertStringContainsString('.workspace-hero__action', $stylesheet);
        $this->assertStringContainsString('.inline-search-widget', $stylesheet);
        $this->assertStringContainsString('.admin-data-table__head th', $stylesheet);
        $this->assertStringContainsString('.guest-view-stack', $stylesheet);
        $this->assertStringContainsString('.header-gradient', $stylesheet);
        $this->assertStringContainsString('.workspace-document-header', $stylesheet);
    }

    public function test_every_first_party_admin_page_uses_the_canonical_page_header(): void
    {
        $adminViews = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                __DIR__.'/../../resources/views/admin',
                \FilesystemIterator::SKIP_DOTS,
            ),
        );

        foreach ($adminViews as $adminView) {
            if (! str_ends_with($adminView->getFilename(), '.blade.php')) {
                continue;
            }

            $markup = (string) file_get_contents($adminView->getPathname());

            if (! str_contains($markup, "@extends('layouts.admin')")) {
                continue;
            }

            preg_match_all("/@include\\('([^']+)'/", $markup, $includedViews);

            foreach ($includedViews[1] as $includedView) {
                $markup .= $this->view(str_replace('.', '/', $includedView).'.blade.php');
            }

            $relativePath = str_replace(__DIR__.'/../../resources/views/', '', $adminView->getPathname());

            $this->assertStringContainsString(
                '<x-admin.page-header',
                $markup,
                "{$relativePath} must use the canonical admin page header.",
            );
        }
    }

    public function test_predb_admin_page_displays_its_stored_size_without_numeric_conversion(): void
    {
        $markup = $this->view('admin/predb/index.blade.php');

        $this->assertStringContainsString("{{ \$pre->size ?: '—' }}", $markup);
        $this->assertStringNotContainsString('human_filesize($pre->size)', $markup);
        $this->assertStringNotContainsString('bytesToSizeString', $markup);
    }

    public function test_every_main_layout_page_uses_the_canonical_user_hero(): void
    {
        $userViews = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                __DIR__.'/../../resources/views',
                \FilesystemIterator::SKIP_DOTS,
            ),
        );

        foreach ($userViews as $userView) {
            if (! str_ends_with($userView->getFilename(), '.blade.php')) {
                continue;
            }

            $markup = (string) file_get_contents($userView->getPathname());

            if (preg_match('/@extends\([\'\"]layouts\.main[\'\"]\)/', $markup) !== 1) {
                continue;
            }

            $relativePath = str_replace(__DIR__.'/../../resources/views/', '', $userView->getPathname());
            $usesCanonicalHero = str_contains($markup, '<x-page-header') || str_contains($markup, 'movies-hero');

            $this->assertTrue($usesCanonicalHero, "{$relativePath} must use the canonical user hero.");
        }
    }

    public function test_first_party_content_fragments_use_the_canonical_user_hero(): void
    {
        foreach ([
            'browsegroup/index.blade.php',
            'mymovies/add.blade.php',
            'mymovies/index.blade.php',
            'myshows/add.blade.php',
            'myshows/index.blade.php',
        ] as $fragment) {
            $this->assertStringContainsString(
                '<x-page-header',
                $this->view($fragment),
                "{$fragment} must use the canonical user hero.",
            );
        }
    }

    public function test_catalog_hero_titles_use_category_names_without_a_library_suffix(): void
    {
        foreach ([
            'books/index.blade.php' => 'title="Books"',
            'console/index.blade.php' => 'title="Console"',
            'games/index.blade.php' => 'title="Games"',
            'movies/index.blade.php' => '>Movies</h1>',
            'music/index.blade.php' => 'title="Music"',
            'xxx/index.blade.php' => 'title="Adult"',
        ] as $view => $categoryTitle) {
            $markup = $this->view($view);

            $this->assertStringContainsString($categoryTitle, $markup);
            $this->assertDoesNotMatchRegularExpression('/(?:Movie|Book|Console|Games|Music|Adult) library/', $markup);
        }
    }

    public function test_inline_search_reserves_independent_space_for_both_icons(): void
    {
        $markup = $this->view('components/inline-search.blade.php');
        $stylesheet = $this->resource('css/app.css');

        $this->assertStringContainsString('inline-search-widget__icon', $markup);
        $this->assertStringContainsString('inline-search-widget__input', $markup);
        $this->assertStringContainsString('inline-search-widget__button', $markup);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr) 2.5rem;', $stylesheet);
        $this->assertStringContainsString('gap: 0.5rem;', $stylesheet);
        $this->assertStringContainsString('padding-inline-start: 2.5rem !important;', $stylesheet);
    }

    public function test_project_owned_welcome_view_does_not_reference_a_missing_logo_asset(): void
    {
        $welcome = $this->view('welcome.blade.php');

        $this->assertStringNotContainsString('assets/images/logo.svg', $welcome);
        $this->assertStringContainsString('workspace-brand-mark', $welcome);
    }

    private function view(string $path): string
    {
        return $this->resource("views/{$path}");
    }

    private function resource(string $path): string
    {
        return (string) file_get_contents(__DIR__."/../../resources/{$path}");
    }
}
