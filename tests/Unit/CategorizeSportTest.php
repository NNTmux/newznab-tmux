<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Category;
use App\Services\Categorization\Categorizers\TvCategorizer;
use App\Services\Categorization\Pipes\BookPipe;
use App\Services\Categorization\Pipes\CategorizationPassable;
use App\Services\Categorization\Pipes\ConsolePipe;
use App\Services\Categorization\Pipes\GroupNamePipe;
use App\Services\Categorization\Pipes\MiscPipe;
use App\Services\Categorization\Pipes\MiscSafetyNetPipe;
use App\Services\Categorization\Pipes\MoviePipe;
use App\Services\Categorization\Pipes\MusicPipe;
use App\Services\Categorization\Pipes\PcPipe;
use App\Services\Categorization\Pipes\TvPipe;
use App\Services\Categorization\Pipes\XxxPipe;
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

    /**
     * Broadcast names that used to fall through looksLikeTV() into Movies HD.
     *
     * @return array<string, array{0: string}>
     */
    public static function untaggedSportBroadcastProvider(): array
    {
        return [
            'fifa world cup' => ['FIFA.World.Cup.2026.England.vs.Argentina.720p60.Disney+.ESPN.ARG'],
            'wnba' => ['WNBA.RS.2026.Seattle.Storm.vs.Chicago.Sky.15.07.1080pEN60fps'],
            'wimbledon fixture' => ['Wimbledon.2026.Jannik.Sinner.vs.Alexander.Zverev.12.07.720pEN50fps.ES'],
            'la liga' => ['La.Liga.2026.Levante.vs.Sevilla.23.04.720p60fps.EN.ESPNP'],
            'uefa champions league' => ['UEFA.CL.2026.Bayern.vs.Real.Madrid.1080i.HDTV.Rus'],
            'formula 2' => ['Formula.2.2026.HungaryGP.Feature.Race.26.07.1080pEN50fps.F1TV'],
            'formula e' => ['Formula.E.2026.R14.Tokyo.E-Prix.Qualifying.25.07.720pEN50fps.ES'],
            'porsche supercup typo' => ['Porshe.SuperCup.2026.BelgiumGP.Practice.17.07.1080pEN50fps.F1TV'],
            'concacaf' => ['CONCACAF.U-20.Championship.2026.United.States.U20.vs.Haiti.U20.26.07.1080p60fps.EN.FOX.Sports'],
            'champions league without league tag' => ['Champions.League.Semifinal.2026.29.04.Atletico.Madrid.vs.Arsenal.1st.leg.1080pSpa25fps'],
            'copa sudamericana' => ['Copa.Sudamericana.2026.Gremio.vs.Bolivar.30.07.720pEN60fps.Beinsport'],
        ];
    }

    #[DataProvider('untaggedSportBroadcastProvider')]
    public function test_sport_broadcasts_are_classified_as_tv_sport_through_the_pipeline(string $name): void
    {
        $this->assertSame(Category::TV_SPORT, $this->runPipeline($name)->bestResult->categoryId);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function sportTitledFilmProvider(): array
    {
        return [
            'wimbledon film' => ['Wimbledon.2004.1080p.BluRay.x264-SINNERS'],
            'world cup disc rip' => ['World.Cup.2010.1080p.BluRay.x264-GRP'],
        ];
    }

    #[DataProvider('sportTitledFilmProvider')]
    public function test_films_named_after_sport_events_stay_movies(string $name): void
    {
        $this->assertSame(Category::MOVIE_HD, $this->runPipeline($name)->bestResult->categoryId);
    }

    /**
     * Titles that name no league; only the NZBSPLIT uploader tag says they are sport.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function nzbSplitTagOnlyProvider(): array
    {
        return [
            'nhl playoffs without league' => ['Playoffs.14-05-2026.Vegas.Golden.Knights.vs.Anaheim.Ducks.R2,.GM.5.720p60.EN.TNT', 'NHL'],
            'uefa footage misread as music' => ['1-2026-2047742-Match.Footage', 'UEFA'],
            'tennis player names only' => ['Potapova,.Anastasia.-.Ostapenko,.Jelena', 'Tennis'],
            'fifa classic in german' => ['FIFA.World.Cup.Classics.Germany.v.Algeria.FIFA.World.Cup.2014.Round.of.16.GERMAN.2160p.4K.UHD-WDTeam', 'Fifa'],
        ];
    }

    #[DataProvider('nzbSplitTagOnlyProvider')]
    public function test_nzb_split_sport_tag_classifies_release_as_tv_sport(string $name, string $tag): void
    {
        $this->assertSame(Category::TV_SPORT, $this->runPipeline($name, sourceTag: $tag)->bestResult->categoryId);
    }

    public function test_nzb_split_sport_tag_does_not_override_magazine(): void
    {
        $result = $this->runPipeline('World.Soccer.June.2026.HYBRID.MAGAZINE.eBook-21A1', sourceTag: 'Fifa');

        $this->assertNotSame(Category::TV_SPORT, $result->bestResult->categoryId);
        $this->assertGreaterThanOrEqual(Category::BOOKS_ROOT, $result->bestResult->categoryId);
        $this->assertLessThan(Category::BOOKS_ROOT + 1000, $result->bestResult->categoryId);
    }

    public function test_nzb_split_pdf_content_is_classified_as_magazine(): void
    {
        $result = $this->runPipeline('World.Soccer.June.2026', sourceTag: 'Fifa', contentExtension: 'pdf');

        $this->assertSame(Category::BOOKS_MAGAZINES, $result->bestResult->categoryId);
    }

    public function test_nzb_split_sport_tag_does_not_override_tv_episode(): void
    {
        $result = $this->runPipeline('24.Legacy.S01E12.HDTV.x264-SVA', sourceTag: 'Fifa');

        $this->assertNotSame(Category::TV_SPORT, $result->bestResult->categoryId);
    }

    private function runPipeline(string $releaseName, ?string $sourceTag = null, ?string $contentExtension = null): CategorizationPassable
    {
        $passable = new CategorizationPassable(new ReleaseContext(
            releaseName: $releaseName,
            groupId: 0,
            groupName: 'alt.binaries.misc',
            sourceTag: $sourceTag,
            contentExtension: $contentExtension,
        ));

        foreach ([new MiscPipe, new GroupNamePipe, new XxxPipe, new TvPipe, new MoviePipe, new BookPipe, new MusicPipe, new PcPipe, new ConsolePipe, new MiscSafetyNetPipe] as $pipe) {
            $passable = $pipe->handle($passable, fn (CategorizationPassable $next): CategorizationPassable => $next);
        }

        return $passable;
    }
}
