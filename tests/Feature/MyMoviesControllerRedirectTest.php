<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\MyMoviesController;
use App\Models\User;
use App\Services\MovieBrowseService;
use App\Services\MovieService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class MyMoviesControllerRedirectTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'nntmux.items_per_cover_page' => 50,
            'nntmux.cache_expiry_long' => 5,
        ]);

        DB::purge();
        DB::reconnect();
        Cache::flush();

        $this->createSchema();
        $this->seedSettings();
    }

    public function test_delete_without_from_redirects_to_mymovies(): void
    {
        DB::table('user_movies')->insert([
            'users_id' => 1,
            'imdbid' => 'tt1234567',
            'categories' => 'NULL',
            'created_at' => now(),
        ]);

        $response = $this->callDelete(1, 'tt1234567');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/mymovies', $response->getTargetUrl());
    }

    public function test_delete_with_from_redirects_to_from_url(): void
    {
        DB::table('user_movies')->insert([
            'users_id' => 1,
            'imdbid' => 'tt7654321',
            'categories' => 'NULL',
            'created_at' => now(),
        ]);

        $response = $this->callDelete(1, 'tt7654321', '/custom-page');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/custom-page', $response->getTargetUrl());
    }

    public function test_delete_nonexistent_movie_redirects_to_mymovies(): void
    {
        $response = $this->callDelete(1, 'tt0000000');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/mymovies', $response->getTargetUrl());
    }

    private function callDelete(int $userId, string $imdbid, ?string $from = null): RedirectResponse
    {
        $movieService = Mockery::mock(MovieService::class);
        $browseService = Mockery::mock(MovieBrowseService::class);

        $controller = new MyMoviesController($movieService, $browseService);
        $user = new User;
        $user->id = $userId;
        $controller->userdata = $user;

        $params = ['id' => 'delete', 'imdb' => $imdbid];
        if ($from !== null) {
            $params['from'] = $from;
        }

        $request = Request::create('/mymovies', 'GET', $params);

        return $controller->show($request);
    }

    private function createSchema(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->text('value')->nullable();
        });

        Schema::create('movieinfo', function (Blueprint $table): void {
            $table->string('imdbid')->primary();
            $table->string('title')->nullable();
        });

        Schema::create('user_movies', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('users_id');
            $table->string('imdbid')->nullable();
            $table->string('categories')->nullable();
            $table->timestamps();
        });
    }

    private function seedSettings(): void
    {
        DB::table('settings')->insert([
            ['name' => 'categorizeforeign', 'value' => '0'],
            ['name' => 'catwebdl', 'value' => '0'],
            ['name' => 'title', 'value' => 'NNTmux Test'],
            ['name' => 'home_link', 'value' => '/'],
        ]);
    }
}
