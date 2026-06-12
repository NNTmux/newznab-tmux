<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Mail\ForgottenPassword;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PasswordResetSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'hashing.bcrypt.rounds' => 4,
            'queue.default' => 'sync',
            'session.driver' => 'array',
            'captcha.enabled' => false,
            'captcha.recaptcha.enabled' => false,
            'captcha.turnstile.enabled' => false,
        ]);

        DB::purge();
        DB::reconnect();

        PasswordRule::defaults(fn () => PasswordRule::min(8));

        $this->createSchema();
        $this->seedSettings();
    }

    public function test_password_reset_link_request_sends_tokenized_link_and_clears_legacy_guid(): void
    {
        Mail::fake();
        $user = $this->createUser('forgotten@example.test', ['resetguid' => 'legacy-reset-guid']);

        $response = $this->post(route('forgottenpassword'), [
            'email' => $user->email,
        ]);

        $response
            ->assertRedirect(route('forgottenpassword'))
            ->assertSessionHas('success', 'Password reset email has been sent!');

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);
        $this->assertNull($user->fresh()->resetguid);

        Mail::assertSent(ForgottenPassword::class, function (ForgottenPassword $mail) use ($user): bool {
            $query = parse_url($mail->resetLink, PHP_URL_QUERY);
            parse_str((string) $query, $params);

            return $mail->hasTo($user->email)
                && str_contains($mail->resetLink, '/password/reset/')
                && ($params['email'] ?? null) === $user->email
                && ! array_key_exists('password', $params)
                && ! str_contains($mail->resetLink, 'legacy-reset-guid');
        });
    }

    public function test_legacy_guid_redirects_to_secure_reset_form_and_invalidates_guid(): void
    {
        $user = $this->createUser('legacy@example.test', ['resetguid' => 'legacy-guid']);

        $response = $this->get(route('resetpassword', ['guid' => 'legacy-guid']));

        $response->assertStatus(302);
        $location = (string) $response->headers->get('Location');

        $this->assertStringContainsString('/password/reset/', $location);
        $this->assertStringContainsString('email=legacy%40example.test', $location);
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);
        $this->assertNull($user->fresh()->resetguid);

        $this->get(route('resetpassword', ['guid' => 'legacy-guid']))
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('error', 'Bad reset code provided.');
    }

    public function test_legacy_reset_rejects_api_token_alias_as_reset_guid(): void
    {
        $apiToken = 'api-token-is-not-a-reset-code';
        $user = $this->createUser('api-token-reset@example.test');
        $user->forceFill([
            'api_token' => $apiToken,
            'resetguid' => $apiToken,
        ])->saveQuietly();

        $response = $this->get(route('resetpassword', ['guid' => $apiToken]));

        $response
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('error', 'Bad reset code provided.');

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);
        $this->assertSame($apiToken, $user->fresh()->api_token);
        $this->assertSame($apiToken, $user->fresh()->resetguid);
    }

    public function test_token_password_reset_updates_password_clears_guid_and_fires_event(): void
    {
        Event::fake([PasswordReset::class]);
        $user = $this->createUser('reset@example.test', ['resetguid' => 'legacy-guid']);
        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('new-secure-password', $user->password));
        $this->assertNull($user->resetguid);
        $this->assertNotNull($user->remember_token);
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);
        Event::assertDispatched(
            PasswordReset::class,
            fn (PasswordReset $event): bool => $event->user instanceof User && $event->user->is($user),
        );
    }

    public function test_invalid_token_does_not_change_password_or_clear_legacy_guid(): void
    {
        $user = $this->createUser('invalid-token@example.test', ['resetguid' => 'legacy-guid']);
        $originalPassword = $user->password;

        $response = $this->from(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => 'invalid-token',
                'email' => $user->email,
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ]);

        $response
            ->assertRedirect(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]))
            ->assertSessionHasErrors(['email']);

        $user->refresh();
        $this->assertSame($originalPassword, $user->password);
        $this->assertSame('legacy-guid', $user->resetguid);
    }

    protected function createSchema(): void
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
            $table->string('resetguid')->nullable();
            $table->unsignedInteger('roles_id')->default(1);
            $table->integer('rate_limit')->default(60);
            $table->string('api_token')->nullable();
            $table->boolean('verified')->default(true);
            $table->boolean('can_post')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('lastlogin')->nullable();
            $table->string('host')->nullable();
            $table->rememberToken();
            $table->string('session_token', 60)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
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
    }

    protected function seedSettings(): void
    {
        DB::table('settings')->insert([
            ['name' => 'title', 'value' => 'NNTmux Test'],
            ['name' => 'home_link', 'value' => '/'],
            ['name' => 'categorizeforeign', 'value' => '0'],
            ['name' => 'catwebdl', 'value' => '0'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createUser(string $email, array $overrides = []): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'User', 'guard_name' => 'web'],
            ['rate_limit' => 60, 'isdefault' => true, 'defaultinvites' => 1],
        );

        return User::query()->create(array_merge([
            'username' => 'user_'.md5($email),
            'email' => $email,
            'password' => Hash::make('old-secure-password'),
            'roles_id' => $role->id,
            'rate_limit' => 60,
            'api_token' => md5($email),
            'verified' => true,
            'email_verified_at' => now(),
            'lastlogin' => now(),
        ], $overrides));
    }
}
