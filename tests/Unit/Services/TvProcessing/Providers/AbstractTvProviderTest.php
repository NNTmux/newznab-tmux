<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TvProcessing\Providers;

use App\Services\TvProcessing\Providers\TraktProvider;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\ImdbScraperTestCase;

class AbstractTvProviderTest extends ImdbScraperTestCase
{
    #[Test]
    public function it_accepts_numeric_values_in_check_match(): void
    {
        Cache::flush();

        $provider = $this->makeProvider();

        $this->assertSame(100.0, $provider->checkMatch('2024', 2024, 80));
        $this->assertSame(0.0, $provider->checkMatch('2024', 2023, 100));
    }

    #[Test]
    public function it_returns_zero_for_non_comparable_values(): void
    {
        Cache::flush();

        $provider = $this->makeProvider();

        $this->assertSame(0.0, $provider->checkMatch('Example Show', ['bad'], 75));
    }

    #[Test]
    public function it_skips_imdb_updates_when_the_new_value_is_empty(): void
    {
        Cache::flush();

        $sql = $this->buildUpdateQuery([
            'country' => '',
            'tvdb' => 394553,
            'trakt' => 0,
            'tvrage' => 0,
            'tvmaze' => 0,
            'imdb' => '',
            'tmdb' => 117643,
            'summary' => 'Summary',
            'publisher' => 'Nine Network',
            'localzone' => "''",
            'aliases' => '',
        ]);

        $this->assertStringContainsString('v.imdb = v.imdb', $sql);
        $this->assertStringNotContainsString('v.imdb = IF(v.imdb = 0, , v.imdb)', $sql);
        $this->assertStringContainsString("tvi.localzone = IF(tvi.localzone = '', '', tvi.localzone)", $sql);
    }

    #[Test]
    public function it_normalizes_tt_prefixed_imdb_ids_in_update_queries(): void
    {
        Cache::flush();

        $sql = $this->buildUpdateQuery([
            'country' => 'AU',
            'tvdb' => 394553,
            'trakt' => 0,
            'tvrage' => 0,
            'tvmaze' => 0,
            'imdb' => 'tt1176432',
            'tmdb' => 117643,
            'summary' => 'Summary',
            'publisher' => 'Nine Network',
            'localzone' => '',
            'aliases' => '',
        ]);

        $this->assertStringContainsString("v.imdb = IF(v.imdb IN ('', '0'), '1176432', v.imdb)", $sql);
    }

    private function makeProvider(): TraktProvider
    {
        return new TraktProvider;
    }

    /**
     * @param  array<string, mixed>  $show
     */
    private function buildUpdateQuery(array $show): string
    {
        $provider = $this->makeProvider();
        $method = new \ReflectionMethod($provider, 'buildUpdateQuery');

        return $method->invoke($provider, 123, $show);
    }
}
