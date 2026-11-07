<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaginationViewTest extends TestCase
{
    #[Test]
    public function numbered_pagination_marks_the_current_page_and_offers_a_compact_phone_view(): void
    {
        $paginator = new LengthAwarePaginator(range(1, 25), 250, 25, 3, ['path' => '/browse/All']);

        $html = (string) $paginator->links();

        $this->assertMatchesRegularExpression('/<span aria-current="page" class="[^"]*bg-accent text-on-accent[^"]*">3<\/span>/', $html);
        $this->assertStringContainsString('aria-label="Go to page 4"', $html);
        $this->assertMatchesRegularExpression('/Page <span[^>]*>3<\/span>\s*of <span[^>]*>10<\/span>/', $html);
        $this->assertStringContainsString('rel="prev"', $html);
        $this->assertStringContainsString('<span class="sr-only">Next</span>', $html);
        $this->assertStringNotContainsString('&amp;laquo;', $html);
    }

    #[Test]
    public function first_page_disables_previous_and_single_pages_render_nothing(): void
    {
        $first = (string) (new LengthAwarePaginator(range(1, 10), 30, 10, 1, ['path' => '/']))->links();
        $this->assertStringContainsString('aria-disabled="true"', $first);
        $this->assertStringNotContainsString('rel="prev"', $first);

        $single = (string) (new LengthAwarePaginator(range(1, 5), 5, 10, 1, ['path' => '/']))->links();
        $this->assertSame('', trim($single));

        $simple = (string) (new Paginator(range(1, 11), 10, 2, ['path' => '/']))->links();
        $this->assertStringContainsString('rel="prev"', $simple);
        $this->assertStringContainsString('rel="next"', $simple);
    }
}
