<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\NameFixing\NzbSplitUnwrapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NzbSplitUnwrapperTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function wrappedReleaseProvider(): array
    {
        return [
            'winter olympics closing ceremony' => [
                'WinterOlympics2026__NZBSPLIT__0456f274737cea074abd86a89144cc7b__NZBSPLIT__Winter_Olympic_Games_Milano_Cortina_2026_Closing_Ceremony_1080p25_WEB-DL_(MultiAudio).7z.065',
                'Winter.Olympic.Games.Milano.Cortina.2026.Closing.Ceremony.1080p25.WEB-DL.(MultiAudio)',
            ],
            'winter olympics with yenc tail' => [
                'WinterOlympics2026__NZBSPLIT__0456f274737cea074abd86a89144cc7b__NZBSPLIT__Winter_Olympic_Games_Milano_Cortina_2026_Closing_Ceremony_1080p25_WEB-DL_(MultiAudio).7z.065" yEnc(1',
                'Winter.Olympic.Games.Milano.Cortina.2026.Closing.Ceremony.1080p25.WEB-DL.(MultiAudio)',
            ],
            'mlb reds rays' => [
                'MLB__NZBSPLIT__f5054661a1d468cc6a8712de8217c1c9__NZBSPLIT__MLB_2026_Cincinnati_Reds_vs_Tampa_Bay_Rays_20_04_720pEN60fps.7z.075',
                'MLB.2026.Cincinnati.Reds.vs.Tampa.Bay.Rays.20.04.720pEN60fps',
            ],
            'nba playoffs wolves nuggets' => [
                'NBA__NZBSPLIT__9b1e889d7fb8d8a63c21217266069c71__NZBSPLIT__NBA_2026_Playoffs_Minnesota_Timberwolves_vs_Denver_Nuggets_Game_2_20_04_1080pEN60fps_NBC.7z.074',
                'NBA.2026.Playoffs.Minnesota.Timberwolves.vs.Denver.Nuggets.Game.2.20.04.1080pEN60fps.NBC',
            ],
            'nba abc feed' => [
                'NBA__NZBSPLIT__bdab31d6f79989608009e7e8eadcbe66__NZBSPLIT__NBA_20260419_PHI_BOS_1080p60_ABC.7z.073',
                'NBA.20260419.PHI.BOS.1080p60.ABC',
            ],
            'mlb blue jays diamondbacks' => [
                'MLB__NZBSPLIT__bc07883ca7b3c29e5cca57b8ebef3ef7__NZBSPLIT__MLB_2026_Toronto_Maple_Leafs_vs_Arizona_Diamondbacks_19_04_720pEN60fps_SN1.7z.028',
                'MLB.2026.Toronto.Maple.Leafs.vs.Arizona.Diamondbacks.19.04.720pEN60fps.SN1',
            ],
            'mlb padres dodgers' => [
                'MLB__NZBSPLIT__9a0239e72a5201f889929a81eac53d9a__NZBSPLIT__MLB_2026_San_Diego_Padres_vs_Los_Angeles_Angels_19_04_720pEN60fps.7z.057',
                'MLB.2026.San.Diego.Padres.vs.Los.Angeles.Angels.19.04.720pEN60fps',
            ],
            'multipart rar' => [
                'TV__NZBSPLIT__1234567890abcdef__NZBSPLIT__Some_Show_2026_Episode_01_1080p_WEB-DL.part01.rar',
                'Some.Show.2026.Episode.01.1080p.WEB-DL',
            ],
            'scene rxx archive' => [
                'MOVIE__NZBSPLIT__1234567890abcdef__NZBSPLIT__Some_Movie_2026_1080p_WEB-DL-GROUP.r05',
                'Some.Movie.2026.1080p.WEB-DL-GROUP',
            ],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidWrappedReleaseProvider(): array
    {
        return [
            'missing marker' => ['Regular.Release.Name.2026.1080p.WEB-DL'],
            'non hex hash' => ['NBA__NZBSPLIT__not-a-hex-hash__NZBSPLIT__NBA_2026_Finals_Game_1_1080p.7z.001'],
            'short hash' => ['NBA__NZBSPLIT__abc1234__NZBSPLIT__NBA_2026_Finals_Game_1_1080p.7z.001'],
        ];
    }

    #[DataProvider('wrappedReleaseProvider')]
    public function test_unwrap_returns_embedded_release_name(string $wrapped, string $expected): void
    {
        $unwrapper = new NzbSplitUnwrapper;

        $this->assertSame($expected, $unwrapper->unwrap($wrapped));
    }

    #[DataProvider('invalidWrappedReleaseProvider')]
    public function test_unwrap_returns_null_for_invalid_or_non_wrapped_input(string $wrapped): void
    {
        $unwrapper = new NzbSplitUnwrapper;

        $this->assertNull($unwrapper->unwrap($wrapped));
    }
}
