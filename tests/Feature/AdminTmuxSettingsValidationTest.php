<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\AdminTmuxController;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminTmuxSettingsValidationTest extends TestCase
{
    #[Test]
    public function safe_backfill_operating_bounds_are_enforced(): void
    {
        $valid = Validator::make([
            'backfillthreads' => 8,
            'backfill_groups' => 16,
            'backfill_qty' => 1_000_000,
            'maxmssgs' => 100_000,
            'back_timer' => 3600,
        ], AdminTmuxController::backfillValidationRules());
        self::assertFalse($valid->fails());

        $invalid = Validator::make([
            'backfillthreads' => 9,
            'backfill_groups' => 0,
            'backfill_qty' => 999,
            'maxmssgs' => 100_001,
            'back_timer' => 4,
        ], AdminTmuxController::backfillValidationRules());

        self::assertTrue($invalid->fails());
        self::assertSame([
            'backfillthreads',
            'backfill_groups',
            'backfill_qty',
            'maxmssgs',
            'back_timer',
        ], array_keys($invalid->errors()->toArray()));
    }
}
