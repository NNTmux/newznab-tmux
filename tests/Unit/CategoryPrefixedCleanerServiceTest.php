<?php

use App\Services\NameFixing\CategoryPrefixedCleanerService;
use Tests\TestCase;

class CategoryPrefixedCleanerServiceTest extends TestCase
{
    private CategoryPrefixedCleanerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CategoryPrefixedCleanerService(false);
    }

    public function test_detects_category_prefixed_releases(): void
    {
        $testCases = [
            '"(Porn Video) Milf Fucks Teen Guy Erotic Porn.part01.rar.vol031+32.par2" yEnc' => true,
            '"(Porn Video) Busty Babe Gets Pounded By Huge Bbc Erotic Porn.part001.rar.vol031+32.par2" yEnc' => true,
            '"(Porn Video) Busty Gilf Gangbanged Erotic Porn.part001.rar.vol031+32.par2" yEnc' => true,
            '"(Porn Video) Horny Bhabhi Lily Erotic Porn.part01.rar.vol063+64.par2" yEnc' => true,
            '"(Porn Video) Brazzers - Taylor Sands Gets Her Pipes Cleaned Erotic Porn.part01.rar.vol031+32.par2" yEnc' => true,
            '"(Porn Video) Petite Teen Sally Squirt Fuck By A Huge Cock Erotic Porn.part01.rar.vol063+64.par2" yEnc' => true,
            // Without yEnc
            '"(Porn Video) Milf Fucks Teen Guy Erotic Porn.part01.rar.vol031+32.par2"' => true,
            '[1/10] "Industry.S04E02.mkv"' => false, // Regular quoted filename
            '(01/50) "Some.Release.Name.rar"' => false, // Regular quoted filename
            'Just a normal subject line' => false,
        ];

        foreach ($testCases as $subject => $expected) {
            $result = $this->service->isCategoryPrefixedRelease($subject);
            $this->assertSame($expected, $result, "Failed for: {$subject}");
        }
    }

    public function test_extracts_category(): void
    {
        $testCases = [
            '"(Porn Video) Milf Fucks Teen Guy Erotic Porn.part01.rar.vol031+32.par2"' => 'Porn Video',
            '"(Adult Video) Some Title Here.rar"' => 'Adult Video',
            '"(Music) Artist - Album.zip"' => 'Music',
        ];

        foreach ($testCases as $subject => $expectedCategory) {
            $result = $this->service->extractCategory($subject);
            $this->assertSame($expectedCategory, $result, "Failed for: {$subject}");
        }
    }

    public function test_extracts_release_name(): void
    {
        $testCases = [
            '"(Porn Video) Milf Fucks Teen Guy Erotic Porn.part01.rar.vol031+32.par2" yEnc' => 'Milf Fucks Teen Guy Erotic Porn',
            '"(Porn Video) Busty Babe Gets Pounded By Huge Bbc Erotic Porn.part001.rar.vol031+32.par2" yEnc' => 'Busty Babe Gets Pounded By Huge Bbc Erotic Porn',
            '"(Porn Video) Busty Gilf Gangbanged Erotic Porn.part001.rar.vol031+32.par2" yEnc' => 'Busty Gilf Gangbanged Erotic Porn',
            '"(Porn Video) Horny Bhabhi Lily Erotic Porn.part01.rar.vol063+64.par2" yEnc' => 'Horny Bhabhi Lily Erotic Porn',
            '"(Porn Video) Brazzers - Taylor Sands Gets Her Pipes Cleaned Erotic Porn.part01.rar.vol031+32.par2" yEnc' => 'Brazzers - Taylor Sands Gets Her Pipes Cleaned Erotic Porn',
            '"(Porn Video) Petite Teen Sally Squirt Fuck By A Huge Cock Erotic Porn.part01.rar.vol063+64.par2" yEnc' => 'Petite Teen Sally Squirt Fuck By A Huge Cock Erotic Porn',
            // Without yEnc
            '"(Porn Video) Milf Fucks Teen Guy Erotic Porn.part01.rar.vol031+32.par2"' => 'Milf Fucks Teen Guy Erotic Porn',
        ];

        foreach ($testCases as $subject => $expectedName) {
            $result = $this->service->extractReleaseName($subject);
            $this->assertSame($expectedName, $result, "Failed for: {$subject}");
        }
    }

    public function test_process_subject_returns_correct_structure(): void
    {
        $subject = '"(Porn Video) Milf Fucks Teen Guy Erotic Porn.part01.rar.vol031+32.par2" yEnc';
        $result = $this->service->processSubject($subject);

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('category', $result);
        $this->assertArrayHasKey('matched', $result);
        $this->assertTrue($result['matched']);
        $this->assertSame('Porn Video', $result['category']);
        $this->assertSame('Milf Fucks Teen Guy Erotic Porn', $result['name']);
    }

    public function test_process_subject_returns_not_matched_for_invalid_subject(): void
    {
        $subject = '[1/10] "Industry.S04E02.mkv"';
        $result = $this->service->processSubject($subject);

        $this->assertFalse($result['matched']);
        $this->assertNull($result['name']);
        $this->assertNull($result['category']);
    }

    public function test_handles_various_file_extensions(): void
    {
        $testCases = [
            '"(Porn Video) Title.par2" yEnc' => 'Title',
            '"(Porn Video) Title.rar" yEnc' => 'Title',
            '"(Porn Video) Title.nfo" yEnc' => 'Title',
            '"(Porn Video) Title.sfv" yEnc' => 'Title',
            '"(Porn Video) Title.r01" yEnc' => 'Title',
            '"(Porn Video) Title.part1.rar" yEnc' => 'Title',
            '"(Porn Video) Title.vol36+35.par2" yEnc' => 'Title',
        ];

        foreach ($testCases as $subject => $expectedName) {
            $result = $this->service->extractReleaseName($subject);
            $this->assertSame($expectedName, $result, "Failed for: {$subject}");
        }
    }

    public function test_handles_compound_extensions(): void
    {
        $subject = '"(Porn Video) Title.part01.rar.vol031+32.par2" yEnc';
        $result = $this->service->extractReleaseName($subject);
        $this->assertSame('Title', $result);
    }
}
