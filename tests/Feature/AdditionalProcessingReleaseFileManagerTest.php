<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Facades\Search;
use App\Models\Release;
use App\Services\AdditionalProcessing\AdditionalWorkPlanner;
use App\Services\AdditionalProcessing\ArchiveExtractionService;
use App\Services\AdditionalProcessing\ConsoleOutputService;
use App\Services\AdditionalProcessing\DTO\DownloadMetrics;
use App\Services\AdditionalProcessing\Enums\DownloadKind;
use App\Services\AdditionalProcessing\Enums\ProcessingOutcome;
use App\Services\AdditionalProcessing\MediaExtractionService;
use App\Services\AdditionalProcessing\NzbContentParser;
use App\Services\AdditionalProcessing\ReleaseFileManager;
use App\Services\AdditionalProcessing\ReleaseFilesArchiveFallback;
use App\Services\AdditionalProcessing\ReleaseProcessor;
use App\Services\AdditionalProcessing\State\PersistenceMetricsCollector;
use App\Services\AdditionalProcessing\State\ReleaseProcessingContext;
use App\Services\AdditionalProcessing\UsenetDownloadService;
use App\Services\CollectionCleanupService;
use App\Services\NameFixing\NameFixingService;
use App\Services\NfoService;
use App\Services\Nzb\NzbService;
use App\Services\ReleaseImageService;
use App\Services\Releases\ReleaseBrowseService;
use App\Services\TempWorkspaceService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;
use Tests\Unit\AdditionalProcessing\BuildsArchiveFixtures;
use Tests\Unit\AdditionalProcessing\CreatesProcessingConfiguration;

class AdditionalProcessingReleaseFileManagerTest extends TestCase
{
    use BuildsArchiveFixtures;
    use CreatesProcessingConfiguration;

    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-release-file-manager-test.sqlite';

        $this->originalEnvironment = [
            'APP_ENV' => getenv('APP_ENV'),
            'DB_CONNECTION' => getenv('DB_CONNECTION'),
            'DB_DATABASE' => getenv('DB_DATABASE'),
        ];

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        $pdo = new PDO('sqlite:'.$this->databasePath);

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

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        if ($this->databasePath !== '' && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();

        foreach ($this->originalEnvironment as $key => $value) {
            $this->setEnvironmentValue($key, $value === false ? null : $value);
        }
    }

    public function test_release_file_rows_are_deduped_and_flushed_once_at_finalize(): void
    {
        DB::table('releases')->insert($this->releaseRow());

        Search::shouldReceive('updateRelease')->once()->with(1);

        $nameFixing = new CountingNameFixingService;

        $manager = $this->makeManager($nameFixing);
        $context = new ReleaseProcessingContext(Release::query()->findOrFail(1));

        $this->assertTrue($manager->addFileInfo([
            'name' => 'Example.Movie.2026.mkv',
            'size' => 1024,
            'date' => 1_788_600_000,
            'pass' => 0,
            'crc32' => 'ABC123',
        ], $context, '\\.(?:par2|sfv|nzb)'));
        $this->assertFalse($manager->addFileInfo([
            'name' => 'Example.Movie.2026.mkv',
            'size' => 1024,
            'date' => 1_788_600_000,
            'pass' => 0,
            'crc32' => 'ABC123',
        ], $context, '\\.(?:par2|sfv|nzb)'));

        $manager->finalizeRelease($context, false);

        $this->assertSame(1, $nameFixing->matchPreDbFilesCalls);
        $this->assertSame(1, DB::table('release_files')->count());
        $this->assertSame(1, DB::table('releases')->where('id', 1)->value('rarinnerfilecount'));
        $this->assertNull(DB::table('releases')->where('id', 1)->value('additional_pp_claimed_at'));
        $this->assertNull(DB::table('releases')->where('id', 1)->value('additional_pp_claim_token'));
    }

