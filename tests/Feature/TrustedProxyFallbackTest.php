<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\TestCase;

final class TrustedProxyFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'trustedproxy.proxies' => null,
            'trustedproxy.cloudflare.storage_path' => sys_get_temp_dir().'/missing-'.uniqid('', true).'.json',
            'trustedproxy.cloudflare.fallback_to_remote_addr' => true,
        ]);
    }

    protected function tearDown(): void
    {
        SymfonyRequest::setTrustedProxies([], -1);

        parent::tearDown();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function clients(): array
    {
        return [
            'internet client cannot spoof its address' => ['203.0.113.5', '203.0.113.5'],
            'local reverse proxy is trusted' => ['10.0.0.2', '198.51.100.7'],
        ];
    }

    #[Test]
    #[DataProvider('clients')]
    public function without_known_proxy_ranges_only_private_network_proxies_are_trusted(string $remoteAddress, string $expectedClientIp): void
    {
        $request = Request::create('/', 'GET', server: [
            'REMOTE_ADDR' => $remoteAddress,
            'HTTP_X_FORWARDED_FOR' => '198.51.100.7',
        ]);

        $clientIp = (new TrustProxies)->handle($request, fn (Request $handled) => response((string) $handled->ip()))->getContent();

        $this->assertSame($expectedClientIp, $clientIp);
    }
}
