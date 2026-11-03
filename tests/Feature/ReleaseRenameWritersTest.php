<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RenameApplyResult;
use App\Events\ReleaseNameFixed;
use App\Models\Predb;
use App\Models\Release;
use App\Services\AdditionalProcessing\ReleaseSearchSyncCoordinator;
use App\Services\AdditionalProcessing\State\PersistenceMetricsCollector;
use App\Services\Categorization\CategorizationService;
use App\Services\NameFixing\ArtOfUsenetCleanerService;
use App\Services\NameFixing\Data\ReleaseIdentity;
use App\Services\NameFixing\ReleaseUpdateService;
use App\Services\NameFixing\ReversedNameDetectorService;
use App\Services\Nzb\NzbContentsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use SimpleXMLElement;
use Tests\Support\ReleaseRenameDatabase;
use Tests\TestCase;

/**
 * Renames found outside the name-fixing checks use the same guarded write as updateRelease().
 */
class ReleaseRenameWritersTest extends TestCase
{
    use ReleaseRenameDatabase;

    private const string REVERSED = '2D.20xoB011.furieziloP';

    private const string DECODED = 'Polizeiruf.110Box02.D2';

    /** @var list<int> */
    private array $synchronized = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->createReleaseRenameSchema();
        Event::fake([ReleaseNameFixed::class]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        $this->removeReleaseRenameDatabase();
        parent::tearDown();
        $this->restoreReleaseRenameEnvironment();
    }

    #[Test]
    public function apply_rename_writes_under_the_identity_guard_and_never_touches_the_predb_link(): void
    {
        $release = $this->insertClaimedRelease(['searchname' => 'raw.subject', 'predb_id' => 9]);
        $updates = $this->updates();

        $this->assertSame(RenameApplyResult::TooLong, $updates->applyRename(ReleaseIdentity::fromRelease($release), str_repeat('x', 256)));
        $this->assertSame(RenameApplyResult::Applied, $updates->applyRename(ReleaseIdentity::fromRelease($release), 'Clean.Name-GROUP', ['isrenamed' => 1, 'predb_id' => 0]));

        $row = DB::table('releases')->first();
        $this->assertSame('Clean.Name-GROUP', $row->searchname);
        $this->assertSame(9, (int) $row->predb_id);
        $this->assertSame([1], $this->synchronized);
        Event::assertNotDispatched(ReleaseNameFixed::class);

        $this->assertSame(RenameApplyResult::StaleIdentity, $updates->applyRename(ReleaseIdentity::fromRelease($release), 'Other.Name-GROUP'));
        $this->assertSame('Clean.Name-GROUP', DB::table('releases')->value('searchname'));
        $this->assertSame(RenameApplyResult::Applied, $updates->applyRename(ReleaseIdentity::fromRelease(DB::table('releases')->first()), 'Final.Name-GROUP', recategorize: true));
        Event::assertDispatched(ReleaseNameFixed::class, fn (ReleaseNameFixed $event): bool => $event->oldName === 'Clean.Name-GROUP');
    }

    #[Test]
    public function the_art_of_usenet_cleaner_renames_through_the_guard_and_keeps_its_password_flag(): void
    {
        $this->insertClaimedRelease(['name' => self::REVERSED.' [Art-of-Usenet] [Password] "'.self::REVERSED.'.par2"', 'searchname' => self::REVERSED, 'passwordstatus' => 0]);
        $cleaner = new ArtOfUsenetCleanerService;
        $cleaner->setReleaseUpdateService($this->updates());

        $stats = $cleaner->processReleases();

        $row = DB::table('releases')->first();
        $this->assertSame(1, $stats['renamed']);
        $this->assertSame(self::DECODED, $row->searchname);
        $this->assertSame(1, (int) $row->isrenamed);
        $this->assertSame(1, (int) $row->passwordstatus);
        $this->assertSame([1], $this->synchronized);
    }

