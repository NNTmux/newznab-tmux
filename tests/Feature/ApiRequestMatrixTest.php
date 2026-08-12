<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\ApiV2Controller;
use App\Models\Category;
use App\Services\Releases\ReleaseBrowseService;
use App\Services\Releases\ReleaseSearchService;
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
use Mockery;
use PDO;
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
        $this->databasePath = $this->makeTempPath('nntmux-api-matrix-test', '.sqlite');
        $this->originalEnvironment = [
            'APP_ENV' => getenv('APP_ENV'),
            'DB_CONNECTION' => getenv('DB_CONNECTION'),
            'DB_DATABASE' => getenv('DB_DATABASE'),
        ];

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        $pdo = new PDO('sqlite:'.$this->databasePath);
        $pdo->exec('CREATE TABLE settings (name VARCHAR PRIMARY KEY, value TEXT NULL)');
        $pdo->exec("INSERT INTO settings (name, value) VALUES
            ('categorizeforeign', '0'),
            ('catwebdl', '0'),
            ('innerfileblacklist', '')");

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

        Schema::create('settings', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->text('value')->nullable();
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
        DB::table('settings')->insert([
            ['name' => 'strapline', 'value' => 'Test strapline'],
            ['name' => 'metakeywords', 'value' => 'test,api'],
            ['name' => 'registerstatus', 'value' => '0'],
            ['name' => 'categorizeforeign', 'value' => '0'],
            ['name' => 'catwebdl', 'value' => '0'],
            ['name' => 'innerfileblacklist', 'value' => ''],
            ['name' => 'title', 'value' => 'NNTmux Test'],
            ['name' => 'home_link', 'value' => '/'],
        ]);

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
