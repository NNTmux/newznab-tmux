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
use App\Services\NameFixing\ReleaseUpdateService;
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

    public function test_season_pack_files_are_not_matched_to_predb_one_by_one(): void
    {
        DB::table('releases')->insert(['searchname' => 'Show.S01.1080p.WEB-DL-GRP'] + $this->releaseRow());
        Search::shouldReceive('updateRelease')->with(1);
        $nameFixing = new CountingNameFixingService;
        $manager = $this->makeManager($nameFixing);
        $context = new ReleaseProcessingContext(Release::query()->findOrFail(1));
        $context->seasonPack = true;

        $manager->addFileInfo(['name' => 'Show.S01E01.1080p.WEB-DL-GRP.mkv', 'size' => 2048], $context, '\\.sfv');
        $manager->processReleaseNameFromRar(['file_list' => [['name' => 'Show.S01E10.1080p.WEB-DL-GRP.mkv']]], $context);
        $manager->finalizeRelease($context, false);

        $this->assertSame(0, $nameFixing->matchPreDbFilesCalls);
        $this->assertSame('Show.S01.1080p.WEB-DL-GRP', DB::table('releases')->where('id', 1)->value('searchname'));
    }

    public function test_season_pack_lists_every_episode_while_other_releases_keep_eleven_files(): void
    {
        DB::table('releases')->insert($this->releaseRow());
        DB::table('releases')->insert(['id' => 2, 'guid' => 'guid-2'] + $this->releaseRow());
        Search::shouldReceive('updateRelease')->twice();
        $manager = $this->makeManager();

        $pack = new ReleaseProcessingContext(Release::query()->findOrFail(1));
        $pack->seasonPack = true;
        $other = new ReleaseProcessingContext(Release::query()->findOrFail(2));
        for ($episode = 1; $episode <= 25; $episode++) {
            $name = sprintf('Show.S01E%02d.1080p.WEB-DL-GRP.mkv', $episode);
            $manager->addFileInfo(['name' => $name, 'size' => 2048], $pack, '\\.sfv');
            $manager->addFileInfo(['name' => $name, 'size' => 2048], $other, '\\.sfv');
        }
        $manager->finalizeRelease($pack, false);
        $manager->finalizeRelease($other, false);

        $this->assertSame(25, DB::table('release_files')->where('releases_id', 1)->count());
        $this->assertSame(11, DB::table('release_files')->where('releases_id', 2)->count());
    }

    public function test_a_season_pack_is_never_renamed_to_one_of_its_episodes(): void
    {
        DB::table('releases')->insert(['searchname' => 'Show.S01.1080p.WEB-DL-GRP'] + $this->releaseRow());
        $service = new ReleaseUpdateService;

        $service->updateRelease(DB::table('releases')->where('id', 1)->first(), 'Show.S01E01.1080p.WEB-DL-GRP', 'test', true, 'Filenames, ', true, false);

        $this->assertFalse($service->matched);
        $this->assertSame(0, $service->fixed);
        $this->assertSame('Show.S01.1080p.WEB-DL-GRP', DB::table('releases')->where('id', 1)->value('searchname'));
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

    #[DataProvider('compressedCandidatePayloads')]
    public function test_plain_media_behind_an_archive_name_finalizes_with_no_password(?string $data, string $expected): void
    {
        $data ??= $this->rar('Movie.2026.1080p.mkv', str_repeat('B', 100));
        DB::table('releases')->insert([...$this->releaseRow(), 'nfostatus' => 1]);
        Search::shouldReceive('updateRelease')->once()->with(1);
        config(['nntmux.archive_retry_delay' => 86400]);
        $this->freezeSecond();

        $config = $this->makeConfig(['processPasswords' => true]);
        $parser = Mockery::mock(NzbContentParser::class);
        $parser->shouldReceive('parseNzb')->once()->with('guid-1')->andReturn([
            'error' => null,
            'contents' => [['title' => '"Movie.2026.part01.rar" yEnc (1/50)', 'segments' => ['<v1>']]],
        ]);
        $download = Mockery::mock(UsenetDownloadService::class);
        $download->shouldReceive('beginReleaseScope')->once();
        $download->shouldReceive('finishReleaseScope')->once()->andReturn(new DownloadMetrics);
        $download->shouldReceive('download')->once()
            ->with(DownloadKind::Compressed, ['<v1>'], '', 1, '"Movie.2026.part01.rar" yEnc (1/50)')
            ->andReturn(['success' => true, 'data' => $data, 'groupUnavailable' => false, 'error' => null]);
        $tmpPath = sys_get_temp_dir().'/media-payload-'.uniqid().'/';
        File::ensureDirectoryExists($tmpPath);
        $workspace = Mockery::mock(TempWorkspaceService::class);
        $workspace->shouldReceive('createReleaseTempFolder')->once()->andReturn($tmpPath);
        $workspace->shouldReceive('listFiles')->andReturn([]);
        $workspace->shouldReceive('clearDirectory')->once()->with($tmpPath, false)
            ->andReturnUsing(static fn () => File::deleteDirectory($tmpPath));
        $processor = new ReleaseProcessor(
            $config,
            $parser,
            new AdditionalWorkPlanner($config),
            new ArchiveExtractionService($config),
            Mockery::mock(MediaExtractionService::class),
            $download,
            $this->makeManager(),
            Mockery::mock(ReleaseFilesArchiveFallback::class)->shouldIgnoreMissing(),
            $workspace,
            Mockery::mock(ConsoleOutputService::class)->shouldIgnoreMissing(),
        );
        $context = new ReleaseProcessingContext(Release::query()->findOrFail(1));

        $processor->process($context, '/tmp/');

        $release = DB::table('releases')->where('id', 1)->first();
        $this->assertSame($expected === 'archive' ? 1 : 0, DB::table('release_files')->count());
        if ($expected === 'unknown') {
            $this->assertSame(-1, (int) $release->passwordstatus);
            $this->assertSame(-1, (int) $release->haspreview);
            $this->assertSame(now()->addDay()->toDateTimeString(), $release->archive_retry_at);
        } else {
            $this->assertSame(ReleaseBrowseService::PASSWD_NONE, (int) $release->passwordstatus);
            $this->assertSame(0, (int) $release->haspreview);
            $this->assertNull($release->archive_retry_at);
        }
    }

    /**
     * @return array<string, array{string|null, string}>
     */
    public static function compressedCandidatePayloads(): array
    {
        $pad = static fn (string $head): string => str_pad($head, 512, "\x5A");
        $ts = str_repeat("\x47".str_repeat("\x10", 187), 3);

        return [
            'Matroska' => [$pad("\x1A\x45\xDF\xA3\x01\x00\x00\x00"), 'media'],
            'MP4' => [$pad("\x00\x00\x00\x20ftypmp42"), 'media'],
            'MOV' => [$pad("\x00\x00\x00\x14ftypqt  "), 'media'],
            'AVI' => [$pad('RIFF'."\x00\x10\x00\x00".'AVI LIST'), 'media'],
            'MPEG-TS' => [$ts, 'media'],
            'MPEG-PS' => [$pad("\x00\x00\x01\xBA\x44"), 'media'],
            'FLV' => [$pad("FLV\x01\x05\x00\x00\x00\x09"), 'media'],
            'Ogg' => [$pad("OggS\x00\x02"), 'media'],
            'RAR' => [null, 'archive'],
            'unknown data' => [$pad("\x8E\x13\x7F\x02random"), 'unknown'],
        ];
    }

    /**
     * @param  list<array{string, string, string|false|null}>  $files  title, message-id and its data (false: download fails,
     *                                                                 null: not downloaded, 'encrypted rar'/'encrypted zip'/'encrypted 7z': such an archive)
     */
    #[DataProvider('mediaBesideOtherCandidates')]
    public function test_plain_media_only_finalizes_when_no_other_archive_stayed_unread(array $files, int $status): void
    {
        $files = array_map(fn (array $file): array => [$file[0], $file[1], match ($file[2]) {
            'encrypted rar' => $this->rar('Movie.2026.1080p.mkv', str_repeat('x', 100), encryptedFile: true),
            'encrypted zip' => $this->zip('Movie.2026.1080p.mkv', str_repeat('x', 100), encryptedFile: true),
            'encrypted 7z' => $this->sevenZip('Movie.2026.1080p.mkv', str_repeat('x', 100), encryptedHeader: true),
            default => $file[2],
        }], $files);
        DB::table('releases')->insert([...$this->releaseRow(), 'nfostatus' => 1]);
        Search::shouldReceive('updateRelease')->once()->with(1);
        config(['nntmux.archive_retry_delay' => 86400]);
        $this->freezeSecond();

        $config = $this->makeConfig(['processPasswords' => true, 'maximumRarPasswordChecks' => 2]);
        $parser = Mockery::mock(NzbContentParser::class);
        $parser->shouldReceive('parseNzb')->once()->with('guid-1')->andReturn([
            'error' => null,
            'contents' => array_map(static fn (array $file): array => ['title' => $file[0], 'segments' => [$file[1]]], $files),
        ]);
        $download = Mockery::mock(UsenetDownloadService::class);
        $download->shouldReceive('beginReleaseScope')->once();
        $download->shouldReceive('finishReleaseScope')->once()->andReturn(new DownloadMetrics);
        foreach ($files as [$title, $messageId, $data]) {
            if ($data === null) {
                continue;
            }
            $download->shouldReceive('download')->once()
                ->with(DownloadKind::Compressed, [$messageId], '', 1, $title)
                ->andReturn(['success' => $data !== false, 'data' => $data === false ? null : $data, 'groupUnavailable' => false, 'error' => null]);
        }
        $tmpPath = sys_get_temp_dir().'/media-payload-'.uniqid().'/';
        File::ensureDirectoryExists($tmpPath);
        $workspace = Mockery::mock(TempWorkspaceService::class);
        $workspace->shouldReceive('createReleaseTempFolder')->once()->andReturn($tmpPath);
        $workspace->shouldReceive('listFiles')->andReturn([]);
        $workspace->shouldReceive('clearDirectory')->once()->with($tmpPath, false)
            ->andReturnUsing(static fn () => File::deleteDirectory($tmpPath));
        $processor = new ReleaseProcessor(
            $config,
            $parser,
            new AdditionalWorkPlanner($config),
            new ArchiveExtractionService($config),
            Mockery::mock(MediaExtractionService::class)->shouldIgnoreMissing(false),
            $download,
            $this->makeManager(),
            Mockery::mock(ReleaseFilesArchiveFallback::class)->shouldIgnoreMissing(),
            $workspace,
            Mockery::mock(ConsoleOutputService::class)->shouldIgnoreMissing(),
        );

        $processor->process(new ReleaseProcessingContext(Release::query()->findOrFail(1)), '/tmp/');

        $release = DB::table('releases')->where('id', 1)->first();
        $this->assertSame($status, (int) $release->passwordstatus);
        $this->assertSame($status === -1 ? now()->addDay()->toDateTimeString() : null, $release->archive_retry_at);
    }

    /**
     * @return array<string, array{list<array{string, string, string|false|null}>, int}>
     */
    public static function mediaBesideOtherCandidates(): array
    {
        $mkv = str_pad("\x1A\x45\xDF\xA3\x01\x00\x00\x00", 512, "\x5A");
        $media = ['"Movie.2026.part01.rar" yEnc (1/50)', '<v1>', $mkv];
        $spaced = ['First Movie.rar yEnc (1/50)', '<v1>', $mkv];

        $unreadable = str_pad("\x8E\x13\x7F\x02", 512, "\x5A");
        $none = ReleaseBrowseService::PASSWD_NONE;
        $passworded = ReleaseBrowseService::PASSWD_RAR;

        return [
            'an unreadable archive beside it' => [[$media, ['"Extras.2026.rar" yEnc (1/3)', '<x1>', $unreadable]], -1],
            'a missing archive beside it' => [[$media, ['"Extras.2026.rar" yEnc (1/3)', '<x1>', false]], -1],
            'later volumes of the same file' => [[$media, ['"Movie.2026.part02.rar" yEnc (1/50)', '<v2>', null]], $none],
            'unquoted later volumes of the same file' => [[
                ['Movie.2026.part01.rar yEnc (1/50)', '<v1>', $mkv],
                ['Movie.2026.part02.rar yEnc (1/50)', '<v2>', null],
                ['[03/50] - Movie.2026.part03.rar yEnc', '<v3>', null],
            ], $none],
            'a split video piece and its later pieces' => [[['"Show.S01E01.mkv.001" yEnc (1/80)', '<s1>', $mkv], ['"Show.S01E01.mkv.002" yEnc (1/80)', '<s2>', null]], $none],
            'an unquoted split video piece and its later pieces' => [[['Show.S01E01.mkv.001 yEnc (1/80)', '<s1>', $mkv], ['Show.S01E01.mkv.002 yEnc (1/80)', '<s2>', null]], $none],
            'an encrypted same-stem ZIP beside it' => [[$media, ['"Movie.2026.zip" yEnc (1/3)', '<z1>', 'encrypted zip']], $passworded],
            'an encrypted same-stem 7z beside it' => [[$media, ['"Movie.2026.7z" yEnc (1/3)', '<z1>', 'encrypted 7z']], $passworded],
            'an unreadable same-stem ZIP beside it' => [[$media, ['"Movie.2026.zip" yEnc (1/3)', '<z1>', $unreadable]], -1],
            'an unreadable same-stem 7z beside it' => [[$media, ['"Movie.2026.7z.001" yEnc (1/3)', '<z1>', $unreadable]], -1],
            'an encrypted same-stem ZIP beside a split video' => [[
                ['"Movie.2026.mkv.001" yEnc (1/80)', '<s1>', $mkv],
                ['"Movie.2026.mkv.zip" yEnc (1/3)', '<z1>', 'encrypted zip'],
            ], $passworded],
            'an encrypted archive with another spaced name beside it' => [[$spaced, ['Second Movie.rar yEnc (1/50)', '<x1>', 'encrypted rar']], $passworded],
            'an unreadable archive with another spaced name beside it' => [[$spaced, ['Second Movie.rar yEnc (1/50)', '<x1>', $unreadable]], -1],
            'a missing archive with another spaced name beside it' => [[$spaced, ['Second Movie.rar yEnc (1/50)', '<x1>', false]], -1],
            'spaced later volumes of the same file' => [[
                ['First Movie.part01.rar yEnc (1/50)', '<v1>', $mkv],
                ['First Movie.part02.rar yEnc (1/50)', '<v2>', null],
                ['[03/50] - First Movie.part03.rar yEnc', '<v3>', null],
                ['"First Movie.part04.rar" yEnc (1/50)', '<v4>', null],
            ], $none],
        ];
    }

    #[DataProvider('sevenZipListings')]
    public function test_a_cut_off_7z_listing_leaves_the_password_status_unknown(string $script, int $passwordStatus, array $names = []): void
    {
        DB::table('releases')->insert($this->releaseRow());
        Search::shouldReceive('updateRelease')->once()->with(1);

        $dir = sys_get_temp_dir().'/'.uniqid('7z-listing-', true);
        mkdir($dir);
        try {
            file_put_contents($dir.'/7z', "#!/bin/sh\n".$script."\n");
            chmod($dir.'/7z', 0755);
            $archiveService = new ArchiveExtractionService($this->makeConfig(['sevenZipPath' => $dir.'/7z']));
            $manager = $this->makeManager();
            $context = new ReleaseProcessingContext(Release::query()->findOrFail(1));
            $context->nzbHasCompressedFile = true;

            $archive = $this->sevenZip('ignored.mkv', str_repeat('x', 100), compressedHeader: true);
            $result = $archiveService->listEncodedSevenZip($archive, null, $context, $dir.'/');
            $this->assertIsArray($result);
            foreach ($result['files'] as $file) {
                if ($context->releaseHasPassword) {
                    break;
                }
                $manager->addFileInfo($file, $context, '\\.(?:par2|sfv|nzb)');
            }
            $manager->finalizeRelease($context, true);
        } finally {
            array_map('unlink', glob($dir.'/*') ?: []);
            rmdir($dir);
        }

        $release = DB::table('releases')->where('id', 1)->first();
        $this->assertSame($passwordStatus, (int) $release->passwordstatus);
        $this->assertSame(0, (int) $release->haspreview);
        $this->assertNull($release->archive_retry_at);
        $this->assertGreaterThan(0, DB::table('release_files')->count());
        foreach ($names as $name) {
            $this->assertTrue(DB::table('release_files')->where('name', $name)->exists(), $name);
        }
    }

    /**
     * @return array<string, array{0: string, 1: int, 2?: list<string>}>
     */
    public static function sevenZipListings(): array
    {
        $plain = static fn (int $count, string $pad = ''): string => 'i=0; while [ $i -lt '.$count.' ]; do '
            .'printf \'Path = %d'.$pad.'.txt\\nSize = 0\\nEncrypted = -\\n\\n\' $i; i=$((i+1)); done; ';
        $encrypted = 'printf \'Path = Movie.2026.1080p.mkv\\nSize = 100\\nEncrypted = +\\n\\n\'; ';

        return [
            'encrypted entry past the entry cap' => [
                $plain(ArchiveExtractionService::SEVENZIP_LISTING_MAX_ENTRIES).$encrypted,
                -1,
            ],
            'encrypted entry past the output cap' => [
                $plain(300, str_repeat('a', 20000)).$encrypted,
                -1,
            ],
            'encrypted entry before the cap' => [
                $plain(5).$encrypted.$plain(2000),
                ReleaseBrowseService::PASSWD_RAR,
            ],
            'complete listing without encryption' => [
                $plain(5),
                ReleaseBrowseService::PASSWD_NONE,
            ],
            'CRLF listing of names containing byte 0x85' => [
                'printf \'Path = \303\205land.mkv\r\nSize = 10\r\nEncrypted = -\r\n\r\n\'; '
                    .'printf \'Path = Movie.\344\271\205.mkv\r\nSize = 13\r\nEncrypted = -\r\n\r\n\'; ',
                ReleaseBrowseService::PASSWD_NONE,
                ['Åland.mkv', 'Movie.久.mkv'],
            ],
            'encrypted entry behind a control character' => [
                $plain(5).'printf \'Path = Movie\001.mkv\nSize = 100\nEncrypted = +\n\n\'; ',
                -1,
            ],
            'encrypted entry behind a duplicate property' => [
                $plain(5).'printf \'Path = a.txt\nPath = Movie.mkv\nSize = 100\nEncrypted = +\n\n\'; ',
                -1,
            ],
        ];
    }

    /**
     * @param  list<string>  $listings  7-Zip client output for each 7z archive of the release, listed in order
     */
    #[DataProvider('sevenZipListingsWithoutReadableRecords')]
    public function test_a_7z_listing_without_one_readable_record_still_leaves_the_password_status_unknown(
        array $listings,
        bool $hasReleaseFile,
        int $passwordStatus,
        bool $retried,
    ): void {
        DB::table('releases')->insert($this->releaseRow());
        if ($hasReleaseFile) {
            DB::table('release_files')->insert(['releases_id' => 1, 'name' => 'Extras/readme.txt', 'size' => 10]);
        }
        Search::shouldReceive('updateRelease')->once()->with(1);
        config(['nntmux.archive_retry_delay' => 86400]);
        $this->freezeSecond();

        $dir = sys_get_temp_dir().'/'.uniqid('7z-listing-', true);
        mkdir($dir);
        try {
            $manager = $this->makeManager();
            $context = new ReleaseProcessingContext(Release::query()->findOrFail(1));
            $context->nzbHasCompressedFile = true;
            $archive = $this->sevenZip('ignored.mkv', str_repeat('x', 100), compressedHeader: true);

            foreach ($listings as $index => $listing) {
                $client = $dir.'/7z-'.$index;
                file_put_contents($client, "#!/bin/sh\n".$listing."\n");
                chmod($client, 0755);
                $result = (new ArchiveExtractionService($this->makeConfig(['sevenZipPath' => $client])))
                    ->listEncodedSevenZip($archive, null, $context, $dir.'/');
                foreach ($result['files'] ?? [] as $file) {
                    if ($context->releaseHasPassword) {
                        break;
                    }
                    $manager->addFileInfo($file, $context, '\\.(?:par2|sfv|nzb)');
                }
            }
            $manager->finalizeRelease($context, true);
        } finally {
            array_map('unlink', glob($dir.'/*') ?: []);
            rmdir($dir);
        }

        $release = DB::table('releases')->where('id', 1)->first();
        $this->assertSame($passwordStatus, (int) $release->passwordstatus);
        $this->assertSame($retried ? -1 : 0, (int) $release->haspreview);
        $this->assertSame($retried ? now()->addDay()->toDateTimeString() : null, $release->archive_retry_at);
    }

    /**
     * @return array<string, array{list<string>, bool, int, bool}>
     */
    public static function sevenZipListingsWithoutReadableRecords(): array
    {
        $controlCharacter = 'printf \'Path = Movie\001.mkv\nSize = 100\nEncrypted = +\n\n\'';
        $duplicateProperty = 'printf \'Path = a.txt\nPath = Movie.mkv\nSize = 100\nEncrypted = +\n\n\'';
        $plain = 'printf \'Path = Extras/info.txt\nSize = 4\nEncrypted = -\n\n\'';
        $encrypted = 'printf \'Path = Movie.2026.1080p.mkv\nSize = 100\nEncrypted = +\n\n\'';

        return [
            'control character beside a stored file' => [[$controlCharacter], true, -1, false],
            'duplicate property beside a stored file' => [[$duplicateProperty], true, -1, false],
            'control character, then a complete listing' => [[$controlCharacter, $plain], false, -1, false],
            'duplicate property, then a complete listing' => [[$duplicateProperty, $plain], false, -1, false],
            'complete listing, then a control character' => [[$plain, $controlCharacter], false, -1, false],
            'control character, then an encrypted entry' => [[$controlCharacter, $encrypted], false, ReleaseBrowseService::PASSWD_RAR, false],
            'control character and no file at all' => [[$controlCharacter], false, -1, true],
            'empty listing beside a stored file' => [[''], true, ReleaseBrowseService::PASSWD_NONE, false],
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
