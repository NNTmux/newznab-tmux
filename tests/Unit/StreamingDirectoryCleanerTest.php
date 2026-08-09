<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\StreamingDirectoryCleaner;
use PHPUnit\Framework\TestCase;

class StreamingDirectoryCleanerTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'sdc_'.uniqid();
        mkdir($this->base, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->base);
        parent::tearDown();
    }

    public function test_deletes_nested_files_but_keeps_directories(): void
    {
        $this->makeFile('a.nzb');
        $this->makeFile('sub1/b.nzb');
        $this->makeFile('sub1/sub2/c.nzb');
        $this->makeFile('sub3/d.nzb');

        $deleted = (new StreamingDirectoryCleaner)->deleteFiles($this->base);

        $this->assertSame(4, $deleted);
        $this->assertFileDoesNotExist($this->base.'/a.nzb');
        $this->assertFileDoesNotExist($this->base.'/sub1/sub2/c.nzb');
        $this->assertDirectoryExists($this->base.'/sub1/sub2');
        $this->assertDirectoryExists($this->base.'/sub3');
    }

    public function test_preserved_basenames_survive_at_root_and_in_subdirectories(): void
    {
        $this->makeFile('.gitignore');
        $this->makeFile('no-cover.jpg');
        $this->makeFile('movies/no-cover.jpg');
        $this->makeFile('movies/deleteme.jpg');
        $this->makeFile('tv/banner/.gitignore');
        $this->makeFile('tv/banner/poster.jpg');

        $deleted = (new StreamingDirectoryCleaner)->deleteFiles($this->base, ['.gitignore', 'no-cover.jpg']);

        $this->assertSame(2, $deleted);
        $this->assertFileExists($this->base.'/.gitignore');
        $this->assertFileExists($this->base.'/no-cover.jpg');
        $this->assertFileExists($this->base.'/movies/no-cover.jpg');
        $this->assertFileExists($this->base.'/tv/banner/.gitignore');
        $this->assertFileDoesNotExist($this->base.'/movies/deleteme.jpg');
        $this->assertFileDoesNotExist($this->base.'/tv/banner/poster.jpg');
    }

    public function test_empty_missing_or_non_directory_path_returns_zero_without_throwing(): void
    {
        $cleaner = new StreamingDirectoryCleaner;

        $this->assertSame(0, $cleaner->deleteFiles(''));
        $this->assertSame(0, $cleaner->deleteFiles($this->base.'/does-not-exist'));

        $this->makeFile('plain-file.txt');
        $this->assertSame(0, $cleaner->deleteFiles($this->base.'/plain-file.txt'));
        $this->assertFileExists($this->base.'/plain-file.txt');
    }

    public function test_empty_directory_returns_zero(): void
    {
        $this->assertSame(0, (new StreamingDirectoryCleaner)->deleteFiles($this->base));
    }

    public function test_progress_callback_fires_at_configured_cadence(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->makeFile("dir{$i}/file{$i}.nzb");
        }

        $reported = [];
        $deleted = (new StreamingDirectoryCleaner(progressInterval: 3))->deleteFiles(
            $this->base,
            [],
            function (int $count) use (&$reported): void {
                $reported[] = $count;
            }
        );

        $this->assertSame(7, $deleted);
        $this->assertSame([3, 6], $reported);
    }

    public function test_failed_deletions_are_skipped_and_counted(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('Read-only directory permissions do not block root.');
        }

        $this->makeFile('locked/keepme.txt');
        $this->makeFile('normal/gone.txt');
        chmod($this->base.'/locked', 0555);

        $cleaner = new StreamingDirectoryCleaner;
        set_error_handler(fn (): bool => true, E_WARNING);
        try {
            $deleted = $cleaner->deleteFiles($this->base);
        } finally {
            restore_error_handler();
            chmod($this->base.'/locked', 0777);
        }

        $this->assertSame(1, $deleted);
        $this->assertSame(1, $cleaner->failedDeletions());
        $this->assertFileExists($this->base.'/locked/keepme.txt');
        $this->assertFileDoesNotExist($this->base.'/normal/gone.txt');
    }

    public function test_failed_deletions_resets_between_runs(): void
    {
        $this->makeFile('x.txt');
        $cleaner = new StreamingDirectoryCleaner;

        $cleaner->deleteFiles($this->base);
        $this->assertSame(0, $cleaner->failedDeletions());
    }

    private function makeFile(string $relative): void
    {
        $path = $this->base.'/'.$relative;
        $dir = \dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, 'x');
    }

    private function removeTree(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        @chmod($path, 0777);
        foreach (new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS) as $item) {
            if ($item->isDir() && ! $item->isLink()) {
                $this->removeTree($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($path);
    }
}
