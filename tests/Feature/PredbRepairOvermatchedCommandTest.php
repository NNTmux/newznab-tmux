<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Facades\Search;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PredbRepairOvermatchedCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('releases');
        Schema::dropIfExists('predb');

        Schema::create('predb', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->string('filename')->default('');
            $table->string('source')->default('');
        });

        Schema::create('releases', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->default('');
            $table->string('searchname')->default('');
            $table->unsignedInteger('predb_id')->default(0);
            $table->smallInteger('isrenamed')->default(0);
            foreach (['proc_pp', 'proc_par2', 'proc_nfo', 'proc_files', 'proc_uid', 'proc_srr', 'proc_hash16k', 'proc_crc32', 'jpgstatus', 'videostatus'] as $column) {
                $table->smallInteger($column)->default(1);
            }
            foreach (['passwordstatus', 'haspreview', 'nfostatus'] as $column) {
                $table->smallInteger($column)->default(1);
            }
            $table->unsignedInteger('videos_id')->default(0);
            $table->integer('tv_episodes_id')->default(0);
            $table->string('imdbid')->nullable();
            $table->integer('movieinfo_id')->nullable();
            $table->integer('musicinfo_id')->nullable();
            $table->integer('consoleinfo_id')->nullable();
            $table->integer('gamesinfo_id')->default(0);
            $table->integer('bookinfo_id')->nullable();
            $table->integer('anidbid')->nullable();
            $table->integer('pp_timeout_count')->default(2);
            $table->timestamp('archive_retry_at')->nullable();
        });

        DB::table('predb')->insert([
            ['id' => 1, 'title' => 'Bad.Xrel.Title.2024.1080p.WEB.x264-GRP', 'filename' => 'movie.mkv', 'source' => 'xrel'],
            ['id' => 2, 'title' => 'Trusted.Title.2024.1080p.WEB.x264-GRP', 'filename' => 'trusted.mkv', 'source' => 'srrdb'],
            ['id' => 3, 'title' => 'Bad.P2P.Title.2024.1080p.WEB.x264-P2P', 'filename' => 'video.mkv', 'source' => 'xrelp2p'],
        ]);

        // PreDB 1 renamed three unrelated releases; one release really is that title.
        $this->linkRelease(10, 'Bad.Xrel.Title.2024.1080p.WEB.x264-GRP', 1);
        $this->linkRelease(11, 'Unrelated.One.2023.720p-AAA', 1);
        $this->linkRelease(12, 'Unrelated.Two.2022.1080p-BBB', 1);
        $this->linkRelease(13, 'Unrelated.Three.2021.2160p-CCC', 1);
        // xrel's P2P feed is untrusted by default as well.
        $this->linkRelease(30, 'Unrelated.Four.2020.1080p-DDD', 3);
        $this->linkRelease(31, 'Unrelated.Five.2019.1080p-EEE', 3);
        $this->linkRelease(32, 'Unrelated.Six.2018.1080p-FFF', 3);
        // PreDB 2 is just as over-linked but comes from a trusted source.
        foreach ([20, 21, 22, 23] as $id) {
            $this->linkRelease($id, 'Repost.'.$id, 2);
        }
    }

    public function test_dry_run_reports_without_changing_releases(): void
    {
        Search::shouldReceive('updateRelease')->never();

        $this->artisan('predb:repair-overmatched', ['--min-releases' => 2])
            ->expectsOutputToContain('Dry run: 6 release(s) would be reset.')
            ->assertExitCode(0);

        $this->assertSame(4, DB::table('releases')->where('predb_id', 1)->count());
    }

    public function test_execute_resets_releases_wrongly_named_by_an_untrusted_predb_row(): void
    {
        foreach ([11, 12, 13, 30, 31, 32] as $id) {
            Search::shouldReceive('updateRelease')->once()->with($id);
        }

        $this->artisan('predb:repair-overmatched', ['--min-releases' => 2, '--execute' => true])
            ->expectsOutputToContain('Reset 6 release(s).')
            ->assertExitCode(0);

        $reset = DB::table('releases')->where('id', 11)->first();
        $this->assertSame(0, (int) $reset->pp_timeout_count);
        $this->assertNull($reset->archive_retry_at);
        $this->assertSame(0, (int) $reset->predb_id);
        $this->assertSame(0, (int) $reset->isrenamed);
        $this->assertSame('Unrelated.One.2023.720p-AAA', $reset->searchname);
        $this->assertSame(0, (int) $reset->proc_files);
        $this->assertSame(-1, (int) $reset->nfostatus);

        $this->assertSame([10], DB::table('releases')->where('predb_id', 1)->pluck('id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame(0, DB::table('releases')->where('predb_id', 3)->count());
        $this->assertSame(4, DB::table('releases')->where('predb_id', 2)->count());
    }

    public function test_rows_at_or_below_the_threshold_are_left_alone(): void
    {
        Search::shouldReceive('updateRelease')->never();

        $this->artisan('predb:repair-overmatched', ['--min-releases' => 4, '--execute' => true])
            ->expectsOutputToContain('No PreDB rows from [xrel, xrelp2p] are linked to more than 4 releases.')
            ->assertExitCode(0);

        $this->assertSame(4, DB::table('releases')->where('predb_id', 1)->count());
    }

    private function linkRelease(int $id, string $name, int $predbId): void
    {
        DB::table('releases')->insert([
            'id' => $id,
            'name' => $name,
            'searchname' => DB::table('predb')->where('id', $predbId)->value('title'),
            'predb_id' => $predbId,
            'isrenamed' => 1,
        ]);
    }
}
