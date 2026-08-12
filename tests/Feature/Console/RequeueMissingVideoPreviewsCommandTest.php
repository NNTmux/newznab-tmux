<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Category;
use App\Models\Release;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RequeueMissingVideoPreviewsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge();
        DB::reconnect();

        Schema::create('releases', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('searchname');
            $table->string('fromname');
            $table->dateTime('postdate');
            $table->dateTime('adddate');
            $table->string('guid');
            $table->unsignedInteger('categories_id');
            $table->integer('nzbstatus');
            $table->integer('haspreview');
            $table->integer('passwordstatus');
            $table->integer('rarinnerfilecount');
            $table->integer('isrenamed');
        });
    }

    public function test_it_dry_runs_then_requeues_only_stuck_non_rar_video_releases_idempotently(): void
    {
        Release::withoutEvents(function (): void {
            Release::factory()->create($this->release(1, Category::TV_WEBDL));
            Release::factory()->create($this->release(2, Category::MOVIE_HD));
            Release::factory()->create($this->release(3, Category::TV_HD, hasPreview: 1));
            Release::factory()->create($this->release(4, Category::MOVIE_WEBDL, rarInnerFileCount: 2));
            Release::factory()->create($this->release(5, Category::TV_UHD, hasPreview: -1, passwordStatus: -1));
            Release::factory()->create($this->release(6, Category::MUSIC_MP3));
            Release::factory()->create($this->release(7, Category::TV_SD, passwordStatus: -1));
        });

        $this->artisan('releases:requeue-missing-video-previews', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run: 2 releases would be re-queued.')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('releases')->where('id', 1)->value('haspreview'));
        $this->assertSame(0, DB::table('releases')->where('id', 1)->value('passwordstatus'));

        $this->artisan('releases:requeue-missing-video-previews', ['--apply' => true])
            ->expectsOutputToContain('Re-queued 2 releases.')
            ->assertSuccessful();

        $this->assertSame(-1, DB::table('releases')->where('id', 1)->value('haspreview'));
        $this->assertSame(-1, DB::table('releases')->where('id', 1)->value('passwordstatus'));
        $this->assertSame(-1, DB::table('releases')->where('id', 2)->value('haspreview'));
        $this->assertSame(1, DB::table('releases')->where('id', 3)->value('haspreview'));
        $this->assertSame(0, DB::table('releases')->where('id', 4)->value('haspreview'));
        $this->assertSame(-1, DB::table('releases')->where('id', 5)->value('haspreview'));
        $this->assertSame(0, DB::table('releases')->where('id', 6)->value('haspreview'));
        $this->assertSame(0, DB::table('releases')->where('id', 7)->value('haspreview'));

        $this->artisan('releases:requeue-missing-video-previews', ['--apply' => true])
            ->expectsOutputToContain('Re-queued 0 releases.')
            ->assertSuccessful();
    }

    public function test_it_rejects_conflicting_execution_modes(): void
    {
        $this->artisan('releases:requeue-missing-video-previews', [
            '--dry-run' => true,
            '--apply' => true,
        ])
            ->expectsOutputToContain('Choose either --dry-run or --apply, not both.')
            ->assertFailed();
    }

    /**
     * @return array<string, int>
     */
    private function release(
        int $id,
        int $categoryId,
        int $hasPreview = 0,
        int $passwordStatus = 0,
        int $rarInnerFileCount = 0,
    ): array {
        return [
            'id' => $id,
            'categories_id' => $categoryId,
            'haspreview' => $hasPreview,
            'passwordstatus' => $passwordStatus,
            'rarinnerfilecount' => $rarInnerFileCount,
        ];
    }
}
