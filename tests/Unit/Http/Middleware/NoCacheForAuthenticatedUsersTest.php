<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\NoCacheForAuthenticatedUsers;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class NoCacheForAuthenticatedUsersTest extends TestCase
{
    public function test_it_adds_no_cache_headers_for_guest_auth_routes(): void
    {
        Auth::shouldReceive('check')
            ->once()
            ->andReturn(false);

        $response = $this->handleRequestForRoute('register');

        $this->assertResponseIsNoStore($response);
    }

    public function test_it_does_not_add_no_cache_headers_for_other_guest_routes(): void
    {
        Auth::shouldReceive('check')
            ->once()
            ->andReturn(false);

        $response = $this->handleRequestForRoute('home');

        $this->assertStringNotContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertNull($response->headers->get('CDN-Cache-Control'));
        $this->assertNull($response->headers->get('Cloudflare-CDN-Cache-Control'));
        $this->assertNull($response->headers->get('Vary'));
    }

    public function test_it_keeps_authenticated_pages_uncached(): void
    {
        Auth::shouldReceive('check')
            ->once()
            ->andReturn(true);

        $response = $this->handleRequestForRoute('home');

        $this->assertResponseIsNoStore($response);
    }

    private function handleRequestForRoute(string $routeName): Response
    {
        $path = $routeName === 'home' ? '/' : '/'.$routeName;
        $request = Request::create($path, 'GET');

        $route = new Route(['GET'], ltrim($path, '/'), static fn (): Response => new Response('OK'));
        $route->name($routeName);
        $request->setRouteResolver(static fn (): Route => $route);

        return (new NoCacheForAuthenticatedUsers)->handle(
            $request,
            static fn (): Response => new Response('OK')
        );
    }

    private function assertResponseIsNoStore(Response $response): void
    {
        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertStringContainsString('s-maxage=0', $cacheControl);
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
        $this->assertSame('Thu, 01 Jan 1970 00:00:00 GMT', $response->headers->get('Expires'));
        $this->assertSame('no-store', $response->headers->get('CDN-Cache-Control'));
        $this->assertSame('no-store', $response->headers->get('Cloudflare-CDN-Cache-Control'));
        $this->assertStringContainsString('Cookie', (string) $response->headers->get('Vary'));
    }
}
