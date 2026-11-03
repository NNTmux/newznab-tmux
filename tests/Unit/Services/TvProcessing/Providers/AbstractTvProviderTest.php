<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TvProcessing\Providers;

use App\Services\TvProcessing\ParsedTvRelease;
use App\Services\TvProcessing\Providers\LocalDbProvider;
use App\Services\TvProcessing\Providers\TraktProvider;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\ImdbScraperTestCase;

class AbstractTvProviderTest extends ImdbScraperTestCase
{
    #[Test]
    public function it_accepts_numeric_values_in_check_match(): void
    {
        Cache::flush();

        $provider = new TraktProvider;

        $this->assertSame(100.0, $provider->checkMatch('2024', 2024, 80));
        $this->assertSame(0.0, $provider->checkMatch('2024', 2023, 100));
    }

    #[Test]
    public function it_returns_zero_for_non_comparable_values(): void
    {
        Cache::flush();

        $this->assertSame(0.0, (new TraktProvider)->checkMatch('Example Show', ['bad'], 75));
    }

    #[Test]
    public function it_prefers_the_candidate_whose_start_year_matches_the_release(): void
    {
        $provider = new LocalDbProvider;

        $classic = $provider->scoreCandidate('Doctor Who', 2005, 'Doctor Who', '1963-11-23');
        $revival = $provider->scoreCandidate('Doctor Who', 2005, 'Doctor Who (2005)', '2005-03-26');

        $this->assertGreaterThan($classic, $revival);
        $this->assertGreaterThan(0.0, $classic, 'An exact title with the wrong year is demoted, not rejected.');
        $this->assertSame(0.0, $provider->scoreCandidate('Shameless', 2011, 'Shameful', '1990-01-01'));
        $this->assertSame(0.0, $provider->scoreCandidate('The Office', null, 'Parks and Recreation', '2009-04-09'));
    }

    #[Test]
    public function it_accepts_api_records_without_descriptive_fields(): void
    {
        $provider = new LocalDbProvider;

        $this->assertTrue($provider->checkRequiredAttr((object) ['id' => 1, 'name' => 'Web Show', 'summary' => null, 'country' => null], 'tvmazeS'));
        $this->assertTrue($provider->checkRequiredAttr((object) ['seasonNumber' => 2, 'number' => 3, 'overview' => null, 'aired' => null], 'tvdbE'));
        $this->assertTrue($provider->checkRequiredAttr(['season_number' => 0, 'episode_number' => 4, 'overview' => ''], 'tmdbE'));
        $this->assertFalse($provider->checkRequiredAttr((object) ['id' => 1, 'name' => ''], 'tvmazeS'));
        $this->assertFalse($provider->checkRequiredAttr(['season' => 1], 'traktE'));
        $this->assertFalse($provider->checkRequiredAttr(['id' => 1], 'unknownType'));
    }

    #[Test]
    public function it_normalizes_two_letter_country_codes(): void
    {
        $provider = new LocalDbProvider;

        $this->assertSame('US', $provider->normalizeCountry('us'));
        $this->assertSame('GB', $provider->normalizeCountry('UK'));
        $this->assertSame('', $provider->normalizeCountry('  '));
    }

    #[Test]
    public function it_maps_tvdb_three_letter_country_codes(): void
    {
        $provider = new LocalDbProvider;

        $this->assertSame('US', $provider->normalizeCountry('usa'));
        $this->assertSame('GB', $provider->normalizeCountry('gbr'));
        $this->assertGreaterThan(
            $provider->scoreCandidate('Ghosts', null, 'Ghosts', null, 'US', 'gbr'),
            $provider->scoreCandidate('Ghosts', null, 'Ghosts', null, 'US', 'usa'),
        );
    }

    #[Test]
    public function it_compares_titles_without_punctuation(): void
    {
        $provider = new LocalDbProvider;

        $this->assertSame(100.0, $provider->scoreCandidate('Greys Anatomy', null, "Grey's Anatomy"));
        $this->assertSame(100.0, $provider->scoreCandidate('Deadline White House', null, 'Deadline: White House'));
        $this->assertSame(100.0, $provider->scoreCandidate('Law and Order', null, 'Law & Order'));
    }

    #[Test]
    public function it_reads_a_country_qualifier_in_the_candidate_title(): void
    {
        $provider = new LocalDbProvider;

        $us = $provider->scoreCandidate('Shameless', null, 'Shameless (US)', null, 'US');
        $uk = $provider->scoreCandidate('Shameless', null, 'Shameless (UK)', null, 'US');

        $this->assertGreaterThan($uk, $us);
        $this->assertGreaterThan(0.0, $uk, 'A country mismatch demotes, it does not reject.');
    }

