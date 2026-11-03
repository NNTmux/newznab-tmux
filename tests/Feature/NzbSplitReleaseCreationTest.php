<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CollectionFileCheckStatus;
use App\Facades\Search;
use App\Models\Category;
use App\Services\CollectionCleanupService;
use App\Services\ReleaseCleaningService;
use App\Services\ReleaseCreationService;
use App\Services\Releases\ReleaseDuplicateFinder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\Support\ConfigurationTestBuilder;
use Tests\TestCase;

final class NzbSplitReleaseCreationTest extends TestCase
{
    private string $databasePath;

    /** @var array<string, string|false> */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = tempnam(sys_get_temp_dir(), 'nntmux-nzbsplit-test-');
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->databasePath] as $key => $value) {
            $this->originalEnvironment[$key] = getenv($key);
            $this->environment($key, $value);
        }
        $pdo = new PDO('sqlite:'.$this->databasePath);
        $pdo->exec('CREATE TABLE settings (name VARCHAR PRIMARY KEY, value TEXT NULL)');
        $pdo->exec("INSERT INTO settings VALUES ('categorizeforeign', '0'), ('catwebdl', '0')");
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $this->databasePath, 'nntmux.echocli' => false]);
        DB::purge();
        DB::reconnect();
        Cache::flush();
        DB::statement('CREATE TABLE releases (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, searchname TEXT, totalpart INTEGER, groups_id INTEGER, adddate TEXT, guid TEXT, leftguid TEXT, postdate TEXT, fromname TEXT, size INTEGER, passwordstatus INTEGER, haspreview INTEGER, categories_id INTEGER, nfostatus INTEGER, nzbstatus INTEGER, isrenamed INTEGER, iscategorized INTEGER, predb_id INTEGER, source INTEGER)');
        DB::statement('CREATE TABLE usenet_groups (id INTEGER PRIMARY KEY, name TEXT)');
        DB::statement('CREATE TABLE collections (id INTEGER PRIMARY KEY, subject TEXT, fromname TEXT, groups_id INTEGER, filecheck INTEGER, filesize INTEGER, totalfiles INTEGER, date TEXT, xref TEXT, collection_regexes_id INTEGER, releases_id INTEGER)');
        DB::statement('CREATE TABLE release_regexes (releases_id INTEGER, collection_regex_id INTEGER, naming_regex_id INTEGER)');
        DB::statement('CREATE TABLE releases_groups (releases_id INTEGER, groups_id INTEGER)');
        DB::statement('CREATE TABLE category_regexes (id INTEGER, group_regex TEXT, regex TEXT, status INTEGER, ordinal INTEGER, categories_id INTEGER)');
        DB::table('usenet_groups')->insert(['id' => 1, 'name' => 'alt.binaries.misc']);
        ConfigurationTestBuilder::installDefaults();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unlink($this->databasePath);
        foreach ($this->originalEnvironment as $key => $value) {
            $this->environment($key, $value === false ? null : $value);
        }
    }

    public function test_sample_only_nzb_split_collection_is_deleted_instead_of_released(): void
    {
        $this->insertCollection('[1/2] "MOTD__NZBSPLIT__16fdb79df730a11998d802bc408b8101__NZBSPLIT__match.of.the.day.2026.05.17.1080p.hdtv.h264-ftp.sample.7z.001" yEnc');
        $cleaner = $this->createMock(ReleaseCleaningService::class);
        $cleaner->expects(self::never())->method('releaseCleaner');
        $cleanup = $this->createMock(CollectionCleanupService::class);
        $cleanup->expects(self::once())
            ->method('deleteCollectionsAndDescendants')
            ->with([1], 'NZBSPLIT sample-only', false)
            ->willReturn(1);

        $service = new ReleaseCreationService($cleaner, $cleanup, new ReleaseDuplicateFinder);

        self::assertSame(['added' => 0, 'dupes' => 0], $service->createReleases(null, 10, false));
        self::assertSame(0, DB::table('releases')->count());
    }

    public function test_sample_only_nzb_split_collection_is_released_when_the_strict_mode_is_off(): void
    {
        config(['nntmux.nzbsplit_require_complete_sets' => false]);
        $this->insertCollection('[1/2] "MOTD__NZBSPLIT__16fdb79df730a11998d802bc408b8101__NZBSPLIT__match.of.the.day.2026.05.17.1080p.hdtv.h264-ftp.sample.7z.001" yEnc');
        Search::shouldReceive('matchPredbExact')->andReturn(null);
        Search::shouldReceive('updateRelease')->once()->with(1);

        $service = new ReleaseCreationService(new ReleaseCleaningService, $this->createMock(CollectionCleanupService::class), new ReleaseDuplicateFinder);

        self::assertSame(['added' => 1, 'dupes' => 0], $service->createReleases(null, 10, false));
        self::assertSame('match.of.the.day.2026.05.17.1080p.hdtv.h264-ftp.sample', DB::table('releases')->value('searchname'));
    }

    public function test_nzb_split_uploader_tag_categorizes_a_title_without_league_as_sport(): void
    {
        $subject = '[04/98] "NHL__NZBSPLIT__402e05a035df97cfd4f50a08e28d6ea8__NZBSPLIT__Playoffs_14-05-2026_Montreal_Canadiens_vs_Buffalo_Sabres_R2,_GM_5_720p60_EN_TNT.7z.004" yEnc';
        $this->insertCollection($subject, totalFiles: 98);
        Search::shouldReceive('matchPredbExact')->andReturn(null);
        Search::shouldReceive('updateRelease')->once()->with(1);

        $service = new ReleaseCreationService(new ReleaseCleaningService, $this->createMock(CollectionCleanupService::class), new ReleaseDuplicateFinder);

        self::assertSame(['added' => 1, 'dupes' => 0], $service->createReleases(null, 10, false));
        $release = DB::table('releases')->first();
        self::assertSame('Playoffs.14-05-2026.Montreal.Canadiens.vs.Buffalo.Sabres.R2,.GM.5.720p60.EN.TNT', $release->searchname);
        self::assertSame($subject, $release->name);
        self::assertSame(Category::TV_SPORT, (int) $release->categories_id);
        self::assertSame(1, (int) $release->isrenamed);
    }

    public function test_random_quoted_filename_is_not_treated_as_a_proper_name(): void
    {
        DB::statement('CREATE TABLE release_naming_regexes (id INTEGER PRIMARY KEY, group_regex TEXT, regex TEXT, status INTEGER, ordinal INTEGER)');
        DB::connection()->getPdo()->sqliteCreateFunction('REGEXP', static fn (): int => 0, 2);
        $this->insertCollection('[43/84] "2CeHrOvvNeiGTXAfDTZqCZ.part42.rar" yEnc', totalFiles: 84);
        Search::shouldReceive('matchPredbExact')->andReturn(null);
        Search::shouldReceive('updateRelease')->once()->with(1);

        $service = new ReleaseCreationService(new ReleaseCleaningService, $this->createMock(CollectionCleanupService::class), new ReleaseDuplicateFinder);

        self::assertSame(['added' => 1, 'dupes' => 0], $service->createReleases(null, 10, false));
        self::assertSame(0, (int) DB::table('releases')->value('isrenamed'), 'left for the PAR2 rename');
    }

    private function insertCollection(string $subject, int $totalFiles = 2): void
    {
        DB::table('collections')->insert([
            'id' => 1,
            'subject' => $subject,
            'fromname' => 'progr@mmer.org',
            'groups_id' => 1,
            'filecheck' => CollectionFileCheckStatus::Sized->value,
            'filesize' => 70_000_000,
            'totalfiles' => $totalFiles,
            'date' => '2026-10-01 00:00:00',
            'xref' => '',
            'collection_regexes_id' => 0,
        ]);
    }

    private function environment(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        } else {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
    }
}
