<?php

declare(strict_types=1);

namespace Tests\Unit\Services\NameFixing;

use App\Services\NameFixing\FileNameCleaner;
use PHPUnit\Framework\TestCase;

class FileNameCleanerTest extends TestCase
{
    public function test_clean_for_matching_rejects_url_shortcuts(): void
    {
        $cleaner = new FileNameCleaner;

        $this->assertFalse($cleaner->cleanForMatching('Film ;-)/Extreem Online Contact.url'));
    }

    public function test_clean_for_matching_rejects_generic_dvd_structure_files(): void
    {
        $cleaner = new FileNameCleaner;

        $this->assertFalse($cleaner->cleanForMatching('Film ;-)/VIDEO_TS/VIDEO_TS.VOB'));
        $this->assertFalse($cleaner->cleanForMatching('Film ;-)/VIDEO_TS/VTS_01_1.VOB'));
        $this->assertFalse($cleaner->cleanForMatching('Film ;-)/1.jpg'));
    }

    public function test_clean_for_matching_keeps_useful_cover_names(): void
    {
        $cleaner = new FileNameCleaner;

        $this->assertSame('cover the fisher king', $cleaner->cleanForMatching('Film ;-)/cover the fisher king.jpg'));
    }
}
