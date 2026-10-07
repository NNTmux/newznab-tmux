<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\TestCase;

class InstallMonitoringCommandTest extends TestCase
{
    private string $workDirectory = '';

    private string $originalEnvironmentPath = '';

    private string $originalStoragePath = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->workDirectory = sys_get_temp_dir().'/nntmux-monitoring-install-'.Str::random(8);
        File::ensureDirectoryExists($this->workDirectory.'/storage');

        $this->originalEnvironmentPath = $this->app->environmentPath();
        $this->originalStoragePath = $this->app->storagePath();
        $this->app->useEnvironmentPath($this->workDirectory);
        $this->app->useStoragePath($this->workDirectory.'/storage');
        File::put($this->envFile(), "APP_NAME=NNTmux\nMONITORING_ENABLED=false\n");
    }

    private function envFile(): string
    {
        return $this->workDirectory.'/'.$this->app->environmentFile();
    }

    protected function tearDown(): void
    {
        $this->app->useEnvironmentPath($this->originalEnvironmentPath);
        $this->app->useStoragePath($this->originalStoragePath);
        File::deleteDirectory($this->workDirectory);

        parent::tearDown();
    }

    public function test_sail_setup_generates_a_keypair_and_enables_monitoring_in_env(): void
    {
        $this->artisan('monitoring:install', ['--sail' => true])->assertSuccessful();

        $privateKey = $this->workDirectory.'/storage/app/monitoring/grafana-jwt.key';
        $publicKey = $this->workDirectory.'/storage/app/monitoring/public/grafana-jwt.pub';
        $this->assertFileExists($privateKey);
        $this->assertFileExists($publicKey);
        $this->assertSame('0600', substr(sprintf('%o', fileperms($privateKey)), -4));
        $this->assertNotFalse(openssl_pkey_get_private((string) File::get($privateKey)));

        $env = File::get($this->envFile());
        $this->assertStringContainsString("MONITORING_ENABLED=true\n", $env);
        $this->assertStringNotContainsString('MONITORING_ENABLED=false', $env);
        $this->assertStringContainsString('GRAFANA_JWT_PRIVATE_KEY_PATH='.$privateKey."\n", $env);
        $this->assertStringContainsString("MONITORING_PUSHGATEWAY_URL=http://pushgateway:9091\n", $env);
        $this->assertStringContainsString("GRAFANA_AUTH=proxy\n", $env);
        $this->assertStringContainsString("APP_NAME=NNTmux\n", $env);
    }

    public function test_sail_setup_keeps_an_existing_keypair(): void
    {
        $this->artisan('monitoring:install', ['--sail' => true])->assertSuccessful();
        $privateKey = $this->workDirectory.'/storage/app/monitoring/grafana-jwt.key';
        $original = File::get($privateKey);

        $this->artisan('monitoring:install', ['--sail' => true])->assertSuccessful();

        $this->assertSame($original, File::get($privateKey));
    }

    public function test_host_mode_runs_detection_and_prints_the_sudo_command_without_running_it(): void
    {
        Process::fake(['*' => Process::result(output: "node_exporter  existing  127.0.0.1:9100\n")]);

        $this->artisan('monitoring:install', ['--web-server' => 'apache'])
            ->expectsOutputToContain('node_exporter  existing')
            ->expectsOutputToContain("'--web-server=apache'")
            ->assertSuccessful();

        Process::assertRanTimes(static fn (PendingProcess $process): bool => in_array('--detect', (array) $process->command, true), 1);
        Process::assertDidntRun(static fn (PendingProcess $process): bool => ((array) $process->command)[0] === 'sudo');
    }

    public function test_host_mode_runs_the_installer_with_sudo_when_asked(): void
    {
        Process::fake();

        $this->artisan('monitoring:install', ['--run' => true])->assertSuccessful();

        Process::assertRan(static fn (PendingProcess $process): bool => ((array) $process->command)[0] === 'sudo'
            && in_array(base_path('scripts/install-monitoring.sh'), (array) $process->command, true));
    }

    public function test_rejects_unknown_web_servers(): void
    {
        Process::fake();

        $this->artisan('monitoring:install', ['--web-server' => 'caddy'])->assertFailed();

        Process::assertNothingRan();
    }
}
