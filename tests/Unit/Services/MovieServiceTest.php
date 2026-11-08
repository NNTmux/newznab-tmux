<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use aharen\OMDbAPI;
use App\Facades\Search;
use App\Models\MovieInfo;
use App\Models\Release;
use App\Services\ImdbScraper;
use App\Services\MovieService;
use App\Services\MoviesProcessor;
use App\Services\Runners\PostProcessRunner;
use App\Services\TmdbClient;
use App\Services\TraktService;
use App\Services\TvProcessing\Providers\TraktProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\ImdbScraperTestCase;

class MovieServiceTest extends ImdbScraperTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('movieinfo');
        Schema::create('movieinfo', function (Blueprint $table): void {
            $table->id();
            $table->string('imdbid')->unique();
            $table->unsignedInteger('tmdbid')->default(0);
            $table->unsignedInteger('traktid')->default(0);
            $table->string('title')->default('');
            $table->string('tagline')->default('');
            $table->string('rating', 4)->default('');
            $table->string('rtrating', 10)->default('');
            $table->string('plot')->default('');
            $table->string('year', 4)->default('');
            $table->string('genre', 64)->default('');
            $table->string('type', 32)->default('');
            $table->string('director', 64)->default('');
            $table->text('actors')->default('');
            $table->string('language', 64)->default('');
            $table->boolean('cover')->default(false);
            $table->boolean('backdrop')->default(false);
            $table->string('trailer')->default('');
            $table->timestamps();
        });

        Schema::dropIfExists('releases');
        Schema::create('releases', function (Blueprint $table): void {
            $table->id();
            $table->string('searchname')->default('');
            $table->unsignedInteger('categories_id')->default(0);
            $table->string('imdbid')->nullable();
            $table->unsignedBigInteger('movieinfo_id')->nullable();
            $table->boolean('isrenamed')->default(false);
        });
    }

    #[Test]
    public function it_accepts_numeric_trakt_years_when_matching_movie_metadata(): void
    {
        Cache::flush();

        $service = $this->makeMovieServiceForTraktResponse([
            'title' => 'Example Movie',
            'year' => 2024,
            'ids' => ['trakt' => 12345],
            'overview' => 'Test overview',
            'tagline' => 'Test tagline',
            'genres' => ['Drama'],
            'rating' => 7.5,
            'votes' => 10,
            'language' => 'en',
            'runtime' => 100,
            'trailer' => '',
        ]);

        $this->setMovieServiceProperty($service, 'currentTitle', 'Example Movie');
        $this->setMovieServiceProperty($service, 'currentYear', '2024');

        $movie = $service->fetchTraktTVProperties('8169446');

        $this->assertIsArray($movie);
        $this->assertSame('Example Movie', $movie['title']);
        $this->assertSame(2024, $movie['year']);
    }

    #[Test]
    public function it_still_rejects_mismatched_numeric_trakt_years(): void
    {
        Cache::flush();

        $service = $this->makeMovieServiceForTraktResponse([
            'title' => 'Example Movie',
            'year' => 2024,
            'ids' => ['trakt' => 12345],
        ]);

        $this->setMovieServiceProperty($service, 'currentTitle', 'Example Movie');
        $this->setMovieServiceProperty($service, 'currentYear', '2022');

        $this->assertFalse($service->fetchTraktTVProperties('8169447'));
    }

    #[Test]
    public function it_finds_movie_info_for_imdb_ids_with_meaningful_leading_zeroes(): void
    {
        Cache::flush();

        $service = new MovieService;
        $service->echooutput = false;

        $service->update([
            'imdbid' => '0137523',
            'title' => 'Example Movie',
            'year' => '2024',
        ]);

        $movie = $service->getMovieInfo('0137523');

        $this->assertNotNull($movie);
        $this->assertSame('0137523', $movie->imdbid);
        $this->assertSame('Example Movie', $movie->title);
    }

    #[Test]
    public function it_returns_existing_trailer_for_imdb_ids_with_meaningful_leading_zeroes(): void
    {
        Cache::flush();

        $service = new MovieService;
        $service->echooutput = false;

        $service->update([
            'imdbid' => '0137523',
            'title' => 'Example Movie',
            'year' => '2024',
            'trailer' => 'https://example.test/embed/trailer',
        ]);

        $this->assertSame('https://example.test/embed/trailer', $service->getTrailer('0137523'));
    }

    #[Test]
    public function it_distinguishes_pending_movie_lookup_sentinels_from_failed_empty_values(): void
    {
        $this->assertTrue(imdb_id_needs_lookup(null));
        $this->assertTrue(imdb_id_needs_lookup('0'));
        $this->assertTrue(imdb_id_needs_lookup('0000000'));
        $this->assertTrue(imdb_id_needs_lookup('00000000'));
        $this->assertFalse(imdb_id_needs_lookup(''));
        $this->assertFalse(imdb_id_needs_lookup('0137523'));
    }

    #[Test]
    public function it_keeps_a_found_imdb_id_when_metadata_refresh_fails(): void
    {
        Cache::flush();

        $service = new class extends MovieService
        {
            public function updateMovieInfo(string $imdbId): bool
            {
                return false;
            }
        };
        $service->echooutput = false;

        Release::query()->insert([
            'id' => 2,
            'searchname' => 'Example.Movie.2024',
            'categories_id' => 2000,
            'imdbid' => null,
            'movieinfo_id' => null,
        ]);

        $result = $service->doMovieUpdate('tt0137523', 'IMDb(scrape)', 2);

        $this->assertSame('0137523', $result);
        $this->assertSame('0137523', Release::query()->whereKey(2)->value('imdbid'));
        $this->assertNull(Release::query()->whereKey(2)->value('movieinfo_id'));
    }

    #[Test]
    public function it_does_not_match_sequels_from_adjacent_years_in_local_search(): void
    {
        Cache::flush();

        $service = new MovieService;
        $service->echooutput = false;
        $service->update([
            'imdbid' => '13622970',
            'title' => 'Moana 2',
            'year' => '2024',
        ]);

        $this->setMovieServiceProperty($service, 'currentTitle', 'Moana');
        $this->setMovieServiceProperty($service, 'currentYear', '2026');

        $this->assertFalse($this->invokeLocalIMDBSearch($service));
    }

    #[Test]
    public function it_prefers_the_exact_year_title_match_in_local_search(): void
    {
        Cache::flush();

        $service = new MovieService;
        $service->echooutput = false;
        $service->update([
            'imdbid' => '13622970',
            'title' => 'Moana 2',
            'year' => '2024',
        ]);
        $service->update([
            'imdbid' => '25347582',
            'title' => 'Moana',
            'year' => '2026',
        ]);

        $this->setMovieServiceProperty($service, 'currentTitle', 'Moana');
        $this->setMovieServiceProperty($service, 'currentYear', '2026');

        $this->assertSame('25347582', $this->invokeLocalIMDBSearch($service));
    }

    #[Test]
    public function it_rejects_same_title_candidates_from_a_different_year_in_local_search(): void
    {
        Cache::flush();

        $service = new MovieService;
        $service->echooutput = false;
        $service->update([
            'imdbid' => '0137523',
            'title' => 'Example Movie',
            'year' => '2022',
        ]);

        $this->setMovieServiceProperty($service, 'currentTitle', 'Example Movie');
        $this->setMovieServiceProperty($service, 'currentYear', '2024');

        $this->assertFalse($this->invokeLocalIMDBSearch($service));
    }

    #[Test]
    public function it_allows_a_one_year_mislabel_for_exact_title_matches_in_local_search(): void
    {
        Cache::flush();

        $service = new MovieService;
        $service->echooutput = false;
        $service->update([
            'imdbid' => '10703978',
            'title' => 'The Free Fall',
            'year' => '2021',
        ]);

        $this->setMovieServiceProperty($service, 'currentTitle', 'The Free Fall');
        $this->setMovieServiceProperty($service, 'currentYear', '2022');

        $this->assertSame('10703978', $this->invokeLocalIMDBSearch($service));
    }

    #[Test]
    public function it_accepts_a_candidate_with_a_one_year_mislabel_for_exact_title_matches(): void
    {
        Cache::flush();

        $service = new MovieService;
        $service->echooutput = false;
        $service->update([
            'imdbid' => '10703978',
            'title' => 'The Free Fall',
            'year' => '2021',
        ]);

        Release::query()->insert([
            'id' => 6,
            'searchname' => 'The.Free.Fall.2022.1080p.AMZN.WEB-DL.DDP5.1.H.264-GPRS',
            'categories_id' => 2000,
            'imdbid' => null,
            'movieinfo_id' => null,
        ]);

        $this->setMovieServiceProperty($service, 'currentTitle', 'The Free Fall');
        $this->setMovieServiceProperty($service, 'currentYear', '2022');

        $this->assertSame('10703978', $service->doMovieUpdate('tt10703978', 'Local DB', 6));
        $this->assertSame('10703978', Release::query()->whereKey(6)->value('imdbid'));
    }

    #[Test]
    public function it_accepts_a_candidate_with_an_alternate_title_when_the_year_matches(): void
    {
        Cache::flush();

        $service = new MovieService;
        $service->echooutput = false;
        $service->update([
            'imdbid' => '39369643',
            'title' => 'State of Fear',
            'year' => '2026',
        ]);

        Release::query()->insert([
            'id' => 5,
            'searchname' => 'Salve.Geral.Irmandade.2026.2160p.Netflix.WEB-DL.HEVC.10bit.DDP5.1.Atmos.6Audios-QHstudIo',
            'categories_id' => 2000,
            'imdbid' => null,
            'movieinfo_id' => null,
        ]);

        $this->setMovieServiceProperty($service, 'currentTitle', 'Salve Geral Irmandade');
        $this->setMovieServiceProperty($service, 'currentYear', '2026');

        $this->assertSame('39369643', $service->doMovieUpdate('tt39369643', 'IMDb(scrape)', 5));
        $this->assertSame('39369643', Release::query()->whereKey(5)->value('imdbid'));
    }

    #[Test]
    public function it_rejects_a_candidate_whose_local_movie_info_year_mismatches(): void
    {
        Cache::flush();

        $service = new MovieService;
        $service->echooutput = false;
        $service->update([
            'imdbid' => '13622970',
            'title' => 'Moana 2',
            'year' => '2024',
        ]);

        Release::query()->insert([
            'id' => 3,
            'searchname' => 'Moana.2026.1080p.AMZN.WEB-DL.H.264-HDShare',
            'categories_id' => 2000,
            'imdbid' => null,
            'movieinfo_id' => null,
        ]);

        $this->setMovieServiceProperty($service, 'currentTitle', 'Moana');
        $this->setMovieServiceProperty($service, 'currentYear', '2026');

        $this->assertFalse($service->doMovieUpdate('tt13622970', 'Local DB', 3));
        $this->assertNull(Release::query()->whereKey(3)->value('imdbid'));
    }

    #[Test]
    public function it_does_not_assign_an_imdb_id_when_a_metadata_fetcher_rejects_the_candidate(): void
    {
        Cache::flush();

        $service = new class extends MovieService
        {
            public function updateMovieInfo(string $imdbId): bool
            {
                $this->candidateMismatch = true;

                return false;
            }
        };
        $service->echooutput = false;

        Release::query()->insert([
            'id' => 4,
            'searchname' => 'Moana.2026.1080p.AMZN.WEB-DL.H.264-HDShare',
            'categories_id' => 2000,
            'imdbid' => null,
            'movieinfo_id' => null,
        ]);

        $result = $service->doMovieUpdate('tt0137523', 'IMDb(scrape)', 4);

        $this->assertFalse($result);
        $this->assertNull(Release::query()->whereKey(4)->value('imdbid'));
    }

    #[Test]
    public function it_skips_imdb_lookups_silently_when_both_imdb_sources_are_disabled(): void
    {
        config([
            'nntmux_api.imdb_scraper_enabled' => false,
            'nntmux_api.imdbapi_dev_enabled' => false,
        ]);
        $mock = $this->bindImdbScraper([
            new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], file_get_contents(base_path('tests/Fixtures/imdb/title_jsonld.html')) ?: ''),
        ]);
        Log::spy();

        $service = new MovieService;
        $service->echooutput = false;

        $this->assertFalse($service->fetchIMDBProperties('1234567'));
        $this->assertCount(1, $mock);
        $this->assertFalse(Cache::has('imdb_movie_'.md5('1234567')));
        Log::shouldNotHaveReceived('warning');
    }

    #[Test]
    public function it_logs_a_waf_block_once_and_skips_later_titles(): void
    {
        config(['nntmux_api.imdbapi_dev_enabled' => false]);
        $mock = $this->bindImdbScraper([
            new Response(202, ['Content-Type' => 'text/html; charset=UTF-8'], '<html><script>window.awsWafCookieDomainList=[];window.gokuProps={};</script></html>'),
        ]);
        Log::spy();

        $service = new MovieService;
        $service->echooutput = false;

        foreach (['1234567', '2345678', '3456789'] as $imdbId) {
            $this->assertFalse($service->fetchIMDBProperties($imdbId));
        }

        $this->assertCount(0, $mock);
        Log::shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function it_accepts_imdb_metadata_that_matches_an_alternate_title(): void
    {
        config(['nntmux_api.imdb_scraper_enabled' => true, 'nntmux_api.imdbapi_dev_enabled' => false]);
        $this->bindImdbScraper([
            new Response(200, ['Content-Type' => 'text/html'], '<html><head><script type="application/ld+json">'
                .'{"@type":"Movie","name":"Life Is Beautiful","alternateName":"La vita è bella","datePublished":"1997-12-20"}'
                .'</script></head></html>'),
        ]);
        $service = new MovieService;
        $service->echooutput = false;
        $this->setMovieServiceProperty($service, 'currentTitle', 'La Vita E Bella');
        $this->setMovieServiceProperty($service, 'currentYear', '');

        $data = $service->fetchIMDBProperties('0118799');

        $this->assertIsArray($data);
        $this->assertSame('Life Is Beautiful', $data['title']);
    }

    #[Test]
    public function it_tries_exact_year_imdb_search_candidates_first(): void
    {
        $scraper = Mockery::mock(ImdbScraper::class);
        $scraper->shouldReceive('isScrapingEnabled')->andReturn(true);
        $scraper->shouldReceive('search')->with('Crash')->andReturn([
            ['imdbid' => '1000001', 'title' => 'Crash', 'year' => '2005', 'type' => 'movie', 'cover' => ''],
            ['imdbid' => '1000002', 'title' => 'Crash', 'year' => '2004', 'type' => 'movie', 'cover' => ''],
            ['imdbid' => '1000003', 'title' => 'Crush', 'year' => '1990', 'type' => 'movie', 'cover' => ''],
        ]);
        $this->app->instance(ImdbScraper::class, $scraper);

        $service = new class extends MovieService
        {
            /** @var list<string> */
            public array $attempts = [];

            public function doMovieUpdate(string $buffer, string $service, int $id, int $processImdb = 1): string|false
            {
                $this->attempts[] = $buffer;

                return false;
            }
        };
        $service->echooutput = false;
        $this->setMovieServiceProperty($service, 'currentTitle', 'Crash');
        $this->setMovieServiceProperty($service, 'currentYear', '2004');

        $this->assertFalse((new \ReflectionMethod($service, 'searchIMDb'))->invoke($service, 1));
        $this->assertSame(['tt1000002', 'tt1000001'], $service->attempts);
    }

    #[Test]
    public function it_uses_an_embedded_imdb_id_before_searching_other_sources(): void
    {
        $service = new class extends MovieService
        {
            public int $localSearchCalls = 0;

            protected function localIMDBSearch(): string|false
            {
                $this->localSearchCalls++;

                return parent::localIMDBSearch();
            }
        };
        $service->echooutput = false;
        MovieInfo::query()->create(['imdbid' => '0137523', 'title' => 'Example Movie', 'year' => '2024']);
        Release::query()->insert([
            'id' => 1,
            'searchname' => 'Example.Movie.2024.1080p.BluRay.imdb-tt0137523',
            'categories_id' => 2040,
            'imdbid' => null,
        ]);
        Search::shouldReceive('updateRelease')->once()->with(1);

        $service->processMovieReleases();

        $this->assertSame('0137523', Release::query()->whereKey(1)->value('imdbid'));
        $this->assertSame('SearchName', $service->service);
        $this->assertSame(0, $service->localSearchCalls);
    }

    #[Test]
    public function it_searches_other_sources_when_an_embedded_imdb_id_is_rejected(): void
    {
        $service = new MovieService;
        $service->echooutput = false;
        MovieInfo::query()->create(['imdbid' => '13622970', 'title' => 'Another Movie', 'year' => '2020']);
        MovieInfo::query()->create(['imdbid' => '0137523', 'title' => 'Example Movie', 'year' => '2024']);
        Release::query()->insert([
            'id' => 1,
            'searchname' => 'Example.Movie.2024.1080p.BluRay.imdb-tt13622970',
            'categories_id' => 2040,
            'imdbid' => null,
        ]);
        Search::shouldReceive('updateRelease')->once()->with(1);

        $service->processMovieReleases();

        $this->assertSame('0137523', Release::query()->whereKey(1)->value('imdbid'));
        $this->assertSame('Local DB', $service->service);
    }

    #[Test]
    public function it_marks_each_failed_lookup_before_moving_to_the_next_release(): void
    {
        Cache::flush();
        Log::spy();
        config(['nntmux_api.omdb_api_key' => '', 'nntmux_api.trakttv_api_key' => '']);
        $this->app->instance(ImdbScraper::class, Mockery::mock(ImdbScraper::class, ['isScrapingEnabled' => false]));
        $this->app->instance(TmdbClient::class, Mockery::mock(TmdbClient::class, ['isConfigured' => false]));
        Search::shouldReceive('updateRelease')->once()->with(2);
        Search::shouldReceive('updateRelease')->once()->with(1);

        Release::query()->insert([
            ['id' => 1, 'searchname' => 'Crashing.Movie.2020.1080p.BluRay.x264-GRP', 'categories_id' => 2040, 'imdbid' => null],
            ['id' => 2, 'searchname' => 'Missing.Movie.2021.1080p.BluRay.x264-GRP', 'categories_id' => 2040, 'imdbid' => null],
            ['id' => 3, 'searchname' => 'Renamed.Movie.2022.1080p.BluRay.x264-GRP', 'categories_id' => 2040, 'imdbid' => null],
        ]);

        $service = new class extends MovieService
        {
            public ?string $missingImdbWhenCrashing = null;

            protected function localIMDBSearch(): string|false
            {
                if ($this->currentRelID === '3') {
                    Release::query()->whereKey(3)->update(['searchname' => 'Another.Movie.2022.1080p.BluRay.x264-GRP']);
                }

                if ($this->currentRelID === '1') {
                    $this->missingImdbWhenCrashing = Release::query()->whereKey(2)->value('imdbid');

                    throw new \RuntimeException('provider crashed');
                }

                return false;
            }
        };
        $service->echooutput = false;

        $service->processMovieReleases();

        $this->assertSame('', $service->missingImdbWhenCrashing);
        $this->assertSame('', Release::query()->whereKey(1)->value('imdbid'));
        $this->assertSame('', Release::query()->whereKey(2)->value('imdbid'));
        $this->assertNull(Release::query()->whereKey(3)->value('imdbid'));
        Log::shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function it_drops_the_old_movie_link_when_the_lookup_of_a_renamed_release_fails(): void
    {
        Cache::flush();
        config(['nntmux_api.omdb_api_key' => '', 'nntmux_api.trakttv_api_key' => '']);
        $this->app->instance(ImdbScraper::class, Mockery::mock(ImdbScraper::class, ['isScrapingEnabled' => false]));
        $this->app->instance(TmdbClient::class, Mockery::mock(TmdbClient::class, ['isConfigured' => false]));
        Search::shouldReceive('updateRelease')->once()->with(1);

        // A rename reset imdbid but the release still points at the movie of its old name.
        Release::query()->insert([
            'id' => 1,
            'searchname' => 'Ghostbusters.2.German.1989.AC3.DVDRip.XviD-CENTi',
            'categories_id' => 2040,
            'imdbid' => null,
            'movieinfo_id' => 7,
        ]);

        $service = new class extends MovieService
        {
            protected function localIMDBSearch(): string|false
            {
                return false;
            }
        };
        $service->echooutput = false;

        $service->processMovieReleases();

        $this->assertSame('', Release::query()->whereKey(1)->value('imdbid'));
        $this->assertNull(Release::query()->whereKey(1)->value('movieinfo_id'));
    }

    /**
     * @param  list<string>  $alternates
     */
    #[Test]
    #[DataProvider('releaseNameProvider')]
    public function it_parses_title_and_year_from_release_names(string $searchName, string $title, string $year, array $alternates): void
    {
        $service = new MovieService;

        $this->assertTrue($this->invokeParse($service, $searchName));
        $this->assertSame($title, $this->getMovieServiceProperty($service, 'currentTitle'));
        $this->assertSame($year, $this->getMovieServiceProperty($service, 'currentYear'));
        $this->assertSame($alternates, $this->getMovieServiceProperty($service, 'alternateTitles'));
    }

    /**
     * Real searchnames from unmatched production releases.
     *
     * @return array<string, array{string, string, string, list<string>}>
     */
    public static function releaseNameProvider(): array
    {
        return [
            'hyphenated title' => ['Re-Animator.1985.2160p.UHD.BDRip.HEVC.10bit.DV.HDR.DTS-HD.MA.5.1-MUSHR00M', 'Re-Animator', '1985', []],
            'hyphenated multi-word title' => ['Spider-Man.Brand.New.Day.2026.2160p.iT.WEB-DL.DDP5.1.Atmos.H.265-Kitsune', 'Spider-Man Brand New Day', '2026', []],
            'two letter title' => ['Pi.1998.UHD.BluRay.1080p.DD+Atmos.5.1.DoVi.HDR10+.x265-SM737', 'Pi', '1998', []],
            'apostrophe' => ["The.Draughtsman's.Contract.1982.720p.BluRay.DD+2.0.x264-playHD", "The Draughtsman's Contract", '1982', []],
            'no year, language tags' => ['Planes.German.DL.1080p.BluRay.X264-EXQUiSiTE', 'Planes', '', []],
            'language tag before year' => ['Armand.German.2024.AC3.DVDRip.x264-LIM', 'Armand', '2024', []],
            'language word in title' => ['The.German.2008.1080p.BluRay.x264-GRP', 'The German', '2008', []],
            'language word in title, no year' => ['The.German.1080p.BluRay.x264-GRP', 'The German', '', []],
            'language word as title' => ['French.2024.1080p.BluRay.x264-GRP', 'French', '2024', []],
            'number in title' => ['Blade.Runner.2049.2017.1080p.BluRay.x264-SPARKS', 'Blade Runner 2049', '2017', []],
            'year as title' => ['1917.2019.1080p.BluRay.x264', '1917', '2019', []],
            'dash separated titles' => ['Starman - Uzayli Yildiz Adam (1984) 1080p NF WEBDL H264 [DUAL] DDP 51@TSRG', 'Starman - Uzayli Yildiz Adam', '1984', ['Starman', 'Uzayli Yildiz Adam']],
            'bracketed title' => ['Orumcek Adam Yepyeni Bir Gun (Spider Man Brand New Day) 2026 WEBDL 2160p X265 10Bit DDP 5.1 [TR-EN] TurkSeeD', 'Orumcek Adam Yepyeni Bir Gun', '2026', ['Spider Man Brand New Day']],
        ];
    }

    #[Test]
    public function it_does_not_keep_the_previous_releases_title_when_parsing(): void
    {
        $service = new MovieService;

        $this->assertTrue($this->invokeParse($service, 'Example.Movie.2024.1080p.BluRay.x264-GRP'));
        $this->assertFalse($this->invokeParse($service, 'Das.Strafgericht.S2025E975.German.576p.RTLP.WEB-DL.AAC2.0.H.264-GLOTZE'));
        $this->assertSame('', $this->getMovieServiceProperty($service, 'currentTitle'));
        $this->assertSame('', $this->getMovieServiceProperty($service, 'currentYear'));
    }

    #[Test]
    public function it_matches_local_titles_that_differ_only_in_punctuation(): void
    {
        Cache::flush();

        $service = new MovieService;
        $service->update(['imdbid' => '30460310', 'title' => 'Spider-Man: Brand New Day', 'year' => '2026']);

        $this->invokeParse($service, 'Spider-Man.Brand.New.Day.2026.2160p.iT.WEB-DL.DDP5.1.Atmos.H.265-Kitsune');

        $this->assertSame('30460310', $this->invokeLocalIMDBSearch($service));
    }

    #[Test]
    public function it_does_not_match_a_short_title_to_a_longer_similar_one(): void
    {
        Cache::flush();

        $service = new MovieService;
        $service->update(['imdbid' => '1234567', 'title' => 'Pig', 'year' => '1998']);

        $this->invokeParse($service, 'Pi.1998.UHD.BluRay.1080p.DD+Atmos.5.1.DoVi.HDR10+.x265-SM737');

        $this->assertFalse($this->invokeLocalIMDBSearch($service));
    }

    #[Test]
    public function it_matches_a_local_title_that_starts_a_german_release_title_from_the_same_year(): void
    {
        Cache::flush();

        $service = new MovieService;
        $service->update(['imdbid' => '0986263', 'title' => 'Surrogates', 'year' => '2009']);

        $this->invokeParse($service, 'Surrogates.Mein.zweites.Ich.2009.GERMAN.DL.1080p.WEB.H264.iNTERNAL-SunDry');

        $this->assertSame('0986263', $this->invokeLocalIMDBSearch($service));
    }

    #[Test]
    public function it_rejects_ambiguous_wrong_year_or_sequel_prefix_matches(): void
    {
        $service = new MovieService;
        $service->update(['imdbid' => '1298649', 'title' => 'The Watch', 'year' => '2012']);
        $service->update(['imdbid' => '18352618', 'title' => 'The Watch', 'year' => '2012']);
        $service->update(['imdbid' => '0986263', 'title' => 'Surrogates', 'year' => '2009']);
        $service->update(['imdbid' => '1691917', 'title' => 'Planes', 'year' => '2014']);

        $cases = [
            'The.Watch.Nachbarn.der.3.Art.2012.German.DL.1080p.BluRay.AVC-VEiL',
            'Surrogates.Mein.zweites.Ich.2010.GERMAN.DL.1080p.WEB.H264-SunDry',
            'Planes.2.Immer.im.Einsatz.2014.German.DL.1080p.BDRip.AV1',
        ];

        foreach ($cases as $searchName) {
            Cache::flush();
            $this->invokeParse($service, $searchName);
            $this->assertFalse($this->invokeLocalIMDBSearch($service), $searchName);
        }
    }

    #[Test]
    public function it_accepts_a_tmdb_movie_by_its_german_translation(): void
    {
        Cache::flush();
        $tmdb = $this->bindTmdbClient();
        $tmdb->shouldReceive('getMovie')->once()->andReturn($this->tmdbMovie());

        $service = new MovieService;
        $service->echooutput = false;
        $this->invokeParse($service, 'Der.Teufel.traegt.Prada.2006.OPEN.MATTE.German.AC3D.DL.1080p.AmazonHD.x265-FuN');

        $movie = $service->fetchTMDBProperties('0458352');

        $this->assertIsArray($movie);
        $this->assertSame('0458352', $movie['imdbid']);
    }

    #[Test]
    public function it_does_not_let_one_releases_tmdb_rejection_hide_the_movie_from_another(): void
    {
        Cache::flush();
        $tmdb = $this->bindTmdbClient();
        $tmdb->shouldReceive('getMovie')->once()->andReturn($this->tmdbMovie());

        $service = new MovieService;
        $service->echooutput = false;

        $this->invokeParse($service, 'Moana.2016.1080p.BluRay.x264-GRP');
        $this->assertFalse($service->fetchTMDBProperties('0458352'));

        $this->invokeParse($service, 'The.Devil.Wears.Prada.2006.1080p.BluRay.x264-GRP');
        $this->assertIsArray($service->fetchTMDBProperties('0458352'));
    }

    #[Test]
    public function it_retries_a_german_tmdb_search_with_umlauts_restored(): void
    {
        Cache::flush();
        $this->disableOtherMovieProviders();
        $tmdb = $this->bindTmdbClient();
        $tmdb->shouldReceive('searchMovies')->andReturnUsing(
            fn (string $query): array => $query === 'Der Teufel trägt Prada'
                ? ['total_results' => 1, 'results' => [['id' => 350, 'release_date' => '2006-06-29']]]
                : ['total_results' => 0, 'results' => []]
        );
        $tmdb->shouldReceive('getMovie')->with('350', Mockery::any())->once()->andReturn($this->tmdbMovie());
        Search::shouldReceive('updateRelease')->with(1);
        Search::shouldReceive('insertMovie');

        Release::query()->insert([
            'id' => 1,
            'searchname' => 'Der.Teufel.traegt.Prada.2006.German.DL.2160p.HDR.WEBRip.AV1',
            'categories_id' => 2010,
            'imdbid' => null,
        ]);

        $service = $this->makeServiceWithoutCovers();
        $service->processMovieReleases();

        $this->assertSame('0458352', Release::query()->whereKey(1)->value('imdbid'));
        $this->assertNotNull(Release::query()->whereKey(1)->value('movieinfo_id'));
        $this->assertSame('TMDB', $service->service);
    }

    #[Test]
    public function it_can_match_a_release_without_a_year_through_tmdb(): void
    {
        Cache::flush();
        $this->disableOtherMovieProviders();
        $tmdb = $this->bindTmdbClient();
        $tmdb->shouldReceive('searchMovies')->with('Planes')->once()
            ->andReturn(['total_results' => 1, 'results' => [['id' => 69735, 'release_date' => '2013-08-09']]]);
        $tmdb->shouldReceive('getMovie')->once()->andReturn([
            'id' => 69735,
            'imdb_id' => 'tt1691917',
            'title' => 'Planes',
            'original_title' => 'Planes',
            'release_date' => '2013-08-09',
        ]);
        Search::shouldReceive('updateRelease')->with(1);
        Search::shouldReceive('insertMovie');

        Release::query()->insert([
            'id' => 1,
            'searchname' => 'Planes.German.DL.1080p.BluRay.X264-EXQUiSiTE',
            'categories_id' => 2010,
            'imdbid' => null,
        ]);

        $this->makeServiceWithoutCovers()->processMovieReleases();

        $this->assertSame('1691917', Release::query()->whereKey(1)->value('imdbid'));
    }

    #[Test]
    public function it_skips_an_omdb_search_result_whose_title_does_not_match(): void
    {
        Cache::flush();

        $service = new class extends MovieService
        {
            public function updateMovieInfo(string $imdbId): bool
            {
                return true;
            }
        };
        $service->echooutput = false;
        $service->omdbapikey = 'test-key';
        $omdbApi = Mockery::mock(OMDbAPI::class);
        $service->omdbApi = $omdbApi;
        $omdbApi->shouldReceive('search')->andReturn((object) [
            'message' => 'OK',
            'data' => (object) [
                'Response' => 'True',
                'Search' => [
                    (object) ['Title' => 'Completely Different', 'Year' => '2024', 'imdbID' => 'tt1111111', 'Type' => 'movie'],
                    (object) ['Title' => 'Example Movie', 'Year' => '2024', 'imdbID' => 'tt0137523', 'Type' => 'movie'],
                ],
            ],
        ]);
        Search::shouldReceive('updateRelease')->with(7)->once();

        Release::query()->insert([
            'id' => 7,
            'searchname' => 'Example.Movie.2024.1080p.BluRay.x264-GRP',
            'categories_id' => 2040,
            'imdbid' => null,
        ]);
        $this->invokeParse($service, 'Example.Movie.2024.1080p.BluRay.x264-GRP');

        $method = new \ReflectionMethod($service, 'searchOMDbAPI');
        $this->assertTrue($method->invoke($service, 7));
        $this->assertSame('0137523', Release::query()->whereKey(7)->value('imdbid'));
    }

    #[Test]
    public function it_stores_decimal_ratings_keeps_created_at_and_links_waiting_releases(): void
    {
        Cache::flush();
        Search::shouldReceive('insertMovie');
        Search::shouldReceive('updateRelease')->with(8)->once();

        MovieInfo::query()->insert([
            'imdbid' => '0137523',
            'title' => 'Example Movie',
            'year' => '2024',
            'created_at' => '2020-01-01 00:00:00',
            'updated_at' => '2020-01-01 00:00:00',
        ]);
        Release::query()->insert([
            'id' => 8,
            'searchname' => 'Example.Movie.2024.1080p.BluRay.x264-GRP',
            'categories_id' => 2040,
            'imdbid' => '0137523',
            'movieinfo_id' => null,
        ]);

        $service = new class extends MovieService
        {
            public function fetchTMDBProperties(string $imdbId, bool $text = false): array|false
            {
                return ['title' => 'Example Movie', 'tmdbid' => 550, 'rating' => 7.8, 'year' => 2024, 'cover' => '', 'backdrop' => ''];
            }

            public function fetchIMDBProperties(string $imdbId): array|false
            {
                return false;
            }

            public function fetchTraktTVProperties(string $imdbId): array|false
            {
                return false;
            }

            public function fetchOmdbAPIProperties(string $imdbId): array|false
            {
                return false;
            }

            protected function fetchFanartTVProperties(string $imdbId): false|array
            {
                return false;
            }
        };
        $service->echooutput = false;

        $this->assertTrue($service->updateMovieInfo('0137523'));

        $movie = MovieInfo::query()->where('imdbid', '0137523')->firstOrFail();
        $this->assertSame('7.8', $movie->rating);
        $this->assertSame('2020-01-01 00:00:00', $movie->created_at?->format('Y-m-d H:i:s'));
        $this->assertSame($movie->id, (int) Release::query()->whereKey(8)->value('movieinfo_id'));
    }

    #[Test]
    public function it_does_not_overwrite_a_release_relinked_by_another_worker_while_linking(): void
    {
        Search::shouldReceive('updateRelease')->with(11)->once();

        Release::query()->insert([
            ['id' => 10, 'searchname' => 'Example.Movie.2024', 'categories_id' => 2040, 'imdbid' => '0137523', 'movieinfo_id' => null],
            ['id' => 11, 'searchname' => 'Example.Movie.2024', 'categories_id' => 2040, 'imdbid' => '0137523', 'movieinfo_id' => null],
        ]);

        // Another worker re-matches release 10 right after this worker selected it.
        $interfered = false;
        DB::listen(function (QueryExecuted $query) use (&$interfered): void {
            if (! $interfered && str_starts_with($query->sql, 'select "id" from "releases"')) {
                $interfered = true;
                Release::query()->whereKey(10)->update(['imdbid' => '9999999', 'movieinfo_id' => 999]);
            }
        });

        $method = new \ReflectionMethod(MovieService::class, 'linkReleasesToMovieInfo');
        $method->invoke(new MovieService, '0137523', 42);

        $this->assertTrue($interfered);
        $this->assertSame(999, (int) Release::query()->whereKey(10)->value('movieinfo_id'));
        $this->assertSame('9999999', Release::query()->whereKey(10)->value('imdbid'));
        $this->assertSame(42, (int) Release::query()->whereKey(11)->value('movieinfo_id'));
    }

    #[Test]
    public function it_passes_the_movie_lookup_mode_to_worker_commands(): void
    {
        $method = new \ReflectionMethod(PostProcessRunner::class, 'postProcessGuidCommand');
        $runner = new PostProcessRunner;

        $movieCommand = $method->invoke($runner, 'movie', (object) ['id' => 'a', 'renamed' => 2]);
        $nfoCommand = $method->invoke($runner, 'nfo', (object) ['id' => 'a']);

        $this->assertSame(['postprocess:guid', 'movie', 'a', '2'], array_slice($movieCommand, 2));
        $this->assertSame(['postprocess:guid', 'nfo', 'a'], array_slice($nfoCommand, 2));
    }

    #[Test]
    public function it_accepts_the_lookup_mode_as_a_cli_string(): void
    {
        Release::query()->insert([
            'id' => 9,
            'searchname' => 'Example.Movie.2024.1080p.BluRay.x264-GRP',
            'categories_id' => 2040,
            'imdbid' => null,
            'isrenamed' => false,
        ]);

        (new MoviesProcessor(false))->process('', '', '2');

        // Mode 2 only looks at renamed releases, so this one is left alone.
        $this->assertNull(Release::query()->whereKey(9)->value('imdbid'));
    }

    private function invokeParse(MovieService $service, string $searchName): bool
    {
        $method = new \ReflectionMethod($service, 'parseMovieSearchName');

        return $method->invoke($service, $searchName);
    }

    private function getMovieServiceProperty(MovieService $service, string $property): mixed
    {
        return (new \ReflectionProperty($service, $property))->getValue($service);
    }

    private function bindTmdbClient(): Mockery\MockInterface
    {
        $tmdb = Mockery::mock(TmdbClient::class);
        $tmdb->shouldReceive('isConfigured')->andReturn(true);
        $this->app->instance(TmdbClient::class, $tmdb);

        return $tmdb;
    }

    private function disableOtherMovieProviders(): void
    {
        config([
            'nntmux_api.omdb_api_key' => '',
            'nntmux_api.trakttv_api_key' => '',
            'nntmux_api.fanarttv_api_key' => '',
        ]);
        $this->app->instance(ImdbScraper::class, Mockery::mock(ImdbScraper::class, [
            'isScrapingEnabled' => false,
            'isEnabled' => false,
        ]));
    }

    /**
     * A MovieService that never downloads cover images.
     */
    private function makeServiceWithoutCovers(): MovieService
    {
        $service = new class extends MovieService
        {
            protected function hasCover(string $imdbId): bool
            {
                return true;
            }
        };
        $service->echooutput = false;

        return $service;
    }

    /**
     * @return array<string, mixed>
     */
    private function tmdbMovie(): array
    {
        return [
            'id' => 350,
            'imdb_id' => 'tt0458352',
            'title' => 'The Devil Wears Prada',
            'original_title' => 'The Devil Wears Prada',
            'release_date' => '2006-06-29',
            'vote_average' => 7.4,
            'alternative_titles' => ['titles' => [['title' => 'Devil Wears Prada']]],
            'translations' => ['translations' => [['iso_639_1' => 'de', 'data' => ['title' => 'Der Teufel trägt Prada']]]],
        ];
    }

    /**
     * @param  array<int, Response>  $responses
     */
    private function bindImdbScraper(array $responses): MockHandler
    {
        $mock = new MockHandler($responses);
        $this->app->instance(ImdbScraper::class, new ImdbScraper(new Client([
            'handler' => HandlerStack::create($mock),
            'http_errors' => false,
        ])));

        return $mock;
    }

    private function invokeLocalIMDBSearch(MovieService $service): string|false
    {
        $method = new \ReflectionMethod($service, 'localIMDBSearch');

        return $method->invoke($service);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function makeMovieServiceForTraktResponse(array $response): MovieService
    {
        $client = new class($response) extends TraktService
        {
            /**
             * @param  array<string, mixed>  $response
             */
            public function __construct(private array $response)
            {
                parent::__construct('test_trakt_key');
            }

            /**
             * @return array<string, mixed>|null
             */
            public function getMovieSummary(string $movie, string $extended = 'min'): ?array
            {
                return $this->response;
            }
        };

        $provider = new TraktProvider;
        $provider->client = $client;

        $service = new MovieService;
        $service->traktTv = $provider;
        $service->echooutput = false;

        $this->setMovieServiceProperty($service, 'traktcheck', 'test-key');

        return $service;
    }

    private function setMovieServiceProperty(MovieService $service, string $property, mixed $value): void
    {
        $reflectionProperty = new \ReflectionProperty($service, $property);
        $reflectionProperty->setValue($service, $value);
    }
}
