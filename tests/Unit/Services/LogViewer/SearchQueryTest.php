<?php

declare(strict_types=1);

namespace Tests\Unit\Services\LogViewer;

use App\Services\LogViewer\SearchQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SearchQueryTest extends TestCase
{
    #[Test]
    public function plain_search_is_case_insensitive_by_default(): void
    {
        $query = new SearchQuery('needle');

        $this->assertTrue($query->matches('A NEEDLE in a haystack'));
        $this->assertSame([
            ['text' => 'A ', 'hit' => false],
            ['text' => 'NEEDLE', 'hit' => true],
            ['text' => ' and ', 'hit' => false],
            ['text' => 'needle', 'hit' => true],
        ], $query->segments('A NEEDLE and needle'));
    }

    #[Test]
    public function case_sensitive_search_only_matches_exact_case(): void
    {
        $query = new SearchQuery('Needle', caseSensitive: true);

        $this->assertFalse($query->matches('needle'));
        $this->assertTrue($query->matches('Needle'));
    }

    #[Test]
    public function plain_search_treats_regex_characters_literally(): void
    {
        $query = new SearchQuery('a.b(c');

        $this->assertTrue($query->matches('x a.b(c y'));
        $this->assertFalse($query->matches('aXb(c'));
    }

    #[Test]
    public function regex_segments_highlight_matches_and_skip_empty_matches(): void
    {
        $query = new SearchQuery('id=\d*', regex: true);

        $this->assertSame([
            ['text' => 'user ', 'hit' => false],
            ['text' => 'id=42', 'hit' => true],
            ['text' => ' and ', 'hit' => false],
            ['text' => 'id=', 'hit' => true],
        ], $query->segments('user id=42 and id='));
        $this->assertSame([['text' => 'nothing', 'hit' => false]], (new SearchQuery('x*', regex: true))->segments('nothing'));
    }

    #[Test]
    public function it_validates_regular_expressions(): void
    {
        $this->assertTrue(SearchQuery::isValidRegex('foo(bar|baz)\d+'));
        $this->assertFalse(SearchQuery::isValidRegex('foo(bar'));
        $this->assertFalse(SearchQuery::isValidRegex("foo\x01bar"));
    }

    #[Test]
    public function level_only_queries_match_headers_of_the_selected_levels(): void
    {
        $query = new SearchQuery('', levels: ['error', 'critical']);

        $this->assertTrue($query->matches('[2026-10-06 18:11:29] NNTmux.ERROR: boom'));
        $this->assertFalse($query->matches('[2026-10-06 18:11:29] NNTmux.INFO: fine'));
        $this->assertFalse($query->matches('#0 ERROR: in a stack trace'));
        $this->assertSame(['-E', '-e', '^\[[^]]*\][[:space:]]+[[:alnum:]_.-]+\.(ERROR|CRITICAL):'], $query->grepArguments());
    }

    #[Test]
    public function grep_arguments_select_the_matcher(): void
    {
        $this->assertSame(['-i', '-F', '-e', '-v'], (new SearchQuery('-v'))->grepArguments());
        $this->assertSame(['-P', '-e', 'a\d'], (new SearchQuery('a\d', regex: true, caseSensitive: true))->grepArguments());
    }
}
