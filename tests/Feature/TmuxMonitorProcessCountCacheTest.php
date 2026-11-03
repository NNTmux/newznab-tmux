<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Services\Tmux\TmuxMonitorService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

final class TmuxMonitorProcessCountCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->app['cache']->setDefaultDriver('array');
        Cache::flush();
    }

    #[Test]
    public function it_reuses_the_cached_proc1_result_for_identical_sql(): void
    {
        $query = 'SELECT COUNT(*) AS processtv FROM releases';
        $databaseResult = (object) ['processtv' => 42];

        DB::shouldReceive('selectOne')
            ->once()
            ->with($query)
            ->andReturn($databaseResult);

        $firstResult = $this->invokeCachedProc1Result($query);
        $secondResult = $this->invokeCachedProc1Result($query);

        $this->assertSame($databaseResult, $firstResult);
        $this->assertSame($databaseResult, $secondResult);
        $this->assertSame($databaseResult, Cache::get('tmux:proc1:'.md5($query)));
    }

    #[Test]
    public function it_uses_a_new_cache_entry_when_the_generated_sql_changes(): void
    {
        $firstQuery = 'SELECT COUNT(*) AS processtv FROM releases WHERE lookupimdb = 1';
        $secondQuery = 'SELECT COUNT(*) AS processtv FROM releases WHERE lookupimdb = 2';
        $firstDatabaseResult = (object) ['processtv' => 42];
        $secondDatabaseResult = (object) ['processtv' => 21];

        DB::shouldReceive('selectOne')
            ->once()
            ->with($firstQuery)
            ->andReturn($firstDatabaseResult);
        DB::shouldReceive('selectOne')
            ->once()
            ->with($secondQuery)
            ->andReturn($secondDatabaseResult);

        $this->assertSame($firstDatabaseResult, $this->invokeCachedProc1Result($firstQuery));
        $this->assertSame($secondDatabaseResult, $this->invokeCachedProc1Result($secondQuery));
    }

    #[Test]
    public function it_keeps_the_proc1_result_for_the_configured_ttl(): void
    {
        config(['tmux.monitor.proc1_cache_ttl' => 300]);
        $query = 'SELECT COUNT(*) AS processnfo FROM releases';

        DB::shouldReceive('selectOne')
            ->twice()
            ->with($query)
            ->andReturn((object) ['processnfo' => 1], (object) ['processnfo' => 2]);

        $this->invokeCachedProc1Result($query);
        $this->travel(299)->seconds();
        $this->assertSame(1, $this->invokeCachedProc1Result($query)?->processnfo);

        $this->travel(2)->seconds();
        $this->assertSame(2, $this->invokeCachedProc1Result($query)?->processnfo);
    }

    #[Test]
    public function it_caches_category_counts_for_the_configured_interval(): void
    {
        config(['tmux.monitor.category_counts_interval' => 300]);
        $monitor = $this->monitorWithoutConstructor();
        $method = new ReflectionMethod(TmuxMonitorService::class, 'getCategoryCounts');

        DB::statement('CREATE TABLE releases (id INTEGER PRIMARY KEY AUTOINCREMENT, categories_id INTEGER NOT NULL)');
        DB::table('releases')->insert(['categories_id' => Category::TV_HD]);
        $method->invoke($monitor);
        $this->assertSame(1, $this->categoryCount($monitor, 'tv'));

        DB::table('releases')->insert(['categories_id' => Category::TV_HD]);
        $method->invoke($monitor);
        $this->assertSame(1, $this->categoryCount($monitor, 'tv'));

        $this->travel(301)->seconds();
        $method->invoke($monitor);
        $this->assertSame(2, $this->categoryCount($monitor, 'tv'));
    }

    private function categoryCount(TmuxMonitorService $monitor, string $name): int
    {
        $runVar = (new ReflectionClass(TmuxMonitorService::class))->getProperty('runVar')->getValue($monitor);

        return (int) $runVar['counts']['now'][$name];
    }

    private function monitorWithoutConstructor(): TmuxMonitorService
    {
        /** @var TmuxMonitorService $monitor */
        $monitor = (new ReflectionClass(TmuxMonitorService::class))->newInstanceWithoutConstructor();

        return $monitor;
    }

    private function invokeCachedProc1Result(string $query): ?object
    {
        $reflection = new ReflectionClass(TmuxMonitorService::class);
        /** @var TmuxMonitorService $monitor */
        $monitor = $reflection->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(TmuxMonitorService::class, 'getCachedProc1Result');

        return $method->invoke($monitor, $query);
    }
}
