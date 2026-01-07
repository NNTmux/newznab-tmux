<?php

namespace Tests\Feature;

use App\Services\TmdbClient;
use Tests\TestCase;

/**
 * Tests for TMDB API integration.
 *
 * These tests verify that the TMDB API is properly configured and responding.
 */
class TmdbApiTest extends TestCase
{
    private TmdbClient $tmdbClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmdbClient = app(TmdbClient::class);
    }

    public function test_tmdb_client_is_configured(): void
    {
        // Skip if not configured
        if (! $this->tmdbClient->isConfigured()) {
            $this->markTestSkipped('TMDB API key is not configured.');
        }

        $this->assertTrue($this->tmdbClient->isConfigured());
    }

    public function test_can_search_tv_shows(): void
    {
        if (! $this->tmdbClient->isConfigured()) {
            $this->markTestSkipped('TMDB API key is not configured.');
        }

        $results = $this->tmdbClient->searchTv('Breaking Bad');

        $this->assertNotNull($results);
        $totalResults = TmdbClient::getInt($results, 'total_results');
        $this->assertGreaterThan(0, $totalResults);
    }

    public function test_can_get_tv_show_details(): void
    {
        if (! $this->tmdbClient->isConfigured()) {
            $this->markTestSkipped('TMDB API key is not configured.');
        }

        // Breaking Bad TMDB ID
        $showId = 1396;
        $details = $this->tmdbClient->getTvShow($showId);

        $this->assertNotNull($details);
        $this->assertEquals('Breaking Bad', TmdbClient::getString($details, 'name'));
    }

    public function test_can_get_tv_episode(): void
    {
        if (! $this->tmdbClient->isConfigured()) {
            $this->markTestSkipped('TMDB API key is not configured.');
        }

        // Breaking Bad TMDB ID, Season 1, Episode 1
        $showId = 1396;
        $episode = $this->tmdbClient->getTvEpisode($showId, 1, 1);

        $this->assertNotNull($episode);
        $this->assertEquals('Pilot', TmdbClient::getString($episode, 'name'));
    }

    public function test_can_get_external_ids(): void
    {
        if (! $this->tmdbClient->isConfigured()) {
            $this->markTestSkipped('TMDB API key is not configured.');
        }

        // Breaking Bad TMDB ID
        $showId = 1396;
        $externalIds = $this->tmdbClient->getTvExternalIds($showId);

        $this->assertNotNull($externalIds);
        $this->assertArrayHasKey('imdb_id', $externalIds);
    }

    public function test_can_get_alternative_titles(): void
    {
        if (! $this->tmdbClient->isConfigured()) {
            $this->markTestSkipped('TMDB API key is not configured.');
        }

        // Breaking Bad TMDB ID
        $showId = 1396;
        $alternativeTitles = $this->tmdbClient->getTvAlternativeTitles($showId);

        $this->assertNotNull($alternativeTitles);
    }

    public function test_search_returns_null_for_invalid_query(): void
    {
        if (! $this->tmdbClient->isConfigured()) {
            $this->markTestSkipped('TMDB API key is not configured.');
        }

        $results = $this->tmdbClient->searchTv('xyznonexistentshow12345');

        // Should return results array but with 0 total_results
        if ($results !== null) {
            $totalResults = TmdbClient::getInt($results, 'total_results');
            $this->assertEquals(0, $totalResults);
        } else {
            $this->assertNull($results);
        }
    }
}
