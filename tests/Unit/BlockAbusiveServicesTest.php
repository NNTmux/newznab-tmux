<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Middleware\BlockAbusiveServices;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PDO;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class BlockAbusiveServicesTest extends TestCase
{
    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-block-abusive-services-test.sqlite';

        $this->originalEnvironment = [
            'APP_ENV' => getenv('APP_ENV'),
            'DB_CONNECTION' => getenv('DB_CONNECTION'),
            'DB_DATABASE' => getenv('DB_DATABASE'),
        ];

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        $pdo = new PDO('sqlite:'.$this->databasePath);
        $pdo->exec('CREATE TABLE settings (name VARCHAR PRIMARY KEY, value TEXT NULL)');
        $pdo->exec("INSERT INTO settings (name, value) VALUES
            ('categorizeforeign', '0'),
            ('catwebdl', '0'),
            ('innerfileblacklist', '')");

        $this->setEnvironmentValue('APP_ENV', 'testing');
        $this->setEnvironmentValue('DB_CONNECTION', 'sqlite');
        $this->setEnvironmentValue('DB_DATABASE', $this->databasePath);

        $app = require __DIR__.'/../../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function tearDown(): void
    {
        if ($this->databasePath !== '' && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();

        foreach ($this->originalEnvironment as $key => $value) {
            $this->setEnvironmentValue($key, $value === false ? null : $value);
        }
    }

    public function test_disabled_proxy_indexer_app_block_allows_configured_user_agent_on_indexer_endpoint(): void
    {
        config()->set('nntmux.block_proxy_indexer_apps', false);
        config()->set('nntmux.block_proxy_indexer_app_user_agents', 'Prowlarr/,NZBHydra2');

        $response = $this->handleRequest('/api/v1/api?t=search&q=linux', 'Prowlarr/2.0.0');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function test_enabled_proxy_indexer_app_block_denies_configured_user_agent_on_newznab_endpoints(): void
    {
        config()->set('nntmux.block_proxy_indexer_apps', true);
        config()->set('nntmux.block_proxy_indexer_app_user_agents', 'Prowlarr/,NZBHydra2');

        foreach ([
            '/api/v1/api?t=caps',
            '/api/v1/api?t=search&q=linux',
            '/api/v1/api?t=get&id=release-guid',
        ] as $uri) {
            $response = $this->handleRequest($uri, 'Prowlarr/2.0.0');

            $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $uri);
            $this->assertStringContainsString('Proxy indexer app access is not allowed', (string) $response->getContent());
        }
    }

    public function test_enabled_proxy_indexer_app_block_denies_configured_user_agent_on_v2_endpoints(): void
    {
        config()->set('nntmux.block_proxy_indexer_apps', true);
        config()->set('nntmux.block_proxy_indexer_app_user_agents', 'Prowlarr/,NZBHydra2');

        foreach ([
            '/api/v2/capabilities',
            '/api/v2/search?q=linux',
            '/api/v2/getnzb?id=release-guid',
        ] as $uri) {
            $response = $this->handleRequest($uri, 'NZBHydra2 8.3.0');

            $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $uri);
        }
    }

    public function test_enabled_proxy_indexer_app_block_denies_configured_user_agent_on_rss_feed_endpoints(): void
    {
        config()->set('nntmux.block_proxy_indexer_apps', true);
        config()->set('nntmux.block_proxy_indexer_app_user_agents', 'Prowlarr/,NZBHydra2');

        foreach ([
            '/rss/full-feed?api_token=token',
            '/rss/category?api_token=token&t=2000',
        ] as $uri) {
            $response = $this->handleRequest($uri, 'Prowlarr/2.0.0');

            $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $uri);
        }
    }

    public function test_enabled_proxy_indexer_app_block_allows_redirected_downloader_user_agent_on_same_download_url(): void
    {
        config()->set('nntmux.block_proxy_indexer_apps', true);
        config()->set('nntmux.block_proxy_indexer_app_user_agents', 'Prowlarr/,NZBHydra2');

        $response = $this->handleRequest('/api/v1/api?t=get&id=release-guid', 'SABnzbd/4.3.3');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function test_enabled_proxy_indexer_app_block_allows_configured_user_agent_on_unrelated_routes(): void
    {
        config()->set('nntmux.block_proxy_indexer_apps', true);
        config()->set('nntmux.block_proxy_indexer_app_user_agents', 'Prowlarr/,NZBHydra2');

        foreach ([
            '/api/inform/release',
            '/api/release/123/mediainfo',
            '/rss/health',
        ] as $uri) {
            $response = $this->handleRequest($uri, 'Prowlarr/2.0.0');

            $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), $uri);
        }
    }

    public function test_existing_abusive_user_agent_block_still_applies(): void
    {
        config()->set('nntmux.block_proxy_indexer_apps', false);

        $response = $this->handleRequest('/api/v1/api?t=caps', 'AIOStreams/1.0');

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertStringContainsString('Streaming services are not allowed', (string) $response->getContent());
    }

    public function test_block_logs_redact_sensitive_query_parameters(): void
    {
        Log::spy();
        config()->set('nntmux.block_proxy_indexer_apps', true);
        config()->set('nntmux.block_proxy_indexer_app_user_agents', 'Prowlarr/,NZBHydra2');

        $response = $this->handleRequest(
            '/api/v1/api?t=search&apikey=secret-api-key&api_token=secret-token&passkey=secret-passkey&q=linux',
            'Prowlarr/2.0.0'
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        Log::shouldHaveReceived('warning')
            ->with('Blocked proxy indexer app request', \Mockery::on(function (mixed $context): bool {
                if (! is_array($context)) {
                    return false;
                }

                $uri = (string) ($context['uri'] ?? '');

                return str_contains($uri, 'apikey=%5Bredacted%5D')
                    && str_contains($uri, 'api_token=%5Bredacted%5D')
                    && str_contains($uri, 'passkey=%5Bredacted%5D')
                    && str_contains($uri, 'q=linux')
                    && ! str_contains($uri, 'secret-api-key')
                    && ! str_contains($uri, 'secret-token')
                    && ! str_contains($uri, 'secret-passkey');
            }))
            ->once();
    }

    private function setEnvironmentValue(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    private function handleRequest(string $uri, string $userAgent): Response
    {
        $request = Request::create($uri, 'GET', server: [
            'HTTP_USER_AGENT' => $userAgent,
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        return app(BlockAbusiveServices::class)->handle(
            $request,
            static fn (): Response => response()->json(['ok' => true])
        );
    }
}
