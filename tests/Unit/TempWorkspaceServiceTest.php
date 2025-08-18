<?php

namespace Tests\Unit;

use App\Services\TempWorkspaceService;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;

class TempWorkspaceServiceTest extends TestCase
{
    private string $base;

    private TempWorkspaceService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        // Minimal Facade container for File facade
        $container = new Container;
        $container->instance('files', new Filesystem);
        Facade::setFacadeApplication($container);

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
