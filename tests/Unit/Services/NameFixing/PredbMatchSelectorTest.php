<?php

declare(strict_types=1);

namespace Tests\Unit\Services\NameFixing;

use App\Services\NameFixing\PredbMatchGuard;
use App\Services\NameFixing\PredbMatchSelector;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_it_rejects_unrelated_release_sharing_only_date_and_resolution(): void
    {
        $selector = new PredbMatchSelector;

        $bestMatch = $selector->selectBestMatch(
            'NubileFilms.Molly.Little.My.Little.Seductress.S50E7.26.04.07.2160p',
            [
                [
                    'id' => 1,
                    'title' => 'Private.26.04.07.Alice.Ross.And.Nata.Gold.XXX.2160p.MP4-WRB',
                    'filename' => 'Private.26.04.07.Alice.Ross.And.Nata.Gold.XXX.2160p.MP4-WRB.mp4',
                    'source' => 'srrdb',
                ],
                [
                    'id' => 2,
                    'title' => 'SomeOther.26.04.07.Random.Title.XXX.2160p.MP4-GRP',
                    'filename' => 'SomeOther.26.04.07.Random.Title.XXX.2160p.MP4-GRP.mp4',
                    'source' => 'srrdb',
                ],
            ]
        );

        $this->assertNull($bestMatch, 'Should reject all results when none share meaningful tokens with the query');
    }

    public function test_it_selects_correct_match_when_date_overlaps_with_unrelated_results(): void
    {
        $selector = new PredbMatchSelector;

        $bestMatch = $selector->selectBestMatch(
            'NubileFilms.Molly.Little.My.Little.Seductress.S50E7.26.04.07.2160p',
            [
                [
                    'id' => 1,
                    'title' => 'Private.26.04.07.Alice.Ross.And.Nata.Gold.XXX.2160p.MP4-WRB',
                    'filename' => 'Private.26.04.07.Alice.Ross.And.Nata.Gold.XXX.2160p.MP4-WRB.mp4',
                    'source' => 'srrdb',
                ],
                [
                    'id' => 2,
                    'title' => 'NubileFilms.Molly.Little.My.Little.Seductress.S50E7.26.04.07.2160p-GRP',
                    'filename' => 'NubileFilms.Molly.Little.My.Little.Seductress.S50E7.26.04.07.2160p.mp4',
                    'source' => 'srrdb',
                ],
            ]
        );

        $this->assertNotNull($bestMatch);
        $this->assertSame(2, $bestMatch['id'], 'Should select the result that shares meaningful tokens with the query');
    }

    public function test_it_ignores_filenames_from_untrusted_sources(): void
    {
        $selector = new PredbMatchSelector(null, new PredbMatchGuard(['xrel']));
        $hit = [
            'id' => 1,
            'title' => 'Completely.Different.Show.S01E01.720p.HDTV.x264-GRP',
            'filename' => 'Holiday.Video.Collection.2023.mkv',
        ];

        $this->assertNull($selector->selectBestMatch('Holiday.Video.Collection.2023', [$hit + ['source' => 'xrel']]));
        $this->assertSame(1, $selector->selectBestMatch('Holiday.Video.Collection.2023', [$hit + ['source' => 'srrdb']])['id'] ?? null);
    }

    public function test_it_does_not_match_on_generic_filenames(): void
    {
        $selector = new PredbMatchSelector(null, new PredbMatchGuard(['xrel']));

        $this->assertNull($selector->selectBestMatch('installer', [[
            'id' => 1,
            'title' => 'Some.Application.v1.2.Incl.Keygen-GRP',
            'filename' => 'installer.exe',
            'source' => 'srrdb',
        ]]));
    }

    public function test_untrusted_sources_only_match_an_identical_title(): void
    {
        $selector = new PredbMatchSelector(null, new PredbMatchGuard(['xrel', 'xrelp2p']));
        $query = 'Nico.1988.German.AC3.DL.1080p.BluRay.x265-FuN';

        $this->assertNull($selector->selectBestMatch($query, [[
            'id' => 1,
            'title' => 'Nico.1988.German.AC3.DL.1080p.BluRay.x264-OTHER',
            'filename' => '',
            'source' => 'xrelp2p',
        ]]));
        $this->assertSame(2, $selector->selectBestMatch($query, [[
            'id' => 2,
            'title' => $query,
            'filename' => '',
            'source' => 'xrelp2p',
        ]])['id'] ?? null);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function conflictingReleases(): array
    {
        return [
            'codec' => ['Nico.1988.German.AC3.DL.1080p.BluRay.x265-FuN', 'Nico.1988.German.AC3.BDRiP.x264-der.sir'],
            'resolution' => ['Some.Movie.2024.German.DL.1080p.BluRay.x264-GRP', 'Some.Movie.2024.German.DL.720p.BluRay.x264-GRP'],
            'episode' => ['Some.Show.S01E05.German.1080p.WEB.h264-GRP', 'Some.Show.S01E06.German.1080p.WEB.h264-GRP'],
            'year' => ['Some.Movie.2019.German.DL.1080p.BluRay.x264-GRP', 'Some.Movie.2024.German.DL.1080p.BluRay.x264-GRP'],
        ];
    }

    #[DataProvider('conflictingReleases')]
    public function test_it_rejects_a_trusted_title_that_conflicts_on_release_traits(string $query, string $title): void
    {
        $selector = new PredbMatchSelector(null, new PredbMatchGuard(['xrel', 'xrelp2p']));

        $this->assertNull($selector->selectBestMatch($query, [[
            'id' => 1,
            'title' => $title,
            'filename' => '',
            'source' => 'srrdb',
        ]]));
    }
}
