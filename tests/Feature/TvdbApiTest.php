<?php

namespace Tests\Feature;

use App\Exceptions\TvProviderException;
use App\Services\TvdbClient;
use Tests\TestCase;

/**
 * Tests for TVDB API integration.
 *
 * These tests verify that the TVDB API is properly configured and responding.
 */
class TvdbApiTest extends TestCase
{
    private const int BREAKING_BAD = 81189;

    private TvdbClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new TvdbClient;

        if (! $this->client->isConfigured()) {
            $this->markTestSkipped('TVDB API key is not configured.');
        }
    }

    public function test_can_search_for_series(): void
    {
        $results = $this->search('Breaking Bad');

        $this->assertNotEmpty($results);
        $this->assertContains((string) self::BREAKING_BAD, array_column($results, 'tvdb_id'));
    }

    public function test_can_find_specific_episode(): void
    {
        try {
            $episodes = $this->client->seriesEpisodes(self::BREAKING_BAD, ['season' => 1, 'episodeNumber' => 1]);
        } catch (TvProviderException $e) {
            $this->markTestSkipped('TVDB API is unavailable: '.$e->getMessage());
        }

        $this->assertCount(1, $episodes);
        $this->assertSame(1, $episodes[0]['seasonNumber']);
        $this->assertSame(1, $episodes[0]['number']);
        $this->assertSame('2008-01-20', $episodes[0]['aired']);
    }

    public function test_finds_a_regular_episode_by_airdate(): void
    {
        try {
            $episodes = $this->client->seriesEpisodes(self::BREAKING_BAD, ['airDate' => '2008-01-20']);
        } catch (TvProviderException $e) {
            $this->markTestSkipped('TVDB API is unavailable: '.$e->getMessage());
        }

        $this->assertSame([[1, 1]], array_map(static fn (array $episode): array => [$episode['seasonNumber'], $episode['number']], $episodes));
    }

    public function test_returns_no_results_for_an_unknown_show(): void
    {
        $this->assertSame([], $this->search('xyznonexistentshow99999'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function search(string $name): array
    {
        try {
            return $this->client->searchSeries($name);
        } catch (TvProviderException $e) {
            $this->markTestSkipped('TVDB API is unavailable: '.$e->getMessage());
        }
    }
}
