<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class AniDBParseTest extends TestCase
{
    private function makeAniDBInstance(): object
    {
        $rc = new ReflectionClass(\Blacklight\processing\post\AniDB::class);

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
        $sut = $this->makeAniDBInstance();
        $name = '[HorribleSubs] My Hero Academia - 12 [1080p].mkv';
        $res = $this->invokeExtract($sut, $name);

        $this->assertSame(['title' => 'My Hero Academia', 'epno' => 12], $res);
    }

    public function test_e_prefix_with_underscores_and_dots(): void
    {
        $sut = $this->makeAniDBInstance();
        $name = '[Group] Neon_Genesis.Evangelion E01 [720p]';
        $res = $this->invokeExtract($sut, $name);

        $this->assertSame('Neon Genesis Evangelion', $res['title']);
        $this->assertSame(1, $res['epno']);
    }

    public function test_bd_release_without_explicit_episode_defaults_to_one(): void
    {
        $sut = $this->makeAniDBInstance();
        $name = '[SomeGroup] Cowboy Bebop [BD][1080p]';
        $res = $this->invokeExtract($sut, $name);

        $this->assertSame('Cowboy Bebop', $res['title']);
        $this->assertSame(1, $res['epno']);
    }

    public function test_simple_dash_pattern(): void
    {
        $sut = $this->makeAniDBInstance();
        $name = 'Naruto - 3 [480p]';
        $res = $this->invokeExtract($sut, $name);

        $this->assertSame(['title' => 'Naruto', 'epno' => 3], $res);
    }

    public function test_movie_and_ova_map_to_episode_one(): void
    {
        $sut = $this->makeAniDBInstance();

        $movieName = '[Group] Cowboy Bebop - Movie - Something [BD]';
        $resMovie = $this->invokeExtract($sut, $movieName);
        $this->assertSame('Cowboy Bebop', $resMovie['title']);
        $this->assertSame(1, $resMovie['epno']);

        $ovaName = 'FLCL - OVA - Disc 1 [720p]';
        $resOva = $this->invokeExtract($sut, $ovaName);
        $this->assertSame('FLCL', $resOva['title']);
        $this->assertSame(1, $resOva['epno']);
    }

    public function test_complete_series_maps_to_episode_zero(): void
    {
        $sut = $this->makeAniDBInstance();
        $name = 'Attack on Titan - Complete Series [BD]';
        $res = $this->invokeExtract($sut, $name);

        $this->assertSame('Attack on Titan', $res['title']);
        $this->assertSame(0, $res['epno']);
    }

    public function test_invalid_name_sets_status_and_returns_empty(): void
    {
        $sut = $this->makeAniDBInstance();
        $name = 'Invalid Release Name Without Episode';
        $res = $this->invokeExtract($sut, $name);

        $this->assertSame([], $res);

        $status = $this->getStatus($sut);
        $this->assertSame(-1, $status, 'Expected PROC_EXTFAIL (-1) status for invalid extraction');
    }
}
