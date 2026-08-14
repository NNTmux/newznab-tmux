<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\BookProviderException;
use App\Services\BookService;
use App\Services\IsbnDbService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class IsbnDbServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

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
        $books = $service->searchBooks('domain driven design');

        $this->assertNotNull($book);
        $this->assertCount(1, $books);
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

    public function test_search_books_uses_the_documented_title_and_match_parameters(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['data' => []])),
        ]);
        $history = [];
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));
        $service = new IsbnDbService(
            new Client(['handler' => $handler, 'base_uri' => 'https://api2.isbndb.com']),
            'test-key'
        );

        $this->assertSame([], $service->searchBooks('Clean Code', 'Robert C Martin', true));
        $this->assertCount(1, $history);

        parse_str($history[0]['request']->getUri()->getQuery(), $query);
        $this->assertArrayNotHasKey('author', $query);
        $this->assertSame('title', $query['column']);
        $this->assertSame('true', $query['shouldMatchAll']);
        $this->assertSame('10', $query['pageSize']);
    }

    public function test_successful_empty_search_is_cached(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['data' => []])),
        ]);
        $history = [];
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));
        $service = new IsbnDbService(
            new Client(['handler' => $handler, 'base_uri' => 'https://api2.isbndb.com']),
            'test-key'
        );

        $this->assertSame([], $service->searchBooks('Unique Empty Book Query'));
        $this->assertSame([], $service->searchBooks('Unique Empty Book Query'));
        $this->assertCount(1, $history);
    }

    public function test_rate_limit_response_throws_provider_exception_and_sets_cooldown(): void
    {
        $mock = new MockHandler([
            new Response(429, ['Retry-After' => '120']),
        ]);
        $service = new IsbnDbService(
            new Client(['handler' => HandlerStack::create($mock), 'base_uri' => 'https://api2.isbndb.com']),
            'test-key'
        );

        try {
            $service->searchBooks('Rate Limited Query');
            $this->fail('Expected the ISBNdb request to report provider unavailability.');
        } catch (BookProviderException $exception) {
            $this->assertSame('isbndb', $exception->provider);
            $this->assertSame(429, $exception->statusCode);
            $this->assertSame(120, $exception->retryAfterSeconds);
        }

        $this->assertTrue(Cache::has(IsbnDbService::COOLDOWN_CACHE_KEY));
    }

    public function test_malformed_success_response_throws_provider_exception(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{not-json'),
        ]);
        $service = new IsbnDbService(
            new Client(['handler' => HandlerStack::create($mock), 'base_uri' => 'https://api2.isbndb.com']),
            'test-key'
        );

        $this->expectException(BookProviderException::class);

        $service->searchBooks('Malformed Response Query');
    }

    public function test_success_response_without_documented_data_envelope_is_retryable(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['books' => []])),
        ]);
        $service = new IsbnDbService(
            new Client(['handler' => HandlerStack::create($mock), 'base_uri' => 'https://api2.isbndb.com']),
            'test-key'
        );

        $this->expectException(BookProviderException::class);

        $service->searchBooks('Missing Data Envelope Query');
    }

    public function test_authentication_failure_is_distinguished_without_transient_cooldown(): void
    {
        $mock = new MockHandler([
            new Response(401),
        ]);
        $service = new IsbnDbService(
            new Client(['handler' => HandlerStack::create($mock), 'base_uri' => 'https://api2.isbndb.com']),
            'test-key'
        );

        try {
            $service->searchBooks('Authentication Failure Query');
            $this->fail('Expected an authentication provider exception.');
        } catch (BookProviderException $exception) {
            $this->assertSame(401, $exception->statusCode);
            $this->assertNull($exception->retryAfterSeconds);
        }

        $this->assertFalse(Cache::has(IsbnDbService::COOLDOWN_CACHE_KEY));
    }

    public function test_server_failure_applies_default_cooldown(): void
    {
        $mock = new MockHandler([
            new Response(503),
        ]);
        $service = new IsbnDbService(
            new Client(['handler' => HandlerStack::create($mock), 'base_uri' => 'https://api2.isbndb.com']),
            'test-key'
        );

        try {
            $service->searchBooks('Server Failure Query');
            $this->fail('Expected a transient provider exception.');
        } catch (BookProviderException $exception) {
            $this->assertSame(503, $exception->statusCode);
            $this->assertSame(300, $exception->retryAfterSeconds);
        }

        $this->assertTrue(Cache::has(IsbnDbService::COOLDOWN_CACHE_KEY));
    }

    public function test_network_failure_applies_default_cooldown(): void
    {
        $request = new Request('GET', 'https://api2.isbndb.com/books/Network');
        $mock = new MockHandler([
            new ConnectException('Connection failed', $request),
        ]);
        $service = new IsbnDbService(
            new Client(['handler' => HandlerStack::create($mock), 'base_uri' => 'https://api2.isbndb.com']),
            'test-key'
        );

        try {
            $service->searchBooks('Network Failure Query');
            $this->fail('Expected a network provider exception.');
        } catch (BookProviderException $exception) {
            $this->assertNull($exception->statusCode);
            $this->assertSame(300, $exception->retryAfterSeconds);
        }

        $this->assertTrue(Cache::has(IsbnDbService::COOLDOWN_CACHE_KEY));
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
