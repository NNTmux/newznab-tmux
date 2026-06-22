<?php

declare(strict_types=1);

namespace App\Services\Search\Support;

use Manticoresearch\Client as ManticoreClient;

final class ManticoreClientFactory
{
    /**
     * Build a Manticore PHP client from app config.
     *
     * @param  array<string, mixed>  $config
     */
    public static function make(array $config): ManticoreClient
    {
        return new ManticoreClient(self::clientConfig($config));
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function clientConfig(array $config): array
    {
        $retries = (int) ($config['retries'] ?? 2);
        $hosts = self::normalizeHosts($config['hosts'] ?? '');

        if ($hosts !== []) {
            return [
                'connections' => array_map(
                    static fn (string|array $host): array => self::connectionConfig($config, $host),
                    $hosts,
                ),
                'retries' => max(1, $retries),
            ];
        }

        return self::connectionConfig($config);
    }

    /**
     * Build Guzzle options for raw HTTP calls to Manticore.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public static function guzzleOptions(array $config, array $options = []): array
    {
        $headers = $options['headers'] ?? [];
        unset($options['headers']);

        $token = self::stringOrNull($config['token'] ?? $config['bearer_token'] ?? null);
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer '.$token;
        } else {
            $username = self::stringOrNull($config['username'] ?? null);
            $password = self::stringOrNull($config['password'] ?? null);
            if ($username !== null && $password !== null) {
                $options['auth'] = [$username, $password];
            }
        }

        if ($headers !== []) {
            $options['headers'] = $headers;
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  string|array<string, mixed>|null  $hostOverride
     * @return array<string, mixed>
     */
    private static function connectionConfig(array $config, string|array|null $hostOverride = null): array
    {
        $connection = self::parseHost($config, $hostOverride);
        $scheme = strtolower((string) ($connection['scheme'] ?? $config['scheme'] ?? 'http'));

        if ($scheme === 'https') {
            $connection['transport'] = 'Https';
        }

        foreach (['timeout', 'connect_timeout', 'proxy', 'persistent'] as $key) {
            if (array_key_exists($key, $config)) {
                $connection[$key] = $config[$key];
            }
        }

        $token = self::stringOrNull($config['token'] ?? $config['bearer_token'] ?? null);
        if ($token !== null) {
            $connection['headers'] = self::headersForManticoreClient($config['headers'] ?? [], $token);

            return $connection;
        }

        $username = self::stringOrNull($config['username'] ?? null);
        $password = self::stringOrNull($config['password'] ?? null);
        if ($username !== null && $password !== null) {
            $connection['username'] = $username;
            $connection['password'] = $password;
        }

        if (! empty($config['headers'])) {
            $connection['headers'] = self::headersForManticoreClient($config['headers']);
        }

        return $connection;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  string|array<string, mixed>|null  $hostOverride
     * @return array<string, mixed>
     */
    private static function parseHost(array $config, string|array|null $hostOverride): array
    {
        if (\is_array($hostOverride)) {
            return $hostOverride + [
                'host' => (string) ($config['host'] ?? '127.0.0.1'),
                'port' => (int) ($config['port'] ?? 9308),
            ];
        }

        $host = $hostOverride ?? (string) ($config['host'] ?? '127.0.0.1');
        $defaultPort = (int) ($config['port'] ?? 9308);
        $defaultScheme = (string) ($config['scheme'] ?? 'http');

        $parseTarget = str_contains($host, '://') ? $host : '//'.$host;
        $parts = parse_url($parseTarget);

        if ($parts === false || empty($parts['host'])) {
            return [
                'host' => $host,
                'port' => $defaultPort,
                'scheme' => $defaultScheme,
            ];
        }

        $connection = [
            'host' => $parts['host'],
            'port' => isset($parts['port']) ? (int) $parts['port'] : $defaultPort,
            'scheme' => $parts['scheme'] ?? $defaultScheme,
        ];

        if (! empty($parts['path']) && $parts['path'] !== '/') {
            $connection['path'] = $parts['path'];
        }

        return $connection;
    }

    /**
     * @return list<string|array<string, mixed>>
     */
    private static function normalizeHosts(mixed $hosts): array
    {
        if (\is_array($hosts)) {
            return array_values(array_filter($hosts));
        }

        if (! \is_string($hosts) || trim($hosts) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $hosts))));
    }

    /**
     * @return list<string>
     */
    private static function headersForManticoreClient(mixed $headers, ?string $bearerToken = null): array
    {
        $normalized = [];

        if (\is_array($headers)) {
            foreach ($headers as $key => $value) {
                if (\is_int($key)) {
                    $header = self::stringOrNull($value);
                    if ($header !== null) {
                        $normalized[] = $header;
                    }

                    continue;
                }

                $headerValue = self::stringOrNull($value);
                if ($headerValue !== null) {
                    $normalized[] = $key.': '.$headerValue;
                }
            }
        }

        if ($bearerToken !== null) {
            $normalized[] = 'Authorization: Bearer '.$bearerToken;
        }

        return $normalized;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
