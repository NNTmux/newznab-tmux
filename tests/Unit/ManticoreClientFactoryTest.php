<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Search\Support\ManticoreClientFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ManticoreClientFactoryTest extends TestCase
{
    #[Test]
    public function it_builds_plain_http_connection_config_without_auth_by_default(): void
    {
        $config = ManticoreClientFactory::clientConfig([
            'host' => 'manticore',
            'port' => 9308,
        ]);

        $this->assertSame('manticore', $config['host']);
        $this->assertSame(9308, $config['port']);
        $this->assertArrayNotHasKey('username', $config);
        $this->assertArrayNotHasKey('password', $config);
        $this->assertArrayNotHasKey('headers', $config);
    }

    #[Test]
    public function it_adds_basic_auth_when_username_and_password_are_configured(): void
    {
        $config = ManticoreClientFactory::clientConfig([
            'host' => 'manticore',
            'port' => 9308,
            'username' => 'search_user',
            'password' => 'secret',
        ]);

        $this->assertSame('search_user', $config['username']);
        $this->assertSame('secret', $config['password']);
    }

    #[Test]
    public function bearer_token_auth_takes_precedence_over_basic_auth(): void
    {
        $config = ManticoreClientFactory::clientConfig([
            'host' => 'manticore',
            'port' => 9308,
            'username' => 'search_user',
            'password' => 'secret',
            'token' => 'token-value',
        ]);

        $this->assertArrayNotHasKey('username', $config);
        $this->assertArrayNotHasKey('password', $config);
        $this->assertSame(['Authorization: Bearer token-value'], $config['headers']);
    }

    #[Test]
    public function it_parses_multiple_hosts_and_https_transport(): void
    {
        $config = ManticoreClientFactory::clientConfig([
            'host' => 'fallback',
            'port' => 9308,
            'scheme' => 'http',
            'hosts' => 'manticore-a:9308,https://manticore-b:9443/search',
            'retries' => 3,
            'token' => 'token-value',
        ]);

        $this->assertSame(3, $config['retries']);
        $this->assertCount(2, $config['connections']);
        $this->assertSame('manticore-a', $config['connections'][0]['host']);
        $this->assertSame(9308, $config['connections'][0]['port']);
        $this->assertSame('manticore-b', $config['connections'][1]['host']);
        $this->assertSame(9443, $config['connections'][1]['port']);
        $this->assertSame('Https', $config['connections'][1]['transport']);
        $this->assertSame('/search', $config['connections'][1]['path']);
        $this->assertSame(['Authorization: Bearer token-value'], $config['connections'][1]['headers']);
    }

    #[Test]
    public function it_builds_guzzle_options_for_basic_or_bearer_auth(): void
    {
        $basic = ManticoreClientFactory::guzzleOptions([
            'username' => 'search_user',
            'password' => 'secret',
        ], [
            'timeout' => 10,
        ]);

        $this->assertSame(['search_user', 'secret'], $basic['auth']);
        $this->assertSame(10, $basic['timeout']);

        $bearer = ManticoreClientFactory::guzzleOptions([
            'username' => 'search_user',
            'password' => 'secret',
            'token' => 'token-value',
        ], [
            'headers' => ['Content-Type' => 'text/plain'],
        ]);

        $this->assertArrayNotHasKey('auth', $bearer);
        $this->assertSame('Bearer token-value', $bearer['headers']['Authorization']);
        $this->assertSame('text/plain', $bearer['headers']['Content-Type']);
    }
}
