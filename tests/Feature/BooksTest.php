<?php

namespace Blacklight;

use PHPUnit\Framework\TestCase;

class BooksTest extends TestCase
{
    /**
     * @throws \DariusIII\ItunesApi\Exceptions\InvalidProviderException
     * @throws \Exception
     */
    public function testFetchItunesBookProperties()
    {
        $book = (new \Blacklight\Books())->fetchItunesBookProperties('One man Army');

        $this->assertArrayHasKey('author', $book);
        $this->assertEquals('Donna Michaels', $book['author']);
    }
}
