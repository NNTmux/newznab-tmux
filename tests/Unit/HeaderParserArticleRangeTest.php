<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Binaries\HeaderParser;
use App\Services\BlacklistService;
use PHPUnit\Framework\TestCase;

class HeaderParserArticleRangeTest extends TestCase
{
    private function parser(): HeaderParser
    {
        return new HeaderParser(new class extends BlacklistService
        {
            public function __construct() {}
        });
    }

    public function test_bogus_number_outside_requested_range_is_ignored(): void
    {
        $range = $this->parser()->getArticleRange([
            ['Number' => 1001, 'Date' => 'a'],
            ['Number' => 1003, 'Date' => 'c'],
            ['Number' => 45, 'Date' => 'junk'],
        ], 1000, 2000);

        $this->assertSame(1001, $range['firstArticleNumber']);
        $this->assertSame(1003, $range['lastArticleNumber']);
        $this->assertSame('c', $range['lastArticleDate']);
    }

    public function test_out_of_order_headers_use_highest_and_lowest(): void
    {
        $range = $this->parser()->getArticleRange([
            ['Number' => 1005],
            ['Number' => 1002],
            ['Number' => 1009],
            ['Number' => 1001],
        ], 1000, 2000);

        $this->assertSame(1001, $range['firstArticleNumber']);
        $this->assertSame(1009, $range['lastArticleNumber']);
    }

    public function test_no_numbers_in_range_returns_empty(): void
    {
        $this->assertSame([], $this->parser()->getArticleRange([['Number' => 45], ['Subject' => 'x']], 1000, 2000));
    }
}
