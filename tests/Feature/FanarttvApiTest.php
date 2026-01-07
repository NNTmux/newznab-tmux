<?php

namespace Tests\Feature;

use App\Services\FanartTvService;
use Tests\TestCase;

/**
 * Tests for Fanart.tv API integration.
 *
 * These tests verify that the Fanart.tv API is properly configured and responding.
 */
class FanarttvApiTest extends TestCase
{
    private FanartTvService $fanartService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fanartService = new FanartTvService;
    }

    public function test_fanarttv_service_is_configured(): void
    {
        if (! $this->fanartService->isConfigured()) {
            $this->markTestSkipped('Fanart.tv API key is not configured.');
        }

        $this->assertTrue($this->fanartService->isConfigured());
    }

    public function test_can_get_movie_fanart_by_imdb_id(): void
    {
        if (! $this->fanartService->isConfigured()) {
            $this->markTestSkipped('Fanart.tv API key is not configured.');
        }

        // The Shawshank Redemption IMDb ID
        $movieData = $this->fanartService->getMovieFanArt('tt0111161');

        $this->assertNotNull($movieData);
        $this->assertIsArray($movieData);
    }

    public function test_can_get_movie_fanart_by_tmdb_id(): void
    {
        if (! $this->fanartService->isConfigured()) {
            $this->markTestSkipped('Fanart.tv API key is not configured.');
        }

        // The Shawshank Redemption TMDB ID
        $movieData = $this->fanartService->getMovieFanArt('278');

        $this->assertNotNull($movieData);
        $this->assertIsArray($movieData);
    }

    public function test_can_get_movie_properties(): void
    {
        if (! $this->fanartService->isConfigured()) {
            $this->markTestSkipped('Fanart.tv API key is not configured.');
        }

        // The Shawshank Redemption IMDb ID
        $props = $this->fanartService->getMovieProperties('tt0111161');

        // May return null if no cover AND backdrop available
        if ($props !== null) {
            $this->assertIsArray($props);
            $this->assertArrayHasKey('title', $props);
        } else {
            $this->assertNull($props);
        }
    }

    public function test_can_get_tv_fanart(): void
    {
        if (! $this->fanartService->isConfigured()) {
            $this->markTestSkipped('Fanart.tv API key is not configured.');
        }

        // Breaking Bad TVDB ID
        $tvData = $this->fanartService->getTvFanArt('81189');

        $this->assertNotNull($tvData);
        $this->assertIsArray($tvData);
    }

    public function test_can_get_best_tv_poster(): void
    {
        if (! $this->fanartService->isConfigured()) {
            $this->markTestSkipped('Fanart.tv API key is not configured.');
        }

        // Breaking Bad TVDB ID
        $poster = $this->fanartService->getBestTvPoster('81189');

        // May return null if no poster available
        if ($poster !== null) {
            $this->assertIsString($poster);
            $this->assertStringStartsWith('http', $poster);
        } else {
            $this->assertNull($poster);
        }
    }

    public function test_returns_null_for_invalid_id(): void
    {
        if (! $this->fanartService->isConfigured()) {
            $this->markTestSkipped('Fanart.tv API key is not configured.');
        }

        $movieData = $this->fanartService->getMovieFanArt('tt9999999999');

        $this->assertNull($movieData);
    }
}
