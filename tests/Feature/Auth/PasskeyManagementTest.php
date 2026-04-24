<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Middleware\Google2FAMiddleware;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Models\Passkey;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PasskeyManagementTest extends TestCase
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

        config()->set('passkeys.actions.generate_passkey_register_options', FakeGeneratePasskeyRegisterOptionsAction::class);
        config()->set('passkeys.actions.store_passkey', FakeStorePasskeyAction::class);
    }

    public function test_verified_user_can_generate_options_store_and_delete_a_passkey(): void
    {
        $user = $this->createUser('passkey-manage@example.test');

        $optionsResponse = $this
            ->actingAs($user)
            ->postJson(route('passkeys.register_options'), ['name' => 'MacBook']);
        $optionsResponse->assertOk()->assertJsonPath('ok', true);

        $storeResponse = $this
            ->actingAs($user)
            ->withSession([
                'passkey-registration-options' => '{}',
            ])
            ->postJson(route('passkeys.store'), [
                'name' => 'MacBook',
                'passkey' => json_encode(['id' => 'credential-1'], JSON_THROW_ON_ERROR),
            ]);

        $storeResponse->assertOk()->assertJsonPath('ok', true);
        $passkeyId = (int) $storeResponse->json('passkey.id');
        $this->assertDatabaseHas('passkeys', ['id' => $passkeyId, 'authenticatable_id' => $user->id]);

        $deleteResponse = $this
            ->actingAs($user)
            ->deleteJson(route('passkeys.destroy', ['passkey' => $passkeyId]));

        $deleteResponse->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseMissing('passkeys', ['id' => $passkeyId]);
    }

    public function test_guest_cannot_hit_passkey_management_routes(): void
    {
        $this->post(route('passkeys.register_options'), ['name' => 'Guest key'])->assertRedirect(route('login'));
        $this->post(route('passkeys.store'), ['name' => 'Guest key', 'passkey' => '{}'])->assertRedirect(route('login'));
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
    }

    private function createUser(string $email): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'User', 'guard_name' => 'web'],
            ['rate_limit' => 60, 'isdefault' => true, 'defaultinvites' => 1]
        );

        $user = User::query()->create([
            'username' => 'user_'.md5($email),
            'email' => $email,
            'password' => bcrypt('password'),
            'roles_id' => $role->id,
            'rate_limit' => 60,
            'api_token' => md5($email),
            'verified' => true,
            'email_verified_at' => now(),
            'lastlogin' => now(),
        ]);
        $user->assignRole($role);

        return $user->fresh();
    }
}

class FakeGeneratePasskeyRegisterOptionsAction extends GeneratePasskeyRegisterOptionsAction
{
    public function execute(HasPasskeys $authenticatable, bool $asJson = true): string
    {
        return json_encode([
            'challenge' => 'test-challenge',
            'rp' => ['name' => 'NNTmux'],
            'user' => ['id' => (string) $authenticatable->id],
        ], JSON_THROW_ON_ERROR);
    }
}

class FakeStorePasskeyAction extends StorePasskeyAction
{
    public function execute(
        HasPasskeys $authenticatable,
        string $passkeyJson,
        string $passkeyOptionsJson,
        string $hostName,
        array $additionalProperties = []
    ): Passkey {
        DB::table('passkeys')->insertGetId([
            'authenticatable_id' => $authenticatable->id,
            'name' => $additionalProperties['name'] ?? 'Unnamed',
            'credential_id' => 'credential-'.md5($passkeyJson),
            'data' => json_encode(['host' => $hostName], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Passkey::query()->where('authenticatable_id', $authenticatable->id)->latest('id')->firstOrFail();
    }
}
