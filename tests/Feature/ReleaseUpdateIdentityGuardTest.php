<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RenameApplyResult;
use App\Events\ReleaseNameFixed;
use App\Listeners\RecategorizeReleaseAfterNameFix;
use App\Services\AdditionalProcessing\ReleaseSearchSyncCoordinator;
use App\Services\AdditionalProcessing\State\PersistenceMetricsCollector;
use App\Services\Categorization\CategorizationService;
use App\Services\NameFixing\Data\ReleaseIdentity;
use App\Services\NameFixing\ReleaseUpdateService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ReleaseUpdateIdentityGuardTest extends TestCase
{
    private const string GUID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const string OBFUSCATED = 'd41d8cd98f00b204e9800998ecf8427e';

    private const string CANONICAL = 'Some.Show.S01E01.1080p.WEB.h264-GROUP';

    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    /**
     * @var list<int>
     */
    private array $synchronized = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-release-identity-guard-test.sqlite';
        $this->originalEnvironment = [
            'APP_ENV' => getenv('APP_ENV'),
            'DB_CONNECTION' => getenv('DB_CONNECTION'),
            'DB_DATABASE' => getenv('DB_DATABASE'),
        ];
        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        new PDO('sqlite:'.$this->databasePath);
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
            'nntmux.echocli' => false,
        ]);
        DB::purge();
        DB::reconnect();
        $this->createSchema();
        Event::fake([ReleaseNameFixed::class]);
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

    #[Test]
    public function it_applies_a_rename_and_dispatches_after_commit(): void
    {
        $release = $this->insertRelease();
        $service = $this->service();

        $service->updateRelease($release, self::CANONICAL, 'test', true, 'PAR2, ', true, false);

        $this->assertSame(RenameApplyResult::Applied, $service->lastResult);
        $this->assertSame(self::CANONICAL, $this->storedSearchname());
        $this->assertSame(1, (int) DB::table('releases')->value('proc_par2'));
        $this->assertSame(self::CANONICAL, $release->searchname, 'The caller object reflects the committed row.');
        Event::assertDispatched(ReleaseNameFixed::class, fn (ReleaseNameFixed $event): bool => $event->oldName === self::OBFUSCATED
            && $event->newName === self::CANONICAL);
        $this->assertSame([1], $this->synchronized);
        $this->assertSame(1, $service->fixed);
    }

    #[Test]
    public function a_rename_requeues_metadata_lookups_with_null_instead_of_zero(): void
    {
        $release = $this->insertRelease([
            'imdbid' => '0087332',
            'movieinfo_id' => 7,
            'musicinfo_id' => -2,
            'consoleinfo_id' => -2,
            'bookinfo_id' => -3,
            'anidbid' => -1,
        ]);
        $service = $this->service();

        $service->updateRelease($release, self::CANONICAL, 'test', true, 'PAR2, ', true, false);

        $this->assertSame(RenameApplyResult::Applied, $service->lastResult);
        $stored = DB::table('releases')->where('id', 1)->first(['imdbid', 'movieinfo_id', 'musicinfo_id', 'consoleinfo_id', 'bookinfo_id', 'anidbid']);
        $this->assertNull($stored->imdbid);
        $this->assertNull($stored->movieinfo_id, 'A failed lookup of the new name must not leave the old movie linked.');
        $this->assertNull($stored->musicinfo_id, 'Lookup queues select IS NULL, so 0 or an empty string would never be retried.');
        $this->assertNull($stored->consoleinfo_id);
        $this->assertNull($stored->bookinfo_id);
        $this->assertNull($stored->anidbid);
    }

    #[Test]
    public function it_rejects_a_release_renamed_after_the_decision(): void
    {
        $release = $this->insertRelease();
        DB::table('releases')->update(['searchname' => 'Concurrent.Rename-OTHER']);
        $service = $this->service();

        $service->updateRelease($release, self::CANONICAL, 'test', true, 'PAR2, ', true, false);

        $this->assertNoCommittedEffects($service, RenameApplyResult::StaleIdentity, 'Concurrent.Rename-OTHER');
    }

    #[Test]
    public function it_rejects_a_byte_different_name_a_case_insensitive_collation_would_accept(): void
    {
        $release = $this->insertRelease(['searchname' => 'Obfuscated.Name']);
        DB::table('releases')->update(['searchname' => 'obfuscated.name']);
        $service = $this->service();

        $service->updateRelease($release, self::CANONICAL, 'test', true, 'PAR2, ', true, false);

        $this->assertNoCommittedEffects($service, RenameApplyResult::StaleIdentity, 'obfuscated.name');
    }

    #[Test]
    public function it_rejects_a_deleted_or_replaced_release(): void
    {
        $release = $this->insertRelease();
        DB::table('releases')->delete();
        $service = $this->service();
        $service->updateRelease($release, self::CANONICAL, 'test', true, 'PAR2, ', true, false);
        $this->assertSame(RenameApplyResult::Missing, $service->lastResult);

        $this->insertRelease(['guid' => str_repeat('b', 40)]);
        $service = $this->service();
        $service->updateRelease($release, self::CANONICAL, 'test', true, 'PAR2, ', true, false);

        $this->assertNoCommittedEffects($service, RenameApplyResult::StaleIdentity, self::OBFUSCATED);
    }

    #[Test]
    public function it_revalidates_the_canonical_predb_record_inside_the_transaction(): void
    {
        DB::table('predb')->insert(['id' => 7, 'title' => self::CANONICAL]);
        $release = $this->insertRelease();
        DB::table('predb')->where('id', 7)->update(['title' => 'Edited.Title-GROUP']);
        $service = $this->service();

        $service->updateRelease($release, self::CANONICAL, 'preDB: Match', true, 'PreDB FT Exact, ', true, false, 7, canonicalTitle: self::CANONICAL);
        $this->assertNoCommittedEffects($service, RenameApplyResult::PredbInvalid, self::OBFUSCATED);

        DB::table('predb')->delete();
        $service = $this->service();
        $service->updateRelease($release, self::CANONICAL, 'preDB: Match', true, 'PreDB FT Exact, ', true, false, 7);
        $this->assertNoCommittedEffects($service, RenameApplyResult::PredbInvalid, self::OBFUSCATED);
    }

    #[Test]
    public function it_never_overwrites_a_different_predb_link(): void
    {
        DB::table('predb')->insert([['id' => 7, 'title' => self::CANONICAL], ['id' => 8, 'title' => 'Other.Title-GROUP']]);
        $release = $this->insertRelease();
        DB::table('releases')->update(['predb_id' => 8]);
        $service = $this->service();

        $service->updateRelease($release, self::CANONICAL, 'preDB: Match', true, 'PreDB FT Exact, ', true, false, 7, canonicalTitle: self::CANONICAL);
        $this->assertSame(RenameApplyResult::StaleIdentity, $service->lastResult);

        $release->predb_id = 8;
        $service = $this->service();
        $service->updateRelease($release, self::CANONICAL, 'preDB: Match', true, 'PreDB FT Exact, ', true, false, 7, canonicalTitle: self::CANONICAL);

        $this->assertNoCommittedEffects($service, RenameApplyResult::PredbConflict, self::OBFUSCATED);
        $this->assertSame(8, (int) DB::table('releases')->value('predb_id'));
    }

    #[Test]
    public function it_rejects_names_that_would_be_truncated(): void
    {
        $release = $this->insertRelease();
        $service = $this->service();

        $service->updateRelease($release, str_repeat('Long.Title.', 30).'x264-GROUP', 'test', true, 'PAR2, ', true, false);

        $this->assertNoCommittedEffects($service, RenameApplyResult::TooLong, self::OBFUSCATED);
    }

    #[Test]
    public function it_never_renames_a_season_pack_to_one_of_its_episodes(): void
    {
        $season = 'Some.Show.S01.1080p.WEB.h264-GROUP';
        $release = $this->insertRelease(['name' => $season, 'searchname' => $season]);
        $service = $this->service();

        $service->updateRelease($release, self::CANONICAL, 'test', true, 'PAR2, ', true, false);

        $this->assertNoCommittedEffects($service, RenameApplyResult::SeasonPackEpisode, $season);

        $service->updateRelease($release, 'Some.Show.S01.1080p.AMZN.WEB-DL.DDP5.1.H.264-GROUP', 'test', true, 'PAR2, ', true, false);

        $this->assertSame(RenameApplyResult::Applied, $service->lastResult);
        $this->assertSame('Some.Show.S01.1080p.AMZN.WEB-DL.DDP5.1.H.264-GROUP', $this->storedSearchname());
    }

    #[Test]
    public function it_refuses_the_write_when_ownership_was_lost(): void
    {
        $release = $this->insertRelease();
        $service = $this->service();

        $service->updateRelease($release, self::CANONICAL, 'test', true, 'PAR2, ', true, false, ownershipCheck: fn (object $row): bool => false);

        $this->assertNoCommittedEffects($service, RenameApplyResult::OwnershipLost, self::OBFUSCATED);
    }

    #[Test]
    public function a_rolled_back_rename_leaves_no_events_or_search_updates(): void
    {
        $release = $this->insertRelease();
        $service = $this->service();

        $thrown = null;
        try {
            DB::transaction(function () use ($service, $release): void {
                $service->updateRelease($release, self::CANONICAL, 'test', true, 'PAR2, ', true, false);
                $this->assertSame(RenameApplyResult::Applied, $service->lastResult);

                throw new RuntimeException('outer failure');
            });
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertSame('outer failure', $thrown->getMessage());

        $this->assertSame(self::OBFUSCATED, $this->storedSearchname());
        Event::assertNotDispatched(ReleaseNameFixed::class);
        $this->assertSame([], $this->synchronized);
        $this->assertSame(self::OBFUSCATED, $release->searchname);
    }

    #[Test]
    public function reapplying_an_identical_result_is_a_no_op(): void
    {
        DB::table('predb')->insert(['id' => 7, 'title' => self::CANONICAL]);
        $release = $this->insertRelease();
        $service = $this->service();
        $service->updateRelease($release, self::CANONICAL, 'preDB: Match', true, 'PreDB FT Exact, ', true, false, 7, canonicalTitle: self::CANONICAL);
        $this->assertSame(RenameApplyResult::Applied, $service->lastResult);

        $service = $this->service();
        $service->attachPredbId(1, 7, ReleaseIdentity::fromRelease($release), self::CANONICAL);

        $this->assertSame(RenameApplyResult::Unchanged, $service->lastResult);
        $this->assertTrue($service->matched);
        Event::assertDispatchedTimes(ReleaseNameFixed::class, 1);
    }

    #[Test]
    public function attaching_a_predb_link_checks_identity_conflicts_and_the_canonical_title(): void
    {
        DB::table('predb')->insert([['id' => 7, 'title' => self::CANONICAL], ['id' => 8, 'title' => 'Other.Title-GROUP']]);
        $release = $this->insertRelease(['searchname' => self::CANONICAL]);
        $identity = ReleaseIdentity::fromRelease($release);

        $service = $this->service();
        $service->attachPredbId(1, 7, $identity, 'Stale.Joined.Title-GROUP');
        $this->assertSame(RenameApplyResult::PredbInvalid, $service->lastResult);
        $this->assertFalse($service->matched);

        DB::table('releases')->update(['searchname' => strtoupper(self::CANONICAL)]);
        $service->attachPredbId(1, 7, $identity, self::CANONICAL);
        $this->assertSame(RenameApplyResult::StaleIdentity, $service->lastResult);

        DB::table('releases')->update(['searchname' => self::CANONICAL, 'predb_id' => 8]);
        $service->attachPredbId(1, 7, new ReleaseIdentity(1, self::GUID), self::CANONICAL);
        $this->assertSame(RenameApplyResult::PredbConflict, $service->lastResult);
        $this->assertSame(8, (int) DB::table('releases')->value('predb_id'));

        DB::table('releases')->update(['predb_id' => 0]);
        $service->attachPredbId(1, 7, new ReleaseIdentity(1, self::GUID, searchname: self::CANONICAL, predbId: 0), self::CANONICAL);
        $this->assertSame(RenameApplyResult::Applied, $service->lastResult);
        $this->assertSame(7, (int) DB::table('releases')->value('predb_id'));
    }

    #[Test]
    public function a_stale_event_does_not_recategorize_a_newer_rename(): void
    {
        $this->insertRelease(['searchname' => 'Newer.Rename-GROUP', 'categories_id' => 5050]);
        $categorization = $this->createMock(CategorizationService::class);
        $categorization->expects($this->never())->method('determineCategory');

        (new RecategorizeReleaseAfterNameFix($categorization))->handle(
            new ReleaseNameFixed(1, self::OBFUSCATED, self::CANONICAL, 5050, 1)
        );

        $this->assertSame(5050, (int) DB::table('releases')->value('categories_id'));
    }

    private function assertNoCommittedEffects(ReleaseUpdateService $service, RenameApplyResult $expected, string $searchname): void
    {
        $this->assertSame($expected, $service->lastResult);
        $this->assertFalse($service->matched);
        $this->assertSame(0, $service->fixed);
        $this->assertSame($searchname, $this->storedSearchname());
        Event::assertNotDispatched(ReleaseNameFixed::class);
        $this->assertSame([], $this->synchronized);
    }

    private function service(): ReleaseUpdateService
    {
        $categorization = $this->createStub(CategorizationService::class);
        $categorization->method('determineCategory')->willReturn(['categories_id' => 5040]);

        return new ReleaseUpdateService(
            $categorization,
            null,
            new ReleaseSearchSyncCoordinator(new PersistenceMetricsCollector, function (int $releaseId): void {
                $this->synchronized[] = $releaseId;
            }, false),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function insertRelease(array $overrides = []): object
    {
        DB::table('releases')->insert([
            'id' => 1,
            'name' => self::OBFUSCATED,
            'searchname' => self::OBFUSCATED,
            'guid' => self::GUID,
            'groups_id' => 1,
            'categories_id' => 10,
            'fromname' => 'poster@example.com',
            ...$overrides,
        ]);

        return DB::table('releases')->where('id', 1)->first();
    }

    private function storedSearchname(): ?string
    {
        $value = DB::table('releases')->value('searchname');

        return $value === null ? null : (string) $value;
    }

    private function createSchema(): void
    {
        Schema::create('releases', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->default('');
            $table->string('searchname')->default('');
            $table->string('guid', 40);
            $table->unsignedInteger('groups_id')->default(0);
            $table->string('fromname')->nullable();
            $table->integer('categories_id')->default(10);
            $table->unsignedInteger('videos_id')->default(0);
            $table->integer('tv_episodes_id')->default(0);
            $table->unsignedTinyInteger('tv_lookup_attempts')->default(0);
            $table->dateTime('tv_lookup_retry_at')->nullable();
            $table->string('imdbid')->nullable();
            $table->integer('movieinfo_id')->nullable();
            $table->integer('musicinfo_id')->nullable();
            $table->integer('consoleinfo_id')->nullable();
            $table->integer('bookinfo_id')->nullable();
            $table->unsignedTinyInteger('book_lookup_attempts')->default(0);
            $table->dateTime('book_lookup_retry_at')->nullable();
            $table->dateTime('book_name_normalized_at')->nullable();
            $table->integer('anidbid')->nullable();
            $table->unsignedInteger('predb_id')->default(0);
            $table->tinyInteger('iscategorized')->default(0);
            $table->tinyInteger('isrenamed')->default(0);
            $table->tinyInteger('proc_par2')->default(0);
            $table->tinyInteger('proc_crc32')->default(0);
        });
        Schema::create('predb', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->unique();
        });
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
