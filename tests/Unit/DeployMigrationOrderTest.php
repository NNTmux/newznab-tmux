<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class DeployMigrationOrderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/deploy-order-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/docker/bin', 0700, true);
        mkdir($this->directory.'/bin', 0700);
        copy(__DIR__.'/../../docker/Makefile', $this->directory.'/docker/Makefile');
        file_put_contents($this->directory.'/docker/bin/deploy-guard', '#!/bin/sh'."\n".'if [ "$1" = verify ]; then exit 0; fi'."\n".'shift 3; exec "$@"'."\n");
        file_put_contents($this->directory.'/bin/docker', '#!/bin/sh'."\n".'printf "%s\n" "$*" >> "$NNTMUX_DEPLOY_EVENTS"'."\n".'case "$*" in *"artisan migrate"*) [ "${NNTMUX_MIGRATION_FAIL:-0}" = 1 ] && exit 42;; esac'."\nexit 0\n");
        chmod($this->directory.'/docker/bin/deploy-guard', 0755);
        chmod($this->directory.'/bin/docker', 0755);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function test_deploy_migrates_before_starting_the_new_runtime(): void
    {
        $process = $this->deploy(false);
        $process->mustRun();
        $events = file_get_contents($this->directory.'/events');
        $stop = strpos($events, ' stop web scheduler indexer horizon');
        $migration = strpos($events, 'artisan migrate --force --no-interaction');
        $up = strpos($events, ' up -d');
        $this->assertNotFalse($stop);
        $this->assertNotFalse($migration);
        $this->assertNotFalse($up);
        $this->assertLessThan($migration, $stop);
        $this->assertLessThan($up, $migration);
    }

    public function test_failed_migration_never_starts_new_runtime(): void
    {
        $process = $this->deploy(true);
        $process->run();
        $this->assertFalse($process->isSuccessful());
        $this->assertStringNotContainsString(' up -d', file_get_contents($this->directory.'/events'));
    }

    private function deploy(bool $fail): Process
    {
        return new Process(['make', '-s', '-C', $this->directory.'/docker', 'deploy', 'DEPLOY_BY=fixture'], env: [
            'PATH' => $this->directory.'/bin:'.getenv('PATH'), 'NNTMUX_DEPLOY_EVENTS' => $this->directory.'/events',
            'NNTMUX_MIGRATION_FAIL' => $fail ? '1' : '0',
        ]);
    }
}
