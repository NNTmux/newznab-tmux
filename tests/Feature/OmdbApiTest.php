<?php

namespace Tests\Feature;

use App\Services\OmdbClient;
use Tests\TestCase;

/**
 * Tests for OMDb API integration.
 *
 * These tests verify that the OMDb API is properly configured and responding.
 */
class OmdbApiTest extends TestCase
{
    private OmdbClient $omdb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->omdb = new OmdbClient;
    }

    public function test_can_fetch_by_imdb_id(): void
    {
        if (! $this->omdb->isConfigured()) {
            $this->markTestSkipped('OMDb API key is not configured.');
        }

        // The Matrix IMDb ID
        $movie = $this->omdb->movieByImdbId('0133093');

        $this->assertNotNull($movie);
        $this->assertSame('The Matrix', $movie['title']);
        $this->assertSame('1999', $movie['year']);
    }

    public function test_returns_null_for_an_unknown_imdb_id(): void
    {
        if (! $this->omdb->isConfigured()) {
            $this->markTestSkipped('OMDb API key is not configured.');
        }

        $this->assertNull($this->omdb->movieByImdbId('9999999'));
    }
}
