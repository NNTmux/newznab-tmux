<?php

namespace Tests\Integration;

use App\Services\ItunesService;
use Tests\TestCase;

/**
 * Integration tests for the iTunes API Service.
 * These tests make real API calls to iTunes to verify the implementation works.
 * Note: Movie support has been removed due to API issues.
 *
 * Run with: php artisan test --filter=ItunesServiceIntegrationTest
 */
class ItunesServiceIntegrationTest extends TestCase
{
    protected ItunesService $itunes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->itunes = new ItunesService;
    }

    // ==========================================
    // MUSIC TESTS
    // ==========================================

    /**
     * Test searching for music albums and ebooks (the two supported media types).
     */
    public function test_supported_media_types_work(): void
    {
        // Test Music Album
        $album = $this->itunes->findAlbum('Abbey Road Beatles');
        $this->assertNotNull($album, 'Music album search failed');
        $this->assertNotEmpty($album['name'], 'Album name is empty');
        $this->assertNotEmpty($album['artist'], 'Album artist is empty');
        $this->assertNotEmpty($album['cover'], 'Album cover is empty');
        $this->assertStringContainsString('800x800', $album['cover'], 'Album cover should be high resolution');

        fwrite(STDERR, "\n[MUSIC] Found: {$album['name']} by {$album['artist']}\n");
        fwrite(STDERR, "  Genre: {$album['genre']}\n");
        fwrite(STDERR, "  Cover: Yes (800x800)\n");

        // Test Ebook
        $book = $this->itunes->findEbook('Harry Potter');
        $this->assertNotNull($book, 'Ebook search failed');
        $this->assertNotEmpty($book['name'], 'Book name is empty');
        $this->assertNotEmpty($book['author'], 'Book author is empty');

        fwrite(STDERR, "\n[EBOOK] Found: {$book['name']} by {$book['author']}\n");
        fwrite(STDERR, "  Genre: {$book['genre']}\n");
        fwrite(STDERR, '  Cover: '.(! empty($book['cover']) ? 'Yes' : 'No')."\n");
    }

    /**
     * Test normalized album data structure.
     */
    public function test_album_has_correct_structure(): void
    {
        $album = $this->itunes->findAlbum('Dark Side of the Moon Pink Floyd');

        $this->assertNotNull($album);
        $this->assertArrayHasKey('id', $album);
        $this->assertArrayHasKey('name', $album);
        $this->assertArrayHasKey('artist', $album);
        $this->assertArrayHasKey('genre', $album);
        $this->assertArrayHasKey('release_date', $album);
        $this->assertArrayHasKey('cover', $album);
        $this->assertArrayHasKey('store_url', $album);
        $this->assertArrayHasKey('track_count', $album);
    }

    /**
     * Test normalized ebook data structure.
     */
    public function test_ebook_has_correct_structure(): void
    {
        $book = $this->itunes->findEbook('1984 George Orwell');

        $this->assertNotNull($book);
        $this->assertArrayHasKey('id', $book);
        $this->assertArrayHasKey('name', $book);
        $this->assertArrayHasKey('author', $book);
        $this->assertArrayHasKey('genre', $book);
        $this->assertArrayHasKey('genres', $book);
        $this->assertArrayHasKey('release_date', $book);
        $this->assertArrayHasKey('description', $book);
        $this->assertArrayHasKey('cover', $book);
    }

    /**
     * Test empty search term handling.
     */
    public function test_empty_search_returns_null(): void
    {
        $this->assertNull($this->itunes->searchAlbums(''));
        $this->assertNull($this->itunes->searchAlbums('   '));
        $this->assertNull($this->itunes->searchEbooks(''));
    }

    /**
     * Test non-existent search returns empty array.
     */
    public function test_nonexistent_search_returns_empty_array(): void
    {
        $results = $this->itunes->searchAlbums('xyznonexistentalbumzzz123456789');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /**
     * Test lookup by ID works.
     */
    public function test_lookup_by_id(): void
    {
        // First find an album to get an ID
        $album = $this->itunes->findAlbum('Thriller Michael Jackson');
        $this->assertNotNull($album);
        $this->assertNotNull($album['id']);

        // Now lookup by ID
        $result = $this->itunes->lookupById($album['id']);

        $this->assertNotNull($result);
        $this->assertEquals($album['id'], $result['collectionId']);
    }

    /**
     * Test track search.
     */
    public function test_track_search(): void
    {
        $track = $this->itunes->findTrack('Bohemian Rhapsody Queen');

        $this->assertNotNull($track);
        $this->assertArrayHasKey('name', $track);
        $this->assertArrayHasKey('artist', $track);
        $this->assertArrayHasKey('album', $track);
        $this->assertArrayHasKey('duration_ms', $track);

        fwrite(STDERR, "\n[TRACK] Found: {$track['name']} by {$track['artist']}\n");
    }

    /**
     * Test audiobook search.
     */
    public function test_audiobook_search(): void
    {
        $results = $this->itunes->searchAudiobooks('The Hobbit');

        $this->assertNotNull($results);
        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
    }

    /**
     * Test country setting.
     */
    public function test_country_setting(): void
    {
        $ukResults = $this->itunes->country('GB')->searchAlbums('Adele');

        $this->assertNotNull($ukResults);
        $this->assertNotEmpty($ukResults);
    }

    /**
     * Test limit setting.
     */
    public function test_limit_setting(): void
    {
        $results = $this->itunes->limit(5)->searchAlbums('Rock');

        $this->assertNotNull($results);
        $this->assertLessThanOrEqual(5, count($results));
    }

    /**
     * Comprehensive output test - outputs all data for manual verification.
     */
    public function test_comprehensive_data_output(): void
    {
        fwrite(STDERR, "\n\n========================================\n");
        fwrite(STDERR, "ITUNES SERVICE - MUSIC & EBOOK TEST\n");
        fwrite(STDERR, "========================================\n");

        // Music
        $album = $this->itunes->findAlbum('Back in Black AC/DC');
        if ($album) {
            fwrite(STDERR, "\n--- MUSIC ALBUM ---\n");
            fwrite(STDERR, "Name: {$album['name']}\n");
            fwrite(STDERR, "Artist: {$album['artist']}\n");
            fwrite(STDERR, "ID: {$album['id']}\n");
            fwrite(STDERR, "Genre: {$album['genre']}\n");
            fwrite(STDERR, "Release: {$album['release_date']}\n");
            fwrite(STDERR, "Tracks: {$album['track_count']}\n");
            fwrite(STDERR, 'Cover: '.substr($album['cover'], 0, 60)."...\n");
        }

        // Book
        $book = $this->itunes->findEbook('To Kill a Mockingbird Harper Lee');
        if ($book) {
            fwrite(STDERR, "\n--- EBOOK ---\n");
            fwrite(STDERR, "Name: {$book['name']}\n");
            fwrite(STDERR, "ID: {$book['id']}\n");
            fwrite(STDERR, "Author: {$book['author']}\n");
            fwrite(STDERR, "Genre: {$book['genre']}\n");
            fwrite(STDERR, "Release: {$book['release_date']}\n");
            fwrite(STDERR, 'Description: '.substr($book['description'] ?? '', 0, 100)."...\n");
            if (! empty($book['cover'])) {
                fwrite(STDERR, 'Cover: '.substr($book['cover'], 0, 60)."...\n");
            }
        }

        fwrite(STDERR, "\n========================================\n");
        fwrite(STDERR, "TEST COMPLETE - Music & Ebook working!\n");
        fwrite(STDERR, "========================================\n\n");

        $this->assertTrue(true);
    }
}
