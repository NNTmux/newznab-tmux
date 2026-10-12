<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Facades\Search;
use App\Services\Releases\ReleaseBrowseService;
use App\Services\Releases\ReleaseSearchService;
use App\Services\Search\Drivers\ElasticSearchDriver;
use App\Services\Search\Drivers\ManticoreSearchDriver;
use App\Services\Search\DTO\ReleaseSearchQuery;
use App\Services\Search\DTO\SearchPage;
use App\Services\Search\SearchService;
use Elastic\Elasticsearch\ClientBuilder;
use GuzzleHttp\Psr7\Response;
use Illuminate\Cache\ArrayStore;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Manticoresearch\Client;
use Manticoresearch\Search as ManticoreQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;

final class ReleaseSearchFiltersTest extends TestCase
{
    #[Test]
    public function ranges_keep_zero_bounds_and_drop_untrusted_fields_and_values(): void
    {
        $criteria = ReleaseSearchQuery::fromCriteria([
            'ranges' => [
                'size' => ['min' => '12', 'max' => 100],
                'totalpart' => ['min' => 0],
                'comments' => ['max' => 0],
                'grabs' => ['min' => -1, 'max' => 'garbage'],
                'adddate_ts' => ['min' => 1700000000, 'max' => 1700086399],
                'passwordstatus' => ['min' => 9],
                'size; DROP TABLE releases' => ['min' => 1],
            ],
            'min_id' => 5,
            'max_id' => 2501,
            'sort_field' => 'comments',
        ], 25)->criteria();

        $this->assertSame([
            'size' => ['min' => 12, 'max' => 100],
            'totalpart' => ['min' => 0],
            'comments' => ['max' => 0],
            'adddate_ts' => ['min' => 1700000000, 'max' => 1700086399],
        ], $criteria['ranges'] ?? []);
        $this->assertSame(5, $criteria['min_id'] ?? null);
        $this->assertSame(2501, $criteria['max_id'] ?? null);
        $this->assertSame('comments', $criteria['sort_field']);
    }

    #[Test]
    public function both_index_drivers_apply_inclusive_ranges_and_keyset_bounds(): void
    {
        $criteria = ['ranges' => ['size' => ['min' => 12, 'max' => 100], 'comments' => ['max' => 0], 'adddate_ts' => ['min' => 1700000000]], 'min_id' => 5, 'max_id' => 2501];
        $reflection = new ReflectionClass(ElasticSearchDriver::class);
        $filters = $reflection->getMethod('buildElasticsearchReleaseFilters')->invoke($reflection->newInstanceWithoutConstructor(), $criteria);
        $this->assertContains(['range' => ['size' => ['gte' => 12, 'lte' => 100]]], $filters);
        $this->assertContains(['range' => ['comments' => ['lte' => 0]]], $filters);
        $this->assertContains(['range' => ['adddate_ts' => ['gte' => 1700000000]]], $filters);
        $this->assertContains(['range' => ['id' => ['gte' => 5, 'lte' => 2501]]], $filters);

        $query = new ManticoreQuery(new Client);
        $reflection = new ReflectionClass(ManticoreSearchDriver::class);
        $reflection->getMethod('applyManticoreReleaseIndexFilters')->invoke($reflection->newInstanceWithoutConstructor(), $query, $criteria);
        $filters = $query->compile()['query']['bool']['must'];
        $this->assertContains(['range' => ['size' => ['gte' => 12]]], $filters);
        $this->assertContains(['range' => ['size' => ['lte' => 100]]], $filters);
        $this->assertContains(['range' => ['comments' => ['lte' => 0]]], $filters);
        $this->assertContains(['range' => ['id' => ['gte' => 5]]], $filters);
        $this->assertContains(['range' => ['id' => ['lte' => 2501]]], $filters);
    }

