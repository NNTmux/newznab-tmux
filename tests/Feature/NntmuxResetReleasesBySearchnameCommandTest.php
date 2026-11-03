<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Facades\Search;
use App\Models\Release;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class NntmuxResetReleasesBySearchnameCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('releases');
        Schema::dropIfExists('settings');

        Schema::create('settings', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->text('value')->nullable();
        });

        Schema::create('releases', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->default('');
            $table->string('searchname')->default('');
            $table->integer('totalpart')->default(0);
            $table->unsignedInteger('groups_id')->default(0);
            $table->unsignedBigInteger('size')->default(0);
            $table->dateTime('postdate')->nullable();
            $table->dateTime('adddate')->nullable();
            $table->string('guid', 40)->unique();
            $table->char('leftguid', 1)->default('a');
            $table->string('fromname')->nullable();
            $table->integer('categories_id')->default(10);
            $table->unsignedInteger('videos_id')->default(0);
            $table->integer('tv_episodes_id')->default(0);
            $table->string('imdbid')->nullable();
            $table->integer('musicinfo_id')->nullable();
            $table->integer('consoleinfo_id')->nullable();
            $table->integer('gamesinfo_id')->default(0);
            $table->integer('bookinfo_id')->nullable();
            $table->integer('anidbid')->nullable();
            $table->integer('movieinfo_id')->nullable();
            $table->unsignedInteger('predb_id')->default(0);
            $table->unsignedInteger('grabs')->default(0);
            $table->integer('comments')->default(0);
            $table->smallInteger('passwordstatus')->default(-1);
            $table->integer('pp_timeout_count')->default(2);
            $table->timestamp('archive_retry_at')->nullable();
            $table->integer('rarinnerfilecount')->default(0);
            $table->smallInteger('haspreview')->default(0);
            $table->smallInteger('nfostatus')->default(0);
            $table->smallInteger('jpgstatus')->default(0);
            $table->smallInteger('videostatus')->default(0);
            $table->smallInteger('reqidstatus')->default(0);
            $table->smallInteger('nzbstatus')->default(0);
            $table->smallInteger('iscategorized')->default(0);
            $table->smallInteger('isrenamed')->default(0);
            $table->smallInteger('proc_pp')->default(0);
            $table->smallInteger('proc_par2')->default(0);
            $table->smallInteger('proc_nfo')->default(0);
            $table->smallInteger('proc_files')->default(0);
            $table->smallInteger('proc_uid')->default(0);
            $table->smallInteger('proc_srr')->default(0);
            $table->smallInteger('proc_hash16k')->default(0);
            $table->smallInteger('proc_crc32')->default(0);
            $table->binary('nzb_guid');
            $table->unsignedSmallInteger('source')->nullable();
        });
    }

    public function test_it_resets_matching_releases_and_restores_raw_names(): void
    {
        $targetSearchname = '1.vs.1.Suzu.Matsuoka.JAPANESE.XXX.720p.WEBRiP.MP4-MFPF';

        $matchingOne = $this->createRelease([
            'name' => 'Original.Raw.Name.One',
            'searchname' => $targetSearchname,
        ]);

        $matchingTwo = $this->createRelease([
            'name' => 'Original.Raw.Name.Two',
            'searchname' => $targetSearchname,
            'guid' => str_repeat('b', 40),
            'leftguid' => 'b',
        ]);

        $nonMatching = $this->createRelease([
            'name' => 'Keep.Me.As.Is',
            'searchname' => 'Different.Search.Name',
            'guid' => str_repeat('c', 40),
            'leftguid' => 'c',
        ]);

        Search::shouldReceive('updateRelease')->once()->with($matchingOne->id);
        Search::shouldReceive('updateRelease')->once()->with($matchingTwo->id);
        Search::shouldReceive('updateRelease')->never()->with($nonMatching->id);

        $this->artisan('nntmux:reset-releases-by-searchname', ['searchname' => $targetSearchname])
            ->assertExitCode(0);

        $matchingOne->refresh();
        $matchingTwo->refresh();
        $nonMatching->refresh();

        $this->assertReleaseWasReset($matchingOne, 'Original.Raw.Name.One');
        $this->assertReleaseWasReset($matchingTwo, 'Original.Raw.Name.Two');

        $this->assertSame('Different.Search.Name', $nonMatching->searchname);
        $this->assertSame(1, (int) $nonMatching->isrenamed);
        $this->assertSame(777, (int) $nonMatching->predb_id);
        $this->assertSame('tt7654321', $nonMatching->imdbid);
    }

    public function test_it_supports_dry_run_without_mutating_releases(): void
    {
        $targetSearchname = '1.vs.1.Suzu.Matsuoka.JAPANESE.XXX.720p.WEBRiP.MP4-MFPF';

        $release = $this->createRelease([
            'name' => 'Original.Raw.Name',
            'searchname' => $targetSearchname,
        ]);

        Search::shouldReceive('updateRelease')->never();

        $this->artisan('nntmux:reset-releases-by-searchname', [
            'searchname' => $targetSearchname,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $release->refresh();

        $this->assertSame($targetSearchname, $release->searchname);
        $this->assertSame(1, (int) $release->isrenamed);
        $this->assertSame(777, (int) $release->predb_id);
        $this->assertSame(1, (int) $release->proc_nfo);
        $this->assertSame('tt7654321', $release->imdbid);
    }

    public function test_it_reports_when_no_releases_match(): void
    {
        Search::shouldReceive('updateRelease')->never();

        $this->artisan('nntmux:reset-releases-by-searchname', [
            'searchname' => 'Missing.Search.Name',
        ])->expectsOutput('No releases found with searchname: Missing.Search.Name')
            ->assertExitCode(0);
    }

    private function createRelease(array $overrides = []): Release
    {
        $now = Carbon::parse('2026-03-11 12:00:00');
        $guid = $overrides['guid'] ?? Str::padLeft((string) Str::uuid(), 40, 'a');

        return Release::withoutEvents(fn (): Release => Release::query()->create(array_merge([
            'name' => 'Original.Raw.Name',
            'searchname' => 'Bad.Search.Name',
            'totalpart' => 1,
            'groups_id' => 1,
            'size' => 123456,
            'postdate' => $now,
            'adddate' => $now,
            'guid' => $guid,
            'leftguid' => $overrides['leftguid'] ?? substr($guid, 0, 1),
            'fromname' => 'poster@example.com',
            'categories_id' => 6070,
            'videos_id' => 120,
            'tv_episodes_id' => 340,
            'imdbid' => 'tt7654321',
            'musicinfo_id' => 12,
            'consoleinfo_id' => 13,
            'gamesinfo_id' => 14,
            'bookinfo_id' => 15,
            'anidbid' => 16,
            'movieinfo_id' => 17,
            'predb_id' => 777,
            'grabs' => 0,
            'comments' => 0,
            'passwordstatus' => 1,
            'rarinnerfilecount' => 0,
            'haspreview' => 1,
            'nfostatus' => 1,
            'jpgstatus' => 1,
            'videostatus' => 1,
            'reqidstatus' => 0,
            'nzbstatus' => 1,
            'iscategorized' => 1,
            'isrenamed' => 1,
            'proc_pp' => 1,
            'proc_par2' => 1,
            'proc_nfo' => 1,
            'proc_files' => 1,
            'proc_uid' => 1,
            'proc_srr' => 1,
            'proc_hash16k' => 1,
            'proc_crc32' => 1,
            'nzb_guid' => 'test-nzb-guid',
        ], $overrides)));
    }

    private function assertReleaseWasReset(Release $release, string $expectedSearchname): void
    {
        $this->assertSame($expectedSearchname, $release->searchname);
        $this->assertSame(0, (int) $release->isrenamed);
        $this->assertSame(0, (int) $release->predb_id);
        $this->assertSame(0, (int) $release->videos_id);
        $this->assertSame(0, (int) $release->tv_episodes_id);
        $this->assertNull($release->imdbid);
        $this->assertNull($release->movieinfo_id);
        $this->assertNull($release->musicinfo_id);
        $this->assertNull($release->consoleinfo_id);
        $this->assertSame(0, (int) $release->gamesinfo_id);
        $this->assertNull($release->bookinfo_id);
        $this->assertNull($release->anidbid);
        $this->assertSame(-1, (int) $release->passwordstatus);
        $this->assertSame(-1, (int) $release->haspreview);
        $this->assertSame(0, (int) $release->pp_timeout_count);
        $this->assertNull($release->archive_retry_at);
        $this->assertSame(0, (int) $release->jpgstatus);
        $this->assertSame(0, (int) $release->videostatus);
        $this->assertSame(-1, (int) $release->nfostatus);
        $this->assertSame(0, (int) $release->proc_pp);
        $this->assertSame(0, (int) $release->proc_par2);
        $this->assertSame(0, (int) $release->proc_nfo);
        $this->assertSame(0, (int) $release->proc_files);
        $this->assertSame(0, (int) $release->proc_uid);
        $this->assertSame(0, (int) $release->proc_srr);
        $this->assertSame(0, (int) $release->proc_hash16k);
        $this->assertSame(0, (int) $release->proc_crc32);
    }
}
