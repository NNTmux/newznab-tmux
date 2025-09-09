<?php

declare(strict_types=1);

namespace Tests\Unit;

use Blacklight\processing\post\AniDB as AniDBProcessing;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AniDBProcessingTest extends TestCase
{
    private function invokeExtract(string $name, ?int &$status = null): array
    {
        $ref = new ReflectionClass(AniDBProcessing::class);
        // Bypass constructor to avoid DB / config calls.
        /** @var AniDBProcessing $instance */
        $instance = $ref->newInstanceWithoutConstructor();

        // Ensure status property exists and set to null before call.
        if ($ref->hasProperty('status')) {
            $propStatus = $ref->getProperty('status');
            $propStatus->setAccessible(true);
            $propStatus->setValue($instance, null);
        }

        $method = $ref->getMethod('extractTitleEpisode');
        $method->setAccessible(true);
        $result = $method->invoke($instance, $name);

        if (isset($propStatus)) {
            $status = $propStatus->getValue($instance);
        }

        return $result;
    }

    public function test_standard_episode_extraction(): void
    {
        $status = null;
        $res = $this->invokeExtract('[SubsPlease] My Hero Academia - 05 [1080p].mkv', $status);
        $this->assertSame(['title' => 'My Hero Academia', 'epno' => 5], $res);
        $this->assertNull($status, 'Status should remain null on successful extraction');
    }

    public function test_movie_token_maps_to_episode_one(): void
    {
        $status = null;
        $res = $this->invokeExtract('Spirited Away - Movie [BD 1080p]');
        $this->assertSame(1, $res['epno']);
        $this->assertSame('Spirited Away', $res['title']);
        $this->assertNull($status);
    }

    public function test_complete_series_maps_to_episode_zero(): void
    {
        $status = null;
        $res = $this->invokeExtract('Great Show - Complete Series [BD 1080p]');
        $this->assertSame(0, $res['epno']);
        $this->assertSame('Great Show', $res['title']);
        $this->assertNull($status);
    }

    public function test_underscore_and_periods_are_normalized(): void
    {
        $status = null;
        $res = $this->invokeExtract('[Group] My_Show.Name - 07 [720p]');
        $this->assertSame('My Show Name', $res['title']);
        $this->assertSame(7, $res['epno']);
        $this->assertNull($status);
    }

    public function test_failed_extraction_sets_status_and_returns_empty(): void
    {
        $status = null;
        $res = $this->invokeExtract('ThisWillNotMatchPattern');
        $this->assertSame([], $res);
        $this->assertIsInt($status);
        $this->assertLessThan(0, $status, 'Status should be negative on failure (PROC_EXTFAIL)');
    }
}
