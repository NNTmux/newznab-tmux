<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Category;
use App\Services\Categorization\Categorizers\XxxCategorizer;
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class XxxCategorizationTest extends TestCase
{
    /**
     * @return list<object>
     */
    private function buildPipes(): array
    {
        return [
            new MiscPipe,
            new GroupNamePipe,
            new XxxPipe,
            new TvPipe,
            new MoviePipe,
            new BookPipe,
            new MusicPipe,
            new PcPipe,
            new ConsolePipe,
            new MiscSafetyNetPipe,
        ];
    }

    private function runPipeline(string $releaseName, string $groupName = ''): CategorizationPassable
    {
        $context = new ReleaseContext(
            releaseName: $releaseName,
            groupId: 0,
            groupName: $groupName,
            poster: '',
        );

        $passable = new CategorizationPassable($context, debug: true);
        $pipes = $this->buildPipes();

        foreach ($pipes as $pipe) {
            $passable = $pipe->handle($passable, fn ($p) => $p);
        }

        return $passable;
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: string}>
     */
    public static function vrReleasesProvider(): array
    {
        return [
            'StockingsVR GearVR release' => [
                'StockingsVR.com.Lady.Lyne.You.Can.Watch.with.DD.Cup.Lady.Lyne.GearVR.1080p',
                Category::XXX_VR,
                'vr_site',
            ],
            'Known VR site with VR180' => [
                'SexBabesVR.23.10.20.Virtual.Reality.VR180.3D.SBS.2160p-VRSins',
                Category::XXX_VR,
                'vr_site',
            ],
            'Generic VR site with GearVR' => [
                'SomeNewSiteVR.com.Performer.GearVR.1080p',
                Category::XXX_VR,
                'vr_site_generic',
            ],
            'VR device with known studio' => [
                'Brazzers.24.01.15.Performer.Oculus.Quest3.1080p',
                Category::XXX_VR,
                'vr_device',
            ],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function nonVrReleasesProvider(): array
    {
        return [
            'Regular movie release' => [
                'Some.Movie.2024.1080p.BluRay.x264-GROUP',
                Category::MOVIE_HD,
            ],
        ];
    }

    #[DataProvider('vrReleasesProvider')]
    public function test_vr_releases_are_categorized_as_xxx_vr(string $releaseName, int $expectedCategoryId, string $expectedMatchedBy): void
    {
        $categorizer = new XxxCategorizer;
        $context = new ReleaseContext(releaseName: $releaseName, groupId: 0);
        $result = $categorizer->categorize($context);

        $this->assertTrue($result->isSuccessful(), "Expected successful match for: {$releaseName}");
        $this->assertSame($expectedCategoryId, $result->categoryId, "Wrong category for: {$releaseName}");
        $this->assertSame($expectedMatchedBy, $result->matchedBy, "Wrong matched_by for: {$releaseName}");
    }

    #[DataProvider('vrReleasesProvider')]
    public function test_vr_releases_through_full_pipeline(string $releaseName, int $expectedCategoryId, string $expectedMatchedBy): void
    {
        $passable = $this->runPipeline($releaseName);

        $this->assertSame($expectedCategoryId, $passable->bestResult->categoryId, "Pipeline wrong category for: {$releaseName}");
        $this->assertSame($expectedMatchedBy, $passable->bestResult->matchedBy, "Pipeline wrong matched_by for: {$releaseName}");
    }

    #[DataProvider('nonVrReleasesProvider')]
    public function test_non_vr_releases_are_not_categorized_as_xxx_vr(string $releaseName, int $expectedCategoryId): void
    {
        $passable = $this->runPipeline($releaseName);

        $this->assertNotSame(Category::XXX_VR, $passable->bestResult->categoryId, "Should not be XXX VR: {$releaseName}");
        $this->assertSame($expectedCategoryId, $passable->bestResult->categoryId, "Wrong category for: {$releaseName}");
    }

    public function test_lady_lyne_dotted_name_is_recognized_as_adult(): void
    {
        $categorizer = new XxxCategorizer;
        $context = new ReleaseContext(
            releaseName: 'Lady.Lyne.24.01.15.Performer.Title.1080p',
            groupId: 0,
        );
        $result = $categorizer->categorize($context);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(Category::XXX_CLIPHD, $result->categoryId);
    }

    public function test_lady_and_lyne_as_separate_tokens_do_not_trigger_xxx(): void
    {
        $categorizer = new XxxCategorizer;
        $context = new ReleaseContext(
            releaseName: 'The.Lady.and.the.Lyne.2022.1080p.WEB-DL.x264-GROUP',
            groupId: 0,
        );
        $result = $categorizer->categorize($context);

        $this->assertFalse($result->isSuccessful());
    }
}
