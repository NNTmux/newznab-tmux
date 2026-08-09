<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Content;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentTest extends TestCase
{
    #[Test]
    public function it_resolves_absolute_urls_as_is(): void
    {
        $content = new Content(['url' => 'https://example.com/page']);

        $this->assertSame('https://example.com/page', $content->resolved_url);
        $this->assertTrue($content->is_external_url);
    }

    #[Test]
    public function it_resolves_http_urls_as_is(): void
    {
        $content = new Content(['url' => 'http://example.com']);

        $this->assertSame('http://example.com', $content->resolved_url);
        $this->assertTrue($content->is_external_url);
    }

    #[Test]
    public function it_prepends_https_to_bare_domains(): void
    {
        $content = new Content(['url' => 'chatgpt.ai']);

        $this->assertSame('https://chatgpt.ai', $content->resolved_url);
        $this->assertTrue($content->is_external_url);
    }

    #[Test]
    public function it_resolves_internal_paths_against_the_site_url(): void
    {
        $content = new Content(['url' => '/forum']);

        $this->assertSame(url('/forum'), $content->resolved_url);
        $this->assertFalse($content->is_external_url);
    }

    #[Test]
    public function it_returns_null_url_when_url_is_empty(): void
    {
        $content = new Content(['url' => null]);

        $this->assertNull($content->resolved_url);
        $this->assertFalse($content->is_external_url);
    }
}
