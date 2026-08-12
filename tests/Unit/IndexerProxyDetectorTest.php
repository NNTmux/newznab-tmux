<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Security\IndexerProxyDetector;
use App\Services\Security\ProxyRequestContext;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Console\Kernel;
use PDO;
use Tests\TestCase;

class IndexerProxyDetectorTest extends TestCase
{
    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    private IndexerProxyDetector $detector;

    public function createApplication()
    {
        $this->databasePath = $this->makeTempPath('nntmux-indexer-proxy-detector-test', '.sqlite');

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

    protected function setUp(): void
    {
        parent::setUp();

        // Each test gets an isolated in-memory cache so sliding-window state
        // never leaks between cases.
        $this->detector = new IndexerProxyDetector(new Repository(new ArrayStore));

        $this->configureDetector();
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

    public function test_disabled_detector_never_blocks_even_with_strong_signals(): void
    {
        $this->configureDetector(['enabled' => false]);

        $this->detector->recordSearch($this->searchContext(
            apiToken: 'tok',
            userAgent: 'NZBHydra2 8.3.0',
            clientIp: '10.0.0.1',
        ));

        $verdict = $this->detector->analyze($this->downloadContext(
            apiToken: 'tok',
            userAgent: 'NZBHydra2 8.3.0',
            clientIp: '10.0.0.1',
            referer: 'http://hydra.local:5076/getnzb/api',
        ));

        $this->assertFalse($verdict->shouldBlock);
        $this->assertSame(0, $verdict->score);
        $this->assertSame([], $verdict->reasons);
    }

    public function test_referer_matching_indexer_host_blocks(): void
    {
        // Isolate the Referer signal: no api_token (skips UA-pair + ratio) and a
        // fresh IP (skips IP correlation).
        $this->configureDetector(['threshold' => 1]);

        $verdict = $this->detector->analyze($this->downloadContext(
            apiToken: null,
            userAgent: 'SABnzbd/4.3.3',
            clientIp: '10.0.0.10',
            referer: 'http://prowlarr.local:9696/1/api',
        ));

        $this->assertTrue($verdict->shouldBlock);
        $this->assertSame(30, $verdict->score);
        $this->assertSame(['referer' => 30], $verdict->reasons);
    }

    public function test_referer_absent_does_not_block(): void
    {
        // Downloaders send no Referer — the redirected-grab shape.
        $verdict = $this->detector->analyze($this->downloadContext(
            apiToken: null,
            userAgent: 'SABnzbd/4.3.3',
            clientIp: '10.0.0.11',
            referer: null,
        ));

        $this->assertFalse($verdict->shouldBlock);
        $this->assertSame(0, $verdict->score);
        $this->assertSame([], $verdict->reasons);
    }

    public function test_same_ua_on_search_then_download_blocks(): void
    {
        // Isolate the UA-pair signal: search + download share the same token and
        // UA, but the download comes from a different IP so IP correlation stays
        // silent, and one search is below the ratio minimum.
        $this->configureDetector(['threshold' => 1]);

        $this->detector->recordSearch($this->searchContext(
            apiToken: 'tok',
            userAgent: 'NZBHydra2 8.3.0',
            clientIp: '10.0.0.1',
        ));

        $verdict = $this->detector->analyze($this->downloadContext(
            apiToken: 'tok',
            userAgent: 'NZBHydra2 8.3.0',
            clientIp: '10.0.0.2',
            referer: null,
        ));

        $this->assertTrue($verdict->shouldBlock);
        $this->assertSame(25, $verdict->score);
        $this->assertSame(['ua_pair' => 25], $verdict->reasons);
    }

    public function test_different_ua_on_search_then_download_allows(): void
    {
        // Redirected grab: Prowlarr searches, SABnzbd downloads from another IP.
        $this->detector->recordSearch($this->searchContext(
            apiToken: 'tok',
            userAgent: 'Prowlarr/2.0.0',
            clientIp: '10.0.0.1',
        ));

        $verdict = $this->detector->analyze($this->downloadContext(
            apiToken: 'tok',
            userAgent: 'SABnzbd/4.3.3',
            clientIp: '10.0.0.2',
            referer: null,
        ));

        $this->assertFalse($verdict->shouldBlock);
        $this->assertSame(0, $verdict->score);
        $this->assertSame([], $verdict->reasons);
    }

    public function test_high_download_to_search_ratio_blocks(): void
    {
        // Isolate the ratio signal: keep min-searches small, search/download with a
        // different UA and IP from the analyzed download so only the ratio fires.
        $this->configureDetector(['threshold' => 1, 'min_searches' => 2]);

        $this->detector->recordSearch($this->searchContext('tok', 'Prowlarr/2.0.0', '10.0.0.1'));
        $this->detector->recordSearch($this->searchContext('tok', 'Prowlarr/2.0.0', '10.0.0.1'));
        $this->detector->recordDownload($this->downloadContext('tok', 'Prowlarr/2.0.0', '10.0.0.1'));
        $this->detector->recordDownload($this->downloadContext('tok', 'Prowlarr/2.0.0', '10.0.0.1'));

        $verdict = $this->detector->analyze($this->downloadContext(
            apiToken: 'tok',
            userAgent: 'SABnzbd/4.3.3',
            clientIp: '10.0.0.9',
            referer: null,
        ));

        $this->assertTrue($verdict->shouldBlock);
        $this->assertSame(25, $verdict->score);
        $this->assertSame(['download_ratio' => 25], $verdict->reasons);
    }

    public function test_low_ratio_allows(): void
    {
        // Many searches, one download — the human shape.
        $this->configureDetector(['min_searches' => 2]);

        for ($i = 0; $i < 5; $i++) {
            $this->detector->recordSearch($this->searchContext('tok', 'Prowlarr/2.0.0', '10.0.0.1'));
        }
        $this->detector->recordDownload($this->downloadContext('tok', 'Prowlarr/2.0.0', '10.0.0.1'));

        $verdict = $this->detector->analyze($this->downloadContext(
            apiToken: 'tok',
            userAgent: 'SABnzbd/4.3.3',
            clientIp: '10.0.0.9',
            referer: null,
        ));

        $this->assertFalse($verdict->shouldBlock);
        $this->assertSame(0, $verdict->score);
        $this->assertSame([], $verdict->reasons);
    }

    public function test_ip_correlation_with_indexer_ua_blocks(): void
    {
        // Isolate the IP-correlation signal: no api_token (skips UA-pair + ratio),
        // but the same IP just searched with the same indexer UA.
        $this->configureDetector(['threshold' => 1]);

        $this->detector->recordSearch($this->searchContext(
            apiToken: null,
            userAgent: 'NZBHydra2 8.3.0',
            clientIp: '10.0.0.5',
        ));

        $verdict = $this->detector->analyze($this->downloadContext(
            apiToken: null,
            userAgent: 'NZBHydra2 8.3.0',
            clientIp: '10.0.0.5',
            referer: null,
        ));

        $this->assertTrue($verdict->shouldBlock);
        $this->assertSame(20, $verdict->score);
        $this->assertSame(['ip_correlation' => 20], $verdict->reasons);
    }

    public function test_score_below_threshold_allows(): void
    {
        // A single Referer signal (30) is below the default threshold of 50.
        $verdict = $this->detector->analyze($this->downloadContext(
            apiToken: null,
            userAgent: 'SABnzbd/4.3.3',
            clientIp: '10.0.0.12',
            referer: 'http://hydra.local:5076/getnzb/api',
        ));

        $this->assertFalse($verdict->shouldBlock);
        $this->assertSame(30, $verdict->score);
        $this->assertSame(['referer' => 30], $verdict->reasons);
    }

    public function test_search_recording_feeds_subsequent_download_analysis(): void
    {
        // Realistic NZBHydra2 direct fetch: one recorded search lets the later
        // download correlate on both UA-pair and IP, and the leaked Referer pushes
        // the combined score over the default threshold.
        $this->detector->recordSearch($this->searchContext(
            apiToken: 'tok',
            userAgent: 'NZBHydra2 8.3.0',
            clientIp: '10.0.0.1',
        ));

        $verdict = $this->detector->analyze($this->downloadContext(
            apiToken: 'tok',
            userAgent: 'NZBHydra2 8.3.0',
            clientIp: '10.0.0.1',
            referer: 'http://hydra.local:5076/getnzb/api',
        ));

        $this->assertTrue($verdict->shouldBlock);
        $this->assertSame(75, $verdict->score);
        $this->assertSame([
            'referer' => 30,
            'ua_pair' => 25,
            'ip_correlation' => 20,
        ], $verdict->reasons);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function configureDetector(array $overrides = []): void
    {
        config()->set('nntmux.proxy_detection_enabled', $overrides['enabled'] ?? true);
        config()->set('nntmux.proxy_detection_threshold', $overrides['threshold'] ?? 50);
        config()->set('nntmux.proxy_detection_window_seconds', $overrides['window_seconds'] ?? 3600);
        config()->set('nntmux.proxy_detection_ratio_min', $overrides['ratio_min'] ?? 0.8);
        config()->set('nntmux.proxy_detection_min_searches', $overrides['min_searches'] ?? 20);
        config()->set(
            'nntmux.proxy_detection_indexer_referer_patterns',
            $overrides['referer_patterns'] ?? 'hydra,prowlarr',
        );
    }

    private function searchContext(?string $apiToken, string $userAgent, string $clientIp, ?string $referer = null): ProxyRequestContext
    {
        return new ProxyRequestContext(
            clientIp: $clientIp,
            userAgent: $userAgent,
            apiToken: $apiToken,
            referer: $referer,
            isDownload: false,
            isSearch: true,
        );
    }

    private function downloadContext(?string $apiToken, string $userAgent, string $clientIp, ?string $referer = null): ProxyRequestContext
    {
        return new ProxyRequestContext(
            clientIp: $clientIp,
            userAgent: $userAgent,
            apiToken: $apiToken,
            referer: $referer,
            isDownload: true,
            isSearch: false,
        );
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
}
