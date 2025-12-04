<?php
namespace Tests\Feature;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
final class RoleUpgradeTest extends TestCase
{
    use RefreshDatabase;
    private User $user;
    private Role $userRole;
    private Role $supporterRole;
    protected function setUp(): void
    {
        parent::setUp();
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
    /**
     * Helper to create a test user without factory.
     */
    private function createTestUser(int $roleId, ?string $roleChangeDate = null): User
    {
        return User::create([
            'username' => 'testuser_'.Str::random(8),
            'email' => Str::random(8).'@test.com',
            'password' => bcrypt('password'),
            'roles_id' => $roleId,
            'rolechangedate' => $roleChangeDate,
            'api_token' => md5(Str::random(32)),
        ]);
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
                "Expiry date should NOT be just 1 year from now. The addYears=2 parameter should have been applied."
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
}
