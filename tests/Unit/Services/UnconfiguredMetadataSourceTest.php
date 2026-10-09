<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\FanartTvService;
use App\Services\TmdbClient;
use App\Services\TraktService;
use App\Services\TvProcessing\Providers\TraktProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class UnconfiguredMetadataSourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'tmdb.api_key' => '',
            'nntmux_api.fanarttv_api_key' => '',
            'nntmux_api.trakttv_api_key' => '',
        ]);

        Cache::flush();
        Http::fake();
        Log::spy();
    }

    public function test_clients_without_keys_make_no_requests_and_log_nothing(): void
    {
        $trakt = new TraktService;
        $tmdb = new TmdbClient;
        $fanart = new FanartTvService;

        $this->assertFalse($trakt->isConfigured());
        $this->assertFalse($tmdb->isConfigured());
        $this->assertFalse($fanart->isConfigured());

        $this->assertNull($trakt->searchShows('Breaking Bad'));
        $this->assertNull($tmdb->searchTv('Breaking Bad'));
        $this->assertNull($fanart->getMovieProperties('0137523'));

        Http::assertNothingSent();
        Log::shouldNotHaveReceived('debug');
        Log::shouldNotHaveReceived('warning');
    }

    public function test_trakt_provider_skips_lookups_without_key(): void
    {
        $provider = new TraktProvider;

        $started = microtime(true);
        $this->assertFalse($provider->getShowInfo('Breaking Bad'));
        $this->assertFalse($provider->getEpisodeInfo(1390, 1, 1));
        $this->assertFalse($provider->getEpisodeInfo(1390, -1, -1, 5));

        $this->assertLessThan(1.0, microtime(true) - $started);
        Http::assertNothingSent();
        Log::shouldNotHaveReceived('debug');
    }
}
