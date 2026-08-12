<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Category;
use App\Services\Categorization\Pipes\CategorizationPassable;
use App\Services\Categorization\Pipes\MoviePipe;
use App\Services\Categorization\Pipes\MusicPipe;
use App\Services\Categorization\ReleaseContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategorizeMovieTest extends TestCase
{
    public function test_web_rip_movie_with_apes_in_title_stays_in_movie_category(): void
    {
        $context = new ReleaseContext(
            releaseName: 'Kingdom Of the Planet of the Apes (2024) English (1080p DS4K WEB-Rip Itunes x265 HEVC 10Bit DDP5.1 Esub - R3TiR3D).mkv',
            groupId: 0,
        );
        $passable = new CategorizationPassable($context, debug: true);

        foreach ([new MoviePipe, new MusicPipe] as $pipe) {
            $passable = $pipe->handle($passable, fn (CategorizationPassable $result): CategorizationPassable => $result);
        }

        $this->assertSame(Category::MOVIE_WEBDL, $passable->bestResult->categoryId);
    }

    public function test_parentheses_can_delimit_a_movie_year_and_resolution(): void
    {
        $context = new ReleaseContext(
            releaseName: 'Example(2024)1080p.x264.mkv',
            groupId: 0,
        );
        $passable = (new MoviePipe)->handle(
            new CategorizationPassable($context),
            fn (CategorizationPassable $result): CategorizationPassable => $result,
        );

        $this->assertSame(Category::MOVIE_HD, $passable->bestResult->categoryId);
    }

    public function test_genuine_high_resolution_flac_release_stays_lossless(): void
    {
        $context = new ReleaseContext(
            releaseName: 'Artist - Album (2023) [FLAC] [24bit 96kHz]',
            groupId: 0,
        );
        $passable = (new MusicPipe)->handle(
            new CategorizationPassable($context),
            fn (CategorizationPassable $result): CategorizationPassable => $result,
        );

        $this->assertSame(Category::MUSIC_LOSSLESS, $passable->bestResult->categoryId);
    }

    public function test_genuine_vbr_mp3_release_stays_in_mp3_category(): void
    {
        $context = new ReleaseContext(releaseName: 'Artist - Album (2023) MP3 VBR WEB', groupId: 0);
        $passable = (new MusicPipe)->handle(
            new CategorizationPassable($context),
            fn (CategorizationPassable $result): CategorizationPassable => $result,
        );

        $this->assertSame(Category::MUSIC_MP3, $passable->bestResult->categoryId);
    }

    public function test_genuine_soundtrack_release_stays_in_other_music_category(): void
    {
        $context = new ReleaseContext(releaseName: 'Movie Original Soundtrack (2024)', groupId: 0);
        $passable = (new MusicPipe)->handle(
            new CategorizationPassable($context),
            fn (CategorizationPassable $result): CategorizationPassable => $result,
        );

        $this->assertSame(Category::MUSIC_OTHER, $passable->bestResult->categoryId);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function ambiguousNonMusicTokenProvider(): array
    {
        return [
            'ape prefix' => ['Kingdom Of the Planet of the Apes (2024) 1080p WEB-Rip.mkv'],
            'wav prefix' => ['The Wave Riders (2024) 1080p WEB-Rip.mkv'],
            'standalone ape video token' => ['The Ape (2024) 1080p WEB-Rip.mkv'],
            'vbr prefix' => ['The VBridge Sessions (2024) WEB'],
            'soundtrack prefix' => ['The Soundtracks Archive (2024)'],
        ];
    }

    #[DataProvider('ambiguousNonMusicTokenProvider')]
    public function test_ambiguous_music_tokens_in_non_music_titles_do_not_categorize_as_music(string $releaseName): void
    {
        $context = new ReleaseContext(releaseName: $releaseName, groupId: 0);
        $passable = (new MusicPipe)->handle(
            new CategorizationPassable($context),
            fn (CategorizationPassable $result): CategorizationPassable => $result,
        );

        $this->assertFalse($passable->bestResult->isSuccessful());
    }
}
