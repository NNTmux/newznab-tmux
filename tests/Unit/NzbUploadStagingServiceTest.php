<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\NzbUploadException;
use App\Services\Nzb\NzbUploadManifestService;
use App\Services\Nzb\NzbUploadStagingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use PDO;
use Tests\TestCase;

final class NzbUploadStagingServiceTest extends TestCase
{
    private string $uploadFolder;

    private string $databasePath;

    /** @var array<string, string|false> */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-nzb-upload-service-test.sqlite';
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

        $this->uploadFolder = sys_get_temp_dir().'/nntmux-nzb-upload-'.bin2hex(random_bytes(6));
        config(['nntmux.nzb_upload_folder' => $this->uploadFolder]);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->uploadFolder);

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();

        foreach ($this->originalEnvironment as $key => $value) {
            $this->setEnvironmentValue($key, $value === false ? null : $value);
        }
    }

    public function test_it_stages_an_nzb_and_arbitrarily_named_nfo_with_a_manifest(): void
    {
        $result = (new NzbUploadStagingService(new Filesystem))->stage(
            $this->nzb('Release.nzb'),
            UploadedFile::fake()->createWithContent('scene-info.nfo', 'release information'),
        );

        $uploadDirectory = $this->onlyUploadDirectory();
        $manifest = json_decode(
            (string) file_get_contents($uploadDirectory.'/'.NzbUploadManifestService::FILENAME),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('Release', $result['name']);
        $this->assertSame('Release.nzb', $result['files']['nzb']['filename']);
        $this->assertSame('scene-info.nfo', $result['files']['nfo']['filename']);
        $this->assertFileExists($uploadDirectory.'/Release.nzb');
        $this->assertFileExists($uploadDirectory.'/scene-info.nfo');
        $this->assertSame(NzbUploadManifestService::STATE_STAGED, $manifest['state']);
        $this->assertSame('Release.nzb', $manifest['nzb']['filename']);
        $this->assertSame('scene-info.nfo', $manifest['nfo']['filename']);
        $this->assertSame(basename($uploadDirectory), $manifest['upload_id']);
    }

    public function test_it_stages_an_nzb_without_an_nfo(): void
    {
        $result = (new NzbUploadStagingService(new Filesystem))->stage($this->nzb('Solo.nzb'));

        $uploadDirectory = $this->onlyUploadDirectory();

        $this->assertNull($result['files']['nfo']);
        $this->assertFileExists($uploadDirectory.'/Solo.nzb');
        $this->assertFileExists($uploadDirectory.'/'.NzbUploadManifestService::FILENAME);
    }

    public function test_it_isolates_repeated_generic_nfo_names(): void
    {
        $service = new NzbUploadStagingService(new Filesystem);
        $service->stage(
            $this->nzb('First.nzb'),
            UploadedFile::fake()->createWithContent('info.nfo', 'first release information'),
        );
        $service->stage(
            $this->nzb('Second.nzb'),
            UploadedFile::fake()->createWithContent('info.nfo', 'second release information'),
        );

        $directories = (new Filesystem)->directories($this->uploadFolder);
        $this->assertCount(2, $directories);
        foreach ($directories as $directory) {
            $this->assertFileExists($directory.'/info.nfo');
        }
    }

    public function test_it_rejects_an_invalid_nzb_payload(): void
    {
        $this->expectException(NzbUploadException::class);
        $this->expectExceptionMessage('Invalid NZB payload');

        (new NzbUploadStagingService(new Filesystem))->stage(
            UploadedFile::fake()->createWithContent('Release.nzb', 'not xml'),
        );
    }

    public function test_it_rejects_an_oversized_nfo(): void
    {
        $this->expectException(NzbUploadException::class);
        $this->expectExceptionMessage('NFO upload must not be empty or exceed 65535 bytes');

        (new NzbUploadStagingService(new Filesystem))->stage(
            $this->nzb('Release.nzb'),
            UploadedFile::fake()->createWithContent('Release.nfo', str_repeat('x', 65536)),
        );
    }

    public function test_it_rolls_back_the_nzb_when_the_nfo_write_fails(): void
    {
        $filesystem = new class extends Filesystem
        {
            public function put($path, $contents, $lock = false): int|bool
            {
                if (str_ends_with((string) $path, '.nfo')) {
                    return false;
                }

                return parent::put($path, $contents, $lock);
            }
        };

        $this->expectException(NzbUploadException::class);
        $this->expectExceptionMessage('Failed to write Release.nfo to disk');

        (new NzbUploadStagingService($filesystem))->stage(
            $this->nzb('Release.nzb'),
            UploadedFile::fake()->createWithContent('Release.nfo', 'release information'),
        );
    }

    public function test_rollback_removes_the_partial_upload_directory(): void
    {
        $filesystem = new class extends Filesystem
        {
            public function put($path, $contents, $lock = false): int|bool
            {
                return str_ends_with((string) $path, '.nfo')
                    ? false
                    : parent::put($path, $contents, $lock);
            }
        };

        try {
            (new NzbUploadStagingService($filesystem))->stage(
                $this->nzb('Release.nzb'),
                UploadedFile::fake()->createWithContent('Release.nfo', 'release information'),
            );
            $this->fail('Expected staged pair write to fail.');
        } catch (NzbUploadException) {
            $this->assertSame([], $filesystem->directories($this->uploadFolder));
        }
    }

    public function test_a_successful_stage_publishes_the_complete_upload_and_leaves_nothing_in_staging(): void
    {
        (new NzbUploadStagingService(new Filesystem))->stage(
            $this->nzb('Release.nzb'),
            UploadedFile::fake()->createWithContent('Release.nfo', 'release information'),
        );

        $uploadDirectory = $this->onlyUploadDirectory();

        $this->assertFileExists($uploadDirectory.'/Release.nzb');
        $this->assertFileExists($uploadDirectory.'/Release.nfo');
        $this->assertFileExists($uploadDirectory.'/'.NzbUploadManifestService::FILENAME);
        $this->assertSame([], $this->stagingEntries());
    }

    public function test_importers_never_see_an_upload_before_it_is_published(): void
    {
        $uploadFolder = $this->uploadFolder;
        $filesystem = new class($uploadFolder) extends Filesystem
        {
            /** @var list<array{files:list<string>,manifests:list<string>}> */
            public array $snapshots = [];

            public function __construct(private readonly string $uploadFolder) {}

            public function move($path, $target): bool
            {
                $this->snapshots[] = [
                    'files' => array_map(
                        static fn (\SplFileInfo $file): string => $file->getPathname(),
                        File::allFiles($this->uploadFolder),
                    ),
                    'manifests' => (new NzbUploadManifestService(new Filesystem))->findManifests($this->uploadFolder),
                ];

                return parent::move($path, $target);
            }
        };

        (new NzbUploadStagingService($filesystem))->stage(
            $this->nzb('Release.nzb'),
            UploadedFile::fake()->createWithContent('Release.nfo', 'release information'),
        );

        $this->assertCount(2, $filesystem->snapshots, 'Expected a manifest write and a publish rename.');
        foreach ($filesystem->snapshots as $snapshot) {
            $this->assertSame([], $snapshot['files']);
            $this->assertSame([], $snapshot['manifests']);
        }
        $this->assertCount(3, File::allFiles($this->uploadFolder));
    }

    public function test_scans_ignore_abandoned_staging_directories(): void
    {
        $abandoned = $this->uploadFolder.'/'.NzbUploadStagingService::STAGING_DIRECTORY.'/crashed-upload';
        (new Filesystem)->makeDirectory($abandoned, 0775, true);
        file_put_contents($abandoned.'/Release.nzb', '<?xml version="1.0"?><nzb></nzb>');
        file_put_contents($abandoned.'/'.NzbUploadManifestService::FILENAME, '{}');

        $this->assertSame([], File::allFiles($this->uploadFolder));
        $this->assertSame([], (new NzbUploadManifestService(new Filesystem))->findManifests($this->uploadFolder));
    }

    public function test_a_manifest_write_failure_leaves_no_staged_or_published_upload(): void
    {
        $filesystem = new class extends Filesystem
        {
            public function put($path, $contents, $lock = false): int|bool
            {
                return str_contains((string) $path, NzbUploadManifestService::FILENAME)
                    ? false
                    : parent::put($path, $contents, $lock);
            }
        };

        try {
            (new NzbUploadStagingService($filesystem))->stage(
                $this->nzb('Release.nzb'),
                UploadedFile::fake()->createWithContent('Release.nfo', 'release information'),
            );
            $this->fail('Expected manifest write to fail.');
        } catch (NzbUploadException $exception) {
            $this->assertSame('Failed to write upload pair to disk', $exception->getMessage());
            $this->assertSame(500, $exception->status);
        }

        $this->assertSame([], $filesystem->directories($this->uploadFolder));
        $this->assertSame([], $this->stagingEntries());
    }

    public function test_a_publish_rename_failure_rolls_back_the_staged_upload(): void
    {
        $filesystem = new class extends Filesystem
        {
            public function move($path, $target): bool
            {
                return str_contains((string) $target, NzbUploadManifestService::FILENAME)
                    && parent::move($path, $target);
            }
        };

        try {
            (new NzbUploadStagingService($filesystem))->stage($this->nzb('Release.nzb'));
            $this->fail('Expected publish rename to fail.');
        } catch (NzbUploadException $exception) {
            $this->assertSame('Failed to publish staged upload', $exception->getMessage());
            $this->assertSame(500, $exception->status);
        }

        $this->assertSame([], $filesystem->directories($this->uploadFolder));
        $this->assertSame([], $this->stagingEntries());
    }

    public function test_a_legacy_nfo_is_published_without_leaving_a_staging_file(): void
    {
        $result = (new NzbUploadStagingService(new Filesystem))->stageLegacyFile(
            UploadedFile::fake()->createWithContent('Release.nfo', 'release information'),
        );

        $this->assertSame('Release.nfo', $result['filename']);
        $this->assertSame('release information', file_get_contents($this->uploadFolder.'/Release.nfo'));
        $this->assertSame([], $this->stagingEntries());
    }

    /** @return list<string> */
    private function stagingEntries(): array
    {
        $staging = $this->uploadFolder.'/'.NzbUploadStagingService::STAGING_DIRECTORY;
        if (! is_dir($staging)) {
            return [];
        }

        return array_values(array_diff((array) scandir($staging), ['.', '..']));
    }

    private function nzb(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            '<?xml version="1.0"?><nzb xmlns="http://www.newzbin.com/DTD/2003/nzb"></nzb>',
        );
    }

    private function onlyUploadDirectory(): string
    {
        $directories = (new Filesystem)->directories($this->uploadFolder);
        $this->assertCount(1, $directories);

        return $directories[0];
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
