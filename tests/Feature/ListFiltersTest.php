<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Navigation\ListFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ListFiltersTest extends TestCase
{
    private function onRequest(string $uri): void
    {
        $this->app->instance('request', Request::create($uri));
    }

    #[Test]
    public function sort_options_get_readable_labels_for_every_controller_ordering(): void
    {
        $options = ListFilters::sortOptions(['name_asc', 'posted_desc', 'stats_desc', 'rating_desc', 'mystery_asc']);

        $this->assertSame('Name (A-Z)', $options['name_asc']['label']);
        $this->assertSame('Posted (Newest)', $options['posted_desc']['label']);
        $this->assertSame('Grabs (Most)', $options['stats_desc']['label']);
        $this->assertSame('Rating (Highest)', $options['rating_desc']['label']);
        $this->assertSame('Mystery (ascending)', $options['mystery_asc']['label']);
    }

    #[Test]
    public function category_items_mark_the_current_category_and_keep_the_sort_order(): void
    {
        $this->onRequest('/Movies/HD?t=2040&ob=title_desc');
        $categories = [['id' => 2040, 'title' => 'HD'], ['id' => 2050, 'title' => 'UHD']];

        $items = ListFilters::categoryItems($categories, 2040, url('/Movies'), 'All movies');

        $this->assertSame(['All movies', 'HD', 'UHD'], array_column($items, 'label'));
        $this->assertSame([false, true, false], array_column($items, 'active'));
        $this->assertSame(url('/Movies').'?ob=title_desc', $items[0]['url']);
        $this->assertSame(url('/Movies/UHD').'?t=2050&ob=title_desc', $items[2]['url']);

        $queryOnly = ListFilters::categoryItems($categories, 1000, url('/Games'), 'All games', false);
        $this->assertTrue($queryOnly[0]['active'], 'Without a matching category, "All" is selected');
        $this->assertSame(url('/Games').'?t=2050&ob=title_desc', $queryOnly[2]['url']);
    }

    #[Test]
    public function browse_offers_main_categories_and_the_selected_ones_subcategories(): void
    {
        $this->onRequest('/browse/Movies/HD?ob=<script>');
        $menu = [
            ['id' => 2000, 'title' => 'Movies', 'categories' => [['id' => 2040, 'title' => 'HD']]],
            ['id' => 5000, 'title' => 'TV', 'categories' => [['id' => 5040, 'title' => 'HD']]],
        ];

        $main = ListFilters::mainCategoryItems($menu, 'Movies');
        $this->assertSame(['All categories', 'Movies', 'TV'], array_column($main, 'label'));
        $this->assertSame([false, true, false], array_column($main, 'active'));
        $this->assertSame(url('/browse/TV'), $main[2]['url'], 'Invalid sort values are not carried over');

        $sub = ListFilters::browseSubcategoryItems($menu[0], 'HD');
        $this->assertSame(['All Movies', 'HD'], array_column($sub, 'label'));
        $this->assertSame(url('/browse/Movies/HD'), $sub[1]['url']);
        $this->assertTrue($sub[1]['active']);

        $this->assertTrue(ListFilters::mainCategoryItems($menu, null)[0]['active']);
    }

    #[Test]
    public function sort_dropdown_with_an_ordering_list_offers_the_default_order(): void
    {
        $this->onRequest('/Audio?t=3010');

        $html = Blade::render('<x-sort-dropdown :ordering="[\'artist_asc\', \'posted_desc\']" />');

        $this->assertStringContainsString('<span>Sort: Default order</span>', $html);
        $this->assertStringContainsString('Artist (A-Z)', $html);
        $this->assertStringContainsString('ob=posted_desc', $html);
        $this->assertStringContainsString('href="'.url('/Audio').'?t=3010"', $html);
    }

    #[Test]
    public function every_list_view_renders_category_and_sort_selectors(): void
    {
        $browse = (string) file_get_contents(resource_path('views/browse/index.blade.php'));
        $this->assertStringContainsString('label="Main category"', $browse);
        $this->assertStringContainsString('label="Category"', $browse);

        foreach (['games', 'music', 'books', 'console'] as $page) {
            $view = (string) file_get_contents(resource_path("views/{$page}/index.blade.php"));
            $this->assertStringContainsString(':category-items="\\App\\Support\\Navigation\\ListFilters::categoryItems(', $view, $page);
            $this->assertStringContainsString(':ordering="$ordering ?? null"', $view, $page);
        }

        foreach (['movies', 'xxx'] as $page) {
            $view = (string) file_get_contents(resource_path("views/{$page}/index.blade.php"));
            $this->assertStringContainsString('<x-link-dropdown label="Category"', $view, $page);
            $this->assertStringContainsString('<x-sort-dropdown :ordering="$ordering ?? []" />', $view, $page);
        }
    }

    #[Test]
    public function the_adult_page_can_switch_to_the_list_view(): void
    {
        $this->onRequest('/XXX/HD?t=6040&ob=size_desc');

        $html = Blade::render('<x-view-toggle current-view="covers" covgroup="xxx" category="HD" parentcat="XXX" :shows="false" />');

        $this->assertStringContainsString('href="'.url('/browse/XXX/HD').'?t=6040&amp;ob=size_desc"', $html);
        $this->assertStringContainsString('>Covers</span>', $html);
        $this->assertStringContainsString('<x-view-toggle current-view="covers" covgroup="xxx"', (string) file_get_contents(resource_path('views/xxx/index.blade.php')));
    }
}
