<?php

namespace Tests\Unit;

use App\Http\Controllers\BasePageController;
use App\Http\Controllers\SearchController;
use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\UrlGenerator as UrlGeneratorContract;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response;

class UserViewRequestInputTest extends TestCase
{
    public function test_page_resolution_rejects_non_scalar_and_negative_values(): void
    {
        $controller = $this->controller();

        $this->assertSame(1, $controller->page(Request::create('/browse', 'GET', ['page' => ['2']])));
        $this->assertSame(1, $controller->page(Request::create('/browse', 'GET', ['page' => -4])));
        $this->assertSame(1, $controller->page(Request::create('/browse', 'GET', ['page' => '3.5'])));
        $this->assertSame(3, $controller->page(Request::create('/browse', 'GET', ['page' => '3'])));
    }

    public function test_order_resolution_only_allows_known_sort_keys(): void
    {
        $controller = $this->controller();
        $ordering = ['name_asc', 'postdate_desc'];

        $this->assertSame('name_asc', $controller->order(Request::create('/browse', 'GET', ['ob' => 'name_asc']), $ordering));
        $this->assertSame('', $controller->order(Request::create('/browse', 'GET', ['ob' => 'name_desc']), $ordering));
        $this->assertSame('', $controller->order(Request::create('/browse', 'GET', ['ob' => ['name_asc']]), $ordering));
    }

    public function test_scalar_input_and_offset_helpers_normalize_user_query_values(): void
    {
        $controller = $this->controller();

        $this->assertSame('', $controller->scalar(Request::create('/search', 'GET', ['title' => ['bad']]), 'title'));
        $this->assertSame('ubuntu', $controller->scalar(Request::create('/search', 'GET', ['title' => 'ubuntu']), 'title'));
        $this->assertSame(42, $controller->integer(Request::create('/browse', 'GET', ['t' => '42']), 't', 100));
        $this->assertSame(100, $controller->integer(Request::create('/browse', 'GET', ['t' => '42.2']), 't', 100));
        $this->assertSame(100, $controller->integer(Request::create('/browse', 'GET', ['t' => ['42']]), 't', 100));
        $this->assertSame(['7010', '7020'], $controller->array(Request::create('/browse', 'GET', ['category' => ['7010', '7020']]), 'category'));
        $this->assertSame([], $controller->array(Request::create('/browse', 'GET', ['category' => '7010']), 'category'));
        $this->assertSame(80, $controller->offset(5, 20));
    }

    public function test_scalar_input_normalizes_an_explicitly_empty_query_after_http_middleware(): void
    {
        $request = Request::create('/admin/anidb-list', 'GET', ['animetitle' => '']);

        (new ConvertEmptyStringsToNull)->handle(
            $request,
            static fn (Request $request): Response => new Response,
        );

        $this->assertNull($request->input('animetitle'));
        $this->assertSame('', $this->controller()->scalar($request, 'animetitle'));
        $this->assertSame('all', $this->controller()->scalar($request, 'animetitle', 'all'));
    }

    public function test_search_category_resolution_rejects_malformed_category_values(): void
    {
        $reflection = new ReflectionClass(SearchController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('resolveCategoryIdsFromRequest');

        $this->assertSame([1000, 2000], $method->invoke($controller, Request::create('/search', 'GET', ['t' => '1000,bad,2000,1000'])));
        $this->assertSame([-1], $method->invoke($controller, Request::create('/search', 'GET', ['t' => ['1000']])));
        $this->assertSame([-1], $method->invoke($controller, Request::create('/search', 'GET', ['searchadvcat' => ''])));
    }

    public function test_local_return_url_accepts_local_targets_and_rejects_external_urls(): void
    {
        $originalContainer = Container::getInstance();
        $container = new Container;
        $container->instance(
            UrlGeneratorContract::class,
            new UrlGenerator(new RouteCollection, Request::create('https://nntmux.test')),
        );
        Container::setInstance($container);

        try {
            $controller = $this->controller();

            $this->assertSame(
                'https://nntmux.test/mymovies',
                $controller->returnUrl(Request::create('https://nntmux.test/mymovies'), '/mymovies'),
            );
            $this->assertSame(
                'https://nntmux.test/browse',
                $controller->returnUrl(Request::create('https://nntmux.test/mymovies', 'GET', ['from' => '/browse']), '/mymovies'),
            );
            $this->assertSame(
                'https://nntmux.test/browse?view=covers',
                $controller->returnUrl(
                    Request::create('https://nntmux.test/mymovies', 'GET', ['from' => 'https://nntmux.test/browse?view=covers']),
                    '/mymovies',
                ),
            );
            $this->assertSame(
                'https://nntmux.test/mymovies',
                $controller->returnUrl(
                    Request::create('https://nntmux.test/mymovies', 'GET', ['from' => 'https://external.example/browse']),
                    '/mymovies',
                ),
            );
        } finally {
            Container::setInstance($originalContainer);
        }
    }

    private function controller(): object
    {
        return new class extends BasePageController
        {
            public function __construct() {}

            public function page(Request $request): int
            {
                return $this->resolvePage($request);
            }

            /**
             * @param  array<int, string>  $ordering
             */
            public function order(Request $request, array $ordering): string
            {
                return $this->resolveOrderBy($request, $ordering);
            }

            public function scalar(Request $request, string $key, string $default = ''): string
            {
                return $this->scalarInput($request, $key, $default);
            }

            public function integer(Request $request, string $key, int $default): int
            {
                return $this->integerInput($request, $key, $default);
            }

            /**
             * @return array<int|string, mixed>
             */
            public function array(Request $request, string $key): array
            {
                return $this->arrayInput($request, $key);
            }

            public function offset(int $page, int $perPage): int
            {
                return $this->paginationOffset($page, $perPage);
            }

            public function returnUrl(Request $request, string $fallback): string
            {
                return $this->localReturnUrl($request, $fallback);
            }
        };
    }
}
