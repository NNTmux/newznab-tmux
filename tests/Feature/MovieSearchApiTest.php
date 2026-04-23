<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Release;
use App\Services\Releases\ReleaseSearchService;
use App\Services\Search\SearchService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MovieSearchApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'mail.from.address' => 'moviesearch@example.test',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);

        DB::purge();
        DB::reconnect();
        Cache::flush();

        $this->registerSqliteConcatIfNeeded();
        $this->createSchema();
        $this->seedData();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function movies_search_does_not_fallback_to_name_when_external_id_lookup_has_no_matches(): void
    {
        $mock = Mockery::mock(SearchService::class, [$this->app]);
        $mock->shouldReceive('searchReleasesByExternalId')->once()->andReturn([]);
        $mock->shouldReceive('searchReleases')->never();
        $mock->shouldReceive('searchReleasesWithFuzzy')->never();
        $mock->shouldReceive('isAvailable')->andReturn(false);

        $this->app->instance(SearchService::class, $mock);

        $service = new ReleaseSearchService;
        $results = $service->moviesSearch(
            '9999999',
            -1,
            -1,
            0,
            100,
            'Resurrection 2025',
            [2030],
            -1,
            0,
            [],
            'posted_desc'
        );

        $this->assertCount(0, $results);
    }

    #[Test]
    public function movies_search_name_queries_use_exact_search_without_fuzzy_fallback(): void
    {
        $mock = Mockery::mock(SearchService::class, [$this->app]);
        $mock->shouldReceive('searchReleasesByExternalId')->never();
        $mock->shouldReceive('searchReleases')->once()->with(['searchname' => 'Resurrection 2025'], 1000)->andReturn([1]);
        $mock->shouldReceive('searchReleasesWithFuzzy')->never();
        $mock->shouldReceive('isAvailable')->andReturn(false);

        $this->app->instance(SearchService::class, $mock);

        $service = new ReleaseSearchService;
        $results = $service->moviesSearch(
            '',
            -1,
            -1,
            0,
            100,
            'Resurrection 2025',
            [2030],
            -1,
            0,
            [],
            'posted_desc'
        );

        $this->assertCount(1, $results);
        $this->assertSame('Resurrection.2025.1080p.WEB-DL.TEST', $results[0]->searchname);
    }

    #[Test]
    public function movies_search_mysql_fallback_excludes_unrelated_2025_and_resurrection_releases(): void
    {
        config(['nntmux.mysql_search_fallback' => true]);

        $mock = Mockery::mock(SearchService::class, [$this->app]);
        $mock->shouldReceive('searchReleasesByExternalId')->never();
        $mock->shouldReceive('searchReleases')->once()->with(['searchname' => 'Resurrection 2025'], 1000)->andReturn([]);
        $mock->shouldReceive('searchReleasesWithFuzzy')->never();
        $mock->shouldReceive('isAvailable')->andReturn(false);

        $this->app->instance(SearchService::class, $mock);

        $service = new ReleaseSearchService;
        $results = $service->moviesSearch(
            '',
            -1,
            -1,
            0,
            100,
            'Resurrection 2025',
            [2030],
            -1,
            0,
            [],
            'posted_desc'
        );

        $this->assertCount(1, $results);
        $this->assertSame(['Resurrection.2025.1080p.WEB-DL.TEST'], $results->pluck('searchname')->all());
        $this->assertNotContains('Titanic.The.Digital.Resurrection.2025.720p.WEBRip-LAMA', $results->pluck('searchname')->all());
    }

    #[Test]
    public function api_search_uses_search_releases_filtered_with_search_phrase(): void
    {
        $mock = Mockery::mock(SearchService::class, [$this->app]);
        $mock->shouldReceive('isAvailable')->andReturn(true);
        $mock->shouldReceive('searchReleasesFiltered')
            ->once()
            ->withArgs(function (array $criteria, int $limit, int $offset): bool {
                return ($criteria['phrases'] ?? null) === 'Resurrection 2025'
                    && ($criteria['try_fuzzy'] ?? null) === true
                    && $limit === 100
                    && $offset === 0;
            })
            ->andReturn([
                'ids' => [],
                'total' => 0,
                'fuzzy' => false,
            ]);

        $this->app->instance(SearchService::class, $mock);

        $service = new ReleaseSearchService;
        $results = $service->apiSearch(
            'Resurrection 2025',
            -1,
            0,
            100,
            -1,
            [],
            [2030],
            0,
            'posted_desc'
        );

        $this->assertCount(0, $results);
    }

    private function registerSqliteConcatIfNeeded(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = DB::connection()->getPdo();
        if ($pdo instanceof \PDO && method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('CONCAT', static fn (...$parts): string => implode('', $parts));
        }
    }

    private function createSchema(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->text('value')->nullable();
        });

        Schema::create('root_categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->integer('status')->default(1);
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->unsignedInteger('root_categories_id')->nullable();
            $table->integer('status')->default(1);
            $table->text('description')->nullable();
        });

        Schema::create('usenet_groups', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->string('description')->nullable();
            $table->timestamp('last_updated')->nullable();
        });

        Schema::create('movieinfo', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('imdbid')->nullable();
            $table->unsignedInteger('tmdbid')->default(0);
            $table->unsignedInteger('traktid')->default(0);
            $table->string('title')->default('');
        });

        Schema::create('releases', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('searchname')->default('');
            $table->string('fromname')->nullable();
            $table->string('postdate')->nullable();
            $table->string('adddate')->nullable();
            $table->string('guid')->nullable();
            $table->unsignedInteger('categories_id')->default(2030);
            $table->unsignedInteger('groups_id')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->integer('totalpart')->default(0);
            $table->integer('passwordstatus')->default(0);
            $table->integer('grabs')->default(0);
            $table->integer('comments')->default(0);
            $table->string('imdbid')->nullable();
            $table->unsignedInteger('movieinfo_id')->nullable();
        });
    }

    private function seedData(): void
    {
        DB::table('settings')->insert([
            ['name' => 'showpasswordedrelease', 'value' => '0'],
            ['name' => 'catwebdl', 'value' => '0'],
        ]);

        DB::table('root_categories')->insert([
            'id' => 2000,
            'title' => 'Movies',
            'status' => 1,
        ]);

        DB::table('categories')->insert([
            'id' => 2030,
            'title' => 'HD',
            'root_categories_id' => 2000,
            'status' => 1,
            'description' => 'Movie HD',
        ]);

        DB::table('usenet_groups')->insert([
            'id' => 1,
            'name' => 'alt.binaries.test',
            'active' => 1,
            'description' => 'Test',
            'last_updated' => now(),
        ]);

        DB::table('movieinfo')->insert([
            [
                'id' => 1,
                'imdbid' => '1234567',
                'tmdbid' => 1001,
                'traktid' => 2001,
                'title' => 'Resurrection',
            ],
            [
                'id' => 2,
                'imdbid' => '7654321',
                'tmdbid' => 1002,
                'traktid' => 2002,
                'title' => 'Resurrection Road',
            ],
            [
                'id' => 3,
                'imdbid' => '2468135',
                'tmdbid' => 1003,
                'traktid' => 2003,
                'title' => 'Drop',
            ],
            [
                'id' => 4,
                'imdbid' => '1123581',
                'tmdbid' => 1004,
                'traktid' => 2004,
                'title' => 'Titanic: The Digital Resurrection',
            ],
        ]);

        $now = now()->toDateTimeString();

        DB::table('releases')->insert([
            [
                'id' => 1,
                'searchname' => 'Resurrection.2025.1080p.WEB-DL.TEST',
                'guid' => 'movie-guid-1',
                'postdate' => $now,
                'adddate' => $now,
                'categories_id' => 2030,
                'groups_id' => 1,
                'size' => 1000,
                'totalpart' => 1,
                'passwordstatus' => 0,
                'grabs' => 0,
                'comments' => 0,
                'imdbid' => '1234567',
                'movieinfo_id' => 1,
            ],
            [
                'id' => 2,
                'searchname' => 'Resurrection.Road.2025.1080p.WEB-DL.TEST',
                'guid' => 'movie-guid-2',
                'postdate' => $now,
                'adddate' => $now,
                'categories_id' => 2030,
                'groups_id' => 1,
                'size' => 1000,
                'totalpart' => 1,
                'passwordstatus' => 0,
                'grabs' => 0,
                'comments' => 0,
                'imdbid' => '7654321',
                'movieinfo_id' => 2,
            ],
            [
                'id' => 3,
                'searchname' => 'Drop.2025.2160p.BluRay.TEST',
                'guid' => 'movie-guid-3',
                'postdate' => $now,
                'adddate' => $now,
                'categories_id' => 2030,
                'groups_id' => 1,
                'size' => 1000,
                'totalpart' => 1,
                'passwordstatus' => 0,
                'grabs' => 0,
                'comments' => 0,
                'imdbid' => '2468135',
                'movieinfo_id' => 3,
            ],
            [
                'id' => 4,
                'searchname' => 'Titanic.The.Digital.Resurrection.2025.720p.WEBRip-LAMA',
                'guid' => 'movie-guid-4',
                'postdate' => $now,
                'adddate' => $now,
                'categories_id' => 2030,
                'groups_id' => 1,
                'size' => 1000,
                'totalpart' => 1,
                'passwordstatus' => 0,
                'grabs' => 0,
                'comments' => 0,
                'imdbid' => '1123581',
                'movieinfo_id' => 4,
            ],
        ]);

        Release::clearBootedModels();
    }
}
