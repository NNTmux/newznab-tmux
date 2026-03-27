<?php

declare(strict_types=1);

namespace App\Services\Search\Support;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch as ElasticsearchResponse;
use Throwable;

final class ElasticsearchResponseHelper
{
    /**
     * @param  array<string, mixed>|ElasticsearchResponse  $response
     * @return array<string, mixed>
     */
    public static function asArray(array|ElasticsearchResponse $response): array
    {
        return is_array($response) ? $response : $response->asArray();
    }

    /**
     * Execute a HEAD-style Elasticsearch call without throwing on 404.
     *
     * @param  callable(Client): ElasticsearchResponse  $callback
     */
    public static function boolResponse(Client $client, callable $callback): bool
    {
        $previousSetting = $client->getResponseException();
        $client->setResponseException(false);

        try {
            return $callback($client)->asBool();
        } finally {
            $client->setResponseException($previousSetting);
        }
    }

    public static function isNotFound(Throwable $throwable): bool
    {
        return $throwable instanceof ClientResponseException && $throwable->getCode() === 404;
    }
}
