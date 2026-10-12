<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ConfigurationDomain;
use App\Services\NameFixing\NameFixingQueryService;
use App\Services\Tmux\Tmux;
use App\Services\Tmux\TmuxMonitorService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Tests\Support\ConfigurationTestBuilder;
use Tests\TestCase;

class TmuxProcessingBacklogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ConfigurationTestBuilder::installDefaults();
        ConfigurationTestBuilder::update(ConfigurationDomain::Tmux, [
            'post_mode' => 3, 'post_non_mode' => 1, 'post_amazon_mode' => 1,
            'fix_names_enabled' => 1,
        ]);
        config(['tmux.fix_names.full_backlog' => true]);

        Schema::create('releases', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('categories_id')->default(10);
            $table->string('name')->default('obfuscated');
            $table->string('searchname')->default('obfuscated');
            $table->string('fromname')->default('poster');
            $table->string('guid')->default('guid');
            $table->integer('groups_id')->default(1);
            $table->dateTime('adddate')->nullable();
            $table->bigInteger('size')->default(2147483648);
            $table->integer('nzbstatus')->default(1);
            $table->integer('isrenamed')->default(0);
            $table->integer('predb_id')->default(0);
            $table->integer('passwordstatus')->default(0);
            $table->integer('nfostatus')->default(0);
            $table->integer('haspreview')->default(-1);
            $table->dateTime('archive_retry_at')->nullable();
            $table->dateTime('additional_pp_claimed_at')->nullable();
            $table->string('additional_pp_claim_token')->nullable();
            foreach (['proc_nfo', 'proc_files', 'proc_par2', 'proc_uid', 'proc_srr', 'proc_crc32', 'proc_hash16k'] as $column) {
                $table->integer($column)->default(1);
            }
            foreach (['anidbid', 'imdbid', 'musicinfo_id', 'consoleinfo_id', 'bookinfo_id'] as $column) {
                $table->integer($column)->nullable();
            }
            $table->integer('videos_id')->default(0);
            $table->integer('tv_episodes_id')->default(0);
            $table->integer('gamesinfo_id')->default(0);
            $table->integer('book_lookup_attempts')->default(0);
            $table->dateTime('book_lookup_retry_at')->nullable();
            $table->dateTime('book_name_normalized_at')->nullable();
        });
        foreach (['release_nfos', 'release_files', 'media_infos', 'par_hashes'] as $name) {
            Schema::create($name, static function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('releases_id');
                foreach (['name', 'crc32', 'unique_id', 'movie_name', 'hash'] as $column) {
                    $table->string($column)->nullable();
                }
            });
        }
        Schema::create('categories', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('disablepreview')->default(0);
        });
        Schema::create('usenet_groups', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->integer('active')->default(0);
            $table->integer('backfill')->default(0);
        });
        /** @var PDO $pdo */
        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('IF', static fn (int $condition, int $yes, int $no): int => $condition ? $yes : $no, 3);
    }

    public function test_disabled_metadata_lookups_do_not_contribute_to_the_backlog(): void
    {
        ConfigurationTestBuilder::update(ConfigurationDomain::Metadata, [
            'anime_lookup' => 0, 'tv_lookup' => 0, 'movie_lookup' => 0,
            'music_lookup' => 0, 'game_lookup' => 0, 'book_lookup' => 0,
        ]);
        foreach ([5070, 5030, 2030, 3010, 1010, 4050, 7010] as $category) {
            DB::table('releases')->insert(['categories_id' => $category]);
        }

        $counts = $this->counts();

        foreach (['anime', 'tv', 'movies', 'music', 'console', 'games', 'books'] as $type) {
            $this->assertSame(0, (int) $counts->{'process'.$type}, $type);
        }
    }

    public function test_disabled_panes_exclude_work_even_when_lookups_are_enabled(): void
    {
        ConfigurationTestBuilder::update(ConfigurationDomain::Tmux, [
            'post_mode' => 0, 'post_non_mode' => 0, 'post_amazon_mode' => 0,
            'fix_names_enabled' => 0,
        ]);
        ConfigurationTestBuilder::update(ConfigurationDomain::Metadata, ['anime_lookup' => 1]);
        foreach ([5070, 5030, 2030, 3010, 1010, 4050, 7010] as $category) {
            DB::table('releases')->insert(['categories_id' => $category]);
        }
        DB::table('releases')->insert(['nfostatus' => -1, 'proc_par2' => 0]);

        $counts = $this->counts();

        foreach (['anime', 'tv', 'movies', 'music', 'console', 'games', 'books', 'nfo', 'renames'] as $type) {
            $this->assertSame(0, (int) $counts->{'process'.$type}, $type);
        }
    }

    public function test_renamed_only_lookups_exclude_unrenamed_tv_movies_and_games(): void
    {
        ConfigurationTestBuilder::update(ConfigurationDomain::Metadata, [
            'tv_lookup' => 2, 'movie_lookup' => 2, 'game_lookup' => 2,
        ]);
        foreach ([5030, 2030, 1010, 4050] as $category) {
            DB::table('releases')->insert([
                ['categories_id' => $category, 'isrenamed' => 0],
                ['categories_id' => $category, 'isrenamed' => 1],
            ]);
        }

        $counts = $this->counts();

        foreach (['tv', 'movies', 'console', 'games'] as $type) {
            $this->assertSame(1, (int) $counts->{'process'.$type}, $type);
        }
    }

    public function test_rename_count_excludes_missing_sources_and_drains_after_a_source_is_checked(): void
    {
        DB::table('releases')->insert([
            ['id' => 1, 'proc_files' => 0],
            ['id' => 2, 'proc_files' => 0],
        ]);
        DB::table('release_files')->insert(['releases_id' => 2, 'name' => 'obfuscated.rar']);

        $this->assertSame(1, (int) $this->counts()->processrenames);
        DB::table('releases')->where('id', 2)->update(['proc_files' => 1]);

        $this->assertSame(0, (int) $this->counts()->processrenames);
        $this->assertSame(0, (int) DB::table('releases')->where('id', 1)->value('proc_files'));
    }

    public function test_renamed_only_music_lookup_excludes_unrenamed_releases(): void
    {
        ConfigurationTestBuilder::update(ConfigurationDomain::Metadata, ['music_lookup' => 2]);
        DB::table('releases')->insert([
            ['categories_id' => 3010, 'isrenamed' => 0],
            ['categories_id' => 3010, 'isrenamed' => 1],
        ]);

        $this->assertSame(1, (int) $this->counts()->processmusic);
    }

    public function test_all_scheduled_rename_sources_are_counted_once_per_release(): void
    {
        $sources = [
            [1, 'proc_nfo', 'release_nfos', []],
            [2, 'proc_files', 'release_files', ['name' => 'title.rar']],
            [3, 'proc_uid', 'media_infos', ['unique_id' => 'uid']],
            [4, 'proc_srr', 'release_files', ['name' => 'title.srr']],
            [5, 'proc_hash16k', 'par_hashes', ['hash' => 'hash']],
            [6, 'proc_crc32', 'release_files', ['name' => 'title.rar', 'crc32' => 'crc']],
            [7, 'proc_uid', 'media_infos', ['movie_name' => 'Movie Title']],
        ];
        foreach ($sources as [$id, $status, $table, $attributes]) {
            DB::table('releases')->insert(['id' => $id, $status => 0]);
            DB::table($table)->insert(['releases_id' => $id, ...$attributes]);
        }
        DB::table('releases')->insert(['id' => 8, 'proc_par2' => 0]);
        DB::table('releases')->insert(['id' => 9, 'proc_files' => 0, 'proc_uid' => 0]);
        DB::table('release_files')->insert(['releases_id' => 9, 'name' => 'title.rar']);
        DB::table('media_infos')->insert(['releases_id' => 9, 'unique_id' => 'uid']);

        $this->assertSame(9, (int) $this->counts()->processrenames);
    }

    public function test_par2_work_without_an_nzb_is_neither_counted_nor_selected(): void
    {
        DB::table('releases')->insert([
            ['id' => 1, 'proc_par2' => 0, 'nzbstatus' => 0],
            ['id' => 2, 'proc_par2' => 0, 'nzbstatus' => 1],
        ]);

        $this->assertSame(1, (int) $this->counts()->processrenames);
        $candidates = (new NameFixingQueryService)->candidateBatch(NameFixingQueryService::SOURCE_PAR2, 2, 1);

        $this->assertSame([2], array_column($candidates, 'id'));
    }

    public function test_par2_remains_actionable_when_other_processing_is_disabled_or_failed(): void
    {
        ConfigurationTestBuilder::update(ConfigurationDomain::PostProcessing, ['lookup_nfo' => 0]);
        DB::table('releases')->insert(['id' => 1, 'proc_par2' => 0, 'passwordstatus' => -1]);
        DB::table('releases')->insert(['id' => 2, 'proc_par2' => 0, 'nfostatus' => -1]);
        DB::table('releases')->insert(['id' => 3, 'proc_par2' => 0, 'nfostatus' => -9]);
        DB::table('releases')->insert(['id' => 4, 'proc_par2' => 0, 'nfostatus' => -10]);

        $this->assertSame(4, (int) $this->counts()->processrenames);
        $candidates = (new NameFixingQueryService)->candidateBatch(NameFixingQueryService::SOURCE_PAR2, 2, 1);

        $this->assertSame([1, 2, 3, 4], array_column($candidates, 'id'));
    }

    public function test_stripped_mode_excludes_panes_it_does_not_run(): void
    {
        ConfigurationTestBuilder::update(ConfigurationDomain::Tmux, ['sequential_mode' => 2]);
        ConfigurationTestBuilder::update(ConfigurationDomain::Metadata, ['anime_lookup' => 1]);
        foreach ([5070, 5030, 2030, 3010] as $category) {
            DB::table('releases')->insert(['categories_id' => $category]);
        }
        DB::table('releases')->insert(['nfostatus' => -1]);
        DB::table('usenet_groups')->insert(['id' => 1, 'active' => 1]);

        $counts = $this->counts();

        foreach (['anime', 'tv', 'movies'] as $type) {
            $this->assertSame(0, (int) $counts->{'process'.$type}, $type);
        }
        $this->assertSame(1, (int) $counts->processmusic);
        $this->assertSame(1, (int) $counts->processnfo);
    }

    public function test_stripped_mode_counts_sequential_additional_and_nfo_work_for_active_or_backfill_groups(): void
    {
        ConfigurationTestBuilder::update(ConfigurationDomain::Tmux, [
            'sequential_mode' => 2, 'post_mode' => 0, 'post_non_mode' => 0, 'post_amazon_mode' => 0,
        ]);
        ConfigurationTestBuilder::update(ConfigurationDomain::PostProcessing, ['lookup_nfo' => 0]);
        DB::table('categories')->insert(['id' => 10]);
        DB::table('usenet_groups')->insert([
            ['id' => 1, 'active' => 1, 'backfill' => 0],
            ['id' => 2, 'active' => 0, 'backfill' => 0],
            ['id' => 3, 'active' => 0, 'backfill' => 1],
        ]);
        foreach ([1, 2, 3] as $groupId) {
            DB::table('releases')->insert(['groups_id' => $groupId, 'passwordstatus' => -1]);
        }
        DB::table('releases')->insert(['groups_id' => 3, 'nfostatus' => -1]);
        DB::table('releases')->insert(['groups_id' => 2, 'nfostatus' => -1]);

        $counts = $this->monitorCounts(postMode: 0, sequentialMode: 2);

        $this->assertSame(2, $counts['work']);
        $this->assertSame(2, $counts['work_available']);
        $this->assertSame(1, (int) $counts['processnfo']);
        $this->assertSame(3, $counts['total_work']);
    }

    public function test_recent_rename_count_keeps_a_stable_cache_key_and_the_six_hour_cutoff(): void
    {
        $this->freezeTime();
        config(['tmux.fix_names.full_backlog' => false]);
        $databaseTime = CarbonImmutable::parse(DB::selectOne('SELECT CURRENT_TIMESTAMP AS clock')->clock);
        DB::table('releases')->insert([
            ['proc_par2' => 0, 'adddate' => $databaseTime->subHours(5)],
            ['proc_par2' => 0, 'adddate' => $databaseTime->subHours(7)],
        ]);
        $tmux = new Tmux;
        $firstSql = $tmux->proc_query(1, '', '');
        $monitor = (new ReflectionClass(TmuxMonitorService::class))->newInstanceWithoutConstructor();
        $readCached = new ReflectionMethod(TmuxMonitorService::class, 'getCachedProc1Result');
        $firstResult = $readCached->invoke($monitor, $firstSql);
        $this->travel(2)->seconds();

        $secondSql = $tmux->proc_query(1, '', '');

        $this->assertSame($firstSql, $secondSql);
        $this->assertSame(1, (int) $firstResult->processrenames);
        $this->assertSame($firstResult, $readCached->invoke($monitor, $secondSql));
    }

    public function test_total_includes_ready_and_in_progress_additional_work_but_excludes_deferred_work(): void
    {
        $this->freezeTime();
        DB::table('categories')->insert(['id' => 10]);
        DB::table('releases')->insert(['id' => 1, 'passwordstatus' => -1]);
        DB::table('releases')->insert(['id' => 2, 'passwordstatus' => -1, 'additional_pp_claimed_at' => now()]);
        DB::table('releases')->insert(['id' => 3, 'passwordstatus' => -1, 'archive_retry_at' => now()->addHour()]);
        DB::table('releases')->insert(['id' => 4, 'passwordstatus' => -1, 'nzbstatus' => 0]);
        DB::table('releases')->insert(['id' => 5, 'passwordstatus' => -1, 'size' => 1]);

        $counts = $this->monitorCounts(postMode: 3);

        $this->assertSame(2, $counts['work']);
        $this->assertSame(1, $counts['work_available']);
        $this->assertSame(2, $counts['total_work']);
    }

    public function test_disabled_additional_processing_does_not_contribute_to_the_total(): void
    {
        DB::table('categories')->insert(['id' => 10]);
        DB::table('releases')->insert(['passwordstatus' => -1]);

        $counts = $this->monitorCounts(postMode: 2);

        $this->assertSame(0, $counts['work']);
        $this->assertSame(0, $counts['work_available']);
        $this->assertSame(0, $counts['total_work']);
    }

    /** @return array<string, int> */
    private function monitorCounts(int $postMode, int $sequentialMode = 0): array
    {
        $monitor = (new ReflectionClass(TmuxMonitorService::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(TmuxMonitorService::class, 'tmux'))->setValue($monitor, new Tmux);
        $state = new ReflectionProperty(TmuxMonitorService::class, 'runVar');
        $state->setValue($monitor, ['settings' => ['post' => $postMode], 'constants' => ['sequential' => $sequentialMode]]);
        (new ReflectionMethod(TmuxMonitorService::class, 'getProcessCounts'))->invoke($monitor);
        (new ReflectionMethod(TmuxMonitorService::class, 'calculateStatistics'))->invoke($monitor);

        return $state->getValue($monitor)['counts']['now'];
    }

    private function counts(): object
    {
        return DB::selectOne((new Tmux)->proc_query(1, '', ''));
    }
}
