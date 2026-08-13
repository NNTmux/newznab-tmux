<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\BookIsbn;
use PHPUnit\Framework\TestCase;

class BookIsbnTest extends TestCase
{
    public function test_normalizes_and_validates_isbn_10_and_isbn_13(): void
    {
        $this->assertSame('0132350882', BookIsbn::normalize('0-13-235088-2'));
        $this->assertSame('9780132350884', BookIsbn::normalize('978-0-13-235088-4'));
        $this->assertNull(BookIsbn::normalize('0132350883'));
        $this->assertNull(BookIsbn::normalize('9780132350885'));
    }

    public function test_converts_and_compares_equivalent_isbn_versions(): void
    {
        $this->assertSame('9780132350884', BookIsbn::toIsbn13('0132350882'));
        $this->assertSame('0132350882', BookIsbn::toIsbn10('9780132350884'));
        $this->assertTrue(BookIsbn::equivalent('0132350882', '9780132350884'));
        $this->assertFalse(BookIsbn::equivalent('0132350882', '9780321125217'));
    }

    public function test_returns_all_equivalent_identifiers_without_duplicates(): void
    {
        $this->assertSame(
            ['0132350882', '9780132350884'],
            BookIsbn::equivalents('9780132350884')
        );
    }
}