    public function test_finalization_flushes_rows_and_synchronizes_search_immediately(): void
    {
        DB::table('releases')->insert($this->releaseRow());

        Search::shouldReceive('updateRelease')->once()->with(1);

        $manager = $this->makeManagerWithImageService(new ReleaseImageService);
        $context = new ReleaseProcessingContext(Release::query()->findOrFail(1));
        $this->assertTrue($manager->addFileInfo([
            'name' => 'Example.Movie.2026.mkv',
            'size' => 2048,
            'date' => 1_788_600_000,
        ], $context, '\\.(?:par2|sfv|nzb)'));

        $manager->finalizeRelease($context, false);

        $this->assertSame(1, DB::table('release_files')->count());
        $this->assertSame(1, DB::table('releases')->where('id', 1)->value('rarinnerfilecount'));
        $this->assertSame([], $context->pendingReleaseFiles);
    }

    public function test_database_statements_are_measured_inside_an_active_release_scope(): void
    {
        DB::table('releases')->insert($this->releaseRow());
        $collector = app(PersistenceMetricsCollector::class);

        $collector->beginReleaseScope(1);
        DB::table('releases')->where('id', 1)->value('guid');
        $metrics = $collector->finishReleaseScope();

        $this->assertSame(1, $metrics->databaseStatements);
        $this->assertGreaterThanOrEqual(0.0, $metrics->databaseMilliseconds);
    }

    public function test_queued_par_hashes_flush_with_release_files(): void
    {
        DB::table('releases')->insert($this->releaseRow());

        Search::shouldReceive('updateRelease')->once()->with(1);

        $manager = $this->makeManager();
        $context = new ReleaseProcessingContext(Release::query()->findOrFail(1));

        $queue = new ReflectionMethod(ReleaseFileManager::class, 'queueReleaseFile');
        $queue->invoke(
            $manager,
            $context,
            1,
            'Example.Movie.2026.par2',
            512,
            1_788_600_000,
            0,
            '1234567890abcdef1234567890abcdef'
        );

        $manager->finalizeRelease($context, false);

        $this->assertSame(1, DB::table('release_files')->count());
        $this->assertSame(1, DB::table('par_hashes')->count());
    }

    public function test_failed_finalization_rolls_back_buffered_rows_and_keeps_them_for_retry(): void
    {
        DB::table('releases')->insert($this->releaseRow());

        Search::shouldReceive('updateRelease')->never();

        $manager = $this->makeManager();
        $context = new ReleaseProcessingContext(Release::query()->findOrFail(1));
        $this->assertTrue($manager->addFileInfo([
            'name' => 'Example.Movie.2026.mkv',
            'size' => 1024,
            'date' => 1_788_600_000,
        ], $context, '\\.(?:par2|sfv|nzb)'));

        DB::unprepared("CREATE TRIGGER fail_release_finalize BEFORE UPDATE ON releases BEGIN SELECT RAISE(ABORT, 'forced finalization failure'); END");

        try {
            $manager->finalizeRelease($context, false);
            $this->fail('Finalization should have failed.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('forced finalization failure', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_release_finalize');
        }

        $this->assertSame(0, DB::table('release_files')->count());
        $this->assertArrayHasKey('Example.Movie.2026.mkv', $context->pendingReleaseFiles);
    }

    public function test_float_release_file_size_is_normalized_before_queueing(): void
    {
        DB::table('releases')->insert($this->releaseRow());

        Search::shouldReceive('updateRelease')->once()->with(1);

        $manager = $this->makeManager();
        $context = new ReleaseProcessingContext(Release::query()->findOrFail(1));

        $this->assertTrue($manager->addFileInfo([
            'name' => 'Example.Movie.2026.mkv',
            'size' => 1024.0,
            'date' => 1_788_600_000,
        ], $context, '\\.(?:par2|sfv|nzb)'));

        $manager->finalizeRelease($context, false);

        $this->assertSame(1024, DB::table('release_files')->value('size'));
    }

    public function test_encrypted_file_marks_release_passworded(): void
    {
        DB::table('releases')->insert($this->releaseRow());

        $manager = $this->makeManager();

        // RarInfo and ZipInfo report encrypted files as 'pass' => 1, not true.
        foreach ([1, true] as $pass) {
            $context = new ReleaseProcessingContext(Release::query()->findOrFail(1));

            $this->assertFalse($manager->addFileInfo([
                'name' => 'Example.Show.S01E01.mkv',
                'size' => 1024,
                'pass' => $pass,
            ], $context, '\\.(?:par2|sfv|nzb)'));

            $this->assertTrue($context->releaseHasPassword);
            $this->assertSame(ReleaseBrowseService::PASSWD_RAR, $context->passwordStatus);
        }
    }

