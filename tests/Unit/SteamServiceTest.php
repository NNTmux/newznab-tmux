<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\SteamService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for SteamService.
 */
class SteamServiceTest extends TestCase
{
    private function getInstance(): SteamService
    {
        $ref = new ReflectionClass(SteamService::class);
        return $ref->newInstanceWithoutConstructor();
    }

    private function invokeMethod(object $obj, string $method, array $args = []): mixed
    {
        $ref = new ReflectionClass($obj);
        $m = $ref->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($obj, $args);
    }

    // ==========================================
    // Title Cleaning Tests
    // ==========================================

    #[DataProvider('cleanTitleProvider')]
    public function test_clean_title(string $input, string $expected): void
    {
        $service = $this->getInstance();
        $result = $service->cleanTitle($input);

        $this->assertSame($expected, $result, "Failed for input: {$input}");
    }

    public static function cleanTitleProvider(): array
    {
        return [
            // Basic scene releases
            ['Cyberpunk.2077-GOG', 'Cyberpunk 2077'],
            ['The.Witcher.3.Wild.Hunt-CODEX', 'The Witcher 3 Wild Hunt'],
            ['Elden.Ring-EMPRESS', 'Elden Ring'],
            ['Baldurs.Gate.3-SKIDROW', 'Baldurs Gate 3'],

            // With version numbers
            ['Starfield.v1.7.29-FLT', 'Starfield'],
            ['Cyberpunk.2077.v2.1.0.1-GOG', 'Cyberpunk 2077'],
            ['Game.v1.2.3.4.5-CODEX', 'Game'],

            // Edition tags
            ['ELDEN RING [v1.08 + DLCs] - EMPRESS', 'ELDEN RING'],
            ['The Witcher 3 Wild Hunt GOTY - GOG', 'The Witcher 3 Wild Hunt'],
            ['Horizon Zero Dawn Complete Edition - CODEX', 'Horizon Zero Dawn'],
            ['Red Dead Redemption 2 Ultimate Edition-EMPRESS', 'Red Dead Redemption 2'],

            // FitGirl-style releases - may include some noise
            ['[FitGirl] Red.Dead.Redemption.2.v1.0.1436.28.Repack', 'Red Dead Redemption 2'],

            // Complex releases - these may retain some noise
            ['Resident.Evil.4.Remake.REPACK-DODI', 'Resident Evil 4 Remake'],

            // Special characters
            ["Assassin's.Creed.Valhalla-CODEX", "Assassin's Creed Valhalla"],
            ['Tom.Clancys.Ghost.Recon.Breakpoint-EMPRESS', 'Tom Clancys Ghost Recon Breakpoint'],

            // Already clean
            ['Cyberpunk 2077', 'Cyberpunk 2077'],
            ['The Witcher 3 Wild Hunt', 'The Witcher 3 Wild Hunt'],

            // Edge cases
            ['', ''],
            ['  ', ''],
        ];
    }

    // ==========================================
    // Title Normalization Tests
    // ==========================================

    #[DataProvider('normalizeTitleProvider')]
    public function test_normalize_title(string $input, string $expectedContains, string $expectedNotContains = ''): void
    {
        $service = $this->getInstance();
        $result = $this->invokeMethod($service, 'normalizeTitle', [$input]);

        if ($expectedContains !== '') {
            $this->assertStringContainsString($expectedContains, $result, "Expected '{$expectedContains}' in result '{$result}'");
        }

        if ($expectedNotContains !== '') {
            $this->assertStringNotContainsString($expectedNotContains, $result, "Expected '{$expectedNotContains}' NOT in result '{$result}'");
        }
    }

    public static function normalizeTitleProvider(): array
    {
        return [
            ['The Witcher 3: Wild Hunt', 'witcher', 'the'],
            ['ELDEN RING', 'elden ring', ''],
            ['Red Dead Redemption 2', 'red dead redemption 2', ''],
            ['Cyberpunk 2077 GOTY Edition', 'cyberpunk 2077', 'goty'],
            ['Assassin\'s Creed IV: Black Flag', 'assassin', ''],
        ];
    }

    // ==========================================
    // Roman Numeral Replacement Tests
    // ==========================================

