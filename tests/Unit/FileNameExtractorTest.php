<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\NameFixing\Extractors\FileNameExtractor;
use PHPUnit\Framework\TestCase;

class FileNameExtractorTest extends TestCase
{
    public function test_extracts_release_name_from_nzb_split_wrapper(): void
    {
        $extractor = new FileNameExtractor;

        $result = $extractor->extractFromFile(
            'NBA__NZBSPLIT__bdab31d6f79989608009e7e8eadcbe66__NZBSPLIT__NBA_20260419_PHI_BOS_1080p60_ABC.7z.073'
        );

        $this->assertNotNull($result);
        $this->assertSame('NBA.20260419.PHI.BOS.1080p60.ABC', $result->newName);
        $this->assertSame('NZBSPLIT wrapper', $result->method);
        $this->assertSame('File', $result->checkerName);
    }

    public function test_rejects_low_information_nzb_split_payloads(): void
    {
        $extractor = new FileNameExtractor;

        $result = $extractor->extractFromFile(
            'TEST__NZBSPLIT__1234567890abcdef__NZBSPLIT__setup.7z.001'
        );

        $this->assertNull($result);
    }
}
