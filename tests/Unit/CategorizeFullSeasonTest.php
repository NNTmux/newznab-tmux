<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Category;
use App\Services\Categorization\Categorizers\MovieCategorizer;
use App\Services\Categorization\Categorizers\TvCategorizer;
use App\Services\Categorization\ReleaseContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategorizeFullSeasonTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function fullSeasonProvider(): array
    {
        return [
            'reported dotted release' => [
                'Tale.of.the.Nine.Tailed.S02.2023.1080p.AMZN.WEB-DL.x264.DDP2.0-ADWeb',
                Category::TV_WEBDL,
            ],
            'season at start' => [
                'S1.2023.Show.Name.1080p.AMZN.WEB-DL.x264-GROUP',
                Category::TV_WEBDL,
            ],
            'dash separated season' => [
                'Show-Name-S2-2023-1080p-WEB-DL-GROUP',
                Category::TV_WEBDL,
            ],
            'underscore separated season' => [
                'Show_Name_S12_2023_1080p_WEB-DL_GROUP',
                Category::TV_WEBDL,
            ],
            'space separated season' => [
                'Show Name S999 2023 1080p WEB-DL GROUP',
                Category::TV_WEBDL,
            ],
            'season without quality markers' => [
                'Show.Name.S03.2023-GROUP',
                Category::TV_OTHER,
            ],
        ];
    }

    #[DataProvider('fullSeasonProvider')]
    public function test_standalone_season_tokens_are_categorized_as_tv(string $name, int $expectedCategory): void
    {
        $context = $this->context($name);

        $tvResult = (new TvCategorizer)->categorize($context);

        $this->assertTrue($context->hasStandaloneSeasonToken());
        $this->assertSame($expectedCategory, $tvResult->categoryId);
        $this->assertTrue((new MovieCategorizer)->shouldSkip($context));
        $this->assertNotSame(Category::MOVIE_WEBDL, $tvResult->categoryId);
    }

    public function test_embedded_season_like_text_is_not_treated_as_a_full_season(): void
    {
        $context = $this->context('Movie.Name.AS02.2023.1080p.AMZN.WEB-DL.x264-GROUP');
        $movieCategorizer = new MovieCategorizer;

        $this->assertFalse($context->hasStandaloneSeasonToken());
        $this->assertFalse($movieCategorizer->shouldSkip($context));
        $this->assertSame(Category::MOVIE_WEBDL, $movieCategorizer->categorize($context)->categoryId);
    }

    public function test_normal_year_based_movie_remains_a_movie(): void
    {
        $context = $this->context('Oppenheimer.2023.1080p.AMZN.WEB-DL.x264-GROUP');
        $movieCategorizer = new MovieCategorizer;

        $this->assertFalse($context->hasStandaloneSeasonToken());
        $this->assertFalse($movieCategorizer->shouldSkip($context));
        $this->assertSame(Category::MOVIE_WEBDL, $movieCategorizer->categorize($context)->categoryId);
    }

    public function test_existing_episode_pattern_remains_tv_and_is_skipped_by_movies(): void
    {
        $context = $this->context('Show.Name.S02E10.2023.1080p.AMZN.WEB-DL.x264-GROUP');

        $this->assertFalse($context->hasStandaloneSeasonToken());
        $this->assertSame(Category::TV_WEBDL, (new TvCategorizer)->categorize($context)->categoryId);
        $this->assertTrue((new MovieCategorizer)->shouldSkip($context));
    }

    private function context(string $name): ReleaseContext
    {
        return new ReleaseContext(
            releaseName: $name,
            groupId: 0,
            catWebDL: true,
        );
    }
}
