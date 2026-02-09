<?php

namespace Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;

class DeletedUsersByColumnTest extends TestCase
{
    /**
     * Test that the deleted users view displays the Deleted By column.
     */
    public function test_deleted_users_view_contains_deleted_by_column(): void
    {
        $bladePath = __DIR__.'/../../../resources/views/admin/users/deleted.blade.php';

        $this->assertFileExists($bladePath);

        $content = file_get_contents($bladePath);

        // Check that the blade file contains the Deleted By column header
        $this->assertStringContainsString('Deleted By', $content);
        $this->assertStringContainsString('deleted_by', $content);
        $this->assertStringContainsString('Self', $content);
        $this->assertStringContainsString('System', $content);
    }

    /**
     * Test that the UserActivityObserver handles deleting event.
     */
    public function test_user_activity_observer_has_deleting_method(): void
    {
        $observerPath = __DIR__.'/../../../app/Observers/UserActivityObserver.php';

        $this->assertFileExists($observerPath);

        $content = file_get_contents($observerPath);

        // Check that the observer has the deleting method
        $this->assertStringContainsString('public function deleting(User $user)', $content);
        $this->assertStringContainsString("'Self'", $content);
        $this->assertStringContainsString("'System'", $content);
        $this->assertStringContainsString('deleted_by', $content);
    }

    /**
     * Test that migration file exists for deleted_by column.
     */
    public function test_migration_for_deleted_by_exists(): void
    {
        $migrationFiles = glob(__DIR__.'/../../../database/migrations/*add_deleted_by_to_users_table*');

        $this->assertNotEmpty($migrationFiles, 'Migration file for deleted_by column should exist');

        $content = file_get_contents($migrationFiles[0]);

        $this->assertStringContainsString('deleted_by', $content);
        $this->assertStringContainsString('->nullable()', $content);
    }

    /**
     * Test that the admin dashboard shows deleted_by in activity log.
     */
    public function test_dashboard_activity_log_shows_deleted_by(): void
    {
        $bladePath = __DIR__.'/../../../resources/views/admin/dashboard.blade.php';

        $this->assertFileExists($bladePath);

        $content = file_get_contents($bladePath);

        // Check that the dashboard shows deleted_by for deleted activity
        $this->assertStringContainsString("activity->type === 'deleted'", $content);
        $this->assertStringContainsString("metadata['deleted_by']", $content);
        $this->assertStringContainsString("metadata['permanent']", $content);
    }

    /**
     * Test that the AdminPageController includes metadata in activity data.
     */
    public function test_admin_controller_includes_metadata_in_activity(): void
    {
        $controllerPath = __DIR__.'/../../../app/Http/Controllers/Admin/AdminPageController.php';

        $this->assertFileExists($controllerPath);

        $content = file_get_contents($controllerPath);

        // Check that the controller includes metadata in activity data
        $this->assertStringContainsString("'metadata' => \$activity->metadata", $content);
    }
}
