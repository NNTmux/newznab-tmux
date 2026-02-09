<?php

namespace Tests\Unit;

use App\Services\Search\Drivers\ManticoreSearchDriver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ManticoreSearchQueryTest extends TestCase
{
    #[Test]
    #[DataProvider('negationQueriesProvider')]
    public function it_preserves_negation_operators(string $input, string $expected): void
    {
        $result = ManticoreSearchDriver::prepareUserSearchQuery($input);
        $this->assertSame($expected, $result);
    }

    public static function negationQueriesProvider(): array
    {
        return [
            'negation with !' => ['!circus', '!circus'],
            'negation with -' => ['-circus', '-circus'],
            'word + negation' => ['dead !circus', 'dead !circus'],
            'word + hyphen negation' => ['dead -circus', 'dead -circus'],
            'multiple negations' => ['!foo !bar', '!foo !bar'],
        ];
    }

    #[Test]
    #[DataProvider('phraseQueriesProvider')]
    public function it_preserves_phrase_search(string $input, string $expected): void
    {
        $result = ManticoreSearchDriver::prepareUserSearchQuery($input);
        $this->assertSame($expected, $result);
    }

    public static function phraseQueriesProvider(): array
    {
        return [
            'exact phrase' => ['"exact phrase"', '"exact phrase"'],
            'negated phrase with !' => ['!"exact phrase"', '!"exact phrase"'],
            'negated phrase with -' => ['-"exact phrase"', '-"exact phrase"'],
            'word + phrase' => ['hello "world peace"', 'hello "world peace"'],
        ];
    }

    #[Test]
    #[DataProvider('orQueriesProvider')]
    public function it_preserves_or_operator(string $input, string $expected): void
    {
        $result = ManticoreSearchDriver::prepareUserSearchQuery($input);
        $this->assertSame($expected, $result);
    }

    public static function orQueriesProvider(): array
    {
        return [
            'basic OR' => ['cats | dogs', 'cats | dogs'],
            'OR with negation' => ['cats | -dogs', 'cats | -dogs'],
        ];
    }

    #[Test]
    #[DataProvider('wildcardQueriesProvider')]
    public function it_preserves_wildcards(string $input, string $expected): void
    {
        $result = ManticoreSearchDriver::prepareUserSearchQuery($input);
        $this->assertSame($expected, $result);
    }

    public static function wildcardQueriesProvider(): array
    {
        return [
            'suffix wildcard' => ['test*', 'test*'],
            'prefix wildcard' => ['*fix', '*fix'],
            'both wildcards' => ['*mid*', '*mid*'],
        ];
    }

    #[Test]
    #[DataProvider('groupingQueriesProvider')]
    public function it_preserves_grouping_parens(string $input, string $expected): void
    {
        $result = ManticoreSearchDriver::prepareUserSearchQuery($input);
        $this->assertSame($expected, $result);
    }

    public static function groupingQueriesProvider(): array
    {
        return [
            'basic grouping' => ['(cats | dogs)', '(cats | dogs)'],
            'grouping with negation' => ['(cats | dogs) -birds', '(cats | dogs) -birds'],
        ];
    }

    #[Test]
    #[DataProvider('escapingQueriesProvider')]
    public function it_still_escapes_dangerous_characters(string $input, string $expected): void
    {
        $result = ManticoreSearchDriver::prepareUserSearchQuery($input);
        $this->assertSame($expected, $result);
    }

    public static function escapingQueriesProvider(): array
    {
        return [
            'escapes @' => ['@field test', '\@field test'],
            'escapes ~' => ['test~2', 'test\~2'],
            'escapes $' => ['test$', 'test\$'],
            'mid-word hyphen escaped' => ['spider-man', 'spider\-man'],
            'mid-word ! escaped' => ['wow!great', 'wow\!great'],
        ];
    }

    #[Test]
    #[DataProvider('edgeCaseQueriesProvider')]
    public function it_handles_edge_cases(string $input, string $expected): void
    {
        $result = ManticoreSearchDriver::prepareUserSearchQuery($input);
        $this->assertSame($expected, $result);
    }

    public static function edgeCaseQueriesProvider(): array
    {
        return [
            'empty string' => ['', ''],
            'only whitespace' => ['   ', ''],
            'just asterisk' => ['*', ''],
            'plain word' => ['circus', 'circus'],
            'multiple words' => ['hello world', 'hello world'],
        ];
    }

    #[Test]
    public function escape_string_still_escapes_everything(): void
    {
        // Verify the original escapeString still escapes operators (for non-search use cases)
        $result = ManticoreSearchDriver::escapeString('!circus');
        $this->assertStringContainsString('\!', $result);
        $this->assertStringContainsString('circus', $result);
    }

    #[Test]
    #[DataProvider('negationDetectionProvider')]
    public function it_detects_negation_operators(array|string $input, bool $expected): void
    {
        $result = ManticoreSearchDriver::queryHasNegation($input);
        $this->assertSame($expected, $result);
    }

    public static function negationDetectionProvider(): array
    {
        return [
            'bang negation string' => ['!harry', true],
            'hyphen negation string' => ['-harry', true],
            'word + negation string' => ['dead !circus', true],
            'word + hyphen negation string' => ['dead -circus', true],
            'plain word string' => ['harry', false],
            'multiple words string' => ['harry potter', false],
            'mid-word hyphen string' => ['spider-man', false],
            'empty string' => ['', false],
            'negation in array value' => [['searchname' => '!harry'], true],
            'plain word in array value' => [['searchname' => 'harry'], false],
            'multiple fields with negation' => [['searchname' => 'potter', 'name' => '!harry'], true],
            'empty array' => [[], false],
            'array with -1 values' => [['searchname' => '-1'], false],
        ];
    }
}
