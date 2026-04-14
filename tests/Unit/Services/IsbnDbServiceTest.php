<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\BookService;
use App\Services\IsbnDbService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class IsbnDbServiceTest extends TestCase
{
    public function test_is_configured_returns_false_when_key_is_missing(): void
    {
        $service = new IsbnDbService(null, '');

        $this->assertFalse($service->isConfigured());
    }

    public function test_search_book_maps_response_to_internal_shape(): void
    {
        $mock = new MockHandler([
            new Response(200, [
                'ratelimit' => 'limit=500, remaining=499',
                'ratelimit-policy' => '500;w=86400',
            ], json_encode([
                'total' => 1,
                'data' => [[
                    'title' => 'Domain-Driven Design',
                    'isbn13' => '9780321125217',
                    'isbn10' => '0321125215',
                    'authors' => ['Eric Evans'],
                    'publisher' => 'Addison-Wesley Professional',
                    'date_published' => '2003-08-30',
                    'pages' => 560,
                    'synopsis' => '<p>A practical guide.</p>',
                    'subjects' => ['Software', 'Architecture'],
                    'image' => 'https://example.com/cover.jpg',
                ]],
            ])),
        ]);

        $service = new IsbnDbService(
            new Client(['handler' => HandlerStack::create($mock), 'base_uri' => 'https://api2.isbndb.com']),
            'test-key'
        );

        $book = $service->searchBook('domain driven design');

        $this->assertNotNull($book);
        $this->assertSame('Domain-Driven Design', $book['title']);
        $this->assertSame('Eric Evans', $book['author']);
        $this->assertSame('9780321125217', $book['isbn']);
        $this->assertSame('0321125215', $book['ean']);
        $this->assertSame('Addison-Wesley Professional', $book['publisher']);
        $this->assertSame('2003-08-30', $book['publishdate']);
        $this->assertSame('560', $book['pages']);
        $this->assertSame('A practical guide.', $book['overview']);
        $this->assertSame('Software, Architecture', $book['genre']);
        $this->assertSame('https://example.com/cover.jpg', $book['coverurl']);
        $this->assertSame('isbndb:9780321125217', $book['asin']);
    }

    public function test_find_by_isbn_reads_book_payload(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'book' => [
                    'title' => 'Clean Code',
                    'isbn13' => '9780132350884',
                    'authors' => ['Robert C. Martin'],
                    'date_published' => '2008',
                    'subjects' => ['Programming'],
                ],
            ])),
        ]);

        $service = new IsbnDbService(
            new Client(['handler' => HandlerStack::create($mock), 'base_uri' => 'https://api2.isbndb.com']),
            'test-key'
        );

        $book = $service->findByIsbn('978-0132350884');

        $this->assertNotNull($book);
        $this->assertSame('Clean Code', $book['title']);
        $this->assertSame('9780132350884', $book['isbn']);
        $this->assertSame('Robert C. Martin', $book['author']);
    }

    public function test_book_service_extracts_isbn_13_and_isbn_10(): void
    {
        /** @var BookService $bookService */
        $bookService = (new \ReflectionClass(BookService::class))->newInstanceWithoutConstructor();

        $isbn13 = $bookService->extractIsbn('Some.Book.Title.978-0132350884.RETAIL.ePub');
        $isbn10 = $bookService->extractIsbn('Another Book 0132350882 mobi');
        $none = $bookService->extractIsbn('No ISBN in this release name');

        $this->assertSame('9780132350884', $isbn13);
        $this->assertSame('0132350882', $isbn10);
        $this->assertNull($none);
    }
}
