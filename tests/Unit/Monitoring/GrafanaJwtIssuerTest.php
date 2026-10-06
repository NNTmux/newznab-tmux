<?php

declare(strict_types=1);

namespace Tests\Unit\Monitoring;

use App\Models\User;
use App\Services\Monitoring\GrafanaJwtIssuer;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class GrafanaJwtIssuerTest extends TestCase
{
    private string $keyDirectory;

    private string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->keyDirectory = sys_get_temp_dir().'/nntmux-jwt-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->keyDirectory);

        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $private);
        File::put($this->keyDirectory.'/grafana-jwt.key', $private);
        $this->publicKey = openssl_pkey_get_details($key)['key'];

        config([
            'monitoring.grafana.jwt.private_key_path' => $this->keyDirectory.'/grafana-jwt.key',
            'monitoring.grafana.jwt.ttl' => 600,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->keyDirectory);

        parent::tearDown();
    }

    public function test_issues_an_rs256_token_grafana_can_verify(): void
    {
        $user = new User(['username' => 'admin_user', 'email' => 'admin@example.test']);

        $issued = app(GrafanaJwtIssuer::class)->issueFor($user);

        [$header, $payload, $signature] = explode('.', $issued['token']);
        $this->assertSame(1, openssl_verify("{$header}.{$payload}", $this->base64UrlDecode($signature), $this->publicKey, OPENSSL_ALGO_SHA256));
        $this->assertSame(['alg' => 'RS256', 'typ' => 'JWT'], json_decode($this->base64UrlDecode($header), true));

        $claims = json_decode($this->base64UrlDecode($payload), true);
        $this->assertSame('admin_user', $claims['sub']);
        $this->assertSame('admin@example.test', $claims['email']);
        $this->assertSame('Viewer', $claims['role']);
        $this->assertSame('nntmux', $claims['iss']);
        $this->assertSame('grafana', $claims['aud']);
        $this->assertSame($claims['iat'] + 600, $claims['exp']);
        $this->assertSame($claims['exp'], $issued['expires_at']);
        $this->assertSame(600, $issued['ttl']);
    }

    public function test_reports_a_missing_key_as_unusable(): void
    {
        config(['monitoring.grafana.jwt.private_key_path' => $this->keyDirectory.'/missing.key']);
        $issuer = app(GrafanaJwtIssuer::class);

        $this->assertFalse($issuer->hasUsableKey());

        $this->expectException(RuntimeException::class);
        $issuer->issueFor(new User(['username' => 'admin_user', 'email' => 'admin@example.test']));
    }

    private function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
