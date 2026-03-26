<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AnimeProcessor;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AnimeMatchingServiceTest extends TestCase
{
    private function invokeExtract(string $searchName): array
    {
        $reflection = new ReflectionClass(AnimeProcessor::class);
        /** @var AnimeProcessor $processor */
        $processor = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('extractTitleEpisode');

        /** @var array $result */
        $result = $method->invoke($processor, $searchName);

        return $result;
    }

    public function test_extracts_title_from_dash_format(): void
    {
        $searchName = '[SubsPlease] Steins Gate - 01 [1080p].mkv';
        $result = $this->invokeExtract($searchName);

        $this->assertSame(['title' => 'Steins Gate'], $result);
    }

    public function test_extracts_title_from_e_format(): void
    {
        $searchName = 'Cowboy Bebop E05 [1080p].mkv';
        $result = $this->invokeExtract($searchName);

        $this->assertSame(['title' => 'Cowboy Bebop'], $result);
    }

    public function test_extracts_movie_format_title(): void
    {
        $searchName = 'One Punch Man Movie [BD 1080p].mkv';
        $result = $this->invokeExtract($searchName);

        $this->assertSame('One Punch Man', $result['title']);
    }

    public function test_strips_group_tags_from_title(): void
    {
        $searchName = '[HorribleSubs] Attack on Titan - 12 [720p].mkv';
        $result = $this->invokeExtract($searchName);

        $this->assertSame('Attack on Titan', $result['title']);
    }

    public function test_falls_back_to_cleaned_title_when_no_episode_pattern_exists(): void
    {
        $searchName = 'Random.File.Name.Without.Episode.mkv';
        $result = $this->invokeExtract($searchName);

        $this->assertSame(['title' => 'Random File Name Without Episode mkv'], $result);
    }
}
