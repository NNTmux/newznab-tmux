<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Backfill\SafeBackfillPlanner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SafeBackfillPlannerTest extends TestCase
{
    #[Test]
    public function it_plans_one_bounded_batch_for_each_distinct_group(): void
    {
        $planner = new SafeBackfillPlanner;

        $batches = $planner->plan([
            (object) ['name' => 'alt.binaries.a', 'our_first' => 50_000, 'their_first' => 1_000, 'backfill_target' => 30],
            (object) ['name' => 'alt.binaries.a', 'our_first' => 50_000, 'their_first' => 1_000, 'backfill_target' => 30],
            (object) ['name' => 'alt.binaries.done', 'our_first' => 1_000, 'their_first' => 1_000, 'backfill_target' => 45],
            (object) ['name' => 'alt.binaries.b', 'our_first' => 8_000, 'their_first' => 1_000, 'backfill_target' => 60],
            (object) ['name' => 'alt.binaries.c', 'our_first' => 20_000, 'their_first' => 1_000, 'backfill_target' => 90],
        ], 20_000, 2, 1, 365);

        self::assertSame([
            ['name' => 'alt.binaries.a', 'articles' => 20_000, 'target_days' => 30],
            ['name' => 'alt.binaries.b', 'articles' => 7_000, 'target_days' => 60],
        ], $batches);
    }

    #[Test]
    public function it_uses_the_global_safe_date_without_multiplying_quantity_by_threads(): void
    {
        $batches = (new SafeBackfillPlanner)->plan([
            ['name' => 'alt.binaries.a', 'our_first' => 100_000, 'their_first' => 1, 'backfill_target' => 30],
        ], 20_000, 16, 2, 180);

        self::assertSame([
            ['name' => 'alt.binaries.a', 'articles' => 20_000, 'target_days' => 180],
        ], $batches);
    }
}
