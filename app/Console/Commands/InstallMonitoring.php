<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Process\Process as SymfonyProcess;

#[AsCommand(name: 'monitoring:install')]
class InstallMonitoring extends Command
{
    private const array SUPPORTED_UBUNTU = ['22.04', '24.04'];

    /**
     * @var string
     */
    protected $signature = 'monitoring:install
                            {--sail : Configure Sail (docker-compose.monitoring.yml) instead of this host}
                            {--run : Run the installer with sudo after the checks}
                            {--web-server= : nginx or apache (detected by the installer when omitted)}';

    /**
     * @var string
     */
    protected $description = 'Check prerequisites and install Prometheus + Grafana monitoring (Ubuntu host or Sail)';

    public function handle(): int
    {
        return $this->option('sail') ? $this->configureSail() : $this->prepareHostInstall();
    }

    private function prepareHostInstall(): int
    {
        $script = base_path('scripts/install-monitoring.sh');
        $webServer = $this->option('web-server');

        if ($webServer !== null && ! in_array($webServer, ['nginx', 'apache'], true)) {
            $this->components->error('--web-server must be nginx or apache.');

            return self::FAILURE;
        }

        if (! File::exists($this->laravel->environmentFilePath())) {
            $this->components->error('No .env file found; finish the NNTmux install first.');

            return self::FAILURE;
        }

        $release = $this->ubuntuRelease();
        if ($release === null || ! in_array($release, self::SUPPORTED_UBUNTU, true)) {
            $this->components->warn('The installer supports Ubuntu '.implode(' and ', self::SUPPORTED_UBUNTU).'; this host reports '.($release === null ? 'a non-Ubuntu OS' : "Ubuntu {$release}").'.');
        }

        // Same detection the installer runs, so the suggested flags match what it will do.
        $this->components->info('Detecting existing monitoring components');
        $detection = Process::path(base_path())->timeout(60)->run(['bash', $script, '--detect', '--app-path='.base_path()]);
        $this->output->write($detection->output());
        if (! $detection->successful()) {
            $this->output->write($detection->errorOutput());
        }

        $command = ['sudo', 'bash', $script, '--app-path='.base_path()];
        if ($webServer !== null) {
            $command[] = '--web-server='.$webServer;
        }

        $this->newLine();
        $this->components->info('Run the installer as root:');
        $this->line('  '.implode(' ', array_map('escapeshellarg', $command)));
        $this->line('  Add --dry-run to preview, or --node-exporter-url=… if an existing exporter needs credentials (see --help).');
        $this->components->warn('The Laravel scheduler must be running for `monitoring:export-metrics` to push NNTmux metrics every minute.');

        if (! $this->option('run')) {
            return self::SUCCESS;
        }

        $result = Process::path(base_path())->forever()->tty(SymfonyProcess::isTtySupported())->run($command, function (string $type, string $output): void {
            $this->output->write($output);
        });

        return $result->successful() ? self::SUCCESS : self::FAILURE;
    }

    private function configureSail(): int
    {
        $keyDirectory = storage_path('app/monitoring');
        $privateKey = $keyDirectory.'/grafana-jwt.key';
        // Only the public/ directory is mounted into the Grafana container.
        $publicKey = $keyDirectory.'/public/grafana-jwt.pub';

        File::ensureDirectoryExists($keyDirectory.'/public', 0755);

        if (File::exists($privateKey) && File::exists($publicKey)) {
            $this->components->info('Keeping the existing Grafana JWT keypair in '.$keyDirectory);
        } else {
            [$private, $public] = $this->generateKeypair();
            File::put($privateKey, $private);
            File::chmod($privateKey, 0600);
            File::put($publicKey, $public);
            // Grafana runs as its own user inside the container and only needs the public key.
            File::chmod($publicKey, 0644);
            $this->components->info('Generated a Grafana JWT keypair in '.$keyDirectory);
        }

        $this->setEnvironmentValues([
            'MONITORING_ENABLED' => 'true',
            'GRAFANA_URL' => '/grafana',
            // docker/8.5/nginx.conf authenticates /grafana/ with auth_request.
            'GRAFANA_AUTH' => 'proxy',
            'GRAFANA_JWT_PRIVATE_KEY_PATH' => $this->projectRelativePath($privateKey),
            'MONITORING_PUSHGATEWAY_URL' => 'http://pushgateway:9091',
        ]);

        $this->components->info('Next steps:');
        $this->line('  make build          # picks up the /grafana/ proxy in docker/8.5/nginx.conf');
        $this->line('  make up             # now also starts Prometheus, Grafana, the Pushgateway and exporters');
        $this->line('  ./sail artisan config:clear');

        return self::SUCCESS;
    }

    /**
     * @return array{string, string}
     */
    private function generateKeypair(): array
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);

        if ($key === false || ! openssl_pkey_export($key, $private)) {
            throw new RuntimeException('Unable to generate an RSA keypair: '.(openssl_error_string() ?: 'unknown OpenSSL error'));
        }

        $details = openssl_pkey_get_details($key);

        if ($details === false) {
            throw new RuntimeException('Unable to read the generated RSA public key.');
        }

        return [$private, $details['key']];
    }

    /**
     * @param  array<string, string>  $values
     */
    private function setEnvironmentValues(array $values): void
    {
        $path = $this->laravel->environmentFilePath();

        if (! File::exists($path)) {
            throw new RuntimeException("No .env file at [{$path}].");
        }

        $contents = File::get($path);

        foreach ($values as $key => $value) {
            $line = $key.'='.$value;
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            $contents = preg_match($pattern, $contents) === 1
                ? (string) preg_replace($pattern, $line, $contents)
                : rtrim($contents, "\n")."\n".$line."\n";

            $this->line("  .env: {$line}");
        }

        File::put($path, $contents);
    }

    private function projectRelativePath(string $path): string
    {
        $base = rtrim(base_path(), '/').'/';

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }

    private function ubuntuRelease(): ?string
    {
        if (! is_readable('/etc/os-release')) {
            return null;
        }

        $release = parse_ini_file('/etc/os-release');

        if (! is_array($release) || ($release['ID'] ?? null) !== 'ubuntu') {
            return null;
        }

        return isset($release['VERSION_ID']) ? (string) $release['VERSION_ID'] : null;
    }
}
