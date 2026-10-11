<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Release;
use App\Services\AdditionalProcessing\ArchiveExtractionService;
use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\State\ReleaseProcessingContext;
use App\Services\NfoService;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * unrar rejects a lone "-" with "ERROR: Unknown option" (exit 7) and extracts nothing;
 * "--" is RAR's documented end-of-switches marker.
 */
class UnrarArgumentsTest extends TestCase
{
    private string $unrarPath;

    private string $tmpPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpPath = sys_get_temp_dir().'/unrar-args-'.uniqid('', true).'/';
        File::ensureDirectoryExists($this->tmpPath);
        $this->unrarPath = $this->tmpPath.'unrar';
        File::put($this->unrarPath, "#!/bin/sh\nexit 0\n");
        chmod($this->unrarPath, 0700);
        Process::fake();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmpPath);

        parent::tearDown();
    }

    #[Test]
    public function archive_extraction_ends_unrar_switches_with_double_dash(): void
    {
        $service = new ArchiveExtractionService($this->config());

        $service->processCompressedData($this->fixture(), new ReleaseProcessingContext(new Release), $this->tmpPath);

        $this->assertUnrarRanWithSwitchTerminator();
    }

    #[Test]
    public function single_file_extraction_ends_unrar_switches_with_double_dash(): void
    {
        $service = new ArchiveExtractionService($this->config());
        $method = new ReflectionMethod($service, 'extractSpecificFileWithExternalTools');

        $method->invoke($service, $this->fixture(), 'sample.mkv', $this->tmpPath);

        $this->assertUnrarRanWithSwitchTerminator();
    }

    #[Test]
    public function nfo_extraction_ends_unrar_switches_with_double_dash(): void
    {
        $nfo = (new ReflectionClass(NfoService::class))->newInstanceWithoutConstructor();
        foreach (['tmpPath' => $this->tmpPath, 'unrarPath' => $this->unrarPath, 'timeoutPath' => false, 'timeoutSeconds' => 0, 'echo' => false] as $property => $value) {
            (new \ReflectionProperty($nfo, $property))->setValue($nfo, $value);
        }

        (new ReflectionMethod($nfo, 'extractNfoViaUnrar'))->invoke($nfo, $this->fixture(), ['release.nfo'], 'guid');

        $this->assertUnrarRanWithSwitchTerminator();
    }

    private function assertUnrarRanWithSwitchTerminator(): void
    {
        Process::assertRan(function (PendingProcess $process): bool {
            $command = (array) $process->command;
            $unrarAt = array_search($this->unrarPath, $command, true);
            if ($unrarAt === false) {
                return false;
            }
            $arguments = array_slice($command, $unrarAt + 1);

            return in_array('--', $arguments, true) && ! in_array('-', $arguments, true);
        });
    }

    private function config(): ProcessingConfiguration
    {
        $config = (new ReflectionClass(ProcessingConfiguration::class))->newInstanceWithoutConstructor();
        foreach ([
            'unrarPath' => $this->unrarPath,
            'unzipPath' => false,
            'extractUsingRarInfo' => false,
            'timeoutPath' => false,
            'timeoutSeconds' => 0,
            'debugMode' => false,
        ] as $property => $value) {
            (new \ReflectionProperty($config, $property))->setValue($config, $value);
        }

        return $config;
    }

    private function fixture(): string
    {
        return (string) file_get_contents(dirname(__DIR__).'/Fixtures/archives/stored-sample.rar');
    }
}
