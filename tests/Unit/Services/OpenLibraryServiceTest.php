<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\OpenLibraryService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenLibraryServiceTest extends TestCase
{
    public function test_search_books_normalizes_docs_payload(): void
    {
        Http::fake([
            'openlibrary.org/search.json*' => Http::response([
                'docs' => [[
                    'key' => '/works/OL123W',
                    'title' => 'Refactoring',
                    'author_name' => ['Martin Fowler'],
                    'isbn' => ['9780201485677', '0201485672'],
                    'publisher' => ['Addison-Wesley'],
                    'first_publish_year' => 1999,
                    'cover_i' => 54321,
                ]],
            ]),
        ]);

        $service = new OpenLibraryService;

        $books = $service->searchBooks('refactoring');

        $this->assertCount(1, $books);
        $this->assertSame('Refactoring', $books[0]['title']);
        $this->assertSame('Martin Fowler', $books[0]['author']);
        $this->assertSame('9780201485677', $books[0]['isbn']);
        $this->assertSame('0201485672', $books[0]['ean']);
        $this->assertSame('openlibrary:9780201485677', $books[0]['asin']);
    }

    public function test_find_by_isbn_normalizes_payload(): void
    {
        Http::fake([
            'openlibrary.org/isbn/*' => Http::response([
                'title' => 'Clean Architecture',
                'authors' => [['name' => 'Robert C. Martin']],
                'publish_date' => '2017-09-20',
                'publishers' => ['Prentice Hall'],
                'covers' => [12345],
            ]),
        ]);

        $service = new OpenLibraryService;

        $book = $service->findByIsbn('9780134494166');

        $this->assertNotNull($book);
        $this->assertSame('Clean Architecture', $book['title']);
        $this->assertSame('Robert C. Martin', $book['author']);
        $this->assertSame('2017-09-20', $book['publishdate']);
        $this->assertSame('Prentice Hall', $book['publisher']);
        $this->assertSame('https://covers.openlibrary.org/b/id/12345-L.jpg', $book['coverurl']);
    }
}
