<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Middleware\ContentSecurityPolicy;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Laravel\Horizon\Horizon;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicyTest extends TestCase
{
    private Container $originalContainer;

    private ?Application $originalFacadeApplication;

    private string $hotFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalContainer = Container::getInstance();
        $facadeApplication = Facade::getFacadeApplication();
        $this->originalFacadeApplication = $facadeApplication instanceof Application ? $facadeApplication : null;

        $application = new Application(dirname(__DIR__, 2));
        $application->instance('config', new Repository([
            'app' => ['env' => 'production'],
            'captcha' => [
                'provider' => null,
                'turnstile' => ['enabled' => false],
            ],
        ]));
        $application->instance(Vite::class, new Vite);

        Container::setInstance($application);
        Facade::setFacadeApplication($application);
        Facade::clearResolvedInstances();

        $this->hotFile = sys_get_temp_dir().'/nntmux-csp-hot-'.bin2hex(random_bytes(8));
        app(Vite::class)->useHotFile($this->hotFile);
    }

    protected function tearDown(): void
    {
        if (is_file($this->hotFile)) {
            unlink($this->hotFile);
        }

        Horizon::$nonceAttribute = '';
        Facade::clearResolvedInstances();
        Container::setInstance($this->originalContainer);
        Facade::setFacadeApplication($this->originalFacadeApplication);

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

    public function test_local_vite_hot_reload_sources_are_added_to_the_csp(): void
    {
        config()->set('app.env', 'local');
        file_put_contents($this->hotFile, 'http://localhost:5173');

        $response = (new ContentSecurityPolicy)->handle(
            Request::create('/'),
            static fn (): Response => new Response,
        );

        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("style-src-elem 'self' 'unsafe-inline'", $csp);
        $this->assertStringContainsString('https://cdn.tiny.cloud http://localhost:5173', $csp);
        $this->assertStringContainsString('https://sp.tinymce.com http://localhost:5173 ws://localhost:5173', $csp);
    }

    public function test_vite_hot_reload_sources_are_not_added_outside_local_environment(): void
    {
        file_put_contents($this->hotFile, 'http://localhost:5173');

        $response = (new ContentSecurityPolicy)->handle(
            Request::create('/'),
            static fn (): Response => new Response,
        );

        $this->assertStringNotContainsString(
            'http://localhost:5173',
            (string) $response->headers->get('Content-Security-Policy'),
        );
    }

    public function test_non_loopback_vite_hot_reload_sources_are_rejected(): void
    {
        config()->set('app.env', 'local');
        file_put_contents($this->hotFile, 'https://assets.example.com:5173');

        $response = (new ContentSecurityPolicy)->handle(
            Request::create('/'),
            static fn (): Response => new Response,
        );

        $this->assertStringNotContainsString(
            'https://assets.example.com:5173',
            (string) $response->headers->get('Content-Security-Policy'),
        );
    }

    public function test_cross_origin_grafana_is_allowed_as_a_frame_source(): void
    {
        config()->set('app.url', 'https://nntmux.example');
        config()->set('monitoring.grafana.url', 'https://grafana.example:3000/grafana');

        $response = (new ContentSecurityPolicy)->handle(
            Request::create('/admin/monitoring'),
            static fn (): Response => new Response,
        );

        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('data: blob: https://grafana.example:3000;', $csp);
        $this->assertStringContainsString('https://cdn.tiny.cloud blob: https://grafana.example:3000;', $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
    }

    public function test_same_origin_grafana_path_adds_no_frame_source(): void
    {
        config()->set('app.url', 'https://nntmux.example');
        config()->set('monitoring.grafana.url', '/grafana');

        $response = (new ContentSecurityPolicy)->handle(
            Request::create('/admin/monitoring'),
            static fn (): Response => new Response,
        );

        $this->assertStringContainsString(
            "frame-src 'self' https://www.google.com https://www.gstatic.com https://challenges.cloudflare.com https://cdn.tiny.cloud data: blob:;",
            (string) $response->headers->get('Content-Security-Policy'),
        );
    }
}
