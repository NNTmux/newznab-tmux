<?php

namespace Tests\Unit;

use App\Services\NameFixing\HouseOfUsenetCleanerService;
use PHPUnit\Framework\TestCase;

class HouseOfUsenetCleanerServiceTest extends TestCase
{
    private HouseOfUsenetCleanerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Pass false explicitly to avoid config() call which isn't available in unit tests
        $this->service = new HouseOfUsenetCleanerService(false);
    }

    public function test_detects_house_of_usenet_releases(): void
    {
        $subject1 = '[29/30] - "HostelXXX 02 - Liza Rowe - HD1080p.vol36+35.par2" - 2,08 GB Join us @ <<House-of-Usenet.com>><<PW FREE 4 ALL>>';
        $subject2 = '[29/30] - "Creampie Filling 4 DiSC4 XXX DVDRip x264-DigitalSin.vol35+34.par2" - 2,03 GB PW free 4 all from <<HOUSE-OF-USENET.COM>> <<visit us>>';
        $subject3 = '[29/30] - "Some.Release.Name.par2" - 2,08 GB yEnc';

        $this->assertTrue($this->service->isHouseOfUsenetRelease($subject1));
        $this->assertTrue($this->service->isHouseOfUsenetRelease($subject2));
        $this->assertFalse($this->service->isHouseOfUsenetRelease($subject3));
    }

    public function test_extracts_release_name_from_quoted_filename(): void
    {
        $subject1 = '[29/30] - "HostelXXX 02 - Liza Rowe - HD1080p.vol36+35.par2" - 2,08 GB Join us @ <<House-of-Usenet.com>><<PW FREE 4 ALL>>';
        $subject2 = '[29/30] - "Creampie Filling 4 DiSC4 XXX DVDRip x264-DigitalSin.vol35+34.par2" - 2,03 GB PW free 4 all from <<HOUSE-OF-USENET.COM>> <<visit us>>';
        $subject3 = '[01/50] - "Some.Movie.2024.1080p.BluRay.x264-GROUP.part01.rar" - 5,0 GB <<House-of-Usenet.com>>';

        $this->assertSame('HostelXXX 02 - Liza Rowe - HD1080p', $this->service->extractReleaseName($subject1));
        $this->assertSame('Creampie Filling 4 DiSC4 XXX DVDRip x264-DigitalSin', $this->service->extractReleaseName($subject2));
        $this->assertSame('Some.Movie.2024.1080p.BluRay.x264-GROUP', $this->service->extractReleaseName($subject3));
    }

    public function test_extracts_release_name_removes_various_extensions(): void
    {
        $testCases = [
            '[01/10] - "Release.Name.par2" - 1 GB <<House-of-Usenet.com>>' => 'Release.Name',
            '[01/10] - "Release.Name.rar" - 1 GB <<House-of-Usenet.com>>' => 'Release.Name',
            '[01/10] - "Release.Name.nfo" - 1 GB <<House-of-Usenet.com>>' => 'Release.Name',
            '[01/10] - "Release.Name.sfv" - 1 GB <<House-of-Usenet.com>>' => 'Release.Name',
            '[01/10] - "Release.Name.r01" - 1 GB <<House-of-Usenet.com>>' => 'Release.Name',
            '[01/10] - "Release.Name.part1.rar" - 1 GB <<House-of-Usenet.com>>' => 'Release.Name',
            '[01/10] - "Release.Name.vol00+01.par2" - 1 GB <<House-of-Usenet.com>>' => 'Release.Name',
        ];

        foreach ($testCases as $subject => $expected) {
            $this->assertSame($expected, $this->service->extractReleaseName($subject), "Failed for: $subject");
        }
    }

    public function test_process_subject_returns_complete_info(): void
    {
        $subject = '[29/30] - "HostelXXX 02 - Liza Rowe - HD1080p.vol36+35.par2" - 2,08 GB Join us @ <<House-of-Usenet.com>><<PW FREE 4 ALL>>';

        $result = $this->service->processSubject($subject);

        $this->assertTrue($result['matched']);
        $this->assertSame('HostelXXX 02 - Liza Rowe - HD1080p', $result['name']);
    }

    public function test_process_subject_non_matching_release(): void
    {
        $subject = '[29/30] - "Some.Normal.Release.par2" - 2,08 GB yEnc';

        $result = $this->service->processSubject($subject);

        $this->assertFalse($result['matched']);
        $this->assertNull($result['name']);
    }

    public function test_extracts_name_with_alternative_pattern(): void
    {
        // Test (##/##) pattern instead of [##/##]
        $subject = '(15/30) - "Alternative.Pattern.Release.HD720p.rar" - 1,5 GB <<House-of-Usenet.com>>';

        $name = $this->service->extractReleaseName($subject);

        $this->assertSame('Alternative.Pattern.Release.HD720p', $name);
    }

    public function test_handles_case_insensitive_house_of_usenet(): void
    {
        $subjects = [
            '[01/10] - "Test.par2" - 1 GB <<HOUSE-OF-USENET.COM>>',
            '[01/10] - "Test.par2" - 1 GB <<house-of-usenet.com>>',
            '[01/10] - "Test.par2" - 1 GB <<House-Of-Usenet.COM>>',
        ];

        foreach ($subjects as $subject) {
            $this->assertTrue($this->service->isHouseOfUsenetRelease($subject), "Failed for: $subject");
        }
    }
}
