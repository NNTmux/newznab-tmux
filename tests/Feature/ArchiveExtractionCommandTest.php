<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\AdditionalProcessing\ArchiveExtractionService;
use App\Services\NfoService;
use dariusiii\rarinfo\ArchiveInfo;
use dariusiii\rarinfo\Par2Info;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Mockery;
use Tests\TestCase;
use Tests\Unit\AdditionalProcessing\BuildsArchiveFixtures;
use Tests\Unit\AdditionalProcessing\CreatesProcessingConfiguration;

class ArchiveExtractionCommandTest extends TestCase
{
    use BuildsArchiveFixtures;
    use CreatesProcessingConfiguration;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_unrar_receives_an_archive_filename_as_one_literal_argument(): void
    {
        Process::fake();

        $filename = 'release$(touch command-injection).nfo';
        $service = $this->serviceWithUnavailableArchiveInfo([
            'unrarPath' => '/usr/bin/unrar',
            'timeoutPath' => '/usr/bin/timeout',
            'timeoutSeconds' => 60,
        ]);

        $service->extractSpecificFile('ARCHIVE', $filename, sys_get_temp_dir().'/');

        Process::assertRan(function (PendingProcess $process) use ($filename): bool {
            return is_array($process->command)
                && array_slice($process->command, 0, 6) === [
                    '/usr/bin/timeout',
                    '--foreground',
                    '--signal=KILL',
                    '60',
                    '/usr/bin/unrar',
                    'e',
                ]
                && in_array($filename, $process->command, true);
        });
    }

    public function test_unzip_receives_an_archive_filename_as_one_literal_argument(): void
    {
        Process::fake();

        $filename = 'release`touch command-injection`.nfo';
        $service = $this->serviceWithUnavailableArchiveInfo([
            'unzipPath' => '/usr/bin/unzip',
        ]);

        $service->extractSpecificFile('ARCHIVE', $filename, sys_get_temp_dir().'/');

        Process::assertRan(function (PendingProcess $process) use ($filename): bool {
            return is_array($process->command)
                && array_slice($process->command, 0, 2) === ['/usr/bin/unzip', '-j']
                && in_array($filename, $process->command, true);
        });
    }

    public function test_nfo_fallback_passes_the_archive_filename_without_a_shell(): void
    {
        Process::fake();

        $filename = 'release$(touch command-injection).nfo';
        $tmpPath = sys_get_temp_dir().'/nntmux-nfo-command-'.uniqid('', true).'/';
        mkdir($tmpPath, 0777, true);

        $reflection = new \ReflectionClass(NfoService::class);
        /** @var NfoService $service */
        $service = $reflection->newInstanceWithoutConstructor();
        foreach ([
            'tmpPath' => $tmpPath,
            'unrarPath' => '/usr/bin/unrar',
            'timeoutPath' => '/usr/bin/timeout',
            'timeoutSeconds' => 60,
        ] as $property => $value) {
            $reflection->getProperty($property)->setValue($service, $value);
        }

        $reflection->getMethod('extractNfoViaUnrar')->invoke($service, 'ARCHIVE', [$filename], 'test-guid');

        Process::assertRan(function (PendingProcess $process) use ($filename): bool {
            return is_array($process->command)
                && array_slice($process->command, 0, 6) === [
                    '/usr/bin/timeout',
                    '--foreground',
                    '--signal=KILL',
                    '60',
                    '/usr/bin/unrar',
                    'e',
                ]
                && in_array($filename, $process->command, true);
        });

        $this->assertSame([], glob($tmpPath.'*') ?: []);
        rmdir($tmpPath);
    }

    public function test_7z_extraction_never_invokes_external_tools_even_with_unreadable_headers(): void
    {
        Process::fake();
        $archiveInfo = Mockery::mock(ArchiveInfo::class);
        $archiveInfo->shouldReceive('setExternalClients')->once();
        $archiveInfo->shouldNotReceive('setData');
        $archiveInfo->shouldNotReceive('getFileData');
        $service = new ArchiveExtractionService($this->makeConfig([
            'unrarPath' => '/usr/bin/unrar',
            'unzipPath' => '/usr/bin/unzip',
        ]), $archiveInfo);
        $archive = $this->sevenZip('release.nfo', str_repeat('A', 20));
        $joined = $service->withSevenZipEndHeader(substr($archive, 0, 40), substr($archive, -80));
        $this->assertIsString($joined);

        foreach ([$archive, $joined, substr($archive, 0, 32)] as $data) {
            $this->assertNull($service->extractSpecificFile($data, 'release.nfo', '/unused/'));
        }

        Process::assertNothingRan();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function serviceWithUnavailableArchiveInfo(array $config): ArchiveExtractionService
    {
        $archiveInfo = Mockery::mock(ArchiveInfo::class);
        $archiveInfo->shouldReceive('setData')->once()->with('ARCHIVE', true)->andReturn(false);
        if (isset($config['unrarPath'])) {
            $archiveInfo->shouldReceive('setExternalClients')->once()->with([
                ArchiveInfo::TYPE_RAR => $config['unrarPath'],
            ]);
        }
        if (! $archiveInfo instanceof ArchiveInfo) {
            throw new \LogicException('ArchiveInfo mock must extend ArchiveInfo.');
        }

        return new ArchiveExtractionService(
            $this->makeConfig($config),
            $archiveInfo,
            new Par2Info,
        );
    }
}
