<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\BookMatchScorer;
use App\Support\DTOs\BookParseResult;
use Tests\TestCase;

class BookMatchScorerTest extends TestCase
{
    public function test_returns_perfect_score_for_isbn_match(): void
    {
        $parsed = new BookParseResult(
            rawName: 'Domain Driven Design',
            title: 'Domain-Driven Design',
            author: 'Eric Evans',
            isbn: '9780321125217'
        );

        $score = (new BookMatchScorer)->score([
            'title' => 'Some Other Title',
            'author' => 'Unknown',
            'isbn' => '9780321125217',
            'publishdate' => '2020-01-01',
            'publisher' => '',
            'cover' => 0,
        ], $parsed);

        $this->assertSame(1.0, $score);
    }

    public function test_higher_score_for_better_title_author_match(): void
    {
        $parsed = new BookParseResult(
            rawName: 'Refactoring',
            title: 'Refactoring',
            author: 'Martin Fowler',
            year: 1999
        );

        $scorer = new BookMatchScorer;

        $good = $scorer->score([
            'title' => 'Refactoring: Improving the Design of Existing Code',
            'author' => 'Martin Fowler',
            'publishdate' => '1999-07-08',
            'publisher' => 'Addison-Wesley',
            'cover' => 1,
        ], $parsed);

        $bad = $scorer->score([
            'title' => 'Cooking for Beginners',
            'author' => 'Random Author',
            'publishdate' => '2018-01-01',
            'publisher' => '',
            'cover' => 0,
        ], $parsed);

        $this->assertGreaterThan($bad, $good);
    }

    public function test_unrelated_title_without_author_scores_below_threshold(): void
    {
        $parsed = new BookParseResult(
            rawName: 'El verano en que me enamore',
            title: 'El verano en que me enamore',
        );

        $score = (new BookMatchScorer)->score([
            'title' => 'Aquellos veranos de pileta',
            'author' => 'Ignacio Pomi & Lucas Enrique Sastre',
            'publishdate' => '',
            'publisher' => '',
            'cover' => 1,
        ], $parsed);

        $this->assertLessThan(0.55, $score, 'Unrelated book with partial word overlap should not reach match threshold');
    }
}
