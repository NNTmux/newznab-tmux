<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\PasswordSecurityController;
use App\Http\Requests\Disable2faPasswordSecurityRequest;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use PHPUnit\Framework\Attributes\Test;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

final class TwoFactorSecurityTest extends TestCase
{
    private const string PASSWORD = 'Existing-password1!';

    private string $secret;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->createSchema();

        $this->secret = (new Google2FA)->generateSecretKey();
        $userId = DB::table('users')->insertGetId([
            'username' => 'secure-user',
            'email' => 'secure@example.test',
            'password' => Hash::make(self::PASSWORD),
            'roles_id' => 1,
        ]);
        DB::table('password_securities')->insert(['user_id' => $userId, 'google2fa_enable' => 1, 'google2fa_secret' => $this->secret]);
        DB::table('trusted_devices')->insert(['user_id' => $userId, 'token_hash' => str_repeat('a', 64), 'expires_at' => now()->addDays(30)]);
        $this->user = User::query()->findOrFail($userId);
        RateLimiter::clear('2fa-otp:'.$userId);
    }

    #[Test]
    public function disabling_active_2fa_with_only_the_password_is_refused(): void
    {
        $response = $this->controller()->disable2fa($this->disableRequest(['current-password' => self::PASSWORD]));

        $this->assertSame('Invalid authentication code. Please try again.', $response->getSession()?->get('error_2fa'));
        $this->assertSame(1, DB::table('password_securities')->count());
    }

    #[Test]
    public function disabling_2fa_with_password_and_current_code_removes_the_secret_and_trusted_devices(): void
    {
        $response = $this->controller()->disable2fa($this->disableRequest([
            'current-password' => self::PASSWORD,
            'one_time_password' => (new Google2FA)->getCurrentOtp($this->secret),
        ]));

        $this->assertSame('2FA is now Disabled.', $response->getSession()?->get('success_2fa'));
        $this->assertSame(0, DB::table('password_securities')->count());
        $this->assertSame(0, DB::table('trusted_devices')->count());
    }

    #[Test]
    public function verification_locks_the_account_after_five_wrong_codes_even_for_the_right_code(): void
    {
        $validCode = (new Google2FA)->getCurrentOtp($this->secret);
        $wrongCode = $validCode === '000000' ? '111111' : '000000';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->controller()->verify2fa($this->verifyRequest($wrongCode));
        }
        $response = $this->controller()->verify2fa($this->verifyRequest($validCode));

        $this->assertStringStartsWith('Too many invalid authentication codes', (string) $response->getSession()?->get('error'));
    }

    #[Test]
    public function the_setup_qr_code_is_hidden_once_2fa_is_enabled(): void
    {
        $request = Request::create('/2fa');
        $request->setUserResolver(fn () => $this->user);

        $view = $this->controller()->show2faForm($request);

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('', $view->getData()['data']['google2fa_url']);
    }

    #[Test]
    public function generating_a_secret_never_replaces_an_existing_one(): void
    {
        $request = Request::create('/generate2faSecret', 'POST');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $this->user);

        $this->controller()->generate2faSecret($request);

        $this->assertSame([$this->secret], DB::table('password_securities')->pluck('google2fa_secret')->all());
    }

    #[Test]
    public function changing_the_password_revokes_trusted_devices(): void
    {
        $this->user->update(['password' => 'Replacement-password2!']);

        $this->assertSame(0, DB::table('trusted_devices')->count());
    }

    private function controller(): PasswordSecurityController
    {
        return new PasswordSecurityController;
    }

    /**
     * @param  array<string, string>  $input
     */
    private function disableRequest(array $input): Disable2faPasswordSecurityRequest
    {
        $request = Disable2faPasswordSecurityRequest::create('/disable2fa', 'POST', $input);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $this->user);

        return $request;
    }

    private function verifyRequest(string $code): Request
    {
        $this->actingAs($this->user);
        $request = Request::create('/2fa/verify', 'POST', ['one_time_password' => $code]);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $this->user);

        return $request;
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username');
            $table->string('email');
            $table->string('password');
            $table->unsignedInteger('roles_id')->default(1);
            $table->string('api_token')->nullable();
            $table->string('session_token')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('password_securities', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->boolean('google2fa_enable')->default(false);
            $table->string('google2fa_secret')->nullable();
            $table->timestamps();
        });
        Schema::create('trusted_devices', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }
}
