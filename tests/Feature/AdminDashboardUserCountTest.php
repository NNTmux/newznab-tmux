<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class AdminDashboardUserCountTest extends TestCase
{
    public function test_dashboard_snapshot_excludes_soft_deleted_users_from_active_count(): void
    {
        $servicePath = app_path('Services/AdminDashboardSnapshotService.php');

        $this->assertFileExists($servicePath);

        $content = (string) file_get_contents($servicePath);

        $this->assertStringContainsString('$usersApproximateCount = ApproximateRowCount::for(\'users\');', $content);
        $this->assertStringContainsString('$softDeletedCount = User::onlyTrashed()->count();', $content);
        $this->assertStringContainsString('$activeUsersCount = max($usersApproximateCount - $softDeletedCount, 0);', $content);
        $this->assertStringContainsString("'users' => \$activeUsersCount", $content);
    }
}



