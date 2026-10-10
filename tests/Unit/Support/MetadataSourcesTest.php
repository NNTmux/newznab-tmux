<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\MetadataSources;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MetadataSourcesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'tmdb.api_key' => 'tmdb-key',
            'nntmux_api.omdb_api_key' => 'omdb-key',
            'tvdb.api_key' => 'tvdb-key',
            'tvdb.user_pin' => '',
            'nntmux_api.fanarttv_api_key' => 'fanart-key',
            'nntmux_api.trakttv_api_key' => 'trakt-key',
            'nntmux_api.imdb_scraper_enabled' => true,
            'nntmux_api.imdbapi_dev_enabled' => true,
            'igdb.credentials.client_id' => 'client-id',
            'igdb.credentials.client_secret' => 'client-secret',
            'nntmux_api.isbndb_api_key' => 'isbndb-key',
        ]);

        Cache::flush();
    }

    public function test_all_sources_available_when_configured(): void
    {
        foreach (MetadataSources::all() as $source) {
            $this->assertTrue($source['available'], $source['label']);
            $this->assertNull($source['reason']);
        }

        $this->assertSame([], MetadataSources::summaryLines());
    }

    public function test_reports_missing_keys_and_disabled_switches(): void
    {
        config([
            'nntmux_api.trakttv_api_key' => '  ',
            'nntmux_api.fanarttv_api_key' => null,
            'tvdb.user_pin' => null,
            'igdb.credentials.client_secret' => '',
            'nntmux_api.imdb_scraper_enabled' => false,
            'nntmux_api.imdbapi_dev_enabled' => 'false',
        ]);

        $this->assertFalse(MetadataSources::isAvailable(MetadataSources::TRAKT));
        $this->assertSame('no API key (TRAKTTV_APIKEY)', MetadataSources::unavailableReason(MetadataSources::TRAKT));
        $this->assertSame('no API key (FANARTTV_APIKEY)', MetadataSources::unavailableReason(MetadataSources::FANARTTV));
        $this->assertSame('no user PIN (TVDB_PIN)', MetadataSources::unavailableReason(MetadataSources::TVDB));
        $this->assertSame('no API key (TWITCH_CLIENT_SECRET)', MetadataSources::unavailableReason(MetadataSources::IGDB));
        $this->assertSame('disabled by IMDB_SCRAPER_ENABLED', MetadataSources::unavailableReason(MetadataSources::IMDB_SCRAPER));
        $this->assertSame('disabled by IMDBAPI_DEV_ENABLED', MetadataSources::unavailableReason(MetadataSources::IMDBAPI_DEV));
        $this->assertTrue(MetadataSources::isAvailable(MetadataSources::TMDB));

        $this->assertSame([
            'Metadata source TVDB: no user PIN (TVDB_PIN), lookups disabled',
            'Metadata source Fanart.tv: no API key (FANARTTV_APIKEY), lookups disabled',
            'Metadata source Trakt: no API key (TRAKTTV_APIKEY), lookups disabled',
            'Metadata source IMDb scraping: disabled by IMDB_SCRAPER_ENABLED, lookups disabled',
            'Metadata source imdbapi.dev: disabled by IMDBAPI_DEV_ENABLED, lookups disabled',
            'Metadata source IGDB: no API key (TWITCH_CLIENT_SECRET), lookups disabled',
        ], MetadataSources::summaryLines());
    }

    public function test_daily_summary_logs_once_per_day(): void
    {
        config(['nntmux_api.trakttv_api_key' => '']);
        Log::spy();

        $this->travelTo(now()->setTime(0, 5));
        $this->assertTrue(MetadataSources::logDailySummary());
        $this->assertFalse(MetadataSources::logDailySummary());
        $this->assertFalse(MetadataSources::logDailySummary());

        Log::shouldHaveReceived('info')
            ->with('Metadata source Trakt: no API key (TRAKTTV_APIKEY), lookups disabled')
            ->once();

        $this->travel(1)->days();
        $this->assertTrue(MetadataSources::logDailySummary());

        Log::shouldHaveReceived('info')->twice();
    }

    public function test_startup_summary_always_logs_and_covers_the_day(): void
    {
        config(['tmdb.api_key' => '']);
        Log::spy();

        $lines = MetadataSources::logSummary();
        MetadataSources::logSummary();

        $this->assertSame(['Metadata source TMDB: no API key (TMDB_APIKEY), lookups disabled'], $lines);
        $this->assertFalse(MetadataSources::logDailySummary());
        Log::shouldHaveReceived('info')->twice();
    }
}
