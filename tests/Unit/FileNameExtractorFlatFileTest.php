<?php

namespace Tests\Unit;

use App\Services\NameFixing\Extractors\FileNameExtractor;
use App\Services\NameFixing\FileNameCleaner;
use PHPUnit\Framework\TestCase;

/**
 * Tests for flat (raw/uncompressed) filename pattern matching in FileNameExtractor.
 *
 * These test the new patterns added for raw media files posted directly to usenet
 * without archive containers.
 */
class FileNameExtractorFlatFileTest extends TestCase
{
    private FileNameExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new FileNameExtractor(new FileNameCleaner);
    }

    // =========================================================================
    // FLAT SCENE RELEASE TESTS
    // =========================================================================

    public function test_flat_scene_tv_release(): void
    {
        $result = $this->extractor->extractFromFile('Some.Show.S03E14.720p.WEB-DL.x264-GROUP.mkv');

        $this->assertNotNull($result);
        $this->assertEquals('Some.Show.S03E14.720p.WEB-DL.x264-GROUP', $result->newName);
        $this->assertEquals('Flat scene release', $result->method);
    }

    public function test_flat_scene_movie_release(): void
    {
        $result = $this->extractor->extractFromFile('Movie.Name.2024.1080p.BluRay.x264-GROUP.mkv');

        $this->assertNotNull($result);
        $this->assertEquals('Movie.Name.2024.1080p.BluRay.x264-GROUP', $result->newName);
        $this->assertEquals('Flat scene release', $result->method);
    }

    public function test_flat_scene_release_mp4(): void
    {
        $result = $this->extractor->extractFromFile('Another.Movie.2023.2160p.WEB-DL.DDP5.1.H.265-FLUX.mp4');

        $this->assertNotNull($result);
        $this->assertEquals('Another.Movie.2023.2160p.WEB-DL.DDP5.1.H.265-FLUX', $result->newName);
    }

    public function test_flat_scene_release_flac(): void
    {
        $result = $this->extractor->extractFromFile('Artist-Album_Name-2024-FLAC-GROUP.flac');

        $this->assertNotNull($result);
        $this->assertStringContainsString('Artist-Album_Name-2024-FLAC-GROUP', $result->newName);
    }

    // =========================================================================
    // FLAT TV TESTS
    // =========================================================================

    public function test_flat_tv_sxxexx_with_quality(): void
    {
        $result = $this->extractor->extractFromFile('The.Office.S02E05.1080p.AMZN.WEB-DL.DDP2.0.H.264.mkv');

        $this->assertNotNull($result);
        $this->assertStringContainsString('The.Office.S02E05', $result->newName);
    }

    public function test_flat_tv_generic(): void
    {
        $result = $this->extractor->extractFromFile('Show.Name.S01E01.Episode.Title.mkv');

        $this->assertNotNull($result);
        $this->assertStringContainsString('Show.Name.S01E01', $result->newName);
    }

    // =========================================================================
    // FLAT MOVIE TESTS
    // =========================================================================

    public function test_flat_movie_with_quality(): void
    {
        $result = $this->extractor->extractFromFile('Inception.2010.1080p.BluRay.x264.DTS.mkv');

        $this->assertNotNull($result);
        $this->assertStringContainsString('Inception.2010.1080p.BluRay', $result->newName);
    }

    public function test_flat_movie_year_only(): void
    {
        $result = $this->extractor->extractFromFile('Some.Movie.2024.mkv');

        $this->assertNotNull($result);
        $this->assertStringContainsString('Some.Movie.2024', $result->newName);
        $this->assertEquals('Flat movie year', $result->method);
    }

    // =========================================================================
    // FLAT MUSIC TESTS
    // =========================================================================

    public function test_flat_music_with_codec(): void
    {
        $result = $this->extractor->extractFromFile('VA-Some.Compilation-FLAC-2024-GROUP.flac');

        $this->assertNotNull($result);
        $this->assertStringContainsString('2024', $result->newName);
    }

    public function test_flat_music_year_group(): void
    {
        $result = $this->extractor->extractFromFile('Artist-Album.Name-2024-GRP.mp3');

        $this->assertNotNull($result);
        $this->assertEquals('Artist-Album.Name-2024-GRP', $result->newName);
    }

    // =========================================================================
    // FLAT STREAMING SERVICE TESTS
    // =========================================================================

    public function test_flat_streaming_service(): void
    {
        $result = $this->extractor->extractFromFile('Show.Name.S01E01.AMZN.WEB-DL.DDP5.1.H264.mkv');

        $this->assertNotNull($result);
        // Could match as flat scene or streaming service — either is correct
        $this->assertStringContainsString('Show.Name.S01E01', $result->newName);
    }

    // =========================================================================
    // OBFUSCATED / HASHED NAME TESTS
    // =========================================================================

    public function test_hashed_hex_filename_is_skipped(): void
    {
        $result = $this->extractor->extractFromFile('ab3f91c0d8e2a4b5c6d7e8f9a0b1c2d3.mkv');

        // Hashed names should be skipped by flat patterns — result should be null
        $this->assertNull($result, 'Hashed hex filename should not produce a match');
    }

    public function test_uuid_filename_is_skipped(): void
    {
        $result = $this->extractor->extractFromFile('550e8400-e29b-41d4-a716-446655440000.mkv');

        // UUID should NOT be matched by flat patterns (hashed name skip)
        // It may still match via a legacy fallback pattern like "Cleaned scene name"
        if ($result !== null) {
            $this->assertStringNotContainsString('Flat', $result->method,
                'UUID filename should not be matched by flat patterns');
        } else {
            $this->assertNull($result);
        }
    }

    // =========================================================================
    // ARCHIVE PATH FILENAMES SHOULD NOT MATCH FLAT PATTERNS
    // =========================================================================

    public function test_archive_path_does_not_match_flat_patterns(): void
    {
        // This has a backslash path separator — should be handled by archive-path patterns, not flat
        $result = $this->extractor->extractFromFile('Some.Release-GROUP\\video.mkv');

        // It should match, but via an archive-path pattern, not flat
        if ($result !== null) {
            $this->assertStringNotContainsString('Flat', $result->method);
        }
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function test_filename_without_media_extension_falls_through(): void
    {
        // Not a media extension — should not match flat patterns
        $result = $this->extractor->extractFromFile('Some.Release-GROUP.nfo');

        // nfo is not in MEDIA_EXT so baseName === filename, flat patterns skipped
        // May still match via other patterns
        if ($result !== null) {
            $this->assertStringNotContainsString('Flat scene release', $result->method);
        }
    }

    // =========================================================================
    // TIMESTAMP SUFFIX STRIPPING TESTS
    // =========================================================================

    public function test_timestamp_suffix_14_digits_stripped(): void
    {
        $result = $this->extractor->extractFromFile(
            'Win.or.Lose.S01E01.DUTCH.DV.720p.WEB.h265-NLKIDS_20250925145545.mkv'
        );

        $this->assertNotNull($result);
        $this->assertEquals('Win.or.Lose.S01E01.DUTCH.DV.720p.WEB.h265-NLKIDS', $result->newName);
        $this->assertEquals('Flat scene release', $result->method);
    }

    public function test_timestamp_suffix_10_digits_stripped(): void
    {
        $result = $this->extractor->extractFromFile(
            'Movie.Name.2024.1080p.BluRay.x264-GRP_1735123456.mkv'
        );

        $this->assertNotNull($result);
        $this->assertEquals('Movie.Name.2024.1080p.BluRay.x264-GRP', $result->newName);
    }

    public function test_no_timestamp_suffix_unchanged(): void
    {
        $result = $this->extractor->extractFromFile(
            'Some.Show.S01E01.720p.WEB-DL.x264-GROUP.mkv'
        );

        $this->assertNotNull($result);
        $this->assertEquals('Some.Show.S01E01.720p.WEB-DL.x264-GROUP', $result->newName);
    }

    public function test_short_digit_suffix_not_stripped(): void
    {
        // 5 digits is too short to be a timestamp — should not strip
        $result = $this->extractor->extractFromFile(
            'Some.Show.S01E01.720p.WEB-DL.x264-GROUP_12345.mkv'
        );

        $this->assertNotNull($result);
        // The _12345 should remain (not stripped), the SxxExx quality pattern matches the whole thing
        $this->assertStringContainsString('12345', $result->newName);
    }
}
