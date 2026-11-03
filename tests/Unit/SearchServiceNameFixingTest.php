<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Search\Contracts\SearchDriverInterface;
use App\Services\Search\DTO\NameFixingSearchQuery;
use App\Services\Search\DTO\NameFixingSearchResult;
use App\Services\Search\SearchService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SearchServiceNameFixingTest extends TestCase
{
    #[Test]
    #[DataProvider('drivers')]
    public function it_delegates_name_fixing_queries_only_to_the_configured_driver(string $driverName): void
    {
        $container = new Container;
        $container->instance('config', new Repository(['search' => ['default' => $driverName]]));
        $driver = $this->createMock(SearchDriverInterface::class);
        $query = new NameFixingSearchQuery(NameFixingSearchQuery::SOURCE_FILES);
        $expected = new NameFixingSearchResult([], [], 0, true, false, $driverName);
        $driver->expects($this->once())
            ->method('searchNameFixingCandidates')
            ->with($query)
            ->willReturn($expected);

        $service = new SearchService($container);
        $service->extend($driverName, static fn (): SearchDriverInterface => $driver);

        $this->assertSame($expected, $service->searchNameFixingCandidates($query));
    }

    /** @return array<string, array{string}> */
    public static function drivers(): array
    {
        return [
            'manticore' => ['manticore'],
            'elasticsearch' => ['elasticsearch'],
        ];
    }
}
