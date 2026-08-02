<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Search;

use App\Services\Search\Support\ReleaseIndexProjection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReleaseIndexProjectionTest extends TestCase
{
    #[Test]
    public function release_population_projection_avoids_a_wide_group_and_sort(): void
    {
        $sql = strtolower(ReleaseIndexProjection::query()->orderBy('r.id')->limit(5000)->toSql());

        self::assertStringContainsString('select group_concat(rf.name', $sql);
        self::assertStringContainsString('where rf.releases_id = r.id', $sql);
        self::assertStringNotContainsString('join "release_files"', $sql);
        self::assertStringNotContainsString(' group by ', $sql);
        self::assertStringContainsString('order by "r"."id" asc limit 5000', $sql);
    }
}
