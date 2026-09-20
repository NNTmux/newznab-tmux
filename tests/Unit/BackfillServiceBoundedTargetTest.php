<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Backfill\SafeBackfillPlanner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BackfillServiceBoundedTargetTest extends TestCase
{
    #[Test]
    public function bounded_target_never_crosses_quantity_date_or_server_oldest_limits(): void
    {
        $planner = new SafeBackfillPlanner;

        self::assertSame(85_000, $planner->targetPost(100_000, 20_000, 5_000, 85_000));
        self::assertSame(80_000, $planner->targetPost(100_000, 20_000, 5_000, 70_000));
        self::assertSame(90_000, $planner->targetPost(100_000, 20_000, 90_000, 85_000));
    }

    #[Test]
    public function bounded_work_is_split_into_sequential_get_range_chunks(): void
    {
        $ranges = (new SafeBackfillPlanner)->ranges(100_000, 75_000, 10_000);

        self::assertSame([
            ['first' => 90_000, 'last' => 99_999],
            ['first' => 80_000, 'last' => 89_999],
            ['first' => 75_000, 'last' => 79_999],
        ], $ranges);
    }

    #[Test]
    public function group_worker_keeps_articles_get_range_as_the_binary_ingestion_boundary(): void
    {
        $source = file_get_contents(__DIR__.'/../../app/Console/Commands/BackfillGroupBatch.php');

        self::assertIsString($source);
        self::assertStringContainsString("'articles:get-range'", $source);
        self::assertStringNotContainsString('backfillBoundedGroup', $source);
    }
}
