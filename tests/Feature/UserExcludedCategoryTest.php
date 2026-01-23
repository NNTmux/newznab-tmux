<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Models\UserExcludedCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class UserExcludedCategoryTest extends TestCase
{
    private User $user;

    private Role $userRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Use in-memory SQLite database for test isolation
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        DB::reconnect();

        // Create minimal tables needed for testing
        $this->createTestTables();

        // Create the basic User role with all view permissions
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

        // Create view permissions
        $permissions = [
            'view console', 'view movies', 'view audio', 'view pc',
            'view tv', 'view adult', 'view books', 'view other',
        ];
        foreach ($permissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }
        $this->userRole->syncPermissions($permissions);

        // Create a test user
        $this->user = $this->createTestUser($this->userRole->id);
        $this->user->assignRole($this->userRole);

        // Give user direct permissions
        foreach ($permissions as $permName) {
            $this->user->givePermissionTo($permName);
        }
    }

    protected function tearDown(): void
    {
        DB::disconnect();
        parent::tearDown();
    }

    private function createTestTables(): void
    {
        // Users table (complete schema like RoleUpgradeTest)
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

        // Roles table
        DB::statement('CREATE TABLE IF NOT EXISTS roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            guard_name VARCHAR(255) NOT NULL DEFAULT "web",
            addyears INTEGER DEFAULT 0,
            apirequests INTEGER DEFAULT 10,
            downloadrequests INTEGER DEFAULT 5,
            defaultinvites INTEGER DEFAULT 0,
            isdefault INTEGER DEFAULT 0,
            donation INTEGER DEFAULT 0,
            canpreview INTEGER DEFAULT 0,
            created_at DATETIME,
            updated_at DATETIME
        )');

        // Permissions table
        DB::statement('CREATE TABLE IF NOT EXISTS permissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            guard_name VARCHAR(255) NOT NULL DEFAULT "web",
            created_at DATETIME,
            updated_at DATETIME
        )');

        // Model has permissions
        DB::statement('CREATE TABLE IF NOT EXISTS model_has_permissions (
            permission_id INTEGER NOT NULL,
            model_type VARCHAR(255) NOT NULL,
            model_id INTEGER NOT NULL,
            PRIMARY KEY (permission_id, model_id, model_type)
        )');

        // Model has roles
        DB::statement('CREATE TABLE IF NOT EXISTS model_has_roles (
            role_id INTEGER NOT NULL,
            model_type VARCHAR(255) NOT NULL,
            model_id INTEGER NOT NULL,
            PRIMARY KEY (role_id, model_id, model_type)
        )');

        // Role has permissions
        DB::statement('CREATE TABLE IF NOT EXISTS role_has_permissions (
            permission_id INTEGER NOT NULL,
            role_id INTEGER NOT NULL,
            PRIMARY KEY (permission_id, role_id)
        )');

        // Root categories table
        DB::statement('CREATE TABLE IF NOT EXISTS root_categories (
            id INTEGER PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            status INTEGER DEFAULT 1,
            disablepreview INTEGER DEFAULT 0,
            created_at DATETIME,
            updated_at DATETIME
        )');

        // Categories table
        DB::statement('CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            root_categories_id INTEGER NOT NULL,
            status INTEGER DEFAULT 1,
            description VARCHAR(255),
            disablepreview INTEGER DEFAULT 0,
            minsizetoformrelease INTEGER DEFAULT 0,
            maxsizetoformrelease INTEGER DEFAULT 0
        )');

        // User excluded categories table
        DB::statement('CREATE TABLE IF NOT EXISTS user_excluded_categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            users_id INTEGER NOT NULL,
            categories_id INTEGER NOT NULL,
            created_at DATETIME,
            updated_at DATETIME,
            UNIQUE(users_id, categories_id)
        )');

        // Insert test root categories
        DB::table('root_categories')->insert([
            ['id' => 2000, 'title' => 'Movies', 'status' => 1],
            ['id' => 5000, 'title' => 'TV', 'status' => 1],
        ]);

        // Insert test subcategories
        DB::table('categories')->insert([
            ['id' => 2030, 'title' => 'SD', 'root_categories_id' => 2000, 'status' => 1],
            ['id' => 2040, 'title' => 'HD', 'root_categories_id' => 2000, 'status' => 1],
            ['id' => 2070, 'title' => 'DVD', 'root_categories_id' => 2000, 'status' => 1],
            ['id' => 5030, 'title' => 'SD', 'root_categories_id' => 5000, 'status' => 1],
            ['id' => 5040, 'title' => 'HD', 'root_categories_id' => 5000, 'status' => 1],
        ]);
    }

    private function createTestUser(int $roleId): User
    {
        $username = 'testuser_'.Str::random(5);
        $email = 'test_'.Str::random(5).'@example.com';
        $apiToken = Str::random(64);

        // Insert directly to bypass observers
        DB::table('users')->insert([
            'username' => $username,
            'email' => $email,
            'password' => bcrypt('password'),
            'api_token' => $apiToken,
            'roles_id' => $roleId,
            'verified' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::where('email', $email)->first();
    }

    public function test_user_can_exclude_subcategories(): void
    {
        // Exclude Movies->DVD category
        $this->user->syncExcludedCategories([2070]);

        $exclusions = $this->user->excludedCategories()->pluck('categories_id')->toArray();

        $this->assertContains(2070, $exclusions);
        $this->assertCount(1, $exclusions);
    }

    public function test_user_can_exclude_multiple_subcategories(): void
    {
        // Exclude Movies->DVD and Movies->SD
        $this->user->syncExcludedCategories([2070, 2030]);

        $exclusions = $this->user->excludedCategories()->pluck('categories_id')->toArray();

        $this->assertContains(2070, $exclusions);
        $this->assertContains(2030, $exclusions);
        $this->assertCount(2, $exclusions);
    }

    public function test_sync_excluded_categories_replaces_existing(): void
    {
        // First set DVD
        $this->user->syncExcludedCategories([2070]);

        // Then sync to HD only
        $this->user->syncExcludedCategories([2040]);

        $exclusions = $this->user->excludedCategories()->pluck('categories_id')->toArray();

        $this->assertNotContains(2070, $exclusions);
        $this->assertContains(2040, $exclusions);
        $this->assertCount(1, $exclusions);
    }

    public function test_get_category_exclusion_by_id_includes_user_exclusions(): void
    {
        // Exclude Movies->DVD subcategory
        $this->user->syncExcludedCategories([2070]);

        $exclusions = User::getCategoryExclusionById($this->user->id);

        $this->assertContains(2070, $exclusions);
    }

    public function test_clearing_exclusions_works(): void
    {
        // First add exclusions
        $this->user->syncExcludedCategories([2070, 2030]);

        // Then clear them
        $this->user->syncExcludedCategories([]);

        $exclusions = $this->user->excludedCategories()->pluck('categories_id')->toArray();

        $this->assertEmpty($exclusions);
    }

    public function test_excluded_categories_relationship(): void
    {
        UserExcludedCategory::create([
            'users_id' => $this->user->id,
            'categories_id' => 2070,
        ]);

        $this->assertCount(1, $this->user->excludedCategories);
        $this->assertEquals(2070, $this->user->excludedCategories->first()->categories_id);
    }
}
