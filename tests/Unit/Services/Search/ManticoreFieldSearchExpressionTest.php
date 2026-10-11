<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Search;

use App\Services\Search\Drivers\ManticoreSearchDriver;
use PHPUnit\Framework\TestCase;

final class ManticoreFieldSearchExpressionTest extends TestCase
{
    /**
     * `@@relaxed` is a query-wide modifier, valid once at the start. Repeating it before each field
     * (`@@relaxed @name (..) @@relaxed @searchname (..)`) made Manticore answer
     * "P08: syntax error, unexpected TOK_FIELDLIMIT", so every PreDB title match failed.
     */
    public function test_a_multi_field_search_has_a_single_leading_relaxed_modifier(): void
    {
        $expression = ManticoreSearchDriver::buildFieldSearchExpression([
            'name' => 'Some.Release.2026-GRP',
            'searchname' => 'Some.Release.2026-GRP',
        ]);

        $this->assertNotNull($expression);
        $this->assertSame(1, substr_count($expression, '@@relaxed'));
        $this->assertStringStartsWith('@@relaxed @name ', $expression);
        $this->assertStringContainsString(' @searchname ', $expression);
    }

    public function test_a_single_field_search_is_unchanged_and_empty_values_are_skipped(): void
    {
        $this->assertSame(
            '@@relaxed (@searchname (Some.Release.2026\\-GRP) | @searchname (Some Release 2026 GRP))',
            ManticoreSearchDriver::buildFieldSearchExpression(['name' => '', 'searchname' => 'Some.Release.2026-GRP', 'other' => null]),
        );
        $this->assertNull(ManticoreSearchDriver::buildFieldSearchExpression(['name' => '', 'searchname' => '']));
    }

    public function test_the_filtered_search_allowlist_drops_other_fields_and_keeps_one_modifier(): void
    {
        $expression = ManticoreSearchDriver::buildFieldSearchExpression(
            ['searchname' => 'Some.Release.2026-GRP', 'fromname' => 'poster', 'categories_id' => 5000, 0 => 'ignored'],
            ['searchname', 'fromname'],
        );

        $this->assertNotNull($expression);
        $this->assertSame(1, substr_count($expression, '@@relaxed'));
        $this->assertStringContainsString('@searchname', $expression);
        $this->assertStringContainsString('@fromname', $expression);
        $this->assertStringNotContainsString('categories_id', $expression);
        $this->assertNull(ManticoreSearchDriver::buildFieldSearchExpression(['categories_id' => 5000], ['searchname']));
    }

    public function test_the_string_zero_is_still_a_searchable_term(): void
    {
        $this->assertSame(
            '@@relaxed @searchname (0)',
            ManticoreSearchDriver::buildFieldSearchExpression(['searchname' => '0']),
        );
    }

    public function test_identical_name_and_searchname_text_is_searched_in_either_field(): void
    {
        $title = 'Some.Release.2026-GRP';

        $this->assertSame($title, ManticoreSearchDriver::sharedNameFieldText(['name' => $title, 'searchname' => $title]));
        $this->assertNull(ManticoreSearchDriver::sharedNameFieldText(['name' => $title, 'searchname' => 'Other']));
        $this->assertNull(ManticoreSearchDriver::sharedNameFieldText(['searchname' => $title]));
        $this->assertNull(ManticoreSearchDriver::sharedNameFieldText(['name' => $title, 'searchname' => $title, 'fromname' => 'poster']));
        $this->assertNull(ManticoreSearchDriver::sharedNameFieldText(['name' => '', 'searchname' => '']));

        // The multi-column selector gets the same separator alternative as a plain @searchname query.
        $scope = new \ReflectionMethod(ManticoreSearchDriver::class, 'scopeReleaseSearchQuery');
        $expression = $scope->invoke(null, $title, ManticoreSearchDriver::prepareUserSearchQuery($title), '@(name,searchname)');
        $this->assertSame('(@(name,searchname) (Some.Release.2026\\-GRP) | @(name,searchname) (Some Release 2026 GRP))', $expression);
    }
}
