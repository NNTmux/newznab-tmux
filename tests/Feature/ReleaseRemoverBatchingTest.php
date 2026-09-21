<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BlacklistConstants;
use App\Facades\Search;
use App\Services\Nzb\NzbService;
use App\Services\ReleaseImageService;
use App\Services\ReleaseRemoverService;
use App\Services\Releases\ReleaseManagementService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PDO;
use Tests\TestCase;

class ReleaseRemoverBatchingTest extends TestCase
{
    private string $databasePath;

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-release-remover-test.sqlite';
        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        $pdo = new PDO('sqlite:'.$this->databasePath);
        $pdo->exec('CREATE TABLE settings (name VARCHAR PRIMARY KEY, value TEXT NULL)');
        $pdo->exec("INSERT INTO settings (name, value) VALUES
            ('categorizeforeign', '0'), ('catwebdl', '0'), ('innerfileblacklist', '')");

        putenv('APP_ENV=testing');
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE='.$this->databasePath);
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
        $_ENV['DB_CONNECTION'] = $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = $this->databasePath;

        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('nntmux.echocli', false);
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $this->databasePath);
        DB::purge();
        DB::reconnect();

        Schema::dropIfExists('release_files');
        Schema::dropIfExists('releases');
        Schema::dropIfExists('binaryblacklist');
        Schema::dropIfExists('usenet_groups');

        Schema::create('usenet_groups', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
        });
        Schema::create('binaryblacklist', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('groupname');
            $table->text('regex');
            $table->unsignedTinyInteger('status');
            $table->unsignedTinyInteger('optype');
            $table->unsignedTinyInteger('msgcol');
        });
        Schema::create('releases', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('guid', 40);
            $table->string('searchname');
            $table->string('fromname')->nullable();
            $table->unsignedInteger('groups_id');
            $table->dateTime('adddate')->nullable();
        });
        Schema::create('release_files', function (Blueprint $table): void {
            $table->unsignedInteger('releases_id');
            $table->string('name');
        });
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    public function test_blacklist_removal_is_not_limited_to_one_hundred_search_results(): void
    {
        DB::table('usenet_groups')->insert(['id' => 1, 'name' => 'alt.binaries.test']);
        DB::table('binaryblacklist')->insert([
            'groupname' => 'alt.binaries.*',
            'regex' => '^blocked-',
            'status' => BlacklistConstants::BLACKLIST_ENABLED,
            'optype' => BlacklistConstants::OPTYPE_BLACKLIST,
            'msgcol' => BlacklistConstants::BLACKLIST_FIELD_SUBJECT,
        ]);

        DB::table('releases')->insert(collect(range(1, 125))->map(static fn (int $id): array => [
            'id' => $id,
            'guid' => str_pad((string) $id, 40, '0', STR_PAD_LEFT),
            'searchname' => 'blocked-'.$id,
            'fromname' => 'poster',
            'groups_id' => 1,
            'adddate' => now(),
        ])->all());

        $management = Mockery::mock(ReleaseManagementService::class);
        $management->shouldReceive('deleteBatch')
            ->once()
            ->withArgs(static fn ($releases): bool => $releases->count() === 125)
            ->andReturn(125);

        $service = new ReleaseRemoverService(
            $management,
            Mockery::mock(NzbService::class),
            Mockery::mock(ReleaseImageService::class)
        );

        self::assertTrue($service->removeCrap(true, 'full', 'blacklist'));
    }

    public function test_invalid_blacklist_regex_does_not_prevent_valid_rules_from_running(): void
    {
        DB::table('usenet_groups')->insert(['id' => 1, 'name' => 'alt.binaries.test']);
        DB::table('binaryblacklist')->insert([
            [
                'groupname' => 'alt.binaries.*',
                'regex' => '[invalid',
                'status' => 1,
                'optype' => 1,
                'msgcol' => 1,
            ],
            [
                'groupname' => 'alt.binaries.*',
                'regex' => '^blocked$',
                'status' => 1,
                'optype' => 1,
                'msgcol' => 1,
            ],
        ]);
        DB::table('releases')->insert([
            'id' => 1,
            'guid' => str_repeat('a', 40),
            'searchname' => 'blocked',
            'fromname' => 'poster',
            'groups_id' => 1,
            'adddate' => now(),
        ]);

        $management = Mockery::mock(ReleaseManagementService::class);
        $management->shouldReceive('deleteBatch')->once()->andReturn(1);

        $service = new ReleaseRemoverService(
            $management,
            Mockery::mock(NzbService::class),
            Mockery::mock(ReleaseImageService::class)
        );

        self::assertTrue($service->removeCrap(true, 'full', 'blacklist'));
    }

    public function test_release_management_batches_search_and_database_deletion(): void
    {
        DB::table('releases')->insert([
            [
                'id' => 1,
                'guid' => str_repeat('a', 40),
                'searchname' => 'one',
                'fromname' => 'poster',
                'groups_id' => 1,
                'adddate' => now(),
            ],
            [
                'id' => 2,
                'guid' => str_repeat('b', 40),
                'searchname' => 'two',
                'fromname' => 'poster',
                'groups_id' => 1,
                'adddate' => now(),
            ],
        ]);

        $nzb = Mockery::mock(NzbService::class);
        $nzb->shouldReceive('deleteNzb')->twice()->andReturnTrue();
        $images = Mockery::mock(ReleaseImageService::class);
        $images->shouldReceive('delete')->twice();
        Search::shouldReceive('deleteReleases')->once()->with([1, 2]);

        $deleted = (new ReleaseManagementService)->deleteBatch([
            (object) ['id' => 1, 'guid' => str_repeat('a', 40)],
            (object) ['id' => 2, 'guid' => str_repeat('b', 40)],
        ], $nzb, $images);

        self::assertSame(2, $deleted);
        self::assertSame(0, DB::table('releases')->count());
    }
}
