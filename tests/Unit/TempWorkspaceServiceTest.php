<?php

namespace Tests\Unit;

use App\Services\TempWorkspaceService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TempWorkspaceServiceTest extends TestCase
{
    private string $base;

    private TempWorkspaceService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        // Minimal Facade container for File facade
        $app = new Application(dirname(__DIR__, 2));
        $app->instance('files', new Filesystem);
        Facade::setFacadeApplication($app);

        $this->svc = new TempWorkspaceService;
        // Unique base path under system temp
        $this->base = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'twsvc_'.uniqid();
        File::makeDirectory($this->base, 0777, true, true);
    }

    protected function tearDown(): void
    {
        // Cleanup
        if (File::exists($this->base)) {
            File::deleteDirectory($this->base);
        }
        parent::tearDown();
    }

    #[WithoutErrorHandler]
    public function test_ensure_main_temp_path_creates_and_namespaces_by_guid_char(): void
    {
        $resolved = $this->svc->ensureMainTempPath($this->base, 'z', '');
        $this->assertStringEndsWith('/z/', str_replace('\\', '/', $resolved));
        $this->assertTrue(File::isDirectory($resolved));
    }

    #[WithoutErrorHandler]
    public function test_create_release_temp_folder_creates_per_release_dir(): void
    {
        $main = $this->svc->ensureMainTempPath($this->base, '', 'group42');
        $tmp = $this->svc->createReleaseTempFolder($main, 'guid-123');
        $this->assertTrue(File::isDirectory($tmp));
        $this->assertStringEndsWith('/group42/guid-123/', str_replace('\\', '/', $tmp));
    }

    #[WithoutErrorHandler]
    public function test_worker_workspaces_are_isolated_within_a_guid_bucket(): void
    {
        $first = $this->svc->ensureMainTempPath($this->base, 'a', '', 'worker-one');
        $second = $this->svc->ensureMainTempPath($this->base, 'a', '', 'worker-two');
        File::put($first.'first.bin', 'first');
        File::put($second.'second.bin', 'second');

        $this->svc->clearDirectory($first, false);

        $this->assertFalse(File::exists($first));
        $this->assertTrue(File::isFile($second.'second.bin'));
        $this->assertNotSame($first, $second);
    }

    #[WithoutErrorHandler]
    public function test_prune_stale_worker_directories_keeps_active_siblings(): void
    {
        $bucket = $this->svc->ensureMainTempPath($this->base, 'a');
        $stale = $this->svc->ensureMainTempPath($this->base, 'a', '', 'stale-worker');
        $active = $this->svc->ensureMainTempPath($this->base, 'a', '', 'active-worker');
        touch(rtrim($stale, '/\\'), time() - 7200);

        $removed = $this->svc->pruneStaleWorkerDirectories($bucket, 3600);

        $this->assertSame(1, $removed);
        $this->assertFalse(File::exists($stale));
        $this->assertTrue(File::isDirectory($active));
    }

    #[WithoutErrorHandler]
    public function test_ensure_main_temp_path_repairs_existing_unwritable_bucket_directory(): void
    {
        $bucket = $this->base.DIRECTORY_SEPARATOR.'a';
        File::makeDirectory($bucket, 0555, true, true);
        chmod($bucket, 0555);

        $resolved = $this->svc->ensureMainTempPath($this->base, 'a', '');

        $this->assertTrue(File::isDirectory($resolved));
        $this->assertTrue(is_writable($resolved));
    }

    #[WithoutErrorHandler]
    public function test_ensure_main_temp_path_reports_path_when_base_cannot_be_created(): void
    {
        $fileBase = $this->base.DIRECTORY_SEPARATOR.'not-a-directory';
        File::put($fileBase, 'x');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Additional post-processing temp path');
        $this->expectExceptionMessage('not-a-directory');

        $this->svc->ensureMainTempPath($fileBase, 'a', '');
    }

    #[WithoutErrorHandler]
    public function test_list_files_with_and_without_pattern(): void
    {
        $main = $this->svc->ensureMainTempPath($this->base, '', 'grp');
        $release = $this->svc->createReleaseTempFolder($main, 'g1');
        // Create files
        File::put($release.'a.txt', 'x');
        File::put($release.'b.jpg', 'y');
        File::makeDirectory($release.'deep', 0777, true, true);
        File::put($release.'deep'.DIRECTORY_SEPARATOR.'c.txt', 'z');

        $all = $this->svc->listFiles($release);
        $this->assertNotEmpty($all);
        $this->assertTrue(collect($all)->every(fn ($f) => method_exists($f, 'getPathname')));

        $matches = $this->svc->listFiles($release, '/.*\.txt$/i');
        $this->assertNotEmpty($matches);
        foreach ($matches as $m) {
            $this->assertIsArray($m);
            $this->assertArrayHasKey(0, $m);
            $this->assertTrue(File::isFile($m[0]));
            $this->assertStringEndsWith('.txt', strtolower($m[0]));
        }
    }

    #[WithoutErrorHandler]
    public function test_clear_directory_preserve_root(): void
    {
        $main = $this->svc->ensureMainTempPath($this->base, '', 'grp2');
        File::put($main.'x.bin', 'data');
        File::makeDirectory($main.'sub', 0777, true, true);
        File::put($main.'sub'.DIRECTORY_SEPARATOR.'y.bin', 'data');

        $this->svc->clearDirectory($main, true);

        $this->assertTrue(File::isDirectory($main));
        $this->assertEmpty(File::files($main));
        $this->assertEmpty(File::directories($main));
    }

    #[WithoutErrorHandler]
    public function test_clear_directory_delete_root(): void
    {
        $main = $this->svc->ensureMainTempPath($this->base, '', 'grp3');
        File::put($main.'x.bin', 'data');

        $this->svc->clearDirectory($main, false);
        $this->assertFalse(File::exists($main));
    }
}
