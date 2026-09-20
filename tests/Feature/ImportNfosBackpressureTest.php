<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\BackgroundWorkPressureGate;
use App\Services\Nzb\NzbUploadManifestService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ImportNfosBackpressureTest extends TestCase
{
    private string $importDirectory;

    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $filesystem = new Filesystem;
        $this->importDirectory = sys_get_temp_dir().'/nntmux-nfo-backpressure-'.bin2hex(random_bytes(6));
        $filesystem->makeDirectory($this->importDirectory);
        $this->databasePath = $this->importDirectory.'/database.sqlite';
        $pdo = new PDO('sqlite:'.$this->databasePath);
        $pdo->exec('CREATE TABLE settings (name VARCHAR PRIMARY KEY, value TEXT NULL)');

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $this->databasePath);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        (new NzbUploadManifestService($filesystem))->create(
            $this->importDirectory,
            'test-upload',
            'example.nzb',
            null,
        );
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        (new Filesystem)->deleteDirectory($this->importDirectory);

        parent::tearDown();
    }

    #[Test]
    public function nfo_import_waits_for_background_capacity_by_default(): void
    {
        $gate = $this->createMock(BackgroundWorkPressureGate::class);
        $gate->expects(self::once())->method('awaitPermission');
        $this->app->instance(BackgroundWorkPressureGate::class, $gate);

        $this->artisan('nntmux:import-nfos', ['--folder' => $this->importDirectory])
            ->expectsOutput('Processed 0 NFOs, skipped 1, failed 0.')
            ->assertSuccessful();
    }

    #[Test]
    public function maintenance_override_skips_the_background_pressure_gate(): void
    {
        $gate = $this->createMock(BackgroundWorkPressureGate::class);
        $gate->expects(self::never())->method('awaitPermission');
        $this->app->instance(BackgroundWorkPressureGate::class, $gate);

        $this->artisan('nntmux:import-nfos', [
            '--folder' => $this->importDirectory,
            '--ignore-backpressure' => true,
        ])
            ->expectsOutput('Processed 0 NFOs, skipped 1, failed 0.')
            ->assertSuccessful();
    }
}