    public function test_a_listed_7z_finalizes_with_no_password_and_its_file_count(): void
    {
        DB::table('releases')->insert($this->releaseRow());
        Search::shouldReceive('updateRelease')->once()->with(1);

        $archive = $this->sevenZip('Example.Show.S01E01.mkv', str_repeat('x', 100));
        $archiveService = new ArchiveExtractionService($this->makeConfig());
        $manager = $this->makeManager();
        $context = new ReleaseProcessingContext(Release::query()->findOrFail(1));
        $context->nzbHasCompressedFile = true;

        $joined = $archiveService->withSevenZipEndHeader(substr($archive, 0, 40), substr($archive, -80));
        $this->assertIsString($joined);
        $result = $archiveService->processCompressedData($joined, $context, sys_get_temp_dir().'/');
        foreach ($result['files'] as $file) {
            $manager->addFileInfo($file, $context, '\\.(?:par2|sfv|nzb)');
        }
        $manager->finalizeRelease($context, true);

        $release = DB::table('releases')->where('id', 1)->first();
        $this->assertSame(ReleaseBrowseService::PASSWD_NONE, (int) $release->passwordstatus);
        $this->assertSame(1, (int) $release->rarinnerfilecount);
        $this->assertSame('Example.Show.S01E01.mkv', DB::table('release_files')->value('name'));
    }

    public function test_an_unreadable_archive_is_queued_for_one_retry_after_the_delay(): void
    {
        DB::table('releases')->insert($this->releaseRow());
        Search::shouldReceive('updateRelease')->twice()->with(1);
        config(['nntmux.archive_retry_delay' => 3600]);
        $this->freezeSecond();

        $manager = $this->makeManager();
        $context = new ReleaseProcessingContext(Release::query()->findOrFail(1));
        $context->nzbHasCompressedFile = true;
        $manager->finalizeRelease($context, true);

        $release = DB::table('releases')->where('id', 1)->first();
        $this->assertSame(-1, (int) $release->passwordstatus);
        $this->assertSame(-1, (int) $release->haspreview);
        $this->assertSame(now()->addHour()->toDateTimeString(), (string) $release->archive_retry_at);
        $this->assertNull($release->additional_pp_claimed_at);

        $this->travel(2)->hours();
        $context = new ReleaseProcessingContext(Release::query()->findOrFail(1));
        $context->nzbHasCompressedFile = true;
        $manager->finalizeRelease($context, true);

        $release = DB::table('releases')->where('id', 1)->first();
        $this->assertSame(-1, (int) $release->passwordstatus);
        $this->assertSame(0, (int) $release->haspreview);
        $this->assertSame(now()->subHour()->toDateTimeString(), (string) $release->archive_retry_at);
    }

