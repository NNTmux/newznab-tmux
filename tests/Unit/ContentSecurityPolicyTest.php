<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Middleware\ContentSecurityPolicy;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Laravel\Horizon\Horizon;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicyTest extends TestCase
{
    private ?Container $originalContainer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalContainer = Container::getInstance();

        $container = new Container;
        $container->instance('config', new Repository([
            'captcha' => [
                'provider' => null,
                'turnstile' => ['enabled' => false],
            ],
        ]));
        $container->instance(Vite::class, new Vite);

        Container::setInstance($container);
        Facade::setFacadeApplication($container);
    }

    protected function tearDown(): void
    {
        Horizon::$nonceAttribute = '';
        Container::setInstance($this->originalContainer);
        Facade::setFacadeApplication($this->originalContainer);

        parent::tearDown();
    }

    public function test_horizon_inline_assets_receive_the_csp_nonce(): void
    {
        $response = (new ContentSecurityPolicy)->handle(
            Request::create('/horizon'),
            static fn (): Response => new Response(Horizon::css().Horizon::js()),
        );

        $nonce = app('csp_nonce');

        $this->assertStringContainsString("script-src 'self' 'nonce-{$nonce}'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('https://fonts.bunny.net', (string) $response->headers->get('Content-Security-Policy'));
        $this->assertSame(4, substr_count((string) $response->getContent(), 'nonce="'.$nonce.'"'));
    }
}
