<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\IsbnDbService;
use Tests\TestCase;

class IsbnDbServiceIntegrationTest extends TestCase
{
    public function test_search_book_with_live_isbndb_api(): void
    {
        $service = new IsbnDbService;

        if (! $service->isConfigured()) {
            $this->markTestSkipped('ISBNDB_API_KEY is not configured.');
        }

        $book = $service->searchBook('The Hobbit J. R. R. Tolkien');

        $this->assertNotNull($book, 'ISBNdb search should return a known book.');
        $this->assertNotSame('', $book['title']);
        $this->assertArrayHasKey('author', $book);
        $this->assertArrayHasKey('isbn', $book);
    }

    public function test_find_by_isbn_with_live_isbndb_api(): void
    {
        $service = new IsbnDbService;

        if (! $service->isConfigured()) {
            $this->markTestSkipped('ISBNDB_API_KEY is not configured.');
        }

        $book = $service->findByIsbn('9780140328721');

        $this->assertNotNull($book, 'ISBNdb ISBN lookup should return a known book.');
        $this->assertNotSame('', $book['title']);
        $this->assertSame('9780140328721', $book['isbn']);
    }
}
