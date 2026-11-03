<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SubjectFileCounter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SubjectFileCounterTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: array{filenumber: int, totalfiles: int}|null}>
     */
    public static function subjects(): iterable
    {
        // Formats the previous regex already handled.
        yield 'brackets' => ['[047/112] "79AkCnv41irNMD4objHSnKpPcMfU.part046.rar" yEnc', ['filenumber' => 47, 'totalfiles' => 112]];
        yield 'leading parentheses' => ['(025/104) - P2H - "7HXN86Z35492PR.part024.rar" - 4,81 GB - Z9F6KN7SXRCY845 yEnc', ['filenumber' => 25, 'totalfiles' => 104]];
        yield 'of separator' => ['Some.Post 3 of 12 "file.rar" yEnc', ['filenumber' => 3, 'totalfiles' => 12]];
        yield 'dash separator' => ['Some.Post (3-12) "file.rar" yEnc', ['filenumber' => 3, 'totalfiles' => 12]];
        // Formats seen in production that used to split posts into one collection per file.
        yield 'dash after bracket' => ['DOA-OEEANBRN[-039/102] - "DOA-OEEANBRN.part038.rar" yEnc', ['filenumber' => 39, 'totalfiles' => 102]];
        yield 'space after slash' => ["Manfred Mann's Earth Band 1 - nightsteff [062/ 115] \"1973 - Messin'.part04.rar\" yEnc", ['filenumber' => 62, 'totalfiles' => 115]];
        yield 'no counter' => ['"4daf2a7aff25c4c5a40003d64d68eee3" yEnc', null];
        yield 'digits glued to text are not a counter' => ['Show.S01-12abc "x.mkv" yEnc', null];
    }

    /**
     * @param  array{filenumber: int, totalfiles: int}|null  $expected
     */
    #[DataProvider('subjects')]
    public function test_parse_finds_the_file_counter(string $subject, ?array $expected): void
    {
        $this->assertSame($expected, SubjectFileCounter::parse($subject));
    }

    public function test_strip_removes_counter_and_its_brackets(): void
    {
        $this->assertSame('DOA-OEEANBRN  - "a.rar" yEnc', SubjectFileCounter::strip('DOA-OEEANBRN[-039/102] - "a.rar" yEnc'));
        $this->assertSame('Earth Band   "b.rar" yEnc', SubjectFileCounter::strip('Earth Band [062/ 115] "b.rar" yEnc'));
        $this->assertSame('  - Description', SubjectFileCounter::strip('(121/229) - Description'));
    }
}
