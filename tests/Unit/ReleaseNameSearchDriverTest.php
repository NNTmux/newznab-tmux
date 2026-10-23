<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Search\Drivers\ElasticSearchDriver;
use App\Services\Search\Drivers\ManticoreSearchDriver;
use Elastic\Elasticsearch\ClientBuilder;
use GuzzleHttp\Psr7\Response as HttpResponse;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Manticoresearch\Client;
use Manticoresearch\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Log\NullLogger;
use ReflectionProperty;

final class ReleaseNameSearchDriverTest extends TestCase
{
    private Container $previousContainer;

    private mixed $previousFacadeApplication;

    /** @var list<array{ReflectionProperty, mixed}> */
    private array $previousProperties = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $container = new Container;
        $container->instance('config', new Repository(['app' => ['debug' => false]]));
        $container->instance('log', new NullLogger);
        Container::setInstance($container);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
        foreach ([ManticoreSearchDriver::class, ElasticSearchDriver::class] as $class) {
            foreach (['availabilityCache' => true, 'availabilityCacheTime' => time()] as $name => $value) {
                $property = new ReflectionProperty($class, $name);
                $this->previousProperties[] = [$property, $property->getValue()];
                $property->setValue(null, $value);
            }
        }
        $property = new ReflectionProperty(ElasticSearchDriver::class, 'client');
        $this->previousProperties[] = [$property, $property->getValue()];
    }

    protected function tearDown(): void
    {
        foreach ($this->previousProperties as [$property, $value]) {
            $property->setValue(null, $value);
        }
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacadeApplication);
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    /** @return iterable<string, array{string, array<string, string>|list<string>|string, bool}> */
    public static function searches(): iterable
    {
        foreach (['manticore', 'elasticsearch'] as $backend) {
            foreach (['Hanuman S07', ['searchname' => 'Hanuman S07'], ['Hanuman', 'S07'], 'acronis', ['searchname' => 'acronis']] as $shape => $phrases) {
                foreach ([false, true] as $miss) {
                    yield $backend.'-'.$shape.'-'.($miss ? 'miss' : 'hits') => [$backend, $phrases, $miss];
                }
            }
        }
    }

    /** @param array<string, string>|list<string>|string $phrases */
    #[DataProvider('searches')]
    public function test_partial_search_never_retries_fuzzy_and_preserves_paging_and_filters(string $backend, array|string $phrases, bool $miss): void
    {
        $requests = [];
        $hits = $miss ? [] : [['_id' => '3', '_source' => ['searchname' => ReleaseNameQueryTest::NAMES[3]], 'sort' => [100, 3]], ['_id' => '2', '_source' => [], 'sort' => [100, 2]]];
        $config = ['indexes' => ['releases' => 'test_releases'], 'fuzzy' => ['enabled' => true], 'max_matches' => 10000];
        if ($backend === 'manticore') {
            $client = $this->createMock(Client::class);
            $client->expects(self::once())->method('search')->willReturnCallback(function (array $request) use (&$requests, $hits, $miss): Response {
                $requests[] = $request['body'];
                $response = $this->createMock(Response::class);
                $response->method('getResponse')->willReturn(['hits' => ['hits' => $hits, 'total' => $miss ? 0 : 6]]);

                return $response;
            });
            $driver = new ManticoreSearchDriver($config);
            $driver->manticoreSearch = $client;
        } else {
            $http = $this->createMock(ClientInterface::class);
            $http->expects(self::once())->method('sendRequest')->willReturnCallback(function ($request) use (&$requests, $hits, $miss): HttpResponse {
                $requests[] = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);

                return new HttpResponse(200, ['X-Elastic-Product' => 'Elasticsearch', 'Content-Type' => 'application/json'], json_encode(['hits' => ['hits' => $hits, 'total' => ['value' => $miss ? 0 : 6]]], JSON_THROW_ON_ERROR));
            });
            $client = ClientBuilder::create()->setHosts(['http://localhost:9200'])->setHttpClient($http)->build();
            (new ReflectionProperty(ElasticSearchDriver::class, 'client'))->setValue(null, $client);
            $driver = new ElasticSearchDriver($config);
        }

        $result = $driver->searchReleasesFiltered([
            'phrases' => $phrases,
            'partial_release_name' => true,
            'try_fuzzy' => true,
            'category_ids' => [5030],
            'excluded_category_ids' => [5040],
            'min_size' => 100,
            'max_age_days' => 30,
            'password_allow_rar' => false,
            'sort_field' => 'postdate_ts',
            'sort_dir' => 'desc',
        ], 2, 2);

        self::assertSame($miss ? [] : [3, 2], $result['ids']);
        self::assertSame($miss ? 0 : 6, $result['total']);
        self::assertFalse($result['fuzzy']);
        self::assertTrue($result['available']);
        self::assertCount(1, $requests);
        $request = $requests[0];
        $serialized = json_encode($request, JSON_THROW_ON_ERROR);
        self::assertStringContainsString(in_array('acronis', (array) $phrases, true) ? '*acronis*' : 's07e0*', $serialized);
        self::assertStringNotContainsString('fuzziness', $serialized);
        self::assertStringContainsString('categories_id', $serialized);
        self::assertStringContainsString('passwordstatus', $serialized);
        self::assertStringContainsString('5030', $serialized);
        self::assertStringContainsString('5040', $serialized);
        self::assertSame(2, $request[$backend === 'manticore' ? 'limit' : 'size']);
        self::assertSame(2, $request[$backend === 'manticore' ? 'offset' : 'from']);
        if ($backend === 'manticore') {
            self::assertArrayNotHasKey('fuzzy', $request['options']);
        }
    }
}
