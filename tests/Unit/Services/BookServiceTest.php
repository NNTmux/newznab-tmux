<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\BookService;
use Tests\TestCase;

class BookServiceTest extends TestCase
{
    public function test_parse_release_name_extracts_author_title_and_isbn(): void
    {
        /** @var BookService $service */
        $service = (new \ReflectionClass(BookService::class))->newInstanceWithoutConstructor();

        $parsed = $service->parseReleaseName('Eric.Evans - Domain.Driven.Design 978-0321125217 RETAIL EPUB');

        $this->assertSame('Domain Driven Design', $parsed->title);
        $this->assertSame('Eric Evans', $parsed->author);
        $this->assertSame('9780321125217', $parsed->isbn);
    }

    public function test_parse_release_name_flags_software_as_junk(): void
    {
        /** @var BookService $service */
        $service = (new \ReflectionClass(BookService::class))->newInstanceWithoutConstructor();

        $parsed = $service->parseReleaseName('Foxit PDF Editor Pro 14.0.4.33508 Multilingual x64', 'ebook');

        $this->assertTrue($parsed->isJunk);
    }
}