    #[Test]
    public function a_cleaner_never_overwrites_a_release_renamed_after_it_was_read(): void
    {
        $this->insertClaimedRelease(['name' => self::REVERSED.' [Art-of-Usenet] "'.self::REVERSED.'.par2"', 'searchname' => self::REVERSED]);
        $racing = new class(null, null, new ReleaseSearchSyncCoordinator(new PersistenceMetricsCollector, static function (int $id): void {}, false)) extends ReleaseUpdateService
        {

            public function applyRename(ReleaseIdentity $identity, string $newTitle, array $columns = [], bool $recategorize = false, ?object $release = null): RenameApplyResult
            {
                DB::table('releases')->where('id', $identity->id)->update(['searchname' => 'Renamed.Elsewhere-GROUP']);

                return parent::applyRename($identity, $newTitle, $columns, $recategorize, $release);
            }
        };
        $cleaner = new ArtOfUsenetCleanerService;
        $cleaner->setReleaseUpdateService($racing);

        $stats = $cleaner->processReleases();

        $this->assertSame(0, $stats['renamed']);
        $this->assertSame('Renamed.Elsewhere-GROUP', DB::table('releases')->value('searchname'));
    }

    #[Test]
    public function the_reversed_name_decoder_renames_through_the_guard(): void
    {
        $this->insertClaimedRelease(['searchname' => self::REVERSED]);
        $detector = new ReversedNameDetectorService;
        $detector->setReleaseUpdateService($this->updates());
        $release = Release::query()->findOrFail(1);

        $result = $detector->processRelease($release);

        $this->assertTrue($result['processed']);
        $this->assertSame(self::DECODED, DB::table('releases')->value('searchname'));
        $this->assertSame(self::DECODED, $release->searchname);
        $this->assertFalse($release->isDirty(), 'The model is not left dirty for an unguarded save.');
        $this->assertSame([1], $this->synchronized);
    }

    #[Test]
    public function predb_check_only_links_byte_identical_names(): void
    {
        Schema::drop('predb');
        Schema::create('predb', function (Blueprint $table): void {
            $table->increments('id');
            // NOCASE mirrors the case-insensitive production collation used by the join.
            $table->string('title')->collation('NOCASE')->unique();
        });
        DB::table('predb')->insert([['id' => 7, 'title' => 'Exact.Title-GROUP'], ['id' => 8, 'title' => 'Case.Title-GROUP']]);
        $this->insertClaimedRelease(['searchname' => 'Exact.Title-GROUP']);
        $this->insertClaimedRelease(['id' => 2, 'guid' => str_repeat('b', 40), 'searchname' => 'case.title-group']);
        $this->insertClaimedRelease(['id' => 3, 'guid' => str_repeat('c', 40), 'searchname' => 'Exact.Title-GROUP', 'predb_id' => 0]);
        $this->app->instance(ReleaseUpdateService::class, $this->updates());

        Predb::checkPre();

        $this->assertSame([1 => 7, 2 => 0, 3 => 7], DB::table('releases')->orderBy('id')->pluck('predb_id', 'id')->map(static fn (mixed $id): int => (int) $id)->all());
    }

    #[Test]
    public function par2_name_lookup_only_downloads_single_article_par2_files_starting_at_segment_one(): void
    {
        $this->insertClaimedRelease();
        $nzb = new SimpleXMLElement(<<<'XML'
            <nzb>
              <file subject="&quot;movie.part01.rar&quot; yEnc (1/50)"><segments><segment number="1">rar-1@test</segment></segments></file>
              <file subject="&quot;movie.mkv&quot; yEnc (1/1)"><segments><segment number="1">mkv-1@test</segment></segments></file>
              <file subject="&quot;movie.par2&quot; yEnc (1/1)"><segments><segment number="2">wrong@test</segment><segment number="1">par2-1@test</segment></segments></file>
            </nzb>
            XML);
        $contents = new class($nzb) extends NzbContentsService
        {
            /** @var list<string> */
            public array $downloaded = [];

            public function __construct(private readonly SimpleXMLElement $nzb) {}

            public function loadNzb(string $guid): SimpleXMLElement|false
            {
                return $this->nzb;
            }

            protected function parsePar2Article(string $messageId, int $relID, int $groupID, int $show): bool
            {
                $this->downloaded[] = $messageId;

                return true;
            }
        };

        $this->assertTrue($contents->checkPar2(str_repeat('a', 40), 1, 1, 1, 0));
        $this->assertSame(['par2-1@test'], $contents->downloaded);
        $this->assertSame(1, (int) DB::table('releases')->value('proc_par2'));
    }

    private function updates(): ReleaseUpdateService
    {
        $categorization = $this->createStub(CategorizationService::class);
        $categorization->method('determineCategory')->willReturn(['categories_id' => 10]);

        return new ReleaseUpdateService(
            $categorization,
            null,
            new ReleaseSearchSyncCoordinator(new PersistenceMetricsCollector, function (int $releaseId): void {
                $this->synchronized[] = $releaseId;
            }, false),
        );
    }
}
