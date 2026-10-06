<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Signs short-lived RS256 tokens that Grafana's [auth.jwt] provider accepts.
 *
 * Grafana keeps no session for JWT logins: its frontend re-sends the token
 * from the iframe URL on every API call, so the admin page refreshes the
 * token (and the iframes) before it expires.
 */
class GrafanaJwtIssuer
{
    /**
     * @return array{token: string, expires_at: int, ttl: int}
     */
    public function issueFor(User $user): array
    {
        $ttl = (int) config('monitoring.grafana.jwt.ttl', 900);
        $now = time();
        $expiresAt = $now + $ttl;

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'sub' => $user->username,
            'email' => $user->email,
            'name' => $user->username,
            'role' => (string) config('monitoring.grafana.jwt.role', 'Viewer'),
            'iss' => (string) config('monitoring.grafana.jwt.issuer'),
            'aud' => (string) config('monitoring.grafana.jwt.audience'),
            'iat' => $now,
            // Small allowance for clock drift between PHP and Grafana.
            'nbf' => $now - 30,
            'exp' => $expiresAt,
            'jti' => (string) Str::uuid(),
        ];

        $signingInput = $this->encode($header).'.'.$this->encode($claims);

        if (! openssl_sign($signingInput, $signature, $this->privateKey(), OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign the Grafana token: '.(openssl_error_string() ?: 'unknown OpenSSL error'));
        }

        return [
            'token' => $signingInput.'.'.$this->base64Url($signature),
            'expires_at' => $expiresAt,
            'ttl' => $ttl,
        ];
    }

    public function hasUsableKey(): bool
    {
        $path = (string) config('monitoring.grafana.jwt.private_key_path');

        return $path !== '' && is_file($path) && is_readable($path);
    }

    private function privateKey(): \OpenSSLAsymmetricKey
    {
        $path = (string) config('monitoring.grafana.jwt.private_key_path');

        if (! $this->hasUsableKey()) {
            throw new RuntimeException("Grafana JWT private key is not readable at [{$path}]. Run `php artisan monitoring:install`.");
        }

        $key = openssl_pkey_get_private((string) file_get_contents($path));

        if ($key === false) {
            throw new RuntimeException("Grafana JWT private key at [{$path}] is not a valid PEM private key.");
        }

        return $key;
    }

    /**
     * @param  array<string, int|string>  $segment
     */
    private function encode(array $segment): string
    {
        return $this->base64Url(json_encode($segment, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
