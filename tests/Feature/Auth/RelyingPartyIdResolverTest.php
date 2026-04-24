<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Support\Passkeys\RelyingPartyIdResolver;
use Illuminate\Http\Request;
use Tests\TestCase;

class RelyingPartyIdResolverTest extends TestCase
{
    public function test_prefers_forwarded_host_for_proxied_requests(): void
    {
        config()->set('passkeys.relying_party.id', 'example.com');

        $request = Request::create('http://127.0.0.1/passkeys/authentication-options', 'GET', [], [], [], [
            'HTTP_X_FORWARDED_HOST' => 'auth.example.com',
        ]);

        $this->assertSame('auth.example.com', RelyingPartyIdResolver::resolve($request));
    }

    public function test_prefers_configured_domain_over_localhost_request_host(): void
    {
        config()->set('passkeys.relying_party.id', 'example.com');

        $request = Request::create('http://localhost/passkeys/authentication-options', 'GET');

        $this->assertSame('example.com', RelyingPartyIdResolver::resolve($request));
    }

    public function test_rejects_ip_hosts_and_falls_back_to_localhost(): void
    {
        config()->set('passkeys.relying_party.id', '127.0.0.1');

        $request = Request::create('http://127.0.0.1/passkeys/authentication-options', 'GET');

        $this->assertSame('localhost', RelyingPartyIdResolver::resolve($request));
    }

    public function test_allows_localhost_for_development(): void
    {
        config()->set('passkeys.relying_party.id', 'localhost');

        $request = Request::create('http://localhost/passkeys/authentication-options', 'GET');

        $this->assertSame('localhost', RelyingPartyIdResolver::resolve($request));
    }
}
