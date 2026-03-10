<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Auth\RegisterController;
use App\Models\User;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Mockery\MockInterface;
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
        $this->partialMock(RegisterController::class, function (MockInterface $mock): void {
            $mock->shouldAllowMockingProtectedMethods();
            $mock->shouldReceive('create')
                ->once()
                ->andReturn(new User([
                    'id' => 123,
                    'username' => 'newmember',
                    'email' => 'newmember@example.com',
                    'roles_id' => 1,
                ]));
        });

        $response = $this->post(route('register.post'), [
            'action' => 'submit',
            'username' => 'newmember',
            'email' => 'newmember@example.com',
            'password' => 'ValidPass1!',
            'password_confirmation' => 'ValidPass1!',
            'terms' => 'on',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('message', 'Your account has been created. You will receive an account confirmation email shortly. Please verify your email address before logging in.');
        $response->assertSessionHas('message_type', 'info');
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

        $this->partialMock(RegisterController::class, function (MockInterface $mock): void {
            $mock->shouldAllowMockingProtectedMethods();
            $mock->shouldReceive('create')
                ->once()
                ->andThrow(new \RuntimeException('Simulated registration failure'));
        });

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
}
