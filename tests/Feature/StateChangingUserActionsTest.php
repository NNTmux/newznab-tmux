<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\MyMoviesController;
use App\Http\Controllers\MyShowsController;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StateChangingUserActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
        DB::purge();
        DB::statement('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
    }

    #[DataProvider('watchlistActions')]
    public function test_get_cannot_mutate_watchlists(string $controllerClass, string $key, string $action): void
    {
        $controller = (new ReflectionClass($controllerClass))->newInstanceWithoutConstructor();

        try {
            $controller->show(Request::create('/watchlist', 'GET', [$key => $action]));
            $this->fail('GET mutation was allowed.');
        } catch (HttpException $exception) {
            $this->assertSame(405, $exception->getStatusCode());
        }
    }

    /** @return array<string, array{class-string<MyShowsController|MyMoviesController>, string, string}> */
    public static function watchlistActions(): array
    {
        $cases = [];
        foreach (['delete', 'doadd', 'doedit'] as $action) {
            $cases['show '.$action] = [MyShowsController::class, 'action', $action];
            $cases['movie '.$action] = [MyMoviesController::class, 'id', $action];
        }

        return $cases;
    }

    #[DataProvider('mutationUrls')]
    public function test_mutations_require_csrf_protection(string $url): void
    {
        $request = Request::create($url, 'POST');
        $request->setLaravelSession(app('session.store'));
        $request->session()->put('_token', 'valid-session-token');
        $route = app('router')->getRoutes()->match($request);
        $middleware = app('router')->resolveMiddleware($route->gatherMiddleware());
        $this->assertContains(PreventRequestForgery::class, $middleware);

        // Exercise the production check that Laravel skips during tests.
        $csrf = new class(app(), app('encrypter')) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        };

        try {
            $csrf->handle($request, function () {
                $this->fail('Mutation ran without a CSRF token.');
            });
            $this->fail('Missing CSRF token was accepted.');
        } catch (TokenMismatchException) {
            $request->headers->set('X-CSRF-TOKEN', 'valid-session-token');
            $response = $csrf->handle($request, fn () => response('accepted'));
            $this->assertSame('accepted', $response->getContent());
        }
    }

    /** @return array<string, array{string}> */
    public static function mutationUrls(): array
    {
        return [
            'invitation' => ['/ajax_profile'],
            'cart add' => ['/cart/add'],
            'cart delete' => ['/cart/delete/test-guid'],
            'shows' => ['/myshows?action=delete&id=7'],
            'movies' => ['/mymovies?id=delete&imdb=1234567'],
        ];
    }

    public function test_post_removes_only_the_current_users_watchlist_entries(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        DB::statement('CREATE TABLE videos (id INTEGER PRIMARY KEY, title TEXT)');
        DB::statement('CREATE TABLE movieinfo (imdbid TEXT PRIMARY KEY, title TEXT)');
        DB::statement('CREATE TABLE user_series (id INTEGER PRIMARY KEY, users_id INTEGER, videos_id INTEGER)');
        DB::statement('CREATE TABLE user_movies (id INTEGER PRIMARY KEY, users_id INTEGER, imdbid TEXT)');
        foreach ([1, 2] as $userId) {
            DB::table('user_series')->insert(['users_id' => $userId, 'videos_id' => 7]);
            DB::table('user_movies')->insert(['users_id' => $userId, 'imdbid' => '1234567']);
        }
        $user = new User;
        $user->forceFill(['id' => 1]);
        foreach ([
            [MyShowsController::class, ['action' => 'delete', 'id' => 7]],
            [MyMoviesController::class, ['id' => 'delete', 'imdb' => '1234567']],
        ] as [$controllerClass, $parameters]) {
            $controller = (new ReflectionClass($controllerClass))->newInstanceWithoutConstructor();
            $controller->userdata = $user;
            $this->assertSame(302, $controller->show(Request::create('/watchlist', 'POST', $parameters))->getStatusCode());
        }
        foreach (['user_series', 'user_movies'] as $table) {
            $this->assertSame(0, DB::table($table)->where('users_id', 1)->count());
            $this->assertSame(1, DB::table($table)->where('users_id', 2)->count());
        }
    }
}