    #[DataProvider('romanNumeralProvider')]
    public function test_replace_roman_numerals(string $input, string $expected): void
    {
        $service = $this->getInstance();
        $result = $this->invokeMethod($service, 'replaceRomanNumerals', [$input]);

        $this->assertSame($expected, $result);
    }

    public static function romanNumeralProvider(): array
    {
        return [
            ['final fantasy vii', 'final fantasy 7'],
            ['final fantasy viii', 'final fantasy 8'],
            ['resident evil iv', 'resident evil 4'],
            ['witcher iii', 'witcher 3'],
            ['gta v', 'gta 5'],
            ['civilization vi', 'civilization 6'],
            ['elder scrolls iv oblivion', 'elder scrolls 4 oblivion'],
            ['dark souls ii', 'dark souls 2'],
            ['street fighter ii', 'street fighter 2'],
            ['assassins creed ii', 'assassins creed 2'],
            // Should not replace standalone 'i' (common in titles)
            ['i robot', 'i robot'],
            // Should handle multiple numerals
            ['title ii and iii', 'title 2 and 3'],
        ];
    }

    // ==========================================
    // Query Variants Generation Tests
    // ==========================================

    public function test_generate_query_variants_includes_base_title(): void
    {
        $service = $this->getInstance();
        $variants = $this->invokeMethod($service, 'generateQueryVariants', ['The Witcher 3: Wild Hunt - Game of the Year Edition']);

        $this->assertIsArray($variants);
        $this->assertNotEmpty($variants);

        // Should include variants without edition tags
        $found = false;
        foreach ($variants as $v) {
            if (stripos($v, 'game of the year') === false && stripos($v, 'witcher') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected base title variant without edition tags');
    }

    public function test_generate_query_variants_handles_colon_titles(): void
    {
        $service = $this->getInstance();
        $variants = $this->invokeMethod($service, 'generateQueryVariants', ["Assassin's Creed IV: Black Flag"]);

        $this->assertIsArray($variants);

        // Should include left side of colon
        $foundLeft = false;
        foreach ($variants as $v) {
            if (stripos($v, 'black flag') === false && stripos($v, "Assassin's Creed") !== false) {
                $foundLeft = true;
                break;
            }
        }
        $this->assertTrue($foundLeft, 'Expected left-side of colon as a variant');
    }

    // ==========================================
    // Title Scoring Tests
    // ==========================================

    #[DataProvider('scoreTitleProvider')]
    public function test_score_title(string $candidate, string $original, float $minScore): void
    {
        $service = $this->getInstance();
        $score = $this->invokeMethod($service, 'scoreTitle', [$candidate, $original]);

        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual($minScore, $score, "Score {$score} should be >= {$minScore} for '{$candidate}' vs '{$original}'");
    }

    public static function scoreTitleProvider(): array
    {
        return [
            // Exact matches
            ['Cyberpunk 2077', 'Cyberpunk 2077', 100.0],
            ['The Witcher 3: Wild Hunt', 'The Witcher 3: Wild Hunt', 100.0],

            // Close matches (should score high)
            ['The Witcher 3: Wild Hunt', 'The.Witcher.3.Wild.Hunt-CODEX', 90.0],
            ['Elden Ring', 'ELDEN RING - Deluxe Edition - EMPRESS', 70.0],
            ['Cyberpunk 2077', 'Cyberpunk.2077.v2.1-GOG', 70.0],
            ['Red Dead Redemption 2', '[FitGirl] Red.Dead.Redemption.2.Repack', 80.0],

            // Partial matches
            ['Witcher 3', 'The Witcher 3: Wild Hunt', 50.0],

            // Non-matches (should score low)
            ['Cyberpunk 2077', 'Elden Ring', 0.0],
        ];
    }

    public function test_score_title_perfect_normalized_match(): void
    {
        $service = $this->getInstance();

        // These should normalize to the same thing
        $score = $this->invokeMethod($service, 'scoreTitle', [
            'The Witcher 3: Wild Hunt',
            'The.Witcher.III.Wild.Hunt.GOTY-CODEX'
        ]);

        // With roman numeral conversion and normalization, these should be very similar
        $this->assertGreaterThanOrEqual(85.0, $score);
    }

    // ==========================================
    // LIKE Pattern Building Tests
    // ==========================================

    public function test_build_like_pattern(): void
    {
        $service = $this->getInstance();

        $pattern = $this->invokeMethod($service, 'buildLikePattern', ['Resident Evil 4']);
        $this->assertStringStartsWith('%', $pattern);
        $this->assertStringEndsWith('%', $pattern);
        $this->assertStringContainsString('resident', strtolower($pattern));
        $this->assertStringContainsString('evil', strtolower($pattern));
        $this->assertStringContainsString('4', $pattern);
    }

    public function test_build_like_pattern_handles_roman_numerals(): void
    {
        $service = $this->getInstance();

        $pattern = $this->invokeMethod($service, 'buildLikePattern', ['Resident Evil VII Biohazard']);
        $this->assertStringContainsString('7', $pattern);
        $this->assertStringNotContainsString('vii', strtolower($pattern));
    }

    // ==========================================
    // Tokenization Tests
    // ==========================================

    public function test_tokenize(): void
    {
        $service = $this->getInstance();

        $tokens = $this->invokeMethod($service, 'tokenize', ['The Witcher 3 Wild Hunt']);
        $this->assertIsArray($tokens);
        $this->assertContains('witcher', $tokens);
        $this->assertContains('3', $tokens);
        $this->assertContains('wild', $tokens);
        $this->assertContains('hunt', $tokens);
    }

    public function test_tokenize_deduplicates(): void
    {
        $service = $this->getInstance();

        $tokens = $this->invokeMethod($service, 'tokenize', ['game game game']);
        $this->assertCount(1, $tokens);
        $this->assertSame(['game'], $tokens);
    }

    // ==========================================
    // Strip Edition Tags Tests
    // ==========================================

    #[DataProvider('stripEditionTagsProvider')]
    public function test_strip_edition_tags(string $input, string $expected): void
    {
        $service = $this->getInstance();
        $result = $this->invokeMethod($service, 'stripEditionTags', [$input]);

        $this->assertSame($expected, $result);
    }

    public static function stripEditionTagsProvider(): array
    {
        return [
            ['Cyberpunk 2077 Ultimate Edition', 'Cyberpunk 2077'],
            ['Horizon Zero Dawn', 'Horizon Zero Dawn'], // No tags
        ];
    }

    // ==========================================
    // Integration-style Tests (Method Combinations)
    // ==========================================

    public function test_full_matching_workflow(): void
    {
        $service = $this->getInstance();

        // Simulate a scene release name
        $releaseName = 'The.Witcher.III.Wild.Hunt.GOTY.Edition-CODEX';

        // Clean it
        $clean = $service->cleanTitle($releaseName);
        $this->assertStringNotContainsString('.', $clean);
        $this->assertStringNotContainsString('CODEX', $clean);

        // Generate variants
        $variants = $this->invokeMethod($service, 'generateQueryVariants', [$clean]);
        $this->assertNotEmpty($variants);

        // Normalize for matching
        $normalized = $this->invokeMethod($service, 'normalizeTitle', [$clean]);
        $this->assertStringContainsString('witcher', $normalized);
        $this->assertStringContainsString('3', $normalized); // Roman numeral converted
    }

    public function test_matching_workflow_with_various_formats(): void
    {
        $testCases = [
            'Cyberpunk.2077-GOG' => 'cyberpunk 2077',
            'ELDEN RING [v1.08 + DLCs] - EMPRESS' => 'elden ring',
            '[FitGirl] Red.Dead.Redemption.2.v1.0.1436.28.Repack' => 'red dead redemption 2',
            'Baldurs_Gate_3_v1.0.2_MULTI12-EMPRESS' => 'baldurs gate 3',
        ];

        $service = $this->getInstance();

        foreach ($testCases as $input => $expectedInNormalized) {
            $clean = $service->cleanTitle($input);
            $normalized = $this->invokeMethod($service, 'normalizeTitle', [$clean]);

            $this->assertStringContainsString(
                $expectedInNormalized,
                $normalized,
                "Failed for input: {$input}"
            );
        }
    }
}

