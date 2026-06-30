<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Facades\Search;
use App\Models\Category;
use App\Models\Release;
use App\Services\BookService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class BookServiceObfuscatedNormalizationTest extends TestCase
{
    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-book-obfuscated-normalization.sqlite';

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
        $pdo->exec("INSERT INTO settings (name, value) VALUES ('maxbooksprocessed', '50'), ('amazonsleep', '0'), ('lookupbooks', '1')");

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
            ['name' => 'maxbooksprocessed', 'value' => '50'],
            ['name' => 'amazonsleep', 'value' => '0'],
            ['name' => 'lookupbooks', 'value' => '1'],
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

    public function test_process_book_releases_normalizes_obfuscated_searchnames_for_existing_book_rows(): void
    {
        Search::shouldReceive('updateRelease')->once();

        DB::table('releases')->insert([
            'id' => 1,
            'name' => "N_NZB_[1_6]_-_Woman's_Day_New_Zealand_-_Issue_45_April_27_2026.par2",
            'searchname' => "N_NZB_[1_6]_-_Woman's_Day_New_Zealand_-_Issue_45_April_27_2026.par2",
            'groups_id' => 1,
            'size' => 1,
            'postdate' => now(),
            'adddate' => now(),
            'guid' => str_repeat('a', 40),
            'leftguid' => 'a',
            'fromname' => 'poster@example.com',
            'categories_id' => Category::BOOKS_MAGAZINES,
            'videos_id' => 0,
            'tv_episodes_id' => 0,
            'imdbid' => null,
            'musicinfo_id' => null,
            'consoleinfo_id' => null,
            'bookinfo_id' => 123,
            'anidbid' => null,
            'predb_id' => 0,
            'iscategorized' => 1,
            'isrenamed' => 0,
            'proc_nfo' => 0,
            'proc_files' => 0,
            'proc_par2' => 0,
            'proc_uid' => 0,
            'proc_hash16k' => 0,
            'proc_srr' => 0,
            'proc_crc32' => 0,
            'passwordstatus' => 0,
            'nzbstatus' => 1,
        ]);

        $service = app(BookService::class);
        $service->processBookReleases();

        $release = Release::query()->findOrFail(1);

        $this->assertSame("Woman's Day New Zealand - Issue 45 April 27 2026", $release->searchname);
        $this->assertSame(123, (int) $release->bookinfo_id);
        $this->assertSame(1, (int) $release->isrenamed);
    }

    public function test_process_book_releases_normalizes_existing_mcn_magazine_searchname(): void
    {
        Search::shouldReceive('updateRelease')->once();

        DB::table('releases')->insert([
            'id' => 2,
            'name' => 'MCN.April.22.2026.HYBRID.MAGAZINE.eBook-21A1',
            'searchname' => 'MCN.April.22.2026.HYBRID.MAGAZINE.eBook-21A1',
            'groups_id' => 1,
            'size' => 1,
            'postdate' => now(),
            'adddate' => now(),
            'guid' => str_repeat('b', 40),
            'leftguid' => 'b',
            'fromname' => 'poster@example.com',
            'categories_id' => Category::BOOKS_MAGAZINES,
            'videos_id' => 0,
            'tv_episodes_id' => 0,
            'imdbid' => null,
            'musicinfo_id' => null,
            'consoleinfo_id' => null,
            'bookinfo_id' => -2,
            'anidbid' => null,
            'predb_id' => 0,
            'iscategorized' => 1,
            'isrenamed' => 0,
            'proc_nfo' => 0,
            'proc_files' => 0,
            'proc_par2' => 0,
            'proc_uid' => 0,
            'proc_hash16k' => 0,
            'proc_srr' => 0,
            'proc_crc32' => 0,
            'passwordstatus' => 0,
            'nzbstatus' => 1,
        ]);

        $service = app(BookService::class);
        $service->processBookReleases();

        $release = Release::query()->findOrFail(2);

        $this->assertSame('MCN - April 22, 2026', $release->searchname);
        $this->assertSame(-2, (int) $release->bookinfo_id);
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
            });
        }
    }
}
