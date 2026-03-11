<?php

declare(strict_types=1);

namespace Tests\Unit\Services\NameFixing;

use App\Services\NameFixing\PredbMatchSelector;
use PHPUnit\Framework\TestCase;

class PredbMatchSelectorTest extends TestCase
{
    public function test_it_prefers_the_best_matching_predb_hit_over_the_first_hit(): void
    {
        $selector = new PredbMatchSelector;

        $bestMatch = $selector->selectBestMatch('The.Fisher.King.1991.1080p.BluRay.x264-GRP', [
            [
                'id' => 1,
                'title' => '1.vs.1.Suzu.Matsuoka.JAPANESE.XXX.720p.WEBRiP.MP4-MFPF',
                'filename' => '1.vs.1.Suzu.Matsuoka.JAPANESE.XXX.720p.WEBRiP.MP4-MFPF.mp4',
            ],
            [
                'id' => 2,
                'title' => 'The.Fisher.King.1991.1080p.BluRay.x264-GRP',
                'filename' => 'The.Fisher.King.1991.1080p.BluRay.x264-GRP.mkv',
            ],
        ]);

        $this->assertNotNull($bestMatch);
        $this->assertSame(2, $bestMatch['id']);
    }

    public function test_it_rejects_numeric_only_queries(): void
    {
        $selector = new PredbMatchSelector;

        $bestMatch = $selector->selectBestMatch('1', [
            [
                'id' => 1,
                'title' => '1.vs.1.Suzu.Matsuoka.JAPANESE.XXX.720p.WEBRiP.MP4-MFPF',
                'filename' => '1.vs.1.Suzu.Matsuoka.JAPANESE.XXX.720p.WEBRiP.MP4-MFPF.mp4',
            ],
        ]);

        $this->assertNull($bestMatch);
    }

    public function test_it_rejects_generic_dvd_structure_queries(): void
    {
        $selector = new PredbMatchSelector;

        $bestMatch = $selector->selectBestMatch('VTS_01_1', [
            [
                'id' => 1,
                'title' => '1.vs.1.Suzu.Matsuoka.JAPANESE.XXX.720p.WEBRiP.MP4-MFPF',
                'filename' => '1.vs.1.Suzu.Matsuoka.JAPANESE.XXX.720p.WEBRiP.MP4-MFPF.mp4',
            ],
        ]);

        $this->assertNull($bestMatch);
    }

    public function test_it_can_match_cover_style_queries_when_the_title_tokens_line_up(): void
    {
        $selector = new PredbMatchSelector;

        $bestMatch = $selector->selectBestMatch('cover the fisher king', [
            [
                'id' => 1,
                'title' => '1.vs.1.Suzu.Matsuoka.JAPANESE.XXX.720p.WEBRiP.MP4-MFPF',
                'filename' => '1.vs.1.Suzu.Matsuoka.JAPANESE.XXX.720p.WEBRiP.MP4-MFPF.mp4',
            ],
            [
                'id' => 2,
                'title' => 'The.Fisher.King.1991.1080p.BluRay.x264-GRP',
                'filename' => 'The.Fisher.King.1991.1080p.BluRay.x264-GRP.mkv',
            ],
        ]);

        $this->assertNotNull($bestMatch);
        $this->assertSame(2, $bestMatch['id']);
    }
}
