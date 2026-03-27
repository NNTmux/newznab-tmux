<?php

declare(strict_types=1);

namespace App\Services\Search\Support;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use GuzzleHttp\Client as GuzzleClient;
use RuntimeException;

final class ElasticsearchClientFactory
{
    /**
     * @param  array<string, mixed>  $config
     */
    public static function make(array $config): Client
    {
        $hosts = self::buildHosts($config['hosts'] ?? []);

        if ($hosts === []) {
            throw new RuntimeException('No Elasticsearch hosts configured');
        }

        $builder = ClientBuilder::create()
            ->setHosts($hosts)
            ->setRetries((int) ($config['retries'] ?? 2));

        $builder->setHttpClient(new GuzzleClient(self::buildHttpClientOptions($config)));

        return $builder->build();
    }

    /**
     * @param  array<int, array<string, mixed>|string>  $configHosts
     * @return list<string>
     */
    public static function buildHosts(array $configHosts): array
    {
        $hosts = [];

        foreach ($configHosts as $host) {
            if (is_string($host) && trim($host) !== '') {
                $hosts[] = $host;

                continue;
            }

            if (! is_array($host)) {
                continue;
            }

            $hostname = trim((string) ($host['host'] ?? ''));
            if ($hostname === '') {
                continue;
            }

            $scheme = trim((string) ($host['scheme'] ?? 'http')) ?: 'http';
            $port = $host['port'] ?? null;
            $portSuffix = $port !== null && $port !== '' ? ':'.(string) $port : '';
            $credentials = '';

            if (! empty($host['user']) && ! empty($host['pass'])) {
                $credentials = rawurlencode((string) $host['user']).':'.rawurlencode((string) $host['pass']).'@';
            }

            $hosts[] = sprintf('%s://%s%s%s', $scheme, $credentials, $hostname, $portSuffix);
        }

        return $hosts;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, int|float|string|bool>
     */
    public static function buildHttpClientOptions(array $config): array
    {
        $options = [];

        $timeout = $config['timeout'] ?? null;
        if (is_numeric($timeout) && (float) $timeout > 0) {
            $options['timeout'] = (float) $timeout;
        }

        $connectTimeout = $config['connect_timeout'] ?? null;
        if (is_numeric($connectTimeout) && (float) $connectTimeout > 0) {
            $options['connect_timeout'] = (float) $connectTimeout;
        }

        return $options;
    }
}
