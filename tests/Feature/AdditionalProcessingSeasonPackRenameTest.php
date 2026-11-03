<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Release;
use App\Services\AdditionalProcessing\ReleaseRenamingService;
use App\Services\AdditionalProcessing\State\ReleaseProcessingContext;
use App\Services\NameFixing\NameFixingService;
use App\Services\NameFixing\ReleaseUpdateService;
use App\Services\NameFixing\SeasonPackDetector;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;
use Tests\Unit\AdditionalProcessing\CreatesProcessingConfiguration;

final class AdditionalProcessingSeasonPackRenameTest extends TestCase
{
    use CreatesProcessingConfiguration;

    private const array EPISODES = [
        ['name' => 'Show.S01E01.Pilot.1080p.WEB-DL-GRP.mkv'],
        ['name' => 'Show.S01E02.The.Title.1080p.WEB-DL-GRP.mkv'],
        ['name' => 'Show.S01E03.Finale.1080p.WEB-DL-GRP.mkv'],
    ];

    private string $databasePath;

    /** @var array<string, string|false> */
    private array $originalEnvironment = [];

    private ReleaseUpdateService&MockObject $updateService;

    private ReleaseRenamingService $service;

    public function createApplication()
    {
        $this->databasePath = (string) tempnam(sys_get_temp_dir(), 'nntmux-season-pack-test-');
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
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $this->databasePath]);
        DB::purge();
        DB::reconnect();
        DB::statement('CREATE TABLE predb (id INTEGER PRIMARY KEY, title TEXT)');
        DB::statement('CREATE TABLE release_informs (id INTEGER PRIMARY KEY, relOName TEXT, relPName TEXT)');

        $nameFixer = $this->createStub(NameFixingService::class);
        $nameFixer->method('isSeasonPack')->willReturnCallback(static fn (string $name): bool => SeasonPackDetector::isSeasonPack($name));
        $this->updateService = $this->createMock(ReleaseUpdateService::class);
        $this->service = new ReleaseRenamingService($this->makeConfig(), $nameFixer, $this->updateService);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_file($this->databasePath)) {
            unlink($this->databasePath);
        }
        foreach ($this->originalEnvironment as $key => $value) {
            $this->environment($key, $value === false ? null : $value);
        }
    }

    #[Test]
    public function a_plausible_season_name_is_kept_without_a_predb_title(): void
    {
        $this->updateService->expects($this->never())->method('updateRelease');

        $this->assertFalse($this->service->fileNameChecks(self::EPISODES, $this->context('Show S01 1080p WEB-DL-GRP')));
    }

    #[Test]
    public function a_plausible_season_name_takes_the_predb_title(): void
    {
        DB::table('predb')->insert(['id' => 9, 'title' => 'Show.S01.1080p.WEB-DL-GRP']);
        $context = $this->context('Show S01 1080p WEB-DL-GRP');
        $this->updateService->expects($this->once())->method('updateRelease')
            ->with($context->release, 'Show.S01.1080p.WEB-DL-GRP', 'RarInfo Season Pack Match', true, 'Filenames, ', true, true, 9);

        $this->assertTrue($this->service->fileNameChecks(self::EPISODES, $context));
    }

    #[Test]
    public function an_obfuscated_release_holding_a_season_takes_the_season_name(): void
    {
        $context = $this->context('a8f3c91e0b7d4e2f9a1c3b5d7e9f0a2c');
        $this->updateService->expects($this->once())->method('updateRelease')
            ->with($context->release, 'Show.S01.1080p.WEB-DL-GRP', 'RarInfo Season Pack Match');

        $this->assertTrue($this->service->fileNameChecks(self::EPISODES, $context));
    }

    #[Test]
    public function one_listed_episode_never_renames_the_season_pack(): void
    {
        $context = $this->context('Show.S01.1080p.WEB-DL-GRP');
        $this->updateService->expects($this->never())->method('updateRelease');

        $this->assertFalse($this->service->fileNameChecks([self::EPISODES[0]], $context));
    }

    #[Test]
    public function an_implausible_season_name_is_named_from_one_episode_without_its_title(): void
    {
        $context = $this->context('Show S01');
        $context->seasonPack = true;
        $this->updateService->expects($this->once())->method('updateRelease')
            ->with($context->release, 'Show.S01.1080p.WEB-DL-GRP');

        $this->assertTrue($this->service->fileNameChecks([self::EPISODES[1]], $context));
    }

    #[Test]
    public function raw_episode_files_name_an_obfuscated_release_after_the_season(): void
    {
        $context = $this->context('a8f3c91e0b7d4e2f9a1c3b5d7e9f0a2c');
        $context->rawMediaFileEntries = array_map(
            static fn (array $file): array => ['title' => $file['name'], 'segments' => ['<id>'], 'size' => 1_000_000_000, 'extension' => 'mkv'],
            self::EPISODES,
        );
        $this->updateService->expects($this->once())->method('updateRelease')
            ->with($context->release, 'Show.S01.1080p.WEB-DL-GRP', 'Raw file: season pack');

        $this->assertTrue($this->service->rawFileNameChecks($context, static fn (string $subject): string => $subject));
    }

    #[Test]
    public function raw_episode_files_keep_a_plausible_season_name(): void
    {
        $context = $this->context('Show.S01.1080p.WEB-DL-GRP');
        $context->rawMediaFileEntries = [['title' => self::EPISODES[0]['name'], 'segments' => ['<id>'], 'size' => 1_000_000_000, 'extension' => 'mkv']];
        $this->updateService->expects($this->never())->method('updateRelease');

        $this->assertFalse($this->service->rawFileNameChecks($context, static fn (string $subject): string => $subject));
    }

    private function context(string $searchname): ReleaseProcessingContext
    {
        $release = (new Release)->forceFill([
            'id' => 1,
            'name' => $searchname,
            'searchname' => $searchname,
            'fromname' => 'poster@example.com',
            'source' => 0,
            'isrenamed' => 0,
        ]);

        return new ReleaseProcessingContext($release);
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
