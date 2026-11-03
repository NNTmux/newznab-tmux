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

    public function test_normalize_candidate_title_removes_pdf_extension(): void
    {
        $cleaner = new FileNameCleaner;

        $this->assertSame('WOB Klassik 4.25', $cleaner->normalizeCandidateTitle('WOB Klassik 4.25.Pdf'));
    }

    public function test_format_search_name_keeps_scene_titles_dotted(): void
    {
        $cleaner = new FileNameCleaner;

        $this->assertSame(
            'Southern.Charm.S11E12.Even.Further.South.720p.AMZN.WEB-DL.DDP2.0.H.264-NTb',
            $cleaner->formatSearchName('Southern Charm S11E12 Even Further South 720p AMZN WEB-DL DDP2 0 H 264-NTb.mkv')
        );
    }

    public function test_a_trailing_h264_codec_is_not_taken_for_a_split_volume(): void
    {
        $cleaner = new FileNameCleaner;
        $name = 'Gegen.den.Strom.1997.GERMAN.720p.ARD.WEB-DL.AAC2.0.H.264';

        $this->assertSame($name, $cleaner->normalizeCandidateTitle($name));
        $this->assertSame($name, $cleaner->formatSearchName($name));
        $this->assertSame('Show.S01E01.1080p.WEB.x.265', $cleaner->formatSearchName('Show.S01E01.1080p.WEB.x.265'));
    }

    public function test_split_volume_extensions_are_still_removed(): void
    {
        $cleaner = new FileNameCleaner;

        $this->assertSame('Movie.2020.1080p.BluRay.x264-GRP', $cleaner->normalizeCandidateTitle('Movie.2020.1080p.BluRay.x264-GRP.001'));
        $this->assertSame('Movie.2020.1080p.BluRay.x264-GRP', $cleaner->formatSearchName('Movie.2020.1080p.BluRay.x264-GRP.7z.001'));
        $this->assertSame('Movie.2020.1080p.BluRay.x264-GRP', $cleaner->formatSearchName('Movie.2020.1080p.BluRay.x264-GRP.002'));
    }
}
