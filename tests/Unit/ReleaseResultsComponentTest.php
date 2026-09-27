<?php

namespace Tests\Unit;

use Illuminate\Auth\GenericUser;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use PHPUnit\Framework\TestCase;

class ReleaseResultsComponentTest extends TestCase
{
    public function test_browse_and_search_use_shared_release_results_component(): void
    {
        $browse = (string) file_get_contents(__DIR__.'/../../resources/views/browse/index.blade.php');
        $search = (string) file_get_contents(__DIR__.'/../../resources/views/search/index.blade.php');
        $panel = (string) file_get_contents(__DIR__.'/../../resources/views/components/release-results-panel.blade.php');

        $this->assertStringContainsString('<x-release-results-panel :results="$results"', $browse);
        $this->assertStringContainsString('<x-release-results-panel :results="$results"', $search);
        $this->assertStringContainsString('<x-release-results :results="$results"', $panel);
        $this->assertStringNotContainsString('@foreach($results as $result)', $browse);
        $this->assertStringNotContainsString('@foreach($results as $result)', $search);
    }

    public function test_shared_release_results_panel_keeps_bulk_actions_and_toolbar_slots(): void
    {
        $panel = (string) file_get_contents(__DIR__.'/../../resources/views/components/release-results-panel.blade.php');

        $this->assertStringContainsString('nzb_multi_operations_form', $panel);
        $this->assertStringContainsString('<x-release-bulk-actions />', $panel);
        $this->assertStringContainsString('$beforeActions', $panel);
        $this->assertStringContainsString('$toolbarRight', $panel);
        $this->assertStringContainsString('$summary', $panel);
    }

    public function test_bulk_actions_render_named_controls_and_only_offer_delete_to_admins(): void
    {
        $originalContainer = Container::getInstance();
        $compiler = new BladeCompiler(new Filesystem, sys_get_temp_dir());
        $compiled = $compiler->compileString((string) file_get_contents(__DIR__.'/../../resources/views/components/release-bulk-actions.blade.php'));

        try {
            foreach (['guest', 'member', 'admin'] as $role) {
                $container = new Container;
                Container::setInstance($container);
                $guard = $this->createMock(Guard::class);
                $guard->method('check')->willReturn($role !== 'guest');
                $user = new class(['role' => $role === 'admin' ? 'Admin' : 'User']) extends GenericUser
                {
                    public function hasRole(string $role): bool
                    {
                        return $this->role === $role;
                    }
                };
                $guard->method('user')->willReturn($user);
                $container->instance(Factory::class, $guard);

                ob_start();
                try {
                    eval('?>'.$compiled);
                    $html = (string) ob_get_contents();
                } finally {
                    ob_end_clean();
                }

                $this->assertStringContainsString('nzb_multi_operations_download', $html);
                $this->assertStringContainsString('<span>Download</span>', $html);
                $this->assertStringContainsString('nzb_multi_operations_cart', $html);
                $this->assertStringContainsString('<span>Add to basket</span>', $html);
                $this->assertSame($role === 'admin', str_contains($html, 'nzb_multi_operations_delete'));
                $this->assertStringNotContainsString('type="submit"', $html);
            }
        } finally {
            Container::setInstance($originalContainer);
        }
    }

    public function test_shared_release_results_component_keeps_expected_release_actions(): void
    {
        $component = (string) file_get_contents(__DIR__.'/../../resources/views/components/release-results.blade.php');
        $this->assertSame(2, substr_count($component, '<x-release-row-actions'));
        $this->assertSame(2, substr_count($component, '<x-release-badges'));
        $component .= file_get_contents(__DIR__.'/../../resources/views/components/release-row-actions.blade.php');
        $component .= file_get_contents(__DIR__.'/../../resources/views/components/release-badges.blade.php');

        $this->assertStringContainsString('download-nzb', $component);
        $this->assertStringContainsString('add-to-cart', $component);
        $this->assertStringContainsString('filelist-badge', $component);
        $this->assertStringContainsString('nfo-badge', $component);
        $this->assertStringContainsString('preview-badge', $component);
        $this->assertStringContainsString('mediainfo-badge', $component);
        $this->assertStringContainsString('<x-report-button', $component);
    }

    public function test_release_actions_keep_their_distinct_visual_roles(): void
    {
        $rowActions = (string) file_get_contents(__DIR__.'/../../resources/views/components/release-row-actions.blade.php');
        $bulkActions = (string) file_get_contents(__DIR__.'/../../resources/views/components/release-bulk-actions.blade.php');
        $stylesheet = (string) file_get_contents(__DIR__.'/../../resources/css/app.css');

        foreach (['release-row-actions__download', 'release-row-actions__details', 'release-row-actions__basket', 'release-row-actions__movies'] as $actionClass) {
            $this->assertStringContainsString($actionClass, $rowActions);
            $this->assertStringContainsString('.'.$actionClass, $stylesheet);
        }

        $this->assertStringContainsString(':variant="$compact ? \'icon\' : \'button\'"', $rowActions);
        $this->assertStringContainsString('min-height: 2rem;', $stylesheet);
        $this->assertStringContainsString('.release-row-actions--compact > .report-trigger', $stylesheet);
        $this->assertStringContainsString('nzb_multi_operations_download release-bulk-actions__button bg-green-600', $bulkActions);
        $this->assertStringContainsString('nzb_multi_operations_cart release-bulk-actions__button bg-primary-600', $bulkActions);
        $this->assertStringContainsString('nzb_multi_operations_delete release-bulk-actions__button bg-red-600', $bulkActions);
    }

    public function test_release_categories_link_to_the_matching_browse_filter(): void
    {
        $releaseResults = (string) file_get_contents(__DIR__.'/../../resources/views/components/release-results.blade.php');
        $categoryLink = (string) file_get_contents(__DIR__.'/../../resources/views/components/release-category-link.blade.php');
        $adultBrowse = (string) file_get_contents(__DIR__.'/../../resources/views/xxx/index.blade.php');

        $this->assertSame(2, substr_count($releaseResults, '<x-release-category-link'));
        $this->assertStringContainsString('<x-release-category-link', $adultBrowse);
        $this->assertStringContainsString("route('browse'", $categoryLink);
        $this->assertStringContainsString("preg_split('/\\s*>\\s*/u'", $categoryLink);
        $this->assertStringContainsString('Browse releases in {{ $categoryLabel }}', $categoryLink);
        $this->assertStringContainsString('@if($categoryUrl !== null)', $categoryLink);
    }
}
