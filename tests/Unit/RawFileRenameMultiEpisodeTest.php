<?php

namespace Tests\Unit;

use App\Services\AdditionalProcessing\ReleaseRenamingService;
use App\Services\NameFixing\FileNameCleaner;
use App\Services\NameFixing\NameFixingService;
use App\Services\NameFixing\ReleaseUpdateService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Tests\Unit\AdditionalProcessing\CreatesProcessingConfiguration;

/**
 * Tests for multi-episode detection and candidate resolution logic.
 */
class RawFileRenameMultiEpisodeTest extends TestCase
{
    use CreatesProcessingConfiguration;

    private FileNameCleaner $cleaner;

    private ReleaseRenamingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleaner = new FileNameCleaner;
        $this->service = new ReleaseRenamingService(
            $this->makeConfig(),
            $this->createStub(NameFixingService::class),
            $this->createStub(ReleaseUpdateService::class),
        );
    }

    /**
     * @param  list<string>  $cleanNames
     */
    private function resolveMultiEpisodeCandidate(array $cleanNames, ?string $largestFile): ?string
    {
        /** @var string|null $result */
        $result = (new ReflectionMethod(ReleaseRenamingService::class, 'resolveMultiEpisodeCandidate'))
            ->invoke($this->service, $cleanNames, $largestFile, $this->cleaner);

        return $result;
    }

    // =========================================================================
    // SINGLE FILE TESTS
    // =========================================================================
    public function test_single_file_returns_as_is(): void
    {
        $result = $this->resolveMultiEpisodeCandidate(
            ['Some.Show.S01E01.720p.WEB-DL.x264-GROUP.mkv'],
            'Some.Show.S01E01.720p.WEB-DL.x264-GROUP.mkv'
        );
        $this->assertEquals('Some.Show.S01E01.720p.WEB-DL.x264-GROUP.mkv', $result);
    }

    public function test_duplicate_subjects_for_one_episode_keep_episode_identifier(): void
    {
        $name = 'Sister.Wives.2010.S19E14.The.Truth.Will.Set.You.Free.1080p.DSCP.WEB-DL.DDP2.0.H.265-FUZEER.mkv';

        $result = $this->resolveMultiEpisodeCandidate(
            [$name, $name, $name, $name, $name, $name, $name],
            $name
        );

        $this->assertSame($name, $result);
    }

    public function test_different_files_for_one_episode_use_largest_file(): void
    {
        $largestFile = 'Sister.Wives.2010.S19E14.The.Truth.Will.Set.You.Free.1080p.DSCP.WEB-DL.DDP2.0.H.265-FUZEER.mkv';

        $result = $this->resolveMultiEpisodeCandidate(
            [
                'Sister.Wives.2010.S19E14.The.Truth.Will.Set.You.Free.720p.DSCP.WEB-DL.DDP2.0.H.264-FUZEER.mkv',
                $largestFile,
            ],
            $largestFile
        );

        $this->assertSame($largestFile, $result);
    }

    public function test_single_multi_episode_file_collapses(): void
    {
        $result = $this->resolveMultiEpisodeCandidate(
            ['Some.Show.S01E01E02.720p.WEB-DL.x264-GROUP.mkv'],
            'Some.Show.S01E01E02.720p.WEB-DL.x264-GROUP.mkv'
        );
        $this->assertEquals('Some.Show.S01.720p.WEB-DL.x264-GROUP.mkv', $result);
    }

    public function test_single_multi_episode_three_eps_collapses(): void
    {
        $result = $this->resolveMultiEpisodeCandidate(
            ['Show.S02E01E02E03.1080p.BluRay.x264-GRP.mkv'],
            'Show.S02E01E02E03.1080p.BluRay.x264-GRP.mkv'
        );
        $this->assertEquals('Show.S02.1080p.BluRay.x264-GRP.mkv', $result);
    }

    // =========================================================================
    // MULTI-FILE SAME SERIES TESTS
    // =========================================================================
    public function test_multi_file_same_series_collapses_to_season(): void
    {
        $result = $this->resolveMultiEpisodeCandidate(
            [
                'Some.Show.S01E01.720p.WEB-DL.x264-GROUP.mkv',
                'Some.Show.S01E02.720p.WEB-DL.x264-GROUP.mkv',
                'Some.Show.S01E03.720p.WEB-DL.x264-GROUP.mkv',
            ],
            'Some.Show.S01E01.720p.WEB-DL.x264-GROUP.mkv'
        );
        $this->assertNotNull($result);
        $this->assertStringContainsString('Some.Show.S01', $result);
        $this->assertStringNotContainsString('E01', $result);
        $this->assertStringNotContainsString('E02', $result);
        $this->assertStringContainsString('720p', $result);
        $this->assertStringContainsString('GROUP', $result);
    }

    public function test_multi_file_two_episodes_collapses(): void
    {
        $result = $this->resolveMultiEpisodeCandidate(
            [
                'The.Office.S02E05.1080p.AMZN.WEB-DL.mkv',
                'The.Office.S02E06.1080p.AMZN.WEB-DL.mkv',
            ],
            'The.Office.S02E05.1080p.AMZN.WEB-DL.mkv'
        );
        $this->assertNotNull($result);
        $this->assertStringContainsString('The.Office.S02', $result);
        $this->assertStringNotContainsString('E05', $result);
    }

    public function test_episode_titles_do_not_leak_into_collapsed_name(): void
    {
        $result = $this->resolveMultiEpisodeCandidate(
            [
                'Show.S01E01.Pilot.1080p.WEB-DL-GRP.mkv',
                'Show.S01E02.The.Title.1080p.WEB-DL-GRP.mkv',
            ],
            'Show.S01E02.The.Title.1080p.WEB-DL-GRP.mkv'
        );
        $this->assertSame('Show.S01.1080p.WEB-DL-GRP', $result);
    }

    // =========================================================================
    // MULTI-FILE DIFFERENT SERIES TESTS
    // =========================================================================
    public function test_multi_file_different_series_uses_largest(): void
    {
        $result = $this->resolveMultiEpisodeCandidate(
            [
                'Show.A.S01E01.720p.WEB-DL.mkv',
                'Show.B.S01E01.720p.WEB-DL.mkv',
            ],
            'Show.A.S01E01.720p.WEB-DL.mkv'
        );
        $this->assertEquals('Show.A.S01E01.720p.WEB-DL.mkv', $result);
    }

    // =========================================================================
    // NON-TV FILES TESTS
    // =========================================================================
    public function test_multi_file_no_episode_pattern_uses_largest(): void
    {
        $result = $this->resolveMultiEpisodeCandidate(
            [
                'Movie.2024.1080p.BluRay.x264-GRP.mkv',
                'Movie.2024.1080p.BluRay.x264-GRP.sample.mkv',
            ],
            'Movie.2024.1080p.BluRay.x264-GRP.mkv'
        );
        $this->assertEquals('Movie.2024.1080p.BluRay.x264-GRP.mkv', $result);
    }

    // =========================================================================
    // SUFFIX PRESERVATION TESTS
    // =========================================================================
    public function test_collapsed_name_preserves_quality_and_group(): void
    {
        $result = $this->resolveMultiEpisodeCandidate(
            [
                'Breaking.Bad.S05E01.1080p.BluRay.x264-DEMAND.mkv',
                'Breaking.Bad.S05E02.1080p.BluRay.x264-DEMAND.mkv',
            ],
            'Breaking.Bad.S05E01.1080p.BluRay.x264-DEMAND.mkv'
        );
        $this->assertNotNull($result);
        $this->assertStringContainsString('Breaking.Bad.S05', $result);
        $this->assertStringContainsString('1080p', $result);
        $this->assertStringContainsString('DEMAND', $result);
    }

    // =========================================================================
    // OBFUSCATED FILE NAME DETECTION
    // =========================================================================
    public function test_hashed_name_detected_by_cleaner(): void
    {
        $this->assertTrue($this->cleaner->looksLikeHashedName('ab3f91c0d8e2a4b5c6d7e8f9a0b1c2d3'));
    }

    public function test_normal_scene_name_not_detected_as_hashed(): void
    {
        $this->assertFalse($this->cleaner->looksLikeHashedName('Some.Show.S01E01.720p.WEB-DL.x264-GROUP'));
    }

    // =========================================================================
    // PARITY RELEASE DETECTION
    // =========================================================================

    /**
     * Replicate the isParityRelease regex for testing.
     */
    private function isParityRelease(string $name): bool
    {
        return (bool) preg_match('/\.(?:vol\d+[\-+]\d+(?:\.par2?)?|par2?)$/i', $name);
    }

    public function test_par2_vol_release_detected(): void
    {
        $this->assertTrue($this->isParityRelease('Good.Boy.2025.BluRay.1080p.DD5.1.x264-TiGER.vol00-01'));
    }

    public function test_par2_vol_with_extension_detected(): void
    {
        $this->assertTrue($this->isParityRelease('Good.Boy.2025.BluRay.1080p.DD5.1.x264-TiGER.vol00+01.par2'));
    }

    public function test_standalone_par2_detected(): void
    {
        $this->assertTrue($this->isParityRelease('Good.Boy.2025.BluRay.1080p.DD5.1.x264-TiGER.par2'));
    }

    public function test_normal_release_not_parity(): void
    {
        $this->assertFalse($this->isParityRelease('Good.Boy.2025.BluRay.1080p.DD5.1.x264-TiGER'));
    }

    public function test_tv_release_not_parity(): void
    {
        $this->assertFalse($this->isParityRelease('Some.Show.S01E01.720p.WEB-DL.x264-GROUP'));
    }
}
