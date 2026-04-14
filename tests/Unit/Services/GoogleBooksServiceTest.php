<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\GoogleBooksService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class GoogleBooksServiceTest extends TestCase
{
    public function test_search_books_maps_google_books_payload(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'items' => [[
                    'id' => 'gbook1',
                    'volumeInfo' => [
                        'title' => 'The Pragmatic Programmer',
                        'authors' => ['Andrew Hunt', 'David Thomas'],
                        'publisher' => 'Addison-Wesley',
                        'publishedDate' => '1999-10-20',
                        'pageCount' => 352,
                        'description' => '<p>Classic software engineering guidance.</p>',
                        'categories' => ['Computers'],
                        'industryIdentifiers' => [
                            ['type' => 'ISBN_13', 'identifier' => '9780201616224'],
                            ['type' => 'ISBN_10', 'identifier' => '020161622X'],
                        ],
                        'imageLinks' => [
                            'thumbnail' => 'https://example.com/cover.jpg',
                        ],
                    ],
                ]],
            ])),
        ]);

        $service = new GoogleBooksService(
            new Client(['handler' => HandlerStack::create($mock)]),
            null
        );

        $books = $service->searchBooks('pragmatic programmer');

        $this->assertCount(1, $books);
        $this->assertSame('The Pragmatic Programmer', $books[0]['title']);
        $this->assertSame('Andrew Hunt, David Thomas', $books[0]['author']);
        $this->assertSame('9780201616224', $books[0]['isbn']);
        $this->assertSame('020161622X', $books[0]['ean']);
        $this->assertSame('googlebooks:9780201616224', $books[0]['asin']);
    }

    public function test_has_api_key_is_false_when_missing(): void
    {
        $service = new GoogleBooksService(null, '');

        $this->assertTrue($service->isConfigured());
        $this->assertFalse($service->hasApiKey());
    }
}
