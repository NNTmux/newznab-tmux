<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Release;
use App\Services\Binaries\BinariesConfig;
use App\Services\CollectionCleanupService;
use App\Services\Nzb\NzbCreationCandidateQuery;
use App\Services\Nzb\NzbService;
use App\Services\ReleaseImageService;
use App\Services\ReleaseProcessingService;
use App\Services\Releases\ReleaseManagementService;
use App\Support\Data\NzbCreationResult;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use Psr\Log\AbstractLogger;
use Tests\TestCase;

class NzbCreationReliabilityTest extends TestCase
{
    private string $databasePath = '';

    private string $tempNzbPath = '';

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = $this->makeTempPath('nntmux-nzb-creation-reliability-test', '.sqlite');

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
        $pdo->exec(
            'INSERT INTO settings (name, value) VALUES '.
            "('categorizeforeign', '0'), ".
            "('catwebdl', '0'), ".
            "('innerfileblacklist', '')"
        );

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

        $this->tempNzbPath = sys_get_temp_dir().'/nntmux-nzb-reliability-'.uniqid('', true);
        mkdir($this->tempNzbPath, 0775, true);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $this->databasePath]);
        config(['nntmux_settings.path_to_nzbs' => $this->tempNzbPath]);
        DB::purge();
        DB::reconnect();
        $this->registerSqliteFunction('UNIX_TIMESTAMP', static fn (?string $value): int => strtotime((string) $value));

        $this->createSchema();
        $this->seedSettings();
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempNzbPath);
        if ($this->databasePath !== '' && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();

        foreach ($this->originalEnvironment as $key => $value) {
            $this->setEnvironmentValue($key, $value === false ? null : $value);
        }
    }

    public function test_candidate_query_skips_active_claims_and_recovers_stale_claims(): void
    {
        $this->insertRelease(1, 'a', claimedAt: now(), postdate: '2026-07-13 10:00:00');
        $this->insertRelease(2, 'b', claimedAt: now()->subSeconds(301), postdate: '2026-07-13 09:00:00');
        $this->insertRelease(3, 'c', postdate: '2026-07-13 08:00:00');

        $claimed = NzbCreationCandidateQuery::claimBatch(null, 10, 'token-one', ['id']);

        $this->assertSame([2, 3], $claimed->pluck('id')->all());
        $this->assertSame('token-one', DB::table('releases')->where('id', 2)->value('nzb_creation_claim_token'));
        $this->assertSame('claimed', DB::table('releases')->where('id', 1)->value('nzb_creation_claim_token'));
    }

    public function test_deterministic_creation_failure_deletes_release_and_cbp_rows(): void
    {
        $this->insertRelease(1, 'a');
        $this->insertCbp(200, 2000, 1);

        $service = $this->releaseProcessingService(
            NzbCreationResult::deterministic('Collection has invalid data.', [200])
        );

        $this->assertSame(0, $service->createNZBs(null));
        $this->assertSame(0, DB::table('releases')->count());
        $this->assertSame(0, DB::table('collections')->count());
        $this->assertSame(0, DB::table('binaries')->count());
        $this->assertSame(0, DB::table('parts')->count());
    }

    public function test_transient_creation_failure_records_retry_before_threshold(): void
    {
        Log::partialMock();
        $nzbCreationLogger = new RecordingNzbCreationLogger;
        Log::shouldReceive('channel')
            ->once()
            ->with('nzb_creation')
            ->andReturn($nzbCreationLogger);

        $this->insertRelease(1, 'a', attempts: 0);
        $this->insertCbp(200, 2000, 1);

        $service = $this->releaseProcessingService(
            NzbCreationResult::transient('Temporary filesystem failure.', [200])
        );

        $this->assertSame(0, $service->createNZBs(null));
        $this->assertSame(1, DB::table('releases')->count());
        $this->assertSame(1, (int) DB::table('releases')->where('id', 1)->value('nzb_creation_attempts'));
        $this->assertSame('Temporary filesystem failure.', DB::table('releases')->where('id', 1)->value('nzb_creation_last_error'));
        $this->assertNull(DB::table('releases')->where('id', 1)->value('nzb_creation_claimed_at'));
        $this->assertSame(1, DB::table('collections')->count());
        $this->assertSame('NZB creation failed; release will be retried', $nzbCreationLogger->warnings[0]['message']);
        $this->assertSame(1, $nzbCreationLogger->warnings[0]['context']['release_id']);
        $this->assertSame(str_repeat('a', 36), $nzbCreationLogger->warnings[0]['context']['guid']);
        $this->assertSame(NzbCreationResult::FAILURE_TRANSIENT, $nzbCreationLogger->warnings[0]['context']['failure_type']);
        $this->assertSame('Temporary filesystem failure.', $nzbCreationLogger->warnings[0]['context']['reason']);
        $this->assertSame(1, $nzbCreationLogger->warnings[0]['context']['next_attempt']);
        $this->assertSame(3, $nzbCreationLogger->warnings[0]['context']['max_attempts']);
    }

    public function test_third_transient_creation_failure_deletes_release_and_cbp_rows(): void
    {
        Log::partialMock();
        $nzbCreationLogger = new RecordingNzbCreationLogger;
        Log::shouldReceive('channel')
            ->once()
            ->with('nzb_creation')
            ->andReturn($nzbCreationLogger);

        $this->insertRelease(1, 'a', attempts: 2);
        $this->insertCbp(200, 2000, 1);

        $service = $this->releaseProcessingService(
            NzbCreationResult::transient('Repeated filesystem failure.', [200])
        );

        $this->assertSame(0, $service->createNZBs(null));
        $this->assertSame(0, DB::table('releases')->count());
        $this->assertSame(0, DB::table('collections')->count());
        $this->assertSame(0, DB::table('binaries')->count());
        $this->assertSame(0, DB::table('parts')->count());
        $this->assertSame('Deleting release after NZB creation failure', $nzbCreationLogger->warnings[0]['message']);
        $this->assertSame(1, $nzbCreationLogger->warnings[0]['context']['release_id']);
        $this->assertSame(str_repeat('a', 36), $nzbCreationLogger->warnings[0]['context']['guid']);
        $this->assertSame(NzbCreationResult::FAILURE_TRANSIENT, $nzbCreationLogger->warnings[0]['context']['failure_type']);
        $this->assertSame('Repeated filesystem failure.', $nzbCreationLogger->warnings[0]['context']['reason']);
        $this->assertSame(3, $nzbCreationLogger->warnings[0]['context']['attempt']);
        $this->assertSame(3, $nzbCreationLogger->warnings[0]['context']['max_attempts']);
    }

    public function test_writer_classifies_final_rename_failure_as_transient_without_final_file(): void
    {
        $this->insertRelease(1, 'd');
        $this->insertWritableCbp(200, 2000, 1);
        $release = Release::query()->findOrFail(1);
        $release->setRelation('category', (object) ['title' => 'Misc', 'parent' => (object) ['title' => 'Other']]);

        $nzb = new RenameFailingNzbService(app(CollectionCleanupService::class));
        $result = $nzb->createNzbForRelease($release);

        $this->assertFalse($result->success);
        $this->assertTrue($result->isTransientFailure());
        $this->assertStringContainsString('move temporary NZB into place', $result->reason);
        $this->assertFileDoesNotExist($nzb->getNzbPath($release->guid));
        $this->assertSame(0, (int) DB::table('releases')->where('id', 1)->value('nzbstatus'));
    }

    public function test_writer_streams_multiple_keyset_pages_in_segment_order(): void
    {
        $this->insertRelease(1, 'h');
        $this->insertWritableCbp(200, 2000, 1);
        DB::table('parts')->where('binaries_id', 2000)->delete();
        DB::table('parts')->insert([
            ['binaries_id' => 2000, 'messageid' => '<part02@example.test>', 'partnumber' => 2, 'size' => 200],
            ['binaries_id' => 2000, 'messageid' => '<part01@example.test>', 'partnumber' => 1, 'size' => 100],
            ['binaries_id' => 2000, 'messageid' => '<part03@example.test>', 'partnumber' => 3, 'size' => 300],
        ]);
        $release = Release::query()->findOrFail(1);
        $release->setRelation('category', (object) ['title' => 'Misc', 'parent' => (object) ['title' => 'Other']]);

        $nzb = new NzbService(app(CollectionCleanupService::class), new BinariesConfig(nzbStreamRows: 2));
        $result = $nzb->createNzbForRelease($release);

        $this->assertTrue($result->success, $result->reason);
        $xml = unzipGzipFile((string) $result->path);
        $this->assertIsString($xml);
        $this->assertLessThan(strpos($xml, 'part02@example.test'), strpos($xml, 'part01@example.test'));
        $this->assertLessThan(strpos($xml, 'part03@example.test'), strpos($xml, 'part02@example.test'));
        $this->assertStringContainsString('<group>alt.test</group>', $xml);
    }

    public function test_stale_temporary_nzb_cleanup_deletes_only_old_temp_files(): void
    {
        $directory = $this->tempNzbPath.'/a';
        mkdir($directory, 0777, true);

        $oldTemporary = $directory.'/'.str_repeat('e', 36).'.nzb.gz.tmp.123.'.str_repeat('a', 12);
        $recentTemporary = $directory.'/'.str_repeat('f', 36).'.nzb.gz.tmp.456.'.str_repeat('b', 12);
        $finalNzb = $directory.'/'.str_repeat('g', 36).'.nzb.gz';

        file_put_contents($oldTemporary, 'old');
        file_put_contents($recentTemporary, 'recent');
        file_put_contents($finalNzb, 'final');
        touch($oldTemporary, time() - 7200);
        touch($recentTemporary, time() - 60);
        touch($finalNzb, time() - 7200);

        $nzb = new NzbService(app(CollectionCleanupService::class));

        $this->assertSame([$oldTemporary], $nzb->findStaleTemporaryNzbPaths(3600));
        $this->assertSame(1, $nzb->cleanupStaleTemporaryNzbs(3600));
        $this->assertFileDoesNotExist($oldTemporary);
        $this->assertFileExists($recentTemporary);
        $this->assertFileExists($finalNzb);
    }

    private function releaseProcessingService(NzbCreationResult $result): ReleaseProcessingService
    {
        return (new ReleaseProcessingService(
            nzb: new FakeNzbCreationService($result),
            releaseManagement: new DatabaseOnlyReleaseManagementService,
            collectionCleanupService: app(CollectionCleanupService::class),
        ))->setEchoCLI(false);
    }

    private function insertRelease(
        int $id,
        string $leftguid,
        int $attempts = 0,
        ?\DateTimeInterface $claimedAt = null,
        string $postdate = '2026-07-13 00:00:00',
    ): void {
        DB::table('releases')->insert([
            'id' => $id,
            'guid' => str_pad($leftguid, 36, $leftguid),
            'leftguid' => $leftguid,
            'name' => 'Release.'.$id,
            'searchname' => 'Release.'.$id,
            'groups_id' => 1,
            'categories_id' => 1,
            'postdate' => $postdate,
            'nzbstatus' => NzbService::NZB_NONE,
            'nzb_creation_claimed_at' => $claimedAt?->format('Y-m-d H:i:s'),
            'nzb_creation_claim_token' => $claimedAt === null ? null : 'claimed',
            'nzb_creation_attempts' => $attempts,
            'nzb_creation_last_error' => null,
        ]);
    }

    private function insertCbp(int $collectionId, int $binaryId, int $releaseId): void
    {
        DB::table('collections')->insert([
            'id' => $collectionId,
            'releases_id' => $releaseId,
        ]);
        DB::table('binaries')->insert([
            'id' => $binaryId,
            'collections_id' => $collectionId,
        ]);
        DB::table('parts')->insert([
            'binaries_id' => $binaryId,
        ]);
    }

    private function insertWritableCbp(int $collectionId, int $binaryId, int $releaseId): void
    {
        DB::table('usenet_groups')->insert(['id' => 1, 'name' => 'alt.test']);
        DB::table('collections')->insert([
            'id' => $collectionId,
            'releases_id' => $releaseId,
            'fromname' => 'poster@example.test',
            'date' => '2026-07-13 10:00:00',
            'xref' => 'alt.test:12345',
            'groups_id' => 1,
        ]);
        DB::table('binaries')->insert([
            'id' => $binaryId,
            'collections_id' => $collectionId,
            'name' => 'Example.Release.part01.rar yEnc',
            'totalparts' => 1,
        ]);
        DB::table('parts')->insert([
            'binaries_id' => $binaryId,
            'messageid' => '<part01@example.test>',
            'partnumber' => 1,
            'size' => 100,
        ]);
    }

    private function seedSettings(): void
    {
        foreach ([
            'categorizeforeign' => '0',
            'catwebdl' => '0',
            'releaseprocessingtimeout' => '120',
            'maxnzbsprocessed' => '1000',
            'nzbsplitlevel' => '1',
        ] as $name => $value) {
            DB::table('settings')->insert(['name' => $name, 'value' => $value]);
        }
    }

    private function createSchema(): void
    {
        foreach (['parts', 'binaries', 'collections', 'releases', 'categories', 'root_categories', 'usenet_groups', 'settings'] as $table) {
            DB::statement("DROP TABLE IF EXISTS {$table}");
        }

        DB::statement('CREATE TABLE settings (name VARCHAR(255) PRIMARY KEY, value TEXT)');
        DB::statement('CREATE TABLE root_categories (id INTEGER PRIMARY KEY, title VARCHAR(255), status INTEGER DEFAULT 1, disablepreview INTEGER DEFAULT 0)');
        DB::statement('CREATE TABLE categories (id INTEGER PRIMARY KEY, title VARCHAR(255), root_categories_id INTEGER NULL)');
        DB::statement('CREATE TABLE releases (
            id INTEGER PRIMARY KEY,
            guid VARCHAR(64),
            leftguid VARCHAR(1),
            name VARCHAR(255),
            searchname VARCHAR(255),
            groups_id INTEGER,
            categories_id INTEGER,
            postdate DATETIME NULL,
            nzbstatus INTEGER,
            nzb_creation_claimed_at DATETIME NULL,
            nzb_creation_claim_token VARCHAR(64) NULL,
            nzb_creation_attempts INTEGER DEFAULT 0,
            nzb_creation_last_error TEXT NULL
        )');
        DB::statement('CREATE TABLE usenet_groups (id INTEGER PRIMARY KEY, name VARCHAR(255))');
        DB::statement('CREATE TABLE collections (
            id INTEGER PRIMARY KEY,
            releases_id INTEGER NULL,
            fromname VARCHAR(255) NULL,
            date DATETIME NULL,
            xref TEXT NULL,
            groups_id INTEGER NULL
        )');
        DB::statement('CREATE TABLE binaries (
            id INTEGER PRIMARY KEY,
            collections_id INTEGER,
            name VARCHAR(255) NULL,
            totalparts INTEGER NULL
        )');
        DB::statement('CREATE TABLE parts (
            binaries_id INTEGER,
            messageid VARCHAR(255) NULL,
            partnumber INTEGER NULL,
            size INTEGER NULL
        )');
        DB::table('root_categories')->insert(['id' => 1, 'title' => 'Other']);
        DB::table('categories')->insert(['id' => 1, 'title' => 'Misc', 'root_categories_id' => 1]);
    }

    private function deleteDirectory(string $path): void
    {
        if ($path === '' || ! is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir() && ! $item->isLink()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
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

class FakeNzbCreationService extends NzbService
{
    public function __construct(private readonly NzbCreationResult $result)
    {
        parent::__construct(app(CollectionCleanupService::class));
    }

    public function createNzbForRelease(Release $release): NzbCreationResult
    {
        return $this->result;
    }
}

class DatabaseOnlyReleaseManagementService extends ReleaseManagementService
{
    /**
     * @param  array<string, mixed>  $identifiers
     */
    public function deleteSingleWithService(array $identifiers, NzbService $nzb, ReleaseImageService $releaseImage): void
    {
        Release::query()->where('guid', $identifiers['g'])->delete();
    }
}

class RenameFailingNzbService extends NzbService
{
    protected function moveTemporaryNzbIntoPlace(string $temporaryPath, string $finalPath): bool
    {
        return false;
    }
}

class RecordingNzbCreationLogger extends AbstractLogger
{
    /**
     * @var list<array{level: mixed, message: string, context: array<string, mixed>}>
     */
    public array $warnings = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->warnings[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
