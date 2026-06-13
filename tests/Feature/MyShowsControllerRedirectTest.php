<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\MyShowsController;
use App\Models\User;
use App\Services\Releases\ReleaseBrowseService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class MyShowsControllerRedirectTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'nntmux.items_per_page' => 25,
            'nntmux.cache_expiry_long' => 5,
        ]);

        DB::purge();
        DB::reconnect();
        Cache::flush();

        $this->createSchema();
        $this->seedSettings();
    }

    public function test_delete_without_from_redirects_to_myshows(): void
    {
        DB::table('user_series')->insert([
            'users_id' => 1,
            'videos_id' => 42,
            'categories' => 'NULL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->callDelete(1, 42);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('myshows', $response->getTargetUrl());
    }

    public function test_delete_with_from_redirects_to_from_url(): void
    {
        DB::table('user_series')->insert([
            'users_id' => 1,
            'videos_id' => 99,
            'categories' => 'NULL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->callDelete(1, 99, '/custom-page');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/custom-page', $response->getTargetUrl());
    }

    public function test_delete_nonexistent_show_redirects_back(): void
    {
        $response = $this->callDelete(1, 0);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    private function callDelete(int $userId, int $videoId, ?string $from = null): RedirectResponse
    {
        $browseService = Mockery::mock(ReleaseBrowseService::class);

        $controller = new MyShowsController($browseService);
        $user = new User;
        $user->id = $userId;
        $controller->userdata = $user;

        $params = ['action' => 'delete', 'id' => $videoId];
        if ($from !== null) {
            $params['from'] = $from;
        }

        $request = Request::create('/myshows', 'GET', $params);

        return $controller->show($request);
    }

    private function createSchema(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->text('value')->nullable();
        });

        Schema::create('videos', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->nullable();
        });

        Schema::create('user_series', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('users_id');
            $table->integer('videos_id');
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
