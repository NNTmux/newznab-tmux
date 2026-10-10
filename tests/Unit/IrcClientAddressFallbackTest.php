<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\IRCClient;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IrcClientAddressFallbackTest extends TestCase
{
    #[Test]
    public function it_tries_the_other_addresses_of_a_host_when_the_first_one_is_dead(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $this->assertNotFalse($server, (string) $error);
        $port = (int) substr(strrchr((string) stream_socket_get_name($server, false), ':'), 1);

        // `irc.invalid` never resolves, so the first attempt fails like a dead round-robin entry would.
        $client = new class extends IRCClient
        {
            /** @var list<string> */
            public array $addresses = [];

            protected function _resolveAddresses(string $host): array
            {
                return $this->addresses;
            }
        };
        $client->addresses = ['127.0.0.1'];
        $client->setConnectionRetries(0);
        $client->setConnectionTimeout(2);

        ob_start();
        $connected = $client->connect('irc.invalid', $port);
        ob_end_clean();

        $this->assertTrue($connected);
        fclose($server);
    }

    #[Test]
    public function it_connects_to_an_ipv6_address_literal(): void
    {
        $server = @stream_socket_server('tcp://[::1]:0', $errno, $error);
        if ($server === false) {
            $this->markTestSkipped('IPv6 loopback is not available.');
        }
        $port = (int) substr(strrchr((string) stream_socket_get_name($server, false), ':'), 1);

        $client = new class extends IRCClient
        {
            protected function _resolveAddresses(string $host): array
            {
                return ['::1'];
            }
        };
        $client->setConnectionRetries(0);
        $client->setConnectionTimeout(2);

        ob_start();
        $connected = $client->connect('irc.invalid', $port);
        ob_end_clean();

        $this->assertTrue($connected);
        fclose($server);
    }

    #[Test]
    public function the_fallback_never_waits_longer_than_the_connection_timeout(): void
    {
        $client = new class extends IRCClient
        {
            protected function _resolveAddresses(string $host): array
            {
                // Non-routable addresses (TEST-NET-1) drop packets, so each attempt runs to its timeout.
                return ['192.0.2.1', '192.0.2.2', '192.0.2.3', '192.0.2.4'];
            }
        };
        $client->setConnectionRetries(0);
        $client->setReConnectDelay(0);
        $client->setConnectionTimeout(2);

        $start = microtime(true);
        ob_start();
        $connected = $client->connect('irc.invalid', 6667);
        ob_end_clean();

        $this->assertFalse($connected);
        $this->assertLessThan(6.0, microtime(true) - $start);
    }

    #[Test]
    public function it_still_fails_when_no_address_answers(): void
    {
        $client = new class extends IRCClient
        {
            protected function _resolveAddresses(string $host): array
            {
                return [];
            }
        };
        $client->setConnectionRetries(0);
        $client->setConnectionTimeout(2);

        ob_start();
        $connected = $client->connect('irc.invalid', 6667);
        ob_end_clean();

        $this->assertFalse($connected);
    }
}
