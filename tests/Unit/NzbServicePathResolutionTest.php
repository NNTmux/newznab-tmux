<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Nzb\NzbService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class NzbServicePathResolutionTest extends TestCase
{
    public function test_select_preferred_base_path_prefers_existing_runtime_storage_path_for_foreign_storage_root(): void
    {
        $tempDir = sys_get_temp_dir().'/nzb-service-'.uniqid('', true);
        $configuredPath = $tempDir.'/foreign-app/storage/nzb/';
        $runtimePath = $tempDir.'/runtime-app/storage/nzb/';
        mkdir($runtimePath, 0777, true);

        try {
            $service = $this->makeServiceWithoutConstructor();
            $preferredPath = \Closure::bind(
                fn (array $paths): string => $this->selectPreferredNzbBasePath($paths),
                $service,
                NzbService::class
            )([$configuredPath, $runtimePath]);

            $this->assertSame($runtimePath, $preferredPath);
        } finally {
            $this->deleteDirectory($tempDir);
        }
    }

    public function test_nzb_path_returns_existing_file_from_alternate_candidate_base_paths(): void
    {
        $tempDir = sys_get_temp_dir().'/nzb-path-'.uniqid('', true);
        $guid = '4aabfe07-daff-4d28-9d1d-d2a4ab7b6511';
        $configuredPath = $tempDir.'/foreign-app/storage/nzb/';
        $runtimePath = $tempDir.'/runtime-app/storage/nzb/';
        $expectedFile = $runtimePath.'4/a/a/b/'.$guid.'.nzb.gz';
        mkdir(dirname($expectedFile), 0777, true);
        file_put_contents($expectedFile, 'test');

        try {
            $service = $this->makeServiceWithoutConstructor();
            \Closure::bind(
                function (int $splitLevel, string $primaryPath, array $paths): void {
                    $this->nzbSplitLevel = $splitLevel;
                    $this->siteNzbPath = $primaryPath;
                    $this->siteNzbPaths = $paths;
                },
                $service,
                NzbService::class
            )(4, $configuredPath, [$configuredPath, $runtimePath]);

            $this->assertSame($expectedFile, $service->nzbPath($guid));
        } finally {
            $this->deleteDirectory($tempDir);
        }
    }

    public function test_nzb_path_honors_split_level_one_when_searching_candidate_paths(): void
    {
        $tempDir = sys_get_temp_dir().'/nzb-path-split-one-'.uniqid('', true);
        $guid = '4aabfe07-daff-4d28-9d1d-d2a4ab7b6511';
        $configuredPath = $tempDir.'/foreign-app/storage/nzb/';
        $runtimePath = $tempDir.'/runtime-app/storage/nzb/';
        $expectedFile = $runtimePath.'4/'.$guid.'.nzb.gz';
        mkdir(dirname($expectedFile), 0777, true);
        file_put_contents($expectedFile, 'test');

        try {
            $service = $this->makeServiceWithoutConstructor();
            \Closure::bind(
                function (int $splitLevel, string $primaryPath, array $paths): void {
                    $this->nzbSplitLevel = $splitLevel;
                    $this->siteNzbPath = $primaryPath;
                    $this->siteNzbPaths = $paths;
                },
                $service,
                NzbService::class
            )(1, $configuredPath, [$configuredPath, $runtimePath]);

            $this->assertSame($expectedFile, $service->nzbPath($guid));
        } finally {
            $this->deleteDirectory($tempDir);
        }
    }

    public function test_nzb_path_works_with_existing_resources_nzb_base_path(): void
    {
        $tempDir = sys_get_temp_dir().'/nzb-path-resources-'.uniqid('', true);
        $guid = '4aabfe07-daff-4d28-9d1d-d2a4ab7b6511';
        $configuredPath = $tempDir.'/resources/nzb/';
        $storagePath = $tempDir.'/storage/nzb/';
        $expectedFile = $configuredPath.'4/'.$guid.'.nzb.gz';
        $configuredLinkTarget = rtrim($configuredPath, '/');
        $storageLinkPath = rtrim($storagePath, '/');

        mkdir(dirname($expectedFile), 0777, true);
        mkdir(dirname($storageLinkPath), 0777, true);
        symlink($configuredLinkTarget, $storageLinkPath);
        file_put_contents($expectedFile, 'test');

        try {
            $service = $this->makeServiceWithoutConstructor();
            \Closure::bind(
                function (int $splitLevel, string $primaryPath, array $paths): void {
                    $this->nzbSplitLevel = $splitLevel;
                    $this->siteNzbPath = $primaryPath;
                    $this->siteNzbPaths = $paths;
                },
                $service,
                NzbService::class
            )(1, $configuredPath, [$configuredPath, $storagePath]);

            $this->assertSame($expectedFile, $service->nzbPath($guid));
        } finally {
            $this->deleteDirectory($tempDir);
        }
    }

    private function makeServiceWithoutConstructor(): NzbService
    {
        return (new ReflectionClass(NzbService::class))->newInstanceWithoutConstructor();
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}
