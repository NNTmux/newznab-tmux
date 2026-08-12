<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Category;
use App\Services\SeriesReleaseService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class SeriesReleaseServiceTest extends TestCase
{
    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = $this->makeTempPath('nntmux-series-release-service-test', '.sqlite');

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
        $pdo->exec("INSERT INTO settings (name, value) VALUES ('showpasswordedrelease', '0'), ('categorizeforeign', '0'), ('catwebdl', '0')");

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
        ]);

        DB::purge();
        DB::reconnect();
        Cache::flush();

        $this->createSchema();
        $this->seedCategories();
    }

    protected function tearDown(): void
    {
        if ($this->databasePath !== '' && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();

        foreach ($this->originalEnvironment as $key => $value) {
            $this->setEnvironmentValue($key, $value === false ? null : $value);
        }
    }

    public function test_fallback_releases_are_only_in_their_parsed_season(): void
    {
        $videoId = $this->createShow();

        $this->createRelease($videoId, 'Test.Show.S01E01.720p-GROUP', null);
        $this->createRelease($videoId, 'Test.Show.S02E03.720p-GROUP', null);

        /** @var SeriesReleaseService $service */
        $service = app(SeriesReleaseService::class);
        $categories = $service->categoryIds([-1]);

        $seasonOne = $service->releasesForSeason($videoId, 1, 0, 20, $categories);
        $seasonTwo = $service->releasesForSeason($videoId, 2, 0, 20, $categories);

        $this->assertSame(['Test.Show.S01E01.720p-GROUP'], $seasonOne['releases']->pluck('searchname')->all());
        $this->assertSame(['Test.Show.S02E03.720p-GROUP'], $seasonTwo['releases']->pluck('searchname')->all());
    }

    private function createSchema(): void
    {
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table): void {
                $table->string('name')->primary();
                $table->text('value')->nullable();
            });
        }

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

        Schema::create('videos', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('type')->default(0);
            $table->string('title')->default('');
            $table->string('countries_id', 2)->nullable();
            $table->string('started')->nullable();
            $table->integer('anidb')->default(0);
            $table->string('imdb')->nullable();
            $table->integer('tmdb')->default(0);
            $table->integer('trakt')->default(0);
            $table->integer('tvdb')->default(0);
            $table->integer('tvmaze')->default(0);
            $table->integer('tvrage')->default(0);
            $table->integer('source')->default(0);
        });

        Schema::create('tv_info', function (Blueprint $table): void {
            $table->unsignedInteger('videos_id')->primary();
            $table->text('summary')->nullable();
            $table->string('publisher')->nullable();
            $table->boolean('image')->default(false);
        });

        Schema::create('tv_episodes', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('videos_id');
            $table->integer('series')->default(0);
            $table->integer('episode')->default(0);
            $table->string('se_complete')->default('');
            $table->string('title')->default('');
            $table->string('firstaired')->nullable();
            $table->text('summary')->nullable();
        });

        Schema::create('releases', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('searchname')->default('');
            $table->string('fromname')->nullable();
            $table->dateTime('postdate')->nullable();
            $table->dateTime('adddate')->nullable();
            $table->string('guid')->nullable();
            $table->unsignedInteger('categories_id')->default(Category::TV_SD);
            $table->unsignedInteger('groups_id')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->integer('totalpart')->default(0);
            $table->integer('passwordstatus')->default(0);
            $table->integer('grabs')->default(0);
            $table->integer('comments')->default(0);
            $table->unsignedInteger('videos_id')->nullable();
            $table->integer('tv_episodes_id')->nullable();
        });

        Schema::create('dnzb_failures', function (Blueprint $table): void {
            $table->unsignedInteger('release_id');
            $table->unsignedInteger('users_id');
            $table->integer('failed')->default(0);
            $table->primary(['release_id', 'users_id']);
        });
    }

    private function seedCategories(): void
    {
        DB::table('root_categories')->insert([
            'id' => Category::TV_ROOT,
            'title' => 'TV',
            'status' => 1,
        ]);

        DB::table('categories')->insert([
            'id' => Category::TV_SD,
            'title' => 'SD',
            'root_categories_id' => Category::TV_ROOT,
            'status' => 1,
            'description' => 'TV SD',
        ]);
    }

    private function createShow(): int
    {
        $videoId = DB::table('videos')->insertGetId([
            'type' => 0,
            'title' => 'Test Show',
            'started' => '2024-01-01',
        ]);

        DB::table('tv_info')->insert([
            'videos_id' => $videoId,
            'summary' => 'A test show.',
            'publisher' => 'Test Network',
            'image' => 0,
        ]);

        return (int) $videoId;
    }

    private function createRelease(int $videoId, string $searchName, ?int $episodeId): void
    {
        DB::table('releases')->insert([
            'name' => $searchName,
            'searchname' => $searchName,
            'fromname' => 'poster@example.test',
            'postdate' => now(),
            'adddate' => now(),
            'guid' => sha1($searchName),
            'categories_id' => Category::TV_SD,
            'groups_id' => null,
            'size' => 1024,
            'totalpart' => 1,
            'passwordstatus' => 0,
            'grabs' => 0,
            'comments' => 0,
            'videos_id' => $videoId,
            'tv_episodes_id' => $episodeId,
        ]);
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
}
