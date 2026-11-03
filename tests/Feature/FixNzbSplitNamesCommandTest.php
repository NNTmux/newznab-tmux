<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Facades\Search;
use App\Models\Category;
use App\Services\Nzb\NzbService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Tests\TestCase;

class FixNzbSplitNamesCommandTest extends TestCase
{
    private const string FIFA_SUBJECT = '[01/99] "Fifa__NZBSPLIT__aaf4ad7d833ad6d98b87b192a0cc6c7a__NZBSPLIT__FIFA_World_Cup_46th_match_Group_L_England_vs_Ghana+Postgame_HDTV_1080iGRE25fps_ERT_HD.7z.001" yEnc';

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', DB::connection()->getDriverName());

        Schema::create('usenet_groups', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
        });
        DB::table('usenet_groups')->insert(['id' => 1, 'name' => 'alt.binaries.misc']);

        Schema::create('releases', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('guid')->default('');
            $table->string('name');
            $table->string('searchname');
            $table->string('fromname')->default('progr@mmer.org');
            $table->integer('groups_id')->default(1);
            $table->integer('categories_id');
            $table->integer('predb_id')->default(0);
            $table->integer('isrenamed')->default(1);
            $table->integer('iscategorized')->default(1);
            $table->integer('totalpart')->default(99);
            $table->unsignedBigInteger('size')->default(7_000_000_000);
            $table->integer('grabs')->default(0);
            $table->string('imdbid')->nullable();
            $table->integer('movieinfo_id')->nullable();
            $table->integer('musicinfo_id')->nullable();
            $table->integer('bookinfo_id')->nullable();
            $table->integer('consoleinfo_id')->nullable();
            $table->integer('gamesinfo_id')->default(0);
            $table->integer('videos_id')->default(0);
            $table->integer('tv_episodes_id')->default(0);
            $table->integer('tv_lookup_attempts')->default(0);
            $table->dateTime('tv_lookup_retry_at')->nullable();
            $table->integer('book_lookup_attempts')->default(0);
            $table->dateTime('book_lookup_retry_at')->nullable();
        });

        Schema::create('release_files', function (Blueprint $table): void {
            $table->integer('releases_id');
            $table->string('name');
        });
    }

    public function test_purge_dry_run_reports_without_deleting(): void
    {
        $this->insertRelease(['name' => $this->subject('[01/98]', 'MLB_RS_2026_Detroit_Tigers_vs_Chicago_White_Sox_20_09_720pEN60fps_DSN'), 'totalpart' => 96]);
        Search::shouldReceive('deleteReleases')->never();

        $this->artisan('releases:fix-nzbsplit', ['--purge' => true, '--dry-run' => true])->assertSuccessful();

        $this->assertSame(1, DB::table('releases')->count());
    }

    public function test_purge_deletes_incomplete_sample_and_orphan_sets_and_keeps_complete_ones(): void
    {
        $incomplete = $this->insertRelease(['name' => $this->subject('[01/98]', 'MLB_RS_2026_Detroit_Tigers_vs_Chicago_White_Sox_20_09_720pEN60fps_DSN'), 'totalpart' => 96]);
        $sample = $this->insertRelease(['name' => $this->subject('[1/2]', 'match.of.the.day.2026.05.17.1080p.hdtv.h264-ftp.sample'), 'totalpart' => 2]);
        $orphan = $this->insertRelease(['name' => $this->subject('[80/98]', 'MLB_RS_2026_Kansas_City_Royals_vs_Colorado_Rockies_31_07_720pEN60fps_COLR'), 'totalpart' => 2]);
        $complete = $this->insertRelease(['name' => $this->subject('[01/98]', 'NBA_20260422_ORL__DET_1080p60_ESPN'), 'totalpart' => 98]);
        $magazine = $this->insertRelease(['name' => $this->subject('[2/2]', 'Athlon_Sports_2026_NFL'), 'totalpart' => 2]);
        $ordinary = $this->insertRelease(['name' => '[01/20] - "Some.Release.part01.rar" yEnc', 'totalpart' => 5]);

        $this->mock(NzbService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('deleteNzb')->times(3);
        });
        Search::shouldReceive('deleteReleases')->once()->with([$incomplete, $sample, $orphan]);

        $this->artisan('releases:fix-nzbsplit', ['--purge' => true])->assertSuccessful();

        $this->assertSame([$complete, $magazine, $ordinary], DB::table('releases')->orderBy('id')->pluck('id')->all());
    }

    public function test_repair_fixes_name_category_and_movie_links_but_keeps_raw_name(): void
    {
        $id = $this->insertRelease([
            'name' => self::FIFA_SUBJECT,
            'searchname' => 'FIFA.World.Cup.46th.match.Group.L.England.vs.Ghana Postgame.HDTV.1080iGRE25fps.ERT.HD',
            'categories_id' => Category::MOVIE_HD,
            'imdbid' => '32915471',
            'movieinfo_id' => 1479331,
        ]);
        DB::table('release_files')->insert(['releases_id' => $id, 'name' => 'Fifa__NZBSPLIT__aaf4ad7d833ad6d98b87b192a0cc6c7a__NZBSPLIT__FIFA_World_Cup_46th_match.mkv']);
        Search::shouldReceive('updateRelease')->once()->with($id);

        $this->artisan('releases:fix-nzbsplit', ['--repair' => true])->assertSuccessful();

        $release = DB::table('releases')->find($id);
        $this->assertSame('FIFA.World.Cup.46th.match.Group.L.England.vs.Ghana.Postgame.HDTV.1080iGRE25fps.ERT.HD', $release->searchname);
        $this->assertSame(self::FIFA_SUBJECT, $release->name);
        $this->assertSame(Category::TV_SPORT, (int) $release->categories_id);
        $this->assertNull($release->imdbid);
        $this->assertNull($release->movieinfo_id);
    }

    public function test_repair_dry_run_writes_nothing(): void
    {
        $id = $this->insertRelease(['name' => self::FIFA_SUBJECT, 'searchname' => 'FIFA.World.Cup.46th.match', 'categories_id' => Category::MOVIE_HD]);
        $before = DB::table('releases')->find($id);
        Search::shouldReceive('updateRelease')->never();

        $this->artisan('releases:fix-nzbsplit', ['--repair' => true, '--dry-run' => true])->assertSuccessful();

        $this->assertEquals($before, DB::table('releases')->find($id));
    }

    public function test_repair_keeps_predb_title_and_still_moves_magazine_to_books(): void
    {
        $id = $this->insertRelease([
            'name' => $this->subject('[1/4]', 'World.Soccer.June.2026'),
            'searchname' => 'World.Soccer.June.2026.HYBRID.MAGAZINE.eBook-21A1',
            'categories_id' => Category::TV_SPORT,
            'predb_id' => 12960198,
            'totalpart' => 4,
        ]);
        DB::table('release_files')->insert(['releases_id' => $id, 'name' => 'Fifa__NZBSPLIT__aaf4ad7d833ad6d98b87b192a0cc6c7a__NZBSPLIT__World.Soccer.June.2026.pdf']);
        Search::shouldReceive('updateRelease')->once()->with($id);

        $this->artisan('releases:fix-nzbsplit', ['--repair' => true])->assertSuccessful();

        $release = DB::table('releases')->find($id);
        $this->assertSame('World.Soccer.June.2026.HYBRID.MAGAZINE.eBook-21A1', $release->searchname);
        $this->assertSame(12960198, (int) $release->predb_id);
        $this->assertGreaterThanOrEqual(Category::BOOKS_ROOT, (int) $release->categories_id);
        $this->assertLessThan(Category::BOOKS_ROOT + 1000, (int) $release->categories_id);
    }

    public function test_repair_keeps_diverged_name_unless_reset_is_requested(): void
    {
        $id = $this->insertRelease([
            'name' => $this->subject('[25/45]', '2023_World_Series_Film_Texas_Rangers__Arizona_Diamondbacks'),
            'searchname' => 'Some Other Name Entirely',
            'categories_id' => Category::OTHER_MISC,
            'totalpart' => 45,
        ]);
        Search::shouldReceive('updateRelease')->twice()->with($id);

        $this->artisan('releases:fix-nzbsplit', ['--repair' => true])->assertSuccessful();
        $this->assertSame('Some Other Name Entirely', DB::table('releases')->where('id', $id)->value('searchname'));
        $this->assertSame(Category::TV_SPORT, (int) DB::table('releases')->where('id', $id)->value('categories_id'));

        $this->artisan('releases:fix-nzbsplit', ['--repair' => true, '--reset-diverged' => true])->assertSuccessful();
        $this->assertSame('2023.World.Series.Film.Texas.Rangers.Arizona.Diamondbacks', DB::table('releases')->where('id', $id)->value('searchname'));
    }

    public function test_default_mode_only_repairs_unrenamed_releases(): void
    {
        $renamed = $this->insertRelease(['name' => self::FIFA_SUBJECT, 'searchname' => 'FIFA.World.Cup.46th.match', 'categories_id' => Category::MOVIE_HD, 'isrenamed' => 1]);
        $unrenamed = $this->insertRelease(['name' => self::FIFA_SUBJECT, 'searchname' => self::FIFA_SUBJECT, 'categories_id' => Category::OTHER_MISC, 'isrenamed' => 0]);
        Search::shouldReceive('updateRelease')->once()->with($unrenamed);

        $this->artisan('releases:fix-nzbsplit')->assertSuccessful();

        $this->assertSame(Category::MOVIE_HD, (int) DB::table('releases')->where('id', $renamed)->value('categories_id'));
        $this->assertSame(1, (int) DB::table('releases')->where('id', $unrenamed)->value('isrenamed'));
        $this->assertSame(Category::TV_SPORT, (int) DB::table('releases')->where('id', $unrenamed)->value('categories_id'));
    }

    private function subject(string $counter, string $title): string
    {
        return $counter.' "Fifa__NZBSPLIT__aaf4ad7d833ad6d98b87b192a0cc6c7a__NZBSPLIT__'.$title.'.7z.001" yEnc';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insertRelease(array $attributes): int
    {
        $id = (int) DB::table('releases')->max('id') + 1;

        return DB::table('releases')->insertGetId([
            'guid' => sprintf('%08d-0000-0000-0000-000000000000', $id),
            'searchname' => 'unused',
            'categories_id' => Category::TV_SPORT,
            ...$attributes,
        ]);
    }
}
