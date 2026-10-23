<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Api\ApiReleaseRowCache;
use App\Services\Releases\ReleaseSearchService;
use App\Services\Search\DTO\ReleaseSearchQuery;
use App\Services\Search\DTO\SearchPage;
use App\Services\Search\SearchService;
use App\Support\Configuration\IngestionConfigurationData;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Pdo\Sqlite;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Unit\ReleaseNameQueryTest;

final class PartialReleaseSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'testing',
            'database.connections.testing.database' => ':memory:',
            'nntmux.mysql_search_fallback' => true,
            'search.drivers.manticore.fuzzy.enabled' => true,
            'search.drivers.elasticsearch.fuzzy.enabled' => true,
        ]);
        DB::purge();
        self::assertSame(':memory:', DB::connection()->getDatabaseName());
        $pdo = DB::connection()->getPdo();
        self::assertInstanceOf(Sqlite::class, $pdo);
        $pdo->createFunction('CONCAT', static fn (...$values): string => implode('', $values));
        $pdo->createFunction('regexp', static fn (string $pattern, string $value): int => (int) preg_match('~'.$pattern.'~u', $value));
        Cache::flush();
        $this->createSchema();
        foreach (ReleaseNameQueryTest::NAMES as $id => $name) {
            DB::table('releases')->insert($this->release($id, $name));
        }
    }

    /** @return array<string, array{string, list<int>}> */
    public static function fallbackQueries(): array
    {
        return [
            'packs and episodes' => ['The.Legend.of.Hanuman.S07', [9, 8, 7, 3, 2, 1]],
            'season before quality' => ['The Legend of Hanuman S07 1080p', [9, 8, 7, 3, 2, 1]],
            'season before quality and source' => ['The Legend of Hanuman S07 1080p HS', [9, 8, 7, 3, 2, 1]],
            'complete name' => [ReleaseNameQueryTest::NAMES[2], [2]],
            'partial title' => ['The Legend of Hanum', [9, 8, 7, 5, 4, 3, 2, 1]],
            'miss stays empty with fuzzy enabled' => ['The.Legend.of.Hanuman.S08', []],
        ];
    }

    /** @param list<int> $expected */
    #[DataProvider('fallbackQueries')]
    public function test_api_sql_fallback_matches_release_names_before_paging(string $query, array $expected): void
    {
        $this->unavailableSearch();
        $rows = (new ReleaseSearchService)->apiSearch($query, -1, cat: [5030]);
        self::assertSame($expected, $rows->pluck('id')->all());
        if ($expected !== []) {
            self::assertSame(count($expected), (int) $rows[0]->_totalrows);
        }
    }

    public function test_sql_filters_totals_and_pagination_use_the_same_matches(): void
    {
        $this->unavailableSearch();
        DB::table('releases')->insert([
            $this->release(10, ReleaseNameQueryTest::NAMES[2], ['categories_id' => 5040]),
            $this->release(11, ReleaseNameQueryTest::NAMES[2], ['passwordstatus' => 2]),
        ]);
        $rows = (new ReleaseSearchService)->apiSearch('The.Legend.of.Hanuman.S07', -1, 1, 2, excludedCats: [5040], cat: [5000], minSize: 2500);
        self::assertSame([8, 7], $rows->pluck('id')->all());
        self::assertSame(4, (int) $rows[0]->_totalrows);
    }

    public function test_acronis_search_matches_the_name_and_embedded_terms(): void
    {
        $this->unavailableSearch();
        DB::table('releases')->insert([
            $this->release(20, 'Acronis True Image 41393 Incl Patch - [09/35] - "Acronis True Image 41393 Incl Patch.part07.rar"'),
            $this->release(21, 'Boot.MyAcronisTools.2026'),
            $this->release(22, 'Wondershare Recoverit 14.0.21.3'),
        ]);
        $rows = (new ReleaseSearchService)->apiSearch('acronis', -1, cat: [5030]);
        self::assertSame([21, 20], $rows->pluck('id')->all());
        self::assertSame(2, (int) $rows[0]->_totalrows);
    }

    public function test_sql_fallback_can_page_beyond_the_previous_candidate_cap(): void
    {
        $this->unavailableSearch();
        foreach (array_chunk(range(100, 2350), 250) as $ids) {
            DB::table('releases')->insert(array_map(fn (int $id): array => $this->release($id, 'Unique.Show.S07E01.1080p.WEB-DL.x264-TEST'), $ids));
        }
        $rows = (new ReleaseSearchService)->apiSearch('Unique.Show.S07', -1, 2200, 25, cat: [5030]);
        self::assertCount(25, $rows);
        self::assertSame(2251, (int) $rows[0]->_totalrows);
    }

    /** @return array<string, array{bool}> */
    public static function callers(): array
    {
        return ['site' => [false], 'api' => [true]];
    }

    #[DataProvider('callers')]
    public function test_site_and_api_request_strict_partial_searches_with_filters(bool $api): void
    {
        config(['nntmux.mysql_search_fallback' => false]);
        $mock = Mockery::mock(SearchService::class, [$this->app]);
        $mock->shouldReceive('isAvailable')->andReturn(true);
        $mock->shouldNotReceive('searchReleasesWithFuzzy');
        $mock->shouldReceive('searchReleasePage')->once()->withArgs(function (ReleaseSearchQuery $query) use ($api): bool {
            self::assertTrue($query->partialReleaseName);
            self::assertFalse($query->tryFuzzy);
            self::assertSame($api ? 'Hanuman S07' : ['searchname' => 'Hanuman S07'], $query->phrases);
            self::assertSame([5030], $query->categoryIds);
            self::assertSame(25, $query->limit);
            self::assertSame(50, $query->offset);
            self::assertSame(100, $query->minSize);

            return true;
        })->andReturn(new SearchPage([], 0, false, 'manticore'));
        $this->app->instance(SearchService::class, $mock);

        $service = new ReleaseSearchService;
        $rows = $api
            ? $service->apiSearch('Hanuman S07', -1, 50, 25, cat: [5030], minSize: 100)
            : $service->search(['searchname' => 'Hanuman S07'], -1, -1, -1, -1, -1, 50, 25, cat: [5030], minSize: 100);
        self::assertCount(0, $rows);
    }

    public function test_previous_api_cache_entries_are_not_reused(): void
    {
        $mock = $this->unavailableSearch();
        $mock->shouldReceive('getCurrentDriver')->andReturn('manticore');
        $parameters = ['id' => 'Hanuman S07'];
        foreach ([[], ['matching' => 'partial-release-v1']] as $oldMatching) {
            $oldKey = 'api_release_rows:v2:search:'.md5(serialize([
                ...$oldMatching, 'driver' => 'manticore', 'release_version' => 1, 'parameters' => $parameters,
            ]));
            Cache::put($oldKey, ['wrong-season']);
        }
        $calls = 0;
        $cache = app(ApiReleaseRowCache::class);
        foreach (range(1, 2) as $_) {
            $result = $cache->remember('v2', 'search', $parameters, function () use (&$calls): array {
                $calls++;

                return ['correct-season'];
            });
            self::assertSame(['correct-season'], $result);
        }
        self::assertSame(1, $calls);
    }

    /** @return array<string, array{bool}> */
    public static function passwordSettings(): array
    {
        return ['hide passworded releases' => [false], 'show passworded releases' => [true]];
    }

    #[DataProvider('passwordSettings')]
    public function test_media_name_search_preserves_the_password_visibility_setting(bool $allowPasswords): void
    {
        Schema::create('ingestion_configurations', function (Blueprint $table): void {
            $table->integer('id')->primary();
        });
        Cache::put('configuration:ingestion', new IngestionConfigurationData(...array_replace(
            IngestionConfigurationData::defaults()->toArray(), ['showPasswordedReleases' => $allowPasswords],
        )));
        $mock = $this->unavailableSearch();
        $mock->shouldReceive('searchReleasesFiltered')->once()->withArgs(function (array $criteria) use ($allowPasswords): bool {
            self::assertSame($allowPasswords, $criteria['password_allow_rar']);
            self::assertTrue($criteria['partial_release_name']);
            self::assertFalse($criteria['try_fuzzy']);

            return true;
        })->andReturn(['ids' => []]);

        self::assertCount(0, (new ReleaseSearchService)->moviesSearch(name: 'Hanuman S07'));
    }

    private function unavailableSearch(): mixed
    {
        $mock = Mockery::mock(SearchService::class, [$this->app]);
        $mock->shouldReceive('isAvailable')->andReturn(false);
        $mock->shouldNotReceive('searchReleasesWithFuzzy');
        $this->app->instance(SearchService::class, $mock);

        return $mock;
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function release(int $id, string $name, array $overrides = []): array
    {
        return array_replace([
            'id' => $id, 'searchname' => $name, 'guid' => 'partial-'.$id,
            'postdate' => sprintf('2026-10-23 00:%02d:%02d', intdiv($id, 60), $id % 60),
            'adddate' => '2026-10-23 00:00:00', 'categories_id' => 5030,
            'groups_id' => 1, 'size' => $id * 1000, 'passwordstatus' => 0,
        ], $overrides);
    }

    private function createSchema(): void
    {
        Schema::create('root_categories', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('title');
            $table->integer('status')->default(1);
        });
        Schema::create('categories', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('root_categories_id');
            $table->string('title');
            $table->integer('status')->default(1);
        });
        Schema::create('usenet_groups', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('name');
        });
        foreach (['movieinfo', 'videos'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->integer('id')->primary();
                foreach (['imdbid', 'tmdbid', 'traktid', 'tvdb', 'trakt', 'tvrage', 'tvmaze', 'imdb', 'tmdb'] as $field) {
                    $table->string($field)->nullable();
                }
            });
        }
        Schema::create('tv_episodes', function (Blueprint $table): void {
            $table->integer('id')->primary();
            foreach (['firstaired', 'title', 'series', 'episode'] as $field) {
                $table->string($field)->nullable();
            }
        });
        Schema::create('releases', function (Blueprint $table): void {
            $table->integer('id')->primary();
            foreach (['searchname', 'guid', 'postdate', 'adddate', 'fromname'] as $field) {
                $table->string($field)->nullable();
            }
            foreach (['categories_id', 'groups_id', 'size', 'passwordstatus', 'totalpart', 'grabs', 'comments', 'movieinfo_id', 'videos_id', 'tv_episodes_id'] as $field) {
                $table->integer($field)->default(0);
            }
        });
        DB::table('root_categories')->insert(['id' => 5000, 'title' => 'TV']);
        DB::table('categories')->insert([
            ['id' => 5030, 'root_categories_id' => 5000, 'title' => 'HD'],
            ['id' => 5040, 'root_categories_id' => 5000, 'title' => 'SD'],
        ]);
        DB::table('usenet_groups')->insert(['id' => 1, 'name' => 'alt.binaries.test']);
    }
}
