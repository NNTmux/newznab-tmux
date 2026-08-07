<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Search;

use App\Services\Search\Support\ReleaseIndexProjection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

final class ReleaseIndexProjectionTest extends TestCase
{
    #[Test]
    public function release_population_projection_avoids_a_wide_group_and_sort(): void
    {
        $sql = strtolower(ReleaseIndexProjection::query()->orderBy('r.id')->limit(5000)->toSql());

        self::assertStringContainsString('select group_concat(rf.name', $sql);
        self::assertStringContainsString('where rf.releases_id = r.id', $sql);
        self::assertStringContainsString('select group_concat(ad.audioformat', $sql);
        self::assertStringContainsString('from release_subtitles rs', $sql);
        self::assertStringContainsString('left join (select "selected_media_info"."releases_id"', $sql);
        self::assertStringContainsString('select min(candidate_media_info.id)', $sql);
        self::assertStringContainsString('left join "video_data" as "vd"', $sql);
        self::assertStringNotContainsString('join "release_files"', $sql);
        self::assertStringNotContainsString('join "audio_data"', $sql);
        self::assertStringNotContainsString('join "release_subtitles"', $sql);
        self::assertStringNotContainsString(' group by ', $sql);
        self::assertStringContainsString('order by "r"."id" asc limit 5000', $sql);
    }

    #[Test]
    public function media_info_projection_selects_one_deterministic_row_per_release(): void
    {
        Schema::create('media_infos', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('releases_id');
            $table->string('movie_name')->nullable();
            $table->string('file_name')->nullable();
            $table->string('unique_id')->nullable();
        });

        try {
            DB::table('media_infos')->insert([
                [
                    'id' => 10,
                    'releases_id' => 1,
                    'movie_name' => 'First movie',
                    'file_name' => 'first.mkv',
                    'unique_id' => 'first-id',
                ],
                [
                    'id' => 11,
                    'releases_id' => 1,
                    'movie_name' => 'Duplicate movie',
                    'file_name' => 'duplicate.mkv',
                    'unique_id' => 'duplicate-id',
                ],
            ]);

            $method = (new ReflectionClass(ReleaseIndexProjection::class))->getMethod('mediaInfoQuery');
            $query = $method->invoke(null);
            self::assertInstanceOf(Builder::class, $query);
            $rows = $query->get();
            $row = (array) $rows->first();

            self::assertCount(1, $rows);
            self::assertSame('First movie', $row['movie_name']);
            self::assertSame('first-id', $row['unique_id']);
        } finally {
            Schema::drop('media_infos');
        }
    }

    #[Test]
    public function both_search_engines_bulk_populate_from_the_canonical_projection(): void
    {
        $commandSource = file_get_contents(__DIR__.'/../../../../app/Console/Commands/NntmuxPopulateSearchIndexes.php');

        self::assertIsString($commandSource);
        self::assertGreaterThanOrEqual(2, substr_count($commandSource, 'ReleaseIndexProjection::query()->orderBy(\'r.id\')'));
        self::assertGreaterThanOrEqual(2, substr_count($commandSource, 'ReleaseSearchIndexDocument::normalize((array) $item)'));
    }
}
