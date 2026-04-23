<?php

declare(strict_types=1);

namespace Tests\Unit\Services\NameFixing;

use App\Services\NameFixing\Extractors\ObfuscatedSubjectExtractor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ObfuscatedSubjectExtractorTest extends TestCase
{
    #[Test]
    public function it_extracts_quoted_title_from_nzb_prefix(): void
    {
        $extractor = new ObfuscatedSubjectExtractor;

        $result = $extractor->extract('N:/NZB [2/5] - "History of War - Issue 158, 2026.rar"');

        $this->assertSame('History of War - Issue 158, 2026', $result);
    }

    #[Test]
    public function it_extracts_title_and_removes_par2_archive_suffix(): void
    {
        $extractor = new ObfuscatedSubjectExtractor;

        $result = $extractor->extract('N:/NZB [1/6] - "Woman\'s Day New Zealand - Issue 45 April 27, 2026.par2"');

        $this->assertSame('Woman\'s Day New Zealand - Issue 45 April 27, 2026', $result);
    }

    #[Test]
    public function it_extracts_title_and_removes_part_rar_suffix(): void
    {
        $extractor = new ObfuscatedSubjectExtractor;

        $result = $extractor->extract('N:/NZB [2/8] - "Harry Styles Songbook - 1st Edition 2026.part1.rar"');

        $this->assertSame('Harry Styles Songbook - 1st Edition 2026', $result);
    }

    #[Test]
    public function it_returns_null_for_already_clean_title(): void
    {
        $extractor = new ObfuscatedSubjectExtractor;

        $result = $extractor->extract('History of War - Issue 158, 2026');

        $this->assertNull($result);
    }
}
