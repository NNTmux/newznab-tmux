<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AnimeProcessor;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class AniDBParseTest extends TestCase
{
    private function makeAnimeProcessorInstance(): object
    {
        $rc = new ReflectionClass(AnimeProcessor::class);

        return $rc->newInstanceWithoutConstructor();
    }

    private function invokeExtract(object $instance, string $name): array
    {
        $rm = new ReflectionMethod($instance, 'extractTitleEpisode');
        $rm->setAccessible(true);
        /** @var array $result */
        $result = $rm->invoke($instance, $name);

        return $result;
    }

    private function getStatus(object $instance): ?int
    {
        $rp = new ReflectionProperty($instance, 'status');
        $rp->setAccessible(true);
        /** @var int|null $status */
        $status = $rp->getValue($instance);

        return $status;
    }

    public function test_standard_bracketed_pattern_with_numeric_episode(): void
    {
        $sut = $this->makeAnimeProcessorInstance();
        $name = '[HorribleSubs] My Hero Academia - 12 [1080p].mkv';
        $res = $this->invokeExtract($sut, $name);

        $this->assertSame(['title' => 'My Hero Academia'], $res);
    }

    public function test_e_prefix_with_underscores_and_dots(): void
    {
        $sut = $this->makeAnimeProcessorInstance();
        $name = '[Group] Neon_Genesis.Evangelion E01 [720p]';
        $res = $this->invokeExtract($sut, $name);

        $this->assertSame('Neon Genesis Evangelion', $res['title']);
    }

    public function test_bd_release_without_explicit_episode(): void
    {
        $sut = $this->makeAnimeProcessorInstance();
        $name = '[SomeGroup] Cowboy Bebop [BD][1080p]';
        $res = $this->invokeExtract($sut, $name);

        $this->assertSame('Cowboy Bebop', $res['title']);
    }

    public function test_simple_dash_pattern(): void
    {
        $sut = $this->makeAnimeProcessorInstance();
        $name = 'Naruto - 3 [480p]';
        $res = $this->invokeExtract($sut, $name);

        $this->assertSame(['title' => 'Naruto'], $res);
    }

    public function test_movie_and_ova_extracts_title(): void
    {
        $sut = $this->makeAnimeProcessorInstance();

        $movieName = '[Group] Cowboy Bebop - Movie - Something [BD]';
        $resMovie = $this->invokeExtract($sut, $movieName);
        $this->assertSame('Cowboy Bebop', $resMovie['title']);

        $ovaName = 'FLCL - OVA - Disc 1 [720p]';
        $resOva = $this->invokeExtract($sut, $ovaName);
        $this->assertSame('FLCL', $resOva['title']);
    }

    public function test_complete_series_extracts_title(): void
    {
        $sut = $this->makeAnimeProcessorInstance();
        $name = 'Attack on Titan - Complete Series [BD]';
        $res = $this->invokeExtract($sut, $name);

        $this->assertSame('Attack on Titan', $res['title']);
    }

    public function test_invalid_name_sets_status_and_returns_empty(): void
    {
        $sut = $this->makeAnimeProcessorInstance();
        $name = '';
        $res = $this->invokeExtract($sut, $name);

        $this->assertSame([], $res);

        $status = $this->getStatus($sut);
        $this->assertSame(-1, $status, 'Expected PROC_EXTFAIL (-1) status for invalid extraction');
    }
}