    #[DataProvider('probedArchiveScenarios')]
    public function test_probed_archives_finalize_from_the_bounded_download_or_remain_unknown(
        string $type,
        bool $encrypted,
        ?string $failure,
    ): void {
        DB::table('releases')->insert([...$this->releaseRow(), 'nfostatus' => 1]);
        Search::shouldReceive('updateRelease')->once()->with(1);
        config(['nntmux.archive_retry_delay' => 86400]);
        $this->freezeSecond();

        $config = $this->makeConfig(['processPasswords' => true, 'maximumRarSegments' => 3]);
        $archive = $type === 'rar'
            ? $this->rar('readme.txt', str_repeat('A', 100)).substr($this->rar('Movie.2026.1080p.mkv', str_repeat('B', 100), $encrypted), 20)
            : $this->zip('readme.txt', str_repeat('A', 100)).$this->zip('Movie.2026.1080p.mkv', str_repeat('B', 100), $encrypted);
        $parser = Mockery::mock(NzbContentParser::class);
        $parser->shouldReceive('parseNzb')->once()->with('guid-1')->andReturn([
            'error' => null,
            'contents' => [[
                'title' => '"obfuscated" yEnc',
                'segments' => ['<probe>', '<second>', '<third>', '<ignored>'],
                'filecount' => 1,
            ]],
        ]);
        $download = Mockery::mock(UsenetDownloadService::class);
        $download->shouldReceive('beginReleaseScope')->once();
        $download->shouldReceive('finishReleaseScope')->once()->andReturn(new DownloadMetrics);
        $download->shouldReceive('download')->once()->with(DownloadKind::Compressed, ['<probe>'], '', 1)
            ->andReturn(['success' => true, 'data' => substr($archive, 0, 80), 'groupUnavailable' => false, 'error' => null]);
        $download->shouldReceive('download')->once()->with(DownloadKind::Compressed, ['<second>'], '', 1)
            ->andReturn(['success' => true, 'data' => substr($archive, 80, 80), 'groupUnavailable' => false, 'error' => null]);
        $download->shouldReceive('download')->once()->with(DownloadKind::Compressed, ['<third>'], '', 1)
            ->andReturn([
                'success' => $failure === null,
                'data' => $failure === null ? substr($archive, 160) : null,
                'groupUnavailable' => $failure === 'group-unavailable',
                'error' => $failure,
            ]);
        $workspace = Mockery::mock(TempWorkspaceService::class);
        $workspace->shouldReceive('createReleaseTempFolder')->once()->andReturn('/tmp/probed-archive/');
        $workspace->shouldReceive('listFiles')->andReturn([]);
        $workspace->shouldReceive('clearDirectory')->once()->with('/tmp/probed-archive/', false);
        $processor = new ReleaseProcessor(
            $config,
            $parser,
            new AdditionalWorkPlanner($config),
            new ArchiveExtractionService($config),
            Mockery::mock(MediaExtractionService::class),
            $download,
            $this->makeManager(),
            Mockery::mock(ReleaseFilesArchiveFallback::class),
            $workspace,
            Mockery::mock(ConsoleOutputService::class)->shouldIgnoreMissing(),
        );
        $context = new ReleaseProcessingContext(Release::query()->findOrFail(1));

        $result = $processor->process($context, '/tmp/');

        $release = DB::table('releases')->where('id', 1)->first();
        $this->assertTrue($context->nzbHasCompressedFile);
        $this->assertSame($failure === 'group-unavailable', $context->groupUnavailable);
        if ($failure !== null) {
            $this->assertSame(-1, (int) $release->passwordstatus);
            $this->assertSame(-1, (int) $release->haspreview);
            $this->assertSame(now()->addDay()->toDateTimeString(), $release->archive_retry_at);
            $this->assertSame(0, DB::table('release_files')->count());
        } else {
            $this->assertSame($encrypted ? ReleaseBrowseService::PASSWD_RAR : ReleaseBrowseService::PASSWD_NONE, (int) $release->passwordstatus);
            $this->assertSame(0, (int) $release->haspreview);
            $this->assertNull($release->archive_retry_at);
            $this->assertSame($encrypted ? 1 : 2, DB::table('release_files')->count());
            $this->assertSame($encrypted, $result->outcome === ProcessingOutcome::Passworded);
        }
    }

    /**
     * @return array<string, array{string, bool, string|null}>
     */
    public static function probedArchiveScenarios(): array
    {
        return [
            'plain RAR' => ['rar', false, null],
            'encrypted RAR entry' => ['rar', true, null],
            'RAR continuation unavailable' => ['rar', false, 'missing-article'],
            'RAR group unavailable' => ['rar', false, 'group-unavailable'],
            'plain ZIP' => ['zip', false, null],
            'encrypted ZIP entry' => ['zip', true, null],
            'ZIP continuation unavailable' => ['zip', false, 'missing-article'],
            'ZIP group unavailable' => ['zip', false, 'group-unavailable'],
        ];
    }

    public function test_invalid_release_file_sizes_are_rejected(): void
    {
        DB::table('releases')->insert($this->releaseRow());

        Log::shouldReceive('warning')
            ->times(4)
            ->with('Skipping release file with invalid size metadata.', Mockery::type('array'));

        $manager = $this->makeManager();
        $context = new ReleaseProcessingContext(Release::query()->findOrFail(1));
        $invalidSizes = [-1, INF, PHP_INT_MAX + 1.0, 'not-numeric'];

        foreach ($invalidSizes as $index => $invalidSize) {
            $this->assertFalse($manager->addFileInfo([
                'name' => "Invalid.Size.{$index}.mkv",
                'size' => $invalidSize,
            ], $context, '\\.(?:par2|sfv|nzb)'));
        }

        $this->assertSame(0, $context->totalFileInfo);
        $this->assertSame(0, $context->addedFileInfo);
    }