    #[Test]
    public function it_needs_a_closer_match_for_short_titles(): void
    {
        $provider = new LocalDbProvider;

        $this->assertSame(0.0, $provider->scoreCandidate('You', null, 'Yo'));
        $this->assertSame(0.0, $provider->scoreCandidate('Bones', null, 'Bone'));
        $this->assertGreaterThan(0.0, $provider->scoreCandidate('The Office', null, 'The Offices'));
    }

    #[Test]
    #[DataProvider('countryNames')]
    public function it_reads_only_a_trailing_country_qualifier(string $searchname, string $country, string $searchTitle): void
    {
        $parsed = ParsedTvRelease::fromParseInfo((new LocalDbProvider)->parseInfo($searchname));

        $this->assertNotNull($parsed);
        $this->assertSame($country, $parsed->countryCode());
        $this->assertSame($searchTitle, $parsed->searchTitle());
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function countryNames(): array
    {
        return [
            'code with year' => ['Who.Wants.to.Be.a.Millionaire.US.2020.S05E08.XviD-AFG', 'US', 'Who Wants to Be a Millionaire'],
            'uk becomes gb' => ['Love.Triangle.UK.Signed.S01E01.1080p.ALL4.WEB-DL.AAC2.0.H.264-FUZEER', 'GB', 'Love Triangle'],
            'country name' => ['Big.Brother.Australia.S2026E05.1080p.WEB', 'AU', 'Big Brother'],
            'ca inside a word' => ['Better.Call.Saul.S06E01.720p.WEB.h264-GRP', '', 'Better Call Saul'],
            'mixed case us is a word' => ['The.Last.of.Us.S02E01.1080p.WEB.h264-GRP', '', 'The Last of Us'],
            'country word not at the end' => ['Canada.Drag.Race.S05E01.720p.WEB.h264-GRP', '', 'Canada Drag Race'],
        ];
    }

    /**
     * @param  array{season: int|null, episode: int|null, seasonPack?: bool, airdate?: string|null, year?: int|null, cleanName?: string}|null  $expected
     */
    #[Test]
    #[DataProvider('releaseNames')]
    public function it_parses_season_episode_and_airdate_from_release_names(string $searchname, ?array $expected): void
    {
        $parsed = ParsedTvRelease::fromParseInfo((new LocalDbProvider)->parseInfo($searchname));

        if ($expected === null) {
            $this->assertNull($parsed);

            return;
        }

        $this->assertNotNull($parsed);
        $this->assertSame($expected['season'], $parsed->season, 'season');
        $this->assertSame($expected['episode'], $parsed->episode, 'episode');
        $this->assertSame($expected['seasonPack'] ?? false, $parsed->seasonPack, 'season pack');
        $this->assertSame($expected['airdate'] ?? null, $parsed->airdate, 'airdate');
        if (\array_key_exists('year', $expected)) {
            $this->assertSame($expected['year'], $parsed->year, 'year');
        }
        if (isset($expected['cleanName'])) {
            $this->assertSame($expected['cleanName'], $parsed->cleanName);
        }
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>|null}>
     */
    public static function releaseNames(): array
    {
        return [
            'standard' => ['The.Expanse.S01E02.720p.HDTV.x264-GRP', ['season' => 1, 'episode' => 2, 'cleanName' => 'The Expanse']],
            'multi episode keeps the first' => ['Show.Name.S02E03E04.720p.HDTV.x264-GRP', ['season' => 2, 'episode' => 3]],
            'multi episode range' => ['Show.Name.S02E03-04.720p.HDTV.x264-GRP', ['season' => 2, 'episode' => 3]],
            'special' => ['Doctor.Who.S00E05.720p.HDTV.x264-GRP', ['season' => 0, 'episode' => 5]],
            'season pack' => ['The.Expanse.S01.1080p.BluRay.x264-GRP', ['season' => 1, 'episode' => null, 'seasonPack' => true]],
            'disc' => ['The.Expanse.S01D2.1080p.BluRay.x264-GRP', ['season' => 1, 'episode' => null, 'seasonPack' => true]],
            'season word' => ['The Expanse Season 3 Complete 1080p', ['season' => 3, 'episode' => null, 'seasonPack' => true]],
            'x notation' => ['Show.Name.3x07.HDTV.XviD-GRP', ['season' => 3, 'episode' => 7]],
            'year with episode' => ['Doctor.Who.2005.S01E01.720p.BluRay.x264-GRP', ['season' => 1, 'episode' => 1, 'year' => 2005, 'cleanName' => 'Doctor Who (2005)']],
            'iso airdate' => ['The.Daily.Show.2024.03.05.Guest.720p.WEB.h264-GRP', ['season' => 0, 'episode' => 0, 'airdate' => '2024-03-05', 'year' => null]],
            'dotted european date' => ['Tagesschau.25.12.2020.German.720p.HDTV.x264-GRP', ['season' => 0, 'episode' => 0, 'airdate' => '2020-12-25']],
            'dotted ambiguous date is day first' => ['Tagesschau.05.12.2020.German.720p.HDTV.x264-GRP', ['season' => 0, 'episode' => 0, 'airdate' => '2020-12-05']],
            'dashed date is month first' => ['Show.Name.05-12-2020.720p.HDTV.x264-GRP', ['season' => 0, 'episode' => 0, 'airdate' => '2020-05-12']],
            'dashed date with day over twelve' => ['Show.Name.12-25-2020.720p.HDTV.x264-GRP', ['season' => 0, 'episode' => 0, 'airdate' => '2020-12-25']],
            'two digit year' => ['Show.Name.24.12.23.720p.HDTV.x264-GRP', ['season' => 0, 'episode' => 0, 'airdate' => '2023-12-24']],
            'year and explicit episode' => ['Show.Name.2019.E05.720p.HDTV.x264-GRP', ['season' => 2019, 'episode' => 5]],
            'year and resolution is not an episode' => ['Show.Name.2019.1080p.WEB.h264-GRP', null],
            'part' => ['Show.Name.Part.3.720p.HDTV.x264-GRP', ['season' => 1, 'episode' => 3]],
            'ep prefix' => ['Band.Of.Brothers.EP06.Bastogne.DVDRiP.XviD-DEiTY', ['season' => 1, 'episode' => 6]],
            'anime style' => ['[SubsPlease]_Some_Anime_-_06_[1080p].mkv', ['season' => 1, 'episode' => 6]],
            'obfuscated hash' => ['a3f8e2c19b7d4e6f8a1b2c3d4e5f6a7b', null],
            'title ending in a number' => ['The.100.S01E01.Pilot.1080p.AMZN.WEB-DL.DD+5.1.H.264-playWEB', ['season' => 1, 'episode' => 1, 'cleanName' => 'The 100']],
            'title number equal to the episode' => ['Station.19.S07E19.720p.HDTV.x264-GRP', ['season' => 7, 'episode' => 19, 'cleanName' => 'Station 19']],
            'title that is a language word' => ['Greek.S01E01.Pilot.1080p.DSNP.WEB-DL.DD+5.1.H.264-playWEB', ['season' => 1, 'episode' => 1, 'cleanName' => 'Greek']],
            'title starting with a language word' => ['Russian.Doll.S02E01.720p.WEB.h264-GRP', ['season' => 2, 'episode' => 1, 'cleanName' => 'Russian Doll']],
            'trailing language tag' => ['Some.Show.German.DL.S01E01.720p.HDTV.x264-GRP', ['season' => 1, 'episode' => 1, 'cleanName' => 'Some Show']],
            'sign language version' => ['Gogglebox.Signed.S21E01.Gogglebox.1080p.ALL4.WEB-DL.AAC2.0.H.264-FUZEER', ['season' => 21, 'episode' => 1, 'cleanName' => 'Gogglebox']],
            'html entity leftover' => ['Preston.andamp.Brianna.S01E01.1080p.FAWE.WEB-DL.AAC2.0.H.264-FUZEER', ['season' => 1, 'episode' => 1, 'cleanName' => 'Preston and Brianna']],
            'broadcast time slot' => ['Deadline.White.House.4PM.2026.10.28.720p.WEB.x264-NGP', ['season' => 0, 'episode' => 0, 'airdate' => '2026-10-28', 'cleanName' => 'Deadline White House']],
            'volume collection' => ['House.Hunters.International.Volume.9.2024.S196E01.Feeling.the.Squeeze.1080p.DSCP.WEB-DL.DDP2.0.H.264-FUZEER', ['season' => 196, 'episode' => 1, 'year' => null, 'cleanName' => 'House Hunters International']],
            'spam site prefix' => ['movieddl.me.Friends.S01E01.1080p.BluRay.x264-TENEIGHTY', ['season' => 1, 'episode' => 1, 'cleanName' => 'Friends']],
            'year as season' => ['Big.Brother.Australia.S2026E05.1080p.WEB', ['season' => 2026, 'episode' => 5, 'cleanName' => 'Big Brother Australia']],
            'group name is not a season pack' => ['The.Daily.Show.2024.01.15.720p.WEB.h264-S4LT', ['season' => 0, 'episode' => 0, 'airdate' => '2024-01-15']],
            'year after the episode is not the show year' => ['Show.Name.S02E03.Class.of.1999.720p.HDTV.x264-GRP', ['season' => 2, 'episode' => 3, 'year' => null, 'cleanName' => 'Show Name']],
            'quoted par2 filename' => ['"ghost.hunters.s08e05.hdtv.xvid-nmbstv.vol03+04.par2"', ['season' => 8, 'episode' => 5, 'cleanName' => 'ghost hunters']],
            'dotted acronym followed by a word' => ['S.W.A.T.Exiles.S01E06.1080p.x265-ELiTE', ['season' => 1, 'episode' => 6, 'cleanName' => 'SWAT Exiles']],
        ];
    }
}
