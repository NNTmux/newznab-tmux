<?php

namespace Tests\Unit;

use Blacklight\Steam;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class SteamMatchTest extends TestCase
{
    private function invokePrivate(object $obj, string $method, array $args = [])
    {
        $ref = new ReflectionClass($obj);
        $m = $ref->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($obj, $args);
    }

    private function getInstance(): Steam
    {
        // Bypass constructor to avoid external dependencies
        $ref = new ReflectionClass(Steam::class);
        /** @var Steam $inst */
        $inst = $ref->newInstanceWithoutConstructor();
        return $inst;
    }

    public function test_normalize_title_handles_scene_noise_and_roman_numerals(): void
    {
        $steam = $this->getInstance();
        $norm = $this->invokePrivate($steam, 'normalizeTitle', ['The.Witcher.III.Wild.Hunt.GOTY-FLT']);
        $this->assertNotSame('', $norm);
        $this->assertStringContainsString('witcher', $norm);
        $this->assertStringContainsString('3', $norm);
        $this->assertStringContainsString('wild', $norm);
        $this->assertStringContainsString('hunt', $norm);
        // Ensure noise removed
        $this->assertStringNotContainsString('goty', $norm);
        $this->assertStringNotContainsString('flt', $norm);
    }

    public function test_score_title_gives_high_similarity_for_common_case(): void
    {
        $steam = $this->getInstance();
        $cand = $this->invokePrivate($steam, 'normalizeTitle', ['The Witcher 3: Wild Hunt']);
        $orig = $this->invokePrivate($steam, 'normalizeTitle', ['The.Witcher.III.Wild.Hunt.GOTY-FLT']);
        $this->assertSame($cand, $orig, 'Normalized mismatch: cand=['.$cand.'] orig=['.$orig.']');
        $score = $this->invokePrivate($steam, 'scoreTitle', ['The Witcher 3: Wild Hunt', 'The.Witcher.III.Wild.Hunt.GOTY-FLT']);
        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(90.0, $score);
    }

    public function test_to_sql_like_builds_reasonable_pattern(): void
    {
        $steam = $this->getInstance();
        $like = $this->invokePrivate($steam, 'toSqlLike', ['Resident Evil VII Biohazard']);
        $this->assertIsString($like);
        $this->assertStringStartsWith('%', $like);
        $this->assertStringContainsString('%resident%evil%', $like);
        // Roman numerals normalized
        $this->assertStringContainsString('%7%', $like);
        $this->assertStringEndsWith('%', $like);
    }

    public function test_generate_query_variants_includes_colon_left_side(): void
    {
        $steam = $this->getInstance();
        $variants = $this->invokePrivate($steam, 'generateQueryVariants', ["Assassin's Creed IV: Black Flag - Deluxe Edition"]);
        $this->assertIsArray($variants);
        $this->assertNotEmpty($variants);
        $foundLeft = false;
        foreach ($variants as $v) {
            if (stripos($v, "Assassin's Creed") !== false && stripos($v, 'black flag') === false) {
                $foundLeft = true;
                break;
            }
        }
        $this->assertTrue($foundLeft, 'Expected left-side of colon to be among variants');
    }
}
