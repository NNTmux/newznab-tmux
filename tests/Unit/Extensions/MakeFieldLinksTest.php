<?php

declare(strict_types=1);

namespace Tests\Unit\Extensions;

use Tests\TestCase;

class MakeFieldLinksTest extends TestCase
{
    public function test_returns_empty_string_when_field_missing(): void
    {
        $this->assertSame('', makeFieldLinks(['title' => 'Test'], 'genre', 'movies'));
    }

    public function test_returns_empty_string_when_field_empty(): void
    {
        $this->assertSame('', makeFieldLinks(['genre' => ''], 'genre', 'movies'));
    }

    public function test_builds_links_for_comma_separated_values(): void
    {
        $result = makeFieldLinks(['genre' => 'Action, Drama'], 'genre', 'movies');

        $this->assertStringContainsString('?genre=Action', $result);
        $this->assertStringContainsString('>Action</a>', $result);
        $this->assertStringContainsString('?genre=Drama', $result);
        $this->assertStringContainsString('>Drama</a>', $result);
    }

    public function test_escapes_html_in_link_text_and_title(): void
    {
        $malicious = '"><script>alert(1)</script>';
        $result = makeFieldLinks(['actors' => $malicious], 'actors', 'movies');

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString(e($malicious), $result);
        // The raw quote must not break out of the title attribute
        $this->assertStringNotContainsString('title="'.$malicious.'"', $result);
    }

    public function test_escapes_ampersands_in_names(): void
    {
        $result = makeFieldLinks(['actors' => 'Tom & Jerry'], 'actors', 'movies');

        $this->assertStringContainsString('>Tom &amp; Jerry</a>', $result);
        $this->assertStringContainsString('title="Tom &amp; Jerry"', $result);
    }

    public function test_limits_number_of_links(): void
    {
        $values = implode(', ', ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J']);
        $result = makeFieldLinks(['genre' => $values], 'genre', 'movies');

        $this->assertStringNotContainsString('>I</a>', $result);
        $this->assertStringNotContainsString('>J</a>', $result);
    }
}
