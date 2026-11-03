<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\NameFixing\FileNameCleaner;
use App\Services\ReleaseCleaningService;
use Tests\TestCase;

class NzbSplitSampleCleaningTest extends TestCase
{
    private const string SAMPLE = 'MOTD__NZBSPLIT__16fdb79df730a11998d802bc408b8101__NZBSPLIT__match.of.the.day.2026.05.17.1080p.hdtv.h264-ftp.sample.7z.001';

    public function test_fixer_cleaner_keeps_the_nzb_split_sample_marker(): void
    {
        $this->assertSame(
            'match.of.the.day.2026.05.17.1080p.hdtv.h264-ftp.sample',
            (new ReleaseCleaningService)->fixerCleaner(self::SAMPLE)
        );
    }

    public function test_fixer_cleaner_still_strips_the_sample_marker_from_ordinary_names(): void
    {
        $this->assertSame(
            'match.of.the.day.2026.05.17.1080p.hdtv.h264-ftp',
            (new ReleaseCleaningService)->fixerCleaner('match.of.the.day.2026.05.17.1080p.hdtv.h264-ftp.sample')
        );
    }

    public function test_nzb_split_sample_never_drives_a_predb_match(): void
    {
        $cleaner = new FileNameCleaner;

        $this->assertFalse($cleaner->cleanForMatching(self::SAMPLE));
        $this->assertSame(
            'MLB.RS.2026.Detroit.Tigers.vs.Chicago.White.Sox.20.09.720pEN60fps.DSN',
            $cleaner->cleanForMatching('MLB__NZBSPLIT__d5e69bbf16e52f8bc43063b814526b7f__NZBSPLIT__MLB_RS_2026_Detroit_Tigers_vs_Chicago_White_Sox_20_09_720pEN60fps_DSN.7z.080')
        );
    }
}
