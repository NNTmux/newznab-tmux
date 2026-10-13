<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\ConfigurationDomain;
use App\Facades\Search;
use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite versions of the tables TV processing reads and writes, with the production
 * unique keys, plus the tv_lookup_* state migration and TV lookup enabled.
 */
trait TvProcessingSchema
{
    protected function createTvProcessingSchema(): void
    {
        ConfigurationTestBuilder::installDefaults();
        ConfigurationTestBuilder::update(ConfigurationDomain::Metadata, ['tv_lookup' => 1, 'max_tv_processed' => 75]);

        Schema::create('releases', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->default('');
            $table->string('searchname')->default('');
            $table->string('guid', 40)->default('');
            $table->char('leftguid', 1)->default('a');
            $table->unsignedInteger('groups_id')->default(1);
            $table->integer('categories_id')->default(Category::TV_HD);
            $table->unsignedBigInteger('size')->default(2_000_000);
            $table->dateTime('postdate')->nullable();
            $table->dateTime('adddate')->nullable();
            $table->boolean('isrenamed')->default(true);
            $table->unsignedInteger('videos_id')->default(0);
            $table->integer('tv_episodes_id')->default(0);
        });
        $this->tvLookupStateMigration()->up();

        Schema::create('videos', static function (Blueprint $table): void {
            $table->increments('id');
            $table->tinyInteger('type')->default(0);
            $table->string('title', 180);
            $table->char('countries_id', 2)->default('');
            $table->string('started')->default('');
            $table->unsignedInteger('anidb')->default(0);
            $table->string('imdb', 100)->default('0');
            $table->unsignedInteger('tmdb')->default(0);
            $table->unsignedInteger('trakt')->default(0);
            $table->unsignedInteger('tvdb')->default(0);
            $table->unsignedInteger('tvmaze')->default(0);
            $table->unsignedInteger('tvrage')->default(0);
            $table->tinyInteger('source')->default(0);
            $table->unique(['title', 'type', 'started', 'countries_id']);
        });

        Schema::create('tv_info', static function (Blueprint $table): void {
            $table->unsignedInteger('videos_id')->primary();
            $table->text('summary')->default('');
            $table->string('publisher', 50)->default('');
            $table->string('localzone', 50)->default('');
            $table->tinyInteger('image')->default(0);
        });

        Schema::create('tv_episodes', static function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('videos_id');
            $table->unsignedSmallInteger('series')->default(0);
            $table->unsignedSmallInteger('episode')->default(0);
            $table->string('se_complete', 10)->default('');
            $table->string('title', 180)->default('');
            $table->date('firstaired')->nullable();
            $table->text('summary')->default('');
            $table->unique(['videos_id', 'series', 'episode', 'firstaired']);
        });

        Schema::create('videos_aliases', static function (Blueprint $table): void {
            $table->unsignedInteger('videos_id');
            $table->string('title', 180);
            $table->timestamps();
            $table->primary(['videos_id', 'title']);
        });

        Search::shouldReceive('updateRelease')->zeroOrMoreTimes();
        Search::shouldReceive('insertTvShow')->zeroOrMoreTimes();
        Search::shouldReceive('updateTvShow')->zeroOrMoreTimes();
    }

    protected function tvLookupStateMigration(): Migration
    {
        return require database_path('migrations/2026_10_13_005535_add_tv_lookup_state_to_releases_table.php');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function insertTvRelease(string $searchname, array $attributes = []): int
    {
        return (int) DB::table('releases')->insertGetId([
            'name' => $searchname,
            'searchname' => $searchname,
            'guid' => sha1($searchname.microtime()),
            'postdate' => now()->subHour(),
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function insertVideo(string $title, array $attributes = []): int
    {
        $videoId = (int) DB::table('videos')->insertGetId(['title' => $title, ...$attributes]);
        DB::table('tv_info')->insert(['videos_id' => $videoId]);

        return $videoId;
    }

    protected function insertEpisode(int $videoId, int $season, int $episode, ?string $firstAired = null): int
    {
        return (int) DB::table('tv_episodes')->insertGetId([
            'videos_id' => $videoId,
            'series' => $season,
            'episode' => $episode,
            'se_complete' => sprintf('S%02dE%02d', $season, $episode),
            'firstaired' => $firstAired,
        ]);
    }
}
