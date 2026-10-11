<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ConfigurationDomain;
use App\Services\Configuration\ConfigurationProvider;
use App\Services\Runners\ReleasesRunner;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ConfigurationTestBuilder;
use Tests\TestCase;

class ReleasesRunnerEligibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'nntmux.echocli' => false,
        ]);
        DB::purge();
        DB::reconnect();

        Schema::create('usenet_groups', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('name');
            $table->integer('active')->default(0);
            $table->integer('backfill')->default(0);
        });
        Schema::create('collections', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('groups_id')->index();
        });
        ConfigurationTestBuilder::update(ConfigurationDomain::Ingestion, ['release_threads' => 2]);
        app(ConfigurationProvider::class)->ingestion();
    }

    /** @return array<string, array{bool}> */
    public static function outputModes(): array
    {
        return ['buffered' => [false], 'streaming' => [true]];
    }

    #[DataProvider('outputModes')]
    public function test_dispatches_each_eligible_group_once_in_existing_order_with_two_selection_queries(bool $streaming): void
    {
        config(['nntmux.stream_fork_output' => $streaming]);
        DB::table('usenet_groups')->insert([
            ['id' => 80, 'name' => 'alt.test.last', 'active' => 1, 'backfill' => 0],
            ['id' => 10, 'name' => 'alt.test.active', 'active' => 1, 'backfill' => 0],
            ['id' => 20, 'name' => 'alt.test.backfill', 'active' => 0, 'backfill' => 1],
            ['id' => 30, 'name' => 'alt.test.both', 'active' => 1, 'backfill' => 1],
            ['id' => 40, 'name' => 'alt.test.empty', 'active' => 1, 'backfill' => 0],
            ['id' => 50, 'name' => 'alt.test.disabled', 'active' => 0, 'backfill' => 0],
            ['id' => 70, 'name' => 'alt.test.other-flag', 'active' => 2, 'backfill' => 2],
        ]);
        DB::table('collections')->insert([
            ['groups_id' => 10], ['groups_id' => 10], ['groups_id' => 20],
            ['groups_id' => 30], ['groups_id' => 50], ['groups_id' => 70], ['groups_id' => 80],
        ]);

        $runner = new CapturingEligibilityReleasesRunner;
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $runner->releases();
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        $this->assertSame([
            [PHP_BINARY, base_path('artisan'), 'releases:process', '10'],
            [PHP_BINARY, base_path('artisan'), 'releases:process', '20'],
            [PHP_BINARY, base_path('artisan'), 'releases:process', '30'],
            [PHP_BINARY, base_path('artisan'), 'releases:process', '80'],
        ], $runner->commands);
        $this->assertSame($streaming ? [2] : [2, 2], $runner->limits);
        $this->assertSame($streaming ? ['releases'] : [], $runner->streamingDescriptions);
        $selectionQueries = array_filter($queries, static fn (array $query): bool => preg_match('/\bfrom\s+["`]?\b(?:usenet_groups|collections)\b/i', $query['query']) === 1);
        $this->assertCount(2, $selectionQueries, 'Group dispatch must not issue one collection lookup per enabled group.');
    }

    public function test_failed_batch_lookup_falls_back_and_skips_only_failed_group_lookups(): void
    {
        config(['nntmux.stream_fork_output' => true]);
        DB::table('usenet_groups')->insert([
            ['id' => 1, 'name' => 'alt.test.first', 'active' => 1, 'backfill' => 0],
            ['id' => 2, 'name' => 'alt.test.failed', 'active' => 0, 'backfill' => 1],
            ['id' => 3, 'name' => 'alt.test.last', 'active' => 1, 'backfill' => 0],
        ]);
        DB::table('collections')->insert([
            ['groups_id' => 1], ['groups_id' => 2], ['groups_id' => 3],
        ]);

        $connection = DB::connection();
        DB::shouldReceive('connection')->andReturn($connection);
        DB::shouldReceive('select')->andReturnUsing(static function (string $sql, array $bindings = []) use ($connection): array {
            if (str_contains($sql, 'EXISTS')) {
                throw new \PDOException('Batch collection lookup failed.');
            }
            if (str_contains($sql, 'groups_id = 2 LIMIT 1')) {
                throw new \PDOException('One group collection lookup failed.');
            }

            return $connection->select($sql, $bindings);
        });

        $runner = new CapturingEligibilityReleasesRunner;
        $runner->releases();

        $this->assertSame([
            [PHP_BINARY, base_path('artisan'), 'releases:process', '1'],
            [PHP_BINARY, base_path('artisan'), 'releases:process', '3'],
        ], $runner->commands);
        $this->assertSame([2], $runner->limits);
    }

    public function test_does_not_dispatch_enabled_groups_without_collections(): void
    {
        DB::table('usenet_groups')->insert([
            ['id' => 1, 'name' => 'alt.test.empty', 'active' => 1, 'backfill' => 0],
            ['id' => 2, 'name' => 'alt.test.disabled', 'active' => 0, 'backfill' => 0],
        ]);
        DB::table('collections')->insert(['groups_id' => 2]);

        $runner = new CapturingEligibilityReleasesRunner;
        $runner->releases();

        $this->assertSame([], $runner->commands);
        $this->assertSame([], $runner->limits);
    }
}

class CapturingEligibilityReleasesRunner extends ReleasesRunner
{
    /** @var list<list<string>> */
    public array $commands = [];

    /** @var list<int> */
    public array $limits = [];

    /** @var list<string> */
    public array $streamingDescriptions = [];

    protected function runParallelCommands(array $commands, int $maxProcesses, ?int $timeout = null): array
    {
        array_push($this->commands, ...array_values($commands));
        $this->limits[] = $maxProcesses;

        return [];
    }

    protected function runStreamingCommands(array $commands, int $maxProcesses, string $desc): void
    {
        array_push($this->commands, ...array_values($commands));
        $this->limits[] = $maxProcesses;
        $this->streamingDescriptions[] = $desc;
    }
}
