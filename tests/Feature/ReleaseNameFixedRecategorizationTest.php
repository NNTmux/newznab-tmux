<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Facades\Search;
use App\Models\Category;
use App\Models\Release;
use App\Models\UsenetGroup;
use App\Services\NameFixing\ReleaseUpdateService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class ReleaseNameFixedRecategorizationTest extends TestCase
{
    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-release-name-fixed-test.sqlite';

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
        $pdo->exec("INSERT INTO settings (name, value) VALUES ('categorizeforeign', '0'), ('catwebdl', '1')");

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

        DB::table('settings')->upsert([
            ['name' => 'categorizeforeign', 'value' => '0'],
            ['name' => 'catwebdl', 'value' => '1'],
        ], ['name'], ['value']);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
        ]);

        DB::purge();
        DB::reconnect();

        $this->createSchema();
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

    public function test_renaming_hashed_release_recategorizes_it_synchronously(): void
    {
        Search::shouldReceive('updateRelease')->twice();

        $group = UsenetGroup::query()->create([
            'name' => 'alt.binaries.hdtv',
            'active' => 1,
            'backfill' => 0,
        ]);

        $release = Release::factory()->create([
            'name' => 'd41d8cd98f00b204e9800998ecf8427e',
            'searchname' => 'd41d8cd98f00b204e9800998ecf8427e',
            'fromname' => 'poster@example.com',
            'groups_id' => $group->id,
            'categories_id' => Category::OTHER_HASHED,
            'iscategorized' => 1,
            'isrenamed' => 0,
            'guid' => str_repeat('a', 40),
            'leftguid' => 'a',
            'nzb_guid' => 'test',
            'size' => 1,
            'postdate' => now(),
            'adddate' => now(),
        ]);

        $service = app(ReleaseUpdateService::class);
        $service->updateRelease(
            $release->fresh(),
            'Show.Name.S03E05.720p.HDTV.x264-GROUP',
            'nfoCheck: Title Match',
            true,
            'NFO, ',
            true,
            false,
        );

        $release->refresh();

        $this->assertSame('Show.Name.S03E05.720p.HDTV.x264-GROUP', $release->searchname);
        $this->assertSame(Category::TV_HD, $release->categories_id);
        $this->assertSame(1, (int) $release->iscategorized);
        $this->assertSame(1, (int) $release->isrenamed);
    }

    public function test_renaming_olympic_webdl_release_recategorizes_it_from_movie_webdl_to_tv_sport(): void
    {
        Search::shouldReceive('updateRelease')->twice();

        $group = UsenetGroup::query()->create([
            'name' => 'alt.binaries.hdtv',
            'active' => 1,
            'backfill' => 0,
        ]);

        $oldName = 'WinterOlympics2026__NZBSPLIT__0456f274737cea074abd86a89144cc7b__NZBSPLIT__Winter_Olympic_Games_Milano_Cortina_2026_Closing_Ceremony_1080p25_WEB-DL_(MultiAudio).7z.065';
        $newName = 'Winter.Olympic.Games.Milano.Cortina.2026.Closing.Ceremony.1080p25.WEB-DL.(MultiAudio)';

        $release = Release::factory()->create([
            'name' => $oldName,
            'searchname' => $oldName,
            'fromname' => 'poster@example.com',
            'groups_id' => $group->id,
            'categories_id' => Category::MOVIE_WEBDL,
            'iscategorized' => 1,
            'isrenamed' => 0,
            'guid' => str_repeat('b', 40),
            'leftguid' => 'b',
            'nzb_guid' => 'test-olympics',
            'size' => 1,
            'postdate' => now(),
            'adddate' => now(),
        ]);

        $service = app(ReleaseUpdateService::class);
        $service->updateRelease(
            $release->fresh(),
            $newName,
            'NZBSPLIT wrapper',
            true,
            'Filenames, ',
            true,
            false,
        );

        $release->refresh();

        $this->assertSame($newName, $release->searchname);
        $this->assertSame(Category::TV_SPORT, $release->categories_id);
        $this->assertSame(1, (int) $release->iscategorized);
        $this->assertSame(1, (int) $release->isrenamed);
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

    private function createSchema(): void
    {
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table): void {
                $table->string('name')->primary();
                $table->text('value')->nullable();
            });
        }

        if (! Schema::hasTable('usenet_groups')) {
            Schema::create('usenet_groups', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('name')->unique();
                $table->integer('backfill_target')->default(1);
                $table->unsignedBigInteger('first_record')->default(0);
                $table->dateTime('first_record_postdate')->nullable();
                $table->unsignedBigInteger('last_record')->default(0);
                $table->dateTime('last_record_postdate')->nullable();
                $table->dateTime('last_updated')->nullable();
                $table->integer('minfilestoformrelease')->nullable();
                $table->bigInteger('minsizetoformrelease')->nullable();
                $table->boolean('active')->default(false);
                $table->boolean('backfill')->default(false);
                $table->string('description')->nullable();
            });
        }

        if (! Schema::hasTable('releases')) {
            Schema::create('releases', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('name')->default('');
                $table->string('searchname')->default('');
                $table->unsignedInteger('groups_id')->default(0);
                $table->unsignedBigInteger('size')->default(0);
                $table->dateTime('postdate')->nullable();
                $table->dateTime('adddate')->nullable();
                $table->string('guid', 40);
                $table->char('leftguid', 1);
                $table->string('fromname')->nullable();
                $table->integer('categories_id')->default(Category::OTHER_MISC);
                $table->unsignedInteger('videos_id')->default(0);
                $table->integer('tv_episodes_id')->default(0);
                $table->string('imdbid')->nullable();
                $table->integer('musicinfo_id')->nullable();
                $table->integer('consoleinfo_id')->nullable();
                $table->integer('bookinfo_id')->nullable();
                $table->integer('anidbid')->nullable();
                $table->unsignedInteger('predb_id')->default(0);
                $table->tinyInteger('iscategorized')->default(0);
                $table->tinyInteger('isrenamed')->default(0);
                $table->tinyInteger('proc_nfo')->default(0);
                $table->tinyInteger('proc_files')->default(0);
                $table->tinyInteger('proc_par2')->default(0);
                $table->tinyInteger('proc_uid')->default(0);
                $table->tinyInteger('proc_hash16k')->default(0);
                $table->tinyInteger('proc_srr')->default(0);
                $table->tinyInteger('proc_crc32')->default(0);
                $table->tinyInteger('passwordstatus')->default(0);
                $table->tinyInteger('nzbstatus')->default(0);
                $table->binary('nzb_guid')->nullable();
            });
        }
    }
}
