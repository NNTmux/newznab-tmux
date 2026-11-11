<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Support\Navigation\CategoryMenu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HeaderCategoryMenuTest extends TestCase
{
    /**
     * @return list<array{id: int, title: string, categories: list<array{id: int, title: string}>}>
     */
    private function parents(): array
    {
        return [
            ['id' => Category::TV_ROOT, 'title' => 'TV', 'categories' => [['id' => Category::TV_FOREIGN, 'title' => 'Foreign'], ['id' => 5040, 'title' => 'HD']]],
            ['id' => Category::MOVIE_ROOT, 'title' => 'Movies', 'categories' => [['id' => 2040, 'title' => 'HD']]],
            ['id' => Category::PC_ROOT, 'title' => 'PC', 'categories' => [['id' => Category::PC_GAMES, 'title' => 'Games'], ['id' => 4020, 'title' => 'ISO']]],
            ['id' => Category::MUSIC_ROOT, 'title' => 'Audio', 'categories' => []],
        ];
    }

    #[Test]
    public function browse_links_are_used_when_cover_views_are_disabled(): void
    {
        $menu = CategoryMenu::build($this->parents(), null);

        $this->assertSame(['All TV', 'TV Series'], array_column($menu[0]['links'], 'label'));
        $this->assertSame(url('/browse/TV/HD'), $menu[0]['children'][1]['url']);
        $this->assertCount(10, $menu[0]['children'][0]['languages'], 'Foreign subcategories offer language shortcuts');
        $this->assertSame(url('/browse/Movies'), $menu[1]['links'][0]['url']);
        $this->assertSame(url('/browse/Movies/HD'), $menu[1]['children'][0]['url']);
        $this->assertSame(url('/browse/PC/Games'), $menu[2]['children'][0]['url']);
        $this->assertSame('fa-music', $menu[3]['icon']);
        $this->assertSame([], $menu[3]['children']);
    }

    #[Test]
    public function cover_views_send_users_to_the_cover_pages(): void
    {
        $user = new User;
        $user->forceFill(['movieview' => 1, 'consoleview' => 0, 'gameview' => '1']);

        $menu = CategoryMenu::build($this->parents(), $user);

        $this->assertSame(url('/Movies'), $menu[1]['links'][0]['url']);
        $this->assertSame(url('/Movies/HD'), $menu[1]['children'][0]['url']);
        $this->assertSame(url('/Games'), $menu[2]['children'][0]['url']);
        $this->assertSame(url('/browse/PC/ISO'), $menu[2]['children'][1]['url']);
    }

    #[Test]
    public function the_dropdown_of_the_section_being_browsed_is_highlighted(): void
    {
        $menu = CategoryMenu::build($this->parents(), null);

        $tvOnTvPage = $this->renderDropdown($menu[0], '/browse/TV/HD');
        $moviesOnTvPage = $this->renderDropdown($menu[1], '/browse/TV/HD');
        $tvOnSeriesPage = $this->renderDropdown($menu[0], '/series/42');

        $this->assertStringContainsString('data-current-section="true"', $tvOnTvPage);
        $this->assertStringNotContainsString('data-current-section', $moviesOnTvPage);
        $this->assertStringContainsString('data-current-section="true"', $tvOnSeriesPage, 'Pages below a menu link belong to its section');
    }

    #[Test]
    public function the_menu_link_for_the_current_page_is_marked_for_assistive_technology(): void
    {
        $menu = CategoryMenu::build($this->parents(), null);

        $this->assertSame(1, substr_count($this->renderDropdown($menu[0], '/series'), 'aria-current="page"'));
        $this->assertStringNotContainsString('aria-current="page"', $this->renderDropdown($menu[0], '/series/42'));
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function renderDropdown(array $entry, string $currentPath): string
    {
        $request = Request::create(url($currentPath));
        $this->app->instance('request', $request);
        URL::setRequest($request);

        return (string) $this->view('partials.header-menu.category-dropdown', ['entry' => $entry]);
    }

    #[Test]
    public function the_header_renders_desktop_and_mobile_menus_from_the_same_data(): void
    {
        $header = (string) file_get_contents(resource_path('views/partials/header-menu.blade.php'));

        $this->assertSame(1, substr_count($header, "@include('partials.header-menu.category-dropdown'"));
        $this->assertSame(2, substr_count($header, '@foreach($categoryMenu as $entry)'));
        $this->assertStringNotContainsString('App\Models\Category::TV_ROOT', $header);
        $this->assertStringContainsString('lg:flex', $header);
    }
}
