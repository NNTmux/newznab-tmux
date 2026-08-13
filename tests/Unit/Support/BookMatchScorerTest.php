<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\BookMatchScorer;
use App\Support\Data\BookParseResult;
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

    public function test_returns_perfect_score_for_equivalent_isbn_10_candidate(): void
    {
        $parsed = new BookParseResult(
            rawName: 'Clean Code 9780132350884',
            title: 'Clean Code',
            isbn: '9780132350884'
        );

        $score = (new BookMatchScorer)->score([
            'title' => 'Clean Code',
            'author' => 'Robert C. Martin',
            'isbn' => null,
            'ean' => '0132350882',
        ], $parsed);

        $this->assertSame(1.0, $score);
    }

    public function test_rejects_candidate_with_conflicting_explicit_isbn(): void
    {
        $parsed = new BookParseResult(
            rawName: 'Clean Code 9780132350884',
            title: 'Clean Code',
            author: 'Robert C. Martin',
            isbn: '9780132350884'
        );

        $score = (new BookMatchScorer)->score([
            'title' => 'Clean Code',
            'author' => 'Robert C. Martin',
            'isbn' => '9780321125217',
            'ean' => '0321125215',
            'publisher' => 'Excellent Publisher',
            'cover' => 1,
        ], $parsed);

        $this->assertSame(0.0, $score);
    }

    public function test_rejects_candidate_when_only_one_of_two_explicit_identifiers_matches(): void
    {
        $parsed = new BookParseResult(
            rawName: 'Clean Code 9780132350884',
            title: 'Clean Code',
            isbn: '9780132350884'
        );

        $score = (new BookMatchScorer)->score([
            'title' => 'Clean Code',
            'author' => 'Robert C. Martin',
            'isbn' => '9780132350884',
            'ean' => '0321125215',
        ], $parsed);

        $this->assertSame(0.0, $score);
    }

    public function test_author_matching_is_order_insensitive_and_supports_initials(): void
    {
        $parsed = new BookParseResult(
            rawName: 'Clean Code by Robert C Martin',
            title: 'Clean Code',
            author: 'Robert C Martin'
        );

        $score = (new BookMatchScorer)->score([
            'title' => 'Clean Code',
            'author' => 'Martin, Robert C.',
        ], $parsed);

        $this->assertGreaterThanOrEqual(0.82, $score);
    }

    public function test_cover_and_publisher_do_not_increase_identity_score(): void
    {
        $parsed = new BookParseResult(
            rawName: 'Completely Different Book',
            title: 'Completely Different Book'
        );
        $scorer = new BookMatchScorer;

        $withoutMetadata = $scorer->score([
            'title' => 'Another Unrelated Work',
            'publisher' => '',
            'cover' => 0,
        ], $parsed);
        $withMetadata = $scorer->score([
            'title' => 'Another Unrelated Work',
            'publisher' => 'Publisher',
            'cover' => 1,
        ], $parsed);

        $this->assertSame($withoutMetadata, $withMetadata);
    }

    public function test_rejects_conflicting_volume_numbers(): void
    {
        $parsed = new BookParseResult(
            rawName: 'Dune Book 2',
            title: 'Dune Book 2',
            author: 'Frank Herbert'
        );

        $score = (new BookMatchScorer)->score([
            'title' => 'Dune Book 3',
            'author' => 'Frank Herbert',
        ], $parsed);

        $this->assertSame(0.0, $score);
    }

    public function test_rejects_conflicting_edition_numbers(): void
    {
        $parsed = new BookParseResult(
            rawName: 'Effective Java 3rd Edition',
            title: 'Effective Java 3rd Edition',
            author: 'Joshua Bloch'
        );

        $score = (new BookMatchScorer)->score([
            'title' => 'Effective Java 2nd Edition',
            'author' => 'Joshua Bloch',
        ], $parsed);

        $this->assertSame(0.0, $score);
    }

    public function test_multilingual_punctuation_normalizes_without_metadata_boosts(): void
    {
        $parsed = new BookParseResult(
            rawName: 'Cien años de soledad by Gabriel García Márquez',
            title: 'Cien años de soledad',
            author: 'Gabriel García Márquez'
        );

        $score = (new BookMatchScorer)->score([
            'title' => 'Cien años de soledad!',
            'author' => 'Márquez, Gabriel García',
        ], $parsed);

        $this->assertSame(1.0, $score);
    }

    public function test_generic_stop_words_do_not_count_as_significant_title_evidence(): void
    {
        $this->assertSame(2, (new BookMatchScorer)->meaningfulTitleTokenCount('The Art of War'));
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

    public function test_diabetes_title_collision_scores_below_no_author_cutoff(): void
    {
        $parsed = new BookParseResult(
            rawName: 'Living With Diabetes - 1st Edition 2026',
            title: 'Living With Diabetes - 1st Edition 2026',
            year: 2026
        );

        $score = (new BookMatchScorer)->score([
            'title' => 'Healthy Lifestyle Against Diabetes 1st Edition',
            'author' => 'Amie Armstrong',
            'publishdate' => '2018-11-04',
            'publisher' => 'Independently Published',
            'cover' => 1,
        ], $parsed);

        $this->assertLessThan(0.68, $score, 'Ambiguous no-author title collision should stay below stricter cutoff');
    }
}