    #[Test]
    public function elasticsearch_orders_keyset_windows_by_actual_numeric_id(): void
    {
        $http = $this->createStub(ClientInterface::class);
        $requests = [];
        $http->method('sendRequest')->willReturnCallback(static function (RequestInterface $request) use (&$requests): ResponseInterface {
            $requests[] = json_decode((string) $request->getBody(), true);

            return new Response(200, ['Content-Type' => 'application/json', 'X-Elastic-Product' => 'Elasticsearch'], json_encode(['hits' => ['total' => ['value' => 1], 'hits' => [['_id' => '2501', 'sort' => [2501, 2501]]]]], JSON_THROW_ON_ERROR));
        });
        $client = ClientBuilder::create()->setHosts(['http://127.0.0.1:9200'])->setHttpClient($http)->build();
        $reflection = new ReflectionClass(ElasticSearchDriver::class);
        $previous = [];
        foreach (['client' => $client, 'availabilityCache' => true, 'availabilityCacheTime' => time()] as $name => $value) {
            $property = $reflection->getProperty($name);
            $previous[$name] = $property->getValue();
            $property->setValue(null, $value);
        }
        try {
            $driver = new ElasticSearchDriver(['indexes' => ['releases' => 'releases']]);
            $result = $driver->searchReleasesFiltered(['sort_field' => 'id', 'sort_dir' => 'desc', 'max_id' => 2501], 1);
            $this->assertSame([2501], $result['ids']);
            $this->assertSame(['id' => ['order' => 'desc']], $requests[0]['sort'][0]);
            $this->assertSame(1, $requests[0]['size']);
            $this->assertSame(0, $requests[0]['from']);
        } finally {
            foreach ($previous as $name => $value) {
                $reflection->getProperty($name)->setValue(null, $value);
            }
        }
    }

