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
        ];
    }
}
