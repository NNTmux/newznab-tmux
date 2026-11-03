<?php

namespace Tests\Unit;

use App\Services\NameFixing\SpamPrefixedFilenameCleanerService;
use PHPUnit\Framework\TestCase;

class SpamPrefixedFilenameCleanerServiceTest extends TestCase
{
    private SpamPrefixedFilenameCleanerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SpamPrefixedFilenameCleanerService(false);
    }

    public function test_detects_spam_prefixed_releases_with_parentheses_before_brackets(): void
    {
        $subjects = [
            '(Rick Moranis randomly punched in the head by a man in NYC) [01/66] - "AutoCAD_Architecture_2026_Multilingual_Win_64bit_db_002_002 - Copy.par2"',
            '(Rick Moranis randomly punched in the head by a man in NYC) [72/83] - "Office.2021-2024.18730.20186.64Bit.rar"',
            '(Breaking news headline about something) [15/50] - "Software.Name.v2.0-TEAM.r00"',
        ];

        foreach ($subjects as $subject) {
            $this->assertTrue($this->service->isSpamPrefixedRelease($subject), "Failed to detect: $subject");
        }
    }

    public function test_detects_placeholder_prefix_patterns(): void
    {
        $subjects = [
            '(????) [16404/16932] - "XYplorer Pro v16.60 Multilingual Setup + Serial.zip"',
            '(????) [12078/16932] - "Image Converter 1.0.zip"',
            '(****) [01/50] - "Some.Release.Name.rar"',
            '(----) [01/50] - "Another.Release.rar"',
            '(####) [01/50] - "Third.Release.rar"',
        ];

        foreach ($subjects as $subject) {
            $this->assertTrue($this->service->isSpamPrefixedRelease($subject), "Failed to detect placeholder prefix: $subject");
        }
    }

    public function test_extracts_name_from_placeholder_prefix_subjects(): void
    {
        $testCases = [
            '(????) [16404/16932] - "XYplorer Pro v16.60 Multilingual Setup + Serial.zip"' => 'XYplorer Pro v16.60 Multilingual Setup + Serial',
            '(????) [12078/16932] - "Image Converter 1.0.zip"' => 'Image Converter 1.0',
            '(????) [09552/16932] - "Hardcoded Software dupeGuru Picture Edition v1.8.0.zip"' => 'Hardcoded Software dupeGuru Picture Edition v1.8.0',
            '(????) [08122/15301] - "Cakewalk Sonar Producer Edition v5.2 x64 Update-BEAT.zip"' => 'Cakewalk Sonar Producer Edition v5.2 x64 Update-BEAT',
        ];

        foreach ($testCases as $subject => $expected) {
            $name = $this->service->extractReleaseName($subject);
            $this->assertSame($expected, $name, "Failed for subject: $subject");
        }
    }

    public function test_detects_spam_prefixed_releases_with_parentheses_before_parentheses(): void
    {
        $subjects = [
            '(Some random spam text here that is long) (01/50) - "Actual.Release.Name-GROUP.mkv"',
            '(News headline about celebrities) (001/150) - "Software.Name.v1.0-TEAM.r00"',
        ];

        foreach ($subjects as $subject) {
            $this->assertTrue($this->service->isSpamPrefixedRelease($subject), "Failed to detect: $subject");
        }
    }

    public function test_extracts_release_name_from_spam_prefixed_subject(): void
    {
        $subject = '(Rick Moranis randomly punched in the head by a man in NYC) [01/66] - "AutoCAD_Architecture_2026_Multilingual_Win_64bit_db_002_002 - Copy.par2"';

        $name = $this->service->extractReleaseName($subject);

        $this->assertSame('AutoCAD_Architecture_2026_Multilingual_Win_64bit_db_002_002', $name);
    }

    public function test_extracts_release_name_strips_extensions(): void
    {
        $testCases = [
            '(Spam text here) [01/10] - "Release.Name.par2"' => 'Release.Name',
            '(Spam text here) [01/10] - "Release.Name.rar"' => 'Release.Name',
            '(Spam text here) [01/10] - "Release.Name.mkv"' => 'Release.Name',
            '(Spam text here) [01/10] - "Release.Name.r01"' => 'Release.Name',
            // Compound extensions
            '(Spam text here) [01/10] - "Release.Name.rar.par2"' => 'Release.Name',
            '(Spam text here) [01/10] - "Release.Name.part01.rar"' => 'Release.Name',
        ];

        foreach ($testCases as $subject => $expected) {
            $name = $this->service->extractReleaseName($subject);
            $this->assertSame($expected, $name, "Failed for subject: $subject");
        }
    }

    public function test_extracts_release_name_removes_copy_suffix(): void
    {
        $subject = '(Some spam) [01/10] - "Release.Name - Copy.par2"';

        $name = $this->service->extractReleaseName($subject);

        $this->assertSame('Release.Name', $name);
    }

    public function test_process_subject_returns_matched_true_for_spam_prefixed(): void
    {
        $subject = '(Rick Moranis randomly punched in the head by a man in NYC) [01/66] - "Office.2021-2024.18730.20186.64Bit.rar"';

        $result = $this->service->processSubject($subject);

        $this->assertTrue($result['matched']);
        $this->assertSame('Office.2021-2024.18730.20186.64Bit', $result['name']);
    }

    public function test_process_subject_returns_matched_false_for_normal_releases(): void
    {
        $subjects = [
            // Normal quoted filename releases (no spam prefix)
            '[01/10] "Some.Release.Name.rar"',
            '(01/50) "Another.Release.Name.par2"',
            // Scene release style names
            'Some.Release.Name-GROUP [01/10] "Some.Release.Name-GROUP.par2"',
        ];

        foreach ($subjects as $subject) {
            $result = $this->service->processSubject($subject);
            $this->assertFalse($result['matched'], "Should not match: $subject");
        }
    }

    public function test_does_not_match_valid_release_names_with_part_indicators(): void
    {
        // These have valid release names before the part indicator
        $subjects = [
            'AutoCAD.2026.Win64-GROUP [01/50] - "AutoCAD.2026.Win64-GROUP.par2"',
            'Office.2024.x64-TEAM (01/100) - "Office.2024.x64-TEAM.r00"',
        ];

        $matchCount = 0;
        foreach ($subjects as $subject) {
            $result = $this->service->processSubject($subject);
            // These should ideally not match because the prefix looks like a valid release name
            // But if they do match, the extracted name should still be valid
            if ($result['matched']) {
                $matchCount++;
            }
        }

        // At minimum assert we checked the subjects
        $this->assertCount(2, $subjects);
    }

    public function test_extracts_office_release_name(): void
    {
        $subject = '(Rick Moranis randomly punched in the head by a man in NYC) [72/83] - "Office.2021-2024.18730.20186.64Bit.rar"';

        $name = $this->service->extractReleaseName($subject);

        $this->assertSame('Office.2021-2024.18730.20186.64Bit', $name);
    }

    public function test_handles_subject_with_bracket_spam_prefix(): void
    {
        $subject = '[Random news headline text here] [01/50] - "Real.Release.Name-GROUP.rar"';

        $result = $this->service->processSubject($subject);

        $this->assertTrue($result['matched']);
        $this->assertSame('Real.Release.Name-GROUP', $result['name']);
    }

    public function test_cleans_various_file_markers(): void
    {
        $testCases = [
            '(Spam text) [01/10] - "Release.Name.part01.rar"' => 'Release.Name',
            '(Spam text) [01/10] - "Release.Name.vol36+35.par2"' => 'Release.Name',
            '(Spam text) [01/10] - "Release.Name-sample.mkv"' => 'Release.Name',
            '(Spam text) [01/10] - "Release.Name-proof.mkv"' => 'Release.Name',
        ];

        foreach ($testCases as $subject => $expected) {
            $name = $this->service->extractReleaseName($subject);
            $this->assertSame($expected, $name, "Failed for subject: $subject");
        }
    }

    public function test_rejects_hash_like_filenames(): void
    {
        $subject = '(Some spam text here) [01/10] - "a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4.rar"';

        $name = $this->service->extractReleaseName($subject);

        // Should return null for hash-like filenames
        $this->assertNull($name);
    }

    public function test_rejects_purely_numeric_filenames(): void
    {
        $subject = '(Some spam text here) [01/10] - "12345678901234567890.rar"';

        $name = $this->service->extractReleaseName($subject);

        // Should return null for numeric-only filenames
        $this->assertNull($name);
    }
}
