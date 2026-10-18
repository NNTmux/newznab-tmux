<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Facades\Search;
use App\Models\Category;
use App\Services\Categorization\CategorizationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use RuntimeException;
use Tests\TestCase;
use Tests\Unit\XxxCategorizationTest;

class RecategorizeOnlyFansTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame('array', config('cache.default'));

        Schema::create('categories', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('root_categories_id');
            $table->string('title');
        });
        DB::table('categories')->insert([
            ['id' => Category::XXX_CLIPHD, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'HD Clips'],
            ['id' => Category::XXX_UHD, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'UHD'],
            ['id' => Category::XXX_OTHER, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'Other'],
            ['id' => Category::XXX_ONLYFANS, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'OnlyFans'],
            ['id' => Category::MOVIE_HD, 'root_categories_id' => Category::MOVIE_ROOT, 'title' => 'HD'],
        ]);
        Schema::create('usenet_groups', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
        });
        DB::table('usenet_groups')->insert(['id' => 1, 'name' => 'alt.binaries.erotica.erotica']);

        Schema::create('releases', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('searchname');
            $table->integer('categories_id');
            $table->integer('groups_id')->default(1);
            $table->string('fromname')->default('poster@example.com');
            $table->integer('iscategorized')->default(0);
            $table->integer('isrenamed')->default(1);
            $table->string('imdbid')->default('tt1234567');
            $table->integer('videos_id')->default(42);
            $table->integer('tv_episodes_id')->default(-2);
            $table->integer('bookinfo_id')->default(37);
            $table->integer('book_lookup_attempts')->default(4);
            $table->dateTime('book_lookup_retry_at')->default('2026-10-19 12:00:00');
            $table->integer('proc_nfo')->default(1);
        });
    }

    public function test_preview_lists_changes_without_writing_or_requesting_search_refreshes(): void
    {
        $id = $this->insertRelease('OF.Sadie.Summers.NEW.TRADWIFE.BG.1080p');
        $before = DB::table('releases')->find($id);
        $cacheVersion = Cache::get('releases:cache_version', 1);
        Search::shouldReceive('updateRelease')->never();

        $this->artisan('releases:recategorize-onlyfans')
            ->expectsOutput(sprintf('#%d OF.Sadie.Summers.NEW.TRADWIFE.BG.1080p: XXX > HD Clips (%d) -> XXX > OnlyFans (6047)', $id, Category::XXX_CLIPHD))
            ->expectsOutput('Inspected: 1; Eligible: 1; Skipped: 0; Failed: 0.')
            ->expectsOutput('Would update: 1. No releases were changed.')
            ->assertSuccessful();

        $this->assertEquals($before, DB::table('releases')->find($id));
        $this->assertSame($cacheVersion, Cache::get('releases:cache_version', 1));
    }

    #[DataProviderExternal(XxxCategorizationTest::class, 'onlyFansReleasesProvider')]
    public function test_service_preserves_onlyfans_markers_before_normalizing_names(string $releaseName): void
    {
        foreach ([0, 1] as $groupId) {
            $result = app(CategorizationService::class)->determineCategory($groupId, $releaseName, debug: true);

            $this->assertSame(Category::XXX_ONLYFANS, $result['categories_id']);
            $this->assertSame($releaseName, $result['debug']['release_name']);
        }
    }

    #[DataProviderExternal(XxxCategorizationTest::class, 'nonOnlyFansReleasesProvider')]
    public function test_service_does_not_broaden_onlyfans_matching_when_normalizing_names(string $releaseName): void
    {
        foreach ([0, 1] as $groupId) {
            $result = app(CategorizationService::class)->determineCategory($groupId, $releaseName);

            $this->assertNotSame(Category::XXX_ONLYFANS, $result['categories_id']);
        }
    }

    public function test_apply_changes_only_categories_and_is_idempotent(): void
    {
        $ids = [
            $this->insertRelease('OF.Theapolloshow.Veronica.Grace.1080p'),
            $this->insertRelease('of.Creator.Title.2160p', Category::XXX_UHD),
            $this->insertRelease('oF.Creator.Pack.mp4', Category::XXX_OTHER),
            $this->insertRelease('OF.Creator.Title', Category::XXX_OTHER),
        ];
        $before = DB::table('releases')->get()->keyBy('id');
        $cacheVersion = Cache::get('releases:cache_version', 1);
        foreach ($ids as $id) {
            Search::shouldReceive('updateRelease')->once()->with($id);
        }

        $this->artisan('releases:recategorize-onlyfans', ['--apply' => true])
            ->expectsOutput('Database categories updated: 4; Search refreshes requested: 4.')
            ->assertSuccessful();

        $this->assertSame($cacheVersion + 1, Cache::get('releases:cache_version', 1));

        foreach ($ids as $id) {
            $expected = (array) $before->get($id);
            $expected['categories_id'] = Category::XXX_ONLYFANS;
            $this->assertSame($expected, (array) DB::table('releases')->find($id));
        }

        $this->artisan('releases:recategorize-onlyfans', ['--apply' => true])
            ->expectsOutput('Database categories updated: 0; Search refreshes requested: 0.')
            ->assertSuccessful();
    }

    public function test_repair_excludes_unrelated_categories_names_and_photo_packs(): void
    {
        $this->insertRelease('OF.Creator.Title.1080p', Category::MOVIE_HD);
        $this->insertRelease('OF.Creator.Title.1080p', Category::XXX_ONLYFANS);
        $this->insertRelease('OF.Creator.Title.1080p', 6998);
        foreach (['Movie.OF.Creator.1080p', 'OF-Creator.1080p', 'OFFER.Creator.1080p', ' OF.Creator.1080p', '[OF.Creator.1080p]', 'OnlyFans.Creator.1080p', 'OF.Creator.Photoset', 'OF.Creator.Pack.1080p'] as $name) {
            $this->insertRelease($name);
        }
        $before = DB::table('releases')->get();
        Search::shouldReceive('updateRelease')->never();

        $this->artisan('releases:recategorize-onlyfans', ['--apply' => true])
            ->expectsOutput('Inspected: 2; Eligible: 0; Skipped: 2; Failed: 0.')
            ->expectsOutput('Database categories updated: 0; Search refreshes requested: 0.')
            ->assertSuccessful();

        $this->assertEquals($before, DB::table('releases')->get());
    }

    /**
     * @return array<string, array{array<string, int|string>}>
     */
    public static function concurrentChangesProvider(): array
    {
        return [
            'renamed' => [['searchname' => 'Some.Movie.2024.1080p']],
            'case-only rename' => [['searchname' => 'of.Creator.1080p']],
            'recategorized' => [['categories_id' => Category::XXX_OTHER]],
        ];
    }

    /** @param array<string, int|string> $changes */
    #[DataProvider('concurrentChangesProvider')]
    public function test_concurrent_changes_are_preserved(array $changes): void
    {
        $id = $this->insertRelease('OF.Creator.1080p');
        $before = (array) DB::table('releases')->find($id);
        Search::shouldReceive('updateRelease')->never();
        $this->mock(CategorizationService::class)->shouldReceive('determineCategory')->once()
            ->andReturnUsing(function () use ($id, $changes): array {
                DB::table('releases')->where('id', $id)->update($changes);

                return ['categories_id' => Category::XXX_ONLYFANS];
            });

        $this->artisan('releases:recategorize-onlyfans', ['--apply' => true])
            ->expectsOutputToContain("#{$id} changed during repair; skipped.")
            ->expectsOutput('Database categories updated: 0; Search refreshes requested: 0.')
            ->assertSuccessful();

        $this->assertSame(array_replace($before, $changes), (array) DB::table('releases')->find($id));
    }

    public function test_missing_destination_fails_before_changing_releases(): void
    {
        $id = $this->insertRelease('OF.Creator.1080p');
        DB::table('categories')->where('id', Category::XXX_ONLYFANS)->delete();
        Search::shouldReceive('updateRelease')->never();

        $this->artisan('releases:recategorize-onlyfans', ['--apply' => true])
            ->expectsOutput('XXX > OnlyFans category (6047) is missing. No releases were changed.')
            ->assertFailed();

        $this->assertSame(Category::XXX_CLIPHD, DB::table('releases')->where('id', $id)->value('categories_id'));
    }

    public function test_database_failure_is_reported_and_other_releases_are_repaired(): void
    {
        $failedId = $this->insertRelease('OF.Failed.1080p');
        $validId = $this->insertRelease('OF.Valid.1080p');
        DB::unprepared("CREATE TRIGGER fail_category_update BEFORE UPDATE ON releases WHEN OLD.id = {$failedId} BEGIN SELECT RAISE(ABORT, 'simulated write failure'); END");
        Search::shouldReceive('updateRelease')->once()->with($validId);

        $this->artisan('releases:recategorize-onlyfans', ['--apply' => true])
            ->expectsOutputToContain("#{$failedId} category correction failed:")
            ->expectsOutput('Inspected: 2; Eligible: 2; Skipped: 0; Failed: 1.')
            ->expectsOutput('Database categories updated: 1; Search refreshes requested: 1.')
            ->assertFailed();

        $this->assertSame(Category::XXX_CLIPHD, DB::table('releases')->where('id', $failedId)->value('categories_id'));
        $this->assertSame(Category::XXX_ONLYFANS, DB::table('releases')->where('id', $validId)->value('categories_id'));
    }

    public function test_search_exception_reports_the_affected_id_and_a_failed_exit_status(): void
    {
        $id = $this->insertRelease('OF.Creator.1080p');
        Search::shouldReceive('updateRelease')->once()->with($id)->andThrow(new RuntimeException('Index unavailable'));

        $this->artisan('releases:recategorize-onlyfans', ['--apply' => true])
            ->expectsOutputToContain("#{$id} category saved, but search refresh failed: Index unavailable")
            ->expectsOutput('Inspected: 1; Eligible: 1; Skipped: 0; Failed: 1.')
            ->expectsOutput('Database categories updated: 1; Search refreshes requested: 0.')
            ->assertFailed();

        $this->assertSame(Category::XXX_ONLYFANS, DB::table('releases')->where('id', $id)->value('categories_id'));
    }

    public function test_apply_processes_more_than_one_batch_without_skipping_rows(): void
    {
        $rows = [];
        for ($index = 0; $index < 251; $index++) {
            $rows[] = ['name' => "OF.Creator.Title.{$index}.1080p", 'searchname' => "OF.Creator.Title.{$index}.1080p", 'categories_id' => Category::XXX_CLIPHD];
        }
        DB::table('releases')->insert($rows);
        foreach (DB::table('releases')->pluck('id') as $id) {
            Search::shouldReceive('updateRelease')->once()->with($id);
        }

        $this->artisan('releases:recategorize-onlyfans', ['--apply' => true])
            ->expectsOutput('Database categories updated: 251; Search refreshes requested: 251.')
            ->assertSuccessful();

        $this->assertSame(251, DB::table('releases')->where('categories_id', Category::XXX_ONLYFANS)->count());
    }

    private function insertRelease(string $name, int $categoryId = Category::XXX_CLIPHD): int
    {
        return (int) DB::table('releases')->insertGetId([
            'name' => $name,
            'searchname' => $name,
            'categories_id' => $categoryId,
        ]);
    }
}
