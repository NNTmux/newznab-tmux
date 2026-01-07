<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class RoleUpgradeTest extends TestCase
{
    private User $user;

    private Role $userRole;

    private Role $supporterRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Use in-memory SQLite database for test isolation
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        DB::reconnect();

        // Create minimal tables needed for testing
        $this->createTestTables();

        // Create the basic User role
        $this->userRole = Role::firstOrCreate(
            ['name' => 'User'],
            [
                'guard_name' => 'web',
                'addyears' => 0,
                'apirequests' => 10,
                'downloadrequests' => 5,
                'defaultinvites' => 0,
                'isdefault' => 1,
                'donation' => 0,
                'canpreview' => 0,
            ]
        );
        // Create the Supporter role with addyears = 1 as default
        $this->supporterRole = Role::firstOrCreate(
            ['name' => 'Supporter'],
            [
                'guard_name' => 'web',
                'addyears' => 1,
                'apirequests' => 100,
                'downloadrequests' => 50,
                'defaultinvites' => 5,
                'isdefault' => 0,
                'donation' => 10,
                'canpreview' => 1,
            ]
        );
        // Create a test user with the User role
        $this->user = $this->createTestUser($this->userRole->id);
        $this->user->assignRole($this->userRole);
    }

    protected function tearDown(): void
    {
        DB::disconnect();
        parent::tearDown();
    }

    /**
     * Create the minimal database tables needed for testing.
     */
    private function createTestTables(): void
    {
        // Users table
        DB::statement('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            roles_id INTEGER DEFAULT 1,
            rolechangedate DATETIME NULL,
            pending_roles_id INTEGER NULL,
            pending_role_start_date DATETIME NULL,
            api_token VARCHAR(255) NULL,
            grabs INTEGER DEFAULT 0,
            invites INTEGER DEFAULT 0,
            notes TEXT DEFAULT "",
            movieview INTEGER DEFAULT 1,
            xxxview INTEGER DEFAULT 0,
            musicview INTEGER DEFAULT 1,
            consoleview INTEGER DEFAULT 1,
            bookview INTEGER DEFAULT 1,
            gameview INTEGER DEFAULT 1,
            verified INTEGER DEFAULT 1,
            can_post INTEGER DEFAULT 1,
            rate_limit INTEGER DEFAULT 60,
            email_verified_at DATETIME NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            deleted_at DATETIME NULL
        )');

        // Roles table (spatie/laravel-permission)
        DB::statement('CREATE TABLE IF NOT EXISTS roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            guard_name VARCHAR(255) NOT NULL,
            addyears INTEGER DEFAULT 0,
            apirequests INTEGER DEFAULT 10,
            downloadrequests INTEGER DEFAULT 5,
            defaultinvites INTEGER DEFAULT 0,
            isdefault INTEGER DEFAULT 0,
            donation INTEGER DEFAULT 0,
            canpreview INTEGER DEFAULT 0,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        // Model has roles table (spatie/laravel-permission)
        DB::statement('CREATE TABLE IF NOT EXISTS model_has_roles (
            role_id INTEGER NOT NULL,
            model_type VARCHAR(255) NOT NULL,
            model_id INTEGER NOT NULL,
            PRIMARY KEY (role_id, model_type, model_id)
        )');

        // Permissions table (spatie/laravel-permission)
        DB::statement('CREATE TABLE IF NOT EXISTS permissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            guard_name VARCHAR(255) NOT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        // Model has permissions table (spatie/laravel-permission)
        DB::statement('CREATE TABLE IF NOT EXISTS model_has_permissions (
            permission_id INTEGER NOT NULL,
            model_type VARCHAR(255) NOT NULL,
            model_id INTEGER NOT NULL,
            PRIMARY KEY (permission_id, model_type, model_id)
        )');

        // Role has permissions table (spatie/laravel-permission)
        DB::statement('CREATE TABLE IF NOT EXISTS role_has_permissions (
            permission_id INTEGER NOT NULL,
            role_id INTEGER NOT NULL,
            PRIMARY KEY (permission_id, role_id)
        )');

        // User role history table (if needed)
        DB::statement('CREATE TABLE IF NOT EXISTS user_role_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            users_id INTEGER NOT NULL,
            old_roles_id INTEGER NULL,
            new_roles_id INTEGER NOT NULL,
            old_rolechangedate DATETIME NULL,
            new_rolechangedate DATETIME NULL,
            changed_by_user_id INTEGER NULL,
            reason TEXT NULL,
            created_at DATETIME NULL
        )');
    }

    /**
     * Helper to create a test user without factory.
     */
    private function createTestUser(int $roleId, ?string $roleChangeDate = null): User
    {
        // Use forceCreate to bypass hashed cast
        $user = new User;
        $user->username = 'testuser_'.Str::random(8);
        $user->email = Str::random(8).'@test.com';
        $user->roles_id = $roleId;
        $user->rolechangedate = $roleChangeDate;
        $user->api_token = md5(Str::random(32));

        // Insert directly to avoid hashed cast issue
        DB::table('users')->insert([
            'username' => $user->username,
            'email' => $user->email,
            'password' => 'hashed_password_placeholder',
            'roles_id' => $roleId,
            'rolechangedate' => $roleChangeDate,
            'api_token' => $user->api_token,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::where('email', $user->email)->first();
    }

    /**
     * Test that updating a user role to Supporter with addYears=2 correctly applies 2 years.
     * This test catches the bug where addYears parameter is not properly passed to updateUserRole.
     */
    public function test_role_upgrade_to_supporter_applies_two_years_correctly(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00'));
        $addYears = 2;
        // Call updateUserRole with addYears = 2
        $result = User::updateUserRole(
            uid: $this->user->id,
            role: 'Supporter',
            applyPromotions: false,
            stackRole: false,
            addYears: $addYears
        );
        $this->assertTrue($result, 'updateUserRole should return true');
        // Refresh user from database
        $this->user->refresh();
        // User should now have Supporter role
        $this->assertEquals($this->supporterRole->id, $this->user->roles_id, 'User should have Supporter role');
        // The rolechangedate should be 2 years from now (730 days)
        $expectedExpiryDate = Carbon::parse('2025-01-01 12:00:00')->addDays($addYears * 365);
        $this->assertNotNull($this->user->rolechangedate, 'Role change date should not be null');
        $actualExpiryDate = Carbon::parse($this->user->rolechangedate);
        // Assert the expiry date is correctly 2 years in the future
        $this->assertEquals(
            $expectedExpiryDate->toDateString(),
            $actualExpiryDate->toDateString(),
            "Expected expiry date {$expectedExpiryDate->toDateString()}, but got {$actualExpiryDate->toDateString()}. The addYears parameter may not have been applied correctly."
        );
        Carbon::setTestNow();
    }

    /**
     * Test that when addYears is null, the role's default addyears is used.
     */
    public function test_role_upgrade_uses_default_addyears_when_parameter_is_null(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00'));
        // Call updateUserRole without specifying addYears (should use role's default of 1)
        $result = User::updateUserRole(
            uid: $this->user->id,
            role: 'Supporter',
            applyPromotions: false,
            stackRole: false,
            addYears: null
        );
        $this->assertTrue($result, 'updateUserRole should return true');
        $this->user->refresh();
        // The rolechangedate should be 1 year from now (365 days) - the role's default
        $expectedExpiryDate = Carbon::parse('2025-01-01 12:00:00')->addDays($this->supporterRole->addyears * 365);
        $this->assertNotNull($this->user->rolechangedate, 'Role change date should not be null');
        $actualExpiryDate = Carbon::parse($this->user->rolechangedate);
        $this->assertEquals(
            $expectedExpiryDate->toDateString(),
            $actualExpiryDate->toDateString(),
            "Expected expiry date {$expectedExpiryDate->toDateString()} (using role default), but got {$actualExpiryDate->toDateString()}"
        );
        Carbon::setTestNow();
    }

    /**
     * Test applying addYears to a user who already has the same role with a future expiry date.
     * This tests the scenario where a user extends their existing Supporter subscription.
     */
    public function test_add_years_applied_to_existing_role_with_future_expiry(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00'));

        // First, set up user with Supporter role expiring in 6 months
        $existingExpiryDate = Carbon::parse('2025-01-01 12:00:00')->addMonths(6);

        // Update user to Supporter with initial expiry
        $this->user->update([
            'roles_id' => $this->supporterRole->id,
            'rolechangedate' => $existingExpiryDate,
        ]);
        $this->user->syncRoles([$this->supporterRole->name]);
        $this->user->refresh();

        // Verify initial state
        $this->assertEquals($this->supporterRole->id, $this->user->roles_id);
        $this->assertTrue(Carbon::parse($this->user->rolechangedate)->isFuture(), 'User should have future expiry date');

        $addYears = 2;

        // Now try to apply the same role with addYears=2
        // This simulates a user buying another 2 years while still having active subscription
        $result = User::updateUserRole(
            uid: $this->user->id,
            role: 'Supporter',
            applyPromotions: false,
            stackRole: true, // Enable stacking
            addYears: $addYears
        );

        $this->assertTrue($result, 'updateUserRole should return true');

        $this->user->refresh();

        // When same role with future expiry, it should stack (pending role)
        // OR if immediate, it should extend from current expiry
        // The key assertion is that addYears (2) was used, not the role's default (1)

        // Check if stacked (pending role set)
        if ($this->user->pending_roles_id !== null) {
            // Role was stacked - the pending role should be Supporter
            $this->assertEquals(
                $this->supporterRole->id,
                $this->user->pending_roles_id,
                'Pending role should be Supporter'
            );
            $this->assertNotNull($this->user->pending_role_start_date, 'Pending role start date should be set');
        } else {
            // Role was applied immediately or expiry was extended
            // The expiry should reflect the addYears parameter being applied
            $actualExpiryDate = Carbon::parse($this->user->rolechangedate);

            // It should NOT be just 1 year (365 days) from now - that would mean addYears was ignored
            $oneYearFromNow = Carbon::parse('2025-01-01 12:00:00')->addDays(365);

            $this->assertNotEquals(
                $oneYearFromNow->toDateString(),
                $actualExpiryDate->toDateString(),
                'Expiry date should NOT be just 1 year from now. The addYears=2 parameter should have been applied.'
            );
        }

        Carbon::setTestNow();
    }

    /**
     * Test upgrading from different role to Supporter with addYears when user has future expiry.
     * This simulates: User has "User" role expiring in future, upgrades to "Supporter 2 years".
     */
    public function test_add_years_applied_when_upgrading_from_different_role(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00'));

        // User has "User" role with a future expiry date (edge case)
        $existingExpiryDate = Carbon::parse('2025-01-01 12:00:00')->addMonths(6);

        $this->user->update([
            'rolechangedate' => $existingExpiryDate,
        ]);
        $this->user->refresh();

        // Verify initial state - User role
        $this->assertEquals($this->userRole->id, $this->user->roles_id);

        $addYears = 2;

        // Upgrade to Supporter with addYears=2 (without stacking)
        $result = User::updateUserRole(
            uid: $this->user->id,
            role: 'Supporter',
            applyPromotions: false,
            stackRole: false, // Disable stacking - should apply immediately
            addYears: $addYears
        );

        $this->assertTrue($result, 'updateUserRole should return true');

        $this->user->refresh();

        // User should now have Supporter role
        $this->assertEquals($this->supporterRole->id, $this->user->roles_id, 'User should have Supporter role');

        // The expiry date should be 2 years from now (addYears=2)
        $expectedExpiryDate = Carbon::parse('2025-01-01 12:00:00')->addDays($addYears * 365);
        $actualExpiryDate = Carbon::parse($this->user->rolechangedate);

        // This verifies addYears=2 was used, not the default addyears=1
        $this->assertEquals(
            $expectedExpiryDate->toDateString(),
            $actualExpiryDate->toDateString(),
            "When upgrading to Supporter with addYears={$addYears}, expected {$expectedExpiryDate->toDateString()}, got {$actualExpiryDate->toDateString()}. The addYears parameter may not have been applied."
        );

        Carbon::setTestNow();
    }

    /**
     * Test that when same role is applied with addYears, the expiry should be extended from current expiry.
     * This catches the bug where addYears is not applied when role doesn't change.
     */
    public function test_add_years_applied_when_same_role_without_stacking(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00'));

        // Set up user with Supporter role expiring in 6 months
        $existingExpiryDate = Carbon::parse('2025-01-01 12:00:00')->addMonths(6); // 2025-07-01

        $this->user->update([
            'roles_id' => $this->supporterRole->id,
            'rolechangedate' => $existingExpiryDate,
        ]);
        $this->user->syncRoles([$this->supporterRole->name]);
        $this->user->refresh();

        $addYears = 2;

        // Apply same role with stacking disabled but with addYears=2
        // This simulates a user renewing their Supporter subscription for 2 more years
        $result = User::updateUserRole(
            uid: $this->user->id,
            role: 'Supporter',
            applyPromotions: false,
            stackRole: false,
            addYears: $addYears
        );

        $this->assertTrue($result, 'updateUserRole should return true');

        $this->user->refresh();

        // EXPECTED: addYears should extend from current expiry date
        // Current expiry: 2025-07-01 + 2 years (730 days) = 2027-07-01
        $expectedExpiryDate = $existingExpiryDate->copy()->addDays($addYears * 365);
        $actualExpiryDate = Carbon::parse($this->user->rolechangedate);

        // This assertion will FAIL if addYears is not applied when role doesn't change
        $this->assertEquals(
            $expectedExpiryDate->toDateString(),
            $actualExpiryDate->toDateString(),
            "BUG: addYears={$addYears} was not applied when same role is set. Expected {$expectedExpiryDate->toDateString()} (current expiry + 2 years), got {$actualExpiryDate->toDateString()}. The rolechangedate should be extended from current expiry."
        );

        Carbon::setTestNow();
    }

    /**
     * Test that addYears=2 produces different result than addYears=1.
     * This is a regression test to ensure the addYears parameter is actually being used.
     */
    public function test_add_years_parameter_makes_a_difference(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00'));
        // First user with 1 year
        $user1 = $this->createTestUser($this->userRole->id);
        $user1->assignRole($this->userRole);
        User::updateUserRole(
            uid: $user1->id,
            role: 'Supporter',
            applyPromotions: false,
            stackRole: false,
            addYears: 1
        );
        $user1->refresh();
        // Second user with 2 years
        $user2 = $this->createTestUser($this->userRole->id);
        $user2->assignRole($this->userRole);
        User::updateUserRole(
            uid: $user2->id,
            role: 'Supporter',
            applyPromotions: false,
            stackRole: false,
            addYears: 2
        );
        $user2->refresh();
        $expiryDate1 = Carbon::parse($user1->rolechangedate);
        $expiryDate2 = Carbon::parse($user2->rolechangedate);
        // The difference should be approximately 365 days (1 year)
        $differenceInDays = $expiryDate1->diffInDays($expiryDate2);
        $this->assertEquals(
            365,
            $differenceInDays,
            "Expected 365 days difference between 1-year and 2-year subscription, but got {$differenceInDays} days. The addYears parameter is not being applied correctly."
        );
        Carbon::setTestNow();
    }

    /**
     * Test BtcPay webhook flow simulation - parsing item_description and applying addYears.
     * This tests the exact flow from BtcPaymentController.
     */
    public function test_btcpay_item_description_parsing_extracts_add_years(): void
    {
        $testCases = [
            ['item_description' => 'Supporter 1', 'expected_role' => 'Supporter', 'expected_years' => 1],
            ['item_description' => 'Supporter 2', 'expected_role' => 'Supporter', 'expected_years' => 2],
            ['item_description' => 'Supporter 3', 'expected_role' => 'Supporter', 'expected_years' => 3],
            ['item_description' => 'Admin ++ 2', 'expected_role' => 'Admin ++', 'expected_years' => 2],
            ['item_description' => 'Friend 1', 'expected_role' => 'Friend', 'expected_years' => 1],
        ];
        foreach ($testCases as $testCase) {
            $itemDescription = $testCase['item_description'];
            // Use the same regex pattern from BtcPaymentController
            if (preg_match('/(?P<role>\w+(\s\+\+)?)[\s]+(?P<addYears>\d+)/i', $itemDescription, $matches)) {
                $roleName = $matches['role'];
                $addYears = (int) $matches['addYears'];
            } else {
                $roleName = $itemDescription;
                $addYears = null;
            }
            $this->assertEquals(
                $testCase['expected_role'],
                $roleName,
                "Failed to extract role name from '{$itemDescription}'"
            );
            $this->assertEquals(
                $testCase['expected_years'],
                $addYears,
                "Failed to extract addYears from '{$itemDescription}'"
            );
        }
    }

    /**
     * Test full BtcPay simulation - from item description to role update.
     * This simulates what happens when a user pays for "Supporter 2" subscription.
     */
    public function test_btcpay_supporter_2_year_upgrade_simulation(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00'));
        // Simulate the item_description from a BTCPay order
        $itemDescription = 'Supporter 2';
        // Extract role name and addYears using the same regex from BtcPaymentController
        if (preg_match('/(?P<role>\w+(\s\+\+)?)[\s]+(?P<addYears>\d+)/i', $itemDescription, $matches)) {
            $roleName = $matches['role'];
            $addYears = (int) $matches['addYears'];
        } else {
            $roleName = $itemDescription;
            $addYears = null;
        }
        // Ensure we extracted the correct values
        $this->assertEquals('Supporter', $roleName);
        $this->assertEquals(2, $addYears);
        // Now apply the role update
        $result = User::updateUserRole($this->user->id, $roleName, addYears: $addYears);
        $this->assertTrue($result, 'updateUserRole should succeed');
        $this->user->refresh();
        // Verify the user has the correct expiry date (2 years from now)
        $expectedExpiryDate = Carbon::parse('2025-01-01 12:00:00')->addDays(2 * 365);
        $actualExpiryDate = Carbon::parse($this->user->rolechangedate);
        $this->assertEquals(
            $expectedExpiryDate->toDateString(),
            $actualExpiryDate->toDateString(),
            "BtcPay 'Supporter 2' order should result in 2-year subscription. Expected {$expectedExpiryDate->toDateString()}, got {$actualExpiryDate->toDateString()}"
        );
        Carbon::setTestNow();
    }

    /**
     * Test that when a user has a role stacking and the expiry date is extended,
     * subsequent role stackings use the new extended expiry date.
     *
     * Scenario:
     * 1. User has Supporter role expiring on 2025-07-01
     * 2. Admin extends the user's expiry to 2025-12-01
     * 3. User purchases another Supporter subscription (stacking)
     * 4. The stacked role should start from 2025-12-01, NOT 2025-07-01
     */
    public function test_role_stacking_uses_updated_expiry_when_extended(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00'));

        // Step 1: User has Supporter role expiring in 6 months (2025-07-01)
        $originalExpiryDate = Carbon::parse('2025-07-01 12:00:00');

        $this->user->update([
            'roles_id' => $this->supporterRole->id,
            'rolechangedate' => $originalExpiryDate,
        ]);
        $this->user->syncRoles([$this->supporterRole->name]);
        $this->user->refresh();

        // Step 2: Admin extends the expiry to 2025-12-01
        $extendedExpiryDate = Carbon::parse('2025-12-01 12:00:00');
        $this->user->update([
            'rolechangedate' => $extendedExpiryDate,
        ]);
        $this->user->refresh();

        // Verify the extended expiry is set
        $this->assertEquals(
            $extendedExpiryDate->toDateString(),
            Carbon::parse($this->user->rolechangedate)->toDateString(),
            'Expiry date should be extended to 2025-12-01'
        );

        $addYears = 1;

        // Step 3: User purchases another Supporter 1 year subscription (stacking)
        // The originalExpiryBeforeEdits simulates what the controller would pass
        // (the expiry before the admin extended it)
        $result = User::updateUserRole(
            uid: $this->user->id,
            role: 'Supporter',
            applyPromotions: false,
            stackRole: true,
            changedBy: null,
            originalExpiryBeforeEdits: $originalExpiryDate->toDateTimeString(), // Old value
            addYears: $addYears
        );

        $this->assertTrue($result, 'updateUserRole should return true');

        $this->user->refresh();

        // Step 4: The stacked role should use the EXTENDED expiry date (2025-12-01)
        // NOT the original expiry date (2025-07-01)
        $this->assertNotNull($this->user->pending_role_start_date, 'Pending role start date should be set');

        $pendingStartDate = Carbon::parse($this->user->pending_role_start_date);

        // The pending role should start from the extended expiry (2025-12-01),
        // not the original expiry (2025-07-01)
        $this->assertEquals(
            $extendedExpiryDate->toDateString(),
            $pendingStartDate->toDateString(),
            'BUG: Role stacking should use the extended expiry date (2025-12-01), not the original (2025-07-01). '.
            "The pending_role_start_date was {$pendingStartDate->toDateString()}."
        );

        Carbon::setTestNow();
    }

    /**
     * Test that role stacking correctly handles the case where currentExpiryDate is newer than oldExpiryDate.
     * This tests the fix where the stacking start date should be the most recent future date.
     */
    public function test_role_stacking_prefers_newer_expiry_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00'));

        // Create another paid role for testing
        $premiumRole = Role::firstOrCreate(
            ['name' => 'Premium'],
            [
                'guard_name' => 'web',
                'addyears' => 2,
                'apirequests' => 200,
                'downloadrequests' => 100,
                'defaultinvites' => 10,
                'isdefault' => 0,
                'donation' => 25,
                'canpreview' => 1,
            ]
        );

        // User has Supporter role with expiry date that was extended
        $currentExpiryDate = Carbon::parse('2026-01-01 12:00:00'); // Extended date

        $this->user->update([
            'roles_id' => $this->supporterRole->id,
            'rolechangedate' => $currentExpiryDate,
        ]);
        $this->user->syncRoles([$this->supporterRole->name]);
        $this->user->refresh();

        // Simulate that the original expiry was earlier (before extension)
        $oldExpiryDate = Carbon::parse('2025-06-01 12:00:00');

        $addYears = 2;

        // Stack a Premium role upgrade
        $result = User::updateUserRole(
            uid: $this->user->id,
            role: 'Premium',
            applyPromotions: false,
            stackRole: true,
            changedBy: null,
            originalExpiryBeforeEdits: $oldExpiryDate->toDateTimeString(),
            addYears: $addYears
        );

        $this->assertTrue($result, 'updateUserRole should return true');

        $this->user->refresh();

        // Verify the pending role is set
        $this->assertEquals($premiumRole->id, $this->user->pending_roles_id, 'Pending role should be Premium');
        $this->assertNotNull($this->user->pending_role_start_date, 'Pending role start date should be set');

        $pendingStartDate = Carbon::parse($this->user->pending_role_start_date);

        // The stacking should use the NEWER (current) expiry date: 2026-01-01
        // Not the older expiry date: 2025-06-01
        $this->assertEquals(
            $currentExpiryDate->toDateString(),
            $pendingStartDate->toDateString(),
            'Role stacking should use the newer expiry date (2026-01-01) when currentExpiryDate > oldExpiryDate. '.
            "Got {$pendingStartDate->toDateString()} instead."
        );

        Carbon::setTestNow();
    }
}
