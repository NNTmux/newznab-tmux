<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\TestCase;

class TrustProxiesTest extends TestCase
{
    private string $manifestPath;

    private string $resolvedManifestPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manifestPath = 'storage/framework/testing/trusted-proxies-test.json';
        $this->resolvedManifestPath = base_path($this->manifestPath);

        config([
            'trustedproxy.proxies' => null,
            'trustedproxy.cloudflare.storage_path' => $this->manifestPath,
            'trustedproxy.cloudflare.enabled' => true,
            'trustedproxy.cloudflare.fallback_to_remote_addr' => true,
        ]);
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();
        SymfonyRequest::setTrustedProxies([], SymfonyRequest::HEADER_X_FORWARDED_FOR);

        if (is_file($this->resolvedManifestPath)) {
            unlink($this->resolvedManifestPath);
        }

        $directory = dirname($this->resolvedManifestPath);

        if (is_dir($directory)) {
            @rmdir($directory);
        }

        parent::tearDown();
    }

    public function test_it_trusts_manual_and_cloudflare_proxies(): void
    {
        config([
            'trustedproxy.proxies' => '10.0.0.1/32, REMOTE_ADDR',
            'trustedproxy.cloudflare.fallback_to_remote_addr' => false,
        ]);

        $this->writeManifest([
            'version' => 1,
            'updated_at' => now()->toIso8601String(),
            'ipv4' => ['173.245.48.0/20'],
            'ipv6' => ['2400:cb00::/32'],
            'proxies' => ['173.245.48.0/20', '2400:cb00::/32'],
        ]);

        $middleware = new TrustProxies;
        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.9']);

        $middleware->handle($request, static fn (Request $request) => response('ok'));

        $this->assertSame([
            '10.0.0.1/32',
            '203.0.113.9',
            '173.245.48.0/20',
            '2400:cb00::/32',
        ], SymfonyRequest::getTrustedProxies());
    }

    public function test_it_falls_back_to_the_calling_ip_when_no_manifest_exists(): void
    {
        $middleware = new TrustProxies;
        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '198.51.100.7']);

        $middleware->handle($request, static fn (Request $request) => response('ok'));

        $this->assertSame(['198.51.100.7'], SymfonyRequest::getTrustedProxies());
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function writeManifest(array $manifest): void
    {
        $directory = dirname($this->resolvedManifestPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents(
            $this->resolvedManifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );
    }
}
