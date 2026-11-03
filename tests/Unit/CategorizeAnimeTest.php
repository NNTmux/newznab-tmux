<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Category;
use App\Services\Categorization\Categorizers\GroupNameCategorizer;
use App\Services\Categorization\Categorizers\TvCategorizer;
use App\Services\Categorization\ReleaseContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CategorizeAnimeTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function animeReleaseProvider(): array
    {
        return [
            'animetosho poster catches non-TV-looking release' => [
                '[SSA] Cestvs - The Roman Fighter - 08 [480p]',
                'alt.binaries.multimedia.anime.highspeed',
                'Anime Tosho <usenet.bot@animetosho.org>',
            ],
            'animetosho poster catches subsplease release with hash' => [
                '[SubsPlease] Cestvs - The Roman Fighter - 08 (720p) [26BDA3C9]',
                'alt.binaries.multimedia.anime.highspeed',
                'Anime Tosho <usenet.bot@animetosho.org>',
            ],
            'animetosho poster catches yuisubs nvenc release' => [
                '[YuiSubs] Cestvs - The Roman Fighter - 08 (NVENC H.265 1080p)',
                'alt.binaries.multimedia.anime.highspeed',
                'Anime Tosho <usenet.bot@animetosho.org>',
            ],
            'animetosho poster catches nyanpasu hevc release' => [
                '[Nyanpasu] Super Cub - 09 [1080p][HEVC][19475939]',
                'alt.binaries.multimedia.anime.highspeed',
                'Anime Tosho <usenet.bot@animetosho.org>',
            ],
            'anime group name alone is enough' => [
                '[QCE] Kami-tachi ni Hirowareta Otoko - 07',
                'alt.binaries.multimedia.anime.highspeed',
                '',
            ],
            'known anime release group tag (SSA) without S/E' => [
                '[SSA] Hetalia World Stars - 10 [480p]',
                '',
                '',
            ],
            'known anime release group tag (DKB) with media wrapper' => [
                '[DKB] Kami-tachi ni Hirowareta Otoko - S01E07 [1080p][H.265 10bit]',
                '',
                '',
            ],
            'anime hash pattern alone' => [
                '[NS] Nanatsu No Taizai - Fundo No Shinpan - 21 - (1080p 10bit) [2C014CB1]',
                '',
                '',
            ],
        ];
    }

    #[DataProvider('animeReleaseProvider')]
    public function test_anime_releases_are_categorized_as_tv_anime(string $name, string $groupName, string $poster): void
    {
        $categorizer = new TvCategorizer;

        $context = new ReleaseContext(
            releaseName: $name,
            groupId: 0,
            groupName: $groupName,
            poster: $poster,
        );

        $result = $categorizer->categorize($context);

        $this->assertTrue($result->isSuccessful(), "Expected anime match for: {$name}");
        $this->assertSame(Category::TV_ANIME, $result->categoryId, "Expected TV_ANIME for: {$name} (matched_by={$result->matchedBy})");
    }

    public function test_group_name_categorizer_classifies_anime_usenet_group(): void
    {
        $categorizer = new GroupNameCategorizer;

        $context = new ReleaseContext(
            releaseName: 'some.random.release.name',
            groupId: 0,
            groupName: 'alt.binaries.multimedia.anime.highspeed',
            poster: '',
        );

        $result = $categorizer->categorize($context);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(Category::TV_ANIME, $result->categoryId);
        $this->assertSame('group_anime', $result->matchedBy);
    }
}
