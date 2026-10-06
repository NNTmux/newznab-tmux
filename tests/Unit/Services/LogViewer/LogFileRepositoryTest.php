<?php

declare(strict_types=1);

namespace Tests\Unit\Services\LogViewer;

use App\Services\LogViewer\LogFile;
use App\Services\LogViewer\LogFileRepository;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LogFileRepositoryTest extends TestCase
{
    private string $root;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/log-repository-'.Str::uuid()->toString());
        $this->directory = $this->root.'/logs';
        File::ensureDirectoryExists($this->directory.'/nested');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    #[Test]
    public function it_lists_readable_files_newest_first_and_detects_monolog_format(): void
    {
        $this->write('old.log', "[2026-10-01 10:00:00] local.INFO: hi\n", time() - 3600);
        $this->write('nested/horizon.log', "  2026-10-05 21:53:20 App\\Jobs\\A ... DONE\n", time());
        $this->write('.gitignore', "*\n", time());

        $files = $this->repository()->all();

        $this->assertSame(['nested/horizon.log', 'old.log'], array_map(static fn (LogFile $file): string => $file->path, $files));
        $this->assertFalse($files[0]->structured);
        $this->assertSame('nested', $files[0]->directory);
        $this->assertTrue($files[1]->structured);
    }

    #[Test]
    public function find_only_returns_whitelisted_paths(): void
    {
        $this->write('app.log', "x\n", time());
        File::put($this->root.'/secret.txt', 'secret');
        symlink($this->root.'/secret.txt', $this->directory.'/link.log');

        $repository = $this->repository();

        $this->assertNotNull($repository->find('/app.log'));
        $this->assertNull($repository->find('../secret.txt'));
        $this->assertNull($repository->find('link.log'));
    }

    #[Test]
    public function it_truncates_and_deletes_files(): void
    {
        $this->write('a.log', "content\n", time());
        $this->write('b.log', "content\n", time());
        $repository = $this->repository();

        $repository->truncate($repository->find('a.log') ?? $this->fail('a.log missing'));
        $repository->delete($repository->find('b.log') ?? $this->fail('b.log missing'));

        $this->assertSame(0, $repository->find('a.log')?->size);
        $this->assertNull($repository->find('b.log'));
        $this->assertFileDoesNotExist($this->directory.'/b.log');
    }

    private function repository(): LogFileRepository
    {
        return app()->makeWith(LogFileRepository::class, ['directory' => $this->directory]);
    }

    private function write(string $relativePath, string $contents, int $modifiedAt): void
    {
        $path = $this->directory.'/'.$relativePath;
        File::put($path, $contents);
        touch($path, $modifiedAt);
    }
}
