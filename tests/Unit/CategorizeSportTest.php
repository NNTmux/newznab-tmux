<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Category;
use App\Services\Categorization\Categorizers\TvCategorizer;
use App\Services\Categorization\ReleaseContext;
use App\Services\NameFixing\NzbSplitUnwrapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategorizeSportTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function nzbSplitSportProvider(): array
    {
        return [
            'mlb reds rays' => [
                'MLB__NZBSPLIT__f5054661a1d468cc6a8712de8217c1c9__NZBSPLIT__MLB_2026_Cincinnati_Reds_vs_Tampa_Bay_Rays_20_04_720pEN60fps.7z.075',
                'MLB.2026.Cincinnati.Reds.vs.Tampa.Bay.Rays.20.04.720pEN60fps',
            ],
            'winter olympics closing ceremony' => [
                'WinterOlympics2026__NZBSPLIT__0456f274737cea074abd86a89144cc7b__NZBSPLIT__Winter_Olympic_Games_Milano_Cortina_2026_Closing_Ceremony_1080p25_WEB-DL_(MultiAudio).7z.065',
                'Winter.Olympic.Games.Milano.Cortina.2026.Closing.Ceremony.1080p25.WEB-DL.(MultiAudio)',
            ],
            'nba playoffs wolves nuggets' => [
                'NBA__NZBSPLIT__9b1e889d7fb8d8a63c21217266069c71__NZBSPLIT__NBA_2026_Playoffs_Minnesota_Timberwolves_vs_Denver_Nuggets_Game_2_20_04_1080pEN60fps_NBC.7z.074',
                'NBA.2026.Playoffs.Minnesota.Timberwolves.vs.Denver.Nuggets.Game.2.20.04.1080pEN60fps.NBC',
            ],
            'nba abc feed' => [
                'NBA__NZBSPLIT__bdab31d6f79989608009e7e8eadcbe66__NZBSPLIT__NBA_20260419_PHI_BOS_1080p60_ABC.7z.073',
                'NBA.20260419.PHI.BOS.1080p60.ABC',
            ],
        ];
    }

    #[DataProvider('nzbSplitSportProvider')]
    public function test_unwrapped_nzb_split_sports_are_classified_as_tv_sport(string $wrapped, string $expectedName): void
    {
        $unwrapper = new NzbSplitUnwrapper;
        $categorizer = new TvCategorizer;

        $unwrapped = $unwrapper->unwrap($wrapped);

        $this->assertSame($expectedName, $unwrapped);

        $context = new ReleaseContext(
            releaseName: $unwrapped ?? '',
            groupId: 0,
            groupName: '',
            poster: ''
        );

        $result = $categorizer->categorize($context);

        $this->assertTrue($result->isSuccessful(), "Expected TV sport match for: {$wrapped}");
        $this->assertSame(Category::TV_SPORT, $result->categoryId, "Expected TV_SPORT for: {$wrapped}");
        $this->assertNotSame(Category::MOVIE_HD, $result->categoryId);
    }

    public function test_plain_epl_release_is_classified_as_tv_sport(): void
    {
        $categorizer = new TvCategorizer;

        $context = new ReleaseContext(
            releaseName: 'EPL.2026.Tottenham.vs.Brighton.18.04.720pEN60fps.Fubo',
            groupId: 0,
            groupName: '',
            poster: ''
        );

        $result = $categorizer->categorize($context);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(Category::TV_SPORT, $result->categoryId);
        $this->assertNotSame(Category::MOVIE_HD, $result->categoryId);
    }
}
