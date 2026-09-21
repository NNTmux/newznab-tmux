<?php

declare(strict_types=1);

namespace Tests\Feature;

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

    private function invokeCachedProc1Result(string $query): ?object
    {
        $reflection = new ReflectionClass(TmuxMonitorService::class);
        /** @var TmuxMonitorService $monitor */
        $monitor = $reflection->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(TmuxMonitorService::class, 'getCachedProc1Result');

        return $method->invoke($monitor, $query);
    }
}
