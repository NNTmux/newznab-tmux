<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Middleware\Google2FAMiddleware;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'session.driver' => 'array',
        ]);

        DB::purge();
        DB::reconnect();

        $this->createSchema();
        $this->seedSettings();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withoutMiddleware(Google2FAMiddleware::class);
    }

    public function test_admin_can_create_user_with_string_role_id(): void
    {
        $admin = $this->createUserWithRole('Admin', false);
        $userRole = Role::query()->firstOrCreate(
            ['name' => 'User', 'guard_name' => 'web'],
            ['rate_limit' => 60, 'isdefault' => true, 'defaultinvites' => 3]
        );

        $response = $this->actingAs($admin)->post(route('admin.user-edit'), [
            'action' => 'submit',
            'username' => 'new_user',
            'password' => 'password',
            'email' => 'new-user@example.test',
            'role' => (string) $userRole->id,
            'notes' => 'created from test',
        ]);

        $response->assertRedirect('admin/user-list');
        $this->assertDatabaseHas('users', [
            'username' => 'new_user',
            'email' => 'new-user@example.test',
            'roles_id' => $userRole->id,
            'invites' => 3,
            'notes' => 'created from test',
        ]);
    }

    public function test_admin_create_user_rejects_unknown_role_id(): void
    {
        $admin = $this->createUserWithRole('Admin', false);

        $response = $this->from('admin/user-edit?action=add')
            ->actingAs($admin)
            ->post(route('admin.user-edit'), [
                'action' => 'submit',
                'username' => 'new_user',
                'password' => 'password',
                'email' => 'new-user@example.test',
                'role' => '9999',
                'notes' => '',
            ]);

        $response->assertRedirect('admin/user-edit?action=add');
        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'new-user@example.test']);
    }

    public function test_admin_user_list_can_filter_by_verified_status(): void
    {
        $admin = $this->createUserWithRole('Admin', false);
        $verifiedUser = $this->createUserWithRole('User', true);
        $unverifiedUser = $this->createUserWithRole('User', true);
        $unverifiedUser->forceFill([
            'verified' => false,
            'email_verified_at' => null,
        ])->save();

        $unverifiedResponse = $this->actingAs($admin)->get(route('admin.user-list', ['verified' => '0']));
        $unverifiedResponse->assertOk();
        $unverifiedResponse->assertSee($unverifiedUser->username);
        $unverifiedResponse->assertDontSee($verifiedUser->username);

        $verifiedResponse = $this->actingAs($admin)->get(route('admin.user-list', ['verified' => '1']));
        $verifiedResponse->assertOk();
        $verifiedResponse->assertSee($verifiedUser->username);
        $verifiedResponse->assertDontSee($unverifiedUser->username);
    }

    public function test_admin_can_bulk_soft_delete_selected_users(): void
    {
        $admin = $this->createUserWithRole('Admin', false);
        $firstUser = $this->createUserWithRole('User', true);
        $secondUser = $this->createUserWithRole('User', true);

        $response = $this->actingAs($admin)->post(route('admin.user-list.bulk'), [
            'action' => 'delete',
            'user_ids' => [$firstUser->id, $secondUser->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', '2 user(s) soft-deleted successfully.');
        $this->assertSoftDeleted('users', ['id' => $firstUser->id]);
        $this->assertSoftDeleted('users', ['id' => $secondUser->id]);
    }

    public function test_admin_can_bulk_mark_selected_users_as_verified(): void
    {
        $admin = $this->createUserWithRole('Admin', false);
        $unverifiedUser = $this->createUserWithRole('User', true);
        $alreadyVerifiedUser = $this->createUserWithRole('User', true);
        $unverifiedUser->forceFill([
            'verified' => false,
            'email_verified_at' => null,
            'verification_token' => 'pending-token',
        ])->save();

        $response = $this->actingAs($admin)->post(route('admin.user-list.bulk'), [
            'action' => 'verify',
            'user_ids' => [$unverifiedUser->id, $alreadyVerifiedUser->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', '1 user(s) marked as verified successfully.');

        $unverifiedUser->refresh();
        $this->assertTrue($unverifiedUser->verified);
        $this->assertNotNull($unverifiedUser->email_verified_at);
        $this->assertNull($unverifiedUser->verification_token);
    }

    public function test_bulk_user_action_rejects_role_updates(): void
    {
        $admin = $this->createUserWithRole('Admin', false);
        $user = $this->createUserWithRole('User', true);

        $response = $this->actingAs($admin)->post(route('admin.user-list.bulk'), [
            'action' => 'role',
            'user_ids' => [$user->id],
            'role' => $admin->roles_id,
        ]);

        $response->assertSessionHasErrors('action');
        $this->assertSame($user->roles_id, $user->fresh()->roles_id);
    }

    private function createSchema(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->text('value')->nullable();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name');
            $table->integer('rate_limit')->default(60);
            $table->boolean('isdefault')->default(false);
            $table->unsignedInteger('defaultinvites')->default(0);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('host')->default('');
            $table->unsignedInteger('roles_id')->default(1);
            $table->unsignedInteger('invites')->default(0);
            $table->unsignedInteger('invitedby')->nullable();
            $table->text('notes')->nullable();
            $table->integer('rate_limit')->default(60);
            $table->string('api_token')->nullable();
            $table->boolean('verified')->default(true);
            $table->boolean('can_post')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('verification_token')->nullable();
            $table->timestamp('lastlogin')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedInteger('role_id');
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedInteger('permission_id');
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedInteger('permission_id');
            $table->unsignedInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('user_requests', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->string('request')->default('');
            $table->string('hosthash')->default('');
            $table->timestamp('timestamp')->nullable();
        });

        Schema::create('user_downloads', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->unsignedInteger('releases_id')->default(0);
            $table->string('hosthash')->default('');
            $table->timestamp('timestamp')->nullable();
        });

        Schema::create('content', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->string('url')->nullable();
            $table->text('body')->nullable();
            $table->string('metadescription')->default('');
            $table->string('metakeywords')->default('');
            $table->integer('contenttype')->default(1);
            $table->integer('status')->default(1);
            $table->integer('ordinal')->nullable();
            $table->integer('role')->default(0);
        });

        Schema::create('user_activities', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('username');
            $table->string('activity_type', 50);
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('root_categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->unsignedInteger('root_categories_id')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('user_excluded_categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->unsignedInteger('categories_id');
        });
    }

    private function seedSettings(): void
    {
        DB::table('settings')->insert([
            ['name' => 'title', 'value' => 'NNTmux Test'],
            ['name' => 'home_link', 'value' => '/'],
            ['name' => 'categorizeforeign', 'value' => '0'],
            ['name' => 'catwebdl', 'value' => '0'],
        ]);

        DB::table('root_categories')->insert([
            'id' => 1,
            'title' => 'General',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createUserWithRole(string $roleName, bool $isDefault): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName, 'guard_name' => 'web'],
            ['rate_limit' => 60, 'isdefault' => $isDefault, 'defaultinvites' => 1]
        );

        $user = User::query()->create([
            'username' => strtolower($roleName).'_'.Str::random(8),
            'email' => Str::random(12).'@example.test',
            'password' => bcrypt('password'),
            'roles_id' => $role->id,
            'rate_limit' => 60,
            'api_token' => Str::random(32),
            'verified' => true,
            'email_verified_at' => now(),
            'lastlogin' => now(),
        ]);
        $user->assignRole($role);

        return $user->fresh();
    }
}
