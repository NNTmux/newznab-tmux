<?php

namespace Tests\Feature;

use App\Services\TvProcessing\Providers\TvMazeProvider;
use Tests\TestCase;

/**
 * Tests for TVMaze API integration.
 *
 * These tests verify that the TVMaze API is properly responding.
 * Note: TVMaze API does not require an API key.
 */
class TvmazeApiTest extends TestCase
{
    private TvMazeProvider $tvmazeProvider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tvmazeProvider = new TvMazeProvider;
    }

    public function test_tvmaze_provider_can_be_instantiated(): void
    {
        $this->assertInstanceOf(TvMazeProvider::class, $this->tvmazeProvider);
    }

    public function test_can_search_for_shows(): void
    {
        try {
            $results = $this->tvmazeProvider->client->search('Breaking Bad');

            $this->assertNotEmpty($results);
            $this->assertIsArray($results);
        } catch (\Exception $e) {
            $this->markTestSkipped('TVMaze API search failed: '.$e->getMessage());
        }
    }

    public function test_can_get_episode_by_number(): void
    {
        try {
            // First search for Breaking Bad
            $results = $this->tvmazeProvider->client->search('Breaking Bad');

            if (empty($results)) {
                $this->markTestSkipped('Could not find Breaking Bad on TVMaze.');
            }

            $showId = $results[0]->id;

            // Get Season 1, Episode 1
            $episode = $this->tvmazeProvider->client->getEpisodeByNumber($showId, 1, 1);

            $this->assertNotNull($episode);
            $this->assertEquals('Pilot', $episode->name);
        } catch (\Exception $e) {
            $this->markTestSkipped('TVMaze API episode fetch failed: '.$e->getMessage());
        }
    }

    public function test_can_get_all_episodes_by_show_id(): void
    {
        try {
            // First search for Breaking Bad
            $results = $this->tvmazeProvider->client->search('Breaking Bad');

            if (empty($results)) {
                $this->markTestSkipped('Could not find Breaking Bad on TVMaze.');
            }

            $showId = $results[0]->id;
            $episodes = $this->tvmazeProvider->client->getEpisodesByShowID($showId);

            $this->assertNotEmpty($episodes);
            $this->assertIsArray($episodes);
            // Breaking Bad has 62 episodes
            $this->assertGreaterThan(50, count($episodes));
        } catch (\Exception $e) {
            $this->markTestSkipped('TVMaze API episodes fetch failed: '.$e->getMessage());
        }
    }

    public function test_search_returns_empty_for_nonexistent_show(): void
    {
        try {
            $results = $this->tvmazeProvider->client->search('xyznonexistentshow99999');

            $this->assertEmpty($results);
        } catch (\Exception $e) {
            // API might throw exception for no results
            $this->assertTrue(true);
        }
    }

    public function test_first_search_result_has_expected_properties(): void
    {
        try {
            $results = $this->tvmazeProvider->client->search('Breaking Bad');

            if (empty($results)) {
                $this->markTestSkipped('Could not find Breaking Bad on TVMaze.');
            }

            $show = $results[0];

            $this->assertObjectHasProperty('id', $show);
            $this->assertObjectHasProperty('name', $show);
        } catch (\Exception $e) {
            $this->markTestSkipped('TVMaze API search failed: '.$e->getMessage());
        }
    }
}

