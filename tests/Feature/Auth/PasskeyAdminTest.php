<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Middleware\Google2FAMiddleware;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PasskeyAdminTest extends TestCase
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

    public function test_non_admin_cannot_delete_another_users_passkey(): void
    {
        $regularUser = $this->createUserWithRole('User');
        $targetUser = $this->createUserWithRole('User');
        $passkeyId = $this->createPasskey($targetUser->id);

        $response = $this->actingAs($regularUser)->delete(route('admin.user-passkey.destroy', ['passkey' => $passkeyId]));

        $response->assertForbidden();
        $this->assertDatabaseHas('passkeys', ['id' => $passkeyId]);
    }

    public function test_admin_can_delete_single_passkey_for_another_user(): void
    {
        $admin = $this->createUserWithRole('Admin');
        $targetUser = $this->createUserWithRole('User');
        $passkeyId = $this->createPasskey($targetUser->id, 'Work Laptop');

        $response = $this->actingAs($admin)->delete(route('admin.user-passkey.destroy', ['passkey' => $passkeyId]));

        $response->assertRedirect('admin/user-edit?id='.$targetUser->id);
        $this->assertDatabaseMissing('passkeys', ['id' => $passkeyId]);
    }

    public function test_admin_wipe_requires_wipe_confirmation_text(): void
    {
        $admin = $this->createUserWithRole('Admin');
        $targetUser = $this->createUserWithRole('User');
        $passkeyId = $this->createPasskey($targetUser->id);

        $response = $this->actingAs($admin)->post(route('admin.user-passkeys.wipe'), [
            'user_id' => $targetUser->id,
            'confirmation' => 'NOPE',
        ]);

        $response->assertSessionHasErrors('confirmation');
        $this->assertDatabaseHas('passkeys', ['id' => $passkeyId]);
    }

    public function test_admin_can_wipe_all_passkeys_for_target_user_only(): void
    {
        $admin = $this->createUserWithRole('Admin');
        $targetUser = $this->createUserWithRole('User');
        $otherUser = $this->createUserWithRole('User');
        $targetPasskeyOne = $this->createPasskey($targetUser->id, 'Target A');
        $targetPasskeyTwo = $this->createPasskey($targetUser->id, 'Target B');
        $otherPasskey = $this->createPasskey($otherUser->id, 'Other');

        $response = $this->actingAs($admin)->post(route('admin.user-passkeys.wipe'), [
            'user_id' => $targetUser->id,
            'confirmation' => 'WIPE',
        ]);

        $response->assertRedirect('admin/user-edit?id='.$targetUser->id);
        $this->assertDatabaseMissing('passkeys', ['id' => $targetPasskeyOne]);
        $this->assertDatabaseMissing('passkeys', ['id' => $targetPasskeyTwo]);
        $this->assertDatabaseHas('passkeys', ['id' => $otherPasskey]);
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
            $table->string('username');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('roles_id')->default(1);
            $table->integer('rate_limit')->default(60);
            $table->string('api_token')->nullable();
            $table->boolean('verified')->default(true);
            $table->boolean('can_post')->default(true);
            $table->timestamp('email_verified_at')->nullable();
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

        Schema::create('passkeys', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('authenticatable_id');
            $table->text('name');
            $table->text('credential_id');
            $table->json('data');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
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

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName, 'guard_name' => 'web'],
            ['rate_limit' => 60, 'isdefault' => $roleName === 'User', 'defaultinvites' => 1]
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

    private function createPasskey(int $userId, string $name = 'Key'): int
    {
        return (int) DB::table('passkeys')->insertGetId([
            'authenticatable_id' => $userId,
            'name' => $name,
            'credential_id' => 'credential-'.Str::random(8),
            'data' => json_encode(['origin' => 'tests'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
