<?php

namespace Tests\Feature;

use Tests\TestCase;
use Blacklight\Books;

class GetBooksBrowseByOptionsTest extends TestCase
{
    /**
     * @throws \Exception
     */
    public function testBookBrowseByTest()
    {
        $books = (new Books())->getBrowseByOptions();
        $this->assertArrayHasKey('author', $books);
    }
}
