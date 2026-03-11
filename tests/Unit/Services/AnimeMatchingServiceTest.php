<?php

namespace Tests\Unit\Services;

use App\Services\AnimeMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnimeMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    private AnimeMatchingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AnimeMatchingService;
    }

    /** @test */
    public function it_extracts_title_and_episode_from_dash_format(): void
    {
        $searchName = '[SubsPlease] Steins Gate - 01 [1080p].mkv';
        $result = $this->service->extractTitleEpisode($searchName);

        $this->assertNotNull($result);
        $this->assertEquals('Steins Gate', $result['title']);
        $this->assertEquals(1, $result['epno']);
    }

    /** @test */
    public function it_extracts_title_and_episode_from_e_format(): void
    {
        $searchName = 'Cowboy Bebop E05 [1080p].mkv';
        $result = $this->service->extractTitleEpisode($searchName);

        $this->assertNotNull($result);
        $this->assertEquals('Cowboy Bebop', $result['title']);
        $this->assertEquals(5, $result['epno']);
    }

    /** @test */
    public function it_extracts_movie_format(): void
    {
        $searchName = 'One Punch Man Movie [BD 1080p].mkv';
        $result = $this->service->extractTitleEpisode($searchName);

        $this->assertNotNull($result);
        $this->assertEquals('One Punch Man', $result['title']);
        $this->assertEquals(1, $result['epno']);
    }

    /** @test */
    public function it_strips_group_tags(): void
    {
        $searchName = '[HorribleSubs] Attack on Titan - 12 [720p].mkv';
        $result = $this->service->extractTitleEpisode($searchName);

        $this->assertNotNull($result);
        $this->assertEquals('Attack on Titan', $result['title']);
        $this->assertEquals(12, $result['epno']);
    }

    /** @test */
    public function it_returns_null_for_unparseable_names(): void
    {
        $searchName = 'Random.File.Name.Without.Episode.mkv';
        $result = $this->service->extractTitleEpisode($searchName);

        $this->assertNull($result);
    }
}
