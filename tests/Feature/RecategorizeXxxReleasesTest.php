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
use RuntimeException;
use Tests\TestCase;

class RecategorizeXxxReleasesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Schema::create('categories', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('root_categories_id');
            $table->string('title');
        });
        DB::table('categories')->insert([
            ['id' => Category::XXX_SD, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'SD'],
            ['id' => Category::XXX_HD, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'HD'],
            ['id' => Category::XXX_UHD, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'UHD'],
            ['id' => Category::XXX_VR, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'VR'],
            ['id' => Category::XXX_ONLYFANS, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'OnlyFans'],
            ['id' => Category::XXX_JAV, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'JAV'],
            ['id' => Category::XXX_PACK, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'Pack'],
            ['id' => Category::XXX_IMAGESET, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'ImgSet'],
            ['id' => Category::XXX_DVD, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'DVD'],
            ['id' => Category::XXX_OTHER, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'Other'],
            ['id' => 6020, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'WMV'],
            ['id' => 6041, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'HD Clips'],
            ['id' => 6080, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'SD (legacy)'],
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
        });

        Schema::create('user_excluded_categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('users_id');
            $table->integer('categories_id');
            $table->timestamps();
            $table->unique(['users_id', 'categories_id']);
        });

        Schema::create('category_regexes', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('regex');
            $table->integer('categories_id');
        });
    }

    public function test_preview_reports_changes_without_writing(): void
    {
        $id = $this->insertRelease('Brazzers.23.11.15.Model.Name.XXX.1080p.MP4-KTR', 6041);
        $this->insertRelease('Brazzers.23.11.15.Model.Name.XXX.2160p.MP4-KTR', Category::XXX_UHD);
        Search::shouldReceive('updateRelease')->never();

        $this->artisan('releases:recategorize-xxx', ['--details' => true])
            ->expectsOutput("#{$id} Brazzers.23.11.15.Model.Name.XXX.1080p.MP4-KTR: XXX > HD Clips (6041) -> XXX > HD (6040)")
            ->expectsOutput('Inspected: 2; Changes: 1; Skipped: 0; Failed: 0.')
            ->expectsOutput('Would update: 1. No releases were changed.')
            ->assertSuccessful();

        $this->assertSame(6041, DB::table('releases')->where('id', $id)->value('categories_id'));
        $this->assertTrue(DB::table('categories')->where('id', 6041)->exists());
    }

    public function test_apply_sorts_releases_and_removes_retired_categories(): void
    {
        $expected = [
            $this->insertRelease('Brazzers.23.11.15.Model.Name.XXX.1080p.MP4-KTR', 6041) => Category::XXX_HD,
            $this->insertRelease('XXX.Model.Name.Hardcore.Scene.WMV-GROUP', 6020) => Category::XXX_SD,
            $this->insertRelease('Brazzers.23.11.15.Model.Name.XXX.480p.MP4-KTR', 6080) => Category::XXX_SD,
            $this->insertRelease('SSIS-123.Model.Name.1080p', Category::XXX_OTHER) => Category::XXX_JAV,
            $this->insertRelease('XXX.Studio.Model.Name.Imageset', Category::XXX_OTHER) => Category::XXX_IMAGESET,
            $this->insertRelease('Brazzers.23.11.15.Model.Name.XXX.2160p.MP4-KTR', Category::XXX_UHD) => Category::XXX_UHD,
        ];
        DB::table('user_excluded_categories')->insert([
            ['users_id' => 1, 'categories_id' => 6041],
            ['users_id' => 2, 'categories_id' => 6041],
            ['users_id' => 2, 'categories_id' => Category::XXX_HD],
            ['users_id' => 3, 'categories_id' => 6080],
        ]);
        DB::table('category_regexes')->insert(['regex' => '/clip/i', 'categories_id' => 6041]);
        $cacheVersion = Cache::get('releases:cache_version', 1);
        Search::shouldReceive('updateRelease')->times(5);

        $this->artisan('releases:recategorize-xxx', ['--apply' => true])
            ->expectsOutput('Inspected: 6; Changes: 5; Skipped: 0; Failed: 0.')
            ->expectsOutput('Database categories updated: 5; Search refreshes requested: 5.')
            ->expectsOutput('Removed retired XXX categories: XXX > WMV (6020), XXX > HD Clips (6041), XXX > SD (legacy) (6080). User exclusions dropped: 3; category regexes moved: 1.')
            ->assertSuccessful();

        foreach ($expected as $id => $categoryId) {
            $this->assertSame($categoryId, DB::table('releases')->where('id', $id)->value('categories_id'));
        }
        $this->assertSame($cacheVersion + 1, Cache::get('releases:cache_version', 1));
        $this->assertFalse(DB::table('categories')->whereIn('id', [6020, 6041, 6080])->exists());
        $this->assertSame(
            [[2, Category::XXX_HD]],
            DB::table('user_excluded_categories')->orderBy('users_id')->get()
                ->map(fn (object $row): array => [(int) $row->users_id, (int) $row->categories_id])->all()
        );
        $this->assertSame(Category::XXX_HD, DB::table('category_regexes')->value('categories_id'));
    }

    public function test_releases_never_leave_xxx_and_retired_ones_use_the_fallback(): void
    {
        $retired = $this->insertRelease('Some.Movie.2024.1080p.BluRay.x264-GROUP', 6041);
        $live = $this->insertRelease('Some.Movie.2024.1080p.BluRay.x264-GROUP', Category::XXX_PACK);
        $formerXvid = $this->insertRelease('Some.Movie.2024.1080p.BluRay.x264-GROUP', Category::XXX_SD);
        $this->insertRelease('Some.Movie.2024.1080p.BluRay.x264-GROUP', Category::MOVIE_HD);
        Search::shouldReceive('updateRelease')->once()->with($retired);

        $this->artisan('releases:recategorize-xxx', ['--apply' => true])
            ->expectsOutput('Inspected: 3; Changes: 1; Skipped: 0; Failed: 0.')
            ->assertSuccessful();

        $this->assertSame(Category::XXX_HD, DB::table('releases')->where('id', $retired)->value('categories_id'));
        $this->assertSame(Category::XXX_PACK, DB::table('releases')->where('id', $live)->value('categories_id'));
        $this->assertSame(Category::XXX_SD, DB::table('releases')->where('id', $formerXvid)->value('categories_id'));
    }

    public function test_retired_categories_are_kept_while_they_still_hold_releases(): void
    {
        $this->insertRelease('Brazzers.23.11.15.Model.Name.XXX.1080p.MP4-KTR', 6041);
        $this->insertRelease('Brazzers.23.11.16.Model.Name.XXX.1080p.MP4-KTR', 6041);
        DB::table('user_excluded_categories')->insert(['users_id' => 1, 'categories_id' => 6041]);
        Search::shouldReceive('updateRelease')->once();

        $this->artisan('releases:recategorize-xxx', ['--apply' => true, '--limit' => 1])
            ->expectsOutput('Retired XXX categories still hold 1 releases, so they were kept. Run again with --apply and without --limit to finish.')
            ->assertSuccessful();

        $this->assertSame(3, DB::table('categories')->whereIn('id', [6020, 6041, 6080])->count());
        $this->assertSame(6041, DB::table('user_excluded_categories')->value('categories_id'));
    }

    public function test_missing_target_categories_fail_before_changing_releases(): void
    {
        $id = $this->insertRelease('SSIS-123.Model.Name.1080p', Category::XXX_OTHER);
        DB::table('categories')->where('id', Category::XXX_JAV)->delete();
        Search::shouldReceive('updateRelease')->never();

        $this->artisan('releases:recategorize-xxx', ['--apply' => true])
            ->expectsOutput('XXX categories missing: 6048. Run php artisan migrate first. No releases were changed.')
            ->assertFailed();

        $this->assertSame(Category::XXX_OTHER, DB::table('releases')->where('id', $id)->value('categories_id'));
    }

    public function test_concurrent_change_is_skipped(): void
    {
        $id = $this->insertRelease('Brazzers.23.11.15.Model.Name.XXX.1080p.MP4-KTR', Category::XXX_OTHER);
        Search::shouldReceive('updateRelease')->never();
        $this->mock(CategorizationService::class)->shouldReceive('determineCategory')->once()
            ->andReturnUsing(function () use ($id): array {
                DB::table('releases')->where('id', $id)->update(['categories_id' => Category::XXX_SD]);

                return ['categories_id' => Category::XXX_HD];
            });

        $this->artisan('releases:recategorize-xxx', ['--apply' => true])
            ->expectsOutputToContain("#{$id} changed during re-sort; skipped.")
            ->expectsOutput('Inspected: 1; Changes: 1; Skipped: 1; Failed: 0.')
            ->assertSuccessful();

        $this->assertSame(Category::XXX_SD, DB::table('releases')->where('id', $id)->value('categories_id'));
    }

    public function test_search_refresh_failure_is_reported(): void
    {
        $id = $this->insertRelease('Brazzers.23.11.15.Model.Name.XXX.1080p.MP4-KTR', Category::XXX_OTHER);
        Search::shouldReceive('updateRelease')->once()->with($id)->andThrow(new RuntimeException('Index unavailable'));

        $this->artisan('releases:recategorize-xxx', ['--apply' => true])
            ->expectsOutputToContain("#{$id} category saved, but search refresh failed: Index unavailable")
            ->expectsOutput('Database categories updated: 1; Search refreshes requested: 0.')
            ->assertFailed();

        $this->assertSame(Category::XXX_HD, DB::table('releases')->where('id', $id)->value('categories_id'));
    }

    private function insertRelease(string $name, int $categoryId): int
    {
        return (int) DB::table('releases')->insertGetId([
            'name' => $name,
            'searchname' => $name,
            'categories_id' => $categoryId,
        ]);
    }
}
