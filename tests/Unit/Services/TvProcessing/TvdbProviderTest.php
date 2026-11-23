<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TvProcessing;

use App\Services\TvProcessing\Providers\TvdbProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TvdbProviderTest extends TestCase
{
    private const int MONEY_HEIST = 327417;

    private const int BREAKING_BAD = 81189;

    protected function setUp(): void
    {
        parent::setUp();

        // Only TVDB is configured, so a show lookup must not reach TMDB or Trakt.
        config([
            'cache.default' => 'array',
            'tvdb.api_key' => 'tvdb-key',
            'tvdb.retry_times' => 1,
            'tmdb.api_key' => '',
            'nntmux_api.trakttv_api_key' => '',
        ]);
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_a_search_result_becomes_show_info_without_further_tvdb_requests(): void
    {
        $this->fakeTvdb(['api4.thetvdb.com/v4/search*' => Http::response(self::fixture('search_money_heist'))]);

        $show = (new TvdbProvider)->getShowInfo('Money Heist', 2017);

        $this->assertIsArray($show);
        $this->assertSame('Money Heist', $show['title']);
        $this->assertContains('La casa de papel', $show['aliases']);
        $this->assertSame(self::MONEY_HEIST, $show['tvdb']);
        $this->assertSame('6468322', $show['imdb']);
        $this->assertSame('Antena 3', $show['publisher']);
        $this->assertSame('esp', $show['country']);
        $this->assertSame('2017-05-02', $show['started']);
        $this->assertStringStartsWith('Eight thieves take hostages', $show['summary']);
        $this->assertSame('https://artworks.thetvdb.com/banners/series/327417/posters/5e824889ad675.jpg', $show['poster']);
        Http::assertSentCount(2);
        Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), '/search')
            && $request['query'] === 'Money Heist' && $request['type'] === 'series' && (int) $request['year'] === 2017
            && $request->hasHeader('Authorization', 'Bearer token'));
    }

    public function test_a_show_looked_up_by_id_keeps_its_english_title_country_and_poster(): void
    {
        $this->fakeTvdb(['api4.thetvdb.com/v4/series/327417/extended*' => Http::response(self::fixture('series_extended_money_heist'))]);
        $provider = new TvdbProvider;

        $show = $provider->formatShowInfo($provider->showById(self::MONEY_HEIST));

        $this->assertSame('Money Heist', $show['title']);
        $this->assertContains('La casa de papel', $show['aliases']);
        $this->assertSame('esp', $show['country']);
        $this->assertSame('Antena 3', $show['publisher']);
        $this->assertSame('6468322', $show['imdb']);
        $this->assertSame('https://artworks.thetvdb.com/banners/posters/5d30cc0b4a75d.jpg', $show['poster']);
        $this->assertStringStartsWith('Eight thieves take hostages', $show['summary']);
        Http::assertSentCount(2);
    }

    public function test_an_unknown_show_id_is_a_miss(): void
    {
        $this->fakeTvdb(['api4.thetvdb.com/v4/series/*' => Http::response(['status' => 'failure', 'message' => 'NotFoundException: not found', 'data' => null], 404)]);

        $this->assertNull((new TvdbProvider)->showById(999999999));
    }

    public function test_an_airdate_lookup_is_not_limited_to_the_specials(): void
    {
        $this->fakeTvdb(['api4.thetvdb.com/v4/series/81189/episodes/default*' => Http::response(self::fixture('series_episodes_breaking_bad'))]);
        $provider = $this->providerCapturingEpisodes();

        $provider->fetchEpisodesByAirdate(self::BREAKING_BAD, 5, '2008-01-20');

        // TVDB reads season=0 as "specials only" and then finds no regular episode for the date.
        Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), '/episodes/default')
            && $request['airDate'] === '2008-01-20' && ! \array_key_exists('season', $request->data()));
        $this->assertContains('S01E01', array_column($provider->storedEpisodes, 'se_complete'));
    }

    public function test_one_episode_is_fetched_by_season_and_number(): void
    {
        $this->fakeTvdb(['api4.thetvdb.com/v4/series/81189/episodes/default*' => Http::response(self::fixture('series_episodes_breaking_bad'))]);

        $episode = (new TvdbProvider)->fetchEpisode(self::BREAKING_BAD, 1, 2);

        $this->assertIsArray($episode);
        $this->assertSame("Cat's in the Bag...", $episode['title']);
        $this->assertSame('S01E02', $episode['se_complete']);
        $this->assertSame('2008-01-27', $episode['firstaired']);
        Http::assertSent(static fn (Request $request): bool => (int) ($request['season'] ?? -1) === 1 && (int) ($request['episodeNumber'] ?? -1) === 2);
    }

    public function test_episodes_come_from_the_original_language_when_there_is_no_english_listing(): void
    {
        $this->fakeTvdb([
            'api4.thetvdb.com/v4/series/327417/episodes/default/eng*' => Http::response(['status' => 'success', 'data' => ['episodes' => []], 'links' => ['next' => null]]),
            'api4.thetvdb.com/v4/series/327417/extended*' => Http::response(self::fixture('series_extended_money_heist')),
            'api4.thetvdb.com/v4/series/327417/episodes/default/spa*' => Http::response(self::fixture('series_episodes_breaking_bad')),
        ]);
        $provider = $this->providerCapturingEpisodes();

        $provider->fetchAllEpisodes(self::MONEY_HEIST, 5);

        $this->assertSame(['S00E01', 'S01E01', 'S01E02'], array_column($provider->storedEpisodes, 'se_complete'));
    }

    public function test_an_episode_list_is_read_across_pages(): void
    {
        $page = self::fixture('series_episodes_breaking_bad');
        $firstPage = $page;
        $firstPage['links']['next'] = 'https://api4.thetvdb.com/v4/series/81189/episodes/default/eng?page=1';
        $this->fakeTvdb(['api4.thetvdb.com/v4/series/81189/episodes/default/eng*' => Http::sequence()->push($firstPage)->push($page)]);
        $provider = $this->providerCapturingEpisodes();

        $provider->fetchAllEpisodes(self::BREAKING_BAD, 5);

        $this->assertCount(6, $provider->storedEpisodes);
        Http::assertSent(static fn (Request $request): bool => (int) ($request['page'] ?? -1) === 1);
    }

    public function test_it_makes_no_request_without_a_key(): void
    {
        config(['tvdb.api_key' => '']);
        Http::fake();
        $provider = new TvdbProvider;

        $this->assertFalse($provider->getShowInfo('Money Heist'));
        $this->assertFalse($provider->fetchEpisode(self::BREAKING_BAD, 1, 1));
        $this->assertNull($provider->showById(self::MONEY_HEIST));
        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $responses
     */
    private function fakeTvdb(array $responses): void
    {
        Http::fake(['api4.thetvdb.com/v4/login' => Http::response(['status' => 'success', 'data' => ['token' => 'token']])] + $responses);
    }

    /**
     * A provider that records the episodes it would store instead of writing them.
     */
    private function providerCapturingEpisodes(): TvdbProvider
    {
        return new class extends TvdbProvider
        {
            /** @var list<array<string, mixed>> */
            public array $storedEpisodes = [];

            public function addEpisodes(int $videoId, iterable $episodes): int
            {
                foreach ($episodes as $episode) {
                    $this->storedEpisodes[] = $episode;
                }

                return \count($this->storedEpisodes);
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function fixture(string $name): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/../../../Fixtures/tvdb/'.$name.'.json'), true, flags: JSON_THROW_ON_ERROR);
    }
}
