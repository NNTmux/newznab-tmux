<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Category;
use App\Services\Categorization\Categorizers\MiscCategorizer;
use App\Services\Categorization\Pipes\CategorizationPassable;
use App\Services\Categorization\Pipes\ConsolePipe;
use App\Services\Categorization\Pipes\GroupNamePipe;
use App\Services\Categorization\Pipes\MiscPipe;
use App\Services\Categorization\Pipes\MoviePipe;
use App\Services\Categorization\Pipes\MusicPipe;
use App\Services\Categorization\Pipes\PcPipe;
use App\Services\Categorization\Pipes\TvPipe;
use App\Services\Categorization\Pipes\XxxPipe;
use App\Services\Categorization\ReleaseContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HashedReleaseCategorizationTest extends TestCase
{
    /**
     * The ordered list of pipes that matches CategorizationPipeline::createDefault().
     * Sorted by priority: MiscPipe(1), GroupNamePipe(5), XxxPipe(10), TvPipe(20),
     * MoviePipe(25), BookPipe, MusicPipe, PcPipe, ConsolePipe.
     *
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
            new MusicPipe,
            new PcPipe,
            new ConsolePipe,
        ];
    }

    /**
     * Run a release name through the full pipe chain and return the passable.
     */
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

        // Manually run through each pipe in order (avoids needing Laravel app container)
        foreach ($pipes as $pipe) {
            $passable = $pipe->handle($passable, fn ($p) => $p);
        }

        return $passable;
    }

    // ------------------------------------------------------------------
    // Data providers
    // ------------------------------------------------------------------

    /**
     * Hashed release names that MUST end up in OTHER_HASHED.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function hashedNamesProvider(): array
    {
        return [
            'MD5 hash' => ['d41d8cd98f00b204e9800998ecf8427e', 'hash_md5'],
            'MD5 hash with quotes' => ['"d41d8cd98f00b204e9800998ecf8427e"', 'hash_md5'],
            'SHA-1 hash' => ['da39a3ee5e6b4b0d3255bfef95601890afd80709', 'hash_sha1'],
            'SHA-256 hash' => ['e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', 'hash_sha256'],
            'UUID with dashes' => ['550e8400-e29b-41d4-a716-446655440000', 'hash_uuid'],
            'Pure hex 20 chars' => ['aabbccdd0011223344ff', 'hash_hex'],
            'All uppercase 20 chars' => ['ABCDEFGH1234567890XY', 'obfuscated_uppercase'],
            'Mixed alphanumeric random' => ['AA7Jl2toE8Q53yNZmQ5R6G', 'obfuscated_mixed_alphanumeric'],
            'Usenet obfuscated filename' => ['[01/10] - "xK9mR2pL4qW7nT3vB.part01.rar"', 'obfuscated_usenet_filename'],
        ];
    }

    /**
     * Gibberish release names that MUST end up in OTHER_HASHED.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function gibberishNamesProvider(): array
    {
        return [
            // Contains dots and lowercase letters — bypasses obfuscated_uppercase (needs ^[A-Z0-9]),
            // obfuscated_mixed_alphanumeric (needs ^[a-zA-Z0-9]{15,}$ — dots break it),
            // and obfuscated_punctuation (needs all uppercase alpha).
            // After stripping separators, coreName has high character-transition rate.
            'Random transitions' => ['aB3c.D4eF.5gH6i.J7kL8m', 'gibberish_random_transitions'],
            // Lowercase with dots and digits — bypasses all obfuscated checks.
            // After stripping, coreName ≥20 with maxConsecutiveLetters < 5 but low transition rate.
            // Grouped letter/digit runs keep transition rate ≤ 0.35.
            'No word structure long' => ['xyz.1234.wvut.5678.srqp.9012', 'gibberish_no_word_structure'],
            // Lowercase with dots and digits — bypasses all obfuscated checks.
            // After stripping, coreName matches digit-heavy pattern (1-3 letters + 6+ digits).
            'Digit-heavy pattern' => ['xz.123456789012', 'gibberish_random_digits'],
        ];
    }

    /**
     * Legitimate release names that MUST NOT be locked to misc.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function legitimateNamesProvider(): array
    {
        return [
            'Movie release' => ['Some.Movie.2024.1080p.BluRay.x264-GROUP', 'alt.binaries.movies'],
            'TV episode' => ['Show.Name.S03E05.720p.HDTV.x264-GROUP', 'alt.binaries.hdtv'],
            'Music album' => ['Artist.Name-Album.Title-2024-FLAC-GROUP', 'alt.binaries.sounds.mp3'],
            'Game release' => ['Starfield-RUNE', 'alt.binaries.games'],
        ];
    }

    /**
     * Hashed names paired with group names that would otherwise
     * categorize the release into a content category.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function hashedWithGroupProvider(): array
    {
        return [
            'MD5 in movies group' => ['d41d8cd98f00b204e9800998ecf8427e', 'alt.binaries.movies'],
            'Random in hdtv group' => ['AA7Jl2toE8Q53yNZmQ5R6G', 'alt.binaries.hdtv'],
            'SHA1 in music group' => ['da39a3ee5e6b4b0d3255bfef95601890afd80709', 'alt.binaries.sounds.mp3'],
            'Uppercase in xxx group' => ['ABCDEFGH1234567890XY', 'alt.binaries.erotica'],
            'Gibberish in games group' => ['aB3cD4eF5gH6iJ7kL8m', 'alt.binaries.games'],
            'Hex in warez group' => ['aabbccdd0011223344ff', 'alt.binaries.warez'],
            'Random digits in ebook group' => ['ab12345678901234', 'alt.binaries.e-book'],
        ];
    }

    // ------------------------------------------------------------------
    // Tests: MiscCategorizer (unit-level)
    // ------------------------------------------------------------------

    #[DataProvider('hashedNamesProvider')]
    public function test_misc_categorizer_detects_hashed_names(string $name, string $expectedMatchedBy): void
    {
        $categorizer = new MiscCategorizer;
        $context = new ReleaseContext(releaseName: $name, groupId: 0);
        $result = $categorizer->categorize($context);

        $this->assertTrue($result->isSuccessful(), "Expected successful match for: $name");
        $this->assertSame($expectedMatchedBy, $result->matchedBy, "Wrong matchedBy tag for: $name");
        $this->assertContains(
            $result->categoryId,
            [Category::OTHER_HASHED, Category::OTHER_MISC],
            "Expected misc category for: $name"
        );
    }

    #[DataProvider('gibberishNamesProvider')]
    public function test_misc_categorizer_detects_gibberish_names(string $name, string $expectedMatchedBy): void
    {
        $categorizer = new MiscCategorizer;
        $context = new ReleaseContext(releaseName: $name, groupId: 0);
        $result = $categorizer->categorize($context);

        $this->assertTrue($result->isSuccessful(), "Expected successful match for: $name");
        $this->assertSame($expectedMatchedBy, $result->matchedBy, "Wrong matchedBy tag for: $name");
        $this->assertSame(Category::OTHER_HASHED, $result->categoryId, "Expected OTHER_HASHED for: $name");
    }

    // ------------------------------------------------------------------
    // Tests: MiscPipe lock mechanism
    // ------------------------------------------------------------------

    #[DataProvider('hashedNamesProvider')]
    public function test_misc_pipe_locks_hashed_releases(string $name, string $expectedMatchedBy): void
    {
        $passable = $this->runPipeline($name);

        $this->assertTrue($passable->lockedToMisc, "Expected lockedToMisc for: $name");
        $this->assertContains(
            $passable->bestResult->categoryId,
            [Category::OTHER_HASHED, Category::OTHER_MISC],
            "Expected misc category for locked release: $name"
        );
    }

    #[DataProvider('gibberishNamesProvider')]
    public function test_misc_pipe_locks_gibberish_releases(string $name, string $expectedMatchedBy): void
    {
        $passable = $this->runPipeline($name);

        $this->assertTrue($passable->lockedToMisc, "Expected lockedToMisc for: $name");
        $this->assertSame(
            Category::OTHER_HASHED,
            $passable->bestResult->categoryId,
            "Expected OTHER_HASHED for locked release: $name"
        );
    }

    // ------------------------------------------------------------------
    // Tests: Hashed releases are NOT overridden by group-based categorization
    // ------------------------------------------------------------------

    #[DataProvider('hashedWithGroupProvider')]
    public function test_hashed_releases_not_overridden_by_group(string $name, string $groupName): void
    {
        $passable = $this->runPipeline($name, $groupName);

        $this->assertTrue($passable->lockedToMisc, "Expected lockedToMisc for: $name (group: $groupName)");
        $this->assertContains(
            $passable->bestResult->categoryId,
            [Category::OTHER_HASHED, Category::OTHER_MISC],
            "Hashed release '$name' in group '$groupName' should stay in misc, got category: {$passable->bestResult->categoryId}"
        );

        // Verify it was NOT assigned a content category
        $this->assertNotContains(
            $passable->bestResult->categoryId,
            [
                Category::TV_OTHER, Category::MOVIE_OTHER, Category::XXX_OTHER,
                Category::MUSIC_OTHER, Category::GAME_OTHER, Category::PC_0DAY,
                Category::BOOKS_EBOOK,
            ],
            "Hashed release '$name' should NOT be in content category, but got: {$passable->bestResult->categoryId}"
        );
    }

    // ------------------------------------------------------------------
    // Tests: Legitimate releases still categorize normally
    // ------------------------------------------------------------------

    #[DataProvider('legitimateNamesProvider')]
    public function test_legitimate_releases_are_not_locked(string $name, string $groupName): void
    {
        $passable = $this->runPipeline($name, $groupName);

        $this->assertFalse($passable->lockedToMisc, "Legitimate release '$name' should NOT be locked to misc");

        // They should NOT end up in OTHER_HASHED
        $this->assertNotSame(
            Category::OTHER_HASHED,
            $passable->bestResult->categoryId,
            "Legitimate release '$name' should NOT be in OTHER_HASHED"
        );
    }

    // ------------------------------------------------------------------
    // Tests: shouldStopProcessing() respects the lock
    // ------------------------------------------------------------------

    public function test_should_stop_processing_returns_true_when_locked(): void
    {
        $context = new ReleaseContext(releaseName: 'test', groupId: 0);
        $passable = new CategorizationPassable($context);

        $this->assertFalse($passable->shouldStopProcessing());

        $passable->lockToMisc();

        $this->assertTrue($passable->shouldStopProcessing());
    }

    public function test_locked_passable_prevents_downstream_pipes(): void
    {
        $context = new ReleaseContext(
            releaseName: 'd41d8cd98f00b204e9800998ecf8427e',
            groupId: 0,
            groupName: 'alt.binaries.movies',
        );

        $passable = new CategorizationPassable($context, debug: true);

        // Run MiscPipe first
        $miscPipe = new MiscPipe;
        $passable = $miscPipe->handle($passable, fn ($p) => $p);

        $this->assertTrue($passable->lockedToMisc);
        $this->assertSame(Category::OTHER_HASHED, $passable->bestResult->categoryId);

        // Now run GroupNamePipe — it should skip because of the lock
        $groupPipe = new GroupNamePipe;
        $passable = $groupPipe->handle($passable, fn ($p) => $p);

        // Category should still be OTHER_HASHED, not MOVIE_OTHER
        $this->assertSame(Category::OTHER_HASHED, $passable->bestResult->categoryId);
        $this->assertTrue($passable->lockedToMisc);
    }

    // ------------------------------------------------------------------
    // Tests: CategorizationResult::isSuccessful() changes
    // ------------------------------------------------------------------

    public function test_other_misc_with_matched_by_is_successful(): void
    {
        $result = new \App\Services\Categorization\CategorizationResult(
            Category::OTHER_MISC, 0.5, 'obfuscated_pattern'
        );

        $this->assertTrue($result->isSuccessful());
    }

    public function test_no_match_sentinel_is_not_successful(): void
    {
        $result = \App\Services\Categorization\CategorizationResult::noMatch();

        $this->assertFalse($result->isSuccessful());
    }

    public function test_other_hashed_is_successful(): void
    {
        $result = new \App\Services\Categorization\CategorizationResult(
            Category::OTHER_HASHED, 0.95, 'hash_md5'
        );

        $this->assertTrue($result->isSuccessful());
    }

    // ------------------------------------------------------------------
    // Tests: Debug output includes locked_to_misc
    // ------------------------------------------------------------------

    public function test_debug_output_includes_locked_to_misc(): void
    {
        $passable = $this->runPipeline('d41d8cd98f00b204e9800998ecf8427e');
        $output = $passable->toArray();

        $this->assertArrayHasKey('debug', $output);
        $this->assertArrayHasKey('locked_to_misc', $output['debug']);
        $this->assertTrue($output['debug']['locked_to_misc']);
    }

    public function test_debug_output_not_locked_for_legitimate(): void
    {
        $context = new ReleaseContext(
            releaseName: 'Some.Movie.2024.1080p.BluRay.x264-GROUP',
            groupId: 0,
            groupName: '',
        );
        $passable = new CategorizationPassable($context, debug: true);

        $miscPipe = new MiscPipe;
        $passable = $miscPipe->handle($passable, fn ($p) => $p);

        $output = $passable->toArray();
        $this->assertArrayHasKey('debug', $output);
        $this->assertArrayHasKey('locked_to_misc', $output['debug']);
        $this->assertFalse($output['debug']['locked_to_misc']);
    }
}
