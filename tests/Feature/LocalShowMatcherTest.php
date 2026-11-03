<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\TvProcessing\LocalShowMatcher;
use App\Services\TvProcessing\ParsedTvRelease;
use App\Services\TvProcessing\Providers\AbstractTvProvider;
use App\Services\TvProcessing\Providers\LocalDbProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\TvProcessingSchema;
use Tests\TestCase;

class LocalShowMatcherTest extends TestCase
{
    use TvProcessingSchema;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->createTvProcessingSchema();
    }

    #[Test]
    public function it_prefers_the_same_named_show_that_holds_the_episode(): void
    {
        // Production: "Jeopardy" (2002, GB, 41 episodes) took every US airdate release.
        $kids = $this->insertVideo('Jeopardy', ['countries_id' => 'GB', 'started' => '2002-04-26']);
        $this->insertEpisode($kids, 1, 1, '2002-04-26');
        $quiz = $this->insertVideo('Jeopardy!', ['started' => '1964-03-01']);
        $this->insertEpisode($quiz, 43, 30, '2026-10-27');

        $this->assertSame($quiz, $this->find('Jeopardy.2026.10.27.1080p.HDTV.AAC2.0.x264-MacP'));
        $this->assertSame($kids, $this->find('Jeopardy.S01E01.576p.WEB.h264-GRP'));
    }

    #[Test]
    public function it_uses_the_release_year_between_same_named_shows(): void
    {
        $classic = $this->insertVideo('Doctor Who', ['started' => '1963-11-23']);
        $revival = $this->insertVideo('Doctor Who (2005)', ['started' => '2005-03-26']);

        $this->assertSame($revival, $this->find('Doctor.Who.2005.S01E01.720p.BluRay.x264-GRP'));
        $this->assertSame($classic, $this->find('Doctor.Who.1963.S01E01.576p.DVDRip.x264-GRP'));
    }

    #[Test]
    public function it_matches_titles_with_numbers_and_punctuation(): void
    {
        $hundred = $this->insertVideo('The 100');
        $this->insertVideo('The');
        $deadline = $this->insertVideo('Deadline: White House', ['countries_id' => 'US']);

        $this->assertSame($hundred, $this->find('The.100.S01E01.Pilot.1080p.AMZN.WEB-DL.DD+5.1.H.264-playWEB'));
        $this->assertSame($deadline, $this->find('Deadline.White.House.4PM.2026.10.28.720p.WEB.x264-NGP'));
    }

    #[Test]
    public function it_falls_back_to_the_title_without_country_only_for_that_country(): void
    {
        $us = $this->insertVideo('Big Brother', ['countries_id' => 'US']);
        $this->insertEpisode($us, 28, 5);
        $au = $this->insertVideo('Big Brother (AU)', ['started' => '2001-04-23']);

        $this->assertSame($au, $this->find('Big.Brother.Australia.S2026E05.1080p.WEB'));
        $this->assertSame(0, $this->find('Big.Brother.Canada.S14E01.720p.WEB.h264-GRP'));
    }

    #[Test]
    public function it_finds_shows_through_their_aliases(): void
    {
        $videoId = $this->insertVideo('La Casa de Papel');
        DB::table('videos_aliases')->insert(['videos_id' => $videoId, 'title' => 'Money Heist']);
        $this->insertVideo('Money Heist', ['type' => AbstractTvProvider::TYPE_FILM]);

        $this->assertSame($videoId, $this->find('Money.Heist.S05E01.1080p.NF.WEB-DL.DDP5.1.x264-GRP'));
    }

    private function find(string $searchname): int
    {
        $provider = new LocalDbProvider;
        $parsed = ParsedTvRelease::fromParseInfo($provider->parseInfo($searchname));
        $this->assertNotNull($parsed, $searchname);

        return (new LocalShowMatcher($provider))->find($parsed);
    }
}
