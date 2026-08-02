<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Console\Commands\NntmuxSearchReconcile;
use App\Services\Search\Drivers\ManticoreSearchDriver;
use Manticoresearch\Client;
use Manticoresearch\Response\SqlToArray;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Regression tests for {@see NntmuxSearchReconcile::fetchIndexedIds()}.
 *
 * Pre-fix the method assumed the Manticore client's raw-mode response had a
 * top-level "data" key, but {@see SqlToArray::getResponse()}
 * actually FLATTENS SELECT results so each row is keyed by its `id`. The result
 * was that every release looked missing from the index. These tests pin the new
 * parser behaviour against the real SqlToArray-shaped responses and against the
 * legacy ['data' => ...] fallback.
 */
class NntmuxSearchReconcileFetchIndexedIdsTest extends TestCase
{
    #[Test]
    public function it_extracts_ids_from_sqltoarray_single_column_response(): void
    {
        // SqlToArray flattens `SELECT id FROM ...` into [id => id] entries.
        $response = [
            101 => 101,
            202 => 202,
            303 => 303,
        ];

        $result = $this->invokeFetchIndexedIds($response, [101, 202, 303, 404]);

        sort($result);
        $this->assertSame([101, 202, 303], $result);
    }

    #[Test]
    public function it_extracts_ids_from_sqltoarray_multi_column_response(): void
    {
        // SqlToArray flattens `SELECT *` into [id => rowArray] entries
        // (with the `id` column stripped from the row payload).
        $response = [
            101 => ['searchname' => 'Foo', 'size' => 1],
            202 => ['searchname' => 'Bar', 'size' => 2],
        ];

        $result = $this->invokeFetchIndexedIds($response, [101, 202, 303]);

        sort($result);
        $this->assertSame([101, 202], $result);
    }

    #[Test]
    public function it_falls_back_to_legacy_data_key_shape(): void
    {
        // Non-raw or pre-4.x client shape.
        $response = [
            'data' => [
                ['id' => 101],
                ['id' => 202],
            ],
            'total' => 2,
            'error' => '',
            'warning' => '',
        ];

        $result = $this->invokeFetchIndexedIds($response, [101, 202, 303]);

        sort($result);
        $this->assertSame([101, 202], $result);
    }

    #[Test]
    public function it_ignores_ids_that_were_not_requested(): void
    {
        // Defensive: response includes an id outside the queried batch.
        $response = [
            101 => 101,
            999 => 999,
        ];

        $result = $this->invokeFetchIndexedIds($response, [101, 202]);

        $this->assertSame([101], $result);
    }

    #[Test]
    public function it_returns_empty_array_for_empty_response(): void
    {
        $this->assertSame([], $this->invokeFetchIndexedIds([], [101, 202]));
    }

    #[Test]
    public function it_returns_empty_array_when_no_ids_requested(): void
    {
        // Should short-circuit before calling sql().
        $this->assertSame([], $this->invokeFetchIndexedIds(['unused'], []));
    }

    #[Test]
    public function it_returns_empty_array_when_response_is_not_array(): void
    {
        $this->assertSame([], $this->invokeFetchIndexedIds('not-an-array', [101]));
    }

    #[Test]
    public function it_issues_sql_with_limit_and_max_matches_matching_batch_size(): void
    {
        // Regression guard: Manticore SQL applies an implicit LIMIT 20 and max_matches=1000
        // when not specified, which previously caused most ids in large batches to be
        // falsely flagged as missing. The probe SQL MUST raise both to the batch size.
        $client = new class extends Client
        {
            public ?string $capturedSql = null;

            public function __construct() {}

            public function sql(mixed ...$params): mixed
            {
                $this->capturedSql = $params[0] ?? null;

                return [];
            }
        };

        $driverRef = new ReflectionClass(ManticoreSearchDriver::class);
        /** @var ManticoreSearchDriver $driver */
        $driver = $driverRef->newInstanceWithoutConstructor();
        $driverRef->getProperty('manticoreSearch')->setValue($driver, $client);

        $command = new NntmuxSearchReconcile;
        $method = (new ReflectionClass(NntmuxSearchReconcile::class))->getMethod('fetchIndexedIds');

        $ids = range(1, 500);
        $method->invoke($command, $driver, 'releases_rt', $ids);

        $this->assertNotNull($client->capturedSql);
        $this->assertStringContainsString('LIMIT 500', $client->capturedSql);
        $this->assertStringContainsString('OPTION max_matches=500', $client->capturedSql);
        $driverSource = file_get_contents(__DIR__.'/../../app/Services/Search/Drivers/ManticoreSearchDriver.php');
        self::assertIsString($driverSource);
        self::assertStringContainsString('ORDER BY id ASC', $driverSource);
    }

    /**
     * @param  mixed  $response  Value the fake Manticore client should return from sql()
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function invokeFetchIndexedIds(mixed $response, array $ids): array
    {
        $client = new class($response) extends Client
        {
            public function __construct(private mixed $stubResponse)
            {
                // Bypass parent ctor: we never hit the network in this test.
            }

            public function sql(mixed ...$params): mixed
            {
                return $this->stubResponse;
            }
        };

        $driverRef = new ReflectionClass(ManticoreSearchDriver::class);
        /** @var ManticoreSearchDriver $driver */
        $driver = $driverRef->newInstanceWithoutConstructor();
        $clientProp = $driverRef->getProperty('manticoreSearch');
        $clientProp->setValue($driver, $client);

        $command = new NntmuxSearchReconcile;
        $method = (new ReflectionClass(NntmuxSearchReconcile::class))->getMethod('fetchIndexedIds');

        /** @var list<int> $result */
        $result = $method->invoke($command, $driver, 'releases_rt', $ids);

        return $result;
    }
}
