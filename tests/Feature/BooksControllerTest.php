<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\BooksController;
use App\Models\Category;
use App\Models\User;
use App\Services\BookService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class BooksControllerTest extends TestCase
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

    public function test_index_coerces_numeric_page_query_strings_to_ints_before_calling_book_service(): void
    {
        $bookService = Mockery::mock(BookService::class);
        $bookService->shouldReceive('getBookOrdering')
            ->once()
            ->andReturn(['posted_desc']);
        $bookService->shouldReceive('getBookRange')
            ->once()
            ->with(2, [Category::BOOKS_ROOT], 50, 50, '', [])
            ->andReturn(collect());

        $controller = new BooksController($bookService);
        $user = new User;
        $user->categoryexclusions = [];
        $controller->userdata = $user;

        $request = Request::create('/Books', 'GET', ['page' => '2']);

        $response = $controller->index($request);

        $this->assertSame(2, $response->getData()['results']->currentPage());
        $this->assertInstanceOf(LengthAwarePaginator::class, $response->getData()['results']);
    }

    public function test_index_defaults_invalid_page_query_values_to_the_first_page(): void
    {
        $bookService = Mockery::mock(BookService::class);
        $bookService->shouldReceive('getBookOrdering')
            ->times(4)
            ->andReturn(['posted_desc']);
        $bookService->shouldReceive('getBookRange')
            ->times(4)
            ->with(1, [Category::BOOKS_ROOT], 0, 50, '', [])
            ->andReturn(collect());

        $invalidPageValues = ['0', '-2', 'abc', ['2']];

        foreach ($invalidPageValues as $invalidPageValue) {
            $controller = new BooksController($bookService);
            $user = new User;
            $user->categoryexclusions = [];
            $controller->userdata = $user;

            $request = Request::create('/Books', 'GET', ['page' => $invalidPageValue]);

            $response = $controller->index($request);

            $this->assertSame(1, $response->getData()['results']->currentPage());
            $this->assertInstanceOf(LengthAwarePaginator::class, $response->getData()['results']);
        }
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
            'id' => Category::BOOKS_ROOT,
            'title' => 'Books',
            'status' => 1,
            'disablepreview' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('categories')->insert([
            'id' => Category::BOOKS_EBOOK,
            'title' => 'EBook',
            'parentid' => Category::BOOKS_ROOT,
            'status' => 1,
            'description' => 'Books',
            'disablepreview' => 0,
            'minsizetoformrelease' => 0,
            'maxsizetoformrelease' => 0,
            'root_categories_id' => Category::BOOKS_ROOT,
        ]);
    }
}
