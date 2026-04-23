<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Release;
use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\DTO\ReleaseProcessingContext;
use App\Services\AdditionalProcessing\ReleaseFileManager;
use App\Services\NameFixing\FileNameCleaner;
use App\Services\NameFixing\NameFixingService;
use App\Services\NameFixing\ReleaseUpdateService;
use App\Services\NfoService;
use App\Services\Nzb\NzbService;
use App\Services\ReleaseImageService;
use PHPUnit\Framework\TestCase;

class AdditionalProcessingNzbSplitRenameTest extends TestCase
{
    public function test_additional_postprocessing_renames_nzb_split_release_from_nzb_titles(): void
    {
        $wrapped = 'WinterOlympics2026__NZBSPLIT__0456f274737cea074abd86a89144cc7b__NZBSPLIT__Winter_Olympic_Games_Milano_Cortina_2026_Closing_Ceremony_1080p25_WEB-DL_(MultiAudio).7z.065" yEnc(1';
        $expected = 'Winter.Olympic.Games.Milano.Cortina.2026.Closing.Ceremony.1080p25.WEB-DL.(MultiAudio)';

        $release = new Release([
            'id' => 123,
            'releases_id' => 123,
            'name' => $wrapped,
            'searchname' => $wrapped,
            'fromname' => 'poster@example.com',
            'guid' => str_repeat('a', 40),
            'groups_id' => 1,
            'categories_id' => Category::OTHER_HASHED,
        ]);

        $context = new ReleaseProcessingContext($release);
        $updateService = $this->createMock(ReleaseUpdateService::class);
        $updateService->expects($this->once())
            ->method('updateRelease')
            ->with(
                $release,
                $expected,
                'NZBSPLIT wrapper',
                true,
                'Filenames, ',
                true,
                true
            );

        $manager = $this->makeReleaseFileManager($updateService);

        $renamed = $manager->processReleaseNameFromNzbContents([
            ['title' => $wrapped],
        ], $context);

        $this->assertTrue($renamed);
        $this->assertSame($expected, $context->release->searchname);
    }

    public function test_additional_postprocessing_skips_non_nzbsplit_wrapped_titles(): void
    {
        $wrapped = "N_NZB_[1_6]_-_Woman's_Day_New_Zealand_-_Issue_45_April_27_2026.par2";

        $release = new Release([
            'id' => 125,
            'releases_id' => 125,
            'name' => $wrapped,
            'searchname' => $wrapped,
            'fromname' => 'poster@example.com',
            'guid' => str_repeat('b', 40),
            'groups_id' => 1,
            'categories_id' => Category::OTHER_HASHED,
        ]);

        $context = new ReleaseProcessingContext($release);
        $updateService = $this->createMock(ReleaseUpdateService::class);
        $updateService->expects($this->never())->method('updateRelease');

        $manager = $this->makeReleaseFileManager($updateService);

        $renamed = $manager->processReleaseNameFromNzbContents([
            ['title' => $wrapped],
        ], $context);

        $this->assertFalse($renamed);
        $this->assertSame($wrapped, $context->release->searchname);
    }

    public function test_additional_postprocessing_skips_low_information_nzb_split_payloads(): void
    {
        $wrapped = 'TEST__NZBSPLIT__1234567890abcdef__NZBSPLIT__setup.7z.001';
        $release = new Release([
            'id' => 124,
            'releases_id' => 124,
            'name' => $wrapped,
            'searchname' => $wrapped,
            'groups_id' => 1,
            'categories_id' => Category::OTHER_HASHED,
        ]);

        $context = new ReleaseProcessingContext($release);
        $updateService = $this->createMock(ReleaseUpdateService::class);
        $updateService->expects($this->never())->method('updateRelease');

        $manager = $this->makeReleaseFileManager($updateService);

        $renamed = $manager->processReleaseNameFromNzbContents([
            ['title' => $wrapped],
        ], $context);

        $this->assertFalse($renamed);
        $this->assertSame($wrapped, $context->release->searchname);
    }

    public function test_additional_postprocessing_does_not_rename_movie_style_obfuscated_subjects(): void
    {
        $wrapped = '[1/6] - "Yoh! Bestie 2026 1080p NF WEB-DL H 264 DDP5 1-UBWEB.par2" yEnc';
        $existingName = 'Yoh!_Bestie_2026_1080p_NF_WEB-DL_H_264_DDP5_1-UBWEB';
        $release = new Release([
            'id' => 126,
            'releases_id' => 126,
            'name' => $wrapped,
            'searchname' => $existingName,
            'groups_id' => 1,
            'categories_id' => Category::MOVIE_HD,
        ]);

        $context = new ReleaseProcessingContext($release);
        $updateService = $this->createMock(ReleaseUpdateService::class);
        $updateService->expects($this->never())->method('updateRelease');

        $manager = $this->makeReleaseFileManager($updateService);

        $renamed = $manager->processReleaseNameFromNzbContents([
            ['title' => $wrapped],
        ], $context);

        $this->assertFalse($renamed);
        $this->assertSame($existingName, $context->release->searchname);
    }

    public function test_archive_name_extractor_unwraps_nzb_split_file_names(): void
    {
        $manager = $this->makeReleaseFileManager();
        $extractMethod = new \ReflectionMethod($manager, 'extractReleaseNameFromFile');

        $result = $extractMethod->invoke(
            $manager,
            'MLB__NZBSPLIT__f5054661a1d468cc6a8712de8217c1c9__NZBSPLIT__MLB_2026_Cincinnati_Reds_vs_Tampa_Bay_Rays_20_04_720pEN60fps.7z.075'
        );

        $this->assertSame('MLB.2026.Cincinnati.Reds.vs.Tampa.Bay.Rays.20.04.720pEN60fps', $result);
    }

    private function makeReleaseFileManager(?ReleaseUpdateService $updateService = null): ReleaseFileManager
    {
        $updateService ??= $this->createMock(ReleaseUpdateService::class);

        return new ReleaseFileManager(
            $this->makeConfiguration(),
            $this->createMock(ReleaseImageService::class),
            $this->createMock(NfoService::class),
            $this->createMock(NzbService::class),
            $this->createMock(NameFixingService::class),
            $updateService,
            new FileNameCleaner
        );
    }

    private function makeConfiguration(): ProcessingConfiguration
    {
        $reflection = new \ReflectionClass(ProcessingConfiguration::class);

        /** @var ProcessingConfiguration $config */
        $config = $reflection->newInstanceWithoutConstructor();

        return $config;
    }
}
