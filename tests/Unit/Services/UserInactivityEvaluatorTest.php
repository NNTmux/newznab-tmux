<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\UserInactivityEvaluator;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

final class UserInactivityEvaluatorTest extends TestCase
{
    public function test_it_flags_users_with_no_recent_login_api_or_download_activity(): void
    {
        $evaluator = new UserInactivityEvaluator;
        $threshold = Carbon::parse('2026-03-11 12:00:00')->subDays(180);
        $staleDate = $threshold->copy()->subDays(181);

        $this->assertTrue($evaluator->shouldPurge(
            createdAt: $staleDate,
            updatedAt: $staleDate,
            lastLoginAt: null,
            apiAccessAt: null,
            lastDownloadAt: null,
            grabs: 0,
            threshold: $threshold,
        ));
    }

    public function test_it_keeps_users_with_recent_download_activity(): void
    {
        $evaluator = new UserInactivityEvaluator;
        $now = Carbon::parse('2026-03-11 12:00:00');
        $threshold = $now->copy()->subDays(180);
        $staleDate = $threshold->copy()->subDays(181);

        $this->assertFalse($evaluator->shouldPurge(
            createdAt: $staleDate,
            updatedAt: $now->copy()->subHours(2),
            lastLoginAt: $staleDate,
            apiAccessAt: $staleDate,
            lastDownloadAt: $now->copy()->subHours(2),
            grabs: 12,
            threshold: $threshold,
        ));
    }

    public function test_it_keeps_legacy_downloaders_while_lastdownload_is_still_null(): void
    {
        $evaluator = new UserInactivityEvaluator;
        $now = Carbon::parse('2026-03-11 12:00:00');
        $threshold = $now->copy()->subDays(180);
        $staleDate = $threshold->copy()->subDays(181);

        $this->assertFalse($evaluator->shouldPurge(
            createdAt: $staleDate,
            updatedAt: $now->copy()->subHours(6),
            lastLoginAt: $staleDate,
            apiAccessAt: $staleDate,
            lastDownloadAt: null,
            grabs: 15,
            threshold: $threshold,
        ));
    }
}