    public function test_finalize_recognizes_webp_preview_and_sample_without_moving_them(): void
    {
        DB::table('releases')->insert($this->releaseRow());
        Search::shouldReceive('updateRelease')->once()->with(1);

        $imageService = new ReleaseImageService;
        $preview = $imageService->imgSavePath.'guid-1_thumb.webp';
        $sample = $imageService->jpgSavePath.'guid-1_thumb.webp';
        File::ensureDirectoryExists(dirname($preview));
        File::ensureDirectoryExists(dirname($sample));
        File::put($preview, 'preview');
        File::put($sample, 'sample');

        try {
            $manager = $this->makeManagerWithImageService($imageService);
            $context = new ReleaseProcessingContext(Release::query()->findOrFail(1));

            $manager->finalizeRelease($context, false);

            $this->assertSame(1, DB::table('releases')->where('id', 1)->value('haspreview'));
            $this->assertSame(1, DB::table('releases')->where('id', 1)->value('jpgstatus'));
        } finally {
            File::delete([$preview, $sample]);
        }
    }

    private function makeManager(?NameFixingService $nameFixing = null): ReleaseFileManager
    {
        return $this->makeManagerWithImageService(new ReleaseImageService, $nameFixing);
    }

    /**
     * @param  array<string, mixed>  $configOverrides
     */
    private function makeManagerWithImageService(
        ReleaseImageService $imageService,
        ?NameFixingService $nameFixing = null,
        array $configOverrides = [],
    ): ReleaseFileManager {
        return new ReleaseFileManager(
            $this->makeConfig($configOverrides),
            $imageService,
            new NfoService,
            new TestNzbService,
            $nameFixing ?? new CountingNameFixingService
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function releaseRow(): array
    {
        return [
            'id' => 1,
            'guid' => 'guid-1',
            'name' => 'Example',
            'searchname' => 'Example',
            'size' => 1024,
            'groups_id' => 1,
            'nfostatus' => -1,
            'categories_id' => 10,
            'passwordstatus' => -1,
            'haspreview' => -1,
            'nzbstatus' => 1,
            'rarinnerfilecount' => 0,
            'pp_timeout_count' => 0,
            'additional_pp_claimed_at' => now(),
            'additional_pp_claim_token' => 'token',
        ];
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

        Schema::dropIfExists('par_hashes');
        Schema::dropIfExists('release_files');
        Schema::dropIfExists('releases');

        Schema::create('releases', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('guid');
            $table->string('name')->default('');
            $table->string('searchname')->default('');
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('groups_id')->default(0);
            $table->integer('nfostatus')->default(0);
            $table->integer('categories_id')->default(10);
            $table->integer('passwordstatus')->default(-1);
            $table->integer('haspreview')->default(-1);
            $table->integer('jpgstatus')->default(0);
            $table->integer('videostatus')->default(0);
            $table->integer('nzbstatus')->default(1);
            $table->integer('rarinnerfilecount')->default(0);
            $table->integer('pp_timeout_count')->default(0);
            $table->timestamp('additional_pp_claimed_at')->nullable();
            $table->string('additional_pp_claim_token', 64)->nullable();
            $table->timestamp('archive_retry_at')->nullable();
        });

        Schema::create('release_files', function (Blueprint $table): void {
            $table->unsignedInteger('releases_id');
            $table->string('name');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('crc32')->default('');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->boolean('passworded')->default(false);
            $table->primary(['releases_id', 'name']);
        });

        Schema::create('par_hashes', function (Blueprint $table): void {
            $table->unsignedInteger('releases_id');
            $table->string('hash', 32);
            $table->primary(['releases_id', 'hash']);
        });
    }
}

class CountingNameFixingService extends NameFixingService
{
    public int $matchPreDbFilesCalls = 0;

    public function matchPreDbFiles(object $release, bool $echo, bool $nameStatus, bool $show): int
    {
        $this->matchPreDbFilesCalls++;

        return 0;
    }
}

class TestNzbService extends NzbService
{
    public function __construct()
    {
        parent::__construct(new CollectionCleanupService);
    }
}