    #[Test]
    public function manticore_orders_comments_with_id_ties_and_keeps_fuzzy_windows_fuzzy(): void
    {
        $requests = [];
        $client = $this->createStub(Client::class);
        $client->method('search')->willReturnCallback(static function (array $request) use (&$requests): \Manticoresearch\Response {
            $requests[] = $request['body'];

            return new \Manticoresearch\Response(['hits' => ['total' => 1, 'hits' => [['_id' => '2', '_source' => []]]]]);
        });
        $driver = new class($client) extends ManticoreSearchDriver
        {
            public function __construct(Client $client)
            {
                $this->config = [];
                $this->manticoreSearch = $client;
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };
        $result = $driver->searchReleasesFiltered(['phrases' => 'exampel', 'sort_field' => 'comments', 'sort_dir' => 'asc', 'fuzzy_only' => true, 'try_fuzzy' => false], 25);
        $this->assertSame([2], $result['ids']);
        $this->assertTrue($result['fuzzy']);
        $this->assertCount(1, $requests);
        $this->assertSame([['comments' => 'asc'], ['id' => 'asc']], $requests[0]['sort']);
        $this->assertTrue($requests[0]['options']['fuzzy']);
    }

    #[Test]
    public function filter_only_search_sends_null_text_and_zero_bounds_to_the_index(): void
    {
        $originalApp = Facade::getFacadeApplication();
        $originalContainer = Container::getInstance();
        $container = new Container;
        $container->instance('config', new Repository(['app' => ['debug' => false], 'nntmux' => ['mysql_search_fallback' => false]]));
        $container->instance('cache', new \Illuminate\Cache\Repository(new ArrayStore));
        $container->instance('db.schema', new class
        {
            public function hasTable(string $table): bool
            {
                return false;
            }
        });
        Container::setInstance($container);
        Facade::setFacadeApplication($container);
        $fake = new class
        {
            public ?ReleaseSearchQuery $query = null;

            public function isAvailable(): bool
            {
                return true;
            }

            public function searchReleasePage(ReleaseSearchQuery $query): SearchPage
            {
                $this->query = $query;

                return new SearchPage([], 0, false, 'manticore');
            }
        };
        Search::swap($fake);
        try {
            $service = new ReleaseSearchService;
            $result = $service->search([], -1, -1, -1, -1, -1, 50, 25, 'comments_asc', filters: ['filter_only' => true, 'ranges' => ['comments' => ['max' => 0]]]);
            $this->assertCount(0, $result);
            $this->assertNotNull($fake->query);
            $this->assertNull($fake->query->phrases);
            $this->assertSame(['comments' => ['max' => 0]], $fake->query->ranges);
            $this->assertSame('comments', $fake->query->sortField);
            $this->assertSame(50, $fake->query->offset);
            $this->assertSame(25, $fake->query->limit);
            $fake->query = null;
            $this->assertCount(0, $service->search([], -1, -1, -1, -1, -1));
            $this->assertNull($fake->query);
            $pdo = new \PDO('sqlite::memory:');
            $pdo->exec('CREATE TABLE usenet_groups (id INTEGER PRIMARY KEY, name TEXT)');
            $pdo->exec("INSERT INTO usenet_groups VALUES (7, 'alt.binaries.example')");
            $originalResolver = Model::getConnectionResolver();
            $resolver = new ConnectionResolver(['testing' => new SQLiteConnection($pdo)]);
            $resolver->setDefaultConnection('testing');
            Model::setConnectionResolver($resolver);
            try {
                $this->assertCount(0, $service->search([], 'alt.binaries.missing', -1, -1, -1, -1, filters: ['filter_only' => true]));
                $this->assertNull($fake->query, 'A missing exact group must not become an unrestricted indexed search.');
                $service->search([], 'alt.binaries.example', -1, -1, -1, -1, filters: ['filter_only' => true]);
                $this->assertSame(7, $fake->query?->groupId);
            } finally {
                if ($originalResolver === null) {
                    Model::unsetConnectionResolver();
                } else {
                    Model::setConnectionResolver($originalResolver);
                }
            }
        } finally {
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($originalApp);
            Container::setInstance($originalContainer);
        }
    }

    #[Test]
    public function comments_sort_is_available_in_both_release_services(): void
    {
        foreach ([new ReleaseSearchService, new ReleaseBrowseService] as $service) {
            $this->assertSame(['comments', 'asc'], $service->getBrowseOrder('comments_asc'));
            $this->assertSame(['comments', 'desc'], $service->getBrowseOrder('comments_desc'));
        }
    }

    #[Test]
    public function sql_ranges_filter_before_paging_and_include_zero_and_date_endpoints(): void
    {
        $service = new ReleaseSearchService;
        $reflection = new ReflectionClass($service);
        $this->assertTrue($reflection->hasMethod('buildRangeSqlConditions'));
        $conditions = $reflection->getMethod('buildRangeSqlConditions')->invoke($service, [
            'size' => ['min' => 20, 'max' => 40],
            'comments' => ['max' => 0],
            'adddate_ts' => ['min' => 1700000000, 'max' => 1700086399],
        ]);
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE releases (id INTEGER, size INTEGER, comments INTEGER, adddate TEXT)');
        $pdo->exec("INSERT INTO releases VALUES (1,20,0,'2023-11-14 22:13:20'), (2,40,0,'2023-11-15 22:13:19'), (3,30,1,'2023-11-15 00:00:00'), (4,19,0,'2023-11-15 00:00:00'), (5,30,0,'2023-11-15 22:13:20')");
        $where = implode(' AND ', $conditions);
        $this->assertSame(['2'], array_map('strval', $pdo->query('SELECT id FROM releases r WHERE '.$where.' ORDER BY id ASC LIMIT 1 OFFSET 1')->fetchAll(\PDO::FETCH_COLUMN)));
        $this->assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM releases r WHERE '.$where)->fetchColumn());
    }

    #[Test]
    public function public_sql_fallback_matches_filename_terms_on_one_release_file_and_counts_before_paging(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE releases (id INTEGER PRIMARY KEY, searchname TEXT, fromname TEXT, size INTEGER, comments INTEGER, passwordstatus INTEGER)');
        $pdo->exec('CREATE TABLE release_files (releases_id INTEGER, name TEXT)');
        $pdo->exec("INSERT INTO releases VALUES (1,'First','Poster',20,0,0), (2,'Second','Poster',20,0,0), (3,'Episode 1080p','Poster',20,0,0), (4,'Fourth','Poster',20,1,0), (5,'Fifth','Poster',20,0,0), (6,'Sixth','Poster',20,0,2)");
        $insert = $pdo->prepare('INSERT INTO release_files VALUES (?, ?)');
        foreach ([[1, 'Episode.1080p.mkv'], [2, 'Episode.mkv'], [2, '1080p.mkv'], [4, 'Episode.1080p.mkv'], [5, "O'Reilly.Episode.1080p.mkv"], [6, 'Episode.1080p.mkv']] as $file) {
            $insert->execute($file);
        }
        $connection = new WebSearchSqliteFixtureConnection($pdo);
        $originalApp = Facade::getFacadeApplication();
        $originalContainer = Container::getInstance();
        $originalResolver = Model::getConnectionResolver();
        $container = new Container;
        $container->instance('config', new Repository(['app' => ['debug' => false], 'nntmux' => ['mysql_search_fallback' => true, 'cache_expiry_medium' => 5]]));
        $container->instance('cache', new \Illuminate\Cache\Repository(new ArrayStore));
        $container->instance('db.schema', new class
        {
            public function hasTable(string $table): bool
            {
                return false;
            }
        });
        Container::setInstance($container);
        Facade::setFacadeApplication($container);
        DB::swap($connection);
        $resolver = new ConnectionResolver(['testing' => $connection]);
        $resolver->setDefaultConnection('testing');
        Model::setConnectionResolver($resolver);
        Search::swap(new class
        {
            public function isAvailable(): bool
            {
                return false;
            }
        });
        try {
            $service = new ReleaseSearchService;
            $failure = null;
            $page = null;
            try {
                $page = $service->search(['filename' => 'Episode 1080p', 'fromname' => 'Poster'], -1, -1, -1, -1, -1, 1, 1, 'name_asc', filters: ['ranges' => ['comments' => ['max' => 0]]]);
            } catch (\Throwable $error) {
                $failure = $error;
            }
            $this->assertNull($failure, 'Filename SQL fallback must execute against the release-files schema.');
            $this->assertNotNull($page);
            $this->assertSame([1], $page->modelKeys());
            $this->assertSame(2, $page[0]->_totalrows);
            $quoted = $service->search(['filename' => "O'Reilly 1080p"], -1, -1, -1, -1, -1, 0, 10, 'name_asc');
            $this->assertSame([5], $quoted->modelKeys());
            $this->assertSame(1, $quoted[0]->_totalrows);
        } finally {
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($originalApp);
            Container::setInstance($originalContainer);
            if ($originalResolver === null) {
                Model::unsetConnectionResolver();
            } else {
                Model::setConnectionResolver($originalResolver);
            }
        }
    }

    #[Test]
    public function indexed_hydration_cache_preserves_the_fresh_total_for_identical_page_ids(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE releases (id INTEGER PRIMARY KEY, searchname TEXT)');
        $pdo->exec("INSERT INTO releases VALUES (1, 'Example')");
        $connection = new WebSearchSqliteFixtureConnection($pdo);
        $originalApp = Facade::getFacadeApplication();
        $originalContainer = Container::getInstance();
        $originalResolver = Model::getConnectionResolver();
        $container = new Container;
        $container->instance('config', new Repository(['app' => ['debug' => false], 'nntmux' => ['mysql_search_fallback' => false, 'cache_expiry_medium' => 5]]));
        $container->instance('cache', new \Illuminate\Cache\Repository(new ArrayStore));
        $container->instance('db.schema', new class
        {
            public function hasTable(string $table): bool
            {
                return false;
            }
        });
        Container::setInstance($container);
        Facade::setFacadeApplication($container);
        DB::swap($connection);
        $resolver = new ConnectionResolver(['testing' => $connection]);
        $resolver->setDefaultConnection('testing');
        Model::setConnectionResolver($resolver);
        Search::swap(new class
        {
            private int $calls = 0;

            public function isAvailable(): bool
            {
                return true;
            }

            public function searchReleasePage(ReleaseSearchQuery $query): SearchPage
            {
                return new SearchPage([1], ++$this->calls === 1 ? 2 : 4, false, 'manticore');
            }
        });
        try {
            $service = new ReleaseSearchService;
            $first = $service->search(['searchname' => 'Example'], -1, -1, -1, -1, -1, 0, 1, 'comments_asc', filters: ['ranges' => ['comments' => ['max' => 0]]]);
            $second = $service->search(['searchname' => 'Example'], -1, -1, -1, -1, -1, 0, 1, 'comments_asc', filters: ['ranges' => ['comments' => ['max' => 5]]]);
            $this->assertSame([1], $first->modelKeys());
            $this->assertSame([1], $second->modelKeys());
            $this->assertSame(2, $first[0]->_totalrows);
            $this->assertSame(4, $second[0]->_totalrows);
        } finally {
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($originalApp);
            Container::setInstance($originalContainer);
            if ($originalResolver === null) {
                Model::unsetConnectionResolver();
            } else {
                Model::setConnectionResolver($originalResolver);
            }
        }
    }

    #[Test]
    public function mysql_fallback_keeps_field_terms_and_normalized_phrase_bound(): void
    {
        $service = new ReleaseSearchService;
        $reflection = new ReflectionClass($service);
        $this->assertTrue($reflection->hasMethod('buildMysqlSearchConditions'));
        [$conditions, $bindings] = $reflection->getMethod('buildMysqlSearchConditions')->invoke($service, ['searchname' => "Example.Show O'Reilly", 'fromname' => 'Poster', 'untrusted_column' => 'bad']);
        $this->assertSame(['%Example.Show%', "%O'Reilly%", "example show o'reilly%", '%Poster%'], $bindings);
        $this->assertCount(4, $conditions);
        $this->assertStringContainsString('r.fromname LIKE ?', implode(' AND ', $conditions));
        $this->assertStringNotContainsString('untrusted_column', implode(' AND ', $conditions));
    }

    #[Test]
    public function name_ordering_pages_all_database_matches_and_breaks_ties_by_id(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE releases (id INTEGER PRIMARY KEY, searchname TEXT)');
        $insert = $pdo->prepare('INSERT INTO releases VALUES (?, ?)');
        for ($id = 1; $id <= 2501; $id++) {
            $insert->execute([$id, $id <= 3 ? 'Alpha' : 'Zulu']);
        }
        $originalApp = Facade::getFacadeApplication();
        Facade::setFacadeApplication(new Container);
        DB::swap(new SQLiteConnection($pdo));
        try {
            $service = new ReleaseSearchService;
            $reflection = new ReflectionClass($service);
            $this->assertTrue($reflection->hasMethod('nameSearchWindow'));
            $window = $reflection->getMethod('nameSearchWindow');
            $this->assertSame(['ids' => [2, 3], 'total' => 2501], $window->invoke($service, range(1, 2502), 1, 2, 'asc'));
            $this->assertSame(['ids' => [3, 2], 'total' => 2501], $window->invoke($service, range(1, 2502), 2498, 2, 'desc'));
            $this->assertSame(['ids' => [2501], 'total' => 2501], $window->invoke($service, range(1, 2502), 2500, 2, 'asc'));
        } finally {
            DB::clearResolvedInstance('db');
            Facade::setFacadeApplication($originalApp);
        }
    }

    #[Test]
    public function a_failed_name_window_discards_prior_ids(): void
    {
        $originalApp = Facade::getFacadeApplication();
        Facade::setFacadeApplication(new Container);
        $fake = new class
        {
            private int $calls = 0;

            public function searchReleasePage(ReleaseSearchQuery $query): SearchPage
            {
                return match (++$this->calls) {
                    1 => new SearchPage([2501], 2501, false, 'manticore'),
                    2 => new SearchPage(range(1, 1000), 2501, false, 'manticore'),
                    default => new SearchPage([], 0, false, 'manticore', available: false),
                };
            }
        };
        Search::swap($fake);
        try {
            $service = new ReleaseSearchService;
            $ids = (new ReflectionClass($service))->getMethod('collectNameSearchIds')->invoke($service, ['phrases' => 'example']);
            $this->assertNull($ids);
        } finally {
            Search::clearResolvedInstance(SearchService::class);
            Facade::setFacadeApplication($originalApp);
        }
    }

    #[Test]
    public function fuzzy_name_windows_keep_the_selected_fuzzy_mode_and_watermark(): void
    {
        $originalApp = Facade::getFacadeApplication();
        Facade::setFacadeApplication(new Container);
        $fake = new class
        {
            /** @var list<array<string, mixed>> */
            public array $criteria = [];

            public function searchReleasePage(ReleaseSearchQuery $query): SearchPage
            {
                $criteria = $query->criteria();
                $this->criteria[] = $criteria;
                $ids = $criteria['sort_dir'] === 'desc' ? [9] : [2, 5, 9];

                return new SearchPage($ids, 3, true, 'manticore');
            }
        };
        Search::swap($fake);
        try {
            $service = new ReleaseSearchService;
            $ids = (new ReflectionClass($service))->getMethod('collectNameSearchIds')->invoke($service, ['phrases' => 'exampel', 'groups_id' => 7]);
            $this->assertSame([2, 5, 9], $ids);
            $this->assertTrue($fake->criteria[1]['fuzzy_only']);
            $this->assertFalse($fake->criteria[1]['try_fuzzy']);
            $this->assertSame(9, $fake->criteria[1]['max_id']);
            $this->assertSame(7, $fake->criteria[1]['groups_id']);
        } finally {
            Search::clearResolvedInstance(SearchService::class);
            Facade::setFacadeApplication($originalApp);
        }
    }

    #[Test]
    public function name_candidates_cover_more_than_two_thousand_matches_without_exact_to_fuzzy_spill(): void
    {
        $originalApp = Facade::getFacadeApplication();
        Facade::setFacadeApplication(new Container);
        $fake = new class
        {
            /** @var list<array<string, mixed>> */
            public array $criteria = [];

            public function searchReleasePage(ReleaseSearchQuery $query): SearchPage
            {
                $criteria = $query->criteria();
                $this->criteria[] = $criteria;
                $ids = range(1, 2501);
                if ($criteria['sort_dir'] === 'desc') {
                    return new SearchPage([2501], 2501, false, 'manticore');
                }
                $ids = array_values(array_filter($ids, fn (int $id): bool => $id >= ($criteria['min_id'] ?? 1) && $id <= ($criteria['max_id'] ?? PHP_INT_MAX)));
                $ids = array_slice($ids, 0, $query->limit);
                if ($ids === [] && $criteria['try_fuzzy']) {
                    $ids = [2502];
                }

                return new SearchPage($ids, 2501, false, 'manticore');
            }
        };
        Search::swap($fake);
        try {
            $service = new ReleaseSearchService;
            $reflection = new ReflectionClass($service);
            $this->assertTrue($reflection->hasMethod('collectNameSearchIds'), 'Name sorting must enumerate all matching IDs before SQL pagination.');
            $ids = $reflection->getMethod('collectNameSearchIds')->invoke($service, ['phrases' => ['searchname' => 'example'], 'ranges' => ['comments' => ['max' => 0]], 'max_age_days' => 3]);
            $this->assertSame(range(1, 2501), $ids);
            foreach (array_slice($fake->criteria, 1) as $criteria) {
                $this->assertFalse($criteria['try_fuzzy']);
                $this->assertSame(2501, $criteria['max_id']);
                $this->assertSame(['comments' => ['max' => 0]], $criteria['ranges']);
                $this->assertSame(-1, $criteria['max_age_days']);
                $this->assertGreaterThan(0, $criteria['min_date']);
            }
        } finally {
            Search::clearResolvedInstance(SearchService::class);
            Facade::setFacadeApplication($originalApp);
        }
    }
}

final class WebSearchSqliteFixtureConnection extends SQLiteConnection
{
    /**
     * SQLite cannot render the MySQL report-badge aggregate projection.
     * Keep the public search predicates, bindings, order, and page window
     * unchanged while selecting the fixture's release columns.
     *
     * @param  string  $query
     * @param  array<int, mixed>  $bindings
     * @return array<int, mixed>
     */
    public function select($query, $bindings = [], $useReadPdo = true, array $fetchUsing = [])
    {
        if (str_starts_with($query, 'SELECT * FROM (')) {
            if (! preg_match('/(WHERE .*)\n\s*GROUP BY r.id/s', $query, $where)
                || ! preg_match('/\) r (ORDER BY .*)$/s', $query, $order)) {
                throw new \RuntimeException('Unable to isolate the public search predicates.');
            }
            $orderSql = str_starts_with($order[1], 'ORDER BY FIELD(r.id, 1)') ? 'ORDER BY r.id ASC' : $order[1];
            $query = 'SELECT r.* FROM releases r '.$where[1].' '.$orderSql;
        }

        return parent::select($query, $bindings, $useReadPdo, $fetchUsing);
    }
}
