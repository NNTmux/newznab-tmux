<?php

declare(strict_types=1);

namespace Tests\Unit\Extensions;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GroupFileSizeTest extends TestCase
{
    #[DataProvider('validSizes')]
    public function test_it_parses_group_file_sizes(string $input, int $expected): void
    {
        self::assertSame($expected, parse_group_file_size($input));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function validSizes(): iterable
    {
        yield 'bare bytes' => ['12345', 12345];
        yield 'megabytes' => ['100M', 104857600];
        yield 'decimal gigabytes' => ['2.5G', 2684354560];
        yield 'optional B suffix' => ['100MB', 104857600];
        yield 'mixed case and whitespace' => [' 100mB ', 104857600];
        yield 'zero' => ['0', 0];
    }

    #[DataProvider('invalidSizes')]
    public function test_it_rejects_unsupported_group_file_sizes(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('whole byte count or a number followed by M, MB, G, or GB');

        parse_group_file_size($input);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidSizes(): iterable
    {
        yield 'empty' => [''];
        yield 'unsupported unit' => ['10K'];
        yield 'bare decimal' => ['2.5'];
        yield 'negative' => ['-1M'];
        yield 'garbage' => ['large'];
    }
}
