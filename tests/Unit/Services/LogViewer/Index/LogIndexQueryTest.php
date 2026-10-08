<?php

declare(strict_types=1);

namespace Tests\Unit\Services\LogViewer\Index;

use App\Services\LogViewer\Index\LogIndexQuery;
use App\Services\LogViewer\SearchQuery;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LogIndexQueryTest extends TestCase
{
    /**
     * @return array<string, array{string, string|null}>
     */
    public static function termProvider(): array
    {
        return [
            'single word expands both ways' => ['Reindex', '*reindex*'],
            'class name becomes an adjacent phrase' => ['Jobs\ReindexRel', '"*jobs reindexrel*"'],
            'separators at the edges keep whole words' => [' failed: ', 'failed'],
            'underscores stay inside a token' => ['payment_declined', '*payment_declined*'],
            'short cut-off edge words are dropped' => ['x ReindexRelease', 'reindexrelease*'],
            'short middle words stay' => ['user 12 not found', '"*user 12 not found*"'],
            'operators cannot be injected' => ['"a" | -b (MAYBE)', '"a b maybe"'],
            'only symbols cannot be indexed' => ['=> ::', null],
            'too short to expand' => ['ab', null],
        ];
    }

    #[Test]
    #[DataProvider('termProvider')]
    public function it_turns_terms_into_infix_phrases(string $term, ?string $expected): void
    {
        $this->assertSame($expected, LogIndexQuery::matchExpression($term));
    }

    #[Test]
    public function regex_and_unindexable_terms_are_not_supported(): void
    {
        $this->assertTrue(LogIndexQuery::supports(new SearchQuery('payment')));
        $this->assertTrue(LogIndexQuery::supports(new SearchQuery('', levels: ['error'])));
        $this->assertFalse(LogIndexQuery::supports(new SearchQuery('pay.*ment', regex: true)));
        $this->assertFalse(LogIndexQuery::supports(new SearchQuery('=>')));
    }

    #[Test]
    public function it_builds_filter_clauses_and_can_leave_out_a_facets_own_filter(): void
    {
        $query = new SearchQuery(
            'payment',
            levels: ['error', 'critical'],
            channels: ['local'],
            from: CarbonImmutable::createFromTimestamp(1_000),
            to: CarbonImmutable::createFromTimestamp(2_000),
        );

        $this->assertSame([
            ['query_string' => '*payment*'],
            ['bool' => ['should' => [['equals' => ['level' => 'error']], ['equals' => ['level' => 'critical']]]]],
            ['bool' => ['should' => [['equals' => ['channel' => 'local']]]]],
            ['range' => ['logged_at' => ['gte' => 1_000, 'lte' => 2_000]]],
        ], LogIndexQuery::clauses($query));

        $this->assertSame([
            ['query_string' => '*payment*'],
            ['bool' => ['should' => [['equals' => ['channel' => 'local']]]]],
            ['range' => ['logged_at' => ['gte' => 1_000, 'lte' => 2_000]]],
        ], LogIndexQuery::clauses($query, withLevels: false));
    }

    #[Test]
    public function an_open_ended_time_range_still_excludes_entries_without_a_timestamp(): void
    {
        $query = new SearchQuery('', to: CarbonImmutable::createFromTimestamp(2_000));

        $this->assertSame([['range' => ['logged_at' => ['lte' => 2_000, 'gte' => 1]]]], LogIndexQuery::clauses($query));
    }
}
