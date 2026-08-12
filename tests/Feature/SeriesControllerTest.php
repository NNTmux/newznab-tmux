<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\ClearanceMiddleware;
use App\Http\Middleware\Google2FAMiddleware;
use App\Http\Middleware\TrustedDevice2FAMiddleware;
use App\Models\Category;
use App\Models\User;
use App\View\Composers\GlobalDataComposer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use ReflectionClass;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SeriesControllerTest extends TestCase
{
    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = $this->makeTempPath('nntmux-series-controller-test', '.sqlite');

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
            ('showpasswordedrelease', '0'),
            ('categorizeforeign', '0'),
            ('catwebdl', '0'),
            ('title', 'NNTmux Test'),
            ('home_link', '/')");

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
            'mail.from.address' => 'noreply@example.test',
            'mail.from.name' => 'NNTmux Tests',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'nntmux.series_view_limit' => 20,
        ]);

        DB::purge();
        DB::reconnect();
        Cache::flush();

        $this->createSchema();
        $this->seedBaseData();
        $this->resetGlobalComposerState();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withoutMiddleware([
            ClearanceMiddleware::class,
            Google2FAMiddleware::class,
            TrustedDevice2FAMiddleware::class,
        ]);
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

    public function test_selected_season_renders_only_that_season_releases(): void
    {
        $user = $this->createUser();
        $videoId = $this->createShow();
        $this->createMatchedRelease($videoId, 1, 1, 'Only.Season.One.S01E01.720p-GROUP');
        $this->createMatchedRelease($videoId, 2, 1, 'Only.Season.Two.S02E01.720p-GROUP');

        $seasonOne = $this->actingAs($user)->get(route('series', ['id' => $videoId, 'season' => 1]));
        $seasonOne->assertOk();
        $seasonOne->assertSee('Only.Season.One.S01E01.720p-GROUP');
        $seasonOne->assertDontSee('Only.Season.Two.S02E01.720p-GROUP');

        $seasonTwo = $this->actingAs($user)->get(route('series', ['id' => $videoId, 'season' => 2]));
        $seasonTwo->assertOk();
        $seasonTwo->assertSee('Only.Season.Two.S02E01.720p-GROUP');
        $seasonTwo->assertDontSee('Only.Season.One.S01E01.720p-GROUP');
    }

    public function test_season_links_preserve_category_and_reset_page(): void
    {
        $user = $this->createUser();
        $videoId = $this->createShow();
        $this->createMatchedRelease($videoId, 1, 1, 'Link.Test.S01E01.720p-GROUP');
        $this->createMatchedRelease($videoId, 2, 1, 'Link.Test.S02E01.720p-GROUP');

        $response = $this->actingAs($user)->get(route('series', [
            'id' => $videoId,
            'season' => 1,
            'page' => 3,
            't' => Category::TV_SD,
        ]));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('season=2', $html);
        $this->assertStringContainsString('page=1', $html);
        $this->assertStringContainsString('t=5030', $html);
        $this->assertStringContainsString('#series-episodes', $html);
        $this->assertStringContainsString('x-data="seriesSeasonLoader"', $html);
        $this->assertStringContainsString('data-series-season-link', $html);
        $this->assertStringNotContainsString('season=2&amp;page=3', $html);
    }

    public function test_lazy_season_fragment_returns_only_selected_season_html(): void
    {
        $user = $this->createUser();
        $videoId = $this->createShow();
        $this->createMatchedRelease($videoId, 1, 1, 'Lazy.Test.S01E01.720p-GROUP');
        $this->createMatchedRelease($videoId, 2, 1, 'Lazy.Test.S02E01.720p-GROUP');

        $response = $this->actingAs($user)->getJson(route('series', [
            'id' => $videoId,
            'season' => 2,
            '_fragment' => 'season',
        ]));

        $response->assertOk();
        $response->assertJsonPath('selectedSeason', 2);

        $contentHtml = $response->json('contentHtml');
        $this->assertIsString($contentHtml);
        $this->assertStringContainsString('data-series-season-content', $contentHtml);
        $this->assertStringContainsString('Lazy.Test.S02E01.720p-GROUP', $contentHtml);
        $this->assertStringNotContainsString('Lazy.Test.S01E01.720p-GROUP', $contentHtml);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $contentHtml);
        $this->assertStringNotContainsString('_fragment=season', (string) $response->json('url'));
    }

    public function test_many_releases_only_render_selected_season_page(): void
    {
        $user = $this->createUser();
        $videoId = $this->createShow();

        for ($episode = 1; $episode <= 30; $episode++) {
            $this->createMatchedRelease($videoId, 1, $episode, sprintf('Paged.Show.S01E%02d.720p-GROUP', $episode));
            $this->createMatchedRelease($videoId, 2, $episode, sprintf('Paged.Show.S02E%02d.720p-GROUP', $episode));
        }

        $response = $this->actingAs($user)->get(route('series', ['id' => $videoId, 'season' => 1]));

        $response->assertOk();
        $response->assertSee('Paged.Show.S01E01.720p-GROUP');
        $response->assertDontSee('Paged.Show.S02E01.720p-GROUP');
        $this->assertSame(20, substr_count($response->getContent(), 'series-episode-card'));
    }

    private function createSchema(): void
    {
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table): void {
                $table->string('name')->primary();
                $table->text('value')->nullable();
            });
        }

        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
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

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('roles_id')->default(1);
            $table->integer('rate_limit')->default(60);
            $table->string('api_token')->nullable();
            $table->boolean('verified')->default(true);
            $table->boolean('can_post')->default(true);
            $table->string('theme_preference', 10)->default('light');
            $table->string('session_token')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('lastlogin')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_excluded_categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->unsignedInteger('categories_id');
        });

        Schema::create('content', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->string('url')->nullable();
            $table->text('body')->nullable();
            $table->text('metadescription')->nullable();
            $table->text('metakeywords')->nullable();
            $table->integer('contenttype')->default(1);
            $table->integer('status')->default(1);
            $table->integer('ordinal')->nullable();
            $table->integer('role')->default(0);
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

        Schema::create('user_series', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->unsignedInteger('videos_id');
        });
    }

    private function seedBaseData(): void
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

        DB::table('roles')->insert([
            'id' => 1,
            'name' => 'User',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('permissions')->insert([
            'id' => 1,
            'name' => 'view tv',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('role_has_permissions')->insert([
            'permission_id' => 1,
            'role_id' => 1,
        ]);
    }

    private function createUser(): User
    {
        $userId = DB::table('users')->insertGetId([
            'username' => 'series-user',
            'email' => 'series@example.test',
            'password' => bcrypt('secret'),
            'roles_id' => 1,
            'api_token' => 'series-token',
            'verified' => true,
            'can_post' => true,
            'theme_preference' => 'light',
            'email_verified_at' => now(),
            'lastlogin' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userModelClass = User::class;
        DB::table('model_has_roles')->insert([
            'role_id' => 1,
            'model_type' => $userModelClass,
            'model_id' => $userId,
        ]);
        DB::table('model_has_permissions')->insert([
            'permission_id' => 1,
            'model_type' => $userModelClass,
            'model_id' => $userId,
        ]);

        return User::query()->findOrFail($userId);
    }

    private function createShow(): int
    {
        $videoId = DB::table('videos')->insertGetId([
            'type' => 0,
            'title' => 'Paged Test Show',
            'started' => '2024-01-01',
            'countries_id' => 'US',
        ]);

        DB::table('tv_info')->insert([
            'videos_id' => $videoId,
            'summary' => 'A test show.',
            'publisher' => 'Test Network',
            'image' => 0,
        ]);

        return (int) $videoId;
    }

    private function createMatchedRelease(int $videoId, int $season, int $episode, string $searchName): void
    {
        $episodeId = DB::table('tv_episodes')->insertGetId([
            'videos_id' => $videoId,
            'series' => $season,
            'episode' => $episode,
            'se_complete' => sprintf('S%02dE%02d', $season, $episode),
            'title' => 'Episode '.$episode,
            'firstaired' => now()->subDays($episode)->toDateString(),
            'summary' => 'Episode summary.',
        ]);

        DB::table('releases')->insert([
            'name' => $searchName,
            'searchname' => $searchName,
            'fromname' => 'poster@example.test',
            'postdate' => now()->subMinutes($episode),
            'adddate' => now()->subMinutes($episode),
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

    private function resetGlobalComposerState(): void
    {
        $reflection = new ReflectionClass(GlobalDataComposer::class);
        $property = $reflection->getProperty('resolvedData');
        $property->setAccessible(true);
        $property->setValue(null, null);
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
