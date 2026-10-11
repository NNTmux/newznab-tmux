<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Facades\Search;
use App\Services\NameFixing\ReleaseUpdateService;
use App\Services\ReleaseCleaningService;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `[01/10] - "file.ext" yEnc` subjects that no group regular expression matches must still yield a clean
 * release name. Subjects are real ones from alt.binaries.multimedia.rail and similar groups.
 */
class CounterSubjectNamingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge();
        DB::reconnect();
        $pdo = DB::connection()->getPdo();
        if ($pdo instanceof \PDO && method_exists($pdo, 'sqliteCreateFunction')) {
            // `subject REGEXP pattern` calls regexp(pattern, subject) in SQLite.
            $pdo->sqliteCreateFunction('REGEXP', static fn (?string $pattern, ?string $value): int => preg_match('/'.$pattern.'/i', (string) $value));
        }
        DB::statement('CREATE TABLE release_naming_regexes (id INTEGER PRIMARY KEY, group_regex VARCHAR(255), regex VARCHAR(255), status INTEGER DEFAULT 1, ordinal INTEGER DEFAULT 0)');
        DB::statement('CREATE TABLE predb (id INTEGER PRIMARY KEY, title VARCHAR(255), filename VARCHAR(255))');
        DB::statement('CREATE TABLE usenet_groups (id INTEGER PRIMARY KEY, name VARCHAR(255))');
        DB::statement('CREATE TABLE releases (id INTEGER PRIMARY KEY, name VARCHAR(255), searchname VARCHAR(255), fromname VARCHAR(255), groups_id INTEGER, categories_id INTEGER, isrenamed INTEGER DEFAULT 0)');
        DB::table('usenet_groups')->insert(['id' => 1, 'name' => 'alt.binaries.multimedia.rail']);
        Search::shouldReceive('matchPredbExact')->andReturn(null)->byDefault();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function subjects(): iterable
    {
        yield 'par2' => ['[1/9] - "Shes.the.Man.2006.BluRay.1080p.DTS-HD.MA.5.1.AVC.REMUX-FraMeSToR.par2" yEnc', 'Shes.the.Man.2006.BluRay.1080p.DTS-HD.MA.5.1.AVC.REMUX-FraMeSToR'];
        yield 'hyphenated par2 volume' => ['[3/9] - "Shes.the.Man.2006.BluRay.1080p.DTS-HD.MA.5.1.AVC.REMUX-FraMeSToR.vol01-03.par2" yEnc', 'Shes.the.Man.2006.BluRay.1080p.DTS-HD.MA.5.1.AVC.REMUX-FraMeSToR'];
        yield 'mkv' => ['[09/10] - "Dark.Phoenix.2019.UHD.BluRay.2160p.TrueHD.Atmos.7.1.HEVC.REMUX-FraMeSToR.mkv" yEnc', 'Dark.Phoenix.2019.UHD.BluRay.2160p.TrueHD.Atmos.7.1.HEVC.REMUX-FraMeSToR'];
        yield 'rar part with segment counter' => ['[05/40] - "Some.Show.S01E02.1080p.WEB.h264-GRP.part04.rar" yEnc (1/50)', 'Some.Show.S01E02.1080p.WEB.h264-GRP'];
        yield 'split 7z' => ['[2/9] - "Some.Movie.2020.1080p.BluRay.x264-GRP.7z.003" yEnc', 'Some.Movie.2020.1080p.BluRay.x264-GRP'];
        yield 'par2 of a rar' => ['[02/10] - "Some.App.v6.41.Multilingual-GRP.rar.par2" yEnc', 'Some.App.v6.41.Multilingual-GRP'];
        yield 'tar.zst' => ['[1/6] - "Some.Show.S01E01.1080p.WEB.h264-GRP.tar.zst" yEnc', 'Some.Show.S01E01.1080p.WEB.h264-GRP'];
        yield 'rar recovery volume' => ['[3/9] - "Movie.2020.1080p-GRP.001.rev" yEnc', 'Movie.2020.1080p-GRP'];
        yield 'digitless part par2' => ['[1/2] - "Movie.2020.1080p-GRP.part.par2" yEnc', 'Movie.2020.1080p-GRP'];
        yield 'encoded quotes' => ['[1/2] - #34;Movie.2020.1080p-GRP.mkv#34; yEnc', 'Movie.2020.1080p-GRP'];
        yield 'path in the file name' => ['[1/2] - "C:\\posts\\Movie.2020.1080p-GRP.mkv" yEnc', 'Movie.2020.1080p-GRP'];
        yield 'title ending in a codec token' => ['[1/5] - "Movie.2020.1080p.DTS.mkv" yEnc', 'Movie.2020.1080p.DTS'];
        yield 'codec token before a rar' => ['[1/2] - "Movie.2020.1080p.DTS.rar" yEnc', 'Movie.2020.1080p.DTS'];
        yield 'm2v' => ['[1/2] - "Movie.2020.1080p-GRP.m2v" yEnc', 'Movie.2020.1080p-GRP'];
        yield 'ifo' => ['[1/2] - "Movie.2020.1080p-GRP.ifo" yEnc', 'Movie.2020.1080p-GRP'];
        yield 'flac token before a rar' => ['[1/2] - "Movie.2020.1080p.FLAC.rar" yEnc', 'Movie.2020.1080p.FLAC'];
        yield 'audiobook' => ['[1/2] - "Author.Book.2020-GRP.m4b" yEnc', 'Author.Book.2020-GRP'];
        yield 'document' => ['[1/2] - "Book.Title-GRP.docx" yEnc', 'Book.Title-GRP'];
        yield 'vob' => ['[1/5] - "Movie.2020.1080p-GRP.vob" yEnc', 'Movie.2020.1080p-GRP'];
        yield 'accented name' => ['[1/5] - "Amélie.2001.1080p.BluRay-GRP.mkv" yEnc', 'Amélie.2001.1080p.BluRay-GRP'];
        yield 'spaced p2p name' => ['[01/11] - "Love Island US S08E07 720p AMZN WEB-DL DDP2 0 H 264-RAWR.mkv" yEnc', 'Love Island US S08E07 720p AMZN WEB-DL DDP2 0 H 264-RAWR'];
    }

    #[Test]
    #[DataProvider('subjects')]
    public function counter_and_file_subjects_get_the_file_based_release_name(string $subject, string $expected): void
    {
        $meta = (new ReleaseCleaningService)->releaseCleaner($subject, 'poster@example.com', 'alt.binaries.multimedia.rail');

        $this->assertSame($expected, $meta['cleansubject']);
    }

    #[Test]
    public function teevee_subjects_no_group_regex_matches_get_the_file_based_release_name(): void
    {
        $meta = (new ReleaseCleaningService)->releaseCleaner('[01/11] - "Love Island US S08E07 720p AMZN WEB-DL DDP2 0 H 264-RAWR.mkv" yEnc', 'x@y.z', 'alt.binaries.teevee');

        $this->assertSame('Love Island US S08E07 720p AMZN WEB-DL DDP2 0 H 264-RAWR', $meta['cleansubject']);
    }

    #[Test]
    public function hashed_file_names_keep_the_subject_for_the_name_fixing_passes(): void
    {
        $meta = (new ReleaseCleaningService)->releaseCleaner('[1/7] - "a3f9c1d8e2b7a6f50d4c3b2a1908f7e6.par2" yEnc', 'x@y.z', 'alt.binaries.multimedia.rail');

        $this->assertSame('[1/7] - "a3f9c1d8e2b7a6f50d4c3b2a1908f7e6.par2"', $meta['cleansubject']);
    }

    #[Test]
    public function a_bare_name_left_after_stripping_stacked_extensions_keeps_the_subject(): void
    {
        // Without `.rar` faking a group suffix, a name with no group, year or quality tag is not plausible.
        $meta = (new ReleaseCleaningService)->releaseCleaner('[2/5] - "WinRAR 7.23 (x64) Final.rar.par2" yEnc', 'x@y.z', 'alt.binaries.multimedia.rail');

        $this->assertSame('[2/5] - "WinRAR 7.23 (x64) Final.rar.par2"', $meta['cleansubject']);
    }

    #[Test]
    public function rot13_scrambled_subjects_keep_the_subject(): void
    {
        foreach (['[82/84] - "26992-D-K-KIvQ-D.iby582+88.CNE7" lRap', '[82/84] - "26992-D-K-KIvQ-D.iby582+88.CNE7" lRap (1/2)', '[1/2] - "Gur.Zngevk.1999.1080c-OEC" - lRap', '[1/2] - "Gur.Zngevk.1999.1080c-OEC" - 1.23 TO - lRap'] as $subject) {
            $meta = (new ReleaseCleaningService)->releaseCleaner($subject, 'x@y.z', 'alt.binaries.multimedia.rail');

            $this->assertStringStartsWith((string) preg_replace('/"\s*(?:-\s*)?lRap.*$/', '"', $subject), $meta['cleansubject']);
        }
    }

    #[Test]
    public function a_dotted_file_name_without_any_release_signal_keeps_the_subject(): void
    {
        $meta = (new ReleaseCleaningService)->releaseCleaner('[1/2] - "Annual.Report.Final.pdf" yEnc', 'x@y.z', 'alt.binaries.multimedia.rail');

        $this->assertStringStartsWith('[1/2] - "Annual.Report.Final.pdf"', $meta['cleansubject']);
    }

    #[Test]
    public function the_fixer_cleaner_strips_hyphenated_par2_volumes(): void
    {
        $this->assertSame('Some.Movie-GRP', (new ReleaseCleaningService)->fixerCleaner('Some.Movie-GRP.vol01-03.par2'));
        $this->assertSame('Some.Movie-GRP', (new ReleaseCleaningService)->fixerCleaner('Some.Movie-GRP.vol00+01.par2'));
    }

    #[Test]
    public function the_backlog_command_renames_raw_subject_releases_through_the_name_fixer(): void
    {
        DB::table('releases')->insert([
            ['id' => 1, 'name' => '[1/9] - "Shes.the.Man.2006.BluRay.1080p.DTS-HD.MA.5.1.AVC.REMUX-FraMeSToR.par2" yEnc', 'searchname' => '[1/9] - "Shes.the.Man.2006.BluRay.1080p.DTS-HD.MA.5.1.AVC.REMUX-FraMeSToR.par2"', 'fromname' => 'a@b.c', 'groups_id' => 1, 'categories_id' => 2040, 'isrenamed' => 0],
            ['id' => 2, 'name' => '[1/7] - "a3f9c1d8e2b7a6f50d4c3b2a1908f7e6.par2" yEnc', 'searchname' => '[1/7] - "a3f9c1d8e2b7a6f50d4c3b2a1908f7e6.par2"', 'fromname' => 'a@b.c', 'groups_id' => 1, 'categories_id' => 10, 'isrenamed' => 0],
            ['id' => 3, 'name' => '[1/2] - "Already.Fixed.2020.1080p.WEB.h264-GRP.mkv" yEnc', 'searchname' => '[1/2] - "Already.Fixed.2020.1080p.WEB.h264-GRP.mkv"', 'fromname' => 'a@b.c', 'groups_id' => 1, 'categories_id' => 10, 'isrenamed' => 1],
            ['id' => 4, 'name' => '[2/9] - "Dark.Phoenix.2019.UHD.BluRay.2160p.TrueHD.Atmos.7.1.HEVC.REMUX-FraMeSToR.vol00-01.par2" yEnc', 'searchname' => '[2/9] - "Dark.Phoenix.2019.UHD.BluRay.2160p.TrueHD.Atmos.7.1.HEVC.REMUX-FraMeSToR.vol00-01.par2"', 'fromname' => 'a@b.c', 'groups_id' => 1, 'categories_id' => 2045, 'isrenamed' => 0],
        ]);
        DB::table('predb')->insert(['id' => 77, 'title' => 'Dark.Phoenix.2019.UHD.BluRay.2160p.TrueHD.Atmos.7.1.HEVC.REMUX-FraMeSToR', 'filename' => '']);
        Search::shouldReceive('matchPredbExact')->with('Dark.Phoenix.2019.UHD.BluRay.2160p.TrueHD.Atmos.7.1.HEVC.REMUX-FraMeSToR')->andReturn(['id' => 77]);

        $calls = [];
        $updater = Mockery::mock(ReleaseUpdateService::class);
        $updater->shouldReceive('deferSearchSync')->andReturnUsing(static fn (int $id, \Closure $work) => $work());
        $updater->shouldReceive('updateRelease')->andReturnUsing(function (object $release, string $name, string $method, bool $echo, string $type, bool $nameStatus, bool $show, int $preId) use (&$calls, $updater): void {
            $calls[] = [(int) $release->id, $name, $preId, $nameStatus, $type];
            $updater->fixed++;
        });
        $this->app->instance(ReleaseUpdateService::class, $updater);

        $this->artisan('releases:clean-subject-names', ['--chunk' => 2])
            ->expectsOutputToContain('Renamed 2 of 3 releases (1 linked to PreDB).')
            ->assertSuccessful();

        // The hashed release is skipped, and the already-renamed one is never read.
        $this->assertSame([
            [1, 'Shes.the.Man.2006.BluRay.1080p.DTS-HD.MA.5.1.AVC.REMUX-FraMeSToR', 0, false, 'Subject, '],
            // The PreDB type is the one that sets `isrenamed`.
            [4, 'Dark.Phoenix.2019.UHD.BluRay.2160p.TrueHD.Atmos.7.1.HEVC.REMUX-FraMeSToR', 77, true, 'PreDB FT Exact, '],
        ], $calls);
    }

    #[Test]
    public function the_backlog_command_cleans_the_search_name_and_skips_rows_changed_or_accented(): void
    {
        DB::table('releases')->insert([
            // `name` lost the `%` when the release was created; the search name kept it.
            ['id' => 1, 'name' => '[1/5] - "100.Wolf.2020.1080p-GRP.mkv" yEnc', 'searchname' => '[1/5] "100%.Wolf.2020.1080p-GRP.mkv"', 'fromname' => 'a@b.c', 'groups_id' => 1, 'categories_id' => 10, 'isrenamed' => 0],
            ['id' => 2, 'name' => '[1/5] - "Amlie.2001.1080p.BluRay-GRP.mkv" yEnc', 'searchname' => '[1/5] - "Amélie.2001.1080p.BluRay-GRP.mkv"', 'fromname' => 'a@b.c', 'groups_id' => 1, 'categories_id' => 10, 'isrenamed' => 0],
            ['id' => 3, 'name' => '[1/5] - "Other.Show.S01E01.1080p.WEB-GRP.mkv" yEnc', 'searchname' => '[1/5] - "Other.Show.S01E01.1080p.WEB-GRP.mkv"', 'fromname' => 'a@b.c', 'groups_id' => 1, 'categories_id' => 10, 'isrenamed' => 0],
        ]);

        $calls = [];
        $updater = Mockery::mock(ReleaseUpdateService::class);
        $updater->shouldReceive('deferSearchSync')->andReturnUsing(static fn (int $id, \Closure $work) => $work());
        $updater->shouldReceive('updateRelease')->andReturnUsing(function (object $release, string $name) use (&$calls, $updater): void {
            $calls[] = [(int) $release->id, $name];
            $updater->fixed++;
            // A name-fixing worker renames release 3 while this chunk is being processed.
            DB::table('releases')->where('id', 3)->update(['searchname' => 'Other.Show.S01E01.1080p.WEB-GRP', 'isrenamed' => 1]);
        });
        $this->app->instance(ReleaseUpdateService::class, $updater);

        $this->artisan('releases:clean-subject-names', ['--chunk' => 10])
            ->expectsOutputToContain('Renamed 1 of 3 releases (0 linked to PreDB).')
            ->assertSuccessful();

        $this->assertSame([[1, '100%.Wolf.2020.1080p-GRP']], $calls);
    }

    #[Test]
    public function the_backlog_command_skips_a_pre_title_with_accents(): void
    {
        DB::table('releases')->insert(['id' => 1, 'name' => '[1/5] - "Amelie.2001.1080p-GRP.mkv" yEnc', 'searchname' => '[1/5] - "Amelie.2001.1080p-GRP.mkv"', 'fromname' => 'a@b.c', 'groups_id' => 1, 'categories_id' => 10, 'isrenamed' => 0]);
        DB::table('predb')->insert(['id' => 5, 'title' => 'Amélie.2001.1080p-GRP', 'filename' => 'Amelie.2001.1080p-GRP']);
        Search::shouldReceive('matchPredbExact')->with('Amelie.2001.1080p-GRP')->andReturn(['id' => 5]);

        $updater = Mockery::mock(ReleaseUpdateService::class);
        $updater->shouldNotReceive('updateRelease');
        $this->app->instance(ReleaseUpdateService::class, $updater);

        $this->artisan('releases:clean-subject-names')
            ->expectsOutputToContain('Renamed 0 of 1 releases')
            ->assertSuccessful();
    }

    #[Test]
    public function a_dry_run_writes_nothing(): void
    {
        DB::table('releases')->insert(['id' => 1, 'name' => '[1/9] - "Shes.the.Man.2006.BluRay.1080p.DTS-HD.MA.5.1.AVC.REMUX-FraMeSToR.par2" yEnc', 'searchname' => '[1/9] - "Shes.the.Man.2006.BluRay.1080p.DTS-HD.MA.5.1.AVC.REMUX-FraMeSToR.par2"', 'fromname' => 'a@b.c', 'groups_id' => 1, 'categories_id' => 2040, 'isrenamed' => 0]);
        $updater = Mockery::mock(ReleaseUpdateService::class);
        $updater->shouldReceive('deferSearchSync')->andReturnUsing(static fn (int $id, \Closure $work) => $work());
        $updater->shouldNotReceive('updateRelease');
        $this->app->instance(ReleaseUpdateService::class, $updater);

        $this->artisan('releases:clean-subject-names', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run: would rename 1 of 1 releases')
            ->assertSuccessful();
    }
}
