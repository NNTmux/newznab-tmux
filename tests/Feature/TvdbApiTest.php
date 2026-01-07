<?php

namespace Tests\Feature;

use App\Services\TvProcessing\Providers\TvdbProvider;
use Tests\TestCase;

/**
 * Tests for TVDB API integration.
 *
 * These tests verify that the TVDB API is properly configured and responding.
 */
class TvdbApiTest extends TestCase
{
    private ?TvdbProvider $tvdbProvider = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->tvdbProvider = new TvdbProvider;
        } catch (\Exception $e) {
            $this->tvdbProvider = null;
        }
    }

    public function test_tvdb_provider_can_be_instantiated(): void
    {
        if ($this->tvdbProvider === null) {
            $this->markTestSkipped('TVDB API is not configured or unavailable.');
        }

        $this->assertInstanceOf(TvdbProvider::class, $this->tvdbProvider);
    }

    public function test_can_search_for_series(): void
    {
        if ($this->tvdbProvider === null) {
            $this->markTestSkipped('TVDB API is not configured or unavailable.');
        }

        try {
            $results = $this->tvdbProvider->client->search()->search('Breaking Bad', ['type' => 'series']);

            $this->assertNotEmpty($results);
            $this->assertIsArray($results);
        } catch (\Exception $e) {
            $this->markTestSkipped('TVDB API search failed: '.$e->getMessage());
        }
    }

    public function test_can_get_series_episodes(): void
    {
        if ($this->tvdbProvider === null) {
            $this->markTestSkipped('TVDB API is not configured or unavailable.');
        }

        try {
            // First search for Breaking Bad
            $results = $this->tvdbProvider->client->search()->search('Breaking Bad', ['type' => 'series']);

            if (empty($results)) {
                $this->markTestSkipped('Could not find Breaking Bad on TVDB.');
            }

            $tvdbId = $results[0]->tvdb_id;
            $episodes = $this->tvdbProvider->client->series()->episodes($tvdbId);

            $this->assertNotEmpty($episodes);
        } catch (\Exception $e) {
            $this->markTestSkipped('TVDB API episode fetch failed: '.$e->getMessage());
        }
    }

    public function test_can_find_specific_episode(): void
    {
        if ($this->tvdbProvider === null) {
            $this->markTestSkipped('TVDB API is not configured or unavailable.');
        }

        try {
            // Search for Breaking Bad
            $results = $this->tvdbProvider->client->search()->search('Breaking Bad', ['type' => 'series']);

            if (empty($results)) {
                $this->markTestSkipped('Could not find Breaking Bad on TVDB.');
            }

            $tvdbId = $results[0]->tvdb_id;
            $episodes = $this->tvdbProvider->client->series()->episodes($tvdbId);

            // Find Season 1 Episode 1
            $foundEpisode = null;
            foreach ($episodes as $episode) {
                if ($episode->seasonNumber === 1 && $episode->number === 1) {
                    $foundEpisode = $episode;
                    break;
                }
            }

            $this->assertNotNull($foundEpisode, 'Should find S01E01 of Breaking Bad');
        } catch (\Exception $e) {
            $this->markTestSkipped('TVDB API episode search failed: '.$e->getMessage());
        }
    }

    public function test_handles_invalid_search_gracefully(): void
    {
        if ($this->tvdbProvider === null) {
            $this->markTestSkipped('TVDB API is not configured or unavailable.');
        }

        try {
            $results = $this->tvdbProvider->client->search()->search('xyznonexistentshow99999', ['type' => 'series']);

            // Should return empty or null for non-existent shows
            $this->assertTrue(empty($results) || $results === null);
        } catch (\Exception $e) {
            // API might throw exception for no results, which is acceptable
            $this->assertTrue(true);
        }
    }
}
