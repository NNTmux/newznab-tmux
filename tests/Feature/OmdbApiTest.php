<?php

namespace Tests\Feature;

use aharen\OMDbAPI;
use Tests\TestCase;

/**
 * Tests for OMDb API integration.
 *
 * These tests verify that the OMDb API is properly configured and responding.
 */
class OmdbApiTest extends TestCase
{
    private ?OMDbAPI $omdb = null;

    protected function setUp(): void
    {
        parent::setUp();

        $apiKey = config('nntmux_api.omdb_api_key');
        if (! empty($apiKey)) {
            $this->omdb = new OMDbAPI($apiKey);
        }
    }

    public function test_omdb_api_key_is_configured(): void
    {
        if ($this->omdb === null) {
            $this->markTestSkipped('OMDb API key is not configured.');
        }

        $this->assertNotNull($this->omdb);
    }

    public function test_can_search_for_movies(): void
    {
        if ($this->omdb === null) {
            $this->markTestSkipped('OMDb API key is not configured.');
        }

        $search = $this->omdb->search('The Matrix', 'movie');

        $this->assertIsObject($search);
        $this->assertNotEquals('False', $search->data->Response);
        $this->assertNotEmpty($search->data->Search);
    }

    public function test_can_search_for_series(): void
    {
        if ($this->omdb === null) {
            $this->markTestSkipped('OMDb API key is not configured.');
        }

        $search = $this->omdb->search('Breaking Bad', 'series');

        $this->assertIsObject($search);
        $this->assertNotEquals('False', $search->data->Response);
        $this->assertNotEmpty($search->data->Search);
    }

    public function test_can_fetch_by_imdb_id(): void
    {
        if ($this->omdb === null) {
            $this->markTestSkipped('OMDb API key is not configured.');
        }

        // The Matrix IMDb ID
        $result = $this->omdb->fetch('i', 'tt0133093');

        $this->assertIsObject($result);
        $this->assertNotEquals('False', $result->data->Response);
        $this->assertEquals('The Matrix', $result->data->Title);
    }

    public function test_returns_false_response_for_invalid_search(): void
    {
        if ($this->omdb === null) {
            $this->markTestSkipped('OMDb API key is not configured.');
        }

        $search = $this->omdb->search('xyznonexistentmovie99999', 'movie');

        $this->assertIsObject($search);
        $this->assertEquals('False', $search->data->Response);
    }

    public function test_search_result_has_expected_properties(): void
    {
        if ($this->omdb === null) {
            $this->markTestSkipped('OMDb API key is not configured.');
        }

        $search = $this->omdb->search('The Matrix', 'movie');

        if ($search->data->Response === 'False') {
            $this->markTestSkipped('OMDb search returned no results.');
        }

        $firstResult = $search->data->Search[0];

        $this->assertObjectHasProperty('Title', $firstResult);
        $this->assertObjectHasProperty('imdbID', $firstResult);
        $this->assertObjectHasProperty('Year', $firstResult);
    }
}

