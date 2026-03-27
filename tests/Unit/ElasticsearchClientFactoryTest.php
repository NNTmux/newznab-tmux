<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Search\Support\ElasticsearchClientFactory;
use Tests\TestCase;

class ElasticsearchClientFactoryTest extends TestCase
{
    public function test_build_hosts_converts_structured_config_to_urls(): void
    {
        $hosts = ElasticsearchClientFactory::buildHosts([
            [
                'host' => 'search.example.test',
                'port' => 9243,
                'scheme' => 'https',
                'user' => 'elastic',
                'pass' => 'secret value',
            ],
            'http://fallback.example.test:9200',
        ]);

        $this->assertSame([
            'https://elastic:secret%20value@search.example.test:9243',
            'http://fallback.example.test:9200',
        ], $hosts);
    }

    public function test_build_http_client_options_uses_supported_timeout_settings(): void
    {
        $options = ElasticsearchClientFactory::buildHttpClientOptions([
            'timeout' => 12,
            'connect_timeout' => 4.5,
        ]);

        $this->assertSame([
            'timeout' => 12.0,
            'connect_timeout' => 4.5,
        ], $options);
    }
}
