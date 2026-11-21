<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\DenyBlockedIps;
use App\Services\Security\IpBlockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class DenyBlockedIpsTest extends TestCase
{
    private const string BLOCKED_IP = '203.0.113.50';

    private const string OTHER_IP = '203.0.113.51';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'nntmux.ip_blocking.enabled' => true,
            'trustedproxy.cloudflare.enabled' => false,
            'trustedproxy.proxies' => null,
        ]);

        DB::purge();
        DB::reconnect();
        Cache::flush();

        (require database_path('migrations/2026_11_21_165723_create_blocked_ips_table.php'))->up();
        $this->travelTo('2026-11-21 12:00:00');
    }

    #[Test]
    public function a_blocked_address_gets_the_blocked_page_instead_of_the_requested_one(): void
    {
        app(IpBlockService::class)->blockManually(self::BLOCKED_IP);

        $response = $this->handle('/login', self::BLOCKED_IP);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('Access from your network address is blocked', (string) $response->getContent());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    #[Test]
    public function a_blocked_address_gets_a_json_refusal_on_api_and_rss_paths(): void
    {
        app(IpBlockService::class)->blockManually(self::BLOCKED_IP);

        foreach (['/api/v2/search', '/rss/full-feed'] as $path) {
            $response = $this->handle($path, self::BLOCKED_IP);

            $this->assertSame(403, $response->getStatusCode(), $path);
            $this->assertSame(
                ['error' => true, 'message' => 'Access denied: this IP address is blocked.'],
                json_decode((string) $response->getContent(), true),
                $path,
            );
        }
    }

    #[Test]
    public function other_addresses_are_let_through(): void
    {
        app(IpBlockService::class)->blockManually(self::BLOCKED_IP);

        $this->assertPassedThrough($this->handle('/login', self::OTHER_IP));
    }

    #[Test]
    public function an_address_is_let_through_again_once_its_block_has_ended(): void
    {
        app(IpBlockService::class)->blockManually(self::BLOCKED_IP, now()->addHour());
        $this->assertSame(403, $this->handle('/login', self::BLOCKED_IP)->getStatusCode());

        $this->travel(61)->minutes();

        $this->assertPassedThrough($this->handle('/login', self::BLOCKED_IP));
    }

    #[Test]
    public function lifting_a_block_takes_effect_on_the_next_request(): void
    {
        $blocks = app(IpBlockService::class);
        $blocks->blockManually(self::BLOCKED_IP);
        $this->assertSame(403, $this->handle('/login', self::BLOCKED_IP)->getStatusCode());

        $blocks->lift(self::BLOCKED_IP);

        $this->assertPassedThrough($this->handle('/login', self::BLOCKED_IP));
    }

    #[Test]
    public function nothing_is_refused_while_enforcement_is_switched_off(): void
    {
        app(IpBlockService::class)->blockManually(self::BLOCKED_IP);
        config(['nntmux.ip_blocking.enabled' => false]);

        $this->assertPassedThrough($this->handle('/login', self::BLOCKED_IP));
    }

    #[Test]
    public function requests_are_let_through_when_the_block_list_cannot_be_read(): void
    {
        Schema::drop('blocked_ips');

        $this->assertPassedThrough($this->handle('/login', self::BLOCKED_IP));
    }

    #[Test]
    public function requests_are_let_through_when_the_block_list_cannot_be_read_and_logging_fails_too(): void
    {
        Schema::drop('blocked_ips');
        config(['app.debug' => true]);
        Log::shouldReceive('debug')->once()->andThrow(new RuntimeException('log channel unavailable'));

        $this->assertPassedThrough($this->handle('/login', self::BLOCKED_IP));
    }

    #[Test]
    public function the_login_page_is_refused_to_a_blocked_address_before_any_other_check_runs(): void
    {
        Http::fake();
        app(IpBlockService::class)->blockManually(self::BLOCKED_IP);

        $response = $this->withServerVariables(['REMOTE_ADDR' => self::BLOCKED_IP])->get('/login');

        $response->assertForbidden();
        $response->assertSee('Access from your network address is blocked');
        // BlockAbusiveServices would have looked the address up at ip-api.com had it run first.
        Http::assertNothingSent();
    }

    private function handle(string $path, string $ip): Response
    {
        $request = Request::create($path, 'GET', server: ['REMOTE_ADDR' => $ip]);

        return app(DenyBlockedIps::class)->handle($request, static fn (): Response => new Response('next'));
    }

    private function assertPassedThrough(Response $response): void
    {
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('next', $response->getContent());
    }
}
