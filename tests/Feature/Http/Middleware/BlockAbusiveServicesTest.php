<?php

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\BlockAbusiveServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class BlockAbusiveServicesTest extends TestCase
{
    private BlockAbusiveServices $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new BlockAbusiveServices;
        Cache::flush();
    }

    /**
     * Test that normal requests pass through.
     */
    public function test_allows_normal_requests(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'as' => 'AS15169 Google LLC',
                'org' => 'Google LLC',
            ], 200),
        ]);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0');
        $request->server->set('REMOTE_ADDR', '8.8.8.8');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * Test that AIOStreams User-Agent is blocked.
     */
    public function test_blocks_aiostreams_user_agent(): void
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('User-Agent', 'AIOStreams/1.0');
        $request->server->set('REMOTE_ADDR', '8.8.8.8');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('Streaming services are not allowed', $response->getContent());
    }

    /**
     * Test that UsenetStreamer User-Agent is blocked.
     */
    public function test_blocks_usenetstreamer_user_agent(): void
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('User-Agent', 'UsenetStreamer/2.0');
        $request->server->set('REMOTE_ADDR', '8.8.8.8');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('Streaming services are not allowed', $response->getContent());
    }

    /**
     * Test that Stremio User-Agent is blocked.
     */
    public function test_blocks_stremio_user_agent(): void
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('User-Agent', 'Stremio/4.5.0');
        $request->server->set('REMOTE_ADDR', '8.8.8.8');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('Streaming services are not allowed', $response->getContent());
    }

    /**
     * Test that User-Agent check is case-insensitive.
     */
    public function test_blocks_user_agent_case_insensitive(): void
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('User-Agent', 'AIOSTREAMS/1.0 (uppercase)');
        $request->server->set('REMOTE_ADDR', '8.8.8.8');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * Test that Oracle Cloud ASN is blocked.
     */
    public function test_blocks_oracle_cloud_asn(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'as' => 'AS31898 Oracle Corporation',
                'org' => 'Oracle Corporation',
            ], 200),
        ]);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0');
        $request->server->set('REMOTE_ADDR', '129.146.10.50');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        $this->assertEquals(403, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Oracle Cloud Infrastructure', $json['message']);
    }

    /**
     * Test that Cloudflare WARP ASN is blocked.
     */
    public function test_blocks_cloudflare_warp_asn(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'as' => 'AS13335 Cloudflare Inc',
                'org' => 'Cloudflare Inc',
            ], 200),
        ]);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0');
        $request->server->set('REMOTE_ADDR', '104.16.50.100');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        $this->assertEquals(403, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Cloudflare WARP', $json['message']);
    }

    /**
     * Test that non-blocked ASNs pass through.
     */
    public function test_allows_non_blocked_asn(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'as' => 'AS15169 Google LLC',
                'org' => 'Google LLC',
            ], 200),
        ]);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0');
        $request->server->set('REMOTE_ADDR', '8.8.8.8');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test that private IPs are allowed (skip ASN lookup).
     */
    public function test_allows_private_ips(): void
    {
        Http::fake(); // Should not be called

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0');
        $request->server->set('REMOTE_ADDR', '192.168.1.100');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
        Http::assertNothingSent();
    }

    /**
     * Test that localhost is allowed.
     */
    public function test_allows_localhost(): void
    {
        Http::fake(); // Should not be called

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
        Http::assertNothingSent();
    }

    /**
     * Test that API failures allow request to pass through.
     */
    public function test_allows_request_on_api_failure(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response(null, 500),
        ]);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0');
        $request->server->set('REMOTE_ADDR', '8.8.8.8');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test that ASN lookups are cached.
     */
    public function test_caches_asn_lookups(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'as' => 'AS15169 Google LLC',
                'org' => 'Google LLC',
            ], 200),
        ]);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0');
        $request->server->set('REMOTE_ADDR', '8.8.8.8');

        // First request
        $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        // Second request (should use cache)
        $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        // API should only be called once
        Http::assertSentCount(1);
    }

    /**
     * Test that empty User-Agent is allowed.
     */
    public function test_allows_empty_user_agent(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'as' => 'AS15169 Google LLC',
                'org' => 'Google LLC',
            ], 200),
        ]);

        $request = Request::create('/api/test', 'GET');
        $request->server->set('REMOTE_ADDR', '8.8.8.8');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test response is JSON format.
     */
    public function test_blocked_response_is_json(): void
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('User-Agent', 'AIOStreams/1.0');
        $request->server->set('REMOTE_ADDR', '8.8.8.8');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        $json = json_decode($response->getContent(), true);

        $this->assertIsArray($json);
        $this->assertArrayHasKey('error', $json);
        $this->assertArrayHasKey('message', $json);
        $this->assertTrue($json['error']);
    }
}
