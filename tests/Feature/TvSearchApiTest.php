<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Release;
use App\Models\User;
use App\Services\Releases\ReleaseSearchService;
use App\Services\Search\SearchService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TvSearchApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'mail.from.address' => 'tvsearch@example.test',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);

        DB::purge();
        DB::reconnect();
        Cache::flush();

        $this->registerSqliteConcatIfNeeded();

        $this->createSchema();
        $this->seedData();
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * When the search index returns release IDs by show-level external IDs only, season/episode
     * must still be applied in SQL (v1 ApiController delegates to this service; successful v1
     * XML responses use echo+exit so they are not exercised via HTTP tests here).
     */
    #[Test]
    public function tv_search_applies_season_episode_when_search_index_returns_external_id_hits(): void
    {
        $this->bindSearchIndexMockReturningBothReleases();

        $service = new ReleaseSearchService;
        $results = $service->tvSearch(
            ['tvdb' => 71663],
            '6',
            '24',
            '',
            0,
            100,
            '',
            [5030],
            -1,
            0,
            [],
            'posted_desc'
        );

        $this->assertCount(1, $results);
        $this->assertSame('Simpsons.S06E24.Test', $results[0]->searchname);
        $this->assertSame(6, (int) $results[0]->series);
        $this->assertSame(24, (int) $results[0]->episode);
    }

    #[Test]
    public function tv_search_does_not_fallback_to_name_when_external_id_lookup_has_no_matches(): void
    {
        $mock = Mockery::mock(SearchService::class, [$this->app]);
        $mock->shouldReceive('searchReleasesByExternalId')->once()->andReturn([]);
        $mock->shouldReceive('searchReleases')->never();
        $mock->shouldReceive('searchReleasesWithFuzzy')->never();
        $mock->shouldReceive('isAvailable')->andReturn(false);

        $this->app->instance(SearchService::class, $mock);

        $service = new ReleaseSearchService;
        $results = $service->tvSearch(
            ['tvdb' => 999999],
            '',
            '',
            '',
            0,
            100,
            'Some Other Show 2025',
            [5030],
            -1,
            0,
            [],
            'posted_desc'
        );

        $this->assertCount(0, $results);
    }

    #[Test]
    public function tv_search_name_queries_use_exact_search_without_fuzzy_fallback(): void
    {
        $mock = Mockery::mock(SearchService::class, [$this->app]);
        $mock->shouldReceive('searchReleasesByExternalId')->never();
        $mock->shouldReceive('searchReleases')->once()->with(['searchname' => 'Simpsons'], 1000)->andReturn([1]);
        $mock->shouldReceive('searchReleasesWithFuzzy')->never();
        $mock->shouldReceive('isAvailable')->andReturn(false);

        $this->app->instance(SearchService::class, $mock);

        $service = new ReleaseSearchService;
        $results = $service->tvSearch(
            [],
            '',
            '',
            '',
            0,
            100,
            'Simpsons',
            [5030],
            -1,
            0,
            [],
            'posted_desc'
        );

        $this->assertCount(1, $results);
        $this->assertSame('Simpsons.S06E24.Test', $results[0]->searchname);
    }

    private function bindSearchIndexMockReturningBothReleases(): void
    {
        $mock = Mockery::mock(SearchService::class, [$this->app]);
        $mock->shouldReceive('searchReleasesByExternalId')->andReturn([1, 2]);
        $mock->shouldReceive('isAvailable')->andReturn(false);

        $this->app->instance(SearchService::class, $mock);
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->integer('rate_limit')->default(60);
            $table->integer('apirequests')->default(1000);
            $table->integer('downloadrequests')->default(100);
            $table->integer('addyears')->default(0);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('roles_id')->default(1);
            $table->string('api_token')->nullable()->index();
            $table->string('host')->nullable();
            $table->timestamp('apiaccess')->nullable();
            $table->boolean('verified')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->integer('rate_limit')->default(60);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_requests', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->text('request')->nullable();
            $table->timestamp('timestamp')->nullable();
        });

        Schema::create('user_downloads', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->timestamp('timestamp')->nullable();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedInteger('role_id');
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedInteger('permission_id');
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedInteger('permission_id');
            $table->unsignedInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('user_excluded_categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->unsignedInteger('categories_id');
        });

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

        Schema::create('videos', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('type')->default(0);
            $table->string('title')->default('');
            $table->string('countries_id', 2)->nullable();
            $table->string('started')->nullable();
            $table->integer('anidb')->default(0);
            $table->string('imdb')->nullable();
            $table->integer('tmdb')->default(0);
            $table->integer('trakt')->default(0);
            $table->integer('tvdb')->default(0);
            $table->integer('tvmaze')->default(0);
            $table->integer('tvrage')->default(0);
            $table->integer('source')->default(0);
        });

        Schema::create('tv_episodes', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('videos_id');
            $table->integer('series')->default(0);
            $table->integer('episode')->default(0);
            $table->string('se_complete')->default('');
            $table->string('title')->default('');
            $table->string('firstaired')->nullable();
            $table->text('summary')->nullable();
        });

        Schema::create('releases', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('searchname')->default('');
            $table->string('fromname')->nullable();
            $table->string('postdate')->nullable();
            $table->string('adddate')->nullable();
            $table->string('guid')->nullable();
            $table->unsignedInteger('categories_id')->default(5030);
            $table->unsignedInteger('groups_id')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->integer('totalpart')->default(0);
            $table->integer('passwordstatus')->default(0);
            $table->integer('grabs')->default(0);
            $table->integer('comments')->default(0);
            $table->unsignedInteger('videos_id')->nullable();
            $table->unsignedInteger('tv_episodes_id')->nullable();
        });

    }

    private function seedData(): void
    {
        DB::table('settings')->insert([
            ['name' => 'showpasswordedrelease', 'value' => '0'],
            ['name' => 'strapline', 'value' => 'Test'],
            ['name' => 'metakeywords', 'value' => 'test'],
            ['name' => 'registerstatus', 'value' => '0'],
            ['name' => 'catwebdl', 'value' => '0'],
            ['name' => 'title', 'value' => 'NNTmux Test'],
            ['name' => 'home_link', 'value' => '/'],
        ]);

        DB::table('roles')->insert([
            'id' => 1,
            'name' => 'User',
            'guard_name' => 'web',
            'rate_limit' => 60,
            'apirequests' => 1000,
            'downloadrequests' => 100,
            'addyears' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            'username' => 'tvsearch_user',
            'email' => 'tvsearch@example.test',
            'password' => bcrypt('secret'),
            'roles_id' => 1,
            'api_token' => Str::random(32),
            'verified' => 1,
            'email_verified_at' => now(),
            'rate_limit' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('permissions')->insert([
            'id' => 1,
            'name' => 'view tv',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userModelClass = User::class;
        DB::table('model_has_roles')->insert([
            'role_id' => 1,
            'model_type' => $userModelClass,
            'model_id' => 1,
        ]);

        DB::table('role_has_permissions')->insert([
            'permission_id' => 1,
            'role_id' => 1,
        ]);

        DB::table('model_has_permissions')->insert([
            'permission_id' => 1,
            'model_type' => $userModelClass,
            'model_id' => 1,
        ]);

        DB::table('root_categories')->insert([
            'id' => 5000,
            'title' => 'TV',
            'status' => 1,
        ]);

        DB::table('categories')->insert([
            'id' => 5030,
            'title' => 'SD',
            'root_categories_id' => 5000,
            'status' => 1,
            'description' => 'TV SD',
        ]);

        DB::table('usenet_groups')->insert([
            'name' => 'alt.binaries.test',
            'active' => 1,
            'description' => 'Test',
            'last_updated' => now(),
        ]);

        DB::table('videos')->insert([
            'id' => 1,
            'type' => 0,
            'title' => 'The Simpsons',
            'tvdb' => 71663,
            'tvmaze' => 83,
            'tvrage' => 6190,
            'imdb' => '96697',
        ]);

        DB::table('tv_episodes')->insert([
            [
                'id' => 1,
                'videos_id' => 1,
                'series' => 6,
                'episode' => 24,
                'se_complete' => 'S06E24',
                'title' => 'Sideshow Bob Roberts',
                'firstaired' => '1994-10-09',
                'summary' => null,
            ],
            [
                'id' => 2,
                'videos_id' => 1,
                'series' => 7,
                'episode' => 1,
                'se_complete' => 'S07E01',
                'title' => 'Who Shot Mr. Burns? (Part Two)',
                'firstaired' => '1995-09-17',
                'summary' => null,
            ],
        ]);

        $now = now()->toDateTimeString();

        DB::table('releases')->insert([
            [
                'id' => 1,
                'searchname' => 'Simpsons.S06E24.Test',
                'guid' => 'guid-s06e24',
                'postdate' => $now,
                'adddate' => $now,
                'categories_id' => 5030,
                'groups_id' => 1,
                'size' => 1000,
                'totalpart' => 1,
                'passwordstatus' => 0,
                'grabs' => 0,
                'comments' => 0,
                'videos_id' => 1,
                'tv_episodes_id' => 1,
            ],
            [
                'id' => 2,
                'searchname' => 'Simpsons.S07E01.Test',
                'guid' => 'guid-s07e01',
                'postdate' => $now,
                'adddate' => $now,
                'categories_id' => 5030,
                'groups_id' => 1,
                'size' => 2000,
                'totalpart' => 1,
                'passwordstatus' => 0,
                'grabs' => 0,
                'comments' => 0,
                'videos_id' => 1,
                'tv_episodes_id' => 2,
            ],
        ]);

        Release::clearBootedModels();
    }
}
