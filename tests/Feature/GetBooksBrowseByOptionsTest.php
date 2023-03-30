<?php

namespace Tests\Feature;

use Blacklight\Books;
use Tests\TestCase;

class GetBooksBrowseByOptionsTest extends TestCase
{
    /**
     * @throws \Exception
     */
    public function testBookBrowseByTest(): void
    {
        $books = (new Books())->getBrowseByOptions();
        $this->assertArrayHasKey('author', $books);
    }
}
