<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Video;
use App\Services\TvProcessing\Providers\TraktProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class TvProviderVideoInsertRaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        DB::reconnect();

        Schema::create('videos', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->integer('type')->default(0);
            $table->string('countries_id', 2)->default('');
            $table->dateTime('started')->nullable();
            $table->string('source')->default('0');
            foreach (['tvdb', 'trakt', 'tvrage', 'tvmaze', 'imdb', 'tmdb'] as $column) {
                $table->string($column)->default('0');
            }
            $table->unique(['title', 'type', 'started', 'countries_id'], 'ix_videos_title');
        });
        Schema::create('tv_info', function (Blueprint $table): void {
            $table->unsignedInteger('videos_id')->primary();
            $table->text('summary')->nullable();
            $table->string('publisher')->default('');
            $table->string('localzone')->default('');
        });
    }

    /**
     * Two TV workers can both find no row by title and both insert; the loser used to die on the
     * unique key (1062 'ix_videos_title') and fail its whole batch.
     */
    public function test_a_show_inserted_by_another_worker_between_the_lookup_and_the_insert_is_reused(): void
    {
        $provider = new class extends TraktProvider
        {
            public int $updatedId = 0;

            public function update(int $videoId, array $show = []): void
            {
                $this->updatedId = $videoId;
            }
        };
        $raced = false;
        // Simulate the other workers: right before this worker's own insert, one adds a different production
        // that shares the title (lower id, so a title-only lookup would pick it) and one adds this very show.
        Video::creating(function (Video $video) use (&$raced): void {
            if (! $raced) {
                $raced = true;
                DB::table('videos')->insert(['title' => 'Less Than Evil', 'type' => 0, 'countries_id' => 'US', 'started' => '2005-01-01 00:00:00']);
                DB::table('videos')->insert(['title' => 'Less Than Evil', 'type' => 0, 'countries_id' => 'KR', 'started' => '2018-12-03 00:00:00']);
            }
        });

        // The real caller runs inside a transaction (TvShowAdder), where the recovery read must be a locking one.
        $id = DB::transaction(static fn (): int => $provider->add([
            'type' => 0, 'title' => 'Less Than Evil', 'country' => 'KR', 'started' => '2018-12-03 00:00:00', 'source' => 5,
            'tvdb' => 0, 'trakt' => 0, 'tvrage' => 0, 'tvmaze' => 0, 'imdb' => 0, 'tmdb' => 0,
            'summary' => 'A prosecutor and a killer.', 'publisher' => 'OCN', 'localzone' => 'Asia/Seoul', 'aliases' => '',
        ]));

        $this->assertTrue($raced);
        $this->assertSame(2, DB::table('videos')->count());
        $this->assertSame((int) DB::table('videos')->where('countries_id', 'KR')->value('id'), $id);
        $this->assertNotSame((int) DB::table('videos')->where('countries_id', 'US')->value('id'), $id);
        $this->assertSame($id, $provider->updatedId);
        // The supplementary row exists even though the winning worker never wrote one.
        $this->assertSame('OCN', DB::table('tv_info')->where('videos_id', $id)->value('publisher'));
    }
}
