<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Auth\RegisterController;
use App\Models\User;
use App\Services\RegistrationStatusService;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'captcha.enabled' => false,
            'captcha.recaptcha.enabled' => false,
            'captcha.turnstile.enabled' => false,
        ]);

        $this->app->instance(UncompromisedVerifier::class, new class implements UncompromisedVerifier
        {
            public function verify($data): bool
            {
                return true;
            }
        });

        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('settings');

        Schema::create('settings', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->text('value')->nullable();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name');
            $table->unsignedInteger('apirequests')->default(0);
            $table->integer('rate_limit')->default(60);
            $table->unsignedInteger('downloadrequests')->default(0);
            $table->unsignedInteger('defaultinvites')->default(0);
            $table->boolean('isdefault')->default(false);
            $table->integer('donation')->default(0);
            $table->integer('addyears')->default(0);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('roles_id')->default(1);
            $table->string('host')->nullable();
            $table->integer('grabs')->default(0);
            $table->string('api_token');
            $table->integer('invites')->default(0);
            $table->boolean('movieview')->default(true);
            $table->boolean('xxxview')->default(false);
            $table->boolean('musicview')->default(true);
            $table->boolean('consoleview')->default(true);
            $table->boolean('bookview')->default(true);
            $table->boolean('gameview')->default(true);
            $table->integer('rate_limit')->default(60);
            $table->string('notes')->nullable();
            $table->boolean('verified')->default(false);
            $table->boolean('can_post')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('registration_periods', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('is_enabled')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('invitations', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('token')->unique();
            $table->string('email')->nullable();
            $table->unsignedInteger('invited_by');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->unsignedInteger('used_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        DB::table('settings')->insert([
            'name' => 'registerstatus',
            'value' => '0',
        ]);

        DB::table('roles')->insert([
            'id' => 1,
            'name' => 'User',
            'guard_name' => 'web',
            'isdefault' => 1,
            'defaultinvites' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_successful_registration_redirects_to_login_with_confirmation_message(): void
    {
        $this->mockRegisterControllerCreate(new User([
            'id' => 123,
            'username' => 'newmember',
            'email' => 'newmember@example.com',
            'roles_id' => 1,
        ]));

        $response = $this->post(route('register.post'), [
            'action' => 'submit',
            'username' => 'newmember',
            'email' => 'newmember@example.com',
            'password' => 'ValidPass1!',
            'password_confirmation' => 'ValidPass1!',
            'terms' => 'on',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('info', 'Your account has been created. You will receive an account confirmation email shortly. Please verify your email address before logging in.');
    }

    public function test_registration_failure_shows_explanation_for_deactivated_account(): void
    {
        Log::partialMock();

        $registrationLogger = Mockery::mock();
        Log::shouldReceive('channel')
            ->once()
            ->with('registration')
            ->andReturn($registrationLogger);

        $registrationLogger->shouldReceive('warning')
            ->once()
            ->with(
                'Registration attempt failed: deactivated_account',
                Mockery::on(function (array $context): bool {
                    return $context['reason'] === 'deactivated_account'
                        && $context['email'] === 'disabled@example.com'
                        && $context['username'] === 'freshmember'
                        && $context['registration_status'] === 0;
                })
            );

        DB::table('users')->insert([
            'username' => 'oldmember',
            'email' => 'disabled@example.com',
            'password' => 'hashed-password',
            'roles_id' => 1,
            'api_token' => 'existing-token',
            'verified' => 0,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => now(),
        ]);

        $response = $this->from(route('register'))->post(route('register.post'), [
            'action' => 'submit',
            'username' => 'freshmember',
            'email' => 'disabled@example.com',
            'password' => 'ValidPass1!',
            'password_confirmation' => 'ValidPass1!',
            'terms' => 'on',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors([
            'registration' => 'This email address belongs to a deactivated account. Please contact us if you need help reactivating it.',
        ]);
    }

    public function test_registration_exception_is_logged_to_registration_channel(): void
    {
        Log::partialMock();

        $registrationLogger = Mockery::mock();
        Log::shouldReceive('channel')
            ->once()
            ->with('registration')
            ->andReturn($registrationLogger);

        $registrationLogger->shouldReceive('error')
            ->once()
            ->with(
                'Registration attempt failed: unexpected_exception',
                Mockery::on(function (array $context): bool {
                    return $context['reason'] === 'unexpected_exception'
                        && $context['email'] === 'broken@example.com'
                        && $context['username'] === 'brokenmember'
                        && $context['exception'] === \RuntimeException::class
                        && $context['registration_status'] === 0;
                })
            );

        $this->mockRegisterControllerCreate(
            new \RuntimeException('Simulated registration failure')
        );

        $response = $this->from(route('register'))->post(route('register.post'), [
            'action' => 'submit',
            'username' => 'brokenmember',
            'email' => 'broken@example.com',
            'password' => 'ValidPass1!',
            'password_confirmation' => 'ValidPass1!',
            'terms' => 'on',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors([
            'registration' => 'We could not complete your registration right now. Please try again in a few minutes. If the problem continues, contact support.',
        ]);
    }

    public function test_invite_only_registration_with_valid_invitation_succeeds(): void
    {
        DB::table('settings')
            ->where('name', 'registerstatus')
            ->update(['value' => '1']);

        DB::table('invitations')->insert([
            'token' => 'valid-token',
            'email' => 'invitee@example.com',
            'invited_by' => 1,
            'expires_at' => now()->addDay(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->mockRegisterControllerCreate(new User([
            'id' => 321,
            'username' => 'inviteemember',
            'email' => 'invitee@example.com',
            'roles_id' => 1,
        ]));

        $response = $this->post(route('register.post'), [
            'action' => 'submit',
            'token' => 'valid-token',
            'username' => 'inviteemember',
            'email' => 'invitee@example.com',
            'password' => 'ValidPass1!',
            'password_confirmation' => 'ValidPass1!',
            'terms' => 'on',
        ]);

        $response->assertRedirect(route('login'));

        $this->assertNotNull(
            DB::table('invitations')
                ->where('token', 'valid-token')
                ->value('used_at')
        );
    }

    public function test_closed_registration_rejects_even_valid_invitation(): void
    {
        DB::table('settings')
            ->where('name', 'registerstatus')
            ->update(['value' => '2']);

        DB::table('invitations')->insert([
            'token' => 'closed-token',
            'email' => 'invitee@example.com',
            'invited_by' => 1,
            'expires_at' => now()->addDay(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->from(route('register'))->post(route('register.post'), [
            'action' => 'submit',
            'token' => 'closed-token',
            'username' => 'closedmember',
            'email' => 'invitee@example.com',
            'password' => 'ValidPass1!',
            'password_confirmation' => 'ValidPass1!',
            'terms' => 'on',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors([
            'registration' => 'Registrations are currently closed.',
        ]);
    }

    public function test_scheduled_open_period_allows_registration_when_manual_status_is_closed(): void
    {
        DB::table('settings')
            ->where('name', 'registerstatus')
            ->update(['value' => '2']);

        DB::table('registration_periods')->insert([
            'name' => 'Weekend Open',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->mockRegisterControllerCreate(new User([
            'id' => 456,
            'username' => 'scheduledmember',
            'email' => 'scheduled@example.com',
            'roles_id' => 1,
        ]));

        $response = $this->post(route('register.post'), [
            'action' => 'submit',
            'username' => 'scheduledmember',
            'email' => 'scheduled@example.com',
            'password' => 'ValidPass1!',
            'password_confirmation' => 'ValidPass1!',
            'terms' => 'on',
        ]);

        $response->assertRedirect(route('login'));
    }

    private function mockRegisterControllerCreate(User|\Throwable $result): void
    {
        $mock = Mockery::mock(RegisterController::class)->makePartial();

        $mock->shouldAllowMockingProtectedMethods();

        $reflection = new \ReflectionProperty(RegisterController::class, 'registrationStatusService');
        $reflection->setAccessible(true);
        $reflection->setValue($mock, app(RegistrationStatusService::class));

        $expectation = $mock->shouldReceive('create')->once();

        if ($result instanceof \Throwable) {
            $expectation->andThrow($result);
        } else {
            $expectation->andReturn($result);
        }

        $this->app->instance(RegisterController::class, $mock);
    }
}
