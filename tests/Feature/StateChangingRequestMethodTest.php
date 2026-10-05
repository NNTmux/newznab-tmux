<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StateChangingRequestMethodTest extends TestCase
{
    #[DataProvider('mutationRouteProvider')]
    public function test_mutation_routes_do_not_accept_get_requests(string $routeName, string $method): void
    {
        $route = app('router')->getRoutes()->getByName($routeName);

        $this->assertInstanceOf(Route::class, $route);
        $this->assertSame([$method], $route->methods());
        $this->assertNotContains('GET', $route->methods());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function mutationRouteProvider(): array
    {
        return [
            'invitation creation' => ['ajax_profile', 'POST'],
            'cart addition' => ['cart.add', 'POST'],
            'cart deletion' => ['cart.delete', 'POST'],
            'profile deletion' => ['profile_delete', 'DELETE'],
            'category deletion' => ['admin.category-delete', 'DELETE'],
            'promotion toggle' => ['admin.promotions.toggle', 'PATCH'],
            'show unlink' => ['admin.show-remove', 'POST'],
        ];
    }

    #[DataProvider('legacyFormRouteProvider')]
    public function test_legacy_admin_form_pages_use_distinct_get_and_post_routes(string $uri): void
    {
        $routes = app('router')->getRoutes();

        $getRoute = $routes->match(Request::create($uri, 'GET'));
        $postRoute = $routes->match(Request::create($uri, 'POST'));

        $this->assertSame(['GET', 'HEAD'], $getRoute->methods());
        $this->assertSame(['POST'], $postRoute->methods());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function legacyFormRouteProvider(): array
    {
        return [
            'profile settings' => ['/profileedit'],
            'admin user editor' => ['/admin/user-edit'],
            'admin movie add' => ['/admin/movie-add'],
            'admin regex editor' => ['/admin/category_regexes-edit'],
        ];
    }
}
