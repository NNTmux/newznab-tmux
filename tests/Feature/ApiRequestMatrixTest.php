<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Facades\Search;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\ApiV2Controller;
use App\Models\Category;
use App\Services\Api\ApiUsageService;
use App\Services\Nzb\NzbService;
use App\Services\Releases\ReleaseBrowseService;
use App\Services\Releases\ReleaseSearchService;
use App\Services\Search\DTO\ReleaseSearchQuery;
use App\Services\Search\DTO\SearchPage;
use App\Services\Search\Support\SearchFailureTracker;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Mockery;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

class ApiRequestMatrixTest extends TestCase
{
    private string $nzbUploadFolder;

    private string $databasePath;

    /** @var array<string, string|false> */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-api-matrix-test.sqlite';
        $this->originalEnvironment = [
            'APP_ENV' => getenv('APP_ENV'),
            'DB_CONNECTION' => getenv('DB_CONNECTION'),
            'DB_DATABASE' => getenv('DB_DATABASE'),
        ];

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        $pdo = new PDO('sqlite:'.$this->databasePath);

        $this->setEnvironmentValue('APP_ENV', 'testing');
        $this->setEnvironmentValue('DB_CONNECTION', 'sqlite');
        $this->setEnvironmentValue('DB_DATABASE', $this->databasePath);

        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'mail.from.address' => 'api-matrix@example.test',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);

        DB::purge();
        DB::reconnect();
        Cache::flush();
        Schema::dropAllTables();

        $this->nzbUploadFolder = sys_get_temp_dir().'/nntmux-api-matrix-'.bin2hex(random_bytes(6));
        config(['nntmux.nzb_upload_folder' => $this->nzbUploadFolder]);

        $this->createSchema();
        $this->seedData();
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->nzbUploadFolder);

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();

        foreach ($this->originalEnvironment as $key => $value) {
            $this->setEnvironmentValue($key, $value === false ? null : $value);
        }
    }

    public function test_v1_invalid_sort_returns_xml_201_error(): void
    {
        $token = (string) DB::table('users')->value('api_token');

        $response = $this->get('/api/v1/api?t=search&apikey='.$token.'&q=test&sort=bad_value');

        $response->assertBadRequest();
        $response->assertSee('<error code="201"', false);
        $response->assertSee('Incorrect parameter (sort', false);
    }

    public function test_v1_invalid_maxage_returns_xml_201_error(): void
    {
        $token = (string) DB::table('users')->value('api_token');

        $response = $this->get('/api/v1/api?t=search&apikey='.$token.'&q=test&maxage=abc');

        $response->assertBadRequest();
        $response->assertSee('<error code="201"', false);
        $response->assertSee('maxage must be numeric', false);
    }

    public function test_v1_invalid_apikey_returns_xml_401_error(): void
    {
        $response = $this->get('/api/v1/api?t=search&apikey=invalid-token&q=test');

        $response->assertUnauthorized();
        $response->assertSee('<error code="100" description="Incorrect user credentials (wrong API key)"/>', false);
    }

    public function test_v2_invalid_api_token_returns_json_401_error(): void
    {
        $this->getJson('/api/v2/search?api_token=invalid-token&id=test')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'Incorrect user credentials');
    }

    public function test_v2_disabled_user_is_rejected_before_request_is_recorded(): void
    {
        DB::table('users')->insert([
            'username' => 'disabled_matrix_user',
            'email' => 'disabled-matrix@example.test',
            'password' => bcrypt('secret'),
            'roles_id' => 3,
            'api_token' => 'disabled-matrix-token',
            'verified' => 1,
            'email_verified_at' => now(),
            'rate_limit' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/v2/search?api_token=disabled-matrix-token&id=test')
            ->assertForbidden()
            ->assertJsonPath('error', 'Account suspended');

        $this->assertSame(0, DB::table('user_requests')->count());
    }

    public function test_v2_invalid_sort_returns_json_400_error(): void
    {
        $token = (string) DB::table('users')->value('api_token');

        $this->getJson('/api/v2/search?api_token='.$token.'&id=test&sort=bad_value')
            ->assertStatus(400)
            ->assertJsonPath('error', 'Incorrect parameter (sort must be one of: cat_asc/desc, name_asc/desc, size_asc/desc, files_asc/desc, stats_asc/desc, posted_asc/desc)');
    }

    public function test_v2_invalid_maxage_returns_json_400_error(): void
    {
        $token = (string) DB::table('users')->value('api_token');

        $this->getJson('/api/v2/search?api_token='.$token.'&id=test&maxage=abc')
            ->assertStatus(400)
            ->assertJsonPath('error', 'Incorrect parameter (maxage must be numeric)');
    }

    public function test_v2_search_reuses_cached_release_rows(): void
    {
        $token = (string) DB::table('users')->value('api_token');
        $request = Request::create('/api/v2/search', 'GET', [
            'api_token' => $token,
            'id' => 'ubuntu',
        ]);

        $releaseSearchService = Mockery::mock(ReleaseSearchService::class);
        $releaseSearchService->shouldReceive('apiSearch')
            ->once()
            ->with('ubuntu', -1, 0, 100, -1, [5030], [-1], 0, 'posted_desc', null)
            ->andReturn(collect());

        $releaseBrowseService = Mockery::mock(ReleaseBrowseService::class);
        $releaseBrowseService->shouldNotReceive('getBrowseRangeForApi');

        $controller = new ApiV2Controller($releaseSearchService, $releaseBrowseService);

        $firstResponse = $controller->apiSearch($request);
        $secondResponse = $controller->apiSearch($request);

        $this->assertSame(200, $firstResponse->getStatusCode());
        $this->assertSame(200, $secondResponse->getStatusCode());
        $this->assertSame([], $firstResponse->getData(true)['results']);
        $this->assertSame([], $secondResponse->getData(true)['results']);
    }

    public function test_v2_search_keeps_category_separator_unescaped_in_json_body(): void
    {
        $token = (string) DB::table('users')->value('api_token');
        $request = Request::create('/api/v2/search', 'GET', [
            'api_token' => $token,
            'id' => 'ubuntu',
        ]);

        $releaseSearchService = Mockery::mock(ReleaseSearchService::class);
        $releaseSearchService->shouldReceive('apiSearch')
            ->once()
            ->with('ubuntu', -1, 0, 100, -1, [5030], [-1], 0, 'posted_desc', null)
            ->andReturn(collect([
                (object) [
                    '_totalrows' => 1,
                    'searchname' => 'Ubuntu.Movie.Release',
                    'guid' => 'movie-release-guid',
                    'categories_id' => 2040,
                    'category_name' => 'Movies > WEBDL',
                    'adddate' => '2026-01-03 00:00:00',
                    'size' => 123456,
                    'totalpart' => 10,
                    'grabs' => 2,
                    'comments' => 1,
                    'passwordstatus' => 0,
                    'postdate' => '2026-01-02 00:00:00',
                ],
            ]));

        $releaseBrowseService = Mockery::mock(ReleaseBrowseService::class);
        $releaseBrowseService->shouldNotReceive('getBrowseRangeForApi');

        $controller = new ApiV2Controller($releaseSearchService, $releaseBrowseService);
        $response = $controller->apiSearch($request);

        $content = $response->getContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertIsString($content);
        $this->assertSame('Movies > WEBDL', $response->getData(true)['results'][0]['category_name']);
        $this->assertStringContainsString('"category_name":"Movies > WEBDL"', $content);
        $this->assertStringNotContainsString('\u003E', $content);
    }

    public function test_v1_search_keeps_category_separator_unescaped_in_json_body(): void
    {
        $token = (string) DB::table('users')->value('api_token');
        $request = Request::create('/api/v1/api', 'GET', [
            't' => 'search',
            'o' => 'json',
            'apikey' => $token,
            'q' => 'ubuntu',
        ]);

        $releaseSearchService = Mockery::mock(ReleaseSearchService::class);
        $releaseSearchService->shouldReceive('apiSearch')
            ->once()
            ->with('ubuntu', -1, 0, 100, -1, [5030], [-1], 0, 'posted_desc')
            ->andReturn(collect([
                (object) [
                    '_totalrows' => 1,
                    'searchname' => 'Ubuntu.Movie.Release',
                    'guid' => 'movie-release-guid',
                    'categories_id' => 2040,
                    'category_name' => 'Movies > WEBDL',
                    'adddate' => '2026-01-03 00:00:00',
                    'size' => 123456,
                    'totalpart' => 10,
                    'grabs' => 2,
                    'comments' => 1,
                    'passwordstatus' => 0,
                    'postdate' => '2026-01-02 00:00:00',
                ],
            ]));

        $releaseBrowseService = Mockery::mock(ReleaseBrowseService::class);
        $releaseBrowseService->shouldNotReceive('getBrowseRangeForApi');

        $controller = new ApiController($releaseSearchService, $releaseBrowseService);
        $response = $controller->api($request);

        $this->assertNotNull($response);
        $content = $response->getContent();

        $this->assertIsString($content);
        $this->assertStringContainsString('"category":"Movies > WEBDL"', $content);
        $this->assertStringNotContainsString('\u003E', $content);
    }

    public function test_v1_search_reuses_cached_release_rows(): void
    {
        $token = (string) DB::table('users')->value('api_token');
        $request = Request::create('/api/v1/api', 'GET', [
            't' => 'search',
            'apikey' => $token,
            'q' => 'ubuntu',
        ]);

        $releaseSearchService = Mockery::mock(ReleaseSearchService::class);
        $releaseSearchService->shouldReceive('apiSearch')
            ->once()
            ->with('ubuntu', -1, 0, 100, -1, [5030], [-1], 0, 'posted_desc')
            ->andReturn(collect());

        $releaseBrowseService = Mockery::mock(ReleaseBrowseService::class);
        $releaseBrowseService->shouldNotReceive('getBrowseRangeForApi');

        $controller = new class($releaseSearchService, $releaseBrowseService) extends ApiController
        {
            /**
             * @var array{data:mixed,params:array<string,mixed>,xml:bool,offset:int,type:string}|null
             */
            public ?array $capturedOutput = null;

            public function output(mixed $data, array $params, bool $xml, int $offset, string $type = '', array $headers = [])
            {
                $this->capturedOutput = [
                    'data' => $data,
                    'params' => $params,
                    'xml' => $xml,
                    'offset' => $offset,
                    'type' => $type,
                ];
            }
        };

        $controller->api($request);
        $this->assertNotNull($controller->capturedOutput);
        $this->assertSame('api', $controller->capturedOutput['type']);
        $this->assertSame(1, DB::table('user_requests')->count());

        $controller->api($request);
        $this->assertSame('api', $controller->capturedOutput['type']);
        $this->assertSame(2, DB::table('user_requests')->count());
    }

    public function test_v1_movie_without_search_params_returns_recent_movie_feed(): void
    {
        $token = (string) DB::table('users')->value('api_token');
        $request = Request::create('/api/v1/api', 'GET', [
            't' => 'm',
            'apikey' => $token,
        ]);

        $releaseSearchService = Mockery::mock(ReleaseSearchService::class);
        $releaseBrowseService = Mockery::mock(ReleaseBrowseService::class);
        $releaseBrowseService->shouldReceive('getBrowseRangeForApi')
            ->once()
            ->andReturn(collect());

        $controller = new class($releaseSearchService, $releaseBrowseService) extends ApiController
        {
            /**
             * @var array{data:mixed,params:array<string,mixed>,xml:bool,offset:int,type:string}|null
             */
            public ?array $capturedOutput = null;

            public function output(mixed $data, array $params, bool $xml, int $offset, string $type = '', array $headers = [])
            {
                $this->capturedOutput = [
                    'data' => $data,
                    'params' => $params,
                    'xml' => $xml,
                    'offset' => $offset,
                    'type' => $type,
                ];
            }
        };

        $controller->api($request);
        $this->assertNotNull($controller->capturedOutput);
        $this->assertSame('api', $controller->capturedOutput['type']);
        $this->assertInstanceOf(Collection::class, $controller->capturedOutput['data']);
    }

    public function test_v1_tv_without_search_params_returns_recent_tv_feed(): void
    {
        $token = (string) DB::table('users')->value('api_token');
        $request = Request::create('/api/v1/api', 'GET', [
            't' => 'tv',
            'apikey' => $token,
        ]);

        $releaseSearchService = Mockery::mock(ReleaseSearchService::class);
        $releaseBrowseService = Mockery::mock(ReleaseBrowseService::class);
        $releaseBrowseService->shouldReceive('getBrowseRangeForApi')
            ->once()
            ->andReturn(collect());

        $controller = new class($releaseSearchService, $releaseBrowseService) extends ApiController
        {
            /**
             * @var array{data:mixed,params:array<string,mixed>,xml:bool,offset:int,type:string}|null
             */
            public ?array $capturedOutput = null;

            public function output(mixed $data, array $params, bool $xml, int $offset, string $type = '', array $headers = [])
            {
                $this->capturedOutput = [
                    'data' => $data,
                    'params' => $params,
                    'xml' => $xml,
                    'offset' => $offset,
                    'type' => $type,
                ];
            }
        };

        $controller->api($request);
        $this->assertNotNull($controller->capturedOutput);
        $this->assertSame('api', $controller->capturedOutput['type']);
        $this->assertInstanceOf(Collection::class, $controller->capturedOutput['data']);
    }

    public function test_v1_music_without_query_browses_requested_categories(): void
    {
        $token = (string) DB::table('users')->value('api_token');
        $request = Request::create('/api/v1/api', 'GET', [
            't' => 'music',
            'cat' => '3000,3010,3020,3030,3040,3050,3060,3999',
            'extended' => '1',
            'apikey' => $token,
        ]);

        $releaseSearchService = Mockery::mock(ReleaseSearchService::class);
        $releaseSearchService->shouldNotReceive('apiMusicSearch');
        $releaseBrowseService = Mockery::mock(ReleaseBrowseService::class);
        $releaseBrowseService->shouldReceive('getBrowseRangeForApi')
            ->once()
            ->with(
                1,
                ['3000', '3010', '3020', '3030', '3040', '3050', '3060', '3999'],
                0,
                100,
                'posted_desc',
                -1,
                [5030],
                -1,
                0
            )
            ->andReturn(collect());

        $controller = new class($releaseSearchService, $releaseBrowseService) extends ApiController
        {
            /**
             * @var array{data:mixed,params:array<string,mixed>,xml:bool,offset:int,type:string}|null
             */
            public ?array $capturedOutput = null;

            public function output(mixed $data, array $params, bool $xml, int $offset, string $type = '', array $headers = [])
            {
                $this->capturedOutput = [
                    'data' => $data,
                    'params' => $params,
                    'xml' => $xml,
                    'offset' => $offset,
                    'type' => $type,
                ];
            }
        };

        $controller->api($request);
        $this->assertNotNull($controller->capturedOutput);
        $this->assertSame('api', $controller->capturedOutput['type']);
        $this->assertInstanceOf(Collection::class, $controller->capturedOutput['data']);
    }

    public function test_v1_book_without_query_browses_requested_categories(): void
    {
        $token = (string) DB::table('users')->value('api_token');
        $request = Request::create('/api/v1/api', 'GET', [
            't' => 'book',
            'cat' => '3030,7020,8010',
            'extended' => '1',
            'apikey' => $token,
        ]);

        $releaseSearchService = Mockery::mock(ReleaseSearchService::class);
        $releaseSearchService->shouldNotReceive('apiBookSearch');
        $releaseBrowseService = Mockery::mock(ReleaseBrowseService::class);
        $releaseBrowseService->shouldReceive('getBrowseRangeForApi')
            ->once()
            ->with(
                1,
                ['3030', '7020', '8010'],
                0,
                100,
                'posted_desc',
                -1,
                [5030],
                -1,
                0
            )
            ->andReturn(collect());

        $controller = new class($releaseSearchService, $releaseBrowseService) extends ApiController
        {
            /**
             * @var array{data:mixed,params:array<string,mixed>,xml:bool,offset:int,type:string}|null
             */
            public ?array $capturedOutput = null;

            public function output(mixed $data, array $params, bool $xml, int $offset, string $type = '', array $headers = [])
            {
                $this->capturedOutput = [
                    'data' => $data,
                    'params' => $params,
                    'xml' => $xml,
                    'offset' => $offset,
                    'type' => $type,
                ];
            }
        };

        $controller->api($request);
        $this->assertNotNull($controller->capturedOutput);
        $this->assertSame('api', $controller->capturedOutput['type']);
        $this->assertInstanceOf(Collection::class, $controller->capturedOutput['data']);
    }

    public function test_v1_music_and_book_without_query_default_to_their_root_categories(): void
    {
        $token = (string) DB::table('users')->value('api_token');

        foreach ([
            'music' => [Category::MUSIC_ROOT],
            'book' => [Category::BOOKS_ROOT],
        ] as $type => $expectedCategory) {
            $request = Request::create('/api/v1/api', 'GET', [
                't' => $type,
                'apikey' => $token,
            ]);

            $releaseSearchService = Mockery::mock(ReleaseSearchService::class);
            $releaseBrowseService = Mockery::mock(ReleaseBrowseService::class);
            $releaseBrowseService->shouldReceive('getBrowseRangeForApi')
                ->once()
                ->with(
                    1,
                    $expectedCategory,
                    0,
                    100,
                    'posted_desc',
                    -1,
                    [5030],
                    -1,
                    0
                )
                ->andReturn(collect());

            $controller = new class($releaseSearchService, $releaseBrowseService) extends ApiController
            {
                public ?array $capturedOutput = null;

                public function output(mixed $data, array $params, bool $xml, int $offset, string $type = '', array $headers = [])
                {
                    $this->capturedOutput = compact('data', 'params', 'xml', 'offset', 'type');
                }
            };

            $controller->api($request);
            $this->assertNotNull($controller->capturedOutput);
            $this->assertSame('api', $controller->capturedOutput['type']);
        }
    }

    public function test_v2_audio_without_id_browses_requested_categories(): void
    {
        $token = (string) DB::table('users')->value('api_token');
        $request = Request::create('/api/v2/audio', 'GET', [
            'cat' => '3000,3010,3020,3030,3040,3050,3060,3999',
            'api_token' => $token,
        ]);

        $releaseSearchService = Mockery::mock(ReleaseSearchService::class);
        $releaseSearchService->shouldNotReceive('apiMusicSearch');
        $releaseBrowseService = Mockery::mock(ReleaseBrowseService::class);
        $releaseBrowseService->shouldReceive('getBrowseRangeForApi')
            ->once()
            ->with(
                1,
                ['3000', '3010', '3020', '3030', '3040', '3050', '3060', '3999'],
                0,
                100,
                'posted_desc',
                -1,
                [5030],
                -1,
                0
            )
            ->andReturn(collect());

        $controller = new ApiV2Controller($releaseSearchService, $releaseBrowseService);

        $response = $controller->audio($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $response->getData(true)['results']);
    }

    public function test_v2_books_without_id_browses_requested_categories(): void
    {
        $token = (string) DB::table('users')->value('api_token');
        $request = Request::create('/api/v2/books', 'GET', [
            'cat' => '3030,7020,8010',
            'api_token' => $token,
        ]);

        $releaseSearchService = Mockery::mock(ReleaseSearchService::class);
        $releaseSearchService->shouldNotReceive('apiBookSearch');
        $releaseBrowseService = Mockery::mock(ReleaseBrowseService::class);
        $releaseBrowseService->shouldReceive('getBrowseRangeForApi')
            ->once()
            ->with(
                1,
                ['3030', '7020', '8010'],
                0,
                100,
                'posted_desc',
                -1,
                [5030],
                -1,
                0
            )
            ->andReturn(collect());

        $controller = new ApiV2Controller($releaseSearchService, $releaseBrowseService);

        $response = $controller->books($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $response->getData(true)['results']);
    }

    public function test_v2_audio_and_books_without_id_default_to_their_root_categories(): void
    {
        $token = (string) DB::table('users')->value('api_token');

        foreach ([
            'audio' => ['method' => 'audio', 'category' => [Category::MUSIC_ROOT]],
            'books' => ['method' => 'books', 'category' => [Category::BOOKS_ROOT]],
        ] as $endpoint => $expectation) {
            $request = Request::create('/api/v2/'.$endpoint, 'GET', [
                'api_token' => $token,
            ]);

            $releaseSearchService = Mockery::mock(ReleaseSearchService::class);
            $releaseBrowseService = Mockery::mock(ReleaseBrowseService::class);
            $releaseBrowseService->shouldReceive('getBrowseRangeForApi')
                ->once()
                ->with(
                    1,
                    $expectation['category'],
                    0,
                    100,
                    'posted_desc',
                    -1,
                    [5030],
                    -1,
                    0
                )
                ->andReturn(collect());

            $controller = new ApiV2Controller($releaseSearchService, $releaseBrowseService);
            $response = $controller->{$expectation['method']}($request);

            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame([], $response->getData(true)['results']);
        }
    }

    public function test_v2_movie_requires_query_or_external_id(): void
    {
        $token = (string) DB::table('users')->value('api_token');

        $this->getJson('/api/v2/movies?api_token='.$token)
            ->assertStatus(400)
            ->assertJsonPath('error', 'Specify id (query), imdbid, tmdbid, or traktid');
    }

    public function test_v2_tv_requires_query_or_external_id(): void
    {
        $token = (string) DB::table('users')->value('api_token');

        $this->getJson('/api/v2/tv?api_token='.$token)
            ->assertStatus(400)
            ->assertJsonPath('error', 'Specify id (query), vid, tvdbid, traktid, rid, tvmazeid, imdbid, or tmdbid');
    }

    public function test_v1_caps_menu_data_includes_groups_and_genres(): void
    {
        $apiController = app(ApiController::class);
        $reflection = new ReflectionClass($apiController);
        $typeProperty = $reflection->getProperty('type');
        $typeProperty->setAccessible(true);
        $typeProperty->setValue($apiController, 'caps');

        $menu = $apiController->getForMenu();

        $this->assertSame('alt.binaries.test', $menu['groups'][0]['name']);
        $this->assertSame('Test Genre', $menu['genres'][0]['name']);
    }

    public function test_v2_capabilities_includes_groups_and_genres(): void
    {
        $this->getJson('/api/v2/capabilities')
            ->assertOk()
            ->assertJsonPath('groups.0.name', 'alt.binaries.test')
            ->assertJsonPath('genres.0.name', 'Test Genre');
    }

    public function test_v2_details_response_shape_is_unchanged(): void
    {
        $token = (string) DB::table('users')->value('api_token');

        $this->getJson('/api/v2/details?api_token='.$token.'&id=release-guid')
            ->assertOk()
            ->assertJsonPath('title', 'Ubuntu.Release')
            ->assertJsonPath('details', 'http://localhost/details/release-guid')
            ->assertJsonPath('link', 'http://localhost/getnzb?id=release-guid.nzb&r='.$token)
            ->assertJsonPath('category', 5030)
            ->assertJsonPath('category_name', 'TV > SD')
            ->assertJsonPath('size', 123456)
            ->assertJsonPath('files', 10)
            ->assertJsonPath('grabs', 2)
            ->assertJsonPath('comments', 1)
            ->assertJsonPath('password', 0);
    }

    public function test_v1_nzbadd_stages_differently_named_nzb_and_nfo_fields(): void
    {
        $token = (string) DB::table('users')->value('api_token');

        $this->post('/api/v1/api?t=nzbadd&apikey='.$token, [
            'cat' => '5040',
            'nzb' => UploadedFile::fake()->createWithContent(
                'Release.nzb',
                '<?xml version="1.0"?><nzb xmlns="http://www.newzbin.com/DTD/2003/nzb"></nzb>',
            ),
            'nfo' => UploadedFile::fake()->createWithContent('scene-info.nfo', 'release information'),
        ])->assertOk()
            ->assertHeader('Content-Type', 'text/xml; charset=UTF-8')
            ->assertSee('<success id="0" guid="" categoryid="5040" name="Release" />', false);

        $this->assertNotNull($this->findStagedFile('Release.nzb'));
        $this->assertNotNull($this->findStagedFile('scene-info.nfo'));
        $this->assertNotNull($this->findStagedFile('nntmux-upload.json'));
    }

    public function test_v1_nzbadd_preserves_legacy_file_fallback_and_rejects_mixed_contracts(): void
    {
        $token = (string) DB::table('users')->value('api_token');

        $this->post('/api/v1/api?t=nzbadd&apikey='.$token, [
            'file' => UploadedFile::fake()->createWithContent(
                'Legacy.nzb',
                '<?xml version="1.0"?><nzb xmlns="http://www.newzbin.com/DTD/2003/nzb"></nzb>',
            ),
        ])->assertOk()
            ->assertSee('name="Legacy"', false);

        $this->assertNotNull($this->findStagedFile('Legacy.nzb'));

        $this->post('/api/v1/api?t=nzbadd&apikey='.$token, [
            'file' => UploadedFile::fake()->createWithContent(
                'Mixed.nzb',
                '<?xml version="1.0"?><nzb xmlns="http://www.newzbin.com/DTD/2003/nzb"></nzb>',
            ),
            'nzb' => UploadedFile::fake()->createWithContent(
                'Modern.nzb',
                '<?xml version="1.0"?><nzb xmlns="http://www.newzbin.com/DTD/2003/nzb"></nzb>',
            ),
        ])->assertBadRequest()
            ->assertSee('<error code="201"', false);
    }

    public function test_v2_nzbadd_stages_a_differently_named_nzb_and_nfo(): void
    {
        $token = (string) DB::table('users')->value('api_token');
        $nzb = UploadedFile::fake()->createWithContent(
            'Release.nzb',
            '<?xml version="1.0"?><nzb xmlns="http://www.newzbin.com/DTD/2003/nzb"></nzb>',
        );
        $nfo = UploadedFile::fake()->createWithContent('scene-info.nfo', 'release information');

        $this->post('/api/v2/nzbadd', [
            'api_token' => $token,
            'cat' => '5040',
            'nzb' => $nzb,
            'nfo' => $nfo,
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'staged')
            ->assertJsonPath('name', 'Release')
            ->assertJsonPath('category', '5040')
            ->assertJsonPath('files.nzb.filename', 'Release.nzb')
            ->assertJsonPath('files.nfo.filename', 'scene-info.nfo');

        $this->assertNotNull($this->findStagedFile('Release.nzb'));
        $this->assertNotNull($this->findStagedFile('scene-info.nfo'));
        $this->assertNotNull($this->findStagedFile('nntmux-upload.json'));
        $this->assertSame(0, DB::table('user_requests')->count());
    }

    public function test_v2_nzbadd_ignores_an_exhausted_api_quota_without_recording_usage(): void
    {
        $userId = (int) DB::table('users')->value('id');
        $token = (string) DB::table('users')->value('api_token');
        DB::table('roles')->where('id', 1)->update(['apirequests' => 0]);
        DB::table('user_requests')->insert([
            'users_id' => $userId,
            'request' => '/api/v2/search?api_token=redacted',
            'timestamp' => now(),
        ]);

        $this->post('/api/v2/nzbadd', [
            'api_token' => $token,
            'nzb' => UploadedFile::fake()->createWithContent(
                'QuotaExempt.nzb',
                '<?xml version="1.0"?><nzb xmlns="http://www.newzbin.com/DTD/2003/nzb"></nzb>',
            ),
        ])->assertCreated();

        $this->assertSame(1, DB::table('user_requests')->count());
        $this->assertNotNull($this->findStagedFile('QuotaExempt.nzb'));
    }

    public function test_v2_nzbadd_requires_posting_privileges(): void
    {
        DB::table('users')->update(['can_post' => false]);
        $token = (string) DB::table('users')->value('api_token');

        $this->post('/api/v2/nzbadd', [
            'api_token' => $token,
            'nzb' => UploadedFile::fake()->createWithContent(
                'Release.nzb',
                '<?xml version="1.0"?><nzb xmlns="http://www.newzbin.com/DTD/2003/nzb"></nzb>',
            ),
        ])->assertForbidden()
            ->assertJsonPath('error', 'Insufficient privileges/not authorized');
    }

    public function test_v2_query_search_passes_the_same_arguments_as_the_equivalent_get(): void
    {
        $token = $this->apiToken();
        $search = $this->bindSearchMocks();
        $search->shouldReceive('apiSearch')
            ->once()
            ->with('ubuntu', -1, 0, 100, -1, [5030], ['2000', '5030'], 0, 'posted_desc', null)
            ->andReturn(collect([$this->releaseRow(1)]));

        $query = $this->queryJson('/api/v2/search', ['api_token' => $token, 'id' => 'ubuntu', 'cat' => [2000, 5030]]);
        // Same canonical filters hit the same row-cache entry, so the mock is not called again.
        $get = $this->getJson('/api/v2/search?api_token='.$token.'&id=ubuntu&cat=2000,5030');

        $query->assertOk()->assertJsonPath('results.0.title', 'Ubuntu.Movie.Release');
        $get->assertOk();
        $this->assertSame($get->json('results'), $query->json('results'));
        $this->assertSame('/api/v2/search?cat=2000%2C5030&id=ubuntu', DB::table('user_requests')->value('request'));
    }

    public function test_v1_query_search_returns_xml(): void
    {
        $search = $this->bindSearchMocks();
        $search->shouldReceive('apiSearch')
            ->once()
            ->with('ubuntu', -1, 0, 100, -1, [5030], [-1], 0, 'posted_desc')
            ->andReturn(collect());

        $response = $this->queryJson('/api/v1/api', ['t' => 'search', 'apikey' => $this->apiToken(), 'q' => 'ubuntu']);

        $response->assertOk();
        $this->assertStringContainsString('xml', (string) $response->headers->get('Content-Type'));
        $this->assertSame(1, DB::table('user_requests')->count());
    }

    public function test_legacy_post_and_head_requests_are_unchanged(): void
    {
        $post = $this->post('/api/v1/api', ['t' => 'caps']);

        $post->assertOk()->assertHeader('Accept-Query', 'application/json');
        $this->assertStringNotContainsString('no-store', (string) $post->headers->get('Cache-Control'));

        $this->call('HEAD', '/api/v2/capabilities')->assertOk();
    }

    public function test_v2_query_rejects_missing_and_invalid_tokens_in_the_body(): void
    {
        $this->queryJson('/api/v2/search', ['id' => 'ubuntu'])
            ->assertBadRequest()
            ->assertJsonPath('error', 'Missing parameter (api_token)');

        $this->queryJson('/api/v2/search', ['api_token' => 'invalid-token', 'id' => 'ubuntu'])
            ->assertUnauthorized()
            ->assertJsonPath('error', 'Incorrect user credentials');
    }

    public function test_v2_query_is_rejected_once_the_daily_quota_is_exhausted(): void
    {
        DB::table('roles')->where('id', 1)->update(['apirequests' => 0]);
        DB::table('user_requests')->insert([
            'users_id' => (int) DB::table('users')->value('id'),
            'request' => '/api/v2/search?id=earlier',
            'timestamp' => now(),
        ]);

        $this->queryJson('/api/v2/search', ['api_token' => $this->apiToken(), 'id' => 'ubuntu'])
            ->assertTooManyRequests()
            ->assertJsonPath('error', 'Request limit reached');
    }

    public function test_v2_query_rate_limit_reads_the_body_token_and_429_is_not_cacheable(): void
    {
        DB::table('users')->update(['rate_limit' => 1]);
        $this->bindSearchMocks()->shouldReceive('apiSearch')->once()->andReturn(collect());
        $body = ['api_token' => $this->apiToken(), 'id' => 'ubuntu'];

        $this->queryJson('/api/v2/search', $body)->assertOk();
        $limited = $this->queryJson('/api/v2/search', $body);

        $limited->assertTooManyRequests()->assertHeader('X-RateLimit-Remaining', '0');
        $this->assertNotCacheable($limited);
    }

    public function test_query_requires_exactly_application_json(): void
    {
        $uri = '/api/v2/search';
        $body = (string) json_encode(['api_token' => $this->apiToken(), 'id' => 'ubuntu']);

        $this->queryRaw($uri, $body, 'application/vnd.api+json')->assertStatus(415);
        $this->queryRaw($uri, 'id=ubuntu', 'application/x-www-form-urlencoded')->assertStatus(415);
        $this->queryRaw($uri, $body, 'application/json; charset=latin1')->assertStatus(415);
        $this->assertSame(0, DB::table('user_requests')->count());
    }

    public function test_query_body_over_the_configured_limit_is_rejected(): void
    {
        config(['nntmux.api.query_max_body_bytes' => 64]);

        $this->queryJson('/api/v2/search', ['api_token' => $this->apiToken(), 'id' => str_repeat('a', 64)])
            ->assertStatus(413)
            ->assertJsonPath('error', 'QUERY body exceeds the maximum size');
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function invalidQueryBodies(): array
    {
        return [
            'malformed JSON' => ['{"id":', 'QUERY body must be valid JSON without nested values'],
            'JSON array' => ['["ubuntu"]', 'QUERY body must be a JSON object'],
            'nested object' => ['{"id":{"a":1}}', 'Parameter id has an unsupported type'],
            'deeply nested' => ['{"cat":[[2000]]}', 'QUERY body must be valid JSON without nested values'],
            'JSON null' => ['{"id":null}', 'Parameter id has an unsupported type'],
            'boolean' => ['{"id":true}', 'Parameter id has an unsupported type'],
            'cat as object' => ['{"cat":{"0":2000,"1":5030}}', 'Parameter cat has an unsupported type'],
            'list for a scalar parameter' => ['{"id":["a","b"]}', 'Parameter id has an unsupported type'],
        ];
    }

    #[DataProvider('invalidQueryBodies')]
    public function test_query_rejects_invalid_bodies(string $body, string $error): void
    {
        $this->queryRaw('/api/v2/search?api_token='.$this->apiToken(), $body)
            ->assertBadRequest()
            ->assertJsonPath('error', $error);

        $this->assertSame(0, DB::table('user_requests')->count());
    }

    public function test_query_rejects_nested_url_parameters(): void
    {
        $this->queryJson('/api/v2/search?group[a]=x', ['api_token' => $this->apiToken(), 'id' => 'ubuntu'])
            ->assertBadRequest()
            ->assertJsonPath('error', 'Parameter group has an unsupported type');
    }

    public function test_query_rejects_a_parameter_sent_in_both_url_and_body(): void
    {
        $this->queryJson('/api/v2/search?id=debian', ['api_token' => $this->apiToken(), 'id' => 'ubuntu'])
            ->assertBadRequest()
            ->assertJsonPath('error', 'Parameter id must not be sent in both the URL and the body');
    }

    public function test_query_rejects_hostile_field_names_with_a_fixed_well_formed_xml_error(): void
    {
        $response = $this->queryRaw('/api/v1/api', (string) json_encode([
            't' => 'search',
            "<a\"\r\n>" => 1,
        ]));

        $response->assertBadRequest();
        $xml = simplexml_load_string((string) $response->getContent());
        $this->assertNotFalse($xml);
        $this->assertSame('201', (string) $xml['code']);
        $this->assertSame('Parameter names may only contain letters, digits and underscores (max 32)', (string) $xml['description']);
    }

    public function test_query_rejects_more_than_64_parameters(): void
    {
        $body = ['api_token' => $this->apiToken()];
        for ($i = 0; $i < 64; $i++) {
            $body['p'.$i] = 'x';
        }

        $this->queryJson('/api/v2/search', $body)
            ->assertBadRequest()
            ->assertJsonPath('error', 'QUERY requests accept at most 64 parameters');
    }

    public function test_query_empty_values_behave_like_empty_get_parameters(): void
    {
        $token = $this->apiToken();
        $this->bindSearchMocks()->shouldReceive('apiSearch')
            ->once()
            ->with(null, -1, 0, 100, -1, [5030], [-1], 0, 'posted_desc', null)
            ->andReturn(collect());

        $this->getJson('/api/v2/search?api_token='.$token.'&id=')->assertOk();
        $this->queryJson('/api/v2/search', ['api_token' => $token, 'id' => ''])->assertOk();
        $this->queryJson('/api/v2/search?id=', ['api_token' => $token])->assertOk();
    }

    public function test_v1_query_refuses_side_effecting_functions_before_recording_usage(): void
    {
        foreach (['get', 'g', 'nzbadd'] as $function) {
            $response = $this->queryJson('/api/v1/api', ['t' => $function, 'apikey' => $this->apiToken(), 'id' => 'release-guid']);

            $response->assertBadRequest();
            $response->assertSee('<error code="203" description="Function not available via QUERY"/>', false);
        }

        $this->assertSame(0, DB::table('user_requests')->count());
        $this->assertSame(0, DB::table('user_downloads')->count());
    }

    public function test_v2_getnzb_does_not_accept_query(): void
    {
        $response = $this->queryJson('/api/v2/getnzb', ['api_token' => $this->apiToken(), 'id' => 'release-guid']);

        $response->assertMethodNotAllowed();
        $this->assertNotCacheable($response);
        $this->assertSame(0, DB::table('user_downloads')->count());
    }

    public function test_accept_query_is_advertised_on_get_and_query_responses(): void
    {
        $get = $this->getJson('/api/v2/search?api_token=invalid-token&id=test');
        $query = $this->queryJson('/api/v2/search', ['api_token' => 'invalid-token', 'id' => 'test']);

        $get->assertHeader('Accept-Query', 'application/json');
        $query->assertHeader('Accept-Query', 'application/json');
        $this->assertStringNotContainsString('no-store', (string) $get->headers->get('Cache-Control'));
        $this->assertNotCacheable($query);
        $this->getJson('/api/v2/capabilities')->assertHeaderMissing('Accept-Query');
    }

    public function test_options_lists_allowed_methods_and_accept_query(): void
    {
        $v2 = $this->call('OPTIONS', '/api/v2/search');
        $v1 = $this->call('OPTIONS', '/api/v1/api');

        $v2->assertNoContent()->assertHeader('Accept-Query', 'application/json');
        $this->assertEqualsCanonicalizing(['GET', 'HEAD', 'QUERY', 'OPTIONS'], explode(', ', (string) $v2->headers->get('Allow')));
        $this->assertEqualsCanonicalizing(['GET', 'HEAD', 'POST', 'QUERY', 'OPTIONS'], explode(', ', (string) $v1->headers->get('Allow')));
    }

    public function test_cors_preflight_allows_query_with_a_json_content_type(): void
    {
        $response = $this->call('OPTIONS', '/api/v2/search', [], [], [], [
            'HTTP_ORIGIN' => 'https://client.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'QUERY',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ]);

        $response->assertNoContent()->assertHeader('Access-Control-Allow-Origin', '*');
        $this->assertStringContainsString('QUERY', (string) $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertStringContainsString('content-type', strtolower((string) $response->headers->get('Access-Control-Allow-Headers')));
    }

    public function test_cors_exposes_query_headers_to_browser_clients(): void
    {
        $response = $this->queryJson('/api/v2/search', ['api_token' => 'invalid-token'], ['Origin' => 'https://client.example']);

        $response->assertHeader('Access-Control-Allow-Origin', '*');
        $this->assertEqualsCanonicalizing(
            ['accept-query', 'allow'],
            array_map('trim', explode(',', strtolower((string) $response->headers->get('Access-Control-Expose-Headers'))))
        );
    }

    public function test_search_cursor_is_shared_by_equivalent_get_and_query_filters_only(): void
    {
        $token = $this->apiToken();
        $this->bindSearchMocks()->shouldReceive('apiSearch')->andReturn(collect([$this->releaseRow(3)]));

        $cursor = (string) $this->getJson('/api/v2/search?api_token='.$token.'&id=ubuntu&cat=2000,5030&limit=1&cursor=')
            ->assertOk()
            ->json('pagination.next_cursor');
        $this->assertNotSame('', $cursor);

        $equivalent = [
            'QUERY list' => fn (): TestResponse => $this->queryJson('/api/v2/search', ['api_token' => $token, 'id' => 'ubuntu', 'cat' => [2000, 5030], 'limit' => 1, 'cursor' => $cursor]),
            'QUERY csv' => fn (): TestResponse => $this->queryJson('/api/v2/search', ['api_token' => $token, 'id' => 'ubuntu', 'cat' => '2000, 5030', 'limit' => '1', 'cursor' => $cursor]),
            'GET cat[]' => fn (): TestResponse => $this->getJson('/api/v2/search?api_token='.$token.'&id=ubuntu&cat[]=2000&cat[]=5030&limit=1&cursor='.urlencode($cursor)),
        ];
        foreach ($equivalent as $label => $request) {
            $this->assertSame(200, $request()->status(), $label);
        }

        $this->queryJson('/api/v2/search', ['api_token' => $token, 'id' => 'debian', 'cat' => [2000, 5030], 'limit' => 1, 'cursor' => $cursor])
            ->assertBadRequest()
            ->assertJsonPath('error', 'Search cursor does not match this query or index generation.');
        $this->queryJson('/api/v2/movies', ['api_token' => $token, 'id' => 'ubuntu', 'cat' => [2000, 5030], 'limit' => 1, 'cursor' => $cursor])
            ->assertBadRequest()
            ->assertJsonPath('error', 'Search cursor does not match this query or index generation.');
    }

    public function test_query_api_hits_and_downloads_from_query_links_are_registered(): void
    {
        $token = $this->apiToken();
        $userId = (int) DB::table('users')->value('id');
        $nzbFolder = sys_get_temp_dir().'/nntmux-api-matrix-nzbs-'.bin2hex(random_bytes(6));
        config(['nntmux_settings.path_to_nzbs' => $nzbFolder]);
        $nzbFile = app(NzbService::class)->getNzbPath('release-guid', 0, true);
        file_put_contents($nzbFile, (string) gzencode('<?xml version="1.0" encoding="UTF-8"?><nzb xmlns="http://www.newzbin.com/DTD/2003/nzb"></nzb>'));

        try {
            // Two API hits over QUERY: a v1 search and a v2 details lookup.
            $this->bindSearchMocks()->shouldReceive('apiSearch')->once()->andReturn(collect());
            $this->queryJson('/api/v1/api', ['t' => 'search', 'apikey' => $token, 'q' => 'ubuntu'])->assertOk();
            $link = (string) $this->queryJson('/api/v2/details', ['api_token' => $token, 'id' => 'release-guid'])
                ->assertOk()
                ->json('link');

            // The download link returned by the QUERY response registers a grab.
            $download = $this->get($link);
            $download->assertOk()->assertHeader('Content-Type', 'application/x-nzb');
            $download->streamedContent();
        } finally {
            (new Filesystem)->deleteDirectory($nzbFolder);
        }

        $this->assertSame(['/api/v1/api?q=ubuntu&t=search', '/api/v2/details?id=release-guid'],
            DB::table('user_requests')->where('users_id', $userId)->orderBy('id')->pluck('request')->all());
        $this->assertSame(1, DB::table('user_downloads')->where(['users_id' => $userId, 'releases_id' => 1])->count());
        $this->assertSame(1, (int) DB::table('users')->where('id', $userId)->value('grabs'));
        $this->assertNotNull(DB::table('users')->where('id', $userId)->value('lastdownload'));
        $this->assertSame(3, (int) DB::table('releases')->where('guid', 'release-guid')->value('grabs'));

        Cache::forget('api_user_stats:'.$userId);
        $stats = app(ApiUsageService::class)->statistics($userId);
        $this->assertSame(2, (int) $stats->api_count);
        $this->assertSame(1, (int) $stats->grab_count);
    }

    public function test_v2_getnzb_streams_the_nzb_and_records_one_grab(): void
    {
        $userId = (int) DB::table('users')->value('id');
        $nzbFolder = $this->writeReleaseNzb();

        try {
            $response = $this->get('/api/v2/getnzb?api_token='.$this->apiToken().'&id=release-guid');
            $response->assertOk()->assertHeader('Content-Type', 'application/x-nzb');
            $this->assertStringContainsString('<nzb', $response->streamedContent());
        } finally {
            (new Filesystem)->deleteDirectory($nzbFolder);
        }

        $this->assertSame(1, DB::table('user_requests')->where('users_id', $userId)->count());
        $this->assertSame(1, DB::table('user_downloads')->where(['users_id' => $userId, 'releases_id' => 1])->count());
    }

    public function test_v2_getnzb_without_a_backing_nzb_file_returns_a_json_404(): void
    {
        config(['nntmux_settings.path_to_nzbs' => sys_get_temp_dir().'/nntmux-api-matrix-missing-'.bin2hex(random_bytes(6))]);

        $this->getJson('/api/v2/getnzb?api_token='.$this->apiToken().'&id=release-guid')
            ->assertNotFound()
            ->assertJsonPath('error', 'NZB file not found!');

        $this->assertSame(0, DB::table('user_downloads')->count());
    }

    public function test_v2_getnzb_with_an_exhausted_download_allowance_returns_a_json_429(): void
    {
        $userId = (int) DB::table('users')->value('id');
        DB::table('roles')->where('id', 1)->update(['downloadrequests' => 1]);
        DB::table('user_downloads')->insert(['users_id' => $userId, 'releases_id' => 1, 'timestamp' => now()]);
        $nzbFolder = $this->writeReleaseNzb();

        try {
            $this->getJson('/api/v2/getnzb?api_token='.$this->apiToken().'&id=release-guid')
                ->assertTooManyRequests()
                ->assertJsonPath('error', 'Download limit reached');
        } finally {
            (new Filesystem)->deleteDirectory($nzbFolder);
        }

        $this->assertSame(1, DB::table('user_downloads')->where('users_id', $userId)->count());
    }

    public function test_v2_getnzb_for_an_unknown_guid_returns_a_json_404(): void
    {
        $this->getJson('/api/v2/getnzb?api_token='.$this->apiToken().'&id=unknown-guid')
            ->assertNotFound()
            ->assertJsonPath('error', 'No such item (the guid you provided has no release in our database)');
    }

    public function test_v2_quota_allows_exactly_the_role_limit_despite_cached_statistics(): void
    {
        $userId = (int) DB::table('users')->value('id');
        DB::table('roles')->where('id', 1)->update(['apirequests' => 2]);
        $this->bindSearchMocks()->shouldReceive('apiSearch')->twice()->andReturn(collect());
        // Warm the 60-second display cache at zero requests.
        app(ApiUsageService::class)->statistics($userId);

        $this->getJson('/api/v2/search?api_token='.$this->apiToken().'&id=one')
            ->assertOk()
            ->assertJsonPath('apiCurrent', 1);
        $this->getJson('/api/v2/search?api_token='.$this->apiToken().'&id=two')
            ->assertOk()
            ->assertJsonPath('apiCurrent', 2);
        $this->getJson('/api/v2/search?api_token='.$this->apiToken().'&id=three')
            ->assertTooManyRequests()
            ->assertJsonPath('error', 'Request limit reached');

        $this->assertSame(2, DB::table('user_requests')->where('users_id', $userId)->count());
    }

    public function test_v1_quota_rejects_a_user_exactly_at_the_role_limit(): void
    {
        DB::table('roles')->where('id', 1)->update(['apirequests' => 1]);
        DB::table('user_requests')->insert([
            'users_id' => (int) DB::table('users')->value('id'),
            'request' => '/api/v1/api?q=earlier&t=search',
            'timestamp' => now(),
        ]);

        $this->get('/api/v1/api?t=search&apikey='.$this->apiToken().'&q=ubuntu')
            ->assertTooManyRequests()
            ->assertSee('Request limit reached (1/1)', false);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidV2PaginationProvider(): array
    {
        return [
            'zero limit' => ['limit=0', 'Incorrect parameter (limit must be a positive integer, at most 100 results are returned)'],
            'non-numeric limit' => ['limit=ten', 'Incorrect parameter (limit must be a positive integer, at most 100 results are returned)'],
            'negative offset' => ['offset=-5', 'Incorrect parameter (offset must be a non-negative integer)'],
            'fractional offset' => ['offset=1.5', 'Incorrect parameter (offset must be a non-negative integer)'],
        ];
    }

    #[DataProvider('invalidV2PaginationProvider')]
    public function test_v2_search_rejects_invalid_pagination(string $parameter, string $error): void
    {
        $this->bindSearchMocks()->shouldNotReceive('apiSearch');

        $this->getJson('/api/v2/search?api_token='.$this->apiToken().'&id=ubuntu&'.$parameter)
            ->assertBadRequest()
            ->assertJsonPath('error', $error);
    }

    public function test_v2_search_caps_limit_at_the_advertised_maximum(): void
    {
        $this->bindSearchMocks()->shouldReceive('apiSearch')
            ->once()
            ->with('ubuntu', -1, 0, 100, -1, [5030], [-1], 0, 'posted_desc', null)
            ->andReturn(collect());

        $this->getJson('/api/v2/search?api_token='.$this->apiToken().'&id=ubuntu&limit=5000')->assertOk();
    }

    public function test_v2_get_rejects_array_values_for_scalar_parameters_before_recording_usage(): void
    {
        $this->bindSearchMocks()->shouldNotReceive('apiSearch');

        $this->getJson('/api/v2/search?api_token='.$this->apiToken().'&id[]=ubuntu')
            ->assertBadRequest()
            ->assertJsonPath('error', 'Parameter id has an unsupported type');

        $this->assertSame(0, DB::table('user_requests')->count());
    }

    public function test_v2_anime_passes_minsize_to_the_search(): void
    {
        $this->bindSearchMocks()->shouldReceive('animeSearch')
            ->once()
            ->with(-1, 0, 100, 'naruto', [-1], -1, [5030], -1, 'posted_desc', 1024)
            ->andReturn(collect());

        $this->getJson('/api/v2/anime?api_token='.$this->apiToken().'&id=naruto&minsize=1024')->assertOk();
    }

    public function test_v2_search_failure_returns_retryable_503_and_is_not_cached(): void
    {
        $search = $this->bindSearchMocks();
        $search->shouldReceive('apiSearch')->once()->andReturnUsing(function (): Collection {
            app(SearchFailureTracker::class)->record();

            return collect();
        });
        $search->shouldReceive('apiSearch')->once()->andReturn(collect([$this->releaseRow(1)]));

        $this->getJson('/api/v2/search?api_token='.$this->apiToken().'&id=ubuntu')
            ->assertServiceUnavailable()
            ->assertHeader('Retry-After', '30')
            ->assertJsonPath('error', 'Search is temporarily unavailable, retry shortly');

        $this->getJson('/api/v2/search?api_token='.$this->apiToken().'&id=ubuntu')
            ->assertOk()
            ->assertJsonCount(1, 'results');
    }

    public function test_v2_search_failure_serves_the_last_known_rows_while_they_are_still_stale_cached(): void
    {
        config(['nntmux.api.release_cache_ttl' => 1, 'nntmux.api.release_cache_jitter' => 0]);
        $search = $this->bindSearchMocks();
        $search->shouldReceive('apiSearch')->once()->andReturn(collect([$this->releaseRow(1)]));
        $search->shouldReceive('apiSearch')->once()->andReturnUsing(function (): Collection {
            app(SearchFailureTracker::class)->record();

            return collect();
        });

        $this->getJson('/api/v2/search?api_token='.$this->apiToken().'&id=ubuntu')->assertOk()->assertJsonCount(1, 'results');
        $this->travel(5)->seconds();

        $this->getJson('/api/v2/search?api_token='.$this->apiToken().'&id=ubuntu')
            ->assertOk()
            ->assertJsonCount(1, 'results');
    }

    public function test_v2_search_results_include_the_release_guid(): void
    {
        $this->bindSearchMocks()->shouldReceive('apiSearch')->once()->andReturn(collect([$this->releaseRow(1)]));

        $this->getJson('/api/v2/search?api_token='.$this->apiToken().'&id=ubuntu')
            ->assertOk()
            ->assertJsonPath('results.0.guid', 'movie-release-guid');
    }

    public function test_v2_capabilities_only_advertise_filters_the_endpoints_apply(): void
    {
        $searching = $this->getJson('/api/v2/capabilities')->assertOk()->json('searching');

        foreach ($searching as $function => $capability) {
            $parameters = explode(',', $capability['supportedParams']);
            $this->assertNotContains('maxsize', $parameters, $function);
            $this->assertNotContains('genre', $parameters, $function);
        }
        $this->assertContains('minsize', explode(',', $searching['anime-search']['supportedParams']));
    }

    public function test_name_sorted_api_search_orders_all_index_candidates_by_name_before_paging(): void
    {
        DB::table('releases')->insert(array_merge((array) DB::table('releases')->where('id', 1)->first(), [
            'id' => 2,
            'searchname' => 'Alpha.Release',
            'guid' => 'alpha-guid',
        ]));
        $pdo = DB::connection()->getPdo();
        if ($pdo instanceof PDO && method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('CONCAT', static fn (...$parts): string => implode('', $parts));
        }
        Search::shouldReceive('isAvailable')->andReturn(true);
        Search::shouldReceive('searchReleasePage')
            ->twice()
            ->withArgs(static fn (ReleaseSearchQuery $query): bool => $query->offset === 0 && $query->limit === 2000)
            ->andReturn(new SearchPage(ids: [1, 2], total: 2, fuzzy: false, driver: 'manticore'));
        $service = app(ReleaseSearchService::class);

        $firstPage = $service->apiSearch('release', -1, 0, 1, -1, [], [-1], 0, 'name_asc');
        $secondPage = $service->apiSearch('release', -1, 1, 1, -1, [], [-1], 0, 'name_asc');

        $this->assertSame(['Alpha.Release'], $firstPage->pluck('searchname')->all());
        $this->assertSame(2, $firstPage[0]->_totalrows);
        $this->assertTrue($firstPage[0]->_search_has_more);
        $this->assertSame(['Ubuntu.Release'], $secondPage->pluck('searchname')->all());
        $this->assertFalse($secondPage[0]->_search_has_more);
    }

    public function test_movie_name_search_filters_every_index_match_before_capping(): void
    {
        config(['search.default' => 'manticore', 'search.drivers.manticore.max_matches' => 7500]);
        Search::shouldReceive('isAvailable')->andReturn(true);
        Search::shouldReceive('searchReleases')->once()->with(['searchname' => 'ubuntu'], 7500)->andReturn([1]);
        Search::shouldReceive('searchReleasesFiltered')->once()->andReturn(['ids' => [], 'total' => 0, 'fuzzy' => false]);

        $releases = app(ReleaseSearchService::class)->moviesSearch(name: 'ubuntu');

        $this->assertCount(0, $releases);
    }

    private function writeReleaseNzb(): string
    {
        $nzbFolder = sys_get_temp_dir().'/nntmux-api-matrix-nzbs-'.bin2hex(random_bytes(6));
        config(['nntmux_settings.path_to_nzbs' => $nzbFolder]);
        $nzbFile = app(NzbService::class)->getNzbPath('release-guid', 0, true);
        file_put_contents($nzbFile, (string) gzencode('<?xml version="1.0" encoding="UTF-8"?><nzb xmlns="http://www.newzbin.com/DTD/2003/nzb"></nzb>'));

        return $nzbFolder;
    }

    private function apiToken(): string
    {
        return (string) DB::table('users')->value('api_token');
    }

    /**
     * Bind search/browse mocks into the container so HTTP-level requests reach them.
     */
    private function bindSearchMocks(): ReleaseSearchService&Mockery\MockInterface
    {
        $search = Mockery::mock(ReleaseSearchService::class);
        $browse = Mockery::mock(ReleaseBrowseService::class);
        $browse->shouldNotReceive('getBrowseRangeForApi');
        $this->app->instance(ReleaseSearchService::class, $search);
        $this->app->instance(ReleaseBrowseService::class, $browse);

        return $search;
    }

    private function releaseRow(int $totalRows): object
    {
        return (object) [
            '_totalrows' => $totalRows,
            'searchname' => 'Ubuntu.Movie.Release',
            'guid' => 'movie-release-guid',
            'categories_id' => 2040,
            'category_name' => 'Movies > WEBDL',
            'adddate' => '2026-01-03 00:00:00',
            'size' => 123456,
            'totalpart' => 10,
            'grabs' => 2,
            'comments' => 1,
            'passwordstatus' => 0,
            'postdate' => '2026-01-02 00:00:00',
        ];
    }

    private function queryRaw(string $uri, string $content, string $contentType = 'application/json'): TestResponse
    {
        return $this->call('QUERY', $uri, [], [], [], [
            'CONTENT_TYPE' => $contentType,
            'CONTENT_LENGTH' => (string) strlen($content),
            'HTTP_ACCEPT' => 'application/json',
        ], $content);
    }

    private function assertNotCacheable(TestResponse $response): void
    {
        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->integer('rate_limit')->default(60);
            $table->integer('apirequests')->default(1000);
            $table->integer('downloadrequests')->default(100);
            $table->integer('addyears')->default(0);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('roles_id')->default(1);
            $table->string('api_token')->nullable()->index();
            $table->string('host')->nullable();
            $table->timestamp('apiaccess')->nullable();
            $table->boolean('verified')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->integer('rate_limit')->default(60);
            $table->boolean('can_post')->default(true);
            $table->integer('grabs')->default(0);
            $table->timestamp('lastdownload')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedInteger('role_id');
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedInteger('permission_id');
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedInteger('permission_id');
            $table->unsignedInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('root_categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->integer('status')->default(1);
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->unsignedInteger('root_categories_id')->nullable();
            $table->integer('status')->default(1);
            $table->text('description')->nullable();
        });

        Schema::create('user_excluded_categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->unsignedInteger('categories_id');
        });

        Schema::create('user_requests', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->text('request')->nullable();
            $table->timestamp('timestamp')->nullable();
        });

        Schema::create('user_downloads', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->unsignedInteger('releases_id')->nullable();
            $table->timestamp('timestamp')->nullable();
        });

        Schema::create('videos', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->unsignedInteger('tvdb')->nullable();
            $table->unsignedInteger('trakt')->nullable();
            $table->unsignedInteger('tvrage')->nullable();
            $table->unsignedInteger('tvmaze')->nullable();
            $table->string('imdb')->nullable();
            $table->unsignedInteger('tmdb')->nullable();
        });

        Schema::create('tv_episodes', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('title')->nullable();
            $table->string('series')->nullable();
            $table->string('episode')->nullable();
            $table->date('firstaired')->nullable();
        });

        Schema::create('movieinfo', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('imdbid')->nullable();
            $table->unsignedInteger('tmdbid')->nullable();
            $table->unsignedInteger('traktid')->nullable();
        });

        Schema::create('releases', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('searchname');
            $table->string('guid')->index();
            $table->dateTime('postdate');
            $table->unsignedInteger('categories_id');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('totalpart');
            $table->string('fromname')->nullable();
            $table->integer('passwordstatus')->default(0);
            $table->unsignedInteger('grabs')->default(0);
            $table->unsignedInteger('comments')->default(0);
            $table->dateTime('adddate');
            $table->unsignedInteger('videos_id')->default(0);
            $table->unsignedInteger('tv_episodes_id')->default(0);
            $table->integer('haspreview')->default(0);
            $table->integer('nfostatus')->default(0);
            $table->unsignedInteger('movieinfo_id')->default(0);
            $table->unsignedInteger('musicinfo_id')->default(0);
            $table->unsignedInteger('consoleinfo_id')->default(0);
            $table->unsignedInteger('groups_id')->nullable();
        });

        Schema::create('releases_groups', function (Blueprint $table): void {
            $table->unsignedInteger('releases_id')->default(0);
            $table->unsignedInteger('groups_id')->default(0);
        });

        Schema::create('usenet_groups', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->string('description')->nullable();
            $table->timestamp('last_updated')->nullable();
        });

        Schema::create('genres', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->integer('type')->default(3000);
            $table->boolean('disabled')->default(false);
        });

        Schema::create('registration_periods', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('is_enabled')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    private function findStagedFile(string $filename): ?string
    {
        foreach ((new Filesystem)->allFiles($this->nzbUploadFolder) as $file) {
            if ($file->getFilename() === $filename) {
                return $file->getPathname();
            }
        }

        return null;
    }

    private function setEnvironmentValue(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    private function seedData(): void
    {

        DB::table('roles')->insert([
            [
                'id' => 1,
                'name' => 'User',
                'guard_name' => 'web',
                'rate_limit' => 60,
                'apirequests' => 1000,
                'downloadrequests' => 100,
                'addyears' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'name' => 'Disabled',
                'guard_name' => 'web',
                'rate_limit' => 60,
                'apirequests' => 0,
                'downloadrequests' => 0,
                'addyears' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('users')->insert([
            'username' => 'matrix_user',
            'email' => 'matrix@example.test',
            'password' => bcrypt('secret'),
            'roles_id' => 1,
            'api_token' => Str::random(32),
            'verified' => 1,
            'email_verified_at' => now(),
            'rate_limit' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('root_categories')->insert([
            'id' => 5000,
            'title' => 'TV',
            'status' => 1,
        ]);

        DB::table('categories')->insert([
            'id' => 5030,
            'title' => 'SD',
            'root_categories_id' => 5000,
            'status' => 1,
            'description' => 'TV SD',
        ]);

        DB::table('usenet_groups')->insert([
            'id' => 1,
            'name' => 'alt.binaries.test',
            'active' => 1,
            'description' => 'Test usenet group',
            'last_updated' => now(),
        ]);

        DB::table('releases')->insert([
            'id' => 1,
            'searchname' => 'Ubuntu.Release',
            'guid' => 'release-guid',
            'postdate' => '2026-01-02 00:00:00',
            'categories_id' => 5030,
            'size' => 123456,
            'totalpart' => 10,
            'fromname' => 'poster',
            'passwordstatus' => 0,
            'grabs' => 2,
            'comments' => 1,
            'adddate' => '2026-01-03 00:00:00',
            'videos_id' => 0,
            'tv_episodes_id' => 0,
            'haspreview' => 0,
            'nfostatus' => 0,
            'movieinfo_id' => 0,
            'musicinfo_id' => 0,
            'consoleinfo_id' => 0,
            'groups_id' => 1,
        ]);

        DB::table('genres')->insert([
            'id' => 1,
            'title' => 'Test Genre',
            'type' => 3000,
            'disabled' => 0,
        ]);
    }
}
