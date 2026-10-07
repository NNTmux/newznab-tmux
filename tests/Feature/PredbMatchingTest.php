<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\NntmuxOffsetPopulate;
use App\Enums\CollectionFileCheckStatus;
use App\Enums\NzbImportStatus;
use App\Events\ReleaseNameFixed;
use App\Facades\Elasticsearch;
use App\Facades\Search;
use App\Models\Predb;
use App\Services\CollectionCleanupService;
use App\Services\IRCScraper;
use App\Services\NameFixing\NameFixingService;
use App\Services\NameFixing\ReleaseUpdateService;
use App\Services\Nzb\NzbImportService;
use App\Services\Nzb\NzbService;
use App\Services\ReleaseCleaningService;
use App\Services\ReleaseCreationService;
use App\Services\Releases\ReleaseDuplicateFinder;
use App\Support\PredbSearchDocument;
use Elastic\Elasticsearch\ClientBuilder;
use GuzzleHttp\Psr7\Response as HttpResponse;
use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Mockery;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

final class PredbMatchingTest extends TestCase
{
    private string $databasePath;

    /** @var array<string, string|false> */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = tempnam(sys_get_temp_dir(), 'nntmux-predb-test-');
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->databasePath] as $key => $value) {
            $this->originalEnvironment[$key] = getenv($key);
            $this->environment($key, $value);
        }
        $pdo = new PDO('sqlite:'.$this->databasePath);
        $pdo->exec('CREATE TABLE settings (name VARCHAR PRIMARY KEY, value TEXT NULL)');
        $pdo->exec("INSERT INTO settings VALUES ('categorizeforeign', '0'), ('catwebdl', '0')");
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $this->databasePath, 'nntmux.echocli' => false]);
        DB::purge();
        DB::reconnect();
        Cache::flush();
        DB::statement('CREATE TABLE predb (id INTEGER PRIMARY KEY, title TEXT, filename TEXT, source TEXT, size TEXT, category TEXT, nuked INTEGER DEFAULT 0)');
        DB::table('predb')->insert(['id' => 7, 'title' => 'Canonical.Scene-GROUP', 'filename' => 'File.Name-GROUP', 'source' => 'srrdb']);
        DB::statement('CREATE TABLE releases (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, searchname TEXT, totalpart INTEGER, groups_id INTEGER, adddate TEXT, guid TEXT, leftguid TEXT, postdate TEXT, fromname TEXT, size INTEGER, passwordstatus INTEGER, haspreview INTEGER, categories_id INTEGER, nfostatus INTEGER, nzbstatus INTEGER, isrenamed INTEGER, iscategorized INTEGER, predb_id INTEGER)');
        DB::statement('CREATE TABLE usenet_groups (id INTEGER PRIMARY KEY, name TEXT)');
        DB::table('usenet_groups')->insert(['id' => 1, 'name' => 'alt.binaries.test']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unlink($this->databasePath);
        foreach ($this->originalEnvironment as $key => $value) {
            $this->environment($key, $value === false ? null : $value);
        }
    }

    /** @return array<string, array{string}> */
    public static function exactNames(): array
    {
        return ['title' => ['canonical.scene-group'], 'filename' => ['file.name-group']];
    }

    #[DataProvider('exactNames')]
    public function test_match_pre_validates_by_primary_key_only(string $name): void
    {
        Search::shouldReceive('matchPredbExact')->once()->with($name)->andReturn($this->hit());
        DB::enableQueryLog();
        DB::flushQueryLog();

        self::assertSame(['title' => 'Canonical.Scene-GROUP', 'predb_id' => 7], Predb::matchPre($name));
        $queries = DB::getQueryLog();
        self::assertCount(1, $queries);
        self::assertStringContainsString('where "predb"."id" = ?', $queries[0]['query']);
        self::assertSame([7], $queries[0]['bindings']);
    }

    /** @return array<string, array{int, string}> */
    public static function staleHits(): array
    {
        return [
            'deleted record' => [99, 'Canonical.Scene-GROUP'],
            'changed title and filename' => [7, 'Old.Scene-GROUP'],
            'punctuation mismatch' => [7, 'Canonical Scene-GROUP'],
            'invalid id' => [0, 'Canonical.Scene-GROUP'],
        ];
    }

    #[DataProvider('staleHits')]
    public function test_missing_or_stale_records_are_rejected(int $id, string $name): void
    {
        Search::shouldReceive('matchPredbExact')->once()->andReturn(array_replace($this->hit(), ['id' => $id]));
        self::assertFalse(Predb::matchPre($name));
    }

    public function test_empty_input_and_outage_do_not_query_predb(): void
    {
        Search::shouldReceive('matchPredbExact')->once()->with('Canonical.Scene-GROUP')->andThrow(new RuntimeException('offline'));
        Log::shouldReceive('warning')->once()->with('PreDB exact lookup failed', ['exception_class' => RuntimeException::class]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        self::assertFalse(Predb::matchPre('  '));
        self::assertFalse(Predb::matchPre('Canonical.Scene-GROUP'));
        self::assertSame([], DB::getQueryLog());
    }

    /** @return array<string, array{bool, bool}> */
    public static function fileMatches(): array
    {
        return ['rename quiet' => [true, false], 'rename verbose' => [true, true], 'same name quiet' => [false, false], 'same name verbose' => [false, true]];
    }

    #[DataProvider('fileMatches')]
    public function test_filename_matching_preserves_id_independently_of_output(bool $rename, bool $output): void
    {
        config(['nntmux.echocli' => $output]);
        $updates = $this->createMock(ReleaseUpdateService::class);
        if ($rename) {
            $updates->expects(self::once())->method('updateRelease')->with(self::anything(), 'Canonical.Scene-GROUP', 'file matched source: srrdb', true, 'PreDB file match, ', true, false, 7);
            $updates->expects(self::never())->method('attachPredbId');
        } else {
            $updates->expects(self::once())->method('attachPredbId')->with(42, 7);
            $updates->expects(self::never())->method('updateRelease');
        }
        Search::shouldReceive('searchPredb')->once()->andReturn([$this->hit()]);
        $service = new NameFixingService(updateService: $updates);
        self::assertSame(1, $service->matchPreDbFiles((object) ['releases_id' => 42, 'filename' => 'File.Name-GROUP.mkv', 'searchname' => $rename ? 'Old.Title' : 'Canonical.Scene-GROUP'], true, true, false));
    }

    public function test_filename_match_does_not_attach_id_when_writes_are_disabled(): void
    {
        $updates = $this->createMock(ReleaseUpdateService::class);
        $updates->expects(self::never())->method('updateRelease');
        $updates->expects(self::never())->method('attachPredbId');
        Search::shouldReceive('searchPredb')->once()->andReturn([$this->hit()]);

        self::assertSame(1, (new NameFixingService(updateService: $updates))->matchPreDbFiles((object) ['releases_id' => 42, 'filename' => 'File.Name-GROUP.mkv', 'searchname' => 'Canonical.Scene-GROUP'], false, true, false));
    }

    #[DataProvider('fileMatches')]
    public function test_filename_match_saves_association_with_real_update_service(bool $rename, bool $output): void
    {
        config(['nntmux.echocli' => $output]);
        foreach (['videos_id', 'tv_episodes_id', 'imdbid', 'musicinfo_id', 'consoleinfo_id', 'bookinfo_id', 'book_lookup_attempts', 'book_lookup_retry_at', 'book_name_normalized_at', 'anidbid'] as $column) {
            DB::statement('ALTER TABLE releases ADD COLUMN '.$column.' TEXT');
        }
        DB::table('releases')->insert(['id' => 42, 'searchname' => $rename ? 'Old.Title' : 'Canonical.Scene-GROUP', 'predb_id' => 0, 'categories_id' => 7010, 'groups_id' => 1]);
        Event::fake([ReleaseNameFixed::class]);
        Search::shouldReceive('searchPredb')->once()->andReturn([$this->hit()]);
        Search::shouldReceive('updateRelease')->times($rename ? 1 : 0)->with(42);
        $release = DB::table('releases')->first();
        $release->releases_id = 42;
        $release->filename = 'File.Name-GROUP.mkv';

        self::assertSame(1, (new NameFixingService)->matchPreDbFiles($release, true, true, false));
        self::assertSame(7, DB::table('releases')->value('predb_id'));
        self::assertSame('Canonical.Scene-GROUP', DB::table('releases')->value('searchname'));
    }

    public function test_filename_matching_rejects_hits_without_ids(): void
    {
        $updates = $this->createMock(ReleaseUpdateService::class);
        $updates->expects(self::never())->method('updateRelease');
        $updates->expects(self::never())->method('attachPredbId');
        Search::shouldReceive('searchPredb')->once()->andReturn([array_replace($this->hit(), ['id' => 0])]);
        self::assertSame(0, (new NameFixingService(updateService: $updates))->matchPreDbFiles((object) ['releases_id' => 42, 'filename' => 'File.Name-GROUP.mkv', 'searchname' => 'Old.Title'], false, true, false));
    }

    /** @return array<string, array{int, string}> */
    public static function imports(): array
    {
        return ['cleaned filename' => [0, ''], 'selected NZB name' => [0, 'File.Name-GROUP'], 'cleaner id' => [7, '']];
    }

    #[DataProvider('imports')]
    public function test_import_persists_matched_or_cleaner_predb_id(int $cleanerId, string $nzbName): void
    {
        $this->app->instance(NzbService::class, $this->createMock(NzbService::class));
        $cleaner = $this->createMock(ReleaseCleaningService::class);
        $cleaner->expects($nzbName === '' ? self::once() : self::never())->method('releaseCleaner')->willReturn(['cleansubject' => 'File.Name-GROUP', 'predb' => $cleanerId]);
        $service = new NzbImportService;
        (new ReflectionProperty($service, 'releaseCleaner'))->setValue($service, $cleaner);
        Search::shouldReceive('matchPredbExact')->times($cleanerId > 0 ? 0 : 1)->with('File.Name-GROUP')->andReturn($this->hit());
        Search::shouldReceive('updateRelease')->once()->with(1);

        $result = (new ReflectionMethod($service, 'insertNZB'))->invoke($service, [
            'subject' => '"File.Name-GROUP.mkv" yEnc (1/1)', 'from' => 'poster', 'groupName' => 'alt.binaries.test',
            'useFName' => $nzbName, 'totalSize' => 1000, 'totalFiles' => 1, 'groups_id' => 1,
            'postDate' => '2026-10-01 00:00:00', 'nzbCategoryId' => 7010,
        ]);
        self::assertSame(NzbImportStatus::Inserted, $result);
        self::assertSame(7, DB::table('releases')->value('predb_id'));
        self::assertSame($cleanerId > 0 ? 'File.Name-GROUP' : 'Canonical.Scene-GROUP', DB::table('releases')->value('searchname'));
    }

    public function test_import_continues_when_backend_fails(): void
    {
        $this->app->instance(NzbService::class, $this->createMock(NzbService::class));
        Search::shouldReceive('matchPredbExact')->once()->andThrow(new RuntimeException('offline'));
        Search::shouldReceive('updateRelease')->once()->with(1);
        Log::shouldReceive('warning')->once();
        $service = new NzbImportService;

        $result = (new ReflectionMethod($service, 'insertNZB'))->invoke($service, [
            'subject' => 'subject yEnc (1/1)', 'from' => 'poster', 'groupName' => 'alt.binaries.test',
            'useFName' => 'File.Name-GROUP', 'totalSize' => 1000, 'totalFiles' => 1, 'groups_id' => 1,
            'postDate' => '2026-10-01 00:00:00', 'nzbCategoryId' => 7010,
        ]);

        self::assertSame(NzbImportStatus::Inserted, $result);
        self::assertSame(0, DB::table('releases')->value('predb_id'));
        self::assertSame('File.Name-GROUP', DB::table('releases')->value('searchname'));
    }

    /** @return array<string, array{mixed}> */
    public static function cleanerIds(): array
    {
        return ['zero' => [0], 'false' => [false], 'absent' => [null], 'positive' => [7]];
    }

    #[DataProvider('cleanerIds')]
    public function test_creation_matches_zero_or_absent_cleaner_ids_and_preserves_positive_ids(mixed $cleanerId): void
    {
        DB::statement('CREATE TABLE collections (id INTEGER PRIMARY KEY, subject TEXT, fromname TEXT, groups_id INTEGER, filecheck INTEGER, filesize INTEGER, totalfiles INTEGER, date TEXT, xref TEXT, collection_regexes_id INTEGER, releases_id INTEGER)');
        DB::statement('CREATE TABLE release_regexes (releases_id INTEGER, collection_regex_id INTEGER, naming_regex_id INTEGER)');
        DB::statement('CREATE TABLE releases_groups (releases_id INTEGER, groups_id INTEGER)');
        DB::statement('CREATE TABLE category_regexes (id INTEGER, group_regex TEXT, regex TEXT, status INTEGER, ordinal INTEGER, categories_id INTEGER)');
        DB::table('collections')->insert(['id' => 1, 'subject' => 'File.Name-GROUP', 'fromname' => 'poster', 'groups_id' => 1, 'filecheck' => CollectionFileCheckStatus::Sized->value, 'filesize' => 1000, 'totalfiles' => 1, 'date' => '2026-10-01 00:00:00', 'xref' => '', 'collection_regexes_id' => 0]);
        $cleaner = $this->createMock(ReleaseCleaningService::class);
        $meta = ['cleansubject' => 'File.Name-GROUP', 'properlynamed' => false];
        if ($cleanerId !== null) {
            $meta['predb'] = $cleanerId;
        }
        $cleaner->expects(self::once())->method('releaseCleaner')->willReturn($meta);
        Search::shouldReceive('matchPredbExact')->times((int) $cleanerId > 0 ? 0 : 1)->andReturn($this->hit());
        Search::shouldReceive('updateRelease')->once()->with(1);
        $service = new ReleaseCreationService($cleaner, $this->createMock(CollectionCleanupService::class), new ReleaseDuplicateFinder);

        self::assertSame(['added' => 1, 'dupes' => 0], $service->createReleases(null, 10, false));
        self::assertSame(7, DB::table('releases')->value('predb_id'));
    }

    public function test_partial_irc_update_indexes_persisted_filename_and_source(): void
    {
        Search::shouldReceive('updatePreDb')->once()->with($this->hit());
        $scraper = new class extends IRCScraper
        {
            public function __construct()
            {
                $this->_curPre = ['title' => 'Canonical.Scene-GROUP', 'nuked' => 2];
                $this->_oldPre = ['category' => ''];
                $this->_debug = false;
                $this->_silent = true;
            }

            public function updatePre(): void
            {
                $this->_updatePre();
            }

            protected function _doEcho(bool $new = true): void {}
        };
        $scraper->updatePre();
        self::assertSame(2, DB::table('predb')->value('nuked'));
    }

    public function test_elasticsearch_population_adds_mappings_and_writes_exact_fields_to_configured_index(): void
    {
        config(['search.drivers.elasticsearch.indexes.predb' => 'custom_pre']);
        Elasticsearch::swap(Mockery::mock());
        Elasticsearch::shouldReceive('indices->putMapping')->once()->with(['index' => 'custom_pre', 'body' => ['properties' => PredbSearchDocument::elasticsearchExactMappings()]])->andReturn([]);
        Elasticsearch::shouldReceive('bulk')->once()->with(['body' => [['index' => ['_index' => 'custom_pre', '_id' => 7]], PredbSearchDocument::forElasticsearch($this->hit())]])->andReturn(['errors' => false]);
        DB::partialMock()->shouldReceive('statement')->once()->with('SET SESSION group_concat_max_len = ?', [16384])->andReturnTrue();

        $this->artisan('nntmux:populate', ['--elastic' => true, '--predb' => true, '--count' => 1, '--batch-size' => 1])->assertSuccessful();
    }

    public function test_elasticsearch_offset_worker_adds_mappings_and_writes_exact_fields_to_configured_index(): void
    {
        config(['search.drivers.elasticsearch.indexes.predb' => 'custom_pre']);
        Elasticsearch::swap(Mockery::mock());
        Elasticsearch::shouldReceive('indices->putMapping')->once()->with(['index' => 'custom_pre', 'body' => ['properties' => PredbSearchDocument::elasticsearchExactMappings()]])->andReturn([]);
        Elasticsearch::shouldReceive('bulk')->once()->with(['body' => [['index' => ['_index' => 'custom_pre', '_id' => 7]], PredbSearchDocument::forElasticsearch($this->hit())]])->andReturn(['errors' => false]);
        DB::partialMock()->shouldReceive('statement')->once()->with('SET SESSION group_concat_max_len = ?', [65535])->andReturnTrue();

        $this->artisan('nntmux:offset-worker', ['--elastic' => true, '--predb' => true, '--limit' => 1, '--batch-size' => 1])->assertSuccessful();
    }

    public function test_fresh_elasticsearch_indexes_have_exact_keyword_mappings(): void
    {
        $created = [];
        $this->mockElasticIndexCreation($created);

        $this->artisan('nntmux:create-es-indexes', ['--create-missing' => true])->assertSuccessful();

        $properties = $created['custom_pre']['mappings']['properties'];
        self::assertSame(['type' => 'keyword'], $properties['title_exact']);
        self::assertSame(['type' => 'keyword'], $properties['filename_exact']);
    }

    public function test_offset_population_creates_configured_predb_index_with_exact_mappings(): void
    {
        $created = [];
        $this->mockElasticIndexCreation($created);
        $command = new NntmuxOffsetPopulate;
        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

        (new ReflectionMethod($command, 'clearIndex'))->invoke($command, 'elastic', 'predb');

        self::assertSame(['custom_pre'], array_keys($created));
        self::assertSame(['type' => 'keyword'], $created['custom_pre']['mappings']['properties']['title_exact']);
        self::assertSame(['type' => 'keyword'], $created['custom_pre']['mappings']['properties']['filename_exact']);
    }

    public function test_database_reset_recreates_configured_predb_index_with_exact_mappings(): void
    {
        foreach (['first_record', 'first_record_postdate', 'last_record', 'last_record_postdate', 'last_updated'] as $column) {
            DB::statement('ALTER TABLE usenet_groups ADD '.$column.' TEXT');
        }
        $created = [];
        $this->mockElasticIndexCreation($created);
        $this->app->detectEnvironment(static fn (): string => 'local');
        config(['search.default' => 'elasticsearch', 'nntmux_settings.path_to_nzbs' => '/unused']);
        DB::partialMock()->shouldReceive('statement')->andReturnTrue();
        File::shouldReceive('allFiles')->twice()->andReturn([]);
        File::shouldReceive('delete')->once()->with([])->andReturnTrue();
        File::shouldReceive('exists')->andReturnFalse();

        $this->artisan('nntmux:resetdb')
            ->expectsConfirmation('This command removes all releases, nzb files, samples, previews , nfos, truncates all article tables and resets all groups. Are you sure you want reset the DB?', 'yes')
            ->assertSuccessful();

        self::assertArrayNotHasKey('predb', $created);
        self::assertSame(['type' => 'keyword'], $created['custom_pre']['mappings']['properties']['title_exact']);
        self::assertSame(['type' => 'keyword'], $created['custom_pre']['mappings']['properties']['filename_exact']);
    }

    /** @param array<string, array<string, mixed>> $created */
    private function mockElasticIndexCreation(array &$created): void
    {
        config(['search.drivers.elasticsearch.indexes.predb' => 'custom_pre']);
        $http = $this->createMock(ClientInterface::class);
        $http->method('sendRequest')->willReturnCallback(static function (RequestInterface $request) use (&$created): HttpResponse {
            if ($request->getMethod() === 'HEAD') {
                return new HttpResponse(404, ['X-Elastic-Product' => 'Elasticsearch']);
            }
            self::assertSame('PUT', $request->getMethod());
            $created[ltrim($request->getUri()->getPath(), '/')] = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);

            return new HttpResponse(200, ['X-Elastic-Product' => 'Elasticsearch', 'Content-Type' => 'application/json'], '{"acknowledged":true}');
        });
        $client = ClientBuilder::create()->setHosts(['http://localhost:9200'])->setHttpClient($http)->build();
        $this->app->instance('elasticsearch', $client);
        Elasticsearch::swap($client);
    }

    /** @return array{id: int, title: string, filename: string, source: string} */
    private function hit(): array
    {
        return ['id' => 7, 'title' => 'Canonical.Scene-GROUP', 'filename' => 'File.Name-GROUP', 'source' => 'srrdb'];
    }

    private function environment(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        } else {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
    }
}
