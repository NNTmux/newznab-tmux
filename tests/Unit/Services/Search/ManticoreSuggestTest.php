<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Search;

use App\Services\Search\Drivers\ManticoreSearchDriver;
use Illuminate\Support\Facades\Cache;
use Manticoresearch\Client;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ManticoreSuggestTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $driverConfig = [
        'host' => '127.0.0.1',
        'port' => 9308,
        'indexes' => [
            'releases' => 'releases_rt',
        ],
        'suggest' => [
            'enabled' => true,
            'max_edits' => 4,
        ],
        'cache_minutes' => 5,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'search.index_generation' => 'suggest-test',
        ]);
    }

    #[Test]
    public function it_normalizes_native_numeric_suggestions_and_metrics(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('suggest')
            ->willReturn([[
                'suggest' => 12345,
                'distance' => '2',
                'docs' => '7',
            ]]);

        $driver = $this->driverWithClient($client);
        $expected = [[
            'suggest' => '12345',
            'distance' => 2,
            'docs' => 7,
        ]];

        self::assertSame($expected, $driver->suggest('12354'));
        self::assertSame($expected, Cache::get($this->cacheKey('12354')));
    }

    #[Test]
    public function it_normalizes_cached_numeric_suggestions_without_contacting_manticore(): void
    {
        $query = 'cached-query';
        Cache::put($this->cacheKey($query), [[
            'suggest' => 67890,
            'distance' => '1',
            'docs' => '4',
        ]]);

        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('suggest');

        self::assertSame([[
            'suggest' => '67890',
            'distance' => 1,
            'docs' => 4,
        ]], $this->driverWithClient($client)->suggest($query));
    }

    #[Test]
    public function it_discards_malformed_suggestion_rows(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('suggest')
            ->willReturn([
                'invalid',
                ['suggest' => []],
                ['suggest' => '   '],
                ['suggest' => 'valid', 'distance' => 'invalid', 'docs' => null],
            ]);

        self::assertSame([[
            'suggest' => 'valid',
            'distance' => 0,
            'docs' => 0,
        ]], $this->driverWithClient($client)->suggest('valud'));
    }

    private function driverWithClient(Client $client): ManticoreSearchDriver
    {
        $driver = new ManticoreSearchDriver($this->driverConfig);
        $driver->manticoreSearch = $client;

        return $driver;
    }

    private function cacheKey(string $query): string
    {
        return 'manticore:suggest:suggest-test:'.md5('releases_rt'.$query);
    }
}
