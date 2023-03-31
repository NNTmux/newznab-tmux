<?php

namespace Tests\Feature;

use Tests\TestCase;

class BooksTest extends TestCase
{
    /**
     * @throws \DariusIII\ItunesApi\Exceptions\InvalidProviderException
     * @throws \Exception
     */
    public function testFetchItunesBookProperties(): void
    {
        $book = (new \Blacklight\Books())->fetchItunesBookProperties('The Volunteer');

        $this->assertArrayHasKey('author', $book);
        $this->assertEquals('Jack Fairweather', $book['author']);
    }
}
