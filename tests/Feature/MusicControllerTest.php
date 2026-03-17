<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\MusicController;
use App\Models\Category;
use App\Models\User;
use App\Services\GenreService;
use App\Services\MusicService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class MusicControllerTest extends TestCase
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
        $this->seedCategories();
    }

    public function test_show_coerces_numeric_page_query_strings_to_ints_before_calling_music_service(): void
    {
        $musicService = Mockery::mock(MusicService::class);
        $musicService->shouldReceive('getMusicOrdering')
            ->once()
            ->andReturn(['posted_desc']);
        $musicService->shouldReceive('getMusicRange')
            ->once()
            ->with(2, [Category::MUSIC_ROOT], 50, 50, '', [])
            ->andReturn(collect());

        $genreService = Mockery::mock(GenreService::class);
        $genreService->shouldReceive('getGenres')
            ->once()
            ->with((string) GenreService::MUSIC_TYPE, true)
            ->andReturn(new EloquentCollection);

        $controller = new MusicController($musicService, $genreService);
        $user = new User;
        $user->categoryexclusions = [];
        $controller->userdata = $user;

        $request = Request::create('/Audio', 'GET', ['page' => '2']);

        $response = $controller->show($request);

        $this->assertSame(2, $response->getData()['results']->currentPage());
        $this->assertInstanceOf(LengthAwarePaginator::class, $response->getData()['results']);
    }

    private function createSchema(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->text('value')->nullable();
        });

        Schema::create('root_categories', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('title');
            $table->integer('status')->default(1);
            $table->boolean('disablepreview')->default(false);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('title');
            $table->unsignedInteger('parentid')->nullable();
            $table->integer('status')->default(1);
            $table->text('description')->nullable();
            $table->boolean('disablepreview')->default(false);
            $table->unsignedBigInteger('minsizetoformrelease')->default(0);
            $table->unsignedBigInteger('maxsizetoformrelease')->default(0);
            $table->unsignedInteger('root_categories_id')->nullable();
        });
    }

    private function seedCategories(): void
    {
        DB::table('root_categories')->insert([
            'id' => Category::MUSIC_ROOT,
            'title' => 'Music',
            'status' => 1,
            'disablepreview' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('categories')->insert([
            'id' => Category::MUSIC_MP3,
            'title' => 'MP3',
            'parentid' => Category::MUSIC_ROOT,
            'status' => 1,
            'description' => 'Music',
            'disablepreview' => 0,
            'minsizetoformrelease' => 0,
            'maxsizetoformrelease' => 0,
            'root_categories_id' => Category::MUSIC_ROOT,
        ]);
    }
}
