<?php

namespace Tests\Feature;

use App\Services\TraktService;
use Tests\TestCase;

/**
 * Tests for Trakt.tv API integration.
 *
 * These tests verify that the Trakt.tv API is properly configured and responding.
 */
class TraktApiTest extends TestCase
{
    private TraktService $traktService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->traktService = new TraktService;
    }

    public function test_trakt_service_is_configured(): void
    {
        if (! $this->traktService->isConfigured()) {
            $this->markTestSkipped('Trakt.tv API key is not configured.');
        }

        $this->assertTrue($this->traktService->isConfigured());
    }

    public function test_can_search_for_shows(): void
    {
        if (! $this->traktService->isConfigured()) {
            $this->markTestSkipped('Trakt.tv API key is not configured.');
        }

        $results = $this->traktService->searchShows('Breaking Bad', 'show');

        $this->assertNotNull($results);
        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
    }

    public function test_can_get_show_summary(): void
    {
        if (! $this->traktService->isConfigured()) {
            $this->markTestSkipped('Trakt.tv API key is not configured.');
        }

        // First search for Breaking Bad
        $results = $this->traktService->searchShows('Breaking Bad', 'show');

        if (empty($results)) {
            $this->markTestSkipped('Could not find Breaking Bad on Trakt.tv.');
        }

        $traktId = $results[0]['show']['ids']['trakt'];
        $summary = $this->traktService->getShowSummary($traktId, 'full');

        $this->assertNotNull($summary);
        $this->assertIsArray($summary);
        $this->assertEquals('Breaking Bad', $summary['title']);
    }

    public function test_can_get_episode_summary(): void
    {
        if (! $this->traktService->isConfigured()) {
            $this->markTestSkipped('Trakt.tv API key is not configured.');
        }

        // First search for Breaking Bad
        $results = $this->traktService->searchShows('Breaking Bad', 'show');

        if (empty($results)) {
            $this->markTestSkipped('Could not find Breaking Bad on Trakt.tv.');
        }

        $traktId = $results[0]['show']['ids']['trakt'];

        // Get Season 1, Episode 1
        $episode = $this->traktService->getEpisodeSummary($traktId, 1, 1, 'full');

        $this->assertNotNull($episode);
        $this->assertIsArray($episode);
        $this->assertEquals('Pilot', $episode['title']);
    }

    public function test_can_get_trending_shows(): void
    {
        if (! $this->traktService->isConfigured()) {
            $this->markTestSkipped('Trakt.tv API key is not configured.');
        }

        $trending = $this->traktService->getTrendingShows(5);

        $this->assertNotNull($trending);
        $this->assertIsArray($trending);
        $this->assertCount(5, $trending);
    }

    public function test_search_result_has_expected_structure(): void
    {
        if (! $this->traktService->isConfigured()) {
            $this->markTestSkipped('Trakt.tv API key is not configured.');
        }

        $results = $this->traktService->searchShows('Breaking Bad', 'show');

        if (empty($results)) {
            $this->markTestSkipped('Could not find Breaking Bad on Trakt.tv.');
        }

        $firstResult = $results[0];

        $this->assertArrayHasKey('show', $firstResult);
        $this->assertArrayHasKey('title', $firstResult['show']);
        $this->assertArrayHasKey('ids', $firstResult['show']);
        $this->assertArrayHasKey('trakt', $firstResult['show']['ids']);
    }

    public function test_returns_empty_for_invalid_search(): void
    {
        if (! $this->traktService->isConfigured()) {
            $this->markTestSkipped('Trakt.tv API key is not configured.');
        }

        $results = $this->traktService->searchShows('xyznonexistentshow99999', 'show');

        $this->assertTrue(empty($results) || $results === null);
    }
}
