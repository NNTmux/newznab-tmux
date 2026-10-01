<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\TurnstileService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TurnstileServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'captcha.turnstile.secret' => 'private-turnstile-secret',
            'nntmux_settings.store_user_ip' => false,
        ]);
        Http::preventStrayRequests();
        Log::spy();
    }

    public function test_successful_verification_sends_the_token_without_logging_it(): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->assertTrue(TurnstileService::verify('private-captcha-token', '203.0.113.10'));

        Http::assertSent(fn (Request $request): bool => $request['secret'] === 'private-turnstile-secret'
            && $request['response'] === 'private-captcha-token'
            && $request['remoteip'] === '203.0.113.10');
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
    }

    #[DataProvider('failedResponses')]
    public function test_failed_verification_logs_only_safe_diagnostics(mixed $body, int $status, array $codes, string $reason): void
    {
        request()->headers->set('CF-Ray', 'visitor-ray');
        Http::fake(['challenges.cloudflare.com/*' => Http::response($body, $status, ['cf-ray' => 'verification-ray'])]);

        $this->assertFalse(TurnstileService::verify('private-captcha-token'));

        Log::shouldHaveReceived('warning')->once()->with('Turnstile verification failed.', [
            'route' => null,
            'cf_ray' => 'visitor-ray',
            'ip' => null,
            'http_status' => $status,
            'error_codes' => $codes,
            'reason' => $reason,
            'siteverify_ray' => 'verification-ray',
        ]);
    }

    public static function failedResponses(): array
    {
        return [
            'expired token' => [['success' => false, 'error-codes' => ['timeout-or-duplicate'], 'token' => 'private-captcha-token'], 200, ['timeout-or-duplicate'], 'rejected'],
            'invalid secret' => [['success' => false, 'error-codes' => ['invalid-input-secret']], 200, ['invalid-input-secret'], 'rejected'],
            'http failure' => ['Service unavailable', 503, [], 'http-error'],
            'http failure with success body' => [['success' => true], 500, [], 'http-error'],
            'invalid json' => ['not-json', 200, [], 'invalid-response'],
            'scalar json' => ['true', 200, [], 'invalid-response'],
            'malformed error codes' => [['success' => false, 'error-codes' => 'unexpected'], 200, [], 'rejected'],
        ];
    }

    public function test_connection_failure_does_not_expose_exception_contents(): void
    {
        Http::fake(fn () => throw new ConnectionException('private-turnstile-secret private-captcha-token'));

        $this->assertFalse(TurnstileService::verify('private-captcha-token'));

        Log::shouldHaveReceived('error')->once()->with('Turnstile verification request failed.', [
            'route' => null,
            'cf_ray' => null,
            'ip' => null,
            'exception_class' => ConnectionException::class,
        ]);
    }

    public function test_missing_secret_is_logged_without_sending_a_request(): void
    {
        config(['captcha.turnstile.secret' => '']);

        $this->assertFalse(TurnstileService::verify('private-captcha-token'));

        Http::assertNothingSent();
        Log::shouldHaveReceived('error')->once()->with('Turnstile verification failed: missing secret.', Mockery::type('array'));
    }

    public function test_failure_logs_respect_the_ip_logging_setting(): void
    {
        config(['nntmux_settings.store_user_ip' => true]);
        request()->server->set('REMOTE_ADDR', '203.0.113.10');
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

        $this->assertFalse(TurnstileService::verify('private-captcha-token'));

        Log::shouldHaveReceived('warning')->once()->with('Turnstile verification failed.', Mockery::on(
            fn (array $context): bool => $context['ip'] === '203.0.113.10'
        ));
    }
}
