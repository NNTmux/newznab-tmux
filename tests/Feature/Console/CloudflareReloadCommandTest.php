<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class CloudflareReloadCommandTest extends TestCase
{
    private string $databasePath;

    private string $manifestPath;

    private string $resolvedManifestPath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-cloudflare-command-test.sqlite';

        $this->originalEnvironment = [
            'APP_ENV' => getenv('APP_ENV'),
            'DB_CONNECTION' => getenv('DB_CONNECTION'),
            'DB_DATABASE' => getenv('DB_DATABASE'),
        ];

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        $pdo = new PDO('sqlite:'.$this->databasePath);
        $pdo->exec('CREATE TABLE settings (name VARCHAR PRIMARY KEY, value TEXT NULL)');
        $pdo->exec("INSERT INTO settings (name, value) VALUES
            ('categorizeforeign', '0'),
            ('catwebdl', '0')");

        $this->setEnvironmentValue('APP_ENV', 'testing');
        $this->setEnvironmentValue('DB_CONNECTION', 'sqlite');
        $this->setEnvironmentValue('DB_DATABASE', $this->databasePath);

        $app = require __DIR__.'/../../../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->manifestPath = 'storage/framework/testing/cloudflare-'.Str::uuid()->toString().'.json';
        $this->resolvedManifestPath = base_path($this->manifestPath);

        config([
            'trustedproxy.proxies' => null,
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'trustedproxy.cloudflare.storage_path' => $this->manifestPath,
            'trustedproxy.cloudflare.ipv4_url' => 'https://www.cloudflare.com/ips-v4',
            'trustedproxy.cloudflare.ipv6_url' => 'https://www.cloudflare.com/ips-v6',
            'trustedproxy.cloudflare.fallback_to_remote_addr' => false,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->resolvedManifestPath)) {
            unlink($this->resolvedManifestPath);
        }

        if ($this->databasePath !== '' && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        $directory = dirname($this->resolvedManifestPath);

        if (is_dir($directory)) {
            @rmdir($directory);
        }

        parent::tearDown();

        foreach ($this->originalEnvironment as $key => $value) {
            $this->setEnvironmentValue($key, $value === false ? null : $value);
        }
    }

    public function test_it_refreshes_and_persists_cloudflare_ip_ranges(): void
    {
        Http::fake([
            'https://www.cloudflare.com/ips-v4' => Http::response("173.245.48.0/20\n104.16.0.0/13\n", 200),
            'https://www.cloudflare.com/ips-v6' => Http::response("2400:cb00::/32\n2a06:98c0::/29\n", 200),
        ]);

        $this->artisan('cloudflare:reload')
            ->expectsOutputToContain('Cloudflare IPv4 ranges: 2')
            ->expectsOutputToContain('Cloudflare IPv6 ranges: 2')
            ->expectsOutputToContain('Combined Cloudflare proxy count: 4')
            ->expectsOutputToContain('Stored Cloudflare manifest: '.base_path($this->manifestPath))
            ->assertExitCode(0);

        $this->assertFileExists($this->resolvedManifestPath);

        $manifest = json_decode((string) file_get_contents($this->resolvedManifestPath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(['173.245.48.0/20', '104.16.0.0/13'], $manifest['ipv4']);
        $this->assertSame(['2400:cb00::/32', '2a06:98c0::/29'], $manifest['ipv6']);
        $this->assertCount(4, $manifest['proxies']);
        Http::assertSentCount(2);
    }

    public function test_it_preserves_the_existing_manifest_when_refresh_fails(): void
    {
        $existingManifest = [
            'version' => 1,
            'updated_at' => '2026-03-30T00:00:00+00:00',
            'ipv4' => ['173.245.48.0/20'],
            'ipv6' => [],
            'proxies' => ['173.245.48.0/20'],
        ];

        if (! is_dir(dirname($this->resolvedManifestPath))) {
            mkdir(dirname($this->resolvedManifestPath), 0777, true);
        }

        file_put_contents(
            $this->resolvedManifestPath,
            json_encode($existingManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );

        Http::fake([
            'https://www.cloudflare.com/ips-v4' => Http::response('upstream failure', 500),
            'https://www.cloudflare.com/ips-v6' => Http::response("2400:cb00::/32\n", 200),
        ]);

        $this->artisan('cloudflare:reload')
            ->expectsOutputToContain('Failed to refresh Cloudflare IP ranges:')
            ->assertExitCode(1);

        $manifest = json_decode((string) file_get_contents($this->resolvedManifestPath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($existingManifest, $manifest);
    }

    private function setEnvironmentValue(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
