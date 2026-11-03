<?php

namespace Tests\Unit;

use App\Services\NameFixing\QuotedFilenameCleanerService;
use PHPUnit\Framework\TestCase;

class QuotedFilenameCleanerServiceTest extends TestCase
{
    private QuotedFilenameCleanerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new QuotedFilenameCleanerService(false);
    }

    public function test_detects_bracket_quoted_filename_releases(): void
    {
        $subjects = [
            '[1/10] "Industry.S04E02.The.Commander.and.The.Grey.Lady.1080p.AMZN.WEB-DL.DDP5.1.H.264-GP-TV-NLsubs.mkv"',
            '[01/50] "Some.Release.Name.720p.BluRay.x264-GROUP.rar"',
            '[15/30] - "Release.Name.HD1080p.vol36+35.par2"',
        ];

        foreach ($subjects as $subject) {
            $this->assertTrue($this->service->isQuotedFilenameRelease($subject), "Failed to detect: $subject");
        }
    }

    public function test_detects_parenthesis_quoted_filename_releases(): void
    {
        $subjects = [
            '(01/50) "Some.Release.Name.720p.BluRay.x264-GROUP.rar"',
            '(15/30) - "Release.Name.HD1080p.par2"',
        ];

        foreach ($subjects as $subject) {
            $this->assertTrue($this->service->isQuotedFilenameRelease($subject), "Failed to detect: $subject");
        }
    }

    public function test_extracts_release_name_from_bracket_pattern(): void
    {
        $subject = '[1/10] "Industry.S04E02.The.Commander.and.The.Grey.Lady.1080p.AMZN.WEB-DL.DDP5.1.H.264-GP-TV-NLsubs.mkv"';

        $name = $this->service->extractReleaseName($subject);

        $this->assertSame('Industry.S04E02.The.Commander.and.The.Grey.Lady.1080p.AMZN.WEB-DL.DDP5.1.H.264-GP-TV-NLsubs', $name);
    }

    public function test_extracts_release_name_strips_common_extensions(): void
    {
        $testCases = [
            '[01/10] "Release.Name.par2"' => 'Release.Name',
            '[01/10] "Release.Name.rar"' => 'Release.Name',
            '[01/10] "Release.Name.nfo"' => 'Release.Name',
            '[01/10] "Release.Name.sfv"' => 'Release.Name',
            '[01/10] "Release.Name.r01"' => 'Release.Name',
            '[01/10] "Release.Name.mkv"' => 'Release.Name',
            '[01/10] "Release.Name.avi"' => 'Release.Name',
            '[01/10] "Release.Name.mp4"' => 'Release.Name',
            '[01/10] "Release.Name.part1.rar"' => 'Release.Name',
            '[01/10] "Release.Name.vol36+35.par2"' => 'Release.Name',
        ];

        foreach ($testCases as $subject => $expected) {
            $name = $this->service->extractReleaseName($subject);
            $this->assertSame($expected, $name, "Failed for subject: $subject");
        }
    }

    public function test_extracts_release_name_with_dash_separator(): void
    {
        $subject = '[15/30] - "Release.Name.HD1080p.vol36+35.par2"';

        $name = $this->service->extractReleaseName($subject);

        $this->assertSame('Release.Name.HD1080p', $name);
    }

    public function test_extracts_release_name_from_parenthesis_pattern(): void
    {
        $subject = '(01/50) "Some.Release.720p.BluRay.x264-GROUP.rar"';

        $name = $this->service->extractReleaseName($subject);

        $this->assertSame('Some.Release.720p.BluRay.x264-GROUP', $name);
    }

    public function test_removes_sample_and_proof_markers(): void
    {
        $testCases = [
            '[01/10] "Release.Name-sample.mkv"' => 'Release.Name',
            '[01/10] "Release.Name-proof.mkv"' => 'Release.Name',
            '[01/10] "Release.Name.sample.mkv"' => 'Release.Name',
            '[01/10] "Release.Name_sample.mkv"' => 'Release.Name',
        ];

        foreach ($testCases as $subject => $expected) {
            $name = $this->service->extractReleaseName($subject);
            $this->assertSame($expected, $name, "Failed for subject: $subject");
        }
    }

    public function test_removes_part_markers(): void
    {
        $subject = '[01/10] "Release.Name.part01.rar"';

        $name = $this->service->extractReleaseName($subject);

        $this->assertSame('Release.Name', $name);
    }

    public function test_removes_vol_markers(): void
    {
        $subject = '[01/10] "Release.Name.vol36+35.par2"';

        $name = $this->service->extractReleaseName($subject);

        $this->assertSame('Release.Name', $name);
    }

    public function test_process_subject_returns_matched_false_for_non_matching(): void
    {
        $subject = 'Some random subject line without quoted filename';

        $result = $this->service->processSubject($subject);

        $this->assertFalse($result['matched']);
        $this->assertNull($result['name']);
    }

    public function test_process_subject_returns_matched_true_for_matching(): void
    {
        $subject = '[1/10] "Industry.S04E02.The.Commander.1080p.AMZN.WEB-DL.mkv"';

        $result = $this->service->processSubject($subject);

        $this->assertTrue($result['matched']);
        $this->assertSame('Industry.S04E02.The.Commander.1080p.AMZN.WEB-DL', $result['name']);
    }

    public function test_handles_real_world_tv_release(): void
    {
        $subject = '[1/10] "Industry.S04E02.The.Commander.and.The.Grey.Lady.1080p.AMZN.WEB-DL.DDP5.1.H.264-GP-TV-NLsubs.mkv"';

        $result = $this->service->processSubject($subject);

        $this->assertTrue($result['matched']);
        $this->assertSame('Industry.S04E02.The.Commander.and.The.Grey.Lady.1080p.AMZN.WEB-DL.DDP5.1.H.264-GP-TV-NLsubs', $result['name']);
    }

    public function test_handles_movie_release(): void
    {
        $subject = '[01/45] "The.Matrix.1999.1080p.BluRay.x264-GROUP.mkv"';

        $result = $this->service->processSubject($subject);

        $this->assertTrue($result['matched']);
        $this->assertSame('The.Matrix.1999.1080p.BluRay.x264-GROUP', $result['name']);
    }

    public function test_handles_iso_extension(): void
    {
        $subject = '[01/10] "Game.Name.Proper-GROUP.iso"';

        $result = $this->service->processSubject($subject);

        $this->assertTrue($result['matched']);
        $this->assertSame('Game.Name.Proper-GROUP', $result['name']);
    }

    public function test_returns_null_for_very_short_names(): void
    {
        $subject = '[01/10] "ab.mkv"';

        $name = $this->service->extractReleaseName($subject);

        $this->assertNull($name);
    }

    public function test_handles_leading_whitespace(): void
    {
        $subject = '  [01/10] "Release.Name.mkv"';

        $result = $this->service->processSubject($subject);

        $this->assertTrue($result['matched']);
        $this->assertSame('Release.Name', $result['name']);
    }

    public function test_handles_uploader_tag_pattern(): void
    {
        $subject = '(17/36) -Olli- "incepti31081980de.part16.rar" - 1,43 GB';

        $result = $this->service->processSubject($subject);

        $this->assertTrue($result['matched']);
        $this->assertSame('incepti31081980de', $result['name']);
    }

    public function test_handles_duplicate_name_pattern(): void
    {
        $subject = '"(04/38) "DEFA - Der blaue Helm (1979) Juergen Trott, Helga Goering DVDrip.part03.rar""DEFA - Der blaue Helm (1979) Juergen Trott, Helga Goering DVDrip"';

        $result = $this->service->processSubject($subject);

        $this->assertTrue($result['matched']);
        $this->assertSame('DEFA - Der blaue Helm (1979) Juergen Trott, Helga Goering DVDrip', $result['name']);
    }

    public function test_detects_uploader_tag_pattern_as_quoted_filename(): void
    {
        $subject = '(17/36) -Olli- "incepti31081980de.part16.rar" - 1,43 GB';

        $this->assertTrue($this->service->isQuotedFilenameRelease($subject));
    }

    public function test_detects_duplicate_name_pattern_as_quoted_filename(): void
    {
        $subject = '"(04/38) "DEFA - Der blaue Helm (1979) Juergen Trott, Helga Goering DVDrip.part03.rar""DEFA - Der blaue Helm (1979) Juergen Trott, Helga Goering DVDrip"';

        $this->assertTrue($this->service->isQuotedFilenameRelease($subject));
    }

    public function test_handles_description_tag_pattern(): void
    {
        $subject = '(01/21) - Description - "Douglas Preston, Lincoln Child - Nora Kelly und Corrie Swanson 01 - 05 (Sammelpack).par2" - 3,95 GB';

        $result = $this->service->processSubject($subject);

        $this->assertTrue($result['matched']);
        $this->assertSame('Douglas Preston, Lincoln Child - Nora Kelly und Corrie Swanson 01 - 05 (Sammelpack)', $result['name']);
    }

    public function test_detects_description_tag_pattern_as_quoted_filename(): void
    {
        $subject = '(01/21) - Description - "Douglas Preston, Lincoln Child - Nora Kelly und Corrie Swanson 01 - 05 (Sammelpack).par2" - 3,95 GB';

        $this->assertTrue($this->service->isQuotedFilenameRelease($subject));
    }
}
