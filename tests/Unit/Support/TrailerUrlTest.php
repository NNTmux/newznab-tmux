<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\TrailerUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TrailerUrlTest extends TestCase
{
    /**
     * @return array<string, array{mixed, ?string}>
     */
    public static function trailers(): array
    {
        return [
            'trakt embed link' => ['https://www.youtube.com/embed/dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'nocookie embed with query' => ['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'plain http watch link' => ['http://youtube.com/watch?v=dQw4w9WgXcQ&t=10', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'short link' => ['https://youtu.be/dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'scheme-less traileraddict' => ['v.traileraddict.com/12345', 'https://v.traileraddict.com/12345'],
            'other http link is upgraded' => ['http://example.com/trailer.mp4', 'https://example.com/trailer.mp4'],
            'https link kept' => ['https://vimeo.com/123456', 'https://vimeo.com/123456'],
            'javascript url' => ['javascript:alert(1)', null],
            'html markup' => ['<iframe src="https://www.youtube.com/embed/x"></iframe>', null],
            'quote injection' => ['https://www.youtube.com/embed/x"><script>alert(1)</script>', null],
            'empty string' => ['', null],
            'null' => [null, null],
        ];
    }

    #[Test]
    #[DataProvider('trailers')]
    public function it_builds_a_watch_url_for_a_new_tab(mixed $trailer, ?string $expected): void
    {
        $this->assertSame($expected, TrailerUrl::watchUrl($trailer));
    }
}
