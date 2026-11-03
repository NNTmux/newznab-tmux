<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\ReleaseNameFixed;
use App\Models\Release;
use App\Services\AdditionalProcessing\ReleaseSearchSyncCoordinator;
use App\Services\AdditionalProcessing\State\PersistenceMetricsCollector;
use App\Services\NameFixing\ReleaseUpdateService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class ReleaseCrcRenamePersistenceTest extends TestCase
{
    private string $databasePath;

    /** @var array<string, string|false> */
    private array $originalEnvironment = [];

    /** @var list<int> */
    private array $searchUpdates = [];

    public function createApplication()
    {
        $this->databasePath = __DIR__.'/../Fixtures/crc-rename-'.getmypid().'.sqlite';
        $pdo = new PDO('sqlite:'.$this->databasePath);
        $pdo->exec('CREATE TABLE settings (name VARCHAR PRIMARY KEY, value TEXT NULL)');
        $pdo->exec("INSERT INTO settings (name, value) VALUES ('categorizeforeign', '0'), ('catwebdl', '1')");
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->databasePath] as $key => $value) {
            $this->originalEnvironment[$key] = getenv($key);
            $this->setEnvironment($key, $value);
        }
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['nntmux.echocli' => false]);
        Event::fake([ReleaseNameFixed::class]);
        Schema::create('releases', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('searchname');
            $table->string('fromname')->default('');
            foreach (['groups_id', 'categories_id', 'predb_id', 'isrenamed', 'iscategorized', 'proc_crc32', 'videos_id', 'tv_episodes_id', 'tv_lookup_attempts'] as $column) {
                $table->integer($column)->default(0);
            }
            $table->dateTime('tv_lookup_retry_at')->nullable();
            $table->string('imdbid')->nullable();
            $table->integer('movieinfo_id')->nullable();
            foreach (['musicinfo_id', 'consoleinfo_id', 'bookinfo_id', 'anidbid'] as $column) {
                $table->integer($column)->nullable();
            }
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect();
        parent::tearDown();
        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }
        foreach ($this->originalEnvironment as $key => $value) {
            $this->setEnvironment($key, $value);
        }
    }

    public function test_quiet_match_persists_resets_metadata_and_synchronizes_memory_event_and_search(): void
    {
        $release = $this->release();
        $service = $this->service();

        $this->assertTrue($service->applyCrcMatch($release, 'Movie.Title.2024-GRP', 42));

        $this->assertDatabaseHas('releases', [
            'id' => $release->id, 'searchname' => 'Movie.Title.2024-GRP',
            'predb_id' => 42, 'proc_crc32' => 1, 'isrenamed' => 1, 'iscategorized' => 1,
            'videos_id' => 0, 'tv_episodes_id' => 0, 'tv_lookup_attempts' => 0, 'tv_lookup_retry_at' => null,
            'imdbid' => null, 'movieinfo_id' => null, 'musicinfo_id' => null,
        ]);
        $this->assertSame('Movie.Title.2024-GRP', $release->searchname);
        $this->assertSame(42, $release->predb_id);
        $this->assertSame(42, $release->preid);
        $this->assertSame(1, $release->proc_crc32);
        $this->assertSame(0, $release->videos_id);
        $this->assertSame([$release->id], $this->searchUpdates);
        Event::assertDispatchedTimes(ReleaseNameFixed::class, 1);
        $this->assertTrue($service->matched);
        $this->assertSame(1, $service->fixed);
    }

    public function test_same_title_attachment_preserves_metadata_and_is_idempotent(): void
    {
        $release = $this->release('Movie.Title.2024-GRP');
        $service = $this->service();

        $this->assertTrue($service->applyCrcMatch($release, 'Movie.Title.2024-GRP', 42));
        $this->assertTrue($service->applyCrcMatch($release, 'Movie.Title.2024-GRP', 42));

        $this->assertDatabaseHas('releases', [
            'id' => $release->id, 'predb_id' => 42, 'proc_crc32' => 1, 'isrenamed' => 1,
            'videos_id' => 99, 'imdbid' => 'tt12345', 'movieinfo_id' => 55,
        ]);
        $this->assertSame(42, $release->predb_id);
        $this->assertSame([$release->id], $this->searchUpdates);
        Event::assertNotDispatched(ReleaseNameFixed::class);
        $this->assertSame(1, $service->fixed);
    }

    public function test_eloquent_input_receives_synchronous_recategorization_changes(): void
    {
        $release = Release::query()->findOrFail($this->release()->id);
        $release->preid = 123;
        Event::swap(new Dispatcher($this->app));
        Event::listen(ReleaseNameFixed::class, static function (ReleaseNameFixed $event): void {
            DB::table('releases')->where('id', $event->releaseId)->update(['categories_id' => 2040]);
        });

        $this->assertTrue($this->service()->applyCrcMatch($release, 'Movie.Title.2024-GRP', 42));

        $this->assertSame(2040, $release->categories_id);
        $this->assertSame(42, $release->predb_id);
        $this->assertSame(42, $release->preid);
        $this->assertSame(1, $release->isrenamed);
        $this->assertSame(1, $release->proc_crc32);
        $this->assertSame('Movie.Title.2024-GRP', $release->searchname);
        $this->assertSame(0, $release->videos_id);
    }

    public function test_dry_run_rename_and_attachment_do_not_write_or_mutate_memory(): void
    {
        $release = $this->release('Movie.Title.2024-GRP');
        $original = clone $release;
        $service = $this->service();
        DB::enableQueryLog();

        $this->assertTrue($service->applyCrcMatch($release, 'Movie.Title.2024-GRP', 42, false));
        $this->assertTrue($service->applyCrcMatch($release, 'Other.Title.2024-GRP', 43, false));

        $this->assertSame([], DB::getQueryLog());
        $this->assertEquals($original, $release);
        $this->assertSame([], $this->searchUpdates);
        Event::assertNotDispatched(ReleaseNameFixed::class);
        $this->assertDatabaseHas('releases', ['id' => $release->id, 'predb_id' => 0, 'proc_crc32' => 0]);
    }

    public function test_dry_run_does_not_block_subsequent_real_apply(): void
    {
        $release = $this->release();
        $service = $this->service();

        $this->assertTrue($service->applyCrcMatch($release, 'Movie.Title.2024-GRP', 42, false));
        $this->assertTrue($service->applyCrcMatch($release, 'Movie.Title.2024-GRP', 42));
        $this->assertDatabaseHas('releases', ['id' => $release->id, 'predb_id' => 42, 'proc_crc32' => 1]);
    }

    public function test_remote_title_without_predb_id_is_allowed_only_for_dry_run(): void
    {
        $release = $this->release();
        $service = $this->service();

        $this->assertTrue($service->applyCrcMatch($release, 'Movie.Title.2024-GRP', 0, false));
        $this->assertFalse($service->applyCrcMatch($release, 'Movie.Title.2024-GRP', 0));
        $this->assertFalse($service->applyCrcMatch($release, 'Movie.Title.2024-GRP', -1, false));
        $this->assertFalse($service->matched);
        $this->assertSame([], $this->searchUpdates);
        Event::assertNotDispatched(ReleaseNameFixed::class);
        $this->assertDatabaseHas('releases', ['id' => $release->id, 'predb_id' => 0, 'searchname' => 'obfuscated']);
    }

    public function test_rejected_attempt_resets_success_state_and_missing_rows_never_report_success(): void
    {
        $service = $this->service();
        $release = $this->release();
        $this->assertTrue($service->applyCrcMatch($release, 'Movie.Title.2024-GRP', 42));

        $this->assertFalse($service->applyCrcMatch($release, '', 42));
        $this->assertFalse($service->matched);
        $this->assertTrue($service->done);
        $this->assertFalse($service->applyCrcMatch((object) ['id' => 999], 'Other.Title.2024-GRP', 43));
        $this->assertFalse($service->matched);
        $this->assertFalse($service->applyCrcMatch($release, 'Other.Title.2024-GRP', 43));
        $this->assertFalse($service->matched);
        $this->assertSame([$release->id], $this->searchUpdates);
        Event::assertDispatchedTimes(ReleaseNameFixed::class, 1);
    }

    public function test_legacy_crc_types_have_consistent_flags_and_array_input_remains_supported(): void
    {
        foreach (['CRC32, ', 'CRC/OSO, ', 'CRC/Size, '] as $type) {
            $release = $this->release();
            $service = $this->service();
            $service->updateRelease((array) $release, 'Movie.Title.2024-GRP', 'verified match', true, $type, true, false, 42);

            $this->assertTrue($service->matched);
            $this->assertDatabaseHas('releases', ['id' => $release->id, 'predb_id' => 42, 'proc_crc32' => 1, 'isrenamed' => 1]);
        }
    }

    public function test_database_rollback_does_not_report_or_index_a_success(): void
    {
        $release = $this->release();
        $service = $this->service();
        try {
            DB::transaction(function () use ($service, $release): void {
                $this->assertTrue($service->applyCrcMatch($release, 'Movie.Title.2024-GRP', 42));
                $this->assertSame([], $this->searchUpdates);
                Event::assertNotDispatched(ReleaseNameFixed::class);

                throw new \RuntimeException('hash cache write failed');
            });
            $this->fail('Expected the transaction to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('hash cache write failed', $exception->getMessage());
        }

        $this->assertSame(0, $service->fixed);
        $this->assertSame([], $this->searchUpdates);
        Event::assertNotDispatched(ReleaseNameFixed::class);
        $this->assertDatabaseHas('releases', ['id' => $release->id, 'predb_id' => 0, 'searchname' => 'obfuscated']);
    }

    public function test_outer_commit_publishes_events_and_search_exactly_once(): void
    {
        $release = $this->release();
        $service = $this->service();

        DB::transaction(function () use ($service, $release): void {
            $this->assertTrue($service->applyCrcMatch($release, 'Movie.Title.2024-GRP', 42));
            Event::assertNotDispatched(ReleaseNameFixed::class);
            $this->assertSame([], $this->searchUpdates);
            $this->assertSame(0, $service->fixed);
        });

        Event::assertDispatchedTimes(ReleaseNameFixed::class, 1);
        $this->assertSame([$release->id], $this->searchUpdates);
        $this->assertSame(1, $service->fixed);
    }

    public function test_postcommit_listener_failure_propagates_and_refreshes_committed_category(): void
    {
        $release = $this->release();
        $service = $this->service();
        Event::swap(new Dispatcher($this->app));
        Event::listen(ReleaseNameFixed::class, static function (ReleaseNameFixed $event): void {
            DB::table('releases')->where('id', $event->releaseId)->update(['categories_id' => 2040]);

            throw new \RuntimeException('listener downstream service failed');
        });

        try {
            DB::transaction(fn (): bool => $service->applyCrcMatch($release, 'Movie.Title.2024-GRP', 42));
            $this->fail('Expected the post-commit listener failure to propagate.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('listener downstream service failed', $exception->getMessage());
        }

        $this->assertSame(2040, $release->categories_id);
        $this->assertSame(42, $release->predb_id);
        $this->assertSame('Movie.Title.2024-GRP', $release->searchname);
        $this->assertTrue($service->matched);
        $this->assertSame(1, $service->fixed);
        $this->assertSame([], $this->searchUpdates);
        $this->assertDatabaseHas('releases', ['id' => $release->id, 'predb_id' => 42, 'categories_id' => 2040]);
    }

    public function test_postcommit_search_failure_propagates_without_reverting_committed_state(): void
    {
        $release = $this->release();
        $service = new ReleaseUpdateService(searchSyncCoordinator: new ReleaseSearchSyncCoordinator(
            new PersistenceMetricsCollector,
            static function (int $id): void {
                throw new \RuntimeException('search unavailable');
            }
        ));

        try {
            $service->applyCrcMatch($release, 'Movie.Title.2024-GRP', 42);
            $this->fail('Expected the post-commit search failure to propagate.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('search unavailable', $exception->getMessage());
        }

        $this->assertSame(42, $release->predb_id);
        $this->assertSame(42, $release->preid);
        $this->assertSame(1, $service->fixed);
        $this->assertTrue($service->matched);
        $this->assertDatabaseHas('releases', ['id' => $release->id, 'predb_id' => 42, 'searchname' => 'Movie.Title.2024-GRP']);
        Event::assertDispatchedTimes(ReleaseNameFixed::class, 1);
    }

    private function service(): ReleaseUpdateService
    {
        return new ReleaseUpdateService(searchSyncCoordinator: new ReleaseSearchSyncCoordinator(
            new PersistenceMetricsCollector,
            function (int $id): void {
                $this->searchUpdates[] = $id;
            }
        ));
    }

    private function release(string $name = 'obfuscated'): object
    {
        $attributes = Release::factory()->make([
            'name' => $name, 'searchname' => $name, 'groups_id' => 1, 'categories_id' => 8010,
        ])->only(['name', 'searchname', 'groups_id', 'categories_id']);
        $id = DB::table('releases')->insertGetId($attributes + [
            'videos_id' => 99, 'tv_episodes_id' => 88, 'tv_lookup_attempts' => 5, 'tv_lookup_retry_at' => now()->addWeek(),
            'imdbid' => 'tt12345', 'movieinfo_id' => 55, 'musicinfo_id' => 77,
        ]);

        return DB::table('releases')->where('id', $id)->first();
    }

    private function setEnvironment(string $key, string|false $value): void
    {
        if ($value === false) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
}
